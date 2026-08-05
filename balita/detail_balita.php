<?php
include '../koneksi.php';

// Cek apakah ID dikirim
if(!isset($_GET['id'])){
    header("Location: data_balita.php");
    exit;
}

$id = $_GET['id'];

// Ambil data balita
$query = mysqli_query($conn,"SELECT * FROM balita WHERE id_balita='$id'");
$data = mysqli_fetch_assoc($query);

// Jika data tidak ditemukan
if(!$data){
    echo "<script>
            alert('Data balita tidak ditemukan!');
            window.location='data_balita.php';
          </script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Data Balita</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-primary text-white">
    <h4 class="mb-0">Detail Data Balita</h4>
</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
    <th width="30%">ID Balita</th>
    <td><?= $data['id_balita']; ?></td>
</tr>

<tr>
    <th>ID User</th>
    <td><?= $data['id_user']; ?></td>
</tr>

<tr>
    <th>NIK Balita</th>
    <td><?= $data['nik_balita']; ?></td>
</tr>

<tr>
    <th>Nama Balita</th>
    <td><?= $data['nama_balita']; ?></td>
</tr>

<tr>
    <th>Jenis Kelamin</th>
    <td><?= $data['jenis_kelamin']; ?></td>
</tr>

<tr>
    <th>Tanggal Lahir</th>
    <td><?= date('d-m-Y', strtotime($data['tanggal_lahir'])); ?></td>
</tr>

<tr>
    <th>Umur</th>
    <td><?= $data['umur']; ?> Bulan</td>
</tr>

<tr>
    <th>Nama Ibu</th>
    <td><?= $data['nama_ibu']; ?></td>
</tr>

<tr>
    <th>Alamat</th>
    <td><?= $data['alamat']; ?></td>
</tr>

<tr>
    <th>Wilayah Posyandu</th>
    <td><?= $data['wilayah_posyandu']; ?></td>
</tr>

</table>

<div class="mt-3">

<a href="edit_balita.php?id=<?= $data['id_balita']; ?>" class="btn btn-warning">
    Edit
</a>

<a href="data_balita.php" class="btn btn-secondary">
    Kembali
</a>

</div>

</div>

</div>

</div>

</body>
</html>