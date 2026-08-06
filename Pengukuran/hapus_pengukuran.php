<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
*/

cekRole(["kader"]);

/*
|--------------------------------------------------------------------------
| Hanya menerima request POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header(
        "Location: data_pengukuran.php?pesan=akses_tidak_valid"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Memvalidasi ID pengukuran
|--------------------------------------------------------------------------
*/

$idPengukuran = filter_input(
    INPUT_POST,
    "id_pengukuran",
    FILTER_VALIDATE_INT
);

if (
    !$idPengukuran
    || $idPengukuran < 1
) {
    header(
        "Location: data_pengukuran.php?pesan=id_tidak_valid"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Memastikan data pengukuran tersedia
|--------------------------------------------------------------------------
*/

$stmtCek = mysqli_prepare(
    $conn,
    "SELECT id_pengukuran
     FROM pengukuran_antropometri
     WHERE id_pengukuran = ?
     LIMIT 1"
);

if (!$stmtCek) {
    header(
        "Location: data_pengukuran.php?pesan=gagal_hapus"
    );
    exit;
}

mysqli_stmt_bind_param(
    $stmtCek,
    "i",
    $idPengukuran
);

mysqli_stmt_execute($stmtCek);

$resultCek = mysqli_stmt_get_result($stmtCek);

$dataPengukuran = mysqli_fetch_assoc($resultCek);

mysqli_stmt_close($stmtCek);

if (!$dataPengukuran) {
    header(
        "Location: data_pengukuran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Menghapus data pengukuran
|--------------------------------------------------------------------------
*/

$stmtHapus = mysqli_prepare(
    $conn,
    "DELETE FROM pengukuran_antropometri
     WHERE id_pengukuran = ?"
);

if (!$stmtHapus) {
    header(
        "Location: data_pengukuran.php?pesan=gagal_hapus"
    );
    exit;
}

mysqli_stmt_bind_param(
    $stmtHapus,
    "i",
    $idPengukuran
);

$berhasil = mysqli_stmt_execute($stmtHapus);

$kodeError = mysqli_stmt_errno($stmtHapus);

mysqli_stmt_close($stmtHapus);

/*
|--------------------------------------------------------------------------
| Redirect berdasarkan hasil
|--------------------------------------------------------------------------
*/

if ($berhasil) {
    header(
        "Location: data_pengukuran.php?pesan=hapus_berhasil"
    );
    exit;
}

/*
 * Error 1451 berarti data masih digunakan tabel lain,
 * misalnya tabel hasil_deteksi.
 */

if ($kodeError === 1451) {
    header(
        "Location: data_pengukuran.php?pesan=data_digunakan"
    );
    exit;
}

header(
    "Location: data_pengukuran.php?pesan=gagal_hapus"
);
exit;