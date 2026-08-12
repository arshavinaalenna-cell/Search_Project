<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_gizi"]);

$judulHalaman =
    "Verifikasi Hasil Deteksi | Sistem Deteksi Stunting";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header(
        "Location: hasil_deteksi.php?pesan=tidak_ditemukan"
    );
    exit;
}

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

mysqli_stmt_execute($stmtPuskesmas);

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
        "Location: hasil_deteksi.php?pesan=tidak_ditemukan"
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

/*
|--------------------------------------------------------------------------
| Ambil hasil deteksi yang boleh diverifikasi
|--------------------------------------------------------------------------
*/

$stmtData = mysqli_prepare(
    $conn,
    "SELECT
        hd.id_deteksi,
        hd.status_gizi,
        hd.status_stunting,
        hd.status_verifikasi,
        hd.catatan_verifikasi,
        hd.keputusan_konsultasi,
        hd.konsultasi_ditetapkan_oleh,
        hd.tanggal_keputusan_konsultasi,

        pa.tanggal_pengukuran,
        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,
        pa.lingkar_kepala,
        pa.lila,

        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,

        p.nama_puskesmas

     FROM hasil_deteksi AS hd

     INNER JOIN pengukuran_antropometri AS pa
        ON hd.id_pengukuran = pa.id_pengukuran

     INNER JOIN balita AS b
        ON pa.id_balita = b.id_balita

     LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas

     WHERE hd.id_deteksi = ?
     AND b.id_puskesmas = ?

     LIMIT 1"
);

