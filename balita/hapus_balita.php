<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

$idKaderAktif = (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Kader aktif
|--------------------------------------------------------------------------
*/

$stmtKader = mysqli_prepare(
    $conn,
    "SELECT id_puskesmas
     FROM pengguna
     WHERE id_user = ?
       AND role = 'kader'
     LIMIT 1"
);

if (!$stmtKader) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param($stmtKader, "i", $idKaderAktif);
mysqli_stmt_execute($stmtKader);
$hasilKader = mysqli_stmt_get_result($stmtKader);
$dataKader = mysqli_fetch_assoc($hasilKader);
mysqli_stmt_close($stmtKader);

$idPuskesmasKader = !empty($dataKader["id_puskesmas"])
    ? (int) $dataKader["id_puskesmas"]
    : 0;

if ($idPuskesmasKader < 1) {
    header("Location: data_balita.php?pesan=puskesmas_belum_terhubung");
    exit;
}

/*
|--------------------------------------------------------------------------
| Hanya menerima POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: data_balita.php");
    exit;
}

$idBalita = filter_input(INPUT_POST, "id_balita", FILTER_VALIDATE_INT);

if (!$idBalita) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

/*
|--------------------------------------------------------------------------
| Pastikan balita milik wilayah Puskesmas Kader
|--------------------------------------------------------------------------
*/

$stmtBalita = mysqli_prepare(
    $conn,
    "SELECT id_balita
     FROM balita
     WHERE id_balita = ?
       AND id_puskesmas = ?
     LIMIT 1"
);

if (!$stmtBalita) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param($stmtBalita, "ii", $idBalita, $idPuskesmasKader);
mysqli_stmt_execute($stmtBalita);
$hasilBalita = mysqli_stmt_get_result($stmtBalita);
$dataBalita = mysqli_fetch_assoc($hasilBalita);
mysqli_stmt_close($stmtBalita);

if (!$dataBalita) {
    header("Location: data_balita.php?pesan=tidak_ditemukan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Lock penghapusan jika data sudah dipakai
|--------------------------------------------------------------------------
|
| Balita yang sudah mempunyai salah satu data pelayanan di bawah tidak
| boleh dihapus. Riwayat tetap disimpan dan tombol Hapus di halaman daftar
| akan tampil sebagai tombol terkunci.
|
*/

$stmtTerpakai = mysqli_prepare(
    $conn,
    "SELECT (
        EXISTS (
            SELECT 1
            FROM pengukuran_antropometri
            WHERE id_balita = ?
            LIMIT 1
        )
        OR EXISTS (
            SELECT 1
            FROM skrining_awal
            WHERE id_balita = ?
            LIMIT 1
        )
        OR EXISTS (
            SELECT 1
            FROM riwayat_kelahiran
            WHERE id_balita = ?
            LIMIT 1
        )
        OR EXISTS (
            SELECT 1
            FROM riwayat_kesehatan
            WHERE id_balita = ?
            LIMIT 1
        )
        OR EXISTS (
            SELECT 1
            FROM konsultasi
            WHERE id_balita = ?
            LIMIT 1
        )
    ) AS data_terpakai"
);

if (!$stmtTerpakai) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param(
    $stmtTerpakai,
    "iiiii",
    $idBalita,
    $idBalita,
    $idBalita,
    $idBalita,
    $idBalita
);

mysqli_stmt_execute($stmtTerpakai);
$hasilTerpakai = mysqli_stmt_get_result($stmtTerpakai);
$dataTerpakai = mysqli_fetch_assoc($hasilTerpakai);
mysqli_stmt_close($stmtTerpakai);

if ((int) ($dataTerpakai["data_terpakai"] ?? 0) === 1) {
    header("Location: data_balita.php?pesan=masih_digunakan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Hanya data yang benar-benar belum dipakai yang boleh dihapus
|--------------------------------------------------------------------------
*/

$stmtHapus = mysqli_prepare(
    $conn,
    "DELETE FROM balita
     WHERE id_balita = ?
       AND id_puskesmas = ?"
);

if (!$stmtHapus) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param($stmtHapus, "ii", $idBalita, $idPuskesmasKader);

try {
    mysqli_stmt_execute($stmtHapus);
    $jumlahTerhapus = mysqli_stmt_affected_rows($stmtHapus);
    mysqli_stmt_close($stmtHapus);

    if ($jumlahTerhapus === 1) {
        header("Location: data_balita.php?pesan=hapus_berhasil");
        exit;
    }

    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;

} catch (Throwable $exception) {
    if (isset($stmtHapus) && $stmtHapus) {
        mysqli_stmt_close($stmtHapus);
    }

    error_log(
        "Hapus balita gagal. ID: " . $idBalita
        . ". Pesan: " . $exception->getMessage()
    );

    /* Jika ada relasi baru yang belum tercakup, tetap jangan paksa hapus. */
    header("Location: data_balita.php?pesan=masih_digunakan");
    exit;
}
