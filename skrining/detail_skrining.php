<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";


cekRole([
    "petugas_gizi",
    "petugas_kia",
    "kepala_puskesmas",
    "dinkes"
]);


$id = (int)($_GET["id"] ?? 0);


$query = mysqli_query(
$conn,

"SELECT

s.*,

b.nama_balita,
b.nik_balita,
b.jenis_kelamin,

pa.umur_bulan,
pa.berat_badan,
pa.tinggi_panjang_badan

FROM skrining s

INNER JOIN balita b
ON s.id_balita=b.id_balita

LEFT JOIN pengukuran_antropometri pa
ON s.id_pengukuran=pa.id_pengukuran

WHERE s.id_skrining=$id"

);


$data=mysqli_fetch_assoc($query);


?>


<?php require "../includes/header.php"; ?>


<div class="container mt-4">


<div class="card">


<div class="card-header">

<h4>
Detail Skrining Balita
</h4>

</div>


<div class="card-body">


<table class="table">


<tr>
<td>Nama Balita</td>
<td>
<?= htmlspecialchars($data["nama_balita"]); ?>
</td>
</tr>


<tr>
<td>NIK</td>
<td>
<?= htmlspecialchars($data["nik_balita"]); ?>
</td>
</tr>


<tr>
<td>Umur</td>
<td>
<?= $data["umur_bulan"]; ?> bulan
</td>
</tr>


<tr>
<td>Berat Badan</td>
<td>
<?= $data["berat_badan"]; ?> kg
</td>
</tr>


<tr>
<td>Tinggi/Panjang Badan</td>
<td>
<?= $data["tinggi_panjang_badan"]; ?> cm
</td>
</tr>


<tr>
<td>Status Skrining</td>
<td>

<span class="badge bg-warning">

<?= htmlspecialchars(
$data["status_skrining"]
); ?>

</span>

</td>
</tr>


<tr>
<td>Status Verifikasi</td>

<td>

<?= htmlspecialchars(
$data["status_verifikasi"]
?? "Belum diverifikasi"
); ?>


</td>
</tr>


<tr>
<td>Catatan Verifikasi</td>

<td>

<?= htmlspecialchars(
$data["catatan_verifikasi"]
?? "-"
); ?>

</td>
</tr>


</table>



<?php if(
$_SESSION["role"]=="petugas_gizi"
): ?>


<a href="verifikasi_skrining.php?id=
<?= $id; ?>"
class="btn btn-success">

Verifikasi Skrining

</a>


<?php endif; ?>


<a href="hasil_skrining.php"
class="btn btn-secondary">

Kembali

</a>


</div>


</div>


</div>


<?php require "../includes/footer.php"; ?>