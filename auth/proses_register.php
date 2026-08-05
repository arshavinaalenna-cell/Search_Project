<?php

session_start();

require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hanya menerima request POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil input registrasi
|--------------------------------------------------------------------------
*/

$nama = trim($_POST["nama"] ?? "");

$username = trim($_POST["username"] ?? "");

$password = $_POST["password"] ?? "";

$konfirmasiPassword =
    $_POST["konfirmasi_password"] ?? "";

/*
|--------------------------------------------------------------------------
| Menyimpan input lama
|--------------------------------------------------------------------------
*/

$_SESSION["register_old"] = [
    "nama"     => $nama,
    "username" => $username
];

/*
|--------------------------------------------------------------------------
| Fungsi untuk mengembalikan pengguna ke halaman registrasi
|--------------------------------------------------------------------------
*/

function kembaliDenganError(string $pesan): void
{
    $_SESSION["register_error"] = $pesan;

    header("Location: register.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Validasi input kosong
|--------------------------------------------------------------------------
*/

if (
    $nama === ""
    || $username === ""
    || $password === ""
    || $konfirmasiPassword === ""
) {
    kembaliDenganError(
        "Semua kolom registrasi wajib diisi."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi nama
|--------------------------------------------------------------------------
*/

if (strlen($nama) < 3) {
    kembaliDenganError(
        "Nama lengkap minimal terdiri dari 3 karakter."
    );
}

if (strlen($nama) > 100) {
    kembaliDenganError(
        "Nama lengkap maksimal terdiri dari 100 karakter."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi username
|--------------------------------------------------------------------------
*/

if (strlen($username) < 4) {
    kembaliDenganError(
        "Username minimal terdiri dari 4 karakter."
    );
}

if (strlen($username) > 50) {
    kembaliDenganError(
        "Username maksimal terdiri dari 50 karakter."
    );
}

if (
    !preg_match(
        "/^[A-Za-z0-9._-]+$/",
        $username
    )
) {
    kembaliDenganError(
        "Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi password
|--------------------------------------------------------------------------
*/

if (strlen($password) < 8) {
    kembaliDenganError(
        "Password minimal terdiri dari 8 karakter."
    );
}

if ($password !== $konfirmasiPassword) {
    kembaliDenganError(
        "Konfirmasi password tidak sama dengan password."
    );
}

/*
|--------------------------------------------------------------------------
| Memeriksa username yang sudah terdaftar
|--------------------------------------------------------------------------
*/

$stmtCek = mysqli_prepare(
    $conn,
    "SELECT id_user
     FROM pengguna
     WHERE username = ?
     LIMIT 1"
);

if (!$stmtCek) {
    kembaliDenganError(
        "Terjadi kesalahan saat memeriksa username."
    );
}

mysqli_stmt_bind_param(
    $stmtCek,
    "s",
    $username
);

mysqli_stmt_execute($stmtCek);

$hasilCek = mysqli_stmt_get_result($stmtCek);

$penggunaLama = mysqli_fetch_assoc($hasilCek);

mysqli_stmt_close($stmtCek);

if ($penggunaLama) {
    kembaliDenganError(
        "Username sudah digunakan. Silakan gunakan username lain."
    );
}

/*
|--------------------------------------------------------------------------
| Mengenkripsi password
|--------------------------------------------------------------------------
*/

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

if ($passwordHash === false) {
    kembaliDenganError(
        "Password gagal diproses."
    );
}

/*
|--------------------------------------------------------------------------
| Role registrasi publik selalu orang_tua
|--------------------------------------------------------------------------
|
| Role tidak diambil dari form agar pengguna tidak dapat mendaftarkan
| dirinya sebagai petugas, kepala puskesmas, atau dinas kesehatan.
|
*/

$role = "orang_tua";

/*
|--------------------------------------------------------------------------
| Menyimpan akun ke tabel pengguna
|--------------------------------------------------------------------------
*/

$stmtInsert = mysqli_prepare(
    $conn,
    "INSERT INTO pengguna
        (
            nama,
            username,
            password,
            role
        )
     VALUES (?, ?, ?, ?)"
);

if (!$stmtInsert) {
    kembaliDenganError(
        "Terjadi kesalahan saat menyiapkan registrasi."
    );
}

mysqli_stmt_bind_param(
    $stmtInsert,
    "ssss",
    $nama,
    $username,
    $passwordHash,
    $role
);

$berhasil = mysqli_stmt_execute($stmtInsert);

if (!$berhasil) {

    $nomorError = mysqli_stmt_errno($stmtInsert);

    mysqli_stmt_close($stmtInsert);

    /*
     * Error 1062 berarti username duplikat.
     */
    if ($nomorError === 1062) {
        kembaliDenganError(
            "Username sudah digunakan. Silakan gunakan username lain."
        );
    }

    kembaliDenganError(
        "Registrasi gagal disimpan. Silakan coba kembali."
    );
}

mysqli_stmt_close($stmtInsert);

/*
|--------------------------------------------------------------------------
| Menghapus data sementara registrasi
|--------------------------------------------------------------------------
*/

unset($_SESSION["register_old"]);
unset($_SESSION["register_error"]);

/*
|--------------------------------------------------------------------------
| Kembali ke halaman login
|--------------------------------------------------------------------------
*/

header(
    "Location: login.php?pesan=registrasi_sukses"
);

exit;