<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_kia"]);

/*
|--------------------------------------------------------------------------
| Proses hapus hanya boleh melalui POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: riwayat_kesehatan.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil ID riwayat
|--------------------------------------------------------------------------
*/

$idRiwayat = filter_input(
    INPUT_POST,
    "id_riwayat",
    FILTER_VALIDATE_INT
);

if (!$idRiwayat) {
    header(
        "Location: riwayat_kesehatan.php?pesan=hapus_gagal"
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
    "SELECT id_riwayat
     FROM riwayat_kesehatan
     WHERE id_riwayat = ?
     LIMIT 1"
);

if (!$stmtCek) {
    header(
        "Location: riwayat_kesehatan.php?pesan=hapus_gagal"
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
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Menghapus riwayat kesehatan
|--------------------------------------------------------------------------
*/

$stmtHapus = mysqli_prepare(
    $conn,
    "DELETE FROM riwayat_kesehatan
     WHERE id_riwayat = ?"
);

if (!$stmtHapus) {
    header(
        "Location: riwayat_kesehatan.php?pesan=hapus_gagal"
    );
    exit;
}

mysqli_stmt_bind_param(
    $stmtHapus,
    "i",
    $idRiwayat
);

if (mysqli_stmt_execute($stmtHapus)) {
    mysqli_stmt_close($stmtHapus);

    header(
        "Location: riwayat_kesehatan.php?pesan=hapus_berhasil"
    );
    exit;
}

mysqli_stmt_close($stmtHapus);

header(
    "Location: riwayat_kesehatan.php?pesan=hapus_gagal"
);
exit;