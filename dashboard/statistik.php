<?php

/*
|--------------------------------------------------------------------------
| Fungsi menghitung total seluruh tabel
|--------------------------------------------------------------------------
*/

function hitungTotal(mysqli $conn, string $namaTabel): int
{
    $tabelDiizinkan = [
        "balita",
        "pengguna",
        "skrining_awal",
        "hasil_deteksi",
        "pengukuran_antropometri",
        "konsultasi"
    ];

    if (!in_array($namaTabel, $tabelDiizinkan, true)) {
        return 0;
    }

    $query = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM {$namaTabel}"
    );

    if (!$query) {
        return 0;
    }

    $data = mysqli_fetch_assoc($query);

    return (int) ($data["total"] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Fungsi menghitung data milik orang tua
|--------------------------------------------------------------------------
*/

function hitungDataOrangTua(
    mysqli $conn,
    string $jenisData,
    int $idUser
): int {
    $querySql = "";

    if ($jenisData === "balita") {
        $querySql = "
            SELECT COUNT(*) AS total
            FROM balita
            WHERE id_user = ?
        ";
    } elseif ($jenisData === "pengukuran") {
        $querySql = "
            SELECT COUNT(*) AS total
            FROM pengukuran_antropometri pa
            INNER JOIN balita b
                ON pa.id_balita = b.id_balita
            WHERE b.id_user = ?
        ";
    } elseif ($jenisData === "skrining") {
        $querySql = "
            SELECT COUNT(*) AS total
            FROM skrining_awal sa
            INNER JOIN balita b
                ON sa.id_balita = b.id_balita
            WHERE b.id_user = ?
        ";
    } elseif ($jenisData === "hasil_deteksi") {
        $querySql = "
            SELECT COUNT(*) AS total
            FROM hasil_deteksi hd
            INNER JOIN pengukuran_antropometri pa
                ON hd.id_pengukuran = pa.id_pengukuran
            INNER JOIN balita b
                ON pa.id_balita = b.id_balita
            WHERE b.id_user = ?
        ";
    } elseif ($jenisData === "konsultasi") {
        $querySql = "
            SELECT COUNT(*) AS total
            FROM konsultasi k
            INNER JOIN balita b
                ON k.id_balita = b.id_balita
            WHERE b.id_user = ?
        ";
    } else {
        return 0;
    }

    $stmt = mysqli_prepare($conn, $querySql);

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $idUser
    );

    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($hasil);

    mysqli_stmt_close($stmt);

    return (int) ($data["total"] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Mengambil role dan ID pengguna
|--------------------------------------------------------------------------
*/

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Nilai awal statistik
|--------------------------------------------------------------------------
*/

$totalBalita = 0;
$totalPengguna = 0;
$totalSkrining = 0;
$totalHasilDeteksi = 0;
$totalPengukuran = 0;
$totalKonsultasi = 0;

/*
|--------------------------------------------------------------------------
| Statistik untuk Orang Tua
|--------------------------------------------------------------------------
| Orang tua hanya melihat data anak yang terhubung dengan akunnya.
|--------------------------------------------------------------------------
*/

if ($roleAktif === "orang_tua") {
    $totalBalita = hitungDataOrangTua(
        $conn,
        "balita",
        $idUserAktif
    );

    $totalPengukuran = hitungDataOrangTua(
        $conn,
        "pengukuran",
        $idUserAktif
    );

    $totalSkrining = hitungDataOrangTua(
        $conn,
        "skrining",
        $idUserAktif
    );

    $totalHasilDeteksi = hitungDataOrangTua(
        $conn,
        "hasil_deteksi",
        $idUserAktif
    );

    $totalKonsultasi = hitungDataOrangTua(
        $conn,
        "konsultasi",
        $idUserAktif
    );
}

/*
|--------------------------------------------------------------------------
| Statistik untuk aktor lainnya
|--------------------------------------------------------------------------
| Karena database saat ini belum memiliki wilayah kerja atau relasi petugas,
| statistik sementara masih berupa data keseluruhan.
|--------------------------------------------------------------------------
*/

else {
    $totalBalita = hitungTotal(
        $conn,
        "balita"
    );

    $totalSkrining = hitungTotal(
        $conn,
        "skrining_awal"
    );

    $totalHasilDeteksi = hitungTotal(
        $conn,
        "hasil_deteksi"
    );

    $totalPengukuran = hitungTotal(
        $conn,
        "pengukuran_antropometri"
    );

    $totalKonsultasi = hitungTotal(
        $conn,
        "konsultasi"
    );

    /*
     * Total pengguna hanya dibutuhkan oleh Dinas Kesehatan.
     */
    if ($roleAktif === "dinkes") {
        $totalPengguna = hitungTotal(
            $conn,
            "pengguna"
        );
    }
}