<?php

require_once "../auth/session.php";
require_once "../config/koneksi.php";
require_once "statistik.php";

$namaPengguna = htmlspecialchars(
    $_SESSION["nama"] ?? "Pengguna",
    ENT_QUOTES,
    "UTF-8"
);

$rolePengguna = htmlspecialchars(
    $_SESSION["role"] ?? "-",
    ENT_QUOTES,
    "UTF-8"
);

$roleTampil = ucwords(
    str_replace("_", " ", $rolePengguna)
);

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
                Ringkasan data pada Sistem Deteksi Stunting.
            </p>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body text-center">

                        <h2><?= $totalBalita ?></h2>

                        <p>Total Balita</p>

                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body text-center">

                        <h2><?= $totalPengguna ?></h2>

                        <p>Total Pengguna</p>

                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body text-center">

                        <h2><?= $totalSkrining ?></h2>

                        <p>Skrining Awal</p>

                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body text-center">

                        <h2><?= $totalHasilDeteksi ?></h2>

                        <p>Hasil Deteksi</p>

                    </div>
                </div>
            </div>

        </div>

        <div class="row g-4 mb-4">

            <div class="col-12 col-md-6">
                <div class="card stat-card">
                    <div class="card-body text-center">

                        <h2><?= $totalPengukuran ?></h2>

                        <p>Total Pengukuran Antropometri</p>

                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="card stat-card">
                    <div class="card-body text-center">

                        <h2><?= $totalKonsultasi ?></h2>

                        <p>Total Konsultasi</p>

                    </div>
                </div>
            </div>

        </div>

        <div class="card welcome-card">
            <div class="card-body">

                <h4 class="mb-3">
                    Selamat Datang, <?= $namaPengguna ?>
                </h4>

                <p class="mb-0">
                    Selamat datang di Sistem Deteksi Stunting.
                    Silakan gunakan menu yang tersedia untuk
                    mengelola data balita, skrining, pengukuran
                    antropometri, hasil deteksi, dan konsultasi.
                </p>

            </div>
        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>