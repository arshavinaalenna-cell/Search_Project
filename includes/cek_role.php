<?php

/*
|--------------------------------------------------------------------------
| Memastikan session aktif
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Menentukan base URL aplikasi secara otomatis
|--------------------------------------------------------------------------
|
| Contoh:
| - Jika aplikasi berada di C:/laragon/www/PROJECT_FIKS/Search_Project,
|   BASE_URL menjadi /Search_Project.
| - Jika virtual host langsung menunjuk ke folder Search_Project,
|   BASE_URL menjadi kosong.
|
*/

if (!defined("BASE_URL")) {
    $documentRoot = realpath(
        $_SERVER["DOCUMENT_ROOT"] ?? ""
    );

    $applicationRoot = realpath(
        __DIR__ . "/.."
    );

    $baseUrl = "";

    if ($documentRoot && $applicationRoot) {
        $documentRootNormal = rtrim(
            str_replace("\\", "/", $documentRoot),
            "/"
        );

        $applicationRootNormal = rtrim(
            str_replace("\\", "/", $applicationRoot),
            "/"
        );

        if (
            stripos(
                $applicationRootNormal,
                $documentRootNormal
            ) === 0
        ) {
            $relativePath = trim(
                substr(
                    $applicationRootNormal,
                    strlen($documentRootNormal)
                ),
                "/"
            );

            if ($relativePath !== "") {
                $baseUrl = "/" . $relativePath;
            }
        }
    }

    define("BASE_URL", $baseUrl);
}

/*
|--------------------------------------------------------------------------
| Membuat URL aplikasi
|--------------------------------------------------------------------------
*/

if (!function_exists("appUrl")) {
    function appUrl(string $path = ""): string
    {
        $path = ltrim($path, "/");

        if ($path === "") {
            return BASE_URL !== ""
                ? BASE_URL
                : "/";
        }

        return BASE_URL . "/" . $path;
    }
}

/*
|--------------------------------------------------------------------------
| Mengubah nama role menjadi lebih mudah dibaca
|--------------------------------------------------------------------------
*/

function namaRole(string $role): string
{
    $daftarRole = [
        "kader"              => "Kader Posyandu",
        "petugas_kia"        => "Petugas KIA",
        "petugas_gizi"       => "Petugas Gizi",
        "orang_tua"          => "Orang Tua",
        "kepala_puskesmas"   => "Kepala Puskesmas",
        "dinkes"             => "Dinas Kesehatan"
    ];

    return $daftarRole[$role] ?? "Pengguna";
}

/*
|--------------------------------------------------------------------------
| Fungsi pemeriksaan hak akses
|--------------------------------------------------------------------------
*/

