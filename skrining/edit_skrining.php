<?php
session_start();
require_once "../config/koneksi.php";

if(!isset($_GET['id'])){
    header("Location: hasil_skrining.php");
    exit;
}

$id = (int)$_GET['id'];

// Ambil data skrining
$query = mysqli_query($conn,"
SELECT *
FROM skrining_awal
WHERE id_skrining='$id'
");

$data = mysqli_fetch_assoc($query);

if(!$data){
    echo "<script>
    alert('Data tidak ditemukan');
    window.location='hasil_skrining.php';
    </script>";
    exit;
}

// Data balita
$balita = mysqli_query($conn,"
SELECT *
FROM balita
ORDER BY nama_balita ASC
");

// Update
if(isset($_POST['update'])){

$id_balita=$_POST['id_balita'];
$tinggi_badan_ibu=$_POST['tinggi_badan_ibu'];
$pendidikan_ibu=$_POST['pendidikan_ibu'];
$pekerjaan_ibu=$_POST['pekerjaan_ibu'];
$lama_asi_eksklusif=$_POST['lama_asi_eksklusif'];
$mpasi=$_POST['mpasi'];
$frekuensi_makan=$_POST['frekuensi_makan'];
$protein_hewani=$_POST['protein_hewani'];
$status_ekonomi=$_POST['status_ekonomi'];
$sanitasi=$_POST['sanitasi'];
$air_bersih=$_POST['air_bersih'];

$update=mysqli_query($conn,"
UPDATE skrining_awal SET

id_balita='$id_balita',
tinggi_badan_ibu='$tinggi_badan_ibu',
pendidikan_ibu='$pendidikan_ibu',
pekerjaan_ibu='$pekerjaan_ibu',
lama_asi_eksklusif='$lama_asi_eksklusif',
mpasi='$mpasi',
frekuensi_makan='$frekuensi_makan',
protein_hewani='$protein_hewani',
status_ekonomi='$status_ekonomi',
sanitasi='$sanitasi',
air_bersih='$air_bersih'

WHERE id_skrining='$id'
");

if($update){

echo "<script>
alert('Data berhasil diupdate');
window.location='hasil_skrining.php';
</script>";

}else{

echo "<script>
alert('Data gagal diupdate');
</script>";

}

}
?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Skrining</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body style="background:#f5f6fa;">

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-warning">

<h4>Edit Skrining Awal</h4>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label>Nama Balita</label>

<select
name="id_balita"
class="form-select"
required>

<?php while($b=mysqli_fetch_assoc($balita)){ ?>

<option
value="<?= $b['id_balita']; ?>"
<?= ($b['id_balita']==$data['id_balita']) ? "selected":"";?>>

<?= $b['nama_balita']; ?>

</option>

<?php } ?>

</select>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label>Tinggi Badan Ibu</label>

<input
type="number"
step="0.01"
name="tinggi_badan_ibu"
value="<?= $data['tinggi_badan_ibu'];?>"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label>Pendidikan Ibu</label>

<select
name="pendidikan_ibu"
class="form-select">

<?php
$pendidikan=["SD","SMP","SMA","Diploma","Sarjana"];

foreach($pendidikan as $p){
?>

<option
<?=($data['pendidikan_ibu']==$p)?"selected":"";?>>

<?= $p; ?>

</option>

<?php } ?>

</select>

</div>

</div>

<div class="mb-3">

<label>Pekerjaan Ibu</label>

<select
name="pekerjaan_ibu"
class="form-select">

<?php
$pekerjaan=[
"Ibu Rumah Tangga",
"Petani",
"Pedagang",
"Karyawan Swasta",
"PNS",
"Wiraswasta",
"Lainnya"
];

foreach($pekerjaan as $pk){
?>

<option
<?=($data['pekerjaan_ibu']==$pk)?"selected":"";?>>

<?= $pk;?>

</option>

<?php } ?>

</select>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label>Lama ASI Eksklusif</label>

<input
type="number"
name="lama_asi_eksklusif"
value="<?= $data['lama_asi_eksklusif'];?>"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>MPASI</label>

<select
name="mpasi"
class="form-select">

<option <?=($data['mpasi']=="Ya")?"selected":"";?>>
Ya
</option>

<option <?=($data['mpasi']=="Tidak")?"selected":"";?>>
Tidak
</option>

</select>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label>Frekuensi Makan</label>

<input
type="text"
name="frekuensi_makan"
value="<?= $data['frekuensi_makan'];?>"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Protein Hewani</label>

<select
name="protein_hewani"
class="form-select">

<option <?=($data['protein_hewani']=="Ya")?"selected":"";?>>
Ya
</option>

<option <?=($data['protein_hewani']=="Tidak")?"selected":"";?>>
Tidak
</option>

</select>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label>Status Ekonomi</label>

<input
type="text"
name="status_ekonomi"
value="<?= $data['status_ekonomi'];?>"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Sanitasi</label>

<select
name="sanitasi"
class="form-select">

<option <?=($data['sanitasi']=="Baik")?"selected":"";?>>
Baik
</option>

<option <?=($data['sanitasi']=="Kurang")?"selected":"";?>>
Kurang
</option>

</select>

</div>

</div>

<div class="mb-3">

<label>Air Bersih</label>

<select
name="air_bersih"
class="form-select">

<option <?=($data['air_bersih']=="Ya")?"selected":"";?>>
Ya
</option>

<option <?=($data['air_bersih']=="Tidak")?"selected":"";?>>
Tidak
</option>

</select>

</div>

<button
type="submit"
name="update"
class="btn btn-warning">

Update

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