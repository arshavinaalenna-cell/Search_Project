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

$judulHalaman = "Detail Riwayat Kesehatan | Sistem Deteksi Stunting";

$idRiwayat = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idRiwayat) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";

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
| Mengambil Puskesmas akun aktif
|--------------------------------------------------------------------------
|
| Petugas KIA, Petugas Gizi, dan Kepala Puskesmas hanya boleh membuka
| detail balita dari Puskesmas akun masing-masing.
|
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
            $dataPuskesmas["id_puskesmas"]
        )
    ) {
        header(
            "Location: riwayat_kesehatan.php"
        );
        exit;
    }

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

/*
|--------------------------------------------------------------------------
| Menyiapkan filter detail berdasarkan role
|--------------------------------------------------------------------------
*/

$whereTambahan = "";
$tipeBind = "i";
$parameterBind = [
    $idRiwayat
];

if ($roleAktif === "orang_tua") {

    $whereTambahan =
        " AND b.id_user = ?";

    $tipeBind = "ii";

    $parameterBind[] =
        $idUserAktif;

} elseif ($roleBerbasisPuskesmas) {

    $whereTambahan =
        " AND b.id_puskesmas = ?";

    $tipeBind = "ii";

    $parameterBind[] =
        $idPuskesmasAktif;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        rk.id_riwayat,
        rk.id_balita,
        rk.riwayat_penyakit,
        rk.riwayat_imunisasi,
        rk.riwayat_perawatan,
        rk.penyakit_penyerta,
        rk.status_red_flag,
        rk.catatan_red_flag,
        rk.penilai_red_flag,
        rk.tanggal_penilaian,
        rk.status_rujukan,
        rk.rekomendasi_rujukan,
        rk.catatan_kia,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu,
        b.id_user,
        b.id_puskesmas,
        p.nama_puskesmas,
        penilai.nama AS nama_penilai_red_flag
     FROM riwayat_kesehatan rk
     INNER JOIN balita b
        ON rk.id_balita = b.id_balita
     LEFT JOIN puskesmas p
        ON b.id_puskesmas = p.id_puskesmas
     LEFT JOIN pengguna penilai
        ON rk.penilai_red_flag = penilai.id_user
     WHERE rk.id_riwayat = ?
     "
     . $whereTambahan
     . "
     LIMIT 1"
);

if (!$stmt) {
    die(
        "Gagal menyiapkan data detail: "
        . mysqli_error($conn)
    );
}

if ($tipeBind === "ii") {

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $parameterBind[0],
        $parameterBind[1]
    );

} else {

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $parameterBind[0]
    );
}

mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasil);

mysqli_stmt_close($stmt);

if (!$data) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

$bolehEdit = $roleAktif === "petugas_kia";

$statusRedFlag =
    trim(
        (string) (
            $data["status_red_flag"]
            ?? "Belum dinilai"
        )
    );

$catatanRedFlag =
    trim(
        (string) (
            $data["catatan_red_flag"]
            ?? ""
        )
    );

if ($statusRedFlag === "Ada") {
    $kelasRedFlag =
        "bg-danger";
    $ikonRedFlag =
        "bi-exclamation-octagon";
} elseif ($statusRedFlag === "Tidak ada") {
    $kelasRedFlag =
        "bg-success";
    $ikonRedFlag =
        "bi-check-circle";
} else {
    $kelasRedFlag =
        "bg-warning text-dark";
    $ikonRedFlag =
        "bi-clock-history";
}

$statusRujukan = trim(
    (string) (
        $data["status_rujukan"] ?? ""
    )
);

$statusRujukanLower =
    strtolower($statusRujukan);

