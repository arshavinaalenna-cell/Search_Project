<?php

session_start();

require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hanya menerima pengiriman form POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil input
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
| Fungsi kembali ke registrasi jika terjadi kesalahan
|--------------------------------------------------------------------------
*/

function kembaliRegister(string $pesan): void
{
    $_SESSION["register_error"] = $pesan;

    header("Location: register.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Validasi kolom kosong
|--------------------------------------------------------------------------
*/

if (
    $nama === ""
    || $username === ""
    || $password === ""
    || $konfirmasiPassword === ""
) {
    kembaliRegister(
        "Semua kolom wajib diisi."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi nama
|--------------------------------------------------------------------------
*/

if (strlen($nama) < 3) {
    kembaliRegister(
        "Nama lengkap minimal 3 karakter."
    );
}

if (strlen($nama) > 100) {
    kembaliRegister(
        "Nama lengkap maksimal 100 karakter."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi username
|--------------------------------------------------------------------------
*/

if (strlen($username) < 4) {
    kembaliRegister(
        "Username minimal 4 karakter."
    );
}

if (strlen($username) > 50) {
    kembaliRegister(
        "Username maksimal 50 karakter."
    );
}

if (
    !preg_match(
        "/^[A-Za-z0-9._-]+$/",
        $username
    )
) {
    kembaliRegister(
        "Username hanya boleh menggunakan huruf, angka, titik, garis bawah, atau tanda hubung."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi password
|--------------------------------------------------------------------------
*/

if (strlen($password) < 8) {
    kembaliRegister(
        "Password minimal 8 karakter."
    );
}

if ($password !== $konfirmasiPassword) {
    kembaliRegister(
        "Konfirmasi password tidak sama."
    );
}

/*
|--------------------------------------------------------------------------
| Memeriksa username
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
    kembaliRegister(
        "Sistem gagal memeriksa username."
    );
}

mysqli_stmt_bind_param(
    $stmtCek,
    "s",
    $username
);

mysqli_stmt_execute($stmtCek);

$resultCek = mysqli_stmt_get_result($stmtCek);

$dataUsername = mysqli_fetch_assoc($resultCek);

mysqli_stmt_close($stmtCek);

if ($dataUsername) {
    kembaliRegister(
        "Username sudah digunakan. Gunakan username lain."
    );
}

/*
|--------------------------------------------------------------------------
| Membuat password hash
|--------------------------------------------------------------------------
*/

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

if ($passwordHash === false) {
    kembaliRegister(
        "Password gagal diproses."
    );
}

/*
|--------------------------------------------------------------------------
| Role otomatis orang tua
|--------------------------------------------------------------------------
*/

$role = "orang_tua";

/*
|--------------------------------------------------------------------------
| Menyimpan akun
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
    kembaliRegister(
        "Sistem gagal menyiapkan registrasi."
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

if (!mysqli_stmt_execute($stmtInsert)) {

    $kodeError = mysqli_stmt_errno($stmtInsert);

    mysqli_stmt_close($stmtInsert);

    if ($kodeError === 1062) {
        kembaliRegister(
            "Username sudah digunakan."
        );
    }

    kembaliRegister(
        "Registrasi gagal disimpan. Silakan coba lagi."
    );
}

mysqli_stmt_close($stmtInsert);

/*
|--------------------------------------------------------------------------
| Menghapus data sementara
|--------------------------------------------------------------------------
*/

unset($_SESSION["register_old"]);
unset($_SESSION["register_error"]);

/*
|--------------------------------------------------------------------------
| Kembali ke login
|--------------------------------------------------------------------------
*/

header(
    "Location: login.php?pesan=registrasi_sukses"
);

exit;