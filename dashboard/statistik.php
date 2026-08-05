<?php

/*
|--------------------------------------------------------------------------
| Statistik Dashboard
|--------------------------------------------------------------------------
| File ini dipanggil setelah koneksi database tersedia.
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

$totalBalita = hitungTotal($conn, "balita");
$totalPengguna = hitungTotal($conn, "pengguna");
$totalSkrining = hitungTotal($conn, "skrining_awal");
$totalHasilDeteksi = hitungTotal($conn, "hasil_deteksi");
$totalPengukuran = hitungTotal($conn, "pengukuran_antropometri");
$totalKonsultasi = hitungTotal($conn, "konsultasi");