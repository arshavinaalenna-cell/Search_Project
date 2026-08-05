<?php
include '../koneksi.php';

$cari = "";

if(isset($_GET['cari'])){

    $cari = mysqli_real_escape_string($conn,$_GET['cari']);

    $query = mysqli_query($conn,"
    SELECT rk.*, b.nama_balita
    FROM riwayat_kesehatan rk
    JOIN balita b ON rk.id_balita=b.id_balita
    WHERE
        b.nama_balita LIKE '%$cari%' OR
        rk.riwayat_penyakit LIKE '%$cari%' OR
        rk.riwayat_imunisasi LIKE '%$cari%' OR
        rk.riwayat_perawatan LIKE '%$cari%'
    ORDER BY rk.id_riwayat DESC
    ");

}else{

    $query = mysqli_query($conn,"
    SELECT rk.*, b.nama_balita
    FROM riwayat_kesehatan rk
    JOIN balita b ON rk.id_balita=b.id_balita
    ORDER BY rk.id_riwayat DESC
    ");

}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<title>Riwayat Kesehatan Anak</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<h3 class="mb-4">Data Riwayat Kesehatan Anak</h3>

<div class="row mb-3">

<div class="col-md-6">

<form method="GET">

<div class="input-group">

<input
type="text"
name="cari"
class="form-control"
placeholder="Cari Nama Balita..."
value="<?= $cari ?>">

<button class="btn btn-primary">

Cari

</button>

</div>

</form>

</div>

<div class="col-md-6 text-end">

<a href="tambah_kesehatan.php" class="btn btn-success">

+ Tambah Data

</a>

</div>

</div>

<table class="table table-bordered table-striped">

<thead class="table-dark">

<tr>

<th>No</th>

<th>Nama Balita</th>

<th>Riwayat Penyakit</th>

<th>Riwayat Imunisasi</th>

<th>Riwayat Perawatan</th>

<th width="230">Aksi</th>

</tr>

</thead>

<tbody>

<?php

$no=1;

while($d=mysqli_fetch_array($query)){

?>

<tr>

<td><?= $no++; ?></td>

<td><?= $d['nama_balita']; ?></td>

<td><?= $d['riwayat_penyakit']; ?></td>

<td><?= $d['riwayat_imunisasi']; ?></td>

<td><?= $d['riwayat_perawatan']; ?></td>

<td>

<a
href="detail_kesehatan.php?id=<?= $d['id_riwayat']; ?>"
class="btn btn-info btn-sm">

Detail

</a>

<a
href="edit_kesehatan.php?id=<?= $d['id_riwayat']; ?>"
class="btn btn-warning btn-sm">

Edit

</a>

<a
href="hapus_kesehatan.php?id=<?= $d['id_riwayat']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Yakin ingin menghapus data ini?')">

Hapus

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</body>

</html>