if (
    $statusRujukanLower === ""
    || $statusRujukanLower === "tidak perlu"
) {
    $kelasRujukan = "bg-success";
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
                        Detail Riwayat Kesehatan
                    </h4>

                    <small class="text-muted">
                        Informasi lengkap riwayat kesehatan,
                        evaluasi KIA, red flag, dan rujukan balita.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        !empty(
                            $data["nama_puskesmas"]
                        )
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex
                            align-items-center
                            px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= htmlspecialchars(
                                $data["nama_puskesmas"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </span>

                    <?php endif; ?>

                    <a
                        href="riwayat_kesehatan.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if ($bolehEdit): ?>

                        <a
                            href="edit_kesehatan.php?id=<?= (int) $data["id_riwayat"]; ?>"
                            class="btn btn-warning btn-sm"
                        >
                            <i class="bi bi-pencil-square"></i>
                            Edit
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <div class="row g-3 mb-3">

                    <div class="col-12 col-lg-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Balita
                            </span>

                            <div class="detail-value">
                                <i
                                    class="bi bi-person-heart
                                    me-1"
                                ></i>

                                <?= htmlspecialchars(
                                    $data["nama_balita"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                NIK Balita
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $data["nik_balita"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Ibu
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $data["nama_ibu"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12">

                        <div class="detail-item">

                            <span class="detail-label">
                                Puskesmas
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-hospital me-1"></i>
                                <?= htmlspecialchars(
                                    $data["nama_puskesmas"]
                                        ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="row g-3">

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Riwayat Penyakit
                            </span>

                            <div class="detail-value">
                                <?= nl2br(
                                    htmlspecialchars(
                                        $data["riwayat_penyakit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Riwayat Imunisasi
                            </span>

                            <div class="detail-value">
                                <?= nl2br(
                                    htmlspecialchars(
                                        $data["riwayat_imunisasi"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12">

                        <div class="detail-item">

                            <span class="detail-label">
                                Riwayat Perawatan
                            </span>

                            <div class="detail-value">
                                <?= nl2br(
                                    htmlspecialchars(
                                        $data["riwayat_perawatan"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ); ?>
                            </div>

                        </div>

                    </div>

                </div>

                <hr>

                <div class="mb-3">

                    <h6 class="mb-1">
                        Evaluasi KIA
                    </h6>

                    <small class="text-muted">
                        Penyakit penyerta, red flag,
                        kebutuhan rujukan, dan catatan Petugas KIA.
                    </small>

                </div>

                <div class="row g-3">

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Penyakit Penyerta
                            </span>

                            <div class="detail-value">

                                <?php if (
                                    trim(
                                        (string) (
                                            $data[
                                                "penyakit_penyerta"
                                            ] ?? ""
                                        )
                                    ) !== ""
                                ): ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $data[
                                                "penyakit_penyerta"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    ); ?>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Tidak ada
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Status Red Flag
                            </span>

                            <div class="detail-value">

                                <span
                                    class="badge
                                    <?= $kelasRedFlag; ?>"
                                >
                                    <i
                                        class="bi
                                        <?= $ikonRedFlag; ?>"
                                    ></i>
                                    <?= htmlspecialchars(
                                        $statusRedFlag,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </span>

                                <?php if (
                                    $statusRedFlag === "Ada"
                                    && $catatanRedFlag !== ""
                                ): ?>

                                    <div class="mt-2">
                                        <?= nl2br(
                                            htmlspecialchars(
                                                $catatanRedFlag,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            )
                                        ); ?>
                                    </div>

                                <?php endif; ?>

                                <div class="mt-3 small text-muted">

                                    <div>
                                        Penilai:
                                        <strong>
                                            <?= htmlspecialchars(
                                                $data[
                                                    "nama_penilai_red_flag"
                                                ]
                                                    ?? "-",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </strong>
                                    </div>

                                    <div>
                                        Tanggal penilaian:
                                        <strong>
                                            <?= !empty(
                                                $data[
                                                    "tanggal_penilaian"
                                                ]
                                            )
                                                ? htmlspecialchars(
                                                    date(
                                                        "d-m-Y H:i",
                                                        strtotime(
                                                            $data[
                                                                "tanggal_penilaian"
                                                            ]
                                                        )
                                                    ),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                                : "-"; ?>
                                        </strong>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Status Rujukan
                            </span>

                            <div class="detail-value">

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

                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-8">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Rekomendasi Rujukan
                            </span>

                            <div class="detail-value">

                                <?php if (
                                    trim(
                                        (string) (
                                            $data[
                                                "rekomendasi_rujukan"
                                            ] ?? ""
                                        )
                                    ) !== ""
                                ): ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $data[
                                                "rekomendasi_rujukan"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    ); ?>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Tidak ada rekomendasi.
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                    <div class="col-12">

                        <div class="detail-item">

                            <span class="detail-label">
                                Catatan KIA
                            </span>

                            <div class="detail-value">

                                <?php if (
                                    trim(
                                        (string) (
                                            $data[
                                                "catatan_kia"
                                            ] ?? ""
                                        )
                                    ) !== ""
                                ): ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $data[
                                                "catatan_kia"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    ); ?>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Belum ada catatan KIA.
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>