<?php
require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_gizi"]);

$id=(int)($_GET["id"] ?? 0);

if($_SERVER["REQUEST_METHOD"]=="POST"){

$status=$_POST["status_verifikasi"];
$catatan=$_POST["catatan_verifikasi"];

$stmt=mysqli_prepare($conn,"
UPDATE hasil_deteksi SET
status_verifikasi=?,
catatan_verifikasi=?,
diverifikasi_oleh=?,
tanggal_verifikasi=NOW()
WHERE id_deteksi=?
");

mysqli_stmt_bind_param(
$stmt,
"ssii",
$status,
$catatan,
$_SESSION["id_user"],
$id
);

mysqli_stmt_execute($stmt);

header("Location: hasil_deteksi.php?pesan=analisis_berhasil");
exit;

}

require_once "../includes/header.php";
?>

<div class="container mt-4">

<div class="card">

<div class="card-header">
<h4>Verifikasi Hasil Deteksi</h4>
</div>

<div class="card-body">

<form method="POST">

<label>Status Verifikasi</label>

<select name="status_verifikasi" class="form-control mb-3">

<option>Belum diverifikasi</option>
<option>Sudah diverifikasi</option>
<option>Perlu pemeriksaan ulang</option>

</select>


<label>Catatan Petugas Gizi</label>

<textarea name="catatan_verifikasi"
class="form-control mb-3"></textarea>


<button class="btn btn-success">
Simpan
</button>

</form>

</div>

</div>

</div>

<?php require_once "../includes/footer.php"; ?>
