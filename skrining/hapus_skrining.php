<?php
session_start();
require_once "../config/koneksi.php";

if(!isset($_GET['id'])){
    header("Location: hasil_skrining.php");
    exit;
}

$id=(int)$_GET['id'];

$hapus=mysqli_query($conn,"
DELETE FROM skrining_awal
WHERE id_skrining='$id'
");

if($hapus){

echo "<script>
alert('Data berhasil dihapus');
window.location='hasil_skrining.php';
</script>";

}else{

echo "<script>
alert('Data gagal dihapus');
window.location='hasil_skrining.php';
</script>";

}
?>