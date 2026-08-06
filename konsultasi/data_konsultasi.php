<?php

include '../koneksi.php';

// Ambil kata kunci pencarian
$kataKunci = isset($_GET['keyword']) ? $_GET['keyword'] : '';

if (!empty($kataKunci)) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            k.id_konsultasi,
            k.id_balita,
            k.id_petugas,
            k.tanggal,
            k.hasil_konsultasi,
            k.tindak_lanjut,
            b.nama_balita,
            b.nik_balita,
            p.nama AS nama_petugas
        FROM konsultasi k
        INNER JOIN balita b
            ON k.id_balita = b.id_balita
        LEFT JOIN pengguna p
            ON k.id_petugas = p.id_user
        WHERE 
            b.nama_balita LIKE ?
            OR b.nik_balita LIKE ?
            OR p.nama LIKE ?
            OR k.hasil_konsultasi LIKE ?
            OR k.tindak_lanjut LIKE ?
            OR k.tanggal LIKE ?
        ORDER BY k.id_konsultasi DESC"
    );

    $search = "%" . $kataKunci . "%";

    mysqli_stmt_bind_param(
        $stmt,
        "ssssss",
        $search,
        $search,
        $search,
        $search,
        $search,
        $search
    );

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            k.id_konsultasi,
            k.id_balita,
            k.id_petugas,
            k.tanggal,
            k.hasil_konsultasi,
            k.tindak_lanjut,
            b.nama_balita,
            b.nik_balita,
            p.nama AS nama_petugas
        FROM konsultasi k
        INNER JOIN balita b
            ON k.id_balita = b.id_balita
        LEFT JOIN pengguna p
            ON k.id_petugas = p.id_user
        ORDER BY k.id_konsultasi DESC"
    );
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

?>