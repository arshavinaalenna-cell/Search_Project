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
                Data Konsultasi
            </a>

        <?php elseif ($roleSidebar === "ahli_gizi"): ?>

            <a href="../balita/data_balita.php">
                Data Balita
            </a>

            <a href="../pengukuran/data_pengukuran.php">
                Pengukuran Antropometri
            </a>

            <a href="../skrining/form_skrining.php">
                Skrining Awal
            </a>

            <a href="../deteksi/hasil_deteksi.php">
                Hasil Deteksi
            </a>

            <a href="../konsultasi/data_konsultasi.php">
                Konsultasi
            </a>

        <?php elseif ($roleSidebar === "orang_tua"): ?>

            <a href="../balita/data_balita.php">
                Data Anak
            </a>

            <a href="../balita/riwayat.php">
                Riwayat Pertumbuhan
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