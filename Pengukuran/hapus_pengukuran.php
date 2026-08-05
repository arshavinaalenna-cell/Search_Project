<?php
session_start();
require_once "../config/koneksi.php";

// Cek apakah ID dikirim
if (!isset($_GET['id'])) {
    header("Location: data_pengukuran.php");
    exit;
}

$id = $_GET['id'];

// Hapus data
$hapus = mysqli_query($conn, "
    DELETE FROM pengukuran
    WHERE id_pengukuran = '$id'
");

// Cek hasil
if ($hapus) {

    echo "<script>
            alert('Data pengukuran berhasil dihapus');
            window.location='data_pengukuran.php';
          </script>";

} else {

    echo "<script>
            alert('Data pengukuran gagal dihapus');
            window.location='data_pengukuran.php';
          </script>";

}
?>