<?php

$namaNavbar = htmlspecialchars(
    $_SESSION["nama"] ?? "Pengguna",
    ENT_QUOTES,
    "UTF-8"
);

$roleNavbar = $_SESSION["role"] ?? "";

$namaRole = [
    "kader" => "Kader",
    "petugas_kia" => "Petugas KIA",
    "petugas_gizi" => "Petugas Gizi",
    "orang_tua" => "Orang Tua",
    "kepala_puskesmas" => "Kepala Puskesmas",
    "dinkes" => "Dinas Kesehatan"
];

$roleNavbarTampil = $namaRole[$roleNavbar] ?? "Pengguna";
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold"
            href="../dashboard/dashboard.php"
        >
            Sistem Deteksi Stunting
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarUtama"
            aria-controls="navbarUtama"
            aria-expanded="false"
            aria-label="Buka navigasi"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="navbarUtama"
        >

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="../dashboard/dashboard.php"
                    >
                        Dashboard
                    </a>
                </li>

                <?php if ($roleNavbar === "dinkes"): ?>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="../user/data_user.php"
                        >
                            Data Pengguna
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

            <div
                class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 text-white"
            >

                <span>
                    Halo,
                    <strong><?= $namaNavbar ?></strong>
                </span>

                <span class="badge bg-light text-success">
                    <?= htmlspecialchars(
                        $roleNavbarTampil,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </span>

                <a
                    href="../auth/logout.php"
                    class="btn btn-light btn-sm"
                >
                    Logout
                </a>

            </div>

        </div>

    </div>
</nav>