<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

/*
|--------------------------------------------------------------------------
| Hanya menerima permintaan POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: data_balita.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil ID balita
|--------------------------------------------------------------------------
*/

$idBalita = filter_input(
    INPUT_POST,
    "id_balita",
    FILTER_VALIDATE_INT
);

if (!$idBalita) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

/*
|--------------------------------------------------------------------------
| Memastikan data balita tersedia
|--------------------------------------------------------------------------
*/

$cekBalita = mysqli_prepare(
    $conn,
    "SELECT id_balita, nama_balita
     FROM balita
     WHERE id_balita = ?
     LIMIT 1"
);

if (!$cekBalita) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param(
    $cekBalita,
    "i",
    $idBalita
);

mysqli_stmt_execute($cekBalita);

$hasilBalita = mysqli_stmt_get_result($cekBalita);
$dataBalita = mysqli_fetch_assoc($hasilBalita);

mysqli_stmt_close($cekBalita);

if (!$dataBalita) {
    header("Location: data_balita.php?pesan=tidak_ditemukan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Menghapus data balita
|--------------------------------------------------------------------------
*/

$hapus = mysqli_prepare(
    $conn,
    "DELETE FROM balita
     WHERE id_balita = ?"
);

if (!$hapus) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param(
    $hapus,
    "i",
    $idBalita
);

if (mysqli_stmt_execute($hapus)) {
    mysqli_stmt_close($hapus);

    header("Location: data_balita.php?pesan=hapus_berhasil");
    exit;
}

/*
|--------------------------------------------------------------------------
| Jika gagal karena data masih terhubung ke tabel lain
|--------------------------------------------------------------------------
*/

$nomorError = mysqli_stmt_errno($hapus);

mysqli_stmt_close($hapus);

if ($nomorError === 1451) {
    header("Location: data_balita.php?pesan=masih_digunakan");
    exit;
}

header("Location: data_balita.php?pesan=hapus_gagal");
exit;