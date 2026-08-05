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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | Sistem Deteksi Stunting</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            margin: 0;
            background: #f5f6fa;
            font-family: Arial, Helvetica, sans-serif;
        }

        .navbar {
            background: #198754;
        }

        .dashboard-header {
            margin-bottom: 25px;
        }

        .dashboard-header h2 {
            margin-bottom: 5px;
            font-weight: 700;
        }

        .dashboard-header p {
            margin: 0;
            color: #6c757d;
        }

        .stat-card {
            height: 100%;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card h2 {
            margin-bottom: 5px;
            font-weight: bold;
            color: #198754;
        }

        .stat-card p {
            margin-bottom: 0;
            color: #6c757d;
        }

        .welcome-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .role-badge {
            text-transform: capitalize;
        }

        @media (max-width: 767px) {
            .navbar-user {
                margin-top: 10px;
            }
        }
    </style>
</head>

<body>

<main class="container py-4">

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

</body>
</html>