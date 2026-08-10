<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";


cekRole([
"petugas_gizi"
]);


$id =
(int)($_GET["id"] ?? 0);



if(
$_SERVER["REQUEST_METHOD"]=="POST"
){


$status =
$_POST["status_verifikasi"];


$catatan =
$_POST["catatan_verifikasi"];



$stmt=mysqli_prepare(
$conn,

"UPDATE skrining SET

status_verifikasi=?,

catatan_verifikasi=?,

diverifikasi_oleh=?,

tanggal_verifikasi=NOW()

WHERE id_skrining=?"


);



mysqli_stmt_bind_param(

$stmt,

"ssii",

$status,

$catatan,

$_SESSION["id_user"],

$id

);



mysqli_stmt_execute($stmt);



header(
"Location:
detail_skrining.php?id=$id"
);


exit;


}


?>


<?php require "../includes/header.php"; ?>


<div class="container mt-4">


<div class="card">


<div class="card-header">

<h4>
Verifikasi Skrining
</h4>

</div>


<div class="card-body">


<form method="POST">


<label>
Status Verifikasi
</label>


<select
name="status_verifikasi"
class="form-control mb-3"
>


<option>
Belum diverifikasi
</option>


<option>
Sudah diverifikasi
</option>


<option>
Perlu pemeriksaan ulang
</option>


</select>



<label>
Catatan Petugas Gizi
</label>


<textarea
name="catatan_verifikasi"
class="form-control mb-3"
rows="5"
placeholder="
Masukkan hasil analisis skrining...
"></textarea>



<button
class="btn btn-success"
>

Simpan Verifikasi

</button>



<a href="hasil_skrining.php"
class="btn btn-secondary">

Kembali

</a>


</form>


</div>


</div>


</div>


<?php require "../includes/footer.php"; ?>