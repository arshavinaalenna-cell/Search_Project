<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| File penghubung verifikasi skrining
|--------------------------------------------------------------------------
|
| Verifikasi utama hanya dilakukan pada hasil_deteksi.
| File ini dipertahankan agar link lama dari detail_skrining.php
| tetap berfungsi tanpa membuat sistem verifikasi kedua.
|
*/

cekRole(["petugas_gizi"]);

$idSkrining = (int) ($_GET["id"] ?? 0);

if ($idSkrining <= 0) {
    header(
        "Location: hasil_skrining.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Cari hasil deteksi terbaru milik balita dari skrining tersebut
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        hd.id_deteksi
     FROM skrining_awal s
     INNER JOIN pengukuran_antropometri pa
        ON pa.id_balita = s.id_balita
     INNER JOIN hasil_deteksi hd
        ON hd.id_pengukuran = pa.id_pengukuran
     WHERE s.id_skrining = ?
     ORDER BY
        hd.tanggal_deteksi DESC,
        hd.id_deteksi DESC
     LIMIT 1"
);

if (!$stmt) {
    die(
        "Gagal menyiapkan data verifikasi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $idSkrining
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$data =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Jika belum ada hasil deteksi
|--------------------------------------------------------------------------
*/

if (!$data) {
    header(
        "Location: detail_skrining.php?id="
        . $idSkrining
        . "&pesan=belum_ada_deteksi"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Arahkan ke satu-satunya halaman verifikasi
|--------------------------------------------------------------------------
*/

$idDeteksi =
    (int) $data["id_deteksi"];

header(
    "Location: ../deteksi/verifikasi_deteksi.php?id="
    . $idDeteksi
    . "&kembali=skrining"
);

exit;
