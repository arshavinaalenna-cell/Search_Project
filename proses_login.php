<?php
session_start();
require_once "koneksi.php";

if (isset($_POST['username']) && isset($_POST['password'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $query = mysqli_query($conn, "SELECT * FROM pengguna WHERE username='$username'");

    if (mysqli_num_rows($query) > 0) {

        $data = mysqli_fetch_assoc($query);

        // Jika password masih plain text
        if ($password == $data['password']) {

            $_SESSION['id_user']  = $data['id_user'];
            $_SESSION['nama']     = $data['nama'];
            $_SESSION['username'] = $data['username'];
            $_SESSION['role']     = $data['role'];

            header("Location: dashboard.php");
            exit;

        } else {

            header("Location: login.php?pesan=gagal");
            exit;

        }

    } else {

        header("Location: login.php?pesan=gagal");
        exit;

    }

} else {

    header("Location: login.php");
    exit;

}
?>