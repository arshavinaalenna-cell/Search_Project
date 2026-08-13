<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua",
    "petugas_gizi",
    "petugas_kia",
    "kader",
    "kepala_puskesmas",
    "dinkes"
]);

date_default_timezone_set("Asia/Jakarta");

$judulHalaman =
    "Riwayat Konsultasi | Sistem Deteksi Stunting";

$roleAktif =
    $_SESSION["role"] ?? "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idBalitaDipilih =
    filter_input(
        INPUT_GET,
        "id_balita",
        FILTER_VALIDATE_INT
    ) ?: 0;

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function amanRiwayatKonsultasi($nilai): string
{
    if ($nilai === null || $nilai === "") {
        return "-";
    }

    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

function bindRiwayatKonsultasi(
    mysqli_stmt $stmt,
    string $tipe,
    array &$parameter
): void {

    if (
        $tipe === ""
        || empty($parameter)
    ) {
        return;
    }

    $argumen = [$tipe];

    foreach ($parameter as &$nilai) {
        $argumen[] = &$nilai;
    }

    call_user_func_array(
        [$stmt, "bind_param"],
        $argumen
    );
}

function formatTanggalKonsultasi(
    ?string $tanggal
): string {

    $tanggal =
        trim((string) $tanggal);

    if (
        $tanggal === ""
        || str_starts_with(
            $tanggal,
            "0000-00-00"
        )
    ) {
        return "-";
    }

    $timestamp =
        strtotime($tanggal);

    if ($timestamp === false) {
        return "-";
    }

    $bulan = [
        1 => "Januari",
        2 => "Februari",
        3 => "Maret",
        4 => "April",
        5 => "Mei",
        6 => "Juni",
        7 => "Juli",
        8 => "Agustus",
        9 => "September",
        10 => "Oktober",
        11 => "November",
        12 => "Desember"
    ];

    return
        date("d", $timestamp)
        . " "
        . (
            $bulan[
                (int) date(
                    "n",
                    $timestamp
                )
            ]
            ?? ""
        )
        . " "
        . date("Y", $timestamp);
}

/*
|--------------------------------------------------------------------------
| Cakupan Puskesmas akun
|--------------------------------------------------------------------------
*/

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

$roleBerbasisPuskesmas =
    in_array(
        $roleAktif,
        [
            "petugas_gizi",
            "petugas_kia",
            "kader",
            "kepala_puskesmas"
        ],
        true
    );

if ($roleBerbasisPuskesmas) {

    $stmtPuskesmas = mysqli_prepare(
        $conn,
        "SELECT
            u.id_puskesmas,
            p.nama_puskesmas
         FROM pengguna AS u
         LEFT JOIN puskesmas AS p
            ON u.id_puskesmas =
                p.id_puskesmas
         WHERE u.id_user = ?
         LIMIT 1"
    );

    if (!$stmtPuskesmas) {
        die(
            "Gagal memeriksa Puskesmas pengguna: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtPuskesmas,
        "i",
        $idUserAktif
    );

    mysqli_stmt_execute(
        $stmtPuskesmas
    );

    $hasilPuskesmas =
        mysqli_stmt_get_result(
            $stmtPuskesmas
        );

    $dataPuskesmas =
        mysqli_fetch_assoc(
            $hasilPuskesmas
        );

    mysqli_stmt_close(
        $stmtPuskesmas
    );

    if (
        !$dataPuskesmas
        || empty(
            $dataPuskesmas[
                "id_puskesmas"
            ]
        )
    ) {
        $puskesmasBelumTerhubung = true;
    } else {
        $idPuskesmasAktif =
            (int) $dataPuskesmas[
                "id_puskesmas"
            ];

        $namaPuskesmasAktif =
            trim(
                (string) (
                    $dataPuskesmas[
                        "nama_puskesmas"
                    ]
                    ?? ""
                )
            );
    }
}

/*
|--------------------------------------------------------------------------
| Filter halaman utama
|--------------------------------------------------------------------------
*/

$cari =
    trim(
        (string) (
            $_GET["cari"]
            ?? ""
        )
    );

$filterBulan =
    filter_input(
        INPUT_GET,
        "bulan",
        FILTER_VALIDATE_INT
    ) ?: 0;

$filterTahun =
    filter_input(
        INPUT_GET,
        "tahun",
        FILTER_VALIDATE_INT
    ) ?: 0;

$filterStatusStunting =
    trim(
        (string) (
            $_GET["status_stunting"]
            ?? ""
        )
    );

$filterUrutan =
    strtolower(
        trim(
            (string) (
                $_GET["urutan"]
                ?? "terbaru"
            )
        )
    );

$filterPuskesmas =
    filter_input(
        INPUT_GET,
        "puskesmas",
        FILTER_VALIDATE_INT
    ) ?: 0;

if (
    $filterBulan < 1
    || $filterBulan > 12
) {
    $filterBulan = 0;
}

if (
    $filterTahun < 2000
    || $filterTahun > 2100
) {
    $filterTahun = 0;
}

$statusStuntingValid = [
    "",
    "Normal",
    "Risiko Stunting",
    "Stunting",
    "Stunting Berat"
];

if (
    !in_array(
        $filterStatusStunting,
        $statusStuntingValid,
        true
    )
) {
    $filterStatusStunting = "";
}

if (
    !in_array(
        $filterUrutan,
        [
            "terbaru",
            "terlama",
            "nama_az",
            "nama_za",
            "riwayat_terbanyak"
        ],
        true
    )
) {
    $filterUrutan = "terbaru";
}

/*
|--------------------------------------------------------------------------
| Daftar tahun
|--------------------------------------------------------------------------
*/

$daftarTahunFilter = [];

$queryTahun = mysqli_query(
    $conn,
    "SELECT DISTINCT
        LEFT(
            CAST(
                COALESCE(
                    tanggal_selesai,
                    tanggal
                ) AS CHAR
            ),
            4
        ) AS tahun
     FROM konsultasi
     WHERE LOWER(
            TRIM(
                COALESCE(
                    status_konsultasi,
                    'aktif'
                )
            )
          ) = 'selesai'
       AND LEFT(
            CAST(
                COALESCE(
                    tanggal_selesai,
                    tanggal
                ) AS CHAR
            ),
            4
       ) <> '0000'
     ORDER BY tahun DESC"
);

if ($queryTahun) {

    while (
        $rowTahun =
            mysqli_fetch_assoc(
                $queryTahun
            )
    ) {

        $tahun =
            (int) (
                $rowTahun["tahun"]
                ?? 0
            );

        if (
            $tahun >= 2000
            && $tahun <= 2100
        ) {
            $daftarTahunFilter[] =
                $tahun;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Daftar Puskesmas untuk Dinkes
|--------------------------------------------------------------------------
*/

$daftarPuskesmasFilter = [];

if ($roleAktif === "dinkes") {

    $queryPuskesmas = mysqli_query(
        $conn,
        "SELECT
            id_puskesmas,
            nama_puskesmas
         FROM puskesmas
         ORDER BY nama_puskesmas ASC"
    );

    if ($queryPuskesmas) {

        while (
            $row =
                mysqli_fetch_assoc(
                    $queryPuskesmas
                )
        ) {
            $daftarPuskesmasFilter[] =
                $row;
        }
    }
}

/*
|--------------------------------------------------------------------------
| MODE DETAIL RIWAYAT PER BALITA
|--------------------------------------------------------------------------
|
| riwayat_konsultasi.php?id_balita=7
| menampilkan seluruh konsultasi selesai milik balita tersebut.
|
*/

if ($idBalitaDipilih > 0) {

    /*
    |--------------------------------------------------------------------------
    | Validasi akses balita
    |--------------------------------------------------------------------------
    */

    $sqlBalita = "
        SELECT
            b.id_balita,
            b.nama_balita,
            b.nik_balita,
            b.id_user,
            b.id_puskesmas,
            ps.nama_puskesmas
        FROM balita AS b
        LEFT JOIN puskesmas AS ps
            ON b.id_puskesmas =
                ps.id_puskesmas
        WHERE b.id_balita = ?
    ";

    $tipeBalita = "i";
    $parameterBalita = [
        $idBalitaDipilih
    ];

    if ($roleAktif === "orang_tua") {

        $sqlBalita .= "
            AND b.id_user = ?
        ";

        $tipeBalita .= "i";
        $parameterBalita[] =
            $idUserAktif;

    } elseif ($roleBerbasisPuskesmas) {

        if ($puskesmasBelumTerhubung) {

            $sqlBalita .= "
                AND 1 = 0
            ";

        } else {

            $sqlBalita .= "
                AND b.id_puskesmas = ?
            ";

            $tipeBalita .= "i";
            $parameterBalita[] =
                $idPuskesmasAktif;
        }
    }

    $sqlBalita .= "
        LIMIT 1
    ";

    $stmtBalita = mysqli_prepare(
        $conn,
        $sqlBalita
    );

    if (!$stmtBalita) {
        die(
            "Gagal menyiapkan data balita."
        );
    }

    bindRiwayatKonsultasi(
        $stmtBalita,
        $tipeBalita,
        $parameterBalita
    );

    mysqli_stmt_execute(
        $stmtBalita
    );

    $hasilBalita =
        mysqli_stmt_get_result(
            $stmtBalita
        );

    $dataBalita =
        mysqli_fetch_assoc(
            $hasilBalita
        );

    mysqli_stmt_close(
        $stmtBalita
    );

    if (!$dataBalita) {
        header(
            "Location: riwayat_konsultasi.php"
        );
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Seluruh konsultasi selesai balita
    |--------------------------------------------------------------------------
    */

    $stmtRiwayat = mysqli_prepare(
        $conn,
        "SELECT
            k.id_konsultasi,
            k.tanggal,
            k.tanggal_selesai,
            k.keluhan,
            k.hasil_konsultasi,
            k.tindak_lanjut,
            p.nama AS nama_petugas,
            hd.status_gizi,
            hd.status_stunting
         FROM konsultasi AS k
         LEFT JOIN pengguna AS p
            ON k.id_petugas =
                p.id_user
         LEFT JOIN hasil_deteksi AS hd
            ON k.id_deteksi =
                hd.id_deteksi
         WHERE k.id_balita = ?
           AND k.sumber_pengajuan =
                'ahli_gizi'
           AND LOWER(
                TRIM(
                    COALESCE(
                        k.status_konsultasi,
                        'aktif'
                    )
                )
               ) = 'selesai'
         ORDER BY
            COALESCE(
                k.tanggal_selesai,
                k.tanggal
            ) DESC,
            k.id_konsultasi DESC"
    );

    if (!$stmtRiwayat) {
        die(
            "Gagal menyiapkan riwayat konsultasi balita: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtRiwayat,
        "i",
        $idBalitaDipilih
    );

    mysqli_stmt_execute(
        $stmtRiwayat
    );

    $queryRiwayat =
        mysqli_stmt_get_result(
            $stmtRiwayat
        );

    $totalRiwayat =
        $queryRiwayat
            ? mysqli_num_rows(
                $queryRiwayat
            )
            : 0;

    require_once "../includes/header.php";
    require_once "../includes/navbar.php";
    ?>

    <div class="layout-wrapper">

        <?php require_once "../includes/sidebar.php"; ?>

        <main class="main-content">

            <div class="card content-card">

                <div class="card-header">

                    <div>

                        <h4 class="mb-1">
                            Riwayat Konsultasi
                            <?= amanRiwayatKonsultasi(
                                $dataBalita[
                                    "nama_balita"
                                ]
                            ); ?>
                        </h4>

                        <small class="text-muted">
                            Seluruh konsultasi yang sudah
                            selesai ditampilkan sebagai
                            riwayat dan bersifat read-only.
                        </small>

                    </div>

                    <div class="d-flex flex-wrap gap-2">

                        <span
                            class="badge badge-info
                            d-inline-flex
                            align-items-center px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= amanRiwayatKonsultasi(
                                $dataBalita[
                                    "nama_puskesmas"
                                ]
                                ?? "-"
                            ); ?>
                        </span>

                        <a
                            href="riwayat_konsultasi.php"
                            class="btn btn-secondary btn-sm"
                        >
                            <i class="bi bi-arrow-left"></i>
                            Kembali
                        </a>

                    </div>

                </div>

                <div class="card-body">

                    <div class="row g-3 mb-4">

                        <div class="col-12 col-lg-4">
                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Balita
                                </span>

                                <div class="detail-value">
                                    <?= amanRiwayatKonsultasi(
                                        $dataBalita[
                                            "nama_balita"
                                        ]
                                    ); ?>
                                </div>

                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    NIK
                                </span>

                                <div class="detail-value">
                                    <?= amanRiwayatKonsultasi(
                                        $dataBalita[
                                            "nik_balita"
                                        ]
                                    ); ?>
                                </div>

                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Jumlah Riwayat Konsultasi
                                </span>

                                <div class="detail-value">
                                    <?= $totalRiwayat; ?>
                                    konsultasi
                                </div>

                            </div>
                        </div>

                    </div>

                    <div class="table-responsive">

                        <table
                            class="table
                            table-hover align-middle"
                        >

                            <thead>

                                <tr>

                                    <th class="text-center">
                                        No
                                    </th>

                                    <th>
                                        Tanggal
                                    </th>

                                    <th>
                                        Status Stunting
                                    </th>

                                    <th>
                                        Petugas Gizi
                                    </th>

                                    <th>
                                        Hasil Konsultasi
                                    </th>

                                    <th>
                                        Tindak Lanjut
                                    </th>

                                    <th class="text-center">
                                        Aksi
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php if (
                                $totalRiwayat > 0
                            ): ?>

                                <?php
                                $no = 1;

                                while (
                                    $d =
                                        mysqli_fetch_assoc(
                                            $queryRiwayat
                                        )
                                ):
                                ?>

                                    <tr>

                                        <td class="text-center">
                                            <?= $no++; ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= amanRiwayatKonsultasi(
                                                    formatTanggalKonsultasi(
                                                        $d[
                                                            "tanggal_selesai"
                                                        ]
                                                        ?: $d[
                                                            "tanggal"
                                                        ]
                                                    )
                                                ); ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <span class="badge badge-info">
                                                <?= amanRiwayatKonsultasi(
                                                    $d[
                                                        "status_stunting"
                                                    ]
                                                    ?? "-"
                                                ); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= amanRiwayatKonsultasi(
                                                $d[
                                                    "nama_petugas"
                                                ]
                                                ?? "-"
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= amanRiwayatKonsultasi(
                                                $d[
                                                    "hasil_konsultasi"
                                                ]
                                                ?? "-"
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= amanRiwayatKonsultasi(
                                                $d[
                                                    "tindak_lanjut"
                                                ]
                                                ?? "-"
                                            ); ?>
                                        </td>

                                        <td class="text-center">

                                            <a
                                                href="detail_konsultasi.php?id=<?= (int) $d["id_konsultasi"]; ?>&kembali=riwayat"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Detail
                                            </a>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="7">

                                        <div class="empty-state">

                                            <div class="empty-state-icon">
                                                <i class="bi bi-chat-heart"></i>
                                            </div>

                                            <h3>
                                                Belum ada riwayat konsultasi
                                            </h3>

                                            <p>
                                                Balita ini belum memiliki
                                                konsultasi yang sudah selesai.
                                            </p>

                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </main>

    </div>

    <?php
    mysqli_stmt_close($stmtRiwayat);
    require_once "../includes/footer.php";
    exit;
}

/*
|--------------------------------------------------------------------------
| MODE UTAMA - 1 BALITA = 1 BARIS
|--------------------------------------------------------------------------
|
| Hanya balita yang mempunyai minimal satu konsultasi selesai yang tampil.
| Catatan terakhir dipakai sebagai ringkasan pada halaman utama.
|
*/

$sql = "
    SELECT
        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.id_puskesmas,

        ps.nama_puskesmas,

        ringkasan.jumlah_riwayat,

        k.id_konsultasi
            AS id_konsultasi_terakhir,

        k.tanggal
            AS tanggal_konsultasi_terakhir,

        k.tanggal_selesai
            AS tanggal_selesai_terakhir,

        k.hasil_konsultasi
            AS hasil_konsultasi_terakhir,

        k.tindak_lanjut
            AS tindak_lanjut_terakhir,

        p.nama
            AS nama_petugas_terakhir,

        hd.status_gizi
            AS status_gizi_terakhir,

        hd.status_stunting
            AS status_stunting_terakhir

    FROM balita AS b

    INNER JOIN (
        SELECT
            id_balita,
            COUNT(*) AS jumlah_riwayat,
            MAX(id_konsultasi)
                AS id_konsultasi_terakhir
        FROM konsultasi
        WHERE sumber_pengajuan =
                'ahli_gizi'
          AND LOWER(
                TRIM(
                    COALESCE(
                        status_konsultasi,
                        'aktif'
                    )
                )
              ) = 'selesai'
        GROUP BY id_balita
    ) AS ringkasan
        ON ringkasan.id_balita =
            b.id_balita

    INNER JOIN konsultasi AS k
        ON k.id_konsultasi =
            ringkasan.id_konsultasi_terakhir

    LEFT JOIN puskesmas AS ps
        ON b.id_puskesmas =
            ps.id_puskesmas

    LEFT JOIN pengguna AS p
        ON k.id_petugas =
            p.id_user

    LEFT JOIN hasil_deteksi AS hd
        ON k.id_deteksi =
            hd.id_deteksi

    WHERE 1 = 1
";

$tipe = "";
$parameter = [];

/*
|--------------------------------------------------------------------------
| Batas akses role
|--------------------------------------------------------------------------
*/

if ($roleAktif === "orang_tua") {

    $sql .= "
        AND b.id_user = ?
    ";

    $tipe .= "i";
    $parameter[] =
        $idUserAktif;

} elseif ($roleBerbasisPuskesmas) {

    if ($puskesmasBelumTerhubung) {

        $sql .= "
            AND 1 = 0
        ";

    } else {

        $sql .= "
            AND b.id_puskesmas = ?
        ";

        $tipe .= "i";
        $parameter[] =
            $idPuskesmasAktif;
    }

} elseif (
    $roleAktif === "dinkes"
    && $filterPuskesmas > 0
) {

    $sql .= "
        AND b.id_puskesmas = ?
    ";

    $tipe .= "i";
    $parameter[] =
        $filterPuskesmas;
}

/*
|--------------------------------------------------------------------------
| Filter Bulan / Tahun pada konsultasi terakhir
|--------------------------------------------------------------------------
*/

if ($filterBulan > 0) {

    $bulanDuaDigit =
        str_pad(
            (string) $filterBulan,
            2,
            "0",
            STR_PAD_LEFT
        );

    $sql .= "
        AND SUBSTRING(
            CAST(
                COALESCE(
                    k.tanggal_selesai,
                    k.tanggal
                ) AS CHAR
            ),
            6,
            2
        ) = ?
    ";

    $tipe .= "s";
    $parameter[] =
        $bulanDuaDigit;
}

if ($filterTahun > 0) {

    $sql .= "
        AND LEFT(
            CAST(
                COALESCE(
                    k.tanggal_selesai,
                    k.tanggal
                ) AS CHAR
            ),
            4
        ) = ?
    ";

    $tipe .= "s";
    $parameter[] =
        (string) $filterTahun;
}

if ($filterStatusStunting !== "") {

    $sql .= "
        AND LOWER(
            TRIM(
                COALESCE(
                    hd.status_stunting,
                    ''
                )
            )
        ) = LOWER(?)
    ";

    $tipe .= "s";
    $parameter[] =
        $filterStatusStunting;
}

/*
|--------------------------------------------------------------------------
| Pencarian seluruh riwayat konsultasi balita
|--------------------------------------------------------------------------
*/

if ($cari !== "") {

    $kataKunci =
        "%" . $cari . "%";

    $sql .= "
        AND (
            b.nama_balita LIKE ?
            OR b.nik_balita LIKE ?
            OR ps.nama_puskesmas LIKE ?
            OR p.nama LIKE ?
            OR hd.status_stunting LIKE ?
            OR EXISTS (
                SELECT 1
                FROM konsultasi AS kc
                LEFT JOIN pengguna AS pc
                    ON kc.id_petugas =
                        pc.id_user
                LEFT JOIN hasil_deteksi AS hdc
                    ON kc.id_deteksi =
                        hdc.id_deteksi
                WHERE kc.id_balita =
                        b.id_balita
                  AND kc.sumber_pengajuan =
                        'ahli_gizi'
                  AND LOWER(
                        TRIM(
                            COALESCE(
                                kc.status_konsultasi,
                                'aktif'
                            )
                        )
                      ) = 'selesai'
                  AND (
                    kc.hasil_konsultasi LIKE ?
                    OR kc.tindak_lanjut LIKE ?
                    OR kc.keluhan LIKE ?
                    OR pc.nama LIKE ?
                    OR hdc.status_stunting LIKE ?
                  )
            )
        )
    ";

    $tipe .=
        str_repeat(
            "s",
            10
        );

    for ($i = 0; $i < 10; $i++) {
        $parameter[] =
            $kataKunci;
    }
}

/*
|--------------------------------------------------------------------------
| Urutan
|--------------------------------------------------------------------------
*/

switch ($filterUrutan) {

    case "terlama":
        $sql .= "
            ORDER BY
                COALESCE(
                    k.tanggal_selesai,
                    k.tanggal
                ) ASC,
                k.id_konsultasi ASC
        ";
        break;

    case "nama_az":
        $sql .= "
            ORDER BY
                b.nama_balita ASC,
                b.id_balita ASC
        ";
        break;

    case "nama_za":
        $sql .= "
            ORDER BY
                b.nama_balita DESC,
                b.id_balita DESC
        ";
        break;

    case "riwayat_terbanyak":
        $sql .= "
            ORDER BY
                ringkasan.jumlah_riwayat DESC,
                b.nama_balita ASC
        ";
        break;

    case "terbaru":
    default:
        $sql .= "
            ORDER BY
                COALESCE(
                    k.tanggal_selesai,
                    k.tanggal
                ) DESC,
                k.id_konsultasi DESC
        ";
        break;
}

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {
    die(
        "Gagal menyiapkan riwayat konsultasi: "
        . mysqli_error($conn)
    );
}

bindRiwayatKonsultasi(
    $stmt,
    $tipe,
    $parameter
);

if (!mysqli_stmt_execute($stmt)) {
    die(
        "Gagal menjalankan riwayat konsultasi: "
        . mysqli_stmt_error($stmt)
    );
}

$query =
    mysqli_stmt_get_result(
        $stmt
    );

$totalData =
    $query
        ? mysqli_num_rows(
            $query
        )
        : 0;

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Riwayat Konsultasi
                    </h4>

                    <small class="text-muted">
                        Satu balita ditampilkan satu kali.
                        Seluruh konsultasi selesai tersedia
                        pada tombol Riwayat.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        $roleBerbasisPuskesmas
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex
                            align-items-center px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= amanRiwayatKonsultasi(
                                $namaPuskesmasAktif
                            ); ?>
                        </span>

                    <?php elseif (
                        $roleAktif === "dinkes"
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex
                            align-items-center px-3"
                        >
                            <i class="bi bi-buildings me-1"></i>
                            Semua Puskesmas
                        </span>

                    <?php endif; ?>

                    <a
                        href="data_konsultasi.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Konsultasi Aktif
                    </a>

                </div>

            </div>

            <div class="card-body">

                <?php if (
                    $puskesmasBelumTerhubung
                ): ?>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Akun belum terhubung dengan Puskesmas.
                    </div>

                <?php endif; ?>

                <form
                    method="GET"
                    class="row g-3 mb-4 align-items-end"
                >

                    <div class="col-12">

                        <div
                            class="d-flex
                            align-items-center gap-2"
                        >
                            <i class="bi bi-funnel"></i>
                            <strong>
                                Filter & Cari Data
                            </strong>
                        </div>

                    </div>

                    <div class="col-12 col-md-6 col-xl-4">

                        <label class="form-label">
                            Cari
                        </label>

                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari balita, NIK, petugas, hasil, tindak lanjut..."
                            value="<?= amanRiwayatKonsultasi(
                                $cari
                            ) === "-"
                                ? ""
                                : amanRiwayatKonsultasi(
                                    $cari
                                ); ?>"
                        >

                    </div>

                    <div class="col-6 col-md-3 col-xl-2">

                        <label class="form-label">
                            Bulan
                        </label>

                        <select
                            name="bulan"
                            class="form-select"
                        >

                            <option value="0">
                                Semua Bulan
                            </option>

                            <?php

                            $namaBulan = [
                                1 => "Januari",
                                2 => "Februari",
                                3 => "Maret",
                                4 => "April",
                                5 => "Mei",
                                6 => "Juni",
                                7 => "Juli",
                                8 => "Agustus",
                                9 => "September",
                                10 => "Oktober",
                                11 => "November",
                                12 => "Desember"
                            ];

                            foreach (
                                $namaBulan
                                as $nomor =>
                                    $nama
                            ):
                            ?>

                                <option
                                    value="<?= $nomor; ?>"
                                    <?= $filterBulan ===
                                        $nomor
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= amanRiwayatKonsultasi(
                                        $nama
                                    ); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-6 col-md-3 col-xl-2">

                        <label class="form-label">
                            Tahun
                        </label>

                        <select
                            name="tahun"
                            class="form-select"
                        >

                            <option value="0">
                                Semua Tahun
                            </option>

                            <?php foreach (
                                $daftarTahunFilter
                                as $tahun
                            ): ?>

                                <option
                                    value="<?= (int) $tahun; ?>"
                                    <?= $filterTahun ===
                                        (int) $tahun
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= (int) $tahun; ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-12 col-md-6 col-xl-2">

                        <label class="form-label">
                            Status Stunting
                        </label>

                        <select
                            name="status_stunting"
                            class="form-select"
                        >

                            <option value="">
                                Semua Status
                            </option>

                            <?php foreach (
                                [
                                    "Normal",
                                    "Risiko Stunting",
                                    "Stunting",
                                    "Stunting Berat"
                                ]
                                as $status
                            ): ?>

                                <option
                                    value="<?= amanRiwayatKonsultasi(
                                        $status
                                    ); ?>"
                                    <?= $filterStatusStunting ===
                                        $status
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= amanRiwayatKonsultasi(
                                        $status
                                    ); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <?php if (
                        $roleAktif === "dinkes"
                    ): ?>

                        <div class="col-12 col-md-6 col-xl-2">

                            <label class="form-label">
                                Puskesmas
                            </label>

                            <select
                                name="puskesmas"
                                class="form-select"
                            >

                                <option value="0">
                                    Semua Puskesmas
                                </option>

                                <?php foreach (
                                    $daftarPuskesmasFilter
                                    as $ps
                                ): ?>

                                    <option
                                        value="<?= (int) $ps["id_puskesmas"]; ?>"
                                        <?= $filterPuskesmas ===
                                            (int) $ps["id_puskesmas"]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanRiwayatKonsultasi(
                                            $ps[
                                                "nama_puskesmas"
                                            ]
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    <?php endif; ?>

                    <div class="col-12 col-md-6 col-xl-2">

                        <label class="form-label">
                            Urutkan
                        </label>

                        <select
                            name="urutan"
                            class="form-select"
                        >

                            <option
                                value="terbaru"
                                <?= $filterUrutan ===
                                    "terbaru"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Terbaru
                            </option>

                            <option
                                value="terlama"
                                <?= $filterUrutan ===
                                    "terlama"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Terlama
                            </option>

                            <option
                                value="nama_az"
                                <?= $filterUrutan ===
                                    "nama_az"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Nama A–Z
                            </option>

                            <option
                                value="nama_za"
                                <?= $filterUrutan ===
                                    "nama_za"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Nama Z–A
                            </option>

                            <option
                                value="riwayat_terbanyak"
                                <?= $filterUrutan ===
                                    "riwayat_terbanyak"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Riwayat Terbanyak
                            </option>

                        </select>

                    </div>

                    <div
                        class="col-12 col-md-6 col-xl-3
                        d-flex gap-2"
                    >

                        <button
                            type="submit"
                            class="btn btn-primary flex-fill"
                        >
                            <i class="bi bi-funnel-fill"></i>
                            Terapkan
                        </button>

                        <a
                            href="riwayat_konsultasi.php"
                            class="btn
                            btn-outline-secondary
                            flex-fill"
                        >
                            <i
                                class="bi
                                bi-arrow-counterclockwise"
                            ></i>
                            Reset
                        </a>

                    </div>

                </form>

                <div
                    class="d-flex
                    justify-content-between
                    align-items-center
                    flex-wrap gap-2 mb-3"
                >

                    <span class="text-muted small">
                        Total:
                        <strong>
                            <?= $totalData; ?>
                        </strong>
                        balita dengan riwayat konsultasi
                    </span>

                    <span class="badge bg-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Selesai
                    </span>

                </div>

                <div class="table-responsive">

                    <table
                        class="table
                        table-hover align-middle"
                    >

                        <thead>

                            <tr>

                                <th class="text-center">
                                    No
                                </th>

                                <th>
                                    Balita
                                </th>

                                <th>
                                    Puskesmas
                                </th>

                                <th class="text-center">
                                    Jumlah Riwayat
                                </th>

                                <th>
                                    Konsultasi Terakhir
                                </th>

                                <th>
                                    Status Stunting Terakhir
                                </th>

                                <th>
                                    Hasil Terakhir
                                </th>

                                <th class="text-center">
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (
                            $totalData > 0
                        ): ?>

                            <?php
                            $no = 1;

                            while (
                                $d =
                                    mysqli_fetch_assoc(
                                        $query
                                    )
                            ):
                            ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $no++; ?>
                                    </td>

                                    <td>

                                        <strong class="d-block">
                                            <?= amanRiwayatKonsultasi(
                                                $d[
                                                    "nama_balita"
                                                ]
                                            ); ?>
                                        </strong>

                                        <small class="text-muted">
                                            NIK:
                                            <?= amanRiwayatKonsultasi(
                                                $d[
                                                    "nik_balita"
                                                ]
                                            ); ?>
                                        </small>

                                    </td>

                                    <td>
                                        <?= amanRiwayatKonsultasi(
                                            $d[
                                                "nama_puskesmas"
                                            ]
                                            ?? "-"
                                        ); ?>
                                    </td>

                                    <td class="text-center">

                                        <span class="badge badge-info">
                                            <?= (int) $d[
                                                "jumlah_riwayat"
                                            ]; ?>
                                            konsultasi
                                        </span>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= amanRiwayatKonsultasi(
                                                formatTanggalKonsultasi(
                                                    $d[
                                                        "tanggal_selesai_terakhir"
                                                    ]
                                                    ?: $d[
                                                        "tanggal_konsultasi_terakhir"
                                                    ]
                                                )
                                            ); ?>
                                        </strong>

                                        <small
                                            class="d-block text-muted"
                                        >
                                            <?= amanRiwayatKonsultasi(
                                                $d[
                                                    "nama_petugas_terakhir"
                                                ]
                                                ?? "-"
                                            ); ?>
                                        </small>

                                    </td>

                                    <td>

                                        <span class="badge badge-info">
                                            <?= amanRiwayatKonsultasi(
                                                $d[
                                                    "status_stunting_terakhir"
                                                ]
                                                ?? "-"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= amanRiwayatKonsultasi(
                                            $d[
                                                "hasil_konsultasi_terakhir"
                                            ]
                                            ?? "-"
                                        ); ?>
                                    </td>

                                    <td class="text-center">

                                        <a
                                            href="riwayat_konsultasi.php?id_balita=<?= (int) $d["id_balita"]; ?>"
                                            class="btn btn-info btn-sm"
                                        >
                                            <i class="bi bi-clock-history"></i>
                                            Riwayat
                                            (<?= (int) $d["jumlah_riwayat"]; ?>)
                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="8">

                                    <div class="empty-state">

                                        <div class="empty-state-icon">
                                            <i class="bi bi-chat-heart"></i>
                                        </div>

                                        <h3>
                                            Belum ada riwayat konsultasi
                                        </h3>

                                        <p>
                                            Tidak ada balita dengan
                                            konsultasi selesai yang
                                            sesuai dengan filter.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</div>

<?php
mysqli_stmt_close($stmt);
require_once "../includes/footer.php";
?>
