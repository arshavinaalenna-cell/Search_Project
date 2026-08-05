<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

if(isset($_POST['simpan'])){

    $id_user = $_POST['id_user'];
    $nik_balita = $_POST['nik_balita'];
    $nama_balita = $_POST['nama_balita'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $nama_ibu = $_POST['nama_ibu'];
    $alamat = $_POST['alamat'];
    $wilayah_posyandu = $_POST['wilayah_posyandu'];

    // Menghitung umur dalam bulan
    $lahir = new DateTime($tanggal_lahir);
    $today = new DateTime();
    $selisih = $today->diff($lahir);
    $umur = ($selisih->y * 12) + $selisih->m;

    $sql = mysqli_query($conn,"INSERT INTO balita
    (
        id_user,
        nik_balita,
        nama_balita,
        jenis_kelamin,
        tanggal_lahir,
        umur,
        nama_ibu,
        alamat,
        wilayah_posyandu
    )
    VALUES
    (
        '$id_user',
        '$nik_balita',
        '$nama_balita',
        '$jenis_kelamin',
        '$tanggal_lahir',
        '$umur',
        '$nama_ibu',
        '$alamat',
        '$wilayah_posyandu'
    )");

    if($sql){
        echo "<script>
        alert('Data berhasil ditambahkan');
        window.location='data_balita.php';
        </script>";
    }else{
        echo "<script>
        alert('Data gagal ditambahkan');
        </script>";
    }

}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Tambah Data Balita</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body>

<div class="container mt-5">

<div class="card">

<div class="card-header bg-success text-white">
<h4>Tambah Data Balita</h4>
</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">
<label>ID User</label>
<input type="number" name="id_user" class="form-control" required>
</div>

<div class="mb-3">
<label>NIK Balita</label>
<input type="text" name="nik_balita" class="form-control" maxlength="16" required>
</div>

<div class="mb-3">
<label>Nama Balita</label>
<input type="text" name="nama_balita" class="form-control" required>
</div>

<div class="mb-3">
<label>Jenis Kelamin</label>

<select name="jenis_kelamin" class="form-select" required>

<option value="">--Pilih--</option>

<option value="Laki-laki">
Laki-laki
</option>

<option value="Perempuan">
Perempuan
</option>

</select>

</div>

<div class="mb-3">
<label>Tanggal Lahir</label>
<input type="date" name="tanggal_lahir" class="form-control" required>
</div>

<div class="mb-3">
<label>Nama Ibu</label>
<input type="text" name="nama_ibu" class="form-control" required>
</div>

<div class="mb-3">
<label>Alamat</label>
<textarea name="alamat" class="form-control" rows="3" required></textarea>
</div>

<div class="mb-3">
<label>Wilayah Posyandu</label>
<input type="text" name="wilayah_posyandu" class="form-control" required>
</div>

<button type="submit" name="simpan" class="btn btn-success">
Simpan
</button>

<a href="data_balita.php" class="btn btn-secondary">
Kembali
</a>

</form>

</div>

</div>

</div>

</body>
</html>