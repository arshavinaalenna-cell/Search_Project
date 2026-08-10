<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
*/

cekRole([
    "petugas_gizi",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman =
    "Detail Laporan Stunting | Sistem Deteksi Stunting";

$roleAktif =
    $_SESSION["role"] ?? "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$aksesPuskesmasTerbatas =
    in_array(
        $roleAktif,
        [
            "petugas_gizi",
            "kepala_puskesmas"
        ],
        true
    );

$idPuskesmasAktif = 0;

/*
|--------------------------------------------------------------------------
| Menentukan Puskesmas pengguna aktif
|--------------------------------------------------------------------------
|
| Petugas Gizi dan Kepala Puskesmas hanya boleh membuka detail laporan
| balita dari Puskesmas yang sama dengan akun mereka.
| Dinkes tetap dapat membuka seluruh wilayah.
|
*/

if ($aksesPuskesmasTerbatas) {

    $stmtPuskesmas = mysqli_prepare(
        $conn,
        "SELECT id_puskesmas
         FROM pengguna
         WHERE id_user = ?
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
        http_response_code(403);

        echo "
            <h2>Akses Ditolak</h2>
            <p>Akun ini belum terhubung dengan Puskesmas.</p>
            <a href='laporan_stunting.php'>Kembali ke Laporan</a>
        ";

        exit;
    }

    $idPuskesmasAktif =
        (int) $dataPuskesmas[
            "id_puskesmas"
        ];
}

/*
|--------------------------------------------------------------------------
| Fungsi bantuan
|--------------------------------------------------------------------------
*/

function amanDetailLaporan($nilai): string
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

function formatTanggalDetail($tanggal): string
{
    if (
        $tanggal === null
        || $tanggal === ""
        || $tanggal === "0000-00-00"
    ) {
        return "-";
    }

    $waktu = strtotime((string) $tanggal);

    if ($waktu === false) {
        return amanDetailLaporan($tanggal);
    }

    return date("d-m-Y", $waktu);
}

function kelasStatusDetail($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    if (
        in_array(
            $status,
            [
                "risiko rendah",
                "normal",
                "normal/sehat",
                "tidak stunting"
            ],
            true
        )
    ) {
        return "badge-success";
    }

    if (
        in_array(
            $status,
            [
                "risiko sedang",
                "risiko stunting",
                "pendek"
            ],
            true
        )
    ) {
        return "badge-warning";
    }

    if (
        in_array(
            $status,
            [
                "stunting berat",
                "sangat pendek",
                "severely stunted"
            ],
            true
        )
    ) {
        return "bg-dark text-white";
    }

    if (
        in_array(
            $status,
            [
                "risiko tinggi",
                "stunting"
            ],
            true
        )
    ) {
        return "badge-danger";
    }

    return "badge-info";
}


function kelasVerifikasiDetail($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    if ($status === "sudah diverifikasi") {
        return "badge-success";
    }

    if ($status === "perlu pemeriksaan ulang") {
        return "badge-warning";
    }

    return "badge-secondary";
}

function kelasRedFlagDetail($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    if ($status === "ada") {
        return "badge-danger";
    }

    if ($status === "tidak ada") {
        return "badge-success";
    }

    return "badge-warning";
}

/*
|--------------------------------------------------------------------------
| Validasi ID deteksi
|--------------------------------------------------------------------------
*/

$idDeteksi = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idDeteksi || $idDeteksi < 1) {
    header(
        "Location: laporan_stunting.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil detail laporan
|--------------------------------------------------------------------------
|
| Skrining yang dipakai adalah skrining terakhir milik balita.
| Untuk Petugas Gizi dan Kepala Puskesmas, detail juga dibatasi
| berdasarkan Puskesmas akun aktif.
|
*/

$filterPuskesmasDetail =
    $aksesPuskesmasTerbatas
        ? " AND b.id_puskesmas = ? "
        : "";

$stmtDetail = mysqli_prepare(
    $conn,
    "SELECT
        hd.id_deteksi,
        hd.id_pengukuran,
        hd.status_gizi,
        hd.status_stunting,
        hd.tanggal_deteksi,
        hd.status_verifikasi,
        hd.catatan_verifikasi,
        hd.diverifikasi_oleh,
        hd.tanggal_verifikasi,

        pa.tanggal_pengukuran,
        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,
        pa.lingkar_kepala,
        pa.lila,

        b.id_balita,
        b.id_puskesmas,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,
        b.tanggal_lahir,
        b.nama_posyandu,

        ps.nama_puskesmas,

        s.tinggi_badan_ibu,
        s.pendidikan_ibu,
        s.pekerjaan_ibu,
        s.lama_asi_eksklusif,
        s.mpasi,
        s.frekuensi_makan,
        s.protein_hewani,
        s.status_ekonomi,
        s.sanitasi,
        s.air_bersih,

        rk.status_red_flag,
        rk.catatan_red_flag,
        rk.penilai_red_flag,
        rk.tanggal_penilaian,
        rk.status_rujukan,
        rk.rekomendasi_rujukan,
        rk.catatan_kia,

        verifikator.nama AS nama_verifikator,
        penilai_rf.nama AS nama_penilai_red_flag

     FROM hasil_deteksi AS hd

     INNER JOIN pengukuran_antropometri AS pa
        ON hd.id_pengukuran = pa.id_pengukuran

     INNER JOIN balita AS b
        ON pa.id_balita = b.id_balita

     LEFT JOIN puskesmas AS ps
        ON b.id_puskesmas = ps.id_puskesmas

     LEFT JOIN skrining_awal AS s
        ON s.id_skrining = (
            SELECT MAX(s2.id_skrining)
            FROM skrining_awal AS s2
            WHERE s2.id_balita = b.id_balita
        )

     LEFT JOIN riwayat_kesehatan AS rk
        ON rk.id_riwayat = (
            SELECT MAX(rk2.id_riwayat)
            FROM riwayat_kesehatan AS rk2
            WHERE rk2.id_balita = b.id_balita
        )

     LEFT JOIN pengguna AS verifikator
        ON hd.diverifikasi_oleh =
            verifikator.id_user

     LEFT JOIN pengguna AS penilai_rf
        ON rk.penilai_red_flag =
            penilai_rf.id_user

     WHERE hd.id_deteksi = ?
     "
     . $filterPuskesmasDetail
     . "
     LIMIT 1"
);

if (!$stmtDetail) {
    die(
        "Gagal menyiapkan detail laporan: "
        . mysqli_error($conn)
    );
}

if ($aksesPuskesmasTerbatas) {

    mysqli_stmt_bind_param(
        $stmtDetail,
        "ii",
        $idDeteksi,
        $idPuskesmasAktif
    );

} else {

    mysqli_stmt_bind_param(
        $stmtDetail,
        "i",
        $idDeteksi
    );
}

mysqli_stmt_execute($stmtDetail);

$resultDetail =
    mysqli_stmt_get_result($stmtDetail);

$data =
    mysqli_fetch_assoc($resultDetail);

mysqli_stmt_close($stmtDetail);

if (!$data) {
    header(
        "Location: laporan_stunting.php?pesan=tidak_ditemukan"
    );
    exit;
}

$kelasStatus =
    kelasStatusDetail(
        $data["status_stunting"]
    );

/*
|--------------------------------------------------------------------------
| Rekomendasi sederhana
|--------------------------------------------------------------------------
*/

$rekomendasi = [];

if (
    strtolower(
        trim((string) ($data["protein_hewani"] ?? ""))
    ) === "tidak"
) {
    $rekomendasi[] =
        "Tingkatkan konsumsi protein hewani sesuai usia balita.";
}

if (
    strtolower(
        trim((string) ($data["sanitasi"] ?? ""))
    ) === "kurang"
) {
    $rekomendasi[] =
        "Perbaiki kondisi sanitasi rumah dan lingkungan.";
}

if (
    strtolower(
        trim((string) ($data["air_bersih"] ?? ""))
    ) === "tidak"
) {
    $rekomendasi[] =
        "Pastikan keluarga menggunakan sumber air bersih dan aman.";
}

if (count($rekomendasi) === 0) {
    $rekomendasi[] =
        "Pertahankan pola makan sehat dan lakukan pemantauan pertumbuhan secara rutin.";
}

/*
|--------------------------------------------------------------------------
| Template aplikasi
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
                        <i class="bi bi-file-earmark-medical me-2"></i>
                        Detail Laporan Stunting
                    </h4>

                    <small class="text-muted">
                        Hasil deteksi, verifikasi Gizi,
                        skrining, dan evaluasi KIA balita.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        !empty($data["nama_puskesmas"])
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex align-items-center px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= amanDetailLaporan(
                                $data["nama_puskesmas"]
                            ); ?>
                        </span>

                    <?php endif; ?>

                    <a
                        href="laporan_stunting.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        onclick="window.print();"
                    >
                        <i class="bi bi-printer"></i>
                        Cetak Detail
                    </button>

                </div>

            </div>

        </div>

        <div class="stat-grid">

            <div class="stat-card stat-info">

                <div class="stat-icon">
                    <i class="bi bi-person-heart"></i>
                </div>

                <div class="stat-content">
                    <p class="stat-label">
                        Nama Balita
                    </p>

                    <p
                        class="stat-value"
                        style="font-size: 18px;"
                    >
                        <?= amanDetailLaporan(
                            $data["nama_balita"]
                        ); ?>
                    </p>
                </div>

            </div>

            <div class="stat-card stat-warning">

                <div class="stat-icon">
                    <i class="bi bi-clipboard2-heart"></i>
                </div>

                <div class="stat-content">
                    <p class="stat-label">
                        Status Gizi
                    </p>

                    <p
                        class="stat-value"
                        style="font-size: 18px;"
                    >
                        <?= amanDetailLaporan(
                            $data["status_gizi"]
                        ); ?>
                    </p>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon">
                    <i class="bi bi-heart-pulse"></i>
                </div>

                <div class="stat-content">
                    <p class="stat-label">
                        Status Risiko
                    </p>

                    <p
                        class="stat-value"
                        style="font-size: 18px;"
                    >
                        <?= amanDetailLaporan(
                            $data["status_stunting"]
                        ); ?>
                    </p>
                </div>

            </div>

        </div>

        <div class="row g-4">

            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>
                            <h4 class="mb-1">
                                Identitas Balita
                            </h4>

                            <small class="text-muted">
                                Data dasar dan fasilitas kesehatan balita
                            </small>
                        </div>

                        <span class="badge badge-primary">
                            <i class="bi bi-person-vcard"></i>
                            Identitas
                        </span>

                    </div>

                    <div class="card-body">

                        <div class="detail-grid">

                            <div class="detail-item">
                                <span class="detail-label">
                                    ID Balita
                                </span>

                                <span class="detail-value">
                                    <?= (int) $data["id_balita"]; ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Nama Balita
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["nama_balita"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    NIK Balita
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["nik_balita"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Jenis Kelamin
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["jenis_kelamin"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Tanggal Lahir
                                </span>

                                <span class="detail-value">
                                    <?= formatTanggalDetail(
                                        $data["tanggal_lahir"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Umur Saat Pengukuran
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["umur_bulan"]
                                    ); ?>
                                    bulan
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Nama Posyandu
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["nama_posyandu"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Puskesmas Pembina
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["nama_puskesmas"]
                                    ); ?>
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>
                            <h4 class="mb-1">
                                Data Pengukuran
                            </h4>

                            <small class="text-muted">
                                Pengukuran yang digunakan dalam deteksi
                            </small>
                        </div>

                        <span class="badge badge-info">
                            <i class="bi bi-rulers"></i>
                            Antropometri
                        </span>

                    </div>

                    <div class="card-body">

                        <div class="detail-grid">

                            <div class="detail-item">
                                <span class="detail-label">
                                    Tanggal Pengukuran
                                </span>

                                <span class="detail-value">
                                    <?= formatTanggalDetail(
                                        $data["tanggal_pengukuran"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Berat Badan
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["berat_badan"]
                                    ); ?>
                                    kg
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Tinggi/Panjang Badan
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "tinggi_panjang_badan"
                                        ]
                                    ); ?>
                                    cm
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Lingkar Kepala
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["lingkar_kepala"]
                                    ); ?>
                                    cm
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    LiLA
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["lila"]
                                    ); ?>
                                    cm
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Tanggal Deteksi
                                </span>

                                <span class="detail-value">
                                    <?= formatTanggalDetail(
                                        $data["tanggal_deteksi"]
                                    ); ?>
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-12">

                <div class="card content-card">

                    <div class="card-header">

                        <div>
                            <h4 class="mb-1">
                                Hasil Deteksi
                            </h4>

                            <small class="text-muted">
                                Kesimpulan pemeriksaan risiko stunting
                            </small>
                        </div>

                        <span
                            class="badge <?= $kelasStatus; ?>"
                        >
                            <?= amanDetailLaporan(
                                $data["status_stunting"]
                            ); ?>
                        </span>

                    </div>

                    <div class="card-body">

                        <div class="detail-grid">

                            <div class="detail-item">
                                <span class="detail-label">
                                    ID Deteksi
                                </span>

                                <span class="detail-value">
                                    <?= (int) $data["id_deteksi"]; ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Status Gizi
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["status_gizi"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Status Risiko
                                </span>

                                <span
                                    class="badge <?= $kelasStatus; ?>"
                                >
                                    <?= amanDetailLaporan(
                                        $data["status_stunting"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Tanggal Deteksi
                                </span>

                                <span class="detail-value">
                                    <?= formatTanggalDetail(
                                        $data["tanggal_deteksi"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Status Verifikasi
                                </span>

                                <span
                                    class="badge
                                    <?= kelasVerifikasiDetail(
                                        $data[
                                            "status_verifikasi"
                                        ]
                                        ?? "Belum diverifikasi"
                                    ); ?>"
                                >
                                    <?= amanDetailLaporan(
                                        $data[
                                            "status_verifikasi"
                                        ]
                                        ?? "Belum diverifikasi"
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Diverifikasi Oleh
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "nama_verifikator"
                                        ]
                                        ?? null
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Tanggal Verifikasi
                                </span>

                                <span class="detail-value">
                                    <?= !empty(
                                        $data[
                                            "tanggal_verifikasi"
                                        ]
                                    )
                                        ? amanDetailLaporan(
                                            date(
                                                "d-m-Y H:i",
                                                strtotime(
                                                    $data[
                                                        "tanggal_verifikasi"
                                                    ]
                                                )
                                            )
                                        )
                                        : "-"; ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Catatan Verifikasi
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "catatan_verifikasi"
                                        ]
                                        ?? null
                                    ); ?>
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>
                            <h4 class="mb-1">
                                Faktor Risiko
                            </h4>

                            <small class="text-muted">
                                Data skrining terakhir
                            </small>
                        </div>

                        <span class="badge badge-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Skrining
                        </span>

                    </div>

                    <div class="card-body">

                        <div class="detail-grid">

                            <div class="detail-item">
                                <span class="detail-label">
                                    Tinggi Badan Ibu
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["tinggi_badan_ibu"]
                                    ); ?>
                                    cm
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Pendidikan Ibu
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["pendidikan_ibu"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Pekerjaan Ibu
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["pekerjaan_ibu"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    ASI Eksklusif
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "lama_asi_eksklusif"
                                        ]
                                    ); ?>
                                    bulan
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    MPASI
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["mpasi"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Frekuensi Makan
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["frekuensi_makan"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Protein Hewani
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["protein_hewani"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Status Ekonomi
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["status_ekonomi"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Sanitasi
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["sanitasi"]
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Air Bersih
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data["air_bersih"]
                                    ); ?>
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>
                            <h4 class="mb-1">
                                Evaluasi KIA & Rujukan
                            </h4>

                            <small class="text-muted">
                                Penilaian klinis terbaru dari Petugas KIA
                            </small>
                        </div>

                        <span class="badge badge-info">
                            <i class="bi bi-heart-pulse"></i>
                            KIA
                        </span>

                    </div>

                    <div class="card-body">

                        <div class="detail-grid">

                            <div class="detail-item">
                                <span class="detail-label">
                                    Status Red Flag
                                </span>

                                <span
                                    class="badge
                                    <?= kelasRedFlagDetail(
                                        $data[
                                            "status_red_flag"
                                        ]
                                        ?? "Belum dinilai"
                                    ); ?>"
                                >
                                    <?= amanDetailLaporan(
                                        $data[
                                            "status_red_flag"
                                        ]
                                        ?? "Belum dinilai"
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Catatan Red Flag
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "catatan_red_flag"
                                        ]
                                        ?? null
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Penilai Red Flag
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "nama_penilai_red_flag"
                                        ]
                                        ?? null
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Tanggal Penilaian
                                </span>

                                <span class="detail-value">
                                    <?= !empty(
                                        $data[
                                            "tanggal_penilaian"
                                        ]
                                    )
                                        ? amanDetailLaporan(
                                            date(
                                                "d-m-Y H:i",
                                                strtotime(
                                                    $data[
                                                        "tanggal_penilaian"
                                                    ]
                                                )
                                            )
                                        )
                                        : "-"; ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Status Rujukan
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "status_rujukan"
                                        ]
                                        ?? null
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Rekomendasi Rujukan
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "rekomendasi_rujukan"
                                        ]
                                        ?? null
                                    ); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">
                                    Catatan KIA
                                </span>

                                <span class="detail-value">
                                    <?= amanDetailLaporan(
                                        $data[
                                            "catatan_kia"
                                        ]
                                        ?? null
                                    ); ?>
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>
                            <h4 class="mb-1">
                                Rekomendasi
                            </h4>

                            <small class="text-muted">
                                Saran tindak lanjut berdasarkan faktor risiko
                            </small>
                        </div>

                        <span class="badge badge-success">
                            <i class="bi bi-lightbulb"></i>
                            Saran
                        </span>

                    </div>

                    <div class="card-body">

                        <div class="d-flex flex-column gap-3">

                            <?php foreach (
                                $rekomendasi
                                as $nomor => $item
                            ): ?>

                                <div class="alert alert-info mb-0">

                                    <div
                                        class="d-flex
                                        align-items-start gap-2"
                                    >
                                        <i
                                            class="bi
                                            bi-check-circle-fill mt-1"
                                        ></i>

                                        <div>
                                            <strong>
                                                Rekomendasi
                                                <?= $nomor + 1; ?>
                                            </strong>

                                            <div>
                                                <?= amanDetailLaporan(
                                                    $item
                                                ); ?>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>