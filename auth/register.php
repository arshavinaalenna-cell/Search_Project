<?php

session_start();

/*
|--------------------------------------------------------------------------
| Halaman registrasi dapat dibuka tanpa login
|--------------------------------------------------------------------------
*/

$error = $_SESSION["register_error"] ?? "";
$old   = $_SESSION["register_old"] ?? [];

unset($_SESSION["register_error"]);
unset($_SESSION["register_old"]);

$namaLama     = $old["nama"] ?? "";
$usernameLama = $old["username"] ?? "";

function aman(string $teks): string
{
    return htmlspecialchars($teks, ENT_QUOTES, "UTF-8");
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
        Registrasi | Sistem Deteksi Stunting
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

            --cream: #fffaf4;
            --text-dark: #495466;
            --text-soft: #7b8798;
            --white-glass: rgba(255, 255, 255, .92);
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
                    circle at 10% 20%,
                    rgba(255, 255, 255, .9) 0 60px,
                    transparent 61px
                ),
                radial-gradient(
                    circle at 90% 15%,
                    rgba(255, 255, 255, .65) 0 80px,
                    transparent 81px
                ),
                linear-gradient(
                    135deg,
                    var(--pink-soft),
                    var(--blue-soft)
                );

            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 18px;
            position: relative;
            overflow-x: hidden;
        }

        .bubble {
            position: fixed;
            border-radius: 50%;
            z-index: 0;
            opacity: .55;
            filter: blur(.2px);
        }

        .bubble-one {
            width: 150px;
            height: 150px;
            background: #ffffff;
            top: -55px;
            left: -40px;
        }

        .bubble-two {
            width: 110px;
            height: 110px;
            background: #ffc8dc;
            right: 6%;
            bottom: 8%;
        }

        .bubble-three {
            width: 85px;
            height: 85px;
            background: #b9ddfa;
            left: 7%;
            bottom: 8%;
        }

        .register-wrapper {
            width: 100%;
            max-width: 530px;
            position: relative;
            z-index: 2;
        }

        .register-card {
            border: 4px solid rgba(255, 255, 255, .8);
            border-radius: 30px;
            overflow: hidden;

            background: var(--white-glass);

            box-shadow:
                0 22px 55px rgba(112, 136, 167, .22);

            backdrop-filter: blur(10px);
        }

        .register-top {
            text-align: center;
            padding: 32px 28px 24px;

            background:
                linear-gradient(
                    135deg,
                    rgba(255, 219, 232, .85),
                    rgba(220, 238, 255, .9)
                );

            border-bottom:
                2px dashed rgba(143, 199, 241, .45);
        }

        .baby-icon {
            width: 92px;
            height: 92px;
            margin: 0 auto 15px;

            background:
                linear-gradient(
                    145deg,
                    #ffffff,
                    #fff3f7
                );

            border: 5px solid #ffffff;
            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 48px;

            box-shadow:
                0 10px 25px rgba(223, 120, 159, .18);
        }

        .register-top h1 {
            margin-bottom: 8px;
            color: var(--text-dark);
            font-size: 29px;
            font-weight: 800;
        }

        .register-top p {
            margin: 0;
            color: var(--text-soft);
            font-size: 15px;
            line-height: 1.6;
        }

        .register-body {
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

            color: var(--blue-dark);
        }

        .form-control {
            min-height: 50px;

            border: 2px solid #d8e8f6;
            border-left: 0;

            border-radius: 0 15px 15px 0;

            color: var(--text-dark);
            background: rgba(255, 255, 255, .94);

            padding: 11px 14px;
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
            border: 2px solid #d8e8f6;
            border-left: 0;

            background: #ffffff;
            color: var(--text-soft);

            border-radius: 0 15px 15px 0;

            min-width: 52px;
        }

        .password-toggle:hover {
            background: var(--pink-soft);
            color: var(--pink-dark);
        }

        .form-text {
            color: #9099a7;
            font-size: 12px;
            margin-left: 4px;
        }

        .btn-register {
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
            letter-spacing: .2px;

            box-shadow:
                0 10px 25px rgba(133, 177, 215, .3);

            transition: .2s ease;
        }

        .btn-register:hover {
            color: #ffffff;
            transform: translateY(-2px);

            background:
                linear-gradient(
                    135deg,
                    var(--pink-dark),
                    var(--blue-dark)
                );
        }

        .login-area {
            margin-top: 24px;
            text-align: center;
            color: var(--text-soft);
        }

        .login-link {
            display: inline-block;
            margin-left: 4px;

            color: var(--pink-dark);
            font-weight: 800;
            text-decoration: none;
        }

        .login-link:hover {
            color: var(--blue-dark);
            text-decoration: underline;
        }

        .role-info {
            padding: 12px 15px;
            margin-bottom: 22px;

            border-radius: 15px;
            background:
                linear-gradient(
                    135deg,
                    rgba(255, 219, 232, .55),
                    rgba(220, 238, 255, .65)
                );

            color: var(--text-soft);
            font-size: 13px;
            text-align: center;
        }

        .alert {
            border: 0;
            border-radius: 15px;
            font-size: 14px;
        }

        .alert-danger {
            background: #ffe4eb;
            color: #a74466;
        }

        @media (max-width: 576px) {

            .register-body {
                padding: 25px 21px 28px;
            }

            .register-top {
                padding: 27px 20px 21px;
            }

            .register-top h1 {
                font-size: 24px;
            }

            .baby-icon {
                width: 78px;
                height: 78px;
                font-size: 39px;
            }

        }

    </style>

