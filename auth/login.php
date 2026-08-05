<?php

session_start();

/*
|--------------------------------------------------------------------------
| Kalau sudah login, masuk ke dashboard
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
        $isiPesan =
            "Username dan password wajib diisi.";
        break;

    case "gagal":
        $jenisAlert = "danger";
        $isiPesan =
            "Username atau password salah.";
        break;

    case "registrasi_sukses":
        $jenisAlert = "success";
        $isiPesan =
            "Registrasi berhasil. Silakan masuk menggunakan akun baru.";
        break;

    case "logout":
        $jenisAlert = "success";
        $isiPesan =
            "Kamu berhasil keluar dari sistem.";
        break;

    case "belum_login":
        $jenisAlert = "warning";
        $isiPesan =
            "Silakan login terlebih dahulu.";
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

        :root {
            --pink-soft: #ffdbe8;
            --pink-main: #f59fbd;
            --pink-dark: #df789f;

            --blue-soft: #dceeff;
            --blue-main: #8fc7f1;
            --blue-dark: #5ca6dd;

            --text-dark: #495466;
            --text-soft: #7b8798;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background:
                radial-gradient(
                    circle at 12% 18%,
                    rgba(255, 255, 255, .85) 0 70px,
                    transparent 71px
                ),
                radial-gradient(
                    circle at 88% 84%,
                    rgba(255, 255, 255, .65) 0 90px,
                    transparent 91px
                ),
                linear-gradient(
                    135deg,
                    var(--pink-soft),
                    var(--blue-soft)
                );

            display: flex;
            justify-content: center;
            align-items: center;

            padding: 30px 18px;
        }

        .login-card {
            width: 100%;
            max-width: 470px;

            border: 4px solid rgba(255, 255, 255, .82);
            border-radius: 30px;

            overflow: hidden;

            background: rgba(255, 255, 255, .93);

            box-shadow:
                0 22px 55px rgba(112, 136, 167, .22);
        }

        .login-top {
            padding: 33px 28px 25px;

            text-align: center;

            background:
                linear-gradient(
                    135deg,
                    rgba(255, 219, 232, .9),
                    rgba(220, 238, 255, .9)
                );

            border-bottom:
                2px dashed rgba(143, 199, 241, .45);
        }

        .baby-icon {
            width: 92px;
            height: 92px;

            margin: 0 auto 15px;

            border-radius: 50%;
            border: 5px solid #ffffff;

            background: #fff8fb;

            display: flex;
            justify-content: center;
            align-items: center;

            font-size: 48px;

            box-shadow:
                0 10px 25px rgba(223, 120, 159, .18);
        }

        .login-top h1 {
            margin-bottom: 8px;

            color: var(--text-dark);

            font-size: 29px;
            font-weight: 800;
        }

        .login-top p {
            margin: 0;

            color: var(--text-soft);

            font-size: 15px;
            line-height: 1.6;
        }

        .login-body {
            padding: 30px 34px 34px;
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .input-group-text {
            width: 48px;

            justify-content: center;

            background: var(--blue-soft);

            border: 2px solid #d8e8f6;
            border-right: 0;

            border-radius: 15px 0 0 15px;
        }

        .form-control {
            min-height: 50px;

            border: 2px solid #d8e8f6;
            border-left: 0;

            border-radius: 0 15px 15px 0;

            padding: 11px 14px;

            color: var(--text-dark);
        }

        .form-control:focus {
            border-color: var(--blue-main);

            box-shadow:
                0 0 0 .22rem rgba(143, 199, 241, .22);
        }

        .password-group .form-control {
            border-radius: 0;
            border-right: 0;
        }

        .password-toggle {
            min-width: 52px;

            border: 2px solid #d8e8f6;
            border-left: 0;

            border-radius: 0 15px 15px 0;

            background: #ffffff;
        }

        .password-toggle:hover {
            background: var(--pink-soft);
        }

        .btn-login {
            min-height: 52px;

            border: 0;
            border-radius: 16px;

            background:
                linear-gradient(
                    135deg,
                    var(--pink-main),
                    var(--blue-main)
                );

            color: #ffffff;

            font-size: 16px;
            font-weight: 800;

            box-shadow:
                0 10px 25px rgba(133, 177, 215, .3);

            transition: .2s ease;
        }

        .btn-login:hover {
            color: #ffffff;

            transform: translateY(-2px);

            background:
                linear-gradient(
                    135deg,
                    var(--pink-dark),
                    var(--blue-dark)
                );
        }

        .register-area {
            margin-top: 24px;

            text-align: center;

            color: var(--text-soft);
        }

        .register-link {
            display: inline-block;
            margin-left: 4px;

            color: var(--pink-dark);

            font-weight: 800;
            text-decoration: none;
        }

        .register-link:hover {
            color: var(--blue-dark);
            text-decoration: underline;
        }

        .alert {
            border: 0;
            border-radius: 15px;
            font-size: 14px;
        }

        @media (max-width: 576px) {

            .login-body {
                padding: 25px 21px 28px;
            }

            .login-top {
                padding: 27px 20px 21px;
            }

            .login-top h1 {
                font-size: 24px;
            }

        }

    </style>

</head>

<body>

<div class="login-card">

    <div class="login-top">

        <div class="baby-icon">
            👶
        </div>

        <h1>
            Tumbuh Bersama
        </h1>

        <p>
            Sistem pemantauan dan deteksi dini stunting
            untuk tumbuh kembang buah hati.
        </p>

    </div>

    <div class="login-body">

        <?php if ($isiPesan !== ""): ?>

            <div
                class="alert alert-<?= htmlspecialchars(
                    $jenisAlert,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?> alert-dismissible fade show"
                role="alert"
            >

                <?= htmlspecialchars(
                    $isiPesan,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>

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

                <div class="input-group">

                    <span class="input-group-text">
                        ☁
                    </span>

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

            </div>

            <div class="mb-4">

                <label
                    for="password"
                    class="form-label"
                >
                    Password
                </label>

                <div class="input-group password-group">

                    <span class="input-group-text">
                        🔒
                    </span>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="btn password-toggle"
                        onclick="togglePassword()"
                    >
                        👁
                    </button>

                </div>

            </div>

            <div class="d-grid">

                <button
                    type="submit"
                    class="btn btn-login"
                >
                    ♡ Masuk ke Sistem
                </button>

            </div>

        </form>

        <!-- Tombol registrasi sengaja berada di luar form login -->
        <div class="register-area">

            Belum memiliki akun?

            <a
                href="register.php"
                class="register-link"
            >
                Daftar sebagai orang tua
            </a>

        </div>

    </div>

</div>

<script>

function togglePassword()
{
    const input = document.getElementById("password");
    const tombol = document.querySelector(
        ".password-toggle"
    );

    if (input.type === "password") {
        input.type = "text";
        tombol.innerHTML = "🙈";
    } else {
        input.type = "password";
        tombol.innerHTML = "👁";
    }
}

</script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>