function cekRole(array $roleDiizinkan): void
{
    /*
    |--------------------------------------------------------------------------
    | Jika belum login
    |--------------------------------------------------------------------------
    */

    if (!isset($_SESSION["id_user"])) {
        header(
            "Location: "
            . appUrl("auth/login.php?pesan=belum_login")
        );
        exit;
    }

    $roleAktif = $_SESSION["role"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | Jika role memiliki izin, lanjutkan halaman
    |--------------------------------------------------------------------------
    */

    if (in_array($roleAktif, $roleDiizinkan, true)) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Jika role tidak memiliki izin
    |--------------------------------------------------------------------------
    */

    http_response_code(403);

    $namaPengguna = htmlspecialchars(
        $_SESSION["nama"] ?? "Pengguna",
        ENT_QUOTES,
        "UTF-8"
    );

    $namaRoleAktif = htmlspecialchars(
        namaRole($roleAktif),
        ENT_QUOTES,
        "UTF-8"
    );

    $daftarRoleDiizinkan = array_map(
        function ($role) {
            return namaRole((string) $role);
        },
        $roleDiizinkan
    );

    $teksRoleDiizinkan = htmlspecialchars(
        implode(", ", $daftarRoleDiizinkan),
        ENT_QUOTES,
        "UTF-8"
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

        <meta
            name="theme-color"
            content="#f3b3ca"
        >

        <title>
            Akses Ditolak | Sistem Deteksi Stunting
        </title>

        <link
            rel="preconnect"
            href="https://fonts.googleapis.com"
        >

        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin
        >

        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        >

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
            rel="stylesheet"
        >

        <?php

        $fileCss = __DIR__ . "/../assets/css/style.css";

        $versiCss = file_exists($fileCss)
            ? filemtime($fileCss)
            : "1.0";

        ?>

        <link
            href="<?= htmlspecialchars(
                appUrl("assets/css/style.css"),
                ENT_QUOTES,
                "UTF-8"
            ); ?>?v=<?= $versiCss; ?>"
            rel="stylesheet"
        >

        <style>

            :root {
                --access-pink: #f4b2ca;
                --access-pink-dark: #c86488;
                --access-pink-soft: #fcebf2;

                --access-blue: #a9cdec;
                --access-blue-dark: #628db9;
                --access-blue-soft: #eaf3fc;

                --access-lavender: #e6def5;

                --access-text: #465366;
                --access-muted: #7c8797;
            }

            * {
                box-sizing: border-box;
            }

            body.access-denied-page {
                min-height: 100vh;
                margin: 0;
                overflow-x: hidden;

                display: flex;
                align-items: center;
                justify-content: center;

                padding: 30px 18px;

                font-family:
                    "Plus Jakarta Sans",
                    system-ui,
                    sans-serif;

                color: var(--access-text);

                background:
                    radial-gradient(
                        circle at 10% 12%,
                        rgba(255, 255, 255, .85) 0 90px,
                        transparent 91px
                    ),
                    radial-gradient(
                        circle at 90% 85%,
                        rgba(255, 255, 255, .70) 0 130px,
                        transparent 131px
                    ),
                    linear-gradient(
                        135deg,
                        #ffe7ef 0%,
                        #eee6f8 48%,
                        #e1f1ff 100%
                    );
            }

            .access-decoration {
                position: fixed;
                border-radius: 50%;
                pointer-events: none;
                opacity: .62;
            }

            .access-decoration-one {
                width: 170px;
                height: 170px;

                top: -65px;
                left: -55px;

                background: rgba(255, 255, 255, .75);
            }

            .access-decoration-two {
                width: 120px;
                height: 120px;

                right: 6%;
                top: 10%;

                background: rgba(244, 178, 202, .50);
            }

            .access-decoration-three {
                width: 95px;
                height: 95px;

                left: 7%;
                bottom: 8%;

                background: rgba(169, 205, 236, .60);
            }

            .access-shell {
                position: relative;
                z-index: 2;

                width: 100%;
                max-width: 660px;
            }

            .access-card {
                position: relative;
                overflow: hidden;

                padding: 48px 42px 40px;

                text-align: center;

                background: rgba(255, 255, 255, .94);

                border:
                    4px solid rgba(255, 255, 255, .80);

                border-radius: 32px;

                box-shadow:
                    0 25px 65px rgba(90, 104, 128, .20);

                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
            }

            .access-card::before {
                position: absolute;
                top: 0;
                left: 0;

                width: 100%;
                height: 9px;

                content: "";

                background:
                    linear-gradient(
                        90deg,
                        var(--access-pink),
                        var(--access-lavender),
                        var(--access-blue)
                    );
            }

            .access-icon-wrapper {
                position: relative;

                width: 112px;
                height: 112px;

                margin: 0 auto 22px;
            }

            .access-icon-circle {
                width: 112px;
                height: 112px;

                display: flex;
                align-items: center;
                justify-content: center;

                color: #ffffff;

                background:
                    linear-gradient(
                        145deg,
                        var(--access-pink),
                        var(--access-blue)
                    );

                border: 6px solid #ffffff;
                border-radius: 50%;

                box-shadow:
                    0 15px 30px rgba(200, 100, 136, .21);

                font-size: 47px;
            }

            .access-icon-small {
                position: absolute;
                right: -2px;
                bottom: 4px;

                width: 38px;
                height: 38px;

                display: flex;
                align-items: center;
                justify-content: center;

                color: var(--access-pink-dark);
                background: #ffffff;

                border:
                    3px solid var(--access-pink-soft);

                border-radius: 50%;

                font-size: 17px;
            }

            .access-code {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 7px;

                margin-bottom: 15px;
                padding: 7px 13px;

                color: var(--access-pink-dark);

                background:
                    linear-gradient(
                        135deg,
                        var(--access-pink-soft),
                        var(--access-blue-soft)
                    );

                border:
                    1px solid rgba(200, 100, 136, .12);

                border-radius: 999px;

                font-size: 11px;
                font-weight: 800;
                letter-spacing: .7px;
                text-transform: uppercase;
            }

            .access-title {
                margin: 0 0 13px;

                color: var(--access-text);

                font-size: clamp(26px, 5vw, 36px);
                font-weight: 800;
                letter-spacing: -.8px;
                line-height: 1.25;
            }

            .access-description {
                max-width: 520px;

                margin:
                    0 auto 26px;

                color: var(--access-muted);

                font-size: 14px;
                font-weight: 500;
                line-height: 1.8;
            }

            .access-user-box {
                display: grid;
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

                gap: 12px;

                margin-bottom: 27px;
                padding: 15px;

                background:
                    linear-gradient(
                        135deg,
                        rgba(252, 235, 242, .75),
                        rgba(234, 243, 252, .85)
                    );

                border:
                    1px solid rgba(255, 255, 255, .90);

                border-radius: 18px;
            }

            .access-info-item {
                padding: 12px 14px;

                text-align: left;

                background: rgba(255, 255, 255, .77);
                border-radius: 13px;
            }

            .access-info-label {
                display: block;

                margin-bottom: 4px;

                color: #929baa;

                font-size: 10px;
                font-weight: 800;
                letter-spacing: .5px;
                text-transform: uppercase;
            }

            .access-info-value {
                display: block;

                overflow: hidden;

                color: var(--access-text);

                font-size: 13px;
                font-weight: 800;

                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .access-permission {
                margin-bottom: 28px;
                padding: 13px 15px;

                color: var(--access-blue-dark);

                background: var(--access-blue-soft);

                border:
                    1px solid rgba(98, 141, 185, .13);

                border-radius: 14px;

                font-size: 12px;
                font-weight: 650;
                line-height: 1.6;
            }

            .access-permission i {
                margin-right: 5px;
            }

            .access-actions {
                display: flex;
                justify-content: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .access-button {
                min-height: 47px;

                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;

                padding: 10px 19px;

                border-radius: 14px;

                font-size: 13px;
                font-weight: 800;

                text-decoration: none;

                transition:
                    transform .18s ease,
                    box-shadow .18s ease,
                    background .18s ease;
            }

            .access-button:hover {
                transform: translateY(-2px);
            }

            .access-button-primary {
                color: #ffffff;

                background:
                    linear-gradient(
                        135deg,
                        var(--access-pink),
                        var(--access-blue)
                    );

                border: 0;

                box-shadow:
                    0 10px 22px rgba(98, 141, 185, .21);
            }

            .access-button-primary:hover {
                color: #ffffff;

                background:
                    linear-gradient(
                        135deg,
                        var(--access-pink-dark),
                        var(--access-blue-dark)
                    );
            }

            .access-button-secondary {
                color: var(--access-text);

                background: #ffffff;

                border: 1px solid #e6e1e8;
            }

            .access-button-secondary:hover {
                color: var(--access-pink-dark);
                background: #fff8fb;
                border-color: #efbfd1;
            }

            .access-footer-text {
                margin: 25px 0 0;

                color: #9ba4b0;

                font-size: 11px;
                font-weight: 600;
            }

            @media (max-width: 576px) {

                .access-card {
                    padding:
                        39px 21px 29px;

                    border-radius: 25px;
                }

                .access-icon-wrapper,
                .access-icon-circle {
                    width: 92px;
                    height: 92px;
                }

                .access-icon-circle {
                    font-size: 39px;
                }

                .access-icon-small {
                    width: 34px;
                    height: 34px;
                }

                .access-user-box {
                    grid-template-columns: 1fr;
                }

                .access-actions {
                    align-items: stretch;
                    flex-direction: column;
                }

                .access-button {
                    width: 100%;
                }

            }

        </style>

    </head>

    <body class="access-denied-page">

        <div
            class="access-decoration access-decoration-one"
        ></div>

        <div
            class="access-decoration access-decoration-two"
        ></div>

        <div
            class="access-decoration access-decoration-three"
        ></div>

        <main class="access-shell">

            <section class="access-card">

                <div class="access-icon-wrapper">

                    <div class="access-icon-circle">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>

                    <div class="access-icon-small">
                        <i class="bi bi-heart-fill"></i>
                    </div>

                </div>

                <div class="access-code">
                    <i class="bi bi-exclamation-circle"></i>
                    403 · Akses terbatas
                </div>

                <h1 class="access-title">
                    Ups, halaman ini bukan untuk akunmu
                </h1>

                <p class="access-description">
                    Kamu tetap aman. Sistem hanya membatasi halaman
                    ini agar data tumbuh kembang balita dikelola oleh
                    petugas yang memiliki kewenangan.
                </p>

                <div class="access-user-box">

                    <div class="access-info-item">

                        <span class="access-info-label">
                            Nama pengguna
                        </span>

                        <span class="access-info-value">
                            <?= $namaPengguna; ?>
                        </span>

                    </div>

                    <div class="access-info-item">

                        <span class="access-info-label">
                            Role akun
                        </span>

                        <span class="access-info-value">
                            <?= $namaRoleAktif; ?>
                        </span>

                    </div>

                </div>

                <div class="access-permission">

                    <i class="bi bi-info-circle-fill"></i>

                    Halaman ini hanya dapat diakses oleh:

                    <strong>
                        <?= $teksRoleDiizinkan; ?>
                    </strong>

                </div>

                <div class="access-actions">

                    <a
                        href="<?= htmlspecialchars(
                            appUrl("dashboard/dashboard.php"),
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>"
                        class="access-button access-button-primary"
                    >
                        <i class="bi bi-house-heart-fill"></i>
                        Kembali ke Dashboard
                    </a>

                    <a
                        href="<?= htmlspecialchars(
                            appUrl("auth/logout.php"),
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>"
                        class="access-button access-button-secondary"
                    >
                        <i class="bi bi-box-arrow-right"></i>
                        Keluar dari Akun
                    </a>

                </div>

                <p class="access-footer-text">
                    Sistem Deteksi dan Pemantauan Stunting
                </p>

            </section>

        </main>

    </body>

    </html>

    <?php

    exit;
}