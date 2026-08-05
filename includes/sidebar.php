<?php

$roleSidebar = $_SESSION["role"] ?? "";
?>

<aside class="sidebar">

    <div class="sidebar-title">
        Menu Utama
    </div>

    <nav class="sidebar-menu">

        <a href="../dashboard/dashboard.php">
            Dashboard
        </a>

        <?php if ($roleSidebar === "dinkes"): ?>

            <a href="../user/data_user.php">
                Data Pengguna
            </a>

            <a href="../balita/data_balita.php">
                Data Balita
            </a>

            <a href="../pengukuran/data_pengukuran.php">
                Data Pengukuran
            </a>

            <a href="../skrining/hasil_skrining.php">
                Data Skrining
            </a>

            <a href="../deteksi/hasil_deteksi.php">
                Hasil Deteksi
            </a>

            <a href="../konsultasi/data_konsultasi.php">
                Monitoring Konsultasi
            </a>

        <?php elseif ($roleSidebar === "kepala_puskesmas"): ?>

            <a href="../balita/data_balita.php">
                Data Balita
            </a>

            <a href="../pengukuran/data_pengukuran.php">
                Grafik Pertumbuhan
            </a>

            <a href="../deteksi/hasil_deteksi.php">
                Data Stunting
            </a>

            <a href="../konsultasi/data_konsultasi.php">
                Monitoring Konsultasi
            </a>

        <?php elseif ($roleSidebar === "kader"): ?>

            <a href="../balita/data_balita.php">
                Data Balita
            </a>

            <a href="../balita/tambah_balita.php">
                Tambah Balita
            </a>

            <a href="../pengukuran/data_pengukuran.php">
                Data Pengukuran
            </a>

            <a href="../pengukuran/tambah_pengukuran.php">
                Input Antropometri
            </a>

        <?php elseif ($roleSidebar === "petugas_kia"): ?>

            <a href="../balita/data_balita.php">
                Data Balita
            </a>

            <a href="../kelahiran/riwayat_kelahiran.php">
                Riwayat Kelahiran
            </a>

            <a href="../kesehatan/riwayat_kesehatan.php">
                Riwayat Kesehatan
            </a>

            <a href="../pengukuran/data_pengukuran.php">
                Grafik Pertumbuhan
            </a>

            <a href="../deteksi/hasil_deteksi.php">
                Hasil Deteksi
            </a>

        <?php elseif ($roleSidebar === "petugas_gizi"): ?>

            <a href="../balita/data_balita.php">
                Data Balita
            </a>

            <a href="../pengukuran/data_pengukuran.php">
                Data Pengukuran
            </a>

            <a href="../skrining/form_skrining.php">
                Skrining Awal
            </a>

            <a href="../skrining/hasil_skrining.php">
                Hasil Skrining
            </a>

            <a href="../deteksi/hasil_deteksi.php">
                Deteksi Risiko Stunting
            </a>

            <a href="../konsultasi/data_konsultasi.php">
                Konsultasi dan Monitoring
            </a>

        <?php elseif ($roleSidebar === "orang_tua"): ?>

            <a href="../balita/data_balita.php">
                Data Anak
            </a>

            <a href="../pengukuran/data_pengukuran.php">
                Grafik Pertumbuhan
            </a>

            <a href="../deteksi/hasil_deteksi.php">
                Hasil Deteksi
            </a>

            <a href="../konsultasi/data_konsultasi.php">
                Konsultasi
            </a>

        <?php endif; ?>

    </nav>

</aside>