<?php
/*
|--------------------------------------------------------------------------
| Riwayat Kesehatan - Router Satu File
|--------------------------------------------------------------------------
|
| Tanpa file riwayat_balita.php.
| - riwayat_kesehatan.php              = daftar balita
| - riwayat_kesehatan.php?id_balita=7  = seluruh riwayat balita 7
|
*/

if (
    isset($_GET["id_balita"])
    && (int) $_GET["id_balita"] > 0
) {

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman =
    "Riwayat Kesehatan Balita | Sistem Deteksi Stunting";

$roleAktif =
    $_SESSION["role"] ?? "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idBalita = filter_input(
    INPUT_GET,
    "id_balita",
    FILTER_VALIDATE_INT
);

if (!$idBalita) {
    header(
        "Location: riwayat_kesehatan.php"
    );
    exit;
}

$idPuskesmasAktif = 0;
$puskesmasBelumTerhubung = false;

$roleBerbasisPuskesmas =
    in_array(
        $roleAktif,
        [
            "petugas_kia",
            "petugas_gizi",
            "kepala_puskesmas"
        ],
        true
    );

if ($roleBerbasisPuskesmas) {

    $stmtPuskesmas = mysqli_prepare(
        $conn,
        "SELECT id_puskesmas
         FROM pengguna
         WHERE id_user = ?
         LIMIT 1"
    );

    if (!$stmtPuskesmas) {
        die(
            "Gagal memeriksa Puskesmas pengguna."
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
    }
}

/*
|--------------------------------------------------------------------------
| Ambil identitas balita sesuai hak akses
|--------------------------------------------------------------------------
*/

$sqlBalita = "
    SELECT
        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu,
        b.id_user,
        b.id_puskesmas,
        p.nama_puskesmas
    FROM balita AS b
    LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas
    WHERE b.id_balita = ?
";

$tipeBalita = "i";
$paramBalita = [$idBalita];

if ($roleAktif === "orang_tua") {

    $sqlBalita .= "
        AND b.id_user = ?
    ";

    $tipeBalita .= "i";
    $paramBalita[] =
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
        $paramBalita[] =
            $idPuskesmasAktif;
    }
}

$sqlBalita .= " LIMIT 1";

$stmtBalita = mysqli_prepare(
    $conn,
    $sqlBalita
);

if (!$stmtBalita) {
    die(
        "Gagal menyiapkan identitas balita."
    );
}

