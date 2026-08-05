<?php

session_start();

require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Pastikan halaman hanya diproses melalui form POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil dan membersihkan input
|--------------------------------------------------------------------------
*/

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    header("Location: login.php?pesan=kosong");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mencari pengguna berdasarkan username
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $conn,
    "SELECT id_user, nama, username, password, role
     FROM pengguna
     WHERE username = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Terjadi kesalahan pada sistem login.");
}

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    mysqli_stmt_close($stmt);

    header("Location: login.php?pesan=gagal");
    exit;
}

/*
|--------------------------------------------------------------------------
| Pemeriksaan password
|--------------------------------------------------------------------------
|
| Database saat ini masih menggunakan password biasa.
| Nantinya password akan kita ubah menjadi password_hash().
|
*/

$passwordBenar = false;

/*
 * Mendukung password yang sudah di-hash.
 */
if (password_verify($password, $data["password"])) {
    $passwordBenar = true;
}

/*
 * Mendukung sementara password lama yang masih plain text.
 */
if ($password === $data["password"]) {
    $passwordBenar = true;
}

if (!$passwordBenar) {
    mysqli_stmt_close($stmt);

    header("Location: login.php?pesan=gagal");
    exit;
}

/*
|--------------------------------------------------------------------------
| Membuat session login
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION["id_user"] = $data["id_user"];
$_SESSION["nama"] = $data["nama"];
$_SESSION["username"] = $data["username"];
$_SESSION["role"] = $data["role"];

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Masuk ke dashboard
|--------------------------------------------------------------------------
*/

header("Location: ../dashboard/dashboard.php");
exit;