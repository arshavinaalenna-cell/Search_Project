<?php

require_once "../auth/session.php";
require_once "../config/koneksi.php";

$cari = "";

if(isset($_GET['cari'])){

    $cari = mysqli_real_escape_string($conn,$_GET['cari']);

    $query = mysqli_query($conn,"
        SELECT * FROM balita
        WHERE
            nik_balita LIKE '%$cari%' OR
            nama_balita LIKE '%$cari%' OR
            nama_ibu LIKE '%$cari%' OR
            wilayah_posyandu LIKE '%$cari%'
        ORDER BY id_balita DESC
    ");

}else{

    $query = mysqli_query($conn,"SELECT * FROM balita ORDER BY id_balita DESC");

}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<title>Data Balita</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<h2 class="mb-4">Data Balita</h2>

<form method="GET" class="row mb-3">

<div class="col-md-6">

<input
type="text"
name="cari"
class="form-control"
placeholder="Cari Nama / NIK / Ibu"
value="<?= $cari ?>">

</div>

<div class="col-md-2">

<button class="btn btn-primary">
Cari
</button>

</div>

<div class="col-md-4 text-end">

<a href="tambah_balita.php" class="btn btn-success">
+ Tambah Balita
</a>

</div>

</form>

<table class="table table-bordered table-striped">

<thead class="table-dark">

<tr>

<th>No</th>
<th>NIK</th>
<th>Nama Balita</th>
<th>JK</th>
<th>Tanggal Lahir</th>
<th>Umur</th>
<th>Nama Ibu</th>
<th>Wilayah</th>
<th width="250">Aksi</th>

</tr>

</thead>

<tbody>

<?php

$no=1;

while($d=mysqli_fetch_array($query)){

?>

<tr>

<td><?= $no++; ?></td>

<td><?= $d['nik_balita']; ?></td>

<td><?= $d['nama_balita']; ?></td>

<td><?= $d['jenis_kelamin']; ?></td>

<td><?= date('d-m-Y',strtotime($d['tanggal_lahir'])); ?></td>

<td><?= $d['umur']; ?> Bulan</td>

<td><?= $d['nama_ibu']; ?></td>

<td><?= $d['wilayah_posyandu']; ?></td>

<td>

<a
href="detail_balita.php?id=<?= $d['id_balita']; ?>"
class="btn btn-info btn-sm">

Detail

</a>

<a
href="edit_balita.php?id=<?= $d['id_balita']; ?>"
class="btn btn-warning btn-sm">

Edit

</a>

<form
    action="hapus_balita.php"
    method="POST"
    class="d-inline form-hapus-balita"
    data-nama="<?= htmlspecialchars(
        $data["nama_balita"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
>
    <input
        type="hidden"
        name="id_balita"
        value="<?= (int) $data["id_balita"] ?>"
    >

    <button
        type="submit"
        class="btn btn-danger btn-sm"
    >
        Hapus
    </button>
</form>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</body>

</html>