</head>

<body>

<div class="bubble bubble-one"></div>
<div class="bubble bubble-two"></div>
<div class="bubble bubble-three"></div>

<div class="register-wrapper">

    <div class="register-card">

        <div class="register-top">

            <div class="baby-icon">
                👶
            </div>

            <h1>
                Buat Akun Orang Tua
            </h1>

            <p>
                Pantau tumbuh kembang buah hati dengan lebih mudah,
                aman, dan menyenangkan.
            </p>

        </div>

        <div class="register-body">

            <?php if ($error !== ""): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <strong>Ups!</strong>

                    <?= aman($error); ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>

                </div>

            <?php endif; ?>

            <div class="role-info">
                🍼 Akun yang dibuat otomatis terdaftar sebagai
                <strong>orang tua</strong>.
            </div>

            <form
                action="proses_register.php"
                method="POST"
                autocomplete="off"
            >

                <div class="mb-3">

                    <label
                        for="nama"
                        class="form-label"
                    >
                        Nama Lengkap
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            ♡
                        </span>

                        <input
                            type="text"
                            name="nama"
                            id="nama"
                            class="form-control"
                            maxlength="100"
                            value="<?= aman($namaLama); ?>"
                            placeholder="Masukkan nama lengkap"
                            required
                            autofocus
                        >

                    </div>

                </div>

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
                            minlength="4"
                            maxlength="50"
                            value="<?= aman($usernameLama); ?>"
                            placeholder="Contoh: bunda_ayu"
                            autocomplete="username"
                            required
                        >

                    </div>

                    <div class="form-text">
                        Minimal 4 karakter tanpa spasi.
                    </div>

                </div>

                <div class="mb-3">

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
                            minlength="8"
                            placeholder="Minimal 8 karakter"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn password-toggle"
                            onclick="togglePassword('password', this)"
                        >
                            👁
                        </button>

                    </div>

                </div>

                <div class="mb-4">

                    <label
                        for="konfirmasi_password"
                        class="form-label"
                    >
                        Konfirmasi Password
                    </label>

                    <div class="input-group password-group">

                        <span class="input-group-text">
                            🔐
                        </span>

                        <input
                            type="password"
                            name="konfirmasi_password"
                            id="konfirmasi_password"
                            class="form-control"
                            minlength="8"
                            placeholder="Ulangi password"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn password-toggle"
                            onclick="togglePassword('konfirmasi_password', this)"
                        >
                            👁
                        </button>

                    </div>

                </div>

                <div class="d-grid">

                    <button
                        type="submit"
                        class="btn btn-register"
                    >
                        ♡ Daftar Sekarang
                    </button>

                </div>

            </form>

            <div class="login-area">

                Sudah punya akun?

                <a
                    href="login.php"
                    class="login-link"
                >
                    Masuk di sini
                </a>

            </div>

        </div>

    </div>

</div>

<script>

function togglePassword(inputId, tombol)
{
    const input = document.getElementById(inputId);

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