<?php
include '../koneksi.php';

// Ambil ID dari URL
$id = $_GET['id'];

// Ambil data balita
$query = mysqli_query($conn, "SELECT * FROM balita WHERE id_balita='$id'");
$data = mysqli_fetch_assoc($query);

// Jika tombol update ditekan
if(isset($_POST['update'])){

    $id_user            = $_POST['id_user'];
    $nik_balita         = $_POST['nik_balita'];
    $nama_balita        = $_POST['nama_balita'];
    $jenis_kelamin      = $_POST['jenis_kelamin'];
    $tanggal_lahir      = $_POST['tanggal_lahir'];
    $nama_ibu           = $_POST['nama_ibu'];
    $alamat             = $_POST['alamat'];
    $wilayah_posyandu   = $_POST['wilayah_posyandu'];

    // Hitung umur dalam bulan
    $lahir = new DateTime($tanggal_lahir);
    $sekarang = new DateTime();
    $selisih = $lahir->diff($sekarang);

    $umur = ($selisih->y * 12) + $selisih->m;

    $update = mysqli_query($conn,"UPDATE balita SET

        id_user='$id_user',
        nik_balita='$nik_balita',
        nama_balita='$nama_balita',
        jenis_kelamin='$jenis_kelamin',
        tanggal_lahir='$tanggal_lahir',
        umur='$umur',
        nama_ibu='$nama_ibu',
        alamat='$alamat',
        wilayah_posyandu='$wilayah_posyandu'

        WHERE id_balita='$id'
    ");

    if($update){

        echo "<script>
        alert('Data berhasil diubah');
        window.location='data_balita.php';
        </script>";

    }else{

        echo "<script>
        alert('Data gagal diubah');
        </script>";

    }

}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Edit Data Balita</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<div class="card">

<div class="card-header bg-warning text-dark">

<h4>Edit Data Balita</h4>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label class="form-label">ID User</label>

<input
type="number"
name="id_user"
class="form-control"
value="<?= $data['id_user']; ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">NIK Balita</label>

<input
type="text"
name="nik_balita"
class="form-control"
value="<?= $data['nik_balita']; ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Nama Balita</label>

<input
type="text"
name="nama_balita"
class="form-control"
value="<?= $data['nama_balita']; ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Jenis Kelamin</label>

<select name="jenis_kelamin" class="form-select">

<option value="Laki-laki"
<?= ($data['jenis_kelamin']=="Laki-laki") ? "selected" : ""; ?>>
Laki-laki
</option>

<option value="Perempuan"
<?= ($data['jenis_kelamin']=="Perempuan") ? "selected" : ""; ?>>
Perempuan
</option>

</select>

</div>

<div class="mb-3">

<label class="form-label">Tanggal Lahir</label>

<input
type="date"
name="tanggal_lahir"
class="form-control"
value="<?= $data['tanggal_lahir']; ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Nama Ibu</label>

<input
type="text"
name="nama_ibu"
class="form-control"
value="<?= $data['nama_ibu']; ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Alamat</label>

<textarea
name="alamat"
class="form-control"
rows="3"
required><?= $data['alamat']; ?></textarea>

</div>

<div class="mb-3">

<label class="form-label">Wilayah Posyandu</label>

<input
type="text"
name="wilayah_posyandu"
class="form-control"
value="<?= $data['wilayah_posyandu']; ?>"
required>

</div>

<button
type="submit"
name="update"
class="btn btn-warning">

Update

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