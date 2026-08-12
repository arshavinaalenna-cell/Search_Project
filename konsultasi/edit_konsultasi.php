<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_gizi"]);

$judulHalaman = "Edit Konsultasi | Sistem Deteksi Stunting";

$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$pesanError = "";

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Petugas Gizi aktif
|--------------------------------------------------------------------------
*/

$stmtPuskesmas = mysqli_prepare(
    $conn,
    "SELECT
        u.id_puskesmas,
        p.nama_puskesmas
     FROM pengguna AS u
     LEFT JOIN puskesmas AS p
        ON u.id_puskesmas = p.id_puskesmas
     WHERE u.id_user = ?
     AND u.role = 'petugas_gizi'
     LIMIT 1"
);

if (!$stmtPuskesmas) {
    die(
        "Gagal memeriksa Puskesmas Petugas Gizi: "
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
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
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

$idKonsultasi = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idKonsultasi) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data konsultasi
|--------------------------------------------------------------------------
|
| Petugas Gizi hanya dapat membuka konsultasi yang ditugaskan kepadanya.
|
*/

$stmtKonsultasi = mysqli_prepare(
    $conn,
    "SELECT
        k.id_konsultasi,
        k.id_deteksi,
        k.sumber_pengajuan,
        k.id_balita,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        k.status_konsultasi,
        k.tanggal_selesai,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu,
        b.id_puskesmas,
        p.nama_puskesmas,
        hd.status_stunting,
        hd.keputusan_konsultasi
     FROM konsultasi k
     INNER JOIN balita b
        ON k.id_balita = b.id_balita
     LEFT JOIN puskesmas p
        ON b.id_puskesmas = p.id_puskesmas
     INNER JOIN hasil_deteksi hd
        ON k.id_deteksi = hd.id_deteksi
     WHERE k.id_konsultasi = ?
     AND k.sumber_pengajuan = 'ahli_gizi'
     AND hd.keputusan_konsultasi = 'Perlu konsultasi' 
     AND k.id_petugas = ?
     AND b.id_puskesmas = ?
     LIMIT 1"
);

if (!$stmtKonsultasi) {
    die(
        "Gagal menyiapkan data konsultasi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtKonsultasi,
    "iii",
    $idKonsultasi,
    $idUserAktif,
    $idPuskesmasAktif
);

mysqli_stmt_execute($stmtKonsultasi);

$hasilKonsultasi = mysqli_stmt_get_result(
    $stmtKonsultasi
);

$dataKonsultasi = mysqli_fetch_assoc(
    $hasilKonsultasi
);

mysqli_stmt_close($stmtKonsultasi);

if (!$dataKonsultasi) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/* Ahli Gizi menanggapi setelah Orang Tua memulai konsultasi. */
if (trim((string) ($dataKonsultasi["keluhan"] ?? "")) === "") {
    header(
        "Location: data_konsultasi.php?pesan=menunggu_orang_tua"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$hasilKonsultasiForm =
    $dataKonsultasi["hasil_konsultasi"] ?? "";

$tindakLanjut =
    $dataKonsultasi["tindak_lanjut"] ?? "";

$konsultasiSudahSelesai =
    strtolower(
        trim(
            (string) (
                $dataKonsultasi["status_konsultasi"]
                ?? "aktif"
            )
        )
    ) === "selesai";

if ($konsultasiSudahSelesai) {
    header(
        "Location: riwayat_konsultasi.php?pesan=terkunci"
    );
    exit;
}

$belumDitanggapi = (
    trim(
        (string) $hasilKonsultasiForm
    ) === ""
);

$judulForm = $belumDitanggapi
    ? "Tanggapi Konsultasi"
    : "Edit Hasil Konsultasi";

$statusKonsultasi = $belumDitanggapi
    ? "Menunggu Tanggapan"
    : "Sudah Ditanggapi";

$kelasStatusKonsultasi = $belumDitanggapi
    ? "bg-warning text-dark"
    : "bg-success";

$ikonStatusKonsultasi = $belumDitanggapi
    ? "bi-hourglass-split"
    : "bi-check-circle";

/*
|--------------------------------------------------------------------------
| Memproses perubahan konsultasi
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $aksiForm =
        strtolower(
            trim(
                (string) (
                    $_POST["aksi"]
                    ?? "simpan_sementara"
                )
            )
        );

    if (
        !in_array(
            $aksiForm,
            ["simpan_sementara", "selesaikan"],
            true
        )
    ) {
        $aksiForm = "simpan_sementara";
    }

    $hasilKonsultasiForm = trim(
        $_POST["hasil_konsultasi"] ?? ""
    );

    $tindakLanjut = trim(
        $_POST["tindak_lanjut"] ?? ""
    );

    if (
        $aksiForm === "selesaikan"
        && $hasilKonsultasiForm === ""
    ) {
        $pesanError =
            "Hasil konsultasi wajib diisi sebelum konsultasi diselesaikan.";
    } elseif (
        $aksiForm === "selesaikan"
        && $tindakLanjut === ""
    ) {
        $pesanError =
            "Tindak lanjut wajib diisi sebelum konsultasi diselesaikan.";
    }

    if ($pesanError === "") {
        $statusBaru =
            $aksiForm === "selesaikan"
                ? "selesai"
                : "aktif";

        $stmtUpdate = mysqli_prepare(
            $conn,
            "UPDATE konsultasi AS k
             INNER JOIN balita AS b
                ON k.id_balita = b.id_balita
             INNER JOIN hasil_deteksi AS hd
                ON k.id_deteksi = hd.id_deteksi
             SET
                k.hasil_konsultasi = ?,
                k.tindak_lanjut = ?,
                k.status_konsultasi = ?,
                k.tanggal_selesai =
                    CASE
                        WHEN ? = 'selesai'
                            THEN NOW()
                        ELSE NULL
                    END
             WHERE k.id_konsultasi = ?
             AND k.id_petugas = ?
             AND b.id_puskesmas = ?
             AND k.sumber_pengajuan = 'ahli_gizi'
             AND hd.keputusan_konsultasi = 'Perlu konsultasi'"
        );

        if (!$stmtUpdate) {
            $pesanError =
                "Gagal menyiapkan perubahan konsultasi: "
                . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmtUpdate,
                "ssssiii",
                $hasilKonsultasiForm,
                $tindakLanjut,
                $statusBaru,
                $statusBaru,
                $idKonsultasi,
                $idUserAktif,
                $idPuskesmasAktif
            );

            if (mysqli_stmt_execute($stmtUpdate)) {
                mysqli_stmt_close($stmtUpdate);

                if ($aksiForm === "selesaikan") {
                    header(
                        "Location: riwayat_konsultasi.php?pesan=selesai"
                    );
                } else {
                    header(
                        "Location: detail_konsultasi.php?id="
                        . (int) $idKonsultasi
                        . "&pesan=simpan_sementara"
                    );
                }

                exit;
            }

            $pesanError =
                "Data konsultasi gagal diperbarui: "
                . mysqli_stmt_error($stmtUpdate);

            mysqli_stmt_close($stmtUpdate);
        }
    }
}

$belumDitanggapi = (
    trim(
        (string) $hasilKonsultasiForm
    ) === ""
);

$judulForm = $belumDitanggapi
    ? "Tanggapi Konsultasi"
    : "Edit Hasil Konsultasi";

$statusKonsultasi = $belumDitanggapi
    ? "Menunggu Tanggapan"
    : "Sudah Ditanggapi";

$kelasStatusKonsultasi = $belumDitanggapi
    ? "bg-warning text-dark"
    : "bg-success";

$ikonStatusKonsultasi = $belumDitanggapi
    ? "bi-hourglass-split"
    : "bi-check-circle";

$tanggalTampil = "-";

if (!empty($dataKonsultasi["tanggal"])) {
    $tanggalTampil = date(
        "d-m-Y",
        strtotime(
            $dataKonsultasi["tanggal"]
        )
    );
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="card content-card mb-4">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        <?= htmlspecialchars(
                            $judulForm,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </h4>

                    <small class="text-muted">
                        Konsultasi ini telah direkomendasikan oleh Ahli Gizi.
                        Isi hasil konsultasi dan rencana tindak lanjut
                        setelah konsultasi dengan Orang Tua dilakukan.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <span
                        class="badge badge-info
                        d-inline-flex align-items-center px-3"
                    >
                        <i class="bi bi-hospital me-1"></i>
                        <?= htmlspecialchars(
                            $dataKonsultasi[
                                "nama_puskesmas"
                            ]
                                ?? $namaPuskesmasAktif,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </span>

                    <span
                        class="badge
                        <?= $kelasStatusKonsultasi; ?>"
                    >
                        <i
                            class="bi
                            <?= $ikonStatusKonsultasi; ?>"
                        ></i>

                        <?= htmlspecialchars(
                            $statusKonsultasi,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </span>

                    <a
                        href="data_konsultasi.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

            </div>

            <div class="card-body">

                <div class="alert alert-warning">
                    <i class="bi bi-bell-fill me-1"></i>
                    Dasar konsultasi: <strong><?= htmlspecialchars($dataKonsultasi["status_stunting"] ?? "-", ENT_QUOTES, "UTF-8"); ?></strong>.
                    Tiket ini dibuat otomatis setelah Ahli Gizi menetapkan anak perlu konsultasi.
                </div>

                <?php if ($pesanError !== ""): ?>

                    <div
                        class="alert alert-danger
                        alert-dismissible fade show"
                        role="alert"
                    >
                        <i class="bi bi-x-circle me-1"></i>

                        <?= htmlspecialchars(
                            $pesanError,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>
                    </div>

                <?php endif; ?>

                <div class="row g-3">

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Tanggal Konsultasi
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-calendar3 me-1"></i>

                                <?= htmlspecialchars(
                                    $tanggalTampil,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Balita
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-person-heart me-1"></i>

                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nama_balita"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                NIK Balita
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nik_balita"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Ibu
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nama_ibu"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12">

                        <div class="detail-item">

                            <span class="detail-label">
                                Keluhan Orang Tua
                            </span>

                            <div class="detail-value">
                                <?= nl2br(
                                    htmlspecialchars(
                                        $dataKonsultasi[
                                            "keluhan"
                                        ] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ); ?>
                            </div>

                            <div class="form-text mt-2">
                                Keluhan ini dikirim oleh Orang Tua dan
                                tidak dapat diedit oleh Petugas Gizi.
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Hasil & Tindak Lanjut
                    </h4>

                    <small class="text-muted">
                        Simpan sementara jika konsultasi masih berjalan.
                        Klik <strong>Selesaikan Konsultasi</strong> hanya jika pelayanan benar-benar sudah selesai.
                    </small>

                </div>

                <span class="badge badge-info">
                    <i class="bi bi-heart-pulse"></i>
                    Petugas Gizi
                </span>

            </div>

            <div class="card-body">

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">

                            <label
                                for="hasil_konsultasi"
                                class="form-label"
                            >
                                Hasil Konsultasi
                            </label>

                            <textarea
                                id="hasil_konsultasi"
                                name="hasil_konsultasi"
                                class="form-control"
                                rows="6"
                                placeholder="Tuliskan hasil pemeriksaan dan saran gizi"
                                required
                            ><?= htmlspecialchars(
                                $hasilKonsultasiForm,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Catat hasil konsultasi, edukasi,
                                atau saran gizi yang diberikan.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="tindak_lanjut"
                                class="form-label"
                            >
                                Tindak Lanjut
                            </label>

                            <textarea
                                id="tindak_lanjut"
                                name="tindak_lanjut"
                                class="form-control"
                                rows="6"
                                placeholder="Tuliskan rencana pemantauan atau tindakan berikutnya"
                                required
                            ><?= htmlspecialchars(
                                $tindakLanjut,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Catat rencana pemantauan,
                                kunjungan ulang, atau tindakan berikutnya.
                            </div>

                        </div>

                    </div>

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            name="aksi"
                            value="simpan_sementara"
                            class="btn btn-outline-primary"
                            formnovalidate
                        >
                            <i class="bi bi-save"></i>
                            Simpan Sementara
                        </button>

                        <button
                            type="submit"
                            name="aksi"
                            value="selesaikan"
                            class="btn btn-success"
                            onclick="return confirm(
                                'Selesaikan konsultasi? Setelah selesai, konsultasi akan dikunci dan masuk ke Riwayat Konsultasi.'
                            );"
                        >
                            <i class="bi bi-check-circle"></i>
                            Selesaikan Konsultasi
                        </button>

                        <a
                            href="detail_konsultasi.php?id=<?= (int) $idKonsultasi; ?>"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-x-circle"></i>
                            Batal
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>