mysqli_stmt_bind_param(
    $stmtBalita,
    $tipeBalita,
    ...$paramBalita
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
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil seluruh riwayat balita
|--------------------------------------------------------------------------
*/

$stmtRiwayat = mysqli_prepare(
    $conn,
    "SELECT
        rk.id_riwayat,
        rk.riwayat_penyakit,
        rk.riwayat_imunisasi,
        rk.riwayat_perawatan,
        rk.penyakit_penyerta,
        rk.status_red_flag,
        rk.catatan_red_flag,
        rk.status_rujukan,
        rk.rekomendasi_rujukan,
        rk.catatan_kia,
        rk.tanggal_penilaian,
        penilai.nama AS nama_penilai
     FROM riwayat_kesehatan AS rk
     LEFT JOIN pengguna AS penilai
        ON rk.penilai_red_flag =
            penilai.id_user
     WHERE rk.id_balita = ?
     ORDER BY
        CASE
            WHEN rk.tanggal_penilaian IS NULL
            THEN 1
            ELSE 0
        END ASC,
        rk.tanggal_penilaian DESC,
        rk.id_riwayat DESC"
);

if (!$stmtRiwayat) {
    die(
        "Gagal menyiapkan riwayat balita: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtRiwayat,
    "i",
    $idBalita
);

mysqli_stmt_execute(
    $stmtRiwayat
);

$queryRiwayat =
    mysqli_stmt_get_result(
        $stmtRiwayat
    );

$totalRiwayat =
    mysqli_num_rows(
        $queryRiwayat
    );

function formatTanggalRiwayatBalita(
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
        return "Tanggal belum tercatat";
    }

    $timestamp =
        strtotime($tanggal);

    if ($timestamp === false) {
        return "Tanggal belum tercatat";
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
        . date("Y", $timestamp)
        . (
            strlen($tanggal) > 10
                ? " • " . date(
                    "H:i",
                    $timestamp
                )
                : ""
        );
}

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
                        Riwayat Kesehatan
                        <?= htmlspecialchars(
                            $dataBalita["nama_balita"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </h4>

                    <small class="text-muted">
                        Seluruh catatan kesehatan balita
                        ditampilkan sebagai histori.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <span
                        class="badge badge-info
                        d-inline-flex
                        align-items-center px-3"
                    >
                        <i class="bi bi-hospital me-1"></i>
                        <?= htmlspecialchars(
                            $dataBalita[
                                "nama_puskesmas"
                            ]
                            ?? "-",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </span>

                    <a
                        href="riwayat_kesehatan.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if (
                        $roleAktif === "petugas_kia"
                    ): ?>

                        <a
                            href="tambah_kesehatan.php?id_balita=<?= (int) $idBalita; ?>"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Tambah Riwayat
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if (
                    isset($_GET["pesan"])
                ): ?>

                    <?php if (
                        $_GET["pesan"] ===
                        "tambah_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Riwayat kesehatan berhasil ditambahkan.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] ===
                        "edit_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Riwayat kesehatan berhasil diperbarui.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] ===
                        "hapus_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Catatan riwayat berhasil dihapus.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] ===
                        "hapus_gagal"
                    ): ?>

                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle me-1"></i>
                            Catatan riwayat gagal dihapus.
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

                <div class="row g-3 mb-4">

                    <div class="col-12 col-lg-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Balita
                            </span>

                            <div class="detail-value">
                                <i
                                    class="bi
                                    bi-person-heart me-1"
                                ></i>
                                <?= htmlspecialchars(
                                    $dataBalita[
                                        "nama_balita"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
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
                                <?= htmlspecialchars(
                                    $dataBalita[
                                        "nik_balita"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Jumlah Riwayat
                            </span>

                            <div class="detail-value">
                                <?= $totalRiwayat; ?>
                                catatan
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
                                    Riwayat Penyakit
                                </th>

                                <th>
                                    Penyakit Penyerta
                                </th>

                                <th class="text-center">
                                    Red Flag
                                </th>

                                <th class="text-center">
                                    Rujukan
                                </th>

                                <th>
                                    Catatan KIA
                                </th>

                                <th
                                    class="text-center"
                                    style="min-width: 260px;"
                                >
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

                                <?php

                                $statusRedFlag =
                                    trim(
                                        (string) (
                                            $d[
                                                "status_red_flag"
                                            ]
                                            ?? "Belum dinilai"
                                        )
                                    );

                                if (
                                    $statusRedFlag === "Ada"
                                ) {
                                    $kelasRedFlag =
                                        "bg-danger";
                                } elseif (
                                    $statusRedFlag ===
                                    "Tidak ada"
                                ) {
                                    $kelasRedFlag =
                                        "bg-success";
                                } else {
                                    $kelasRedFlag =
                                        "bg-warning text-dark";
                                }

                                $statusRujukan =
                                    trim(
                                        (string) (
                                            $d[
                                                "status_rujukan"
                                            ]
                                            ?? ""
                                        )
                                    );

                                $rujukanLower =
                                    strtolower(
                                        $statusRujukan
                                    );

                                if (
                                    $rujukanLower === ""
                                    || $rujukanLower ===
                                        "tidak perlu"
                                ) {
                                    $kelasRujukan =
                                        "bg-success";
                                    $labelRujukan =
                                        $statusRujukan !== ""
                                            ? $statusRujukan
                                            : "Tidak Perlu";
                                } elseif (
                                    str_contains(
                                        $rujukanLower,
                                        "rekomend"
                                    )
                                ) {
                                    $kelasRujukan =
                                        "bg-warning text-dark";
                                    $labelRujukan =
                                        $statusRujukan;
                                } else {
                                    $kelasRujukan =
                                        "bg-danger";
                                    $labelRujukan =
                                        $statusRujukan;
                                }

                                ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $no++; ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                formatTanggalRiwayatBalita(
                                                    $d[
                                                        "tanggal_penilaian"
                                                    ]
                                                ),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </strong>

                                        <?php if (
                                            trim(
                                                (string) (
                                                    $d[
                                                        "nama_penilai"
                                                    ]
                                                    ?? ""
                                                )
                                            ) !== ""
                                        ): ?>

                                            <small
                                                class="d-block
                                                text-muted"
                                            >
                                                Petugas:
                                                <?= htmlspecialchars(
                                                    $d[
                                                        "nama_penilai"
                                                    ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </small>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= nl2br(
                                            htmlspecialchars(
                                                $d[
                                                    "riwayat_penyakit"
                                                ]
                                                ?? "-",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            )
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= nl2br(
                                            htmlspecialchars(
                                                $d[
                                                    "penyakit_penyerta"
                                                ]
                                                ?? "-",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            )
                                        ); ?>
                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge
                                            <?= $kelasRedFlag; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $statusRedFlag,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge
                                            <?= $kelasRujukan; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $labelRujukan,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= nl2br(
                                            htmlspecialchars(
                                                $d[
                                                    "catatan_kia"
                                                ]
                                                ?? "-",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            )
                                        ); ?>
                                    </td>

                                    <td>

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

                                            <a
                                                href="detail_kesehatan.php?id=<?= (int) $d["id_riwayat"]; ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Detail
                                            </a>

                                            <?php if (
                                                $roleAktif ===
                                                "petugas_kia"
                                            ): ?>

                                                <a
                                                    href="edit_kesehatan.php?id=<?= (int) $d["id_riwayat"]; ?>"
                                                    class="btn btn-warning btn-sm"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-pencil-square"
                                                    ></i>
                                                    Edit
                                                </a>

                                                <form
                                                    action="hapus_kesehatan.php"
                                                    method="POST"
                                                    class="d-inline
                                                    form-hapus-riwayat"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="id_riwayat"
                                                        value="<?= (int) $d["id_riwayat"]; ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_balita"
                                                        value="<?= (int) $idBalita; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                    >
                                                        <i
                                                            class="bi
                                                            bi-trash3"
                                                        ></i>
                                                        Hapus
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="8">

                                        <div class="empty-state">

                                            <div
                                                class="empty-state-icon"
                                            >
                                                <i
                                                    class="bi
                                                    bi-heart-pulse"
                                                ></i>
                                            </div>

                                            <h3>
                                                Belum ada riwayat
                                            </h3>

                                            <p>
                                                Balita ini belum
                                                memiliki catatan
                                                riwayat kesehatan.
                                            </p>

                                            <?php if (
                                                $roleAktif ===
                                                "petugas_kia"
                                            ): ?>

                                                <a
                                                    href="tambah_kesehatan.php?id_balita=<?= (int) $idBalita; ?>"
                                                    class="btn btn-primary mt-3"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-plus-circle"
                                                    ></i>
                                                    Tambah Riwayat Pertama
                                                </a>

                                            <?php endif; ?>

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

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        document
            .querySelectorAll(
                ".form-hapus-riwayat"
            )
            .forEach(function (form) {

                form.addEventListener(
                    "submit",
                    function (event) {

                        if (
                            !window.confirm(
                                "Hapus catatan riwayat kesehatan ini? Data yang dihapus tidak dapat dikembalikan."
                            )
                        ) {
                            event.preventDefault();
                        }

                    }
                );

            });

    }
);
</script>

<?php
mysqli_stmt_close($stmtRiwayat);
require_once "../includes/footer.php";
?>

<?php
    exit;
}
?>
<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman =
    "Riwayat Kesehatan | Sistem Deteksi Stunting";

$roleAktif =
    $_SESSION["role"] ?? "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$cari =
    trim($_GET["cari"] ?? "");

$kataKunci =
    "%" . $cari . "%";

/*
|--------------------------------------------------------------------------
| Filter daftar balita
|--------------------------------------------------------------------------
*/

$statusRiwayat =
    trim($_GET["status_riwayat"] ?? "");

$bulanFilter =
    filter_input(
        INPUT_GET,
        "bulan",
        FILTER_VALIDATE_INT
    );

$tahunFilter =
    filter_input(
        INPUT_GET,
        "tahun",
        FILTER_VALIDATE_INT
    );

if (
    $bulanFilter === false
    || $bulanFilter < 1
    || $bulanFilter > 12
) {
    $bulanFilter = 0;
}

if (
    $tahunFilter === false
    || $tahunFilter < 2000
    || $tahunFilter > 2100
) {
    $tahunFilter = 0;
}

$statusRedFlagFilter =
    trim($_GET["red_flag"] ?? "");

$statusRujukanFilter =
    trim($_GET["rujukan"] ?? "");

$urutkan =
    trim($_GET["urutkan"] ?? "nama_az");

$opsiStatusRiwayat = [
    "",
    "ada",
    "belum"
];

$opsiRedFlag = [
    "",
    "ada",
    "tidak_ada",
    "belum"
];

$opsiRujukan = [
    "",
    "tidak_perlu",
    "direkomendasikan",
    "dirujuk",
    "belum"
];

$opsiUrutan = [
    "nama_az",
    "nama_za",
    "terbaru",
    "terlama",
    "riwayat_terbanyak"
];

if (
    !in_array(
        $statusRiwayat,
        $opsiStatusRiwayat,
        true
    )
) {
    $statusRiwayat = "";
}

if (
    !in_array(
        $statusRedFlagFilter,
        $opsiRedFlag,
        true
    )
) {
    $statusRedFlagFilter = "";
}

if (
    !in_array(
        $statusRujukanFilter,
        $opsiRujukan,
        true
    )
) {
    $statusRujukanFilter = "";
}

if (
    !in_array(
        $urutkan,
        $opsiUrutan,
        true
    )
) {
    $urutkan = "nama_az";
}

$jumlahFilterAktif = 0;

foreach (
    [
        $statusRiwayat,
        $statusRedFlagFilter,
        $statusRujukanFilter
    ]
    as $nilaiFilter
) {
    if ($nilaiFilter !== "") {
        $jumlahFilterAktif++;
    }
}

if ($bulanFilter > 0) {
    $jumlahFilterAktif++;
}

if ($tahunFilter > 0) {
    $jumlahFilterAktif++;
}

if ($urutkan !== "nama_az") {
    $jumlahFilterAktif++;
}

/*
|--------------------------------------------------------------------------
| Daftar tahun untuk filter
|--------------------------------------------------------------------------
|
| Tahun diambil dari tanggal_penilaian yang benar-benar tersimpan.
|
*/

$daftarTahunFilter = [];

$queryTahunFilter = mysqli_query(
    $conn,
    "SELECT DISTINCT
        CAST(
            LEFT(
                CAST(tanggal_penilaian AS CHAR),
                4
            ) AS UNSIGNED
        ) AS tahun
     FROM riwayat_kesehatan
     WHERE tanggal_penilaian IS NOT NULL
       AND LEFT(
            CAST(tanggal_penilaian AS CHAR),
            10
       ) <> '0000-00-00'
       AND LEFT(
            CAST(tanggal_penilaian AS CHAR),
            4
       ) REGEXP '^[0-9]{4}$'
     ORDER BY tahun DESC"
);

if ($queryTahunFilter) {
    while (
        $rowTahunFilter =
            mysqli_fetch_assoc(
                $queryTahunFilter
            )
    ) {
        $tahun =
            (int) (
                $rowTahunFilter["tahun"]
                ?? 0
            );

        if ($tahun > 0) {
            $daftarTahunFilter[] =
                $tahun;
        }
    }
}

if (
    $tahunFilter > 0
    && !in_array(
        $tahunFilter,
        $daftarTahunFilter,
        true
    )
) {
    $daftarTahunFilter[] =
        $tahunFilter;

    rsort(
        $daftarTahunFilter,
        SORT_NUMERIC
    );
}

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

$roleBerbasisPuskesmas =
    in_array(
        $roleAktif,
        [
            "petugas_kia",
            "petugas_gizi",
            "kepala_puskesmas"
        ],
        true
    );

/*
|--------------------------------------------------------------------------
| Puskesmas akun
|--------------------------------------------------------------------------
*/

if ($roleBerbasisPuskesmas) {

    $stmtPuskesmas = mysqli_prepare(
        $conn,
        "SELECT
            u.id_puskesmas,
            p.nama_puskesmas
         FROM pengguna AS u
         LEFT JOIN puskesmas AS p
            ON u.id_puskesmas = p.id_puskesmas
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
| Query utama
|--------------------------------------------------------------------------
|
| Tabel utama memakai BALITA sebagai tabel induk.
| Riwayat kesehatan hanya diambil catatan TERBARU untuk ringkasan.
|
| Dengan cara ini:
| - satu balita selalu satu baris;
| - jumlah riwayat tetap tersimpan;
| - penambahan riwayat baru tidak membuat baris balita menjadi ganda.
|
*/

$sql = "
    SELECT
        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.id_puskesmas,

        p.nama_puskesmas,

        COALESCE(ringkasan.jumlah_riwayat, 0)
            AS jumlah_riwayat,

        rk.id_riwayat
            AS id_riwayat_terbaru,

        rk.riwayat_penyakit
            AS riwayat_penyakit_terbaru,

        rk.penyakit_penyerta
            AS penyakit_penyerta_terbaru,

        rk.status_red_flag
            AS status_red_flag_terbaru,

        rk.catatan_red_flag
            AS catatan_red_flag_terbaru,

        rk.status_rujukan
            AS status_rujukan_terbaru,

        rk.catatan_kia
            AS catatan_kia_terbaru,

        rk.tanggal_penilaian
            AS tanggal_terakhir

    FROM balita AS b

    LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas

    LEFT JOIN (
        SELECT
            id_balita,
            COUNT(*) AS jumlah_riwayat,
            MAX(id_riwayat) AS id_riwayat_terbaru
        FROM riwayat_kesehatan
        GROUP BY id_balita
    ) AS ringkasan
        ON ringkasan.id_balita = b.id_balita

    LEFT JOIN riwayat_kesehatan AS rk
        ON rk.id_riwayat =
            ringkasan.id_riwayat_terbaru

    WHERE 1 = 1
";

$tipeBind = "";
$parameterBind = [];

/*
|--------------------------------------------------------------------------
| Batas data berdasarkan role
|--------------------------------------------------------------------------
*/

if ($roleAktif === "orang_tua") {

    $sql .= "
        AND b.id_user = ?
    ";

    $tipeBind .= "i";
    $parameterBind[] =
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

        $tipeBind .= "i";
        $parameterBind[] =
            $idPuskesmasAktif;
    }
}

/*
|--------------------------------------------------------------------------
| Pencarian
|--------------------------------------------------------------------------
|
| Pencarian dapat menemukan balita berdasarkan identitas maupun isi
| SELURUH catatan riwayat kesehatan, bukan hanya catatan terbaru.
|
*/

if ($cari !== "") {

    $sql .= "
        AND (
            b.nama_balita LIKE ?
            OR b.nik_balita LIKE ?
            OR p.nama_puskesmas LIKE ?

            OR EXISTS (
                SELECT 1
                FROM riwayat_kesehatan AS rk_cari
                WHERE rk_cari.id_balita = b.id_balita
                  AND (
                    rk_cari.riwayat_penyakit LIKE ?
                    OR rk_cari.riwayat_imunisasi LIKE ?
                    OR rk_cari.riwayat_perawatan LIKE ?
                    OR rk_cari.penyakit_penyerta LIKE ?
                    OR rk_cari.status_red_flag LIKE ?
                    OR rk_cari.catatan_red_flag LIKE ?
                    OR rk_cari.status_rujukan LIKE ?
                    OR rk_cari.rekomendasi_rujukan LIKE ?
                    OR rk_cari.catatan_kia LIKE ?
                  )
            )
        )
    ";

    $tipeBind .=
        str_repeat("s", 12);

    for ($i = 0; $i < 12; $i++) {
        $parameterBind[] =
            $kataKunci;
    }
}

/*
|--------------------------------------------------------------------------
| Filter jumlah riwayat
|--------------------------------------------------------------------------
*/

if ($statusRiwayat === "ada") {
    $sql .= "
        AND COALESCE(
            ringkasan.jumlah_riwayat,
            0
        ) > 0
    ";
} elseif ($statusRiwayat === "belum") {
    $sql .= "
        AND COALESCE(
            ringkasan.jumlah_riwayat,
            0
        ) = 0
    ";
}

/*
|--------------------------------------------------------------------------
| Filter periode catatan kesehatan terakhir
|--------------------------------------------------------------------------
|
| Bulan dan tahun diterapkan pada tanggal_penilaian catatan TERBARU
| yang sedang mewakili setiap balita di halaman utama.
|
*/

if ($bulanFilter > 0) {
    $sql .= "
        AND rk.tanggal_penilaian IS NOT NULL
        AND LEFT(
            CAST(
                rk.tanggal_penilaian AS CHAR
            ),
            10
        ) <> '0000-00-00'
        AND CAST(
            SUBSTRING(
                CAST(
                    rk.tanggal_penilaian AS CHAR
                ),
                6,
                2
            ) AS UNSIGNED
        ) = ?
    ";

    $tipeBind .= "i";
    $parameterBind[] =
        $bulanFilter;
}

if ($tahunFilter > 0) {
    $sql .= "
        AND rk.tanggal_penilaian IS NOT NULL
        AND LEFT(
            CAST(
                rk.tanggal_penilaian AS CHAR
            ),
            10
        ) <> '0000-00-00'
        AND CAST(
            LEFT(
                CAST(
                    rk.tanggal_penilaian AS CHAR
                ),
                4
            ) AS UNSIGNED
        ) = ?
    ";

    $tipeBind .= "i";
    $parameterBind[] =
        $tahunFilter;
}

/*
|--------------------------------------------------------------------------
| Filter red flag terakhir
|--------------------------------------------------------------------------
*/

if ($statusRedFlagFilter === "ada") {
    $sql .= "
        AND LOWER(
            TRIM(
                COALESCE(
                    rk.status_red_flag,
                    ''
                )
            )
        ) = 'ada'
    ";
} elseif (
    $statusRedFlagFilter === "tidak_ada"
) {
    $sql .= "
        AND LOWER(
            TRIM(
                COALESCE(
                    rk.status_red_flag,
                    ''
                )
            )
        ) = 'tidak ada'
    ";
} elseif (
    $statusRedFlagFilter === "belum"
) {
    $sql .= "
        AND (
            COALESCE(
                ringkasan.jumlah_riwayat,
                0
            ) = 0
            OR TRIM(
                COALESCE(
                    rk.status_red_flag,
                    ''
                )
            ) = ''
        )
    ";
}

/*
|--------------------------------------------------------------------------
| Filter status rujukan terakhir
|--------------------------------------------------------------------------
*/

if (
    $statusRujukanFilter ===
    "tidak_perlu"
) {
    $sql .= "
        AND LOWER(
            TRIM(
                COALESCE(
                    rk.status_rujukan,
                    ''
                )
            )
        ) = 'tidak perlu'
    ";
} elseif (
    $statusRujukanFilter ===
    "direkomendasikan"
) {
    $sql .= "
        AND LOWER(
            TRIM(
                COALESCE(
                    rk.status_rujukan,
                    ''
                )
            )
        ) = 'direkomendasikan'
    ";
} elseif (
    $statusRujukanFilter ===
    "dirujuk"
) {
    $sql .= "
        AND LOWER(
            TRIM(
                COALESCE(
                    rk.status_rujukan,
                    ''
                )
            )
        ) = 'dirujuk'
    ";
} elseif (
    $statusRujukanFilter ===
    "belum"
) {
    $sql .= "
        AND (
            COALESCE(
                ringkasan.jumlah_riwayat,
                0
            ) = 0
            OR TRIM(
                COALESCE(
                    rk.status_rujukan,
                    ''
                )
            ) = ''
        )
    ";
}

/*
|--------------------------------------------------------------------------
| Pengurutan
|--------------------------------------------------------------------------
*/

switch ($urutkan) {

    case "nama_za":
        $sql .= "
            ORDER BY
                b.nama_balita DESC,
                b.id_balita DESC
        ";
        break;

    case "terbaru":
        $sql .= "
            ORDER BY
                CASE
                    WHEN rk.tanggal_penilaian
                        IS NULL
                    THEN 1
                    ELSE 0
                END ASC,
                rk.tanggal_penilaian DESC,
                rk.id_riwayat DESC,
                b.nama_balita ASC
        ";
        break;

    case "terlama":
        $sql .= "
            ORDER BY
                CASE
                    WHEN rk.tanggal_penilaian
                        IS NULL
                    THEN 1
                    ELSE 0
                END ASC,
                rk.tanggal_penilaian ASC,
                rk.id_riwayat ASC,
                b.nama_balita ASC
        ";
        break;

    case "riwayat_terbanyak":
        $sql .= "
            ORDER BY
                COALESCE(
                    ringkasan.jumlah_riwayat,
                    0
                ) DESC,
                b.nama_balita ASC
        ";
        break;

    case "nama_az":
    default:
        $sql .= "
            ORDER BY
                b.nama_balita ASC,
                b.id_balita ASC
        ";
        break;
}

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {
    die(
        "Gagal menyiapkan data riwayat kesehatan: "
        . mysqli_error($conn)
    );
}

if (
    $tipeBind !== ""
    && !empty($parameterBind)
) {
    mysqli_stmt_bind_param(
        $stmt,
        $tipeBind,
        ...$parameterBind
    );
}

mysqli_stmt_execute(
    $stmt
);

$query =
    mysqli_stmt_get_result(
        $stmt
    );

if (!$query) {
    die(
        "Gagal membaca data riwayat kesehatan."
    );
}

$totalData =
    mysqli_num_rows($query);

/*
|--------------------------------------------------------------------------
| Formatter
|--------------------------------------------------------------------------
*/

function formatTanggalRiwayatKesehatan(
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
                        Riwayat Kesehatan Anak
                    </h4>

                    <small class="text-muted">
                        Satu balita ditampilkan satu kali.
                        Seluruh catatan kesehatan tersimpan
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
                            <?= htmlspecialchars(
                                $namaPuskesmasAktif,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </span>

                    <?php endif; ?>

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

            </div>

            <div class="card-body">

                <?php if (
                    $roleBerbasisPuskesmas
                    && $puskesmasBelumTerhubung
                ): ?>

                    <div class="alert alert-warning">
                        <i
                            class="bi
                            bi-exclamation-triangle me-1"
                        ></i>
                        Akun ini belum terhubung dengan
                        Puskesmas. Data wilayah tidak
                        dapat ditampilkan.
                    </div>

                <?php endif; ?>

                <div class="detail-item mb-3">

                    <form
                        method="GET"
                        class="row g-3 align-items-end"
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

                            <label
                                for="cari"
                                class="form-label"
                            >
                                Cari
                            </label>

                            <input
                                type="text"
                                id="cari"
                                name="cari"
                                class="form-control"
                                placeholder="Cari nama balita, NIK, penyakit, red flag, rujukan..."
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >

                        </div>

                        <div class="col-6 col-md-3 col-xl-2">

                            <label
                                for="bulan"
                                class="form-label"
                            >
                                Bulan
                            </label>

                            <select
                                id="bulan"
                                name="bulan"
                                class="form-select"
                            >

                                <option value="">
                                    Semua Bulan
                                </option>

                                <?php

                                $namaBulanFilter = [
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
                                    $namaBulanFilter
                                    as $nomorBulan =>
                                        $namaBulan
                                ):
                                ?>

                                    <option
                                        value="<?= $nomorBulan; ?>"
                                        <?= (int) $bulanFilter ===
                                            (int) $nomorBulan
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $namaBulan,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-6 col-md-3 col-xl-2">

                            <label
                                for="tahun"
                                class="form-label"
                            >
                                Tahun
                            </label>

                            <select
                                id="tahun"
                                name="tahun"
                                class="form-select"
                            >

                                <option value="">
                                    Semua Tahun
                                </option>

                                <?php foreach (
                                    $daftarTahunFilter
                                    as $tahun
                                ): ?>

                                    <option
                                        value="<?= (int) $tahun; ?>"
                                        <?= (int) $tahunFilter ===
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

                            <label
                                for="status_riwayat"
                                class="form-label"
                            >
                                Status Riwayat
                            </label>

                            <select
                                id="status_riwayat"
                                name="status_riwayat"
                                class="form-select"
                            >

                                <option value="">
                                    Semua Status
                                </option>

                                <option
                                    value="ada"
                                    <?= $statusRiwayat ===
                                        "ada"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Sudah Ada Riwayat
                                </option>

                                <option
                                    value="belum"
                                    <?= $statusRiwayat ===
                                        "belum"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Belum Ada Riwayat
                                </option>

                            </select>

                        </div>

                        <div class="col-12 col-md-6 col-xl-2">

                            <label
                                for="red_flag"
                                class="form-label"
                            >
                                Red Flag
                            </label>

                            <select
                                id="red_flag"
                                name="red_flag"
                                class="form-select"
                            >

                                <option value="">
                                    Semua
                                </option>

                                <option
                                    value="ada"
                                    <?= $statusRedFlagFilter ===
                                        "ada"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Ada
                                </option>

                                <option
                                    value="tidak_ada"
                                    <?= $statusRedFlagFilter ===
                                        "tidak_ada"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Tidak Ada
                                </option>

                                <option
                                    value="belum"
                                    <?= $statusRedFlagFilter ===
                                        "belum"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Belum Dinilai
                                </option>

                            </select>

                        </div>

                        <div class="col-12 col-md-6 col-xl-3">

                            <label
                                for="rujukan"
                                class="form-label"
                            >
                                Status Rujukan
                            </label>

                            <select
                                id="rujukan"
                                name="rujukan"
                                class="form-select"
                            >

                                <option value="">
                                    Semua Status
                                </option>

                                <option
                                    value="tidak_perlu"
                                    <?= $statusRujukanFilter ===
                                        "tidak_perlu"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Tidak Perlu
                                </option>

                                <option
                                    value="direkomendasikan"
                                    <?= $statusRujukanFilter ===
                                        "direkomendasikan"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Direkomendasikan
                                </option>

                                <option
                                    value="dirujuk"
                                    <?= $statusRujukanFilter ===
                                        "dirujuk"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Dirujuk
                                </option>

                                <option
                                    value="belum"
                                    <?= $statusRujukanFilter ===
                                        "belum"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Belum Ada
                                </option>

                            </select>

                        </div>

                        <div class="col-12 col-md-6 col-xl-3">

                            <label
                                for="urutkan"
                                class="form-label"
                            >
                                Urutkan
                            </label>

                            <select
                                id="urutkan"
                                name="urutkan"
                                class="form-select"
                            >

                                <option
                                    value="nama_az"
                                    <?= $urutkan ===
                                        "nama_az"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Nama A–Z
                                </option>

                                <option
                                    value="nama_za"
                                    <?= $urutkan ===
                                        "nama_za"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Nama Z–A
                                </option>

                                <option
                                    value="terbaru"
                                    <?= $urutkan ===
                                        "terbaru"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Catatan Terbaru
                                </option>

                                <option
                                    value="terlama"
                                    <?= $urutkan ===
                                        "terlama"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Catatan Terlama
                                </option>

                                <option
                                    value="riwayat_terbanyak"
                                    <?= $urutkan ===
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
                                class="btn btn-primary
                                flex-fill"
                            >
                                <i class="bi bi-funnel-fill"></i>
                                Terapkan
                            </button>

                            <a
                                href="riwayat_kesehatan.php"
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

                </div>

                <div
                    class="d-flex flex-wrap
                    justify-content-between
                    align-items-center gap-2 mb-3"
                >

                    <span class="text-muted small">
                        Total:
                        <strong>
                            <?= $totalData; ?>
                        </strong>
                        balita
                    </span>

                    <div class="d-flex flex-wrap gap-2">

                        <?php if (
                            $roleAktif === "petugas_kia"
                        ): ?>

                            <span class="badge badge-primary">
                                <i class="bi bi-person-check"></i>
                                Kelola per Balita
                            </span>

                        <?php else: ?>

                            <span class="badge badge-info">
                                <i class="bi bi-eye"></i>
                                Mode lihat
                            </span>

                        <?php endif; ?>

                        <?php if (
                            $roleAktif === "dinkes"
                        ): ?>

                            <span class="badge badge-info">
                                <i class="bi bi-buildings"></i>
                                Semua Puskesmas
                            </span>

                        <?php endif; ?>

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
                                    Balita
                                </th>

                                <th>
                                    Puskesmas
                                </th>

                                <th class="text-center">
                                    Jumlah Riwayat
                                </th>

                                <th>
                                    Catatan Terakhir
                                </th>

                                <th class="text-center">
                                    Red Flag Terakhir
                                </th>

                                <th class="text-center">
                                    Rujukan Terakhir
                                </th>

                                <th
                                    class="text-center"
                                    style="min-width: 250px;"
                                >
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

                                <?php

                                $jumlahRiwayat =
                                    (int) (
                                        $d[
                                            "jumlah_riwayat"
                                        ]
                                        ?? 0
                                    );

                                $statusRedFlag =
                                    trim(
                                        (string) (
                                            $d[
                                                "status_red_flag_terbaru"
                                            ]
                                            ?? ""
                                        )
                                    );

                                if ($jumlahRiwayat === 0) {
                                    $kelasRedFlag =
                                        "bg-secondary";
                                    $labelRedFlag =
                                        "Belum ada";
                                    $ikonRedFlag =
                                        "bi-dash-circle";
                                } elseif (
                                    $statusRedFlag === "Ada"
                                ) {
                                    $kelasRedFlag =
                                        "bg-danger";
                                    $labelRedFlag =
                                        "Ada";
                                    $ikonRedFlag =
                                        "bi-exclamation-octagon";
                                } elseif (
                                    $statusRedFlag === "Tidak ada"
                                ) {
                                    $kelasRedFlag =
                                        "bg-success";
                                    $labelRedFlag =
                                        "Tidak ada";
                                    $ikonRedFlag =
                                        "bi-check-circle";
                                } else {
                                    $kelasRedFlag =
                                        "bg-warning text-dark";
                                    $labelRedFlag =
                                        $statusRedFlag !== ""
                                            ? $statusRedFlag
                                            : "Belum dinilai";
                                    $ikonRedFlag =
                                        "bi-clock-history";
                                }

                                $statusRujukan =
                                    trim(
                                        (string) (
                                            $d[
                                                "status_rujukan_terbaru"
                                            ]
                                            ?? ""
                                        )
                                    );

                                $statusRujukanLower =
                                    strtolower(
                                        $statusRujukan
                                    );

                                if ($jumlahRiwayat === 0) {
                                    $kelasRujukan =
                                        "bg-secondary";
                                    $labelRujukan =
                                        "Belum ada";
                                } elseif (
                                    $statusRujukanLower === ""
                                    || $statusRujukanLower ===
                                        "tidak perlu"
                                ) {
                                    $kelasRujukan =
                                        "bg-success";
                                    $labelRujukan =
                                        $statusRujukan !== ""
                                            ? $statusRujukan
                                            : "Tidak Perlu";
                                } elseif (
                                    str_contains(
                                        $statusRujukanLower,
                                        "rekomend"
                                    )
                                ) {
                                    $kelasRujukan =
                                        "bg-warning text-dark";
                                    $labelRujukan =
                                        $statusRujukan;
                                } elseif (
                                    str_contains(
                                        $statusRujukanLower,
                                        "rujuk"
                                    )
                                ) {
                                    $kelasRujukan =
                                        "bg-danger";
                                    $labelRujukan =
                                        $statusRujukan;
                                } else {
                                    $kelasRujukan =
                                        "bg-info";
                                    $labelRujukan =
                                        $statusRujukan;
                                }

                                $riwayatPenyakit =
                                    trim(
                                        (string) (
                                            $d[
                                                "riwayat_penyakit_terbaru"
                                            ]
                                            ?? ""
                                        )
                                    );

                                ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $no++; ?>
                                    </td>

                                    <td>

                                        <div
                                            class="d-flex
                                            align-items-center gap-2"
                                        >

                                            <span
                                                class="badge
                                                badge-primary"
                                            >
                                                <i
                                                    class="bi
                                                    bi-person-heart"
                                                ></i>
                                            </span>

                                            <div>

                                                <strong
                                                    class="d-block"
                                                >
                                                    <?= htmlspecialchars(
                                                        $d[
                                                            "nama_balita"
                                                        ],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                </strong>

                                                <small
                                                    class="text-muted"
                                                >
                                                    NIK:
                                                    <?= htmlspecialchars(
                                                        $d[
                                                            "nik_balita"
                                                        ],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                </small>

                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $d[
                                                "nama_puskesmas"
                                            ]
                                            ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge
                                            <?= $jumlahRiwayat > 0
                                                ? "badge-info"
                                                : "bg-secondary"; ?>"
                                        >
                                            <?= $jumlahRiwayat; ?>
                                            catatan
                                        </span>

                                    </td>

                                    <td>

                                        <?php if (
                                            $jumlahRiwayat > 0
                                        ): ?>

                                            <strong
                                                class="d-block"
                                            >
                                                <?= htmlspecialchars(
                                                    formatTanggalRiwayatKesehatan(
                                                        $d[
                                                            "tanggal_terakhir"
                                                        ]
                                                    ),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </strong>

                                            <small
                                                class="text-muted"
                                            >
                                                <?= htmlspecialchars(
                                                    $riwayatPenyakit !== ""
                                                        ? (
                                                            mb_strlen(
                                                                $riwayatPenyakit
                                                            ) > 55
                                                                ? mb_substr(
                                                                    $riwayatPenyakit,
                                                                    0,
                                                                    55
                                                                ) . "..."
                                                                : $riwayatPenyakit
                                                        )
                                                        : "Tidak ada keterangan penyakit",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </small>

                                        <?php else: ?>

                                            <span
                                                class="text-muted"
                                            >
                                                Belum ada
                                                riwayat kesehatan.
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge
                                            <?= $kelasRedFlag; ?>"
                                        >
                                            <i
                                                class="bi
                                                <?= $ikonRedFlag; ?>"
                                            ></i>
                                            <?= htmlspecialchars(
                                                $labelRedFlag,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge
                                            <?= $kelasRujukan; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $labelRujukan,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

                                            <a
                                                href="riwayat_kesehatan.php?id_balita=<?= (int) $d["id_balita"]; ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i
                                                    class="bi
                                                    bi-clock-history"
                                                ></i>
                                                Riwayat
                                                <?php if (
                                                    $jumlahRiwayat > 0
                                                ): ?>
                                                    (<?= $jumlahRiwayat; ?>)
                                                <?php endif; ?>
                                            </a>

                                            <?php if (
                                                $roleAktif ===
                                                "petugas_kia"
                                                && !$puskesmasBelumTerhubung
                                            ): ?>

                                                <a
                                                    href="tambah_kesehatan.php?id_balita=<?= (int) $d["id_balita"]; ?>"
                                                    class="btn btn-primary btn-sm"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-plus-circle"
                                                    ></i>
                                                    Tambah Riwayat
                                                </a>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="8">

                                        <div class="empty-state">

                                            <div
                                                class="empty-state-icon"
                                            >
                                                <i
                                                    class="bi
                                                    bi-person-heart"
                                                ></i>
                                            </div>

                                            <h3>
                                                Balita tidak ditemukan
                                            </h3>

                                            <p>
                                                Tidak ada data balita
                                                yang sesuai dengan
                                                cakupan akun atau pencarian.
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
