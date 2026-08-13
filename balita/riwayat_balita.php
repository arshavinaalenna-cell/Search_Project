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
