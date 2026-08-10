<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_kia"]);

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Petugas KIA aktif
|--------------------------------------------------------------------------
*/

$stmtPuskesmas = mysqli_prepare(
    $conn,
    "SELECT id_puskesmas
     FROM pengguna
     WHERE id_user = ?
     AND role = 'petugas_kia'
     LIMIT 1"
);

if (!$stmtPuskesmas) {
    header(
        "Location: riwayat_kesehatan.php?pesan=hapus_gagal"
    );
    exit;
}

mysqli_stmt_bind_param(
    $stmtPuskesmas,
    "i",
    $idUserAktif
);

mysqli_stmt_execute($stmtPuskesmas);

$hasilPuskesmas =
    mysqli_stmt_get_result($stmtPuskesmas);

$dataPuskesmas =
    mysqli_fetch_assoc($hasilPuskesmas);

mysqli_stmt_close($stmtPuskesmas);

if (
    !$dataPuskesmas
    || empty($dataPuskesmas["id_puskesmas"])
) {
    header(
        "Location: riwayat_kesehatan.php?pesan=hapus_gagal"
    );
    exit;
}

$idPuskesmasAktif =
    (int) $dataPuskesmas["id_puskesmas"];

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
    "SELECT rk.id_riwayat
     FROM riwayat_kesehatan AS rk
     INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita
     WHERE rk.id_riwayat = ?
     AND b.id_puskesmas = ?
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
    "ii",
    $idRiwayat,
    $idPuskesmasAktif
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
    "DELETE rk
     FROM riwayat_kesehatan AS rk
     INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita
     WHERE rk.id_riwayat = ?
     AND b.id_puskesmas = ?"
);

if (!$stmtHapus) {
    header(
        "Location: riwayat_kesehatan.php?pesan=hapus_gagal"
    );
    exit;
}

mysqli_stmt_bind_param(
    $stmtHapus,
    "ii",
    $idRiwayat,
    $idPuskesmasAktif
);

try {

    mysqli_stmt_execute(
        $stmtHapus
    );

    $jumlahTerhapus =
        mysqli_stmt_affected_rows(
            $stmtHapus
        );

    mysqli_stmt_close(
        $stmtHapus
    );

    if ($jumlahTerhapus > 0) {
        header(
            "Location: riwayat_kesehatan.php?pesan=hapus_berhasil"
        );
        exit;
    }

    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;

} catch (mysqli_sql_exception $exception) {

    mysqli_stmt_close(
        $stmtHapus
    );

    header(
        "Location: riwayat_kesehatan.php?pesan=hapus_gagal"
    );
    exit;
}