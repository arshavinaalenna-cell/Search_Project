<?php
include '../koneksi.php';

if(isset($_POST['simpan'])){

    $id_balita = $_POST['id_balita'];
    $berat_lahir = $_POST['berat_lahir'];
    $panjang_lahir = $_POST['panjang_lahir'];
    $usia_kehamilan = $_POST['usia_kehamilan'];
    $jenis_persalinan = $_POST['jenis_persalinan'];

    $query = mysqli_query($conn,"INSERT INTO riwayat_kelahiran
    (
        id_balita,
        berat_lahir,
        panjang_lahir,
        usia_kehamilan,
        jenis_persalinan
    )
    VALUES
    (
        '$id_balita',
        '$berat_lahir',
        '$panjang_lahir',
        '$usia_kehamilan',
        '$jenis_persalinan'
    )");

    if($query){

        echo "<script>
        alert('Data berhasil disimpan');
        window.location='data_riwayat_kelahiran.php';
        </script>";

    }else{

        echo "<script>
        alert('Data gagal disimpan');
        </script>";

    }

}
?>

<!DOCTYPE html>
<html>

<head>

<title>Tambah Riwayat Kelahiran</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<div class="card">

<div class="card-header bg-success text-white">

<h4>Tambah Riwayat Kelahiran</h4>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label>ID Balita</label>

<input
type="number"
name="id_balita"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Berat Lahir (kg)</label>

<input
type="number"
step="0.01"
name="berat_lahir"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Panjang Lahir (cm)</label>

<input
type="number"
step="0.01"
name="panjang_lahir"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Usia Kehamilan (Minggu)</label>

<input
type="number"
name="usia_kehamilan"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Jenis Persalinan</label>

<select
name="jenis_persalinan"
class="form-select"
required>

<option value="">-- Pilih --</option>

<option value="Normal">Normal</option>

<option value="Caesar">Caesar</option>

<option value="Vakum">Vakum</option>

<option value="Forceps">Forceps</option>

</select>

</div>

<button
type="submit"
name="simpan"
class="btn btn-success">

Simpan

</button>

<a href="data_riwayat_kelahiran.php" class="btn btn-secondary">

Kembali

</a>

</form>

</div>

</div>

</div>

</body>
</html>