if (!$stmtData) {
    die(
        "Gagal menyiapkan data hasil deteksi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtData,
    "ii",
    $id,
    $idPuskesmasAktif
);

mysqli_stmt_execute(
    $stmtData
);

$resultData =
    mysqli_stmt_get_result(
        $stmtData
    );

$dataDeteksi =
    mysqli_fetch_assoc(
        $resultData
    );

mysqli_stmt_close(
    $stmtData
);

if (!$dataDeteksi) {
    header(
        "Location: hasil_deteksi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Proses verifikasi dan keputusan kebutuhan konsultasi
|--------------------------------------------------------------------------
|
| Hanya hasil dengan kategori Risiko Stunting, Stunting, atau Stunting Berat
| yang dapat ditetapkan "Perlu konsultasi". Jika Petugas Gizi memilih
| "Perlu konsultasi" dan hasil sudah diverifikasi, sistem otomatis membuat
| satu tiket konsultasi untuk anak tersebut. Orang Tua tidak membuat tiket.
|
*/

$error = "";

$statusVerifikasi =
    $dataDeteksi["status_verifikasi"]
    ?? "Belum diverifikasi";

$catatanVerifikasi =
    $dataDeteksi["catatan_verifikasi"]
    ?? "";

$keputusanKonsultasi =
    $dataDeteksi["keputusan_konsultasi"]
    ?? "Belum ditentukan";

$statusStuntingNormalisasi = strtolower(
    trim((string) ($dataDeteksi["status_stunting"] ?? ""))
);

$kategoriBerisikoKonsultasi = in_array(
    $statusStuntingNormalisasi,
    [
        "risiko stunting",
        "stunting",
        "stunting berat"
    ],
    true
);

$daftarStatus = [
    "Belum diverifikasi",
    "Sudah diverifikasi",
    "Perlu pemeriksaan ulang"
];

$labelStatusVerifikasi = [
    "Belum diverifikasi" =>
        "Belum Diverifikasi",
    "Sudah diverifikasi" =>
        "Terverifikasi",
    "Perlu pemeriksaan ulang" =>
        "Perlu Pemeriksaan Ulang"
];

$daftarKeputusanKonsultasi = [
    "Perlu konsultasi",
    "Tidak perlu konsultasi"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $statusVerifikasi =
        trim($_POST["status_verifikasi"] ?? "");

    $catatanVerifikasi =
        trim($_POST["catatan_verifikasi"] ?? "");

    $keputusanKonsultasi =
        trim($_POST["keputusan_konsultasi"] ?? "");

    if (
        !in_array(
            $statusVerifikasi,
            $daftarStatus,
            true
        )
    ) {
        $error = "Status verifikasi tidak valid.";

    } elseif (
        $statusVerifikasi === "Perlu pemeriksaan ulang"
        && $catatanVerifikasi === ""
    ) {
        $error =
            "Catatan Petugas Gizi wajib diisi jika hasil memerlukan pemeriksaan ulang.";

    } elseif (
        $kategoriBerisikoKonsultasi
        && $statusVerifikasi === "Sudah diverifikasi"
        && !in_array(
            $keputusanKonsultasi,
            $daftarKeputusanKonsultasi,
            true
        )
    ) {
        $error =
            "Tentukan apakah balita perlu konsultasi dengan Ahli Gizi.";
    }

    /*
    |------------------------------------------------------------------------
    | Status selain kategori risiko tidak membuka konsultasi
    |------------------------------------------------------------------------
    */
    if (!$kategoriBerisikoKonsultasi) {
        $keputusanKonsultasi =
            $statusVerifikasi === "Sudah diverifikasi"
                ? "Tidak perlu konsultasi"
                : "Belum ditentukan";
    } elseif ($statusVerifikasi !== "Sudah diverifikasi") {
        $keputusanKonsultasi = "Belum ditentukan";
    }

    /*
    |------------------------------------------------------------------------
    | Konsultasi yang sudah dimulai tidak boleh dibatalkan dari verifikasi
    |------------------------------------------------------------------------
    */
    if ($error === "" && $kategoriBerisikoKonsultasi) {
        $stmtCekAktif = mysqli_prepare(
            $conn,
            "SELECT
                id_konsultasi,
                keluhan,
                hasil_konsultasi,
                tindak_lanjut
             FROM konsultasi
             WHERE id_deteksi = ?
             AND sumber_pengajuan = 'ahli_gizi'
             LIMIT 1"
        );

        if ($stmtCekAktif) {
            mysqli_stmt_bind_param($stmtCekAktif, "i", $id);
            mysqli_stmt_execute($stmtCekAktif);
            $hasilCekAktif = mysqli_stmt_get_result($stmtCekAktif);
            $dataCekAktif = mysqli_fetch_assoc($hasilCekAktif);
            mysqli_stmt_close($stmtCekAktif);

            if ($dataCekAktif) {
                $konsultasiSudahDimulai = (
                    trim((string) ($dataCekAktif["keluhan"] ?? "")) !== ""
                    || trim((string) ($dataCekAktif["hasil_konsultasi"] ?? "")) !== ""
                    || trim((string) ($dataCekAktif["tindak_lanjut"] ?? "")) !== ""
                );

                if (
                    $konsultasiSudahDimulai
                    && !(
                        $statusVerifikasi === "Sudah diverifikasi"
                        && $keputusanKonsultasi === "Perlu konsultasi"
                    )
                ) {
                    $error =
                        "Konsultasi sudah dimulai oleh Orang Tua sehingga keputusan konsultasi tidak dapat dibatalkan.";
                }
            }
        }
    }

    if ($error === "") {

        try {

            mysqli_begin_transaction($conn);

            if ($statusVerifikasi === "Belum diverifikasi") {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE hasil_deteksi AS hd
                     INNER JOIN pengukuran_antropometri AS pa
                        ON hd.id_pengukuran = pa.id_pengukuran
                     INNER JOIN balita AS b
                        ON pa.id_balita = b.id_balita
                     SET
                        hd.status_verifikasi = ?,
                        hd.catatan_verifikasi = ?,
                        hd.diverifikasi_oleh = NULL,
                        hd.tanggal_verifikasi = NULL,
                        hd.keputusan_konsultasi = 'Belum ditentukan',
                        hd.konsultasi_ditetapkan_oleh = NULL,
                        hd.tanggal_keputusan_konsultasi = NULL
                     WHERE hd.id_deteksi = ?
                     AND b.id_puskesmas = ?"
                );

                if (!$stmt) {
                    throw new Exception(
                        "Gagal menyiapkan verifikasi hasil deteksi: "
                        . mysqli_error($conn)
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssii",
                    $statusVerifikasi,
                    $catatanVerifikasi,
                    $id,
                    $idPuskesmasAktif
                );

            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE hasil_deteksi AS hd
                     INNER JOIN pengukuran_antropometri AS pa
                        ON hd.id_pengukuran = pa.id_pengukuran
                     INNER JOIN balita AS b
                        ON pa.id_balita = b.id_balita
                     SET
                        hd.status_verifikasi = ?,
                        hd.catatan_verifikasi = ?,
                        hd.diverifikasi_oleh = ?,
                        hd.tanggal_verifikasi = NOW(),
                        hd.keputusan_konsultasi = ?,
                        hd.konsultasi_ditetapkan_oleh = ?,
                        hd.tanggal_keputusan_konsultasi = NOW()
                     WHERE hd.id_deteksi = ?
                     AND b.id_puskesmas = ?"
                );

                if (!$stmt) {
                    throw new Exception(
                        "Gagal menyiapkan verifikasi hasil deteksi: "
                        . mysqli_error($conn)
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssisiii",
                    $statusVerifikasi,
                    $catatanVerifikasi,
                    $idUserAktif,
                    $keputusanKonsultasi,
                    $idUserAktif,
                    $id,
                    $idPuskesmasAktif
                );
            }

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(
                    "Verifikasi hasil deteksi gagal disimpan: "
                    . mysqli_stmt_error($stmt)
                );
            }

            mysqli_stmt_close($stmt);

            /*
            |------------------------------------------------------------------
            | Jika disetujui perlu konsultasi, buat tiket otomatis
            |------------------------------------------------------------------
            */
            if (
                $kategoriBerisikoKonsultasi
                && $statusVerifikasi === "Sudah diverifikasi"
                && $keputusanKonsultasi === "Perlu konsultasi"
            ) {

                $stmtCek = mysqli_prepare(
                    $conn,
                    "SELECT id_konsultasi
                     FROM konsultasi
                     WHERE id_deteksi = ?
                     LIMIT 1"
                );

                if (!$stmtCek) {
                    throw new Exception(
                        "Gagal memeriksa tiket konsultasi: "
                        . mysqli_error($conn)
                    );
                }

                mysqli_stmt_bind_param($stmtCek, "i", $id);
                mysqli_stmt_execute($stmtCek);
                $hasilCek = mysqli_stmt_get_result($stmtCek);
                $tiketAda = mysqli_fetch_assoc($hasilCek);
                mysqli_stmt_close($stmtCek);

                if ($tiketAda) {
                    $stmtTiket = mysqli_prepare(
                        $conn,
                        "UPDATE konsultasi
                         SET
                            id_balita = ?,
                            id_petugas = ?,
                            tanggal = CURDATE(),
                            sumber_pengajuan = 'ahli_gizi'
                         WHERE id_deteksi = ?"
                    );

                    if (!$stmtTiket) {
                        throw new Exception(
                            "Gagal memperbarui tiket konsultasi: "
                            . mysqli_error($conn)
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmtTiket,
                        "iii",
                        $dataDeteksi["id_balita"],
                        $idUserAktif,
                        $id
                    );
                } else {
                    $stmtTiket = mysqli_prepare(
                        $conn,
                        "INSERT INTO konsultasi
                        (
                            id_deteksi,
                            id_balita,
                            id_petugas,
                            tanggal,
                            keluhan,
                            hasil_konsultasi,
                            tindak_lanjut,
                            sumber_pengajuan
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            CURDATE(),
                            '',
                            NULL,
                            NULL,
                            'ahli_gizi'
                        )"
                    );

                    if (!$stmtTiket) {
                        throw new Exception(
                            "Gagal membuat tiket konsultasi: "
                            . mysqli_error($conn)
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmtTiket,
                        "iii",
                        $id,
                        $dataDeteksi["id_balita"],
                        $idUserAktif
                    );
                }

                if (!mysqli_stmt_execute($stmtTiket)) {
                    throw new Exception(
                        "Tiket konsultasi gagal disimpan: "
                        . mysqli_stmt_error($stmtTiket)
                    );
                }

                mysqli_stmt_close($stmtTiket);

            } else {

                /*
                |--------------------------------------------------------------
                | Jika keputusan dibatalkan, hapus hanya tiket otomatis
                | yang belum pernah ditanggapi. Riwayat yang sudah selesai
                | tidak dihapus.
                |--------------------------------------------------------------
                */
                $stmtBatal = mysqli_prepare(
                    $conn,
                    "DELETE FROM konsultasi
                     WHERE id_deteksi = ?
                     AND sumber_pengajuan = 'ahli_gizi'
                     AND (hasil_konsultasi IS NULL OR TRIM(hasil_konsultasi) = '')
                     AND (tindak_lanjut IS NULL OR TRIM(tindak_lanjut) = '')"
                );

                if (!$stmtBatal) {
                    throw new Exception(
                        "Gagal memperbarui keputusan konsultasi: "
                        . mysqli_error($conn)
                    );
                }

                mysqli_stmt_bind_param($stmtBatal, "i", $id);
                mysqli_stmt_execute($stmtBatal);
                mysqli_stmt_close($stmtBatal);
            }

            mysqli_commit($conn);

            header(
                "Location: detail_deteksi.php?id="
                . $id
                . "&pesan=verifikasi_berhasil"
            );
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Template
|--------------------------------------------------------------------------
*/

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
                        <i class="bi bi-check2-circle me-2"></i>
                        Verifikasi Hasil Deteksi
                    </h4>

                    <small class="text-muted">
                        Tinjau dan tetapkan status verifikasi
                        hasil deteksi <?= htmlspecialchars(
                            $dataDeteksi["nama_balita"] ?? "balita",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>.
                    </small>

                </div>

                <a
                    href="detail_deteksi.php?id=<?= $id; ?>"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

            </div>

            <div class="card-body">

                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </div>

                <?php endif; ?>

                <div class="row g-3 mb-4">

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="detail-item h-100">
                            <span class="detail-label">
                                Balita
                            </span>

                            <div class="detail-value">
                                <strong>
                                    <?= htmlspecialchars(
                                        $dataDeteksi["nama_balita"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </strong>
                            </div>

                            <div class="form-text">
                                NIK:
                                <?= htmlspecialchars(
                                    $dataDeteksi["nik_balita"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="detail-item h-100">
                            <span class="detail-label">
                                Status Stunting
                            </span>

                            <div class="detail-value">
                                <strong>
                                    <?= htmlspecialchars(
                                        $dataDeteksi["status_stunting"]
                                            ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </strong>
                            </div>

                            <div class="form-text">
                                Indikator: TB/U
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="detail-item h-100">
                            <span class="detail-label">
                                Status Gizi
                            </span>

                            <div class="detail-value">
                                <strong>
                                    <?= htmlspecialchars(
                                        $dataDeteksi["status_gizi"]
                                            ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </strong>
                            </div>

                            <div class="form-text">
                                Indikator: BB/PB atau BB/TB
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="detail-item h-100">
                            <span class="detail-label">
                                Puskesmas
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-hospital me-1"></i>
                                <?= htmlspecialchars(
                                    $dataDeteksi["nama_puskesmas"]
                                        ?? $namaPuskesmasAktif,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Verifikasi ini menetapkan
                    <strong>status hasil deteksi</strong>,
                    bukan memverifikasi identitas atau data balita.
                </div>

                <div class="mb-4">

                    <button
                        class="btn btn-outline-primary btn-sm"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#dataPendukungVerifikasi"
                        aria-expanded="false"
                        aria-controls="dataPendukungVerifikasi"
                    >
                        <i class="bi bi-clipboard2-data me-1"></i>
                        Lihat Data Pendukung
                    </button>

                    <div
                        class="collapse mt-3"
                        id="dataPendukungVerifikasi"
                    >
                        <div class="detail-item">

                            <div class="row g-3">

                                <div class="col-6 col-lg-3">
                                    <span class="detail-label">
                                        Tanggal Pengukuran
                                    </span>
                                    <div class="detail-value">
                                        <?= !empty(
                                            $dataDeteksi[
                                                "tanggal_pengukuran"
                                            ]
                                        )
                                            ? date(
                                                "d-m-Y",
                                                strtotime(
                                                    $dataDeteksi[
                                                        "tanggal_pengukuran"
                                                    ]
                                                )
                                            )
                                            : "-"; ?>
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <span class="detail-label">
                                        Umur
                                    </span>
                                    <div class="detail-value">
                                        <?= htmlspecialchars(
                                            (string) (
                                                $dataDeteksi[
                                                    "umur_bulan"
                                                ]
                                                ?? "-"
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        bulan
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <span class="detail-label">
                                        Berat Badan
                                    </span>
                                    <div class="detail-value">
                                        <?= htmlspecialchars(
                                            (string) (
                                                $dataDeteksi[
                                                    "berat_badan"
                                                ]
                                                ?? "-"
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        kg
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <span class="detail-label">
                                        Tinggi / Panjang Badan
                                    </span>
                                    <div class="detail-value">
                                        <?= htmlspecialchars(
                                            (string) (
                                                $dataDeteksi[
                                                    "tinggi_panjang_badan"
                                                ]
                                                ?? "-"
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        cm
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <span class="detail-label">
                                        Lingkar Kepala
                                    </span>
                                    <div class="detail-value">
                                        <?= htmlspecialchars(
                                            (string) (
                                                $dataDeteksi[
                                                    "lingkar_kepala"
                                                ]
                                                ?? "-"
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        cm
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <span class="detail-label">
                                        LILA
                                    </span>
                                    <div class="detail-value">
                                        <?= htmlspecialchars(
                                            (string) (
                                                $dataDeteksi[
                                                    "lila"
                                                ]
                                                ?? "-"
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        cm
                                    </div>
                                </div>

                                <div class="col-12 col-lg-6">
                                    <span class="detail-label">
                                        Indikator Analisis
                                    </span>
                                    <div class="detail-value">
                                        TB/U untuk status stunting
                                        dan BB/PB atau BB/TB untuk status gizi.
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>

                </div>

                <?php if ($kategoriBerisikoKonsultasi): ?>

                    <div class="alert alert-warning">
                        <i class="bi bi-bell-fill me-1"></i>
                        Status anak adalah
                        <strong><?= htmlspecialchars(
                            $dataDeteksi["status_stunting"] ?? "-",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?></strong>.
                        Setelah hasil dinyatakan <strong>Terverifikasi</strong>,
                        Ahli Gizi wajib menentukan apakah Orang Tua perlu
                        melakukan konsultasi gizi.
                    </div>

                <?php else: ?>

                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Status stunting anak saat ini tidak termasuk kategori
                        Risiko Stunting, Stunting, atau Stunting Berat sehingga
                        sistem tidak membuka kebutuhan konsultasi otomatis.
                    </div>

                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">

                        <label
                            for="status_verifikasi"
                            class="form-label"
                        >
                            Status Verifikasi
                            <span class="text-danger">*</span>
                        </label>

                        <select
                            name="status_verifikasi"
                            id="status_verifikasi"
                            class="form-select"
                            required
                        >

                            <?php foreach ($daftarStatus as $status): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                    <?= $statusVerifikasi === $status
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= htmlspecialchars(
                                        $labelStatusVerifikasi[
                                            $status
                                        ] ?? $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            Pilih hasil peninjauan Petugas Gizi:
                            Belum Diverifikasi, Terverifikasi,
                            atau Perlu Pemeriksaan Ulang.
                        </div>

                    </div>

                    <?php if ($kategoriBerisikoKonsultasi): ?>

                        <div class="form-group" id="blokKeputusanKonsultasi">

                            <label
                                for="keputusan_konsultasi"
                                class="form-label"
                            >
                                Kebutuhan Konsultasi Orang Tua
                                <span id="tandaKonsultasiWajib" class="text-danger">*</span>
                            </label>

                            <select
                                name="keputusan_konsultasi"
                                id="keputusan_konsultasi"
                                class="form-select"
                            >
                                <option value="">
                                    -- Tentukan kebutuhan konsultasi --
                                </option>
                                <option
                                    value="Perlu konsultasi"
                                    <?= $keputusanKonsultasi === "Perlu konsultasi"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Perlu Konsultasi
                                </option>
                                <option
                                    value="Tidak perlu konsultasi"
                                    <?= $keputusanKonsultasi === "Tidak perlu konsultasi"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Tidak Perlu Konsultasi
                                </option>
                            </select>

                            <div class="form-text">
                                Jika memilih <strong>Perlu Konsultasi</strong>,
                                sistem otomatis membuat tiket konsultasi dan
                                menampilkan pemberitahuan pada akun Kader,
                                Petugas KIA, serta Orang Tua.
                            </div>

                        </div>

                    <?php endif; ?>

                    <div class="form-group">

                        <label
                            for="catatan_verifikasi"
                            class="form-label"
                        >
                            Catatan Petugas Gizi
                            <span
                                id="tandaCatatanWajib"
                                class="text-danger d-none"
                            >*</span>
                        </label>

                        <textarea
                            name="catatan_verifikasi"
                            id="catatan_verifikasi"
                            class="form-control"
                            rows="4"
                            placeholder="Tambahkan catatan verifikasi bila diperlukan."
                        ><?= htmlspecialchars(
                            $catatanVerifikasi,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?></textarea>

                        <div class="form-text">
                            Wajib diisi jika memilih
                            <strong>Perlu Pemeriksaan Ulang</strong>.
                            Untuk status Terverifikasi,
                            catatan bersifat opsional.
                        </div>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            <i class="bi bi-floppy"></i>
                            Simpan Verifikasi
                        </button>

                        <a
                            href="detail_deteksi.php?id=<?= $id; ?>"
                            class="btn btn-light"
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

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        const status =
            document.getElementById(
                "status_verifikasi"
            );

        const catatan =
            document.getElementById(
                "catatan_verifikasi"
            );

        const tandaWajib =
            document.getElementById(
                "tandaCatatanWajib"
            );

        const keputusanKonsultasi =
            document.getElementById(
                "keputusan_konsultasi"
            );

        const tandaKonsultasiWajib =
            document.getElementById(
                "tandaKonsultasiWajib"
            );

        function aturCatatan() {

            if (!status || !catatan) {
                return;
            }

            const wajib =
                status.value ===
                "Perlu pemeriksaan ulang";

            catatan.required =
                wajib;

            if (tandaWajib) {
                tandaWajib.classList.toggle(
                    "d-none",
                    !wajib
                );
            }
        }

        status.addEventListener(
            "change",
            function () {
                aturCatatan();
                aturKonsultasi();
            }
        );

        function aturKonsultasi() {
            if (!keputusanKonsultasi || !status) {
                return;
            }

            const wajib =
                status.value === "Sudah diverifikasi";

            keputusanKonsultasi.disabled = !wajib;
            keputusanKonsultasi.required = wajib;

            if (tandaKonsultasiWajib) {
                tandaKonsultasiWajib.classList.toggle(
                    "d-none",
                    !wajib
                );
            }
        }

        aturKonsultasi();

        aturCatatan();
    }
);
</script>

<?php require_once "../includes/footer.php"; ?>