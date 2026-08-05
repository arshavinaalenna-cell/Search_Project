<?php
session_start();

if (isset($_SESSION['id_user'])) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sistem Deteksi Stunting</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background: linear-gradient(135deg,#4CAF50,#81C784);
            height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            font-family:Arial, Helvetica, sans-serif;
        }

        .login-box{
            width:400px;
            background:#fff;
            padding:35px;
            border-radius:15px;
            box-shadow:0 10px 25px rgba(0,0,0,.2);
        }

        .login-box h3{
            text-align:center;
            margin-bottom:10px;
            font-weight:bold;
        }

        .login-box p{
            text-align:center;
            color:#777;
            margin-bottom:25px;
        }

        .btn-success{
            width:100%;
        }

        img{
            display:block;
            margin:auto;
            width:90px;
            margin-bottom:15px;
        }
    </style>
</head>

<body>

<div class="login-box">

    <img src="assets/img/logo.png" alt="Logo">

    <h3>Sistem Deteksi Stunting</h3>
    <p>Silakan login untuk melanjutkan</p>

    <?php
    if(isset($_GET['pesan'])){
        echo '<div class="alert alert-danger">Username atau Password salah!</div>';
    }
    ?>

    <form action="proses_login.php" method="POST">

        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text"
                   name="username"
                   class="form-control"
                   required>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password"
                   name="password"
                   class="form-control"
                   required>
        </div>

        <button type="submit" class="btn btn-success">
            Login
        </button>

    </form>

</div>

</body>
</html>