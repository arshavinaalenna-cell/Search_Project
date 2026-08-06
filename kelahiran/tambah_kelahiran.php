<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "kader",
    "petugas_kia"
]);


if(isset($_POST['simpan'])){

    $id_balita        = $_POST['id_balita'];
    $berat_lahir      = $_POST['berat_lahir'];
    $panjang_lahir    = $_POST['panjang_lahir'];
    $usia_kehamilan   = $_POST['usia_kehamilan'];
    $jenis_persalinan = $_POST['jenis_persalinan'];


    $query = mysqli_query($conn,"
        INSERT INTO riwayat_kelahiran
        (
            id_balita,
            berat_lahir,
            panjang_lahir,
            usia_kehamilan,
            jenis_persalinan
        )
        VALUES
        (
            '$id_balita',
            '$berat_lahir',
            '$panjang_lahir',
            '$usia_kehamilan',
            '$jenis_persalinan'
        )
    ");


    if($query){

        echo "
        <script>
            alert('Data riwayat kelahiran berhasil ditambahkan');
            window.location='riwayat_kelahiran.php';
        </script>";

        exit;

    }else{

        echo "
        <script>
            alert('Data gagal disimpan');
        </script>";

    }

}


$balita = mysqli_query($conn,"
    SELECT *
    FROM balita
    ORDER BY nama_balita ASC
");


require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>


<div class="layout-wrapper">


<?php require_once "../includes/sidebar.php"; ?>


<main class="main-content">


<div class="d-flex flex-column flex-md-row
justify-content-between align-items-md-center
gap-3 mb-4">


<div>

<h2 class="mb-1">
Tambah Riwayat Kelahiran
</h2>


<p class="text-muted mb-0">
Input data riwayat kelahiran balita.
</p>


</div>


<button
type="button"
class="btn btn-successy"
onclick="history.back()">

<i class="bi bi-arrow-left"></i>
Kembali

</button>


</div>



<div class="card content-card">


<div class="card-body p-4">


<form method="POST">



<div class="mb-3">

<label class="form-label">
Balita
</label>


<select
name="id_balita"
class="form-select"
required>


<option value="">
-- Pilih Balita --
</option>


<?php while($b=mysqli_fetch_assoc($balita)){ ?>


<option value="<?= $b['id_balita']; ?>">

<?= $b['nama_balita']; ?>
-
<?= $b['nik_balita']; ?>

</option>


<?php } ?>


</select>


</div>



<div class="mb-3">

<label class="form-label">
Berat Lahir (Kg)
</label>


<input

type="number"

step="0.01"

name="berat_lahir"

class="form-control"

required>

</div>




<div class="mb-3">

<label class="form-label">
Panjang Lahir (cm)
</label>


<input

type="number"

step="0.01"

name="panjang_lahir"

class="form-control"

required>

</div>




<div class="mb-3">

<label class="form-label">
Usia Kehamilan (Minggu)
</label>


<input

type="number"

name="usia_kehamilan"

class="form-control"

required>


</div>




<div class="mb-3">


<label class="form-label">
Jenis Persalinan
</label>


<select
name="jenis_persalinan"
class="form-select"
required>


<option value="">
-- Pilih --
</option>

<option value="Normal">
Normal
</option>


<option value="Caesar">
Caesar
</option>


<option value="Vakum">
Vakum
</option>


<option value="Forceps">
Forceps
</option>


</select>


</div>




<div class="d-flex gap-2">


<button

type="submit"

name="simpan"

class="btn btn-success">

Simpan

</button>



<button

type="button"

class="btn btn-success"

onclick="history.back()">

<i class="bi bi-arrow-left"></i>

Kembali

</button>


</div>


</form>


</div>

</div>


</main>


</div>


<?php require_once "../includes/footer.php"; ?>