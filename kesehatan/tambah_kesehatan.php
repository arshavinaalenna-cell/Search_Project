<?php
include '../koneksi.php';

if(isset($_POST['simpan'])){

    $id_balita           = $_POST['id_balita'];
    $riwayat_penyakit    = $_POST['riwayat_penyakit'];
    $riwayat_imunisasi   = $_POST['riwayat_imunisasi'];
    $riwayat_perawatan   = $_POST['riwayat_perawatan'];

    $query = mysqli_query($conn,"INSERT INTO riwayat_kesehatan
    (
        id_balita,
        riwayat_penyakit,
        riwayat_imunisasi,
        riwayat_perawatan
    )
    VALUES
    (
        '$id_balita',
        '$riwayat_penyakit',
        '$riwayat_imunisasi',
        '$riwayat_perawatan'
    )");

    if($query){

        echo "<script>
            alert('Data riwayat kesehatan berhasil ditambahkan');
            window.location='riwayat_kesehatan.php';
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

<title>Tambah Riwayat Kesehatan</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h4>Tambah Riwayat Kesehatan Anak</h4>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label class="form-label">Nama Balita</label>

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

<label class="form-label">Riwayat Penyakit</label>

<textarea
name="riwayat_penyakit"
class="form-control"
rows="3"
required></textarea>

</div>

<div class="mb-3">

<label class="form-label">Riwayat Imunisasi</label>

<textarea
name="riwayat_imunisasi"
class="form-control"
rows="3"
required></textarea>

</div>

<div class="mb-3">

<label class="form-label">Riwayat Perawatan</label>

<textarea
name="riwayat_perawatan"
class="form-control"
rows="3"
required></textarea>

</div>

<button
type="submit"
name="simpan"
class="btn btn-success">

Simpan

</button>

<a
href="riwayat_kesehatan.php"
class="btn btn-secondary">

Kembali

</a>

</form>

</div>

</div>

</div>

</body>
</html>