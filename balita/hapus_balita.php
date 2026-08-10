<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

$idKaderAktif =
    (int) ($_SESSION["id_user"] ?? 0);

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

mysqli_stmt_bind_param(
    $stmtKader,
    "i",
    $idKaderAktif
);

mysqli_stmt_execute($stmtKader);

$hasilKader =
    mysqli_stmt_get_result(
        $stmtKader
    );

$dataKader =
    mysqli_fetch_assoc(
        $hasilKader
    );

mysqli_stmt_close($stmtKader);

$idPuskesmasKader =
    !empty($dataKader["id_puskesmas"])
        ? (int) $dataKader["id_puskesmas"]
        : 0;

if ($idPuskesmasKader < 1) {
    header("Location: data_balita.php?pesan=puskesmas_belum_terhubung");
    exit;
}

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
     AND id_puskesmas = ?
     LIMIT 1"
);

if (!$cekBalita) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param(
    $cekBalita,
    "ii",
    $idBalita,
    $idPuskesmasKader
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
     WHERE id_balita = ?
     AND id_puskesmas = ?"
);

if (!$hapus) {
    header("Location: data_balita.php?pesan=hapus_gagal");
    exit;
}

mysqli_stmt_bind_param(
    $hapus,
    "ii",
    $idBalita,
    $idPuskesmasKader
);

/*
|--------------------------------------------------------------------------
| Menjalankan penghapusan
|--------------------------------------------------------------------------
|
| mysqli pada konfigurasi saat ini melempar mysqli_sql_exception ketika
| foreign key masih dipakai. Karena itu execute harus ditangkap dengan
| try/catch agar halaman tidak berhenti sebagai Fatal Error.
|
*/

try {

    mysqli_stmt_execute($hapus);

    $jumlahTerhapus =
        mysqli_stmt_affected_rows(
            $hapus
        );

    mysqli_stmt_close($hapus);

    if ($jumlahTerhapus > 0) {
        header(
            "Location: data_balita.php?pesan=hapus_berhasil"
        );
        exit;
    }

    header(
        "Location: data_balita.php?pesan=tidak_ditemukan"
    );
    exit;

} catch (mysqli_sql_exception $exception) {

    $nomorError =
        (int) $exception->getCode();

    mysqli_stmt_close($hapus);

    if ($nomorError === 1451) {
        header(
            "Location: data_balita.php?pesan=masih_digunakan"
        );
        exit;
    }

    header(
        "Location: data_balita.php?pesan=hapus_gagal"
    );
    exit;
}