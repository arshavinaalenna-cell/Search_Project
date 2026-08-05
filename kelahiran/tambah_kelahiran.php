<?php
include '../koneksi.php';

if(isset($_POST['simpan'])){

    $id_balita         = $_POST['id_balita'];
    $berat_lahir       = $_POST['berat_lahir'];
    $panjang_lahir     = $_POST['panjang_lahir'];
    $usia_kehamilan    = $_POST['usia_kehamilan'];
    $jenis_persalinan  = $_POST['jenis_persalinan'];

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
        alert('Data riwayat kelahiran berhasil ditambahkan');
        window.location='data_kelahiran.php';
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
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Tambah Riwayat Kelahiran</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h4>Tambah Riwayat Kelahiran</h4>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label class="form-label">ID Balita</label>

<select name="id_balita" class="form-select" required>

<option value="">-- Pilih Balita --</option>

<?php

$balita = mysqli_query($conn,"SELECT * FROM balita ORDER BY nama_balita ASC");

while($b = mysqli_fetch_array($balita)){

?>

<option value="<?= $b['id_balita']; ?>">

<?= $b['nama_balita']; ?> - <?= $b['nik_balita']; ?>

</option>

<?php } ?>

</select>

</div>

<div class="mb-3">

<label class="form-label">Berat Lahir (Kg)</label>

<input
type="number"
step="0.01"
name="berat_lahir"
class="form-control"
placeholder="Contoh: 3.20"
required>

</div>

<div class="mb-3">

<label class="form-label">Panjang Lahir (cm)</label>

<input
type="number"
step="0.01"
name="panjang_lahir"
class="form-control"
placeholder="Contoh: 49.5"
required>

</div>

<div class="mb-3">

<label class="form-label">Usia Kehamilan (Minggu)</label>

<input
type="number"
name="usia_kehamilan"
class="form-control"
placeholder="Contoh: 39"
required>

</div>

<div class="mb-3">

<label class="form-label">Jenis Persalinan</label>

<select
name="jenis_persalinan"
class="form-select"
required>

<option value="">-- Pilih Jenis Persalinan --</option>

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

<a
href="data_kelahiran.php"
class="btn btn-secondary">

Kembali

</a>

</form>

</div>

</div>

</div>

</body>
</html>