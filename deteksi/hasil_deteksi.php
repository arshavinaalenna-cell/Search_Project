<?php
require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_gizi",
    "petugas_kia",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$roleAktif = $_SESSION["role"] ?? "";

$sql = "
SELECT
hd.id_deteksi,
hd.status_gizi,
hd.status_stunting,
hd.status_verifikasi,
hd.tanggal_deteksi,
pa.umur_bulan,
pa.berat_badan,
pa.tinggi_panjang_badan,
pa.zscore_tb_u,
pa.zscore_bb_u,
pa.zscore_bb_tb,
b.nama_balita,
b.nik_balita,
b.jenis_kelamin
FROM hasil_deteksi hd
INNER JOIN pengukuran_antropometri pa
ON hd.id_pengukuran = pa.id_pengukuran
INNER JOIN balita b
ON pa.id_balita = b.id_balita
ORDER BY hd.id_deteksi DESC";

$query = mysqli_query($conn,$sql);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">
<?php require_once "../includes/sidebar.php"; ?>

<main class="main-content">

<div class="card">
<div class="card-header">
<h4>Hasil Deteksi Stunting</h4>
<small>Verifikasi hasil analisis oleh Petugas Gizi</small>
</div>

<div class="card-body">

<table class="table table-hover">

<thead>
<tr>
<th>No</th>
<th>Balita</th>
<th>BB</th>
<th>TB/PB</th>
<th>Z-score TB/U</th>
<th>Status Gizi</th>
<th>Status Stunting</th>
<th>Verifikasi</th>
<th>Aksi</th>
</tr>
</thead>

<tbody>

<?php
$no=1;
while($data=mysqli_fetch_assoc($query)):

$status=$data["status_verifikasi"] ?? "Belum diverifikasi";

$warna="bg-warning text-dark";

if($status=="Sudah diverifikasi"){
    $warna="bg-success";
}
elseif($status=="Perlu pemeriksaan ulang"){
    $warna="bg-danger";
}
?>

<tr>

<td><?= $no++; ?></td>

<td>
<strong><?= htmlspecialchars($data["nama_balita"]); ?></strong><br>
<?= htmlspecialchars($data["nik_balita"]); ?>
</td>

<td><?= $data["berat_badan"]; ?> kg</td>

<td><?= $data["tinggi_panjang_badan"]; ?> cm</td>

<td><?= $data["zscore_tb_u"] ?? "-"; ?></td>

<td><?= htmlspecialchars($data["status_gizi"]); ?></td>

<td><?= htmlspecialchars($data["status_stunting"]); ?></td>

<td>
<span class="badge <?= $warna ?>">
<?= htmlspecialchars($status); ?>
</span>
</td>

<td>

<a href="detail_deteksi.php?id=<?= $data["id_deteksi"]; ?>"
class="btn btn-info btn-sm">
Detail
</a>

<?php if($roleAktif=="petugas_gizi"): ?>

<a href="verifikasi_deteksi.php?id=<?= $data["id_deteksi"]; ?>"
class="btn btn-success btn-sm">
Verifikasi
</a>

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>
</div>

</main>
</div>

<?php require_once "../includes/footer.php"; ?>
