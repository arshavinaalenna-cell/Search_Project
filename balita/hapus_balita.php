<?php
include '../koneksi.php';

if(isset($_GET['id'])){

    $id = $_GET['id'];

    $hapus = mysqli_query($conn,"DELETE FROM balita WHERE id_balita='$id'");

    if($hapus){

        echo "<script>
                alert('Data balita berhasil dihapus');
                window.location='data_balita.php';
              </script>";

    }else{

        echo "<script>
                alert('Data balita gagal dihapus');
                window.location='data_balita.php';
              </script>";

    }

}else{

    echo "<script>
            alert('ID balita tidak ditemukan');
            window.location='data_balita.php';
          </script>";

}
?>