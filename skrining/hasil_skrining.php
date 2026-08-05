<?php
session_start();
require_once "../config/koneksi.php";

// Ambil data skrining beserta nama balita
$query = mysqli_query($conn,"
SELECT s.*, b.nama_balita
FROM skrining_awal s
INNER JOIN balita b
ON s.id_balita = b.id_balita
ORDER BY s.id_skrining DESC
");
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Hasil Skrining</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.card{
    border:none;
    border-radius:15px;
    box-shadow:0px 5px 15px rgba(0,0,0,.1);
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

<div class="card-header bg-success text-white d-flex justify-content-between align-items-center">

<h4 class="mb-0">
Data Skrining Awal
</h4>

<a
href="form_skrining.php"
class="btn btn-light">

+ Tambah Skrining

</a>

</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-hover">

<thead>

<tr>

<th>No</th>
<th>Nama Balita</th>
<th>Tinggi Ibu</th>
<th>Pendidikan</th>
<th>Pekerjaan</th>
<th>ASI</th>
<th>MPASI</th>
<th>Frekuensi Makan</th>
<th>Protein Hewani</th>
<th>Status Ekonomi</th>
<th>Sanitasi</th>
<th>Air Bersih</th>
<th>Aksi</th>

</tr>

</thead>

<tbody>

<?php

$no=1;

while($data=mysqli_fetch_assoc($query))
{

?>

<tr>

<td><?= $no++; ?></td>

<td><?= $data['nama_balita']; ?></td>

<td><?= $data['tinggi_badan_ibu']; ?> cm</td>

<td><?= $data['pendidikan_ibu']; ?></td>

<td><?= $data['pekerjaan_ibu']; ?></td>

<td><?= $data['lama_asi_eksklusif']; ?> Bulan</td>

<td><?= $data['mpasi']; ?></td>

<td><?= $data['frekuensi_makan']; ?></td>

<td><?= $data['protein_hewani']; ?></td>

<td><?= $data['status_ekonomi']; ?></td>

<td><?= $data['sanitasi']; ?></td>

<td><?= $data['air_bersih']; ?></td>

<td>

<a
href="edit_skrining.php?id=<?= $data['id_skrining']; ?>"
class="btn btn-warning btn-sm">

Edit

</a>

<a
href="hapus_skrining.php?id=<?= $data['id_skrining']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Yakin ingin menghapus data ini?')">

Hapus

</a>

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