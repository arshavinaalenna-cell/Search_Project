<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$judulHalaman = $judulHalaman ?? "Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Versi CSS
|--------------------------------------------------------------------------
| CSS hanya dimuat ulang ketika file style.css benar-benar diubah.
| Lebih baik daripada memakai time() pada setiap halaman dibuka.
*/

$fileCss = __DIR__ . "/../assets/css/style.css";

$versiCss = file_exists($fileCss)
    ? filemtime($fileCss)
    : "1.0";
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

    <meta
        name="theme-color"
        content="#e91e8d"
    >

    <title>
        <?= htmlspecialchars(
            $judulHalaman,
            ENT_QUOTES,
            "UTF-8"
        ) ?>
    </title>

    <!-- Font utama aplikasi -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

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

    <!-- CSS utama aplikasi -->
    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=<?= $versiCss ?>"
    >
</head>

<body class="app-body">