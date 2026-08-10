<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";


cekRole([
    "kader",
    "petugas_gizi",
    "petugas_kia",
    "kepala_puskesmas",
    "dinkes"
]);


/*
|--------------------------------------------------------------------------
| Ambil ID skrining
|--------------------------------------------------------------------------
*/

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: hasil_skrining.php?pesan=tidak_ditemukan");
    exit;
}


/*
|--------------------------------------------------------------------------
| Ambil detail skrining
|--------------------------------------------------------------------------
|
| skrining_awal
|      ↓
| balita
|
| Pengukuran diambil dari pengukuran terbaru balita.
|
| Status gizi, status stunting, dan verifikasi diambil dari
| hasil_deteksi terbaru balita.
|
*/

$query = mysqli_query(
    $conn,
    "
    SELECT

        s.*,

        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,

        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,
        pa.tanggal_pengukuran,

        hd.id_deteksi,
        hd.status_gizi,
        hd.status_stunting,
        hd.status_verifikasi,
        hd.catatan_verifikasi,
        hd.diverifikasi_oleh,
        hd.tanggal_verifikasi

    FROM skrining_awal s

    INNER JOIN balita b
        ON s.id_balita = b.id_balita

    LEFT JOIN pengukuran_antropometri pa
        ON pa.id_pengukuran = (
            SELECT pa2.id_pengukuran
            FROM pengukuran_antropometri pa2
            WHERE pa2.id_balita = s.id_balita
            ORDER BY
                pa2.tanggal_pengukuran DESC,
                pa2.id_pengukuran DESC
            LIMIT 1
        )

    LEFT JOIN hasil_deteksi hd
        ON hd.id_deteksi = (
            SELECT hd2.id_deteksi
            FROM hasil_deteksi hd2

            INNER JOIN pengukuran_antropometri pa3
                ON hd2.id_pengukuran = pa3.id_pengukuran

            WHERE pa3.id_balita = s.id_balita

            ORDER BY
                hd2.tanggal_deteksi DESC,
                hd2.id_deteksi DESC

            LIMIT 1
        )

    WHERE s.id_skrining = $id

    LIMIT 1
    "
);


if (!$query) {
    die(
        "Gagal mengambil detail skrining: "
        . mysqli_error($conn)
    );
}


$data = mysqli_fetch_assoc($query);


if (!$data) {
    header("Location: hasil_skrining.php?pesan=tidak_ditemukan");
    exit;
}


