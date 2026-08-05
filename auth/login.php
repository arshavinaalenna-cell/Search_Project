<?php

session_start();

/*
|--------------------------------------------------------------------------
| Pengguna yang sudah login langsung masuk dashboard
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["id_user"])) {
    header("Location: ../dashboard/dashboard.php");
    exit;
}

$pesan = $_GET["pesan"] ?? "";

$jenisAlert = "";
$isiPesan   = "";

switch ($pesan) {

    case "kosong":
        $jenisAlert = "warning";
        $isiPesan   = "Username dan password wajib diisi.";
        break;

    case "gagal":
        $jenisAlert = "danger";
        $isiPesan   = "Username atau password salah.";
        break;

    case "registrasi_sukses":
        $jenisAlert = "success";
        $isiPesan   =
            "Registrasi berhasil. Silakan login menggunakan akun baru.";
        break;

    case "logout":
        $jenisAlert = "success";
        $isiPesan   = "Anda berhasil keluar dari sistem.";
        break;

    case "belum_login":
        $jenisAlert = "warning";
        $isiPesan   =
            "Silakan login terlebih dahulu untuk mengakses halaman tersebut.";
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

    <title>
        Login | Sistem Deteksi Stunting
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            min-height: 100vh;
            background:
                linear-gradient(
                    135deg,
                    #198754,
                    #20c997
                );
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 25px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            border: none;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, .20);
        }

        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .login-header h3 {
            color: #198754;
            font-weight: 700;
        }

        .form-control {
            border-radius: 10px;
            padding: 11px 14px;
        }

        .btn-login {
            border-radius: 10px;
            padding: 11px;
            font-weight: 600;
        }

    </style>

</head>

<body>

<div class="card login-card">

    <div class="card-body p-4 p-md-5">

        <div class="login-header">

            <h3>
                Sistem Deteksi Stunting
            </h3>

            <p class="text-muted mb-0">
                Silakan login untuk masuk ke sistem.
            </p>

        </div>

        <?php if ($isiPesan !== ""): ?>

            <div
                class="alert alert-<?= $jenisAlert ?>
                alert-dismissible fade show"
                role="alert"
            >

                <?= htmlspecialchars(
                    $isiPesan,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>

        <form
            action="proses_login.php"
            method="POST"
        >

            <div class="mb-3">

                <label
                    for="username"
                    class="form-label"
                >
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    id="username"
                    class="form-control"
                    placeholder="Masukkan username"
                    autocomplete="username"
                    required
                    autofocus
                >

            </div>

            <div class="mb-4">

                <label
                    for="password"
                    class="form-label"
                >
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <div class="d-grid">

                <button
                    type="submit"
                    class="btn btn-success btn-login"
                >
                    Login
                </button>

            </div>

        </form>

        <div class="text-center mt-4">

            <span class="text-muted">
                Belum memiliki akun?
            </span>

            <a
                href="register.php"
                class="text-success fw-semibold text-decoration-none"
            >
                Daftar sebagai orang tua
            </a>

        </div>

    </div>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>