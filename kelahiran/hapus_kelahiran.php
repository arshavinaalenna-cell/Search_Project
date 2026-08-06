<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
*/

cekRole([
    "kader",
    "petugas_kia"
]);

/*
|--------------------------------------------------------------------------
| Hanya menerima request POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header(
        "Location: riwayat_kelahiran.php?pesan=akses_tidak_valid"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Validasi ID
|--------------------------------------------------------------------------
*/

$idRiwayat = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idRiwayat || $idRiwayat < 1) {
    header(
        "Location: riwayat_kelahiran.php?pesan=id_tidak_valid"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mencari nama primary key tabel
|--------------------------------------------------------------------------
|
| Tetap bekerja jika nama primary key adalah:
| id_kelahiran, id_riwayat, atau id_riwayat_kelahiran.
|
*/

$queryPrimaryKey = mysqli_query(
    $conn,
    "SHOW KEYS
     FROM riwayat_kelahiran
     WHERE Key_name = 'PRIMARY'"
);

$dataPrimaryKey = $queryPrimaryKey
    ? mysqli_fetch_assoc($queryPrimaryKey)
    : null;

$kolomPrimaryKey =
    $dataPrimaryKey["Column_name"] ?? "";

if ($kolomPrimaryKey === "") {
    header(
        "Location: riwayat_kelahiran.php?pesan=gagal_hapus"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Memastikan data tersedia
|--------------------------------------------------------------------------
*/

$stmtCek = mysqli_prepare(
    $conn,
    "SELECT `$kolomPrimaryKey`
     FROM riwayat_kelahiran
     WHERE `$kolomPrimaryKey` = ?
     LIMIT 1"
);

if (!$stmtCek) {
    header(
        "Location: riwayat_kelahiran.php?pesan=gagal_hapus"
    );
    exit;
}

mysqli_stmt_bind_param(
    $stmtCek,
    "i",
    $idRiwayat
);

mysqli_stmt_execute($stmtCek);

$hasilCek = mysqli_stmt_get_result($stmtCek);

$dataRiwayat = mysqli_fetch_assoc($hasilCek);

mysqli_stmt_close($stmtCek);

if (!$dataRiwayat) {
    header(
        "Location: riwayat_kelahiran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Menghapus data
|--------------------------------------------------------------------------
*/

$stmtHapus = mysqli_prepare(
    $conn,
    "DELETE FROM riwayat_kelahiran
     WHERE `$kolomPrimaryKey` = ?"
);

if (!$stmtHapus) {
    header(
        "Location: riwayat_kelahiran.php?pesan=gagal_hapus"
    );
    exit;
}

mysqli_stmt_bind_param(
    $stmtHapus,
    "i",
    $idRiwayat
);

$berhasil = mysqli_stmt_execute($stmtHapus);

$kodeError = mysqli_stmt_errno($stmtHapus);

mysqli_stmt_close($stmtHapus);

/*
|--------------------------------------------------------------------------
| Redirect hasil
|--------------------------------------------------------------------------
*/

if ($berhasil) {
    header(
        "Location: riwayat_kelahiran.php?pesan=hapus_berhasil"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Data masih digunakan oleh tabel lain
|--------------------------------------------------------------------------
*/

if ($kodeError === 1451) {
    header(
        "Location: riwayat_kelahiran.php?pesan=data_digunakan"
    );
    exit;
}

header(
    "Location: riwayat_kelahiran.php?pesan=gagal_hapus"
);
exit;