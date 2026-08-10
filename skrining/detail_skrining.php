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
    header(
        "Location: hasil_skrining.php?pesan=tidak_ditemukan"
    );
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
    header(
        "Location: hasil_skrining.php?pesan=tidak_ditemukan"
    );
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


/*
|--------------------------------------------------------------------------
| Template
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>


<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>


    <main class="main-content">


        <div class="card content-card">


            <!-- =====================================================
                 HEADER
            ====================================================== -->

            <div class="card-header">

                <div>

                    <h4 class="mb-1">

                        <i class="bi bi-clipboard2-heart me-2"></i>

                        Detail Skrining Balita

                    </h4>

                    <small class="text-muted">

                        Tinjau identitas, data pengukuran,
                        faktor skrining awal, dan hasil deteksi balita.

                    </small>

                </div>


                <div class="d-flex flex-wrap gap-2">

                    <a
                        href="hasil_skrining.php"
                        class="btn btn-secondary btn-sm"
                    >

                        <i class="bi bi-arrow-left"></i>

                        Kembali

                    </a>


                    <?php if (
                        $roleAktif === "petugas_gizi"
                    ): ?>

                        <a
                            href="verifikasi_skrining.php?id=<?= $id; ?>"
                            class="btn btn-success btn-sm"
                        >

                            <i class="bi bi-check-circle"></i>

                            Verifikasi Skrining

                        </a>

                    <?php endif; ?>

                </div>

            </div>


            <div class="card-body">


                <!-- =================================================
                     IDENTITAS BALITA
                ================================================== -->

                <div class="mb-4">

                    <div
                        class="d-flex
                        align-items-center
                        gap-2
                        mb-3"
                    >

                        <span class="badge badge-primary">

                            <i class="bi bi-person-heart"></i>

                        </span>

                        <div>

                            <h5 class="mb-0">
                                Identitas Balita
                            </h5>

                            <small class="text-muted">
                                Informasi dasar balita yang diskrining.
                            </small>

                        </div>

                    </div>


                    <div class="row g-3">


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Nama Balita
                                </span>

                                <div class="detail-value">

                                    <strong>

                                        <?= amanDetailSkrining(
                                            $data["nama_balita"]
                                        ); ?>

                                    </strong>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    NIK Balita
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["nik_balita"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Jenis Kelamin
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["jenis_kelamin"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Umur Saat Pengukuran
                                </span>

                                <div class="detail-value">

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

                                </div>

                            </div>

                        </div>


                    </div>

                </div>


                <hr class="my-4">


                <!-- =================================================
                     DATA ANTROPOMETRI
                ================================================== -->

                <div class="mb-4">

                    <div
                        class="d-flex
                        align-items-center
                        gap-2
                        mb-3"
                    >

                        <span class="badge badge-info">

                            <i class="bi bi-rulers"></i>

                        </span>

                        <div>

                            <h5 class="mb-0">
                                Pengukuran Antropometri
                            </h5>

                            <small class="text-muted">
                                Data pengukuran terbaru balita.
                            </small>

                        </div>

                    </div>


                    <div class="row g-3">


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Berat Badan
                                </span>

                                <div class="detail-value">

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

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Tinggi/Panjang Badan
                                </span>

                                <div class="detail-value">

                                    <?php if (
                                        $data[
                                            "tinggi_panjang_badan"
                                        ] !== null
                                        &&
                                        $data[
                                            "tinggi_panjang_badan"
                                        ] !== ""
                                    ): ?>

                                        <?= amanDetailSkrining(
                                            $data[
                                                "tinggi_panjang_badan"
                                            ]
                                        ); ?>

                                        cm

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Tanggal Pengukuran
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data[
                                            "tanggal_pengukuran"
                                        ]
                                        ?? null
                                    ); ?>

                                </div>

                            </div>

                        </div>


                    </div>

                </div>


                <hr class="my-4">


                <!-- =================================================
                     INFORMASI IBU
                ================================================== -->

                <div class="mb-4">

                    <div
                        class="d-flex
                        align-items-center
                        gap-2
                        mb-3"
                    >

                        <span class="badge badge-primary">

                            <i class="bi bi-person-hearts"></i>

                        </span>

                        <div>

                            <h5 class="mb-0">
                                Informasi Ibu
                            </h5>

                            <small class="text-muted">
                                Faktor maternal yang tercatat saat skrining.
                            </small>

                        </div>

                    </div>


                    <div class="row g-3">


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Tinggi Badan Ibu
                                </span>

                                <div class="detail-value">

                                    <?php if (
                                        $data[
                                            "tinggi_badan_ibu"
                                        ] !== null
                                        &&
                                        $data[
                                            "tinggi_badan_ibu"
                                        ] !== ""
                                    ): ?>

                                        <?= amanDetailSkrining(
                                            $data[
                                                "tinggi_badan_ibu"
                                            ]
                                        ); ?>

                                        cm

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Pendidikan Ibu
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["pendidikan_ibu"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Pekerjaan Ibu
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["pekerjaan_ibu"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                    </div>

                </div>


                <hr class="my-4">


                <!-- =================================================
                     POLA PEMBERIAN MAKAN
                ================================================== -->

                <div class="mb-4">

                    <div
                        class="d-flex
                        align-items-center
                        gap-2
                        mb-3"
                    >

                        <span class="badge badge-info">

                            <i class="bi bi-cup-straw"></i>

                        </span>

                        <div>

                            <h5 class="mb-0">
                                Pola Pemberian Makan
                            </h5>

                            <small class="text-muted">
                                Riwayat ASI, MPASI, dan pola konsumsi balita.
                            </small>

                        </div>

                    </div>


                    <div class="row g-3">


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Lama ASI Eksklusif
                                </span>

                                <div class="detail-value">

                                    <?php if (
                                        $data[
                                            "lama_asi_eksklusif"
                                        ] !== null
                                        &&
                                        $data[
                                            "lama_asi_eksklusif"
                                        ] !== ""
                                    ): ?>

                                        <?= amanDetailSkrining(
                                            $data[
                                                "lama_asi_eksklusif"
                                            ]
                                        ); ?>

                                        bulan

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Pemberian MPASI
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["mpasi"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Frekuensi Makan
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["frekuensi_makan"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Protein Hewani
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["protein_hewani"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                    </div>

                </div>


                <hr class="my-4">


                <!-- =================================================
                     KONDISI LINGKUNGAN
                ================================================== -->

                <div class="mb-4">

                    <div
                        class="d-flex
                        align-items-center
                        gap-2
                        mb-3"
                    >

                        <span class="badge badge-primary">

                            <i class="bi bi-house-heart"></i>

                        </span>

                        <div>

                            <h5 class="mb-0">
                                Kondisi Lingkungan
                            </h5>

                            <small class="text-muted">
                                Kondisi ekonomi dan lingkungan rumah balita.
                            </small>

                        </div>

                    </div>


                    <div class="row g-3">


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Status Ekonomi
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["status_ekonomi"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Sanitasi
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["sanitasi"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Air Bersih
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data["air_bersih"]
                                    ); ?>

                                </div>

                            </div>

                        </div>


                    </div>

                </div>


                <hr class="my-4">


                <!-- =================================================
                     HASIL DETEKSI
                ================================================== -->

                <div>

                    <div
                        class="d-flex
                        align-items-center
                        gap-2
                        mb-3"
                    >

                        <span class="badge badge-info">

                            <i class="bi bi-activity"></i>

                        </span>

                        <div>

                            <h5 class="mb-0">
                                Hasil Deteksi
                            </h5>

                            <small class="text-muted">
                                Hasil deteksi dan status verifikasi terbaru.
                            </small>

                        </div>

                    </div>


                    <div class="row g-3">


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Status Gizi
                                </span>

                                <div class="detail-value">

                                    <?php if (
                                        !empty(
                                            $data["status_gizi"]
                                        )
                                    ): ?>

                                        <span
                                            class="badge
                                            rounded-pill
                                            bg-info"
                                        >

                                            <?= amanDetailSkrining(
                                                $data["status_gizi"]
                                            ); ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge
                                            rounded-pill
                                            bg-secondary"
                                        >
                                            Belum dianalisis
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Status Stunting
                                </span>

                                <div class="detail-value">

                                    <?php if (
                                        !empty(
                                            $data["status_stunting"]
                                        )
                                    ): ?>

                                        <span
                                            class="badge
                                            rounded-pill
                                            bg-warning
                                            text-dark"
                                        >

                                            <?= amanDetailSkrining(
                                                $data[
                                                    "status_stunting"
                                                ]
                                            ); ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge
                                            rounded-pill
                                            bg-secondary"
                                        >
                                            Belum dianalisis
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Status Verifikasi
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data[
                                            "status_verifikasi"
                                        ]
                                        ?? "Belum diverifikasi"
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Catatan Verifikasi
                                </span>

                                <div class="detail-value">

                                    <?= amanDetailSkrining(
                                        $data[
                                            "catatan_verifikasi"
                                        ]
                                        ?? "-"
                                    ); ?>

                                </div>

                            </div>

                        </div>


                    </div>

                </div>


            </div>


        </div>


    </main>


</div>


<?php require_once "../includes/footer.php"; ?>