<?php

$namaNavbar = htmlspecialchars(
    $_SESSION["nama"] ?? "Pengguna",
    ENT_QUOTES,
    "UTF-8"
);

$roleNavbar = $_SESSION["role"] ?? "";

$namaRole = [
    "kader" => "Kader Posyandu",
    "petugas_kia" => "Petugas KIA",
    "petugas_gizi" => "Petugas Gizi",
    "orang_tua" => "Orang Tua",
    "kepala_puskesmas" => "Kepala Puskesmas",
    "dinkes" => "Dinas Kesehatan"
];

$roleNavbarTampil = $namaRole[$roleNavbar] ?? "Pengguna";
?>

<header class="app-navbar">

    <!-- Brand aplikasi -->
    <a
        href="../dashboard/dashboard.php"
        class="app-brand"
        aria-label="Kembali ke dashboard"
    >
        <span
            class="app-brand-icon"
            aria-hidden="true"
        >
            🧸
        </span>

        <span class="app-brand-copy">

            <strong>
                Sistem Deteksi Stunting
            </strong>

            <small>
                Pemantauan tumbuh kembang balita
            </small>

        </span>
    </a>

    <!-- Informasi pengguna -->
    <div class="app-navbar-user">

        <div class="app-user-greeting">

            <span>
                Selamat datang,
            </span>

            <strong>
                <?= $namaNavbar ?>
            </strong>

        </div>

        <span class="app-role-badge">

            <?= htmlspecialchars(
                $roleNavbarTampil,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </span>

        <a
            href="../auth/logout.php"
            class="app-logout-button"
        >
            Logout
        </a>

    </div>

</header>