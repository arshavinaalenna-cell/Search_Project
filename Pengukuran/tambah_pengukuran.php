<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

// Proses Simpan Data
if (isset($_POST['simpan'])) {

    $id_balita = $_POST['id_balita'];
    $tanggal = $_POST['tanggal_pengukuran'];
    $umur = $_POST['umur_bulan'];
    $bb = $_POST['berat_badan'];
    $tb = $_POST['tinggi_panjang_badan'];
    $lk = $_POST['lingkar_kepala'];
    $lila = $_POST['lila'];

    $simpan = mysqli_query($conn, "
        INSERT INTO pengukuran
        (
            id_balita,
            tanggal_pengukuran,
            umur_bulan,
            berat_badan,
            tinggi_panjang_badan,
            lingkar_kepala,
            lila
        )
        VALUES
        (
            '$id_balita',
            '$tanggal',
            '$umur',
            '$bb',
            '$tb',
            '$lk',
            '$lila'
        )
    ");

    if ($simpan) {
        echo "<script>
                alert('Data pengukuran berhasil disimpan');
                window.location='data_pengukuran.php';
              </script>";
    } else {
        echo "<script>
                alert('Gagal menyimpan data');
              </script>";
    }
}

// Ambil data balita
$balita = mysqli_query($conn, "SELECT * FROM balita ORDER BY nama_balita ASC");

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Tambah Pengukuran</title>

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

</style>

</head>

<body>

<div class="container mt-4">

<div class="card">

<div class="card-header bg-success text-white">

<h4>Tambah Data Pengukuran</h4>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label class="form-label">
Nama Balita
</label>

<select
name="id_balita"
class="form-select"
required>

<option value="">-- Pilih Balita --</option>

<?php while($b=mysqli_fetch_assoc($balita)){ ?>

<option value="<?= $b['id_balita']; ?>">

<?= $b['nama_balita']; ?>

</option>

<?php } ?>

</select>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Tanggal Pengukuran

</label>

<input
type="date"
name="tanggal_pengukuran"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Umur (Bulan)

</label>

<input
type="number"
name="umur_bulan"
class="form-control"
required>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Berat Badan (kg)

</label>

<input
type="number"
step="0.01"
name="berat_badan"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Tinggi / Panjang Badan (cm)

</label>

<input
type="number"
step="0.01"
name="tinggi_panjang_badan"
class="form-control"
required>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Lingkar Kepala (cm)

</label>

<input
type="number"
step="0.01"
name="lingkar_kepala"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

LiLA (cm)

</label>

<input
type="number"
step="0.01"
name="lila"
class="form-control">

</div>

</div>

<div class="mt-4">

<button
type="submit"
name="simpan"
class="btn btn-success">

Simpan

</button>

<a
href="data_pengukuran.php"
class="btn btn-secondary">

Kembali

</a>

</div>

</form>

</div>

</div>

</div>

</body>

</html>