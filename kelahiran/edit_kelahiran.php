<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";


cekRole([
    "kader",
    "petugas_kia"
]);


$judulHalaman = "Edit Riwayat Kelahiran | Sistem Deteksi Stunting";


// cek id

if(!isset($_GET['id']) || empty($_GET['id'])){

    echo "
    <script>
        alert('ID Riwayat Kelahiran tidak ditemukan!');
        window.location='riwayat_kelahiran.php';
    </script>";

    exit;

}


$id = (int) $_GET['id'];


// ambil data

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM riwayat_kelahiran
     WHERE id_kelahiran = ?"
);


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);


$data = mysqli_fetch_assoc($result);



if(!$data){

    echo "
    <script>
        alert('Data tidak ditemukan!');
        window.location='riwayat_kelahiran.php';
    </script>";

    exit;

}



// proses update

if(isset($_POST['update'])){


    $id_balita = $_POST['id_balita'];
    $berat_lahir = $_POST['berat_lahir'];
    $panjang_lahir = $_POST['panjang_lahir'];
    $usia_kehamilan = $_POST['usia_kehamilan'];
    $jenis_persalinan = $_POST['jenis_persalinan'];



    $update = mysqli_prepare(
        $conn,
        "UPDATE riwayat_kelahiran SET

        id_balita=?,
        berat_lahir=?,
        panjang_lahir=?,
        usia_kehamilan=?,
        jenis_persalinan=?

        WHERE id_kelahiran=?"
    );



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



    if(mysqli_stmt_execute($update)){


        echo "
        <script>
            alert('Data berhasil diperbarui');
            window.location='riwayat_kelahiran.php';
        </script>";

        exit;


    }else{


        echo "
        <script>
            alert('Data gagal diperbarui');
        </script>";

    }

}



// ambil data balita

$balita = mysqli_query(
    $conn,
    "SELECT *
     FROM balita
     ORDER BY nama_balita ASC"
);



require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>



<div class="layout-wrapper">


<?php require_once "../includes/sidebar.php"; ?>


<main class="main-content">



<div class="card content-card">



<div class="card-header">


<div>


<h4 class="mb-1">
Edit Riwayat Kelahiran
</h4>


<small class="text-muted">
Perbarui data riwayat kelahiran balita.
</small>


</div>



<a
href="riwayat_kelahiran.php"
class="btn btn-secondary btn-sm">


<i class="bi bi-arrow-left"></i>


Kembali


</a>



</div>





<div class="card-body">



<form method="POST">



<div class="row g-3">





<div class="col-12">


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
<?= ($b['id_balita']==$data['id_balita']) ? "selected" : ""; ?>
>


<?= $b['nama_balita']; ?>
-
<?= $b['nik_balita']; ?>


</option>


<?php } ?>


</select>


<div class="form-text">
Pilih balita yang riwayat kelahirannya akan diperbarui.
</div>


</div>





<div class="col-12 col-md-6">


<label class="form-label">
Berat Lahir
</label>


<div class="input-group">


<input

type="number"

step="0.01"

name="berat_lahir"

class="form-control"

value="<?= $data['berat_lahir']; ?>"

required>


<span class="input-group-text">
kg
</span>


</div>


</div>





<div class="col-12 col-md-6">


<label class="form-label">
Panjang Lahir
</label>


<div class="input-group">


<input

type="number"

step="0.01"

name="panjang_lahir"

class="form-control"

value="<?= $data['panjang_lahir']; ?>"

required>


<span class="input-group-text">
cm
</span>


</div>


</div>





<div class="col-12 col-md-6">


<label class="form-label">
Usia Kehamilan
</label>


<div class="input-group">


<input

type="number"

name="usia_kehamilan"

class="form-control"

value="<?= $data['usia_kehamilan']; ?>"

required>


<span class="input-group-text">
minggu
</span>


</div>


</div>





<div class="col-12 col-md-6">


<label class="form-label">
Jenis Persalinan
</label>


<select

name="jenis_persalinan"

class="form-select"

required>


<option value="Normal"
<?= $data['jenis_persalinan']=="Normal" ? "selected":""; ?>>

Normal

</option>



<option value="Caesar"
<?= $data['jenis_persalinan']=="Caesar" ? "selected":""; ?>>

Caesar

</option>



<option value="Vakum"
<?= $data['jenis_persalinan']=="Vakum" ? "selected":""; ?>>

Vakum

</option>



<option value="Forceps"
<?= $data['jenis_persalinan']=="Forceps" ? "selected":""; ?>>

Forceps

</option>


</select>


</div>





</div>





<hr>





<div class="form-actions">


<button

type="submit"

name="update"

class="btn btn-primary">


<i class="bi bi-check-circle"></i>


Simpan Perubahan


</button>



<a

href="riwayat_kelahiran.php"

class="btn btn-outline-secondary">


<i class="bi bi-x-circle"></i>


Batal


</a>



</div>



</form>


</div>


</div>



</main>


</div>



<?php require_once "../includes/footer.php"; ?>