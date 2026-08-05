<?php
session_start();
require_once "../config/koneksi.php";

// Cek apakah id_balita dikirim
if (!isset($_GET['id_balita'])) {
    echo "<script>
            alert('Balita belum dipilih!');
            window.location='../skrining/hasil_skrining.php';
          </script>";
    exit;
}

$id_balita = (int) $_GET['id_balita'];

/*
=========================================================
AMBIL DATA PENGUKURAN TERAKHIR
=========================================================
*/

$queryPengukuran = mysqli_query($conn, "
SELECT *
FROM pengukuran_antropometri
WHERE id_balita='$id_balita'
ORDER BY tanggal_pengukuran DESC
LIMIT 1
");

if(mysqli_num_rows($queryPengukuran)==0){

    echo "<script>
    alert('Balita belum memiliki data pengukuran!');
    window.location='../pengukuran/data_pengukuran.php';
    </script>";

    exit;

}

$pengukuran = mysqli_fetch_assoc($queryPengukuran);

/*
=========================================================
AMBIL DATA SKRINING
=========================================================
*/

$querySkrining = mysqli_query($conn, "
SELECT *
FROM skrining_awal
WHERE id_balita='$id_balita'
LIMIT 1
");

if(mysqli_num_rows($querySkrining)==0){

    echo "<script>
    alert('Balita belum memiliki data skrining!');
    window.location='../skrining/form_skrining.php';
    </script>";

    exit;

}

$skrining = mysqli_fetch_assoc($querySkrining);

/*
=========================================================
AMBIL DATA BALITA
=========================================================
*/

$queryBalita = mysqli_query($conn,"
SELECT *
FROM balita
WHERE id_balita='$id_balita'
");

$balita = mysqli_fetch_assoc($queryBalita);

/*
=========================================================
RULE BASED
=========================================================
*/

$skor = 0;

$rekomendasi = [];

/*
=========================================================
RULE 4

Protein Hewani
=========================================================
*/

if($skrining['protein_hewani']=="Tidak"){

    $skor++;

    $rekomendasi[] =
    "Tambahkan konsumsi protein hewani.";

}

/*
=========================================================
RULE 5

Sanitasi
=========================================================
*/

if($skrining['sanitasi']=="Kurang"){

    $skor++;

    $rekomendasi[] =
    "Perbaiki sanitasi lingkungan.";

}

/*
=========================================================
RULE 6

Air Bersih
=========================================================
*/

if($skrining['air_bersih']=="Tidak"){

    $skor++;

    $rekomendasi[] =
    "Pastikan menggunakan air bersih.";

}

/*
=========================================================
MENENTUKAN STATUS STUNTING
=========================================================
*/

if($skor <= 1){

    $status_stunting = "Risiko Rendah";

}elseif($skor <=3){

    $status_stunting = "Risiko Sedang";

}else{

    $status_stunting = "Risiko Tinggi";

}

/*
=========================================================
STATUS GIZI (SEMENTARA)
=========================================================
*/

$status_gizi = "Perlu Pemeriksaan";

/*
=========================================================
CEK APAKAH SUDAH PERNAH DILAKUKAN DETEKSI
=========================================================
*/

$id_pengukuran = $pengukuran['id_pengukuran'];

$cek = mysqli_query($conn,"
SELECT *
FROM hasil_deteksi
WHERE id_pengukuran='$id_pengukuran'
");

if(mysqli_num_rows($cek)>0){

    mysqli_query($conn,"
    UPDATE hasil_deteksi
    SET

    status_gizi='$status_gizi',
    status_stunting='$status_stunting',
    tanggal_deteksi=CURDATE()

    WHERE id_pengukuran='$id_pengukuran'
    ");

}else{

    mysqli_query($conn,"
    INSERT INTO hasil_deteksi
    (

    id_pengukuran,
    status_gizi,
    status_stunting,
    tanggal_deteksi

    )

    VALUES
    (

    '$id_pengukuran',
    '$status_gizi',
    '$status_stunting',
    CURDATE()

    )
    ");

}

/*
=========================================================
MENAMPILKAN HASIL ANALISIS
=========================================================
*/
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Analisis Stunting</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.card{
    border:none;
    border-radius:15px;
    box-shadow:0px 5px 15px rgba(0,0,0,.15);
}

.status{
    font-size:22px;
    font-weight:bold;
}

</style>

</head>

<body>

<div class="container mt-5">

<div class="card">

<div class="card-header bg-success text-white">

<h3>Hasil Analisis Risiko Stunting</h3>

</div>

<div class="card-body">

<table class="table">

<tr>

<th width="35%">Nama Balita</th>

<td><?= $balita['nama_balita']; ?></td>

</tr>

<tr>

<th>Umur</th>

<td><?= $pengukuran['umur_bulan']; ?> Bulan</td>

</tr>

<tr>

<th>Berat Badan</th>

<td><?= $pengukuran['berat_badan']; ?> Kg</td>

</tr>

<tr>

<th>Tinggi Badan</th>

<td><?= $pengukuran['tinggi_panjang_badan']; ?> Cm</td>

</tr>

<tr>

<th>Skor Risiko</th>

<td>

<span class="badge bg-primary">

<?= $skor; ?>

</span>

</td>

</tr>

<tr>

<th>Status Gizi</th>

<td>

<span class="badge bg-warning text-dark">

<?= $status_gizi; ?>

</span>

</td>

</tr>

<tr>

<th>Status Risiko</th>

<td>

<?php

if($status_stunting=="Risiko Rendah"){

echo "<span class='badge bg-success status'>$status_stunting</span>";

}elseif($status_stunting=="Risiko Sedang"){

echo "<span class='badge bg-warning text-dark status'>$status_stunting</span>";

}else{

echo "<span class='badge bg-danger status'>$status_stunting</span>";

}

?>

</td>

</tr>

</table>

<hr>

<h5>Rekomendasi</h5>

<ul>

<?php

if(count($rekomendasi)>0){

foreach($rekomendasi as $r){

?>

<li><?= $r; ?></li>

<?php

}

}else{

?>

<li>

Pertahankan pola hidup sehat dan lakukan pemantauan rutin di Posyandu.

</li>

<?php

}

?>

</ul>

<hr>

<a
href="hasil_deteksi.php"
class="btn btn-success">

Lihat Data Hasil Deteksi

</a>

<a
href="../skrining/hasil_skrining.php"
class="btn btn-secondary">

Kembali

</a>

</div>

</div>

</div>

</body>

</html>