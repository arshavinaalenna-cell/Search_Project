<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Arahkan berdasarkan status login
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["id_user"])) {
    header("Location: dashboard/dashboard.php");
    exit;
}

header("Location: auth/login.php");
exit;