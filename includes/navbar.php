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

<nav
    class="navbar navbar-expand-lg navbar-dark"
    style="
        background: linear-gradient(
            135deg,
            #cf6482 0%,
            #df7894 50%,
            #eda2b5 100%
        );
        padding: 12px 18px;
        box-shadow: 0 4px 15px rgba(158, 74, 101, 0.18);
    "
>
    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold d-flex align-items-center gap-2"
            href="../dashboard/dashboard.php"
            style="
                color: #ffffff;
                font-size: 21px;
                letter-spacing: 0.2px;
            "
        >
            <span
                style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 36px;
                    height: 36px;
                    background: rgba(255, 255, 255, 0.22);
                    border: 1px solid rgba(255, 255, 255, 0.35);
                    border-radius: 11px;
                    font-size: 18px;
                "
            >
                ♡
            </span>

            <span>Sistem Deteksi Stunting</span>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarUtama"
            aria-controls="navbarUtama"
            aria-expanded="false"
            aria-label="Buka navigasi"
            style="
                border-color: rgba(255, 255, 255, 0.45);
                box-shadow: none;
            "
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="navbarUtama"
        >

            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">

                <li class="nav-item">
                    <a
                        class="nav-link"
                        href="../dashboard/dashboard.php"
                        style="
                            color: rgba(255, 255, 255, 0.92);
                            font-weight: 600;
                        "
                    >
                        Dashboard
                    </a>
                </li>

                <?php if ($roleNavbar === "dinkes"): ?>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="../user/data_user.php"
                            style="
                                color: rgba(255, 255, 255, 0.92);
                                font-weight: 600;
                            "
                        >
                            Data Pengguna
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

            <div
                class="d-flex flex-column flex-lg-row align-items-lg-center gap-2"
                style="color: #ffffff;"
            >

                <span style="font-size: 14px;">
                    Halo,
                    <strong><?= $namaNavbar ?></strong>
                </span>

                <span
                    class="badge"
                    style="
                        padding: 7px 13px;
                        background: #fff0f4;
                        border: 1px solid rgba(255, 255, 255, 0.75);
                        border-radius: 999px;
                        color: #b84f6d;
                        font-size: 12px;
                        font-weight: 700;
                    "
                >
                    <?= htmlspecialchars(
                        $roleNavbarTampil,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </span>

                <a
                    href="../auth/logout.php"
                    class="btn btn-sm"
                    style="
                        padding: 7px 15px;
                        background: #ffffff;
                        border: 1px solid rgba(255, 255, 255, 0.85);
                        border-radius: 9px;
                        color: #b84f6d;
                        font-size: 13px;
                        font-weight: 700;
                        box-shadow: 0 3px 9px rgba(123, 53, 76, 0.12);
                    "
                >
                    Logout
                </a>

            </div>

        </div>

    </div>
</nav>