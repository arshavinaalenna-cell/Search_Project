<?php
require_once '../config/koneksi.php';

// Cek apakah ID ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>
            alert('ID Riwayat Kelahiran tidak ditemukan!');
            window.location='riwayat_kelahiran.php';
          </script>";
    exit;
}

$id = (int)$_GET['id'];

// Ambil data riwayat kelahiran
$stmt = mysqli_prepare($conn, "SELECT * FROM riwayat_kelahiran WHERE id_kelahiran = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<script>
            alert('Data tidak ditemukan!');
            window.location='riwayat_kelahiran.php';
          </script>";
    exit;
}

// Simpan perubahan
if (isset($_POST['update'])) {

    $id_balita        = $_POST['id_balita'];
    $berat_lahir      = $_POST['berat_lahir'];
    $panjang_lahir    = $_POST['panjang_lahir'];
    $usia_kehamilan   = $_POST['usia_kehamilan'];
    $jenis_persalinan = $_POST['jenis_persalinan'];

    $update = mysqli_prepare($conn, "UPDATE riwayat_kelahiran SET
        id_balita=?,
        berat_lahir=?,
        panjang_lahir=?,
        usia_kehamilan=?,
        jenis_persalinan=?
        WHERE id_kelahiran=?");

    mysqli_stmt_bind_param(
        $update,
        "iddisi",
        $id_balita,
        $berat_lahir,
        $panjang_lahir,
        $usia_kehamilan,
        $jenis_persalinan,
        $id
    );

    if (mysqli_stmt_execute($update)) {

        echo "<script>
                alert('Data berhasil diperbarui');
                window.location='riwayat_kelahiran.php';
              </script>";

    } else {

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

<title>Edit Riwayat Kelahiran</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-warning text-dark">

<h4>Edit Riwayat Kelahiran</h4>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label class="form-label">Nama Balita</label>

<select name="id_balita" class="form-select" required>

<?php

$balita = mysqli_query($conn,"SELECT * FROM balita ORDER BY nama_balita ASC");

while($b = mysqli_fetch_assoc($balita)){

?>

<option value="<?= $b['id_balita']; ?>"
<?= ($b['id_balita']==$data['id_balita']) ? "selected" : ""; ?>>

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
value="<?= $data['berat_lahir']; ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Panjang Lahir (cm)</label>

<input
type="number"
step="0.01"
name="panjang_lahir"
class="form-control"
value="<?= $data['panjang_lahir']; ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Usia Kehamilan (Minggu)</label>

<input
type="number"
name="usia_kehamilan"
class="form-control"
value="<?= $data['usia_kehamilan']; ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Jenis Persalinan</label>

<select name="jenis_persalinan" class="form-select" required>

<option value="Normal" <?= ($data['jenis_persalinan']=="Normal") ? "selected" : ""; ?>>
Normal
</option>

<option value="Caesar" <?= ($data['jenis_persalinan']=="Caesar") ? "selected" : ""; ?>>
Caesar
</option>

<option value="Vakum" <?= ($data['jenis_persalinan']=="Vakum") ? "selected" : ""; ?>>
Vakum
</option>

<option value="Forceps" <?= ($data['jenis_persalinan']=="Forceps") ? "selected" : ""; ?>>
Forceps
</option>

</select>

</div>

<button type="submit" name="update" class="btn btn-warning">
Update
</button>

<a href="riwayat_kelahiran.php" class="btn btn-secondary">
Kembali
</a>

</form>

</div>

</div>

</div>

</body>
</html>