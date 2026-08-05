<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["dinkes"]);

/*
|--------------------------------------------------------------------------
| Hanya menerima metode POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: data_user.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil ID pengguna
|--------------------------------------------------------------------------
*/

$idUser = filter_input(
    INPUT_POST,
    "id_user",
    FILTER_VALIDATE_INT
);

if (!$idUser) {
    header("Location: data_user.php?pesan=hapus_gagal");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mencegah pengguna menghapus akun sendiri
|--------------------------------------------------------------------------
*/

if ($idUser === (int) $_SESSION["id_user"]) {
    header("Location: data_user.php?pesan=hapus_sendiri");
    exit;
}

/*
|--------------------------------------------------------------------------
| Memastikan pengguna tersedia
|--------------------------------------------------------------------------
*/

$cekPengguna = mysqli_prepare(
    $conn,
    "SELECT id_user, nama
     FROM pengguna
     WHERE id_user = ?
     LIMIT 1"
);

if (!$cekPengguna) {
    header("Location: data_user.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param(
    $cekPengguna,
    "i",
    $idUser
);

mysqli_stmt_execute($cekPengguna);

$hasilCek = mysqli_stmt_get_result($cekPengguna);
$dataPengguna = mysqli_fetch_assoc($hasilCek);

mysqli_stmt_close($cekPengguna);

if (!$dataPengguna) {
    header("Location: data_user.php?pesan=tidak_ditemukan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Menghapus pengguna
|--------------------------------------------------------------------------
*/

$hapus = mysqli_prepare(
    $conn,
    "DELETE FROM pengguna
     WHERE id_user = ?"
);

if (!$hapus) {
    header("Location: data_user.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param(
    $hapus,
    "i",
    $idUser
);

if (mysqli_stmt_execute($hapus)) {
    mysqli_stmt_close($hapus);

    header("Location: data_user.php?pesan=hapus_berhasil");
    exit;
}

mysqli_stmt_close($hapus);

header("Location: data_user.php?pesan=hapus_gagal");
exit;