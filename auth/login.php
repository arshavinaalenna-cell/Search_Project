<?php
session_start();

if (isset($_SESSION['id_user'])) {
    header("Location: ../dashboard/dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Sistem Deteksi Stunting</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: linear-gradient(135deg, #4CAF50, #81C784);
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, Helvetica, sans-serif;
        }

        .login-box {
            width: 400px;
            max-width: calc(100% - 30px);
            background: #ffffff;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .login-box h3 {
            text-align: center;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .login-box p {
            text-align: center;
            color: #777777;
            margin-bottom: 25px;
        }

        .btn-success {
            width: 100%;
        }

        .logo {
            display: block;
            width: 90px;
            margin: 0 auto 15px;
        }
    </style>
</head>

<body>

<div class="login-box">


    <h3>Sistem Deteksi Stunting</h3>
    <p>Silakan login untuk melanjutkan</p>

    <?php if (isset($_GET['pesan'])): ?>

        <?php if ($_GET['pesan'] === 'belum_login'): ?>
            <div class="alert alert-warning">
                Silakan login terlebih dahulu.
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                Username atau password salah.
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <form action="proses_login.php" method="POST">

        <div class="mb-3">
            <label for="username" class="form-label">
                Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                class="form-control"
                autocomplete="username"
                required
            >
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-success">
            Login
        </button>

    </form>

</div>

</body>
</html>