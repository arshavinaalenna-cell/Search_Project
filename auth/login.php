<?php
session_start();

if (isset($_SESSION["id_user"])) {
    header("Location: ../dashboard/dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Versi CSS
|--------------------------------------------------------------------------
*/

$fileCss = __DIR__ . "/../assets/css/style.css";

$versiCss = file_exists($fileCss)
    ? filemtime($fileCss)
    : "1.0";

/*
|--------------------------------------------------------------------------
| Pesan Login
|--------------------------------------------------------------------------
*/

$pesan = $_GET["pesan"] ?? "";

$pesanLogin = null;
$jenisAlert = "danger";
$ikonAlert = "bi-exclamation-circle";

switch ($pesan) {
    case "belum_login":
        $pesanLogin = "Silakan login terlebih dahulu untuk mengakses sistem.";
        $jenisAlert = "warning";
        $ikonAlert = "bi-shield-exclamation";
        break;

    case "kosong":
        $pesanLogin = "Username dan password wajib diisi.";
        $jenisAlert = "warning";
        $ikonAlert = "bi-exclamation-triangle";
        break;

    case "logout":
        $pesanLogin = "Kamu berhasil logout dari sistem.";
        $jenisAlert = "success";
        $ikonAlert = "bi-check-circle";
        break;

    case "gagal":
    case "salah":
        $pesanLogin = "Username atau password yang dimasukkan salah.";
        $jenisAlert = "danger";
        $ikonAlert = "bi-x-circle";
        break;

    default:
        if ($pesan !== "") {
            $pesanLogin = "Username atau password yang dimasukkan salah.";
        }
        break;
}
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
        name="description"
        content="Login Sistem Informasi Deteksi dan Pemantauan Stunting"
    >

    <meta
        name="theme-color"
        content="#d96f93"
    >

    <title>
        Login | Sistem Deteksi Stunting
    </title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- CSS utama aplikasi -->
    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=<?= $versiCss ?>"
    >
</head>

<body class="login-page">

    <main class="login-container">

        <!-- Bagian informasi -->
        <section class="login-visual">

            <div class="d-flex align-items-center gap-3 mb-auto">

                <span class="quick-card-icon mb-0">
                    <i class="bi bi-heart-pulse-fill"></i>
                </span>

                <div>
                    <strong class="d-block fs-5">
                        Sistem Deteksi Stunting
                    </strong>

                    <small class="text-secondary">
                        Pemantauan tumbuh kembang balita
                    </small>
                </div>

            </div>

            <div class="mt-5">

                <span class="badge badge-primary mb-3">
                    <i class="bi bi-stars"></i>
                    Tumbuh Sehat Bersama
                </span>

                <h1 class="mb-3 fw-bold">
                    Pantau pertumbuhan balita dengan lebih mudah.
                </h1>

                <p class="mb-4">
                    Sistem terintegrasi untuk membantu kader, tenaga
                    kesehatan, orang tua, puskesmas, dan dinas kesehatan
                    dalam melakukan pemantauan serta deteksi risiko
                    stunting.
                </p>

                <div class="d-grid gap-3">

                    <div class="d-flex align-items-start gap-3">
                        <span class="badge badge-primary">
                            <i class="bi bi-rulers"></i>
                        </span>

                        <div>
                            <strong class="d-block">
                                Pemantauan Antropometri
                            </strong>

                            <small class="text-secondary">
                                Catat dan pantau perkembangan fisik balita.
                            </small>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <span class="badge badge-info">
                            <i class="bi bi-clipboard2-pulse"></i>
                        </span>

                        <div>
                            <strong class="d-block">
                                Skrining dan Deteksi
                            </strong>

                            <small class="text-secondary">
                                Identifikasi risiko stunting secara berkala.
                            </small>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <span class="badge badge-success">
                            <i class="bi bi-chat-heart"></i>
                        </span>

                        <div>
                            <strong class="d-block">
                                Konsultasi dan Monitoring
                            </strong>

                            <small class="text-secondary">
                                Mendukung tindak lanjut kesehatan dan gizi.
                            </small>
                        </div>
                    </div>

                </div>

            </div>

        </section>

        <!-- Bagian formulir -->
        <section class="login-panel">

            <div class="login-card">

                <div class="mb-4">

                    <span class="badge badge-primary mb-3">
                        <i class="bi bi-shield-check"></i>
                        Akses Terverifikasi
                    </span>

                    <h2 class="login-title">
                        Selamat datang
                    </h2>

                    <p class="login-subtitle">
                        Masukkan username dan password untuk mengakses
                        sistem sesuai hak akses akunmu.
                    </p>

                </div>

                <?php if ($pesanLogin !== null): ?>

                    <div
                        class="alert alert-<?= htmlspecialchars(
                            $jenisAlert,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?> d-flex align-items-start gap-2"
                        role="alert"
                    >
                        <i
                            class="bi <?= htmlspecialchars(
                                $ikonAlert,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            aria-hidden="true"
                        ></i>

                        <span>
                            <?= htmlspecialchars(
                                $pesanLogin,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </span>
                    </div>

                <?php endif; ?>

                <form
                    action="proses_login.php"
                    method="POST"
                    autocomplete="on"
                >

                    <div class="form-group">

                        <label
                            for="username"
                            class="form-label"
                        >
                            <i class="bi bi-person me-1"></i>
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control"
                            placeholder="Masukkan username"
                            autocomplete="username"
                            autofocus
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label
                            for="password"
                            class="form-label"
                        >
                            <i class="bi bi-lock me-1"></i>
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary btn-lg w-100 mt-2"
                    >
                        <i class="bi bi-box-arrow-in-right"></i>
                        Masuk ke Sistem
                    </button>

                </form>

                <div class="text-center mt-4">

                    <small class="text-secondary">
                        <i class="bi bi-shield-lock me-1"></i>
                        Informasi akunmu dilindungi dan digunakan
                        sesuai hak akses.
                    </small>

                </div>

            </div>

        </section>

    </main>

</body>

</html>