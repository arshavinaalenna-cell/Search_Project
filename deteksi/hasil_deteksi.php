<?php
session_start();
require_once "../config/koneksi.php";

/*
====================================================
AMBIL DATA HASIL DETEKSI
====================================================
*/

$query = mysqli_query($conn, "

SELECT
    h.*,
    b.nama_balita,
    p.umur_bulan,
    p.berat_badan,
    p.tinggi_panjang_badan

FROM hasil_deteksi h

INNER JOIN pengukuran_antropometri p
    ON h.id_pengukuran = p.id_pengukuran

INNER JOIN balita b
    ON p.id_balita = b.id_balita

ORDER BY h.tanggal_deteksi DESC

");
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Hasil Deteksi</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{

background:#f5f6fa;

}

.card{

border:none;
border-radius:15px;
box-shadow:0 5px 15px rgba(0,0,0,.15);

}

.table th{

background:#198754;
color:white;
text-align:center;

}

.table td{

text-align:center;
vertical-align:middle;

}

</style>

</head>

<body>

<div class="container mt-4">

<div class="card">

<div class="card-header bg-success text-white d-flex justify-content-between">

<h4>

Hasil Deteksi Risiko Stunting

</h4>

<a
href="../skrining/hasil_skrining.php"
class="btn btn-light">

Kembali

</a>

</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-hover">

<thead>

<tr>

<th>No</th>

<th>Nama Balita</th>

<th>Umur</th>

<th>BB</th>

<th>TB</th>

<th>Status Gizi</th>

<th>Status Risiko</th>

<th>Tanggal</th>

<th>Rekomendasi</th>

</tr>

</thead>

<tbody>

<?php

$no=1;

while($data=mysqli_fetch_assoc($query)){

?>

<tr>

<td><?= $no++; ?></td>

<td><?= $data['nama_balita']; ?></td>

<td><?= $data['umur_bulan']; ?> Bulan</td>

<td><?= $data['berat_badan']; ?> Kg</td>

<td><?= $data['tinggi_panjang_badan']; ?> Cm</td>

<td>

<span class="badge bg-warning text-dark">

<?= $data['status_gizi']; ?>

</span>

</td>

<td>

<?php

if($data['status_stunting']=="Risiko Rendah"){

echo "<span class='badge bg-success'>Risiko Rendah</span>";

}elseif($data['status_stunting']=="Risiko Sedang"){

echo "<span class='badge bg-warning text-dark'>Risiko Sedang</span>";

}else{

echo "<span class='badge bg-danger'>Risiko Tinggi</span>";

}

?>

</td>

<td>

<?= $data['tanggal_deteksi']; ?>

</td>

<td>

<?php

if($data['status_stunting']=="Risiko Rendah"){

echo "Pertahankan pola makan sehat dan rutin ke Posyandu.";

}elseif($data['status_stunting']=="Risiko Sedang"){

echo "Tingkatkan konsumsi protein hewani dan lakukan pemantauan rutin.";

}else{

echo "Segera konsultasi dengan Petugas Gizi/Puskesmas untuk penanganan lebih lanjut.";

}

?>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

</div>

</div>

</body>

</html>