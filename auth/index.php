<?php

session_start();

if (!isset($_SESSION['id_user'])) {
    header("Location: auth/login.php");
    exit;
}

header("Location: dashboard/dashboard.php");
exit;