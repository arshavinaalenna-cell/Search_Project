<?php
session_start();
require_once "koneksi.php";

// Jika sudah login
if (isset($_SESSION['id_user'])) {
    header("Location: dashboard.php");
    exit;
}

// Jika belum login
header("Location: login.php");
exit;
?>