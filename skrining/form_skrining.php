<?php
session_start();
require_once "../config/koneksi.php";

// Simpan Data
if(isset($_POST['simpan'])){

    $id_balita            = $_POST['id_balita'];
    $tinggi_badan_ibu     = $_POST['tinggi_badan_ibu'];
    $pendidikan_ibu       = $_POST['pendidikan_ibu'];
    $pekerjaan_ibu        = $_POST['pekerjaan_ibu'];
    $lama_asi_eksklusif   = $_POST['lama_asi_eksklusif'];
    $mpasi                = $_POST['mpasi'];
    $frekuensi_makan      = $_POST['frekuensi_makan'];
    $protein_hewani       = $_POST['protein_hewani'];
    $status_ekonomi       = $_POST['status_ekonomi'];
    $sanitasi             = $_POST['sanitasi'];
    $air_bersih           = $_POST['air_bersih'];

    $query = mysqli_query($conn,"
        INSERT INTO skrining_awal
        (
            id_balita,
            tinggi_badan_ibu,
            pendidikan_ibu,
            pekerjaan_ibu,
            lama_asi_eksklusif,
            mpasi,
            frekuensi_makan,
            protein_hewani,
            status_ekonomi,
            sanitasi,
            air_bersih
        )
        VALUES
        (
            '$id_balita',
            '$tinggi_badan_ibu',
            '$pendidikan_ibu',
            '$pekerjaan_ibu',
            '$lama_asi_eksklusif',
            '$mpasi',
            '$frekuensi_makan',
            '$protein_hewani',
            '$status_ekonomi',
            '$sanitasi',
            '$air_bersih'
        )
    ");

    if($query){

        echo "<script>
                alert('Data skrining berhasil disimpan');
                window.location='hasil_skrining.php';
              </script>";

    }else{

        echo "<script>
                alert('Data gagal disimpan');
              </script>";

    }

}

// Data Balita
$balita = mysqli_query($conn,"
SELECT *
FROM balita
ORDER BY nama_balita ASC
");

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Form Skrining</title>

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

</style>

</head>

<body>

<div class="container mt-4">

<div class="card">

<div class="card-header bg-success text-white">

<h4>Form Skrining Awal Stunting</h4>

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

<label>Tinggi Badan Ibu (cm)</label>

<input
type="number"
step="0.01"
name="tinggi_badan_ibu"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label>Pendidikan Ibu</label>

<select
name="pendidikan_ibu"
class="form-select"
required>

<option value="">Pilih</option>
<option>SD</option>
<option>SMP</option>
<option>SMA</option>
<option>Diploma</option>
<option>Sarjana</option>

</select>

</div>

</div>

<div class="mb-3">

<div class="mb-3">

<label class="form-label">
Pekerjaan Ibu
</label>

<select
name="pekerjaan_ibu"
class="form-select"
required>

<option value="">-- Pilih Pekerjaan --</option>

<option value="Ibu Rumah Tangga">Ibu Rumah Tangga</option>
<option value="Petani">Petani</option>
<option value="Pedagang">Pedagang</option>
<option value="Karyawan Swasta">Karyawan Swasta</option>
<option value="PNS">PNS</option>
<option value="Wiraswasta">Wiraswasta</option>
<option value="Lainnya">Lainnya</option>

</select>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label>Lama ASI Eksklusif (bulan)</label>

<input
type="number"
name="lama_asi_eksklusif"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label>MPASI</label>

<div>

<input
type="radio"
name="mpasi"
value="Ya"
required> Ya

<input
type="radio"
name="mpasi"
value="Tidak"> Tidak

</div>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label>Frekuensi Makan</label>

<select
name="frekuensi_makan"
class="form-select"
required>

<option value="">Pilih</option>

<option value="Kurang dari 3 kali">Kurang dari 3 kali</option>
<option value="3 kali">3 kali</option>
<option value="Lebih dari 3 kali">Lebih dari 3 kali</option>

</select>

</div>

<div class="col-md-6 mb-3">

<label>Protein Hewani</label>

<div>

<input
type="radio"
name="protein_hewani"
value="Ya"
required> Ya

<input
type="radio"
name="protein_hewani"
value="Tidak"> Tidak

</div>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label>Status Ekonomi</label>

<select
name="status_ekonomi"
class="form-select"
required>

<option value="">Pilih</option>

<option>Rendah</option>
<option>Sedang</option>
<option>Tinggi</option>

</select>

</div>

<div class="col-md-6 mb-3">

<label>Sanitasi</label>

<div>

<input
type="radio"
name="sanitasi"
value="Baik"
required> Baik

<input
type="radio"
name="sanitasi"
value="Kurang"> Kurang

</div>

</div>

</div>

<div class="mb-3">

<label>Air Bersih</label>

<div>

<input
type="radio"
name="air_bersih"
value="Ya"
required> Ya

<input
type="radio"
name="air_bersih"
value="Tidak"> Tidak

</div>

</div>

<button
type="submit"
name="simpan"
class="btn btn-success">

Simpan

</button>

<a
href="hasil_skrining.php"
class="btn btn-secondary">

Kembali

</a>

</form>

</div>

</div>

</div>

</body>

</html>