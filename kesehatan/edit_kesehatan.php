<?php
include '../koneksi.php';

// Ambil ID dari URL
$id = $_GET['id'];

// Ambil data berdasarkan ID
$query = mysqli_query($conn, "SELECT * FROM riwayat_kesehatan WHERE id_riwayat='$id'");
$data = mysqli_fetch_assoc($query);

// Update data
if(isset($_POST['update'])){

    $id_balita = $_POST['id_balita'];
    $riwayat_penyakit = $_POST['riwayat_penyakit'];
    $riwayat_imunisasi = $_POST['riwayat_imunisasi'];
    $riwayat_perawatan = $_POST['riwayat_perawatan'];

    $update = mysqli_query($conn,"UPDATE riwayat_kesehatan SET

        id_balita='$id_balita',
        riwayat_penyakit='$riwayat_penyakit',
        riwayat_imunisasi='$riwayat_imunisasi',
        riwayat_perawatan='$riwayat_perawatan'

        WHERE id_riwayat='$id'
    ");

    if($update){

        echo "<script>
            alert('Data berhasil diperbarui');
            window.location='riwayat_kesehatan.php';
        </script>";

    }else{

        echo "<script>
            alert('Data gagal diperbarui');
        </script>";

    }

}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Edit Riwayat Kesehatan</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-warning text-dark">

<h4>Edit Riwayat Kesehatan Anak</h4>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label class="form-label">Nama Balita</label>

<select name="id_balita" class="form-select" required>

<?php

$balita = mysqli_query($conn,"SELECT * FROM balita ORDER BY nama_balita ASC");

while($b = mysqli_fetch_array($balita)){

    $selected = ($b['id_balita']==$data['id_balita']) ? "selected" : "";

?>

<option value="<?= $b['id_balita']; ?>" <?= $selected; ?>>

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
required><?= $data['riwayat_penyakit']; ?></textarea>

</div>

<div class="mb-3">

<label class="form-label">Riwayat Imunisasi</label>

<textarea
name="riwayat_imunisasi"
class="form-control"
rows="3"
required><?= $data['riwayat_imunisasi']; ?></textarea>

</div>

<div class="mb-3">

<label class="form-label">Riwayat Perawatan</label>

<textarea
name="riwayat_perawatan"
class="form-control"
rows="3"
required><?= $data['riwayat_perawatan']; ?></textarea>

</div>

<button
type="submit"
name="update"
class="btn btn-warning">

Update

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