/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function amanDetailSkrining($nilai): string
{
    if ($nilai === null || $nilai === "") {
        return "-";
    }

    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| Role aktif
|--------------------------------------------------------------------------
*/

$roleAktif = $_SESSION["role"] ?? "";

?>


<?php require "../includes/header.php"; ?>


<div class="container mt-4">


    <div class="card">


        <div class="card-header">

            <h4 class="mb-0">

                <i class="bi bi-clipboard2-heart me-2"></i>

                Detail Skrining Balita

            </h4>

        </div>


        <div class="card-body">


            <!-- =====================================================
                 IDENTITAS BALITA
            ====================================================== -->

            <h5 class="mb-3">
                Identitas dan Pengukuran Balita
            </h5>


            <div class="table-responsive">

                <table class="table table-bordered align-middle">


                    <tr>

                        <td width="35%">
                            Nama Balita
                        </td>

                        <td>
                            <strong>
                                <?= amanDetailSkrining(
                                    $data["nama_balita"]
                                ); ?>
                            </strong>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            NIK
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["nik_balita"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Jenis Kelamin
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["jenis_kelamin"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Umur Saat Pengukuran
                        </td>

                        <td>

                            <?php if (
                                $data["umur_bulan"] !== null
                                && $data["umur_bulan"] !== ""
                            ): ?>

                                <?= amanDetailSkrining(
                                    $data["umur_bulan"]
                                ); ?>
                                bulan

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            Berat Badan
                        </td>

                        <td>

                            <?php if (
                                $data["berat_badan"] !== null
                                && $data["berat_badan"] !== ""
                            ): ?>

                                <?= amanDetailSkrining(
                                    $data["berat_badan"]
                                ); ?>
                                kg

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            Tinggi/Panjang Badan
                        </td>

                        <td>

                            <?php if (
                                $data["tinggi_panjang_badan"] !== null
                                && $data["tinggi_panjang_badan"] !== ""
                            ): ?>

                                <?= amanDetailSkrining(
                                    $data["tinggi_panjang_badan"]
                                ); ?>
                                cm

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                    </tr>


                </table>

            </div>


            <!-- =====================================================
                 DATA SKRINING AWAL
            ====================================================== -->

            <h5 class="mt-4 mb-3">
                Faktor Skrining Awal
            </h5>


            <div class="table-responsive">

                <table class="table table-bordered align-middle">


                    <tr>

                        <td width="35%">
                            Tinggi Badan Ibu
                        </td>

                        <td>

                            <?php if (
                                $data["tinggi_badan_ibu"] !== null
                                && $data["tinggi_badan_ibu"] !== ""
                            ): ?>

                                <?= amanDetailSkrining(
                                    $data["tinggi_badan_ibu"]
                                ); ?>
                                cm

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            Pendidikan Ibu
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["pendidikan_ibu"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Pekerjaan Ibu
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["pekerjaan_ibu"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Lama ASI Eksklusif
                        </td>

                        <td>

                            <?php if (
                                $data["lama_asi_eksklusif"] !== null
                                && $data["lama_asi_eksklusif"] !== ""
                            ): ?>

                                <?= amanDetailSkrining(
                                    $data["lama_asi_eksklusif"]
                                ); ?>
                                bulan

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            MPASI
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["mpasi"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Frekuensi Makan
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["frekuensi_makan"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Protein Hewani
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["protein_hewani"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Status Ekonomi
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["status_ekonomi"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Sanitasi
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["sanitasi"]
                            ); ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Air Bersih
                        </td>

                        <td>
                            <?= amanDetailSkrining(
                                $data["air_bersih"]
                            ); ?>
                        </td>

                    </tr>


                </table>

            </div>


            <!-- =====================================================
                 HASIL DETEKSI
            ====================================================== -->

            <h5 class="mt-4 mb-3">
                Hasil Deteksi
            </h5>


            <div class="table-responsive">

                <table class="table table-bordered align-middle">


                    <tr>

                        <td width="35%">
                            Status Gizi
                        </td>

                        <td>

                            <?php if (
                                !empty($data["status_gizi"])
                            ): ?>

                                <span class="badge bg-info">

                                    <?= amanDetailSkrining(
                                        $data["status_gizi"]
                                    ); ?>

                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    Belum dianalisis
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            Status Stunting
                        </td>

                        <td>

                            <?php if (
                                !empty($data["status_stunting"])
                            ): ?>

                                <span class="badge bg-warning text-dark">

                                    <?= amanDetailSkrining(
                                        $data["status_stunting"]
                                    ); ?>

                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    Belum dianalisis
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            Status Verifikasi
                        </td>

                        <td>

                            <?= amanDetailSkrining(
                                $data["status_verifikasi"]
                                ?? "Belum diverifikasi"
                            ); ?>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            Catatan Verifikasi
                        </td>

                        <td>

                            <?= amanDetailSkrining(
                                $data["catatan_verifikasi"]
                                ?? "-"
                            ); ?>

                        </td>

                    </tr>


                </table>

            </div>


            <!-- =====================================================
                 TOMBOL
            ====================================================== -->

            <div class="d-flex flex-wrap gap-2 mt-4">


                <?php if (
                    $roleAktif === "petugas_gizi"
                ): ?>

                    <a
                        href="verifikasi_skrining.php?id=<?= $id; ?>"
                        class="btn btn-success"
                    >

                        <i class="bi bi-check-circle"></i>

                        Verifikasi Skrining

                    </a>

                <?php endif; ?>


                <a
                    href="hasil_skrining.php"
                    class="btn btn-secondary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Kembali

                </a>


            </div>


        </div>


    </div>


</div>


<?php require "../includes/footer.php"; ?>