<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
| Analisis deteksi dilakukan oleh Petugas Gizi.
*/

cekRole([
    "petugas_gizi"
]);

$judulHalaman =
    "Analisis Risiko Stunting | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function amanAnalisis($nilai): string
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

/*
|--------------------------------------------------------------------------
| Memeriksa ID balita
|--------------------------------------------------------------------------
*/

$idBalita = filter_input(
    INPUT_GET,
    "id_balita",
    FILTER_VALIDATE_INT
);

if (!$idBalita || $idBalita < 1) {
    header(
        "Location: ../skrining/hasil_skrining.php?pesan=balita_belum_dipilih"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data balita
|--------------------------------------------------------------------------
*/

$stmtBalita = mysqli_prepare(
    $conn,
    "SELECT
        id_balita,
        nama_balita,
        nik_balita,
        jenis_kelamin,
        tanggal_lahir
     FROM balita
     WHERE id_balita = ?
     LIMIT 1"
);

if (!$stmtBalita) {
    die(
        "Gagal menyiapkan data balita: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtBalita,
    "i",
    $idBalita
);

mysqli_stmt_execute($stmtBalita);

$resultBalita =
    mysqli_stmt_get_result($stmtBalita);

$balita =
    mysqli_fetch_assoc($resultBalita);

mysqli_stmt_close($stmtBalita);

if (!$balita) {
    header(
        "Location: ../skrining/hasil_skrining.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil pengukuran terakhir
|--------------------------------------------------------------------------
*/

$stmtPengukuran = mysqli_prepare(
    $conn,
    "SELECT
        id_pengukuran,
        id_balita,
        tanggal_pengukuran,
        umur_bulan,
        berat_badan,
        tinggi_panjang_badan,
        lingkar_kepala,
        lila
     FROM pengukuran_antropometri
     WHERE id_balita = ?
     ORDER BY
        tanggal_pengukuran DESC,
        id_pengukuran DESC
     LIMIT 1"
);

if (!$stmtPengukuran) {
    die(
        "Gagal menyiapkan data pengukuran: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPengukuran,
    "i",
    $idBalita
);

mysqli_stmt_execute($stmtPengukuran);

$resultPengukuran =
    mysqli_stmt_get_result($stmtPengukuran);

$pengukuran =
    mysqli_fetch_assoc($resultPengukuran);

mysqli_stmt_close($stmtPengukuran);

if (!$pengukuran) {
    header(
        "Location: ../pengukuran/data_pengukuran.php?pesan=belum_ada_pengukuran"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil skrining terakhir
|--------------------------------------------------------------------------
*/

$stmtSkrining = mysqli_prepare(
    $conn,
    "SELECT
        id_skrining,
        id_balita,
        protein_hewani,
        sanitasi,
        air_bersih
     FROM skrining_awal
     WHERE id_balita = ?
     ORDER BY id_skrining DESC
     LIMIT 1"
);

if (!$stmtSkrining) {
    die(
        "Gagal menyiapkan data skrining: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtSkrining,
    "i",
    $idBalita
);

mysqli_stmt_execute($stmtSkrining);

$resultSkrining =
    mysqli_stmt_get_result($stmtSkrining);

$skrining =
    mysqli_fetch_assoc($resultSkrining);

mysqli_stmt_close($stmtSkrining);

if (!$skrining) {
    header(
        "Location: ../skrining/form_skrining.php?pesan=belum_ada_skrining"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Analisis rule-based
|--------------------------------------------------------------------------
*/

$skor = 0;
$rekomendasi = [];

/*
|--------------------------------------------------------------------------
| Indikator 1: protein hewani
|--------------------------------------------------------------------------
*/

if (
    strtolower(
        trim($skrining["protein_hewani"] ?? "")
    ) === "tidak"
) {
    $skor++;

    $rekomendasi[] =
        "Tingkatkan konsumsi protein hewani seperti telur, ikan, ayam, atau daging sesuai usia balita.";
}

/*
|--------------------------------------------------------------------------
| Indikator 2: sanitasi
|--------------------------------------------------------------------------
*/

if (
    strtolower(
        trim($skrining["sanitasi"] ?? "")
    ) === "kurang"
) {
    $skor++;

    $rekomendasi[] =
        "Perbaiki kondisi sanitasi rumah dan lingkungan untuk mengurangi risiko infeksi.";
}

/*
|--------------------------------------------------------------------------
| Indikator 3: air bersih
|--------------------------------------------------------------------------
*/

if (
    strtolower(
        trim($skrining["air_bersih"] ?? "")
    ) === "tidak"
) {
    $skor++;

    $rekomendasi[] =
        "Pastikan keluarga menggunakan sumber air bersih dan aman untuk kebutuhan sehari-hari.";
}

/*
|--------------------------------------------------------------------------
| Menentukan status risiko
|--------------------------------------------------------------------------
| Karena indikator aktif berjumlah tiga:
| 0–1 = rendah
| 2   = sedang
| 3   = tinggi
*/

if ($skor <= 1) {
    $statusStunting = "Risiko Rendah";
    $kelasStatus = "badge-success";
    $kelasStat = "stat-success";
    $ikonStatus = "bi-shield-check";

} elseif ($skor === 2) {
    $statusStunting = "Risiko Sedang";
    $kelasStatus = "badge-warning";
    $kelasStat = "stat-warning";
    $ikonStatus = "bi-exclamation-triangle";

} else {
    $statusStunting = "Risiko Tinggi";
    $kelasStatus = "badge-danger";
    $kelasStat = "stat-warning";
    $ikonStatus = "bi-exclamation-octagon";
}

/*
|--------------------------------------------------------------------------
| Status gizi sementara
|--------------------------------------------------------------------------
*/

$statusGizi = "Perlu Pemeriksaan";

/*
|--------------------------------------------------------------------------
| Menyimpan atau memperbarui hasil deteksi
|--------------------------------------------------------------------------
*/

$idPengukuran =
    (int) $pengukuran["id_pengukuran"];

$stmtCekHasil = mysqli_prepare(
    $conn,
    "SELECT id_deteksi
     FROM hasil_deteksi
     WHERE id_pengukuran = ?
     LIMIT 1"
);

if (!$stmtCekHasil) {
    die(
        "Gagal memeriksa hasil deteksi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtCekHasil,
    "i",
    $idPengukuran
);

mysqli_stmt_execute($stmtCekHasil);

$resultCekHasil =
    mysqli_stmt_get_result($stmtCekHasil);

$dataHasil =
    mysqli_fetch_assoc($resultCekHasil);

mysqli_stmt_close($stmtCekHasil);

if ($dataHasil) {

    $stmtSimpanHasil = mysqli_prepare(
        $conn,
        "UPDATE hasil_deteksi
         SET
            status_gizi = ?,
            status_stunting = ?,
            tanggal_deteksi = CURDATE()
         WHERE id_pengukuran = ?"
    );

    if (!$stmtSimpanHasil) {
        die(
            "Gagal menyiapkan perubahan hasil deteksi: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtSimpanHasil,
        "ssi",
        $statusGizi,
        $statusStunting,
        $idPengukuran
    );

} else {

    $stmtSimpanHasil = mysqli_prepare(
        $conn,
        "INSERT INTO hasil_deteksi
        (
            id_pengukuran,
            status_gizi,
            status_stunting,
            tanggal_deteksi
        )
        VALUES (?, ?, ?, CURDATE())"
    );

    if (!$stmtSimpanHasil) {
        die(
            "Gagal menyiapkan hasil deteksi: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtSimpanHasil,
        "iss",
        $idPengukuran,
        $statusGizi,
        $statusStunting
    );
}

$hasilDisimpan =
    mysqli_stmt_execute($stmtSimpanHasil);

if (!$hasilDisimpan) {
    die(
        "Hasil deteksi gagal disimpan: "
        . mysqli_stmt_error($stmtSimpanHasil)
    );
}

mysqli_stmt_close($stmtSimpanHasil);

/*
|--------------------------------------------------------------------------
| Rekomendasi umum
|--------------------------------------------------------------------------
*/

if (count($rekomendasi) === 0) {
    $rekomendasi[] =
        "Pertahankan pola hidup sehat dan lakukan pemantauan pertumbuhan secara rutin di Posyandu.";
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

        <!-- Header halaman -->
        <div class="page-header">

            <div>

                <h1 class="page-title">

                    <i class="bi bi-heart-pulse me-2"></i>

                    Analisis Risiko Stunting

                </h1>

                <p class="page-subtitle">

                    Hasil analisis berdasarkan pengukuran terakhir
                    dan faktor risiko pada skrining awal balita.

                </p>

            </div>

            <div class="d-flex flex-wrap gap-2">

                <a
                    href="../skrining/hasil_skrining.php"
                    class="btn btn-secondary"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Skrining
                </a>

                <a
                    href="hasil_deteksi.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-clipboard2-pulse"></i>
                    Data Hasil Deteksi
                </a>

            </div>

        </div>

        <!-- Statistik hasil -->
        <div class="stat-grid">

            <div class="stat-card stat-info">

                <div class="stat-icon">
                    <i class="bi bi-speedometer2"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Skor Risiko
                    </p>

                    <p class="stat-value">
                        <?= $skor; ?>/3
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
                        <?= amanAnalisis($statusGizi); ?>
                    </p>

                </div>

            </div>

            <div class="stat-card <?= $kelasStat; ?>">

                <div class="stat-icon">
                    <i class="bi <?= $ikonStatus; ?>"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Status Risiko
                    </p>

                    <p
                        class="stat-value"
                        style="font-size: 18px;"
                    >
                        <?= amanAnalisis($statusStunting); ?>
                    </p>

                </div>

            </div>

        </div>

        <div class="row g-4">

            <!-- Identitas balita -->
            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>

                            <h4 class="mb-1">
                                Identitas Balita
                            </h4>

                            <small class="text-muted">
                                Data balita yang dianalisis
                            </small>

                        </div>

                        <span class="badge badge-primary">

                            <i class="bi bi-person-heart"></i>

                            Balita

                        </span>

                    </div>

                    <div class="card-body">

                        <div class="detail-grid">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Nama Balita
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $balita["nama_balita"]
                                    ); ?>
                                </span>

                            </div>

                            <div class="detail-item">

                                <span class="detail-label">
                                    NIK Balita
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $balita["nik_balita"]
                                    ); ?>
                                </span>

                            </div>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Jenis Kelamin
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $balita["jenis_kelamin"]
                                    ); ?>
                                </span>

                            </div>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Umur Saat Pengukuran
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $pengukuran["umur_bulan"]
                                    ); ?>
                                    bulan
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Pengukuran terakhir -->
            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>

                            <h4 class="mb-1">
                                Pengukuran Terakhir
                            </h4>

                            <small class="text-muted">

                                <?= !empty(
                                    $pengukuran[
                                        "tanggal_pengukuran"
                                    ]
                                )
                                    ? date(
                                        "d-m-Y",
                                        strtotime(
                                            $pengukuran[
                                                "tanggal_pengukuran"
                                            ]
                                        )
                                    )
                                    : "-"; ?>

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
                                    Berat Badan
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $pengukuran[
                                            "berat_badan"
                                        ]
                                    ); ?>
                                    kg
                                </span>

                            </div>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Tinggi/Panjang Badan
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $pengukuran[
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
                                    <?= amanAnalisis(
                                        $pengukuran[
                                            "lingkar_kepala"
                                        ]
                                    ); ?>
                                    cm
                                </span>

                            </div>

                            <div class="detail-item">

                                <span class="detail-label">
                                    LiLA
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $pengukuran["lila"]
                                    ); ?>
                                    cm
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Faktor risiko -->
            <div class="col-12 col-lg-5">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>

                            <h4 class="mb-1">
                                Faktor Risiko
                            </h4>

                            <small class="text-muted">
                                Berdasarkan skrining terakhir
                            </small>

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="detail-grid">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Protein Hewani
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $skrining[
                                            "protein_hewani"
                                        ]
                                    ); ?>
                                </span>

                            </div>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Sanitasi
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $skrining["sanitasi"]
                                    ); ?>
                                </span>

                            </div>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Air Bersih
                                </span>

                                <span class="detail-value">
                                    <?= amanAnalisis(
                                        $skrining["air_bersih"]
                                    ); ?>
                                </span>

                            </div>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Kesimpulan
                                </span>

                                <span
                                    class="badge <?= $kelasStatus; ?>"
                                >
                                    <?= amanAnalisis(
                                        $statusStunting
                                    ); ?>
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Rekomendasi -->
            <div class="col-12 col-lg-7">

                <div class="card content-card h-100">

                    <div class="card-header">

                        <div>

                            <h4 class="mb-1">
                                Rekomendasi
                            </h4>

                            <small class="text-muted">
                                Tindak lanjut berdasarkan faktor risiko
                            </small>

                        </div>

                        <span class="badge badge-success">

                            <i class="bi bi-lightbulb"></i>

                            Saran

                        </span>

                    </div>

                    <div class="card-body">

                        <div class="d-flex flex-column gap-3">

                            <?php
                            foreach (
                                $rekomendasi
                                as $nomor => $item
                            ):
                            ?>

                                <div class="alert alert-info mb-0">

                                    <div
                                        class="d-flex
                                        align-items-start gap-2"
                                    >

                                        <i
                                            class="bi
                                            bi-check-circle-fill
                                            mt-1"
                                        ></i>

                                        <div>

                                            <strong>
                                                Rekomendasi
                                                <?= $nomor + 1; ?>
                                            </strong>

                                            <div>
                                                <?= amanAnalisis(
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

        <div class="form-actions">

            <a
                href="hasil_deteksi.php"
                class="btn btn-primary"
            >
                <i class="bi bi-clipboard2-pulse"></i>
                Lihat Data Hasil Deteksi
            </a>

            <a
                href="../skrining/hasil_skrining.php"
                class="btn btn-light"
            >
                <i class="bi bi-arrow-left"></i>
                Kembali ke Skrining
            </a>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>