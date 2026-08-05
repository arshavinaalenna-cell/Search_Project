<?php

require_once "../auth/session.php";
require_once "../config/koneksi.php";
require_once "statistik.php";

$namaPengguna = htmlspecialchars(
    $_SESSION["nama"] ?? "Pengguna",
    ENT_QUOTES,
    "UTF-8"
);

$rolePengguna = $_SESSION["role"] ?? "";

$namaRole = [
    "kader" => "Kader",
    "petugas_kia" => "Petugas KIA",
    "petugas_gizi" => "Petugas Gizi",
    "orang_tua" => "Orang Tua",
    "kepala_puskesmas" => "Kepala Puskesmas",
    "dinkes" => "Dinas Kesehatan"
];

$roleTampil = $namaRole[$rolePengguna] ?? "Pengguna";

$judulHalaman = "Dashboard | Sistem Deteksi Stunting";

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="dashboard-header">
            <h2>Dashboard</h2>

            <p>
                Ringkasan data untuk <?= htmlspecialchars(
                    $roleTampil,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>.
            </p>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h2><?= $totalBalita ?></h2>

                        <p>
                            <?= $rolePengguna === "orang_tua"
                                ? "Data Anak"
                                : "Total Balita" ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($rolePengguna === "dinkes"): ?>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2><?= $totalPengguna ?></h2>
                            <p>Total Pengguna</p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <?php if (
                in_array(
                    $rolePengguna,
                    [
                        "dinkes",
                        "petugas_gizi",
                        "petugas_kia",
                        "kepala_puskesmas",
                        "orang_tua"
                    ],
                    true
                )
            ): ?>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2><?= $totalSkrining ?></h2>
                            <p>Skrining Awal</p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <?php if (
                in_array(
                    $rolePengguna,
                    [
                        "dinkes",
                        "petugas_gizi",
                        "petugas_kia",
                        "kepala_puskesmas",
                        "orang_tua"
                    ],
                    true
                )
            ): ?>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2><?= $totalHasilDeteksi ?></h2>
                            <p>Hasil Deteksi</p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <?php if (
                in_array(
                    $rolePengguna,
                    [
                        "dinkes",
                        "kader",
                        "petugas_kia",
                        "petugas_gizi",
                        "kepala_puskesmas",
                        "orang_tua"
                    ],
                    true
                )
            ): ?>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2><?= $totalPengukuran ?></h2>
                            <p>Pengukuran Antropometri</p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <?php if (
                in_array(
                    $rolePengguna,
                    [
                        "dinkes",
                        "petugas_gizi",
                        "kepala_puskesmas",
                        "orang_tua"
                    ],
                    true
                )
            ): ?>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <h2><?= $totalKonsultasi ?></h2>
                            <p>Konsultasi</p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>

        <div class="card welcome-card">
            <div class="card-body">

                <h4 class="mb-3">
                    Selamat Datang, <?= $namaPengguna ?>
                </h4>

                <?php if ($rolePengguna === "kader"): ?>

                    <p class="mb-0">
                        Kamu dapat mendaftarkan data balita baru dan
                        memasukkan hasil pengukuran antropometri dari
                        kegiatan Posyandu.
                    </p>

                <?php elseif ($rolePengguna === "petugas_kia"): ?>

                    <p class="mb-0">
                        Kamu dapat mengelola riwayat kelahiran,
                        riwayat kesehatan, serta meninjau hasil
                        pertumbuhan dan deteksi risiko stunting.
                    </p>

                <?php elseif ($rolePengguna === "petugas_gizi"): ?>

                    <p class="mb-0">
                        Kamu dapat memvalidasi data balita, meninjau
                        hasil pengukuran, melakukan analisis risiko
                        stunting, dan memberikan konsultasi gizi.
                    </p>

                <?php elseif ($rolePengguna === "orang_tua"): ?>

                    <p class="mb-0">
                        Kamu dapat melihat data anak, grafik pertumbuhan,
                        hasil deteksi risiko stunting, serta melakukan
                        konsultasi dengan petugas gizi.
                    </p>

                <?php elseif ($rolePengguna === "kepala_puskesmas"): ?>

                    <p class="mb-0">
                        Kamu dapat memantau data stunting tingkat
                        Puskesmas, aktivitas konsultasi, serta laporan
                        pelayanan.
                    </p>

                <?php elseif ($rolePengguna === "dinkes"): ?>

                    <p class="mb-0">
                        Kamu dapat memantau data agregat stunting,
                        mengelola pengguna, dan melihat laporan
                        tingkat wilayah.
                    </p>

                <?php else: ?>

                    <p class="mb-0">
                        Selamat datang di Sistem Deteksi Stunting.
                    </p>

                <?php endif; ?>

            </div>
        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>