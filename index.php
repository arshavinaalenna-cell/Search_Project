<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION["id_user"])) {
    header("Location: dashboard/dashboard.php");
    exit;
}

header("Location: auth/login.php");
exit;