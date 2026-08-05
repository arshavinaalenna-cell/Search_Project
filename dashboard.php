<?php
session_start();
require_once "koneksi.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

// Menghitung jumlah data
$balita = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM balita"));
$pengguna = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM pengguna"));
$skrining = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM skrining_awal"));
$hasil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM hasil_deteksi"));
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard | Sistem Deteksi Stunting</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.navbar{
    background:#198754;
}

.card{
    border:none;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.1);
}

.card h2{
    font-weight:bold;
}

</style>

</head>
<body>

<nav class="navbar navbar-dark">
    <div class="container-fluid">

        <span class="navbar-brand">
            Sistem Deteksi Stunting
        </span>

        <div class="text-white">

            Halo,
            <b><?= $_SESSION['nama']; ?></b>

            <a href="logout.php"
               class="btn btn-light btn-sm ms-3">
                Logout
            </a>

        </div>

    </div>
</nav>

<div class="container mt-4">

<div class="row">

<div class="col-md-3 mb-4">

<div class="card">
<div class="card-body text-center">

<h2><?= $balita['total']; ?></h2>

<p>Total Balita</p>

</div>
</div>

</div>

<div class="col-md-3 mb-4">

<div class="card">
<div class="card-body text-center">

<h2><?= $pengguna['total']; ?></h2>

<p>Total Pengguna</p>

</div>
</div>

</div>

<div class="col-md-3 mb-4">

<div class="card">
<div class="card-body text-center">

<h2><?= $skrining['total']; ?></h2>

<p>Skrining Awal</p>

</div>
</div>

</div>

<div class="col-md-3 mb-4">

<div class="card">
<div class="card-body text-center">

<h2><?= $hasil['total']; ?></h2>

<p>Hasil Deteksi</p>

</div>
</div>

</div>

</div>

<div class="card">

<div class="card-body">

<h4>Selamat Datang</h4>

<p>
Selamat datang di Sistem Deteksi Stunting.
Silakan pilih menu yang tersedia untuk mengelola data balita,
skrining, pengukuran antropometri, hasil deteksi, dan konsultasi.
</p>

</div>

</div>

</div>

</body>
</html>