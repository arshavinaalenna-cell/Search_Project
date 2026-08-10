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
    "petugas_kia"
]);

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Hanya menerima request POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header(
        "Location: riwayat_kelahiran.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Petugas KIA aktif
|--------------------------------------------------------------------------
*/

$stmtPuskesmasAkun = mysqli_prepare(
    $conn,
    "SELECT id_puskesmas
     FROM pengguna
     WHERE id_user = ?
     AND role = 'petugas_kia'
     LIMIT 1"
);

if (!$stmtPuskesmasAkun) {
    header(
        "Location: riwayat_kelahiran.php?pesan=gagal_hapus"
    );
    exit;
}

mysqli_stmt_bind_param(
    $stmtPuskesmasAkun,
    "i",
    $idUserAktif
);

mysqli_stmt_execute(
    $stmtPuskesmasAkun
);

$hasilPuskesmasAkun =
    mysqli_stmt_get_result(
        $stmtPuskesmasAkun
    );

$dataPuskesmasAkun =
    mysqli_fetch_assoc(
        $hasilPuskesmasAkun
    );

mysqli_stmt_close(
    $stmtPuskesmasAkun
);

$idPuskesmasAktif =
    !empty(
        $dataPuskesmasAkun["id_puskesmas"]
    )
        ? (int) $dataPuskesmasAkun[
            "id_puskesmas"
        ]
        : 0;

if ($idPuskesmasAktif < 1) {
    header(
        "Location: riwayat_kelahiran.php"
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
        "Location: riwayat_kelahiran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mencari nama primary key tabel
|--------------------------------------------------------------------------
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
| Memastikan data tersedia dan satu Puskesmas
|--------------------------------------------------------------------------
*/

$stmtCek = mysqli_prepare(
    $conn,
    "SELECT
        rk.`$kolomPrimaryKey`
     FROM riwayat_kelahiran AS rk
     INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita
     WHERE rk.`$kolomPrimaryKey` = ?
     AND b.id_puskesmas = ?
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
    "ii",
    $idRiwayat,
    $idPuskesmasAktif
);

mysqli_stmt_execute($stmtCek);

$hasilCek =
    mysqli_stmt_get_result(
        $stmtCek
    );

$dataRiwayat =
    mysqli_fetch_assoc(
        $hasilCek
    );

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
|
| DELETE juga dibatasi berdasarkan Puskesmas agar manipulasi POST
| tidak dapat menghapus riwayat milik wilayah lain.
|
*/

$stmtHapus = mysqli_prepare(
    $conn,
    "DELETE rk
     FROM riwayat_kelahiran AS rk
     INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita
     WHERE rk.`$kolomPrimaryKey` = ?
     AND b.id_puskesmas = ?"
);

if (!$stmtHapus) {
    header(
        "Location: riwayat_kelahiran.php?pesan=gagal_hapus"
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
            "Location: riwayat_kelahiran.php?pesan=hapus_berhasil"
        );
        exit;
    }

    header(
        "Location: riwayat_kelahiran.php?pesan=tidak_ditemukan"
    );
    exit;

} catch (mysqli_sql_exception $exception) {

    mysqli_stmt_close(
        $stmtHapus
    );

    /*
    |----------------------------------------------------------------------
    | Tidak menghapus data turunan secara otomatis.
    | Jika suatu saat riwayat ini dipakai tabel lain, tampilkan gagal hapus
    | dan pertahankan integritas data.
    |----------------------------------------------------------------------
    */

    header(
        "Location: riwayat_kelahiran.php?pesan=gagal_hapus"
    );
    exit;
}
