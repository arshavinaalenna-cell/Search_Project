<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$judulHalaman = $judulHalaman ?? "Sistem Deteksi Stunting";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Sistem Informasi Deteksi dan Pemantauan Stunting"
    >

    <title>
        <?= htmlspecialchars(
            $judulHalaman,
            ENT_QUOTES,
            "UTF-8"
        ) ?>
    </title>

    <!-- Font modern -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- CSS project -->
    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=<?= time(); ?>"
    >
</head>

<body>