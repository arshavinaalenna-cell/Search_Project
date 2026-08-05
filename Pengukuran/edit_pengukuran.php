<?php
session_start();
require_once "../config/koneksi.php";

// Cek ID
if (!isset($_GET['id'])) {
    header("Location: data_pengukuran.php");
    exit;
}

$id = $_GET['id'];

// Ambil data pengukuran
$query = mysqli_query($conn,"
SELECT *
FROM pengukuran_antropometri
WHERE id_pengukuran='$id'
");

$data = mysqli_fetch_assoc($query);

if (!$data) {
    echo "<script>
            alert('Data tidak ditemukan');
            window.location='data_pengukuran.php';
          </script>";
    exit;
}

// Ambil data balita
$balita = mysqli_query($conn,"
SELECT *
FROM balita
ORDER BY nama_balita ASC
");

// Proses Update
if(isset($_POST['update'])){

    $id_balita = $_POST['id_balita'];
    $tanggal = $_POST['tanggal_pengukuran'];
    $umur = $_POST['umur_bulan'];
    $bb = $_POST['berat_badan'];
    $tb = $_POST['tinggi_panjang_badan'];
    $lk = $_POST['lingkar_kepala'];
    $lila = $_POST['lila'];

    $update = mysqli_query($conn,"
    UPDATE pengukuran_antropometri
    SET
        id_balita='$id_balita',
        tanggal_pengukuran='$tanggal',
        umur_bulan='$umur',
        berat_badan='$bb',
        tinggi_panjang_badan='$tb',
        lingkar_kepala='$lk',
        lila='$lila'
    WHERE id_pengukuran='$id'
    ");

    if($update){

        echo "<script>
                alert('Data berhasil diperbarui');
                window.location='data_pengukuran.php';
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Pengukuran</title>

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

<div class="card-header bg-warning">

<h4>Edit Data Pengukuran</h4>

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

<?php while($b=mysqli_fetch_assoc($balita)){ ?>

<option
value="<?= $b['id_balita']; ?>"
<?= ($b['id_balita']==$data['id_balita']) ? "selected" : ""; ?>>

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
value="<?= $data['tanggal_pengukuran']; ?>"
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
value="<?= $data['umur_bulan']; ?>"
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
value="<?= $data['berat_badan']; ?>"
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
value="<?= $data['tinggi_panjang_badan']; ?>"
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
class="form-control"
value="<?= $data['lingkar_kepala']; ?>">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

LiLA (cm)

</label>

<input
type="number"
step="0.01"
name="lila"
class="form-control"
value="<?= $data['lila']; ?>">

</div>

</div>

<div class="mt-4">

<button
type="submit"
name="update"
class="btn btn-warning">

Update

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