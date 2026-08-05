<?php
session_start();
require_once "../config/koneksi.php";

// Mengambil data pengukuran beserta nama balita
$query = mysqli_query($conn, "
    SELECT p.*, b.nama_balita
    FROM pengukuran_antropometri p
    INNER JOIN balita b
        ON p.id_balita = b.id_balita
    ORDER BY p.tanggal_pengukuran DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Pengukuran</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.card{
    border:none;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.1);
}

.table th{
    background:#198754;
    color:white;
    text-align:center;
}

.table td{
    vertical-align:middle;
    text-align:center;
}

</style>

</head>

<body>

<?php
// Jika nanti sudah ada header/navbar tinggal aktifkan
// include "../includes/header.php";
// include "../includes/navbar.php";
// include "../includes/sidebar.php";
?>

<div class="container mt-4">

<div class="card">

<div class="card-header bg-success text-white d-flex justify-content-between align-items-center">

<h4 class="mb-0">
Data Pengukuran Antropometri
</h4>

<a href="tambah_pengukuran.php" class="btn btn-light">
+ Tambah Pengukuran
</a>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead>

<tr>

<th>No</th>
<th>Nama Balita</th>
<th>Tanggal</th>
<th>Umur (Bulan)</th>
<th>BB (kg)</th>
<th>TB/PB (cm)</th>
<th>Lingkar Kepala</th>
<th>LiLA</th>
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

<td><?= $data['tanggal_pengukuran']; ?></td>

<td><?= $data['umur_bulan']; ?></td>

<td><?= $data['berat_badan']; ?></td>

<td><?= $data['tinggi_panjang_badan']; ?></td>

<td><?= $data['lingkar_kepala']; ?></td>

<td><?= $data['lila']; ?></td>

<td>

<a
href="edit_pengukuran.php?id=<?= $data['id_pengukuran']; ?>"
class="btn btn-warning btn-sm">
Edit
</a>

<a
href="hapus_pengukuran.php?id=<?= $data['id_pengukuran']; ?>"
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