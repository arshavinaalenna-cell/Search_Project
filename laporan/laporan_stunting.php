<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses laporan
|--------------------------------------------------------------------------
*/

cekRole([
    "petugas_gizi",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman =
    "Laporan Stunting | Sistem Deteksi Stunting";

$roleAktif =
    $_SESSION["role"] ?? "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$aksesPuskesmasTerbatas =
    in_array(
        $roleAktif,
        [
            "petugas_gizi",
            "kepala_puskesmas"
        ],
        true
    );

$idPuskesmasAkun = 0;
$namaPuskesmasAkun = "";
$puskesmasBelumTerhubung = false;

/*
|--------------------------------------------------------------------------
| Menentukan Puskesmas akun aktif
|--------------------------------------------------------------------------
|
| Petugas Gizi dan Kepala Puskesmas hanya boleh melihat laporan dari
| Puskesmas yang terhubung dengan akun mereka. Dinkes dapat melihat semua.
|
*/

if ($aksesPuskesmasTerbatas) {

    $stmtPuskesmasAkun = mysqli_prepare(
        $conn,
        "SELECT
            u.id_puskesmas,
            p.nama_puskesmas
         FROM pengguna AS u
         LEFT JOIN puskesmas AS p
            ON u.id_puskesmas = p.id_puskesmas
         WHERE u.id_user = ?
         LIMIT 1"
    );

    if (!$stmtPuskesmasAkun) {
        die(
            "Gagal memeriksa Puskesmas pengguna: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtPuskesmasAkun,
        "i",
        $idUserAktif
    );

    mysqli_stmt_execute(
        $stmtPuskesmasAkun
    );

    $hasilPuskesmasAkun =
        mysqli_stmt_get_result(
            $stmtPuskesmasAkun
        );

    $dataPuskesmasAkun =
        mysqli_fetch_assoc(
            $hasilPuskesmasAkun
        );

    mysqli_stmt_close(
        $stmtPuskesmasAkun
    );

    if (
        !$dataPuskesmasAkun
        || empty(
            $dataPuskesmasAkun[
                "id_puskesmas"
            ]
        )
        || empty(
            $dataPuskesmasAkun[
                "nama_puskesmas"
            ]
        )
    ) {
        $puskesmasBelumTerhubung = true;
    } else {
        $idPuskesmasAkun =
            (int) $dataPuskesmasAkun[
                "id_puskesmas"
            ];

        $namaPuskesmasAkun =
            trim(
                (string) $dataPuskesmasAkun[
                    "nama_puskesmas"
                ]
            );
    }
}

/*
|--------------------------------------------------------------------------
| Fungsi keamanan output
|--------------------------------------------------------------------------
*/

function amanLaporan($nilai): string
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
| Memvalidasi tanggal
|--------------------------------------------------------------------------
*/

function tanggalLaporanValid(string $tanggal): bool
{
    $objekTanggal = DateTime::createFromFormat(
        "Y-m-d",
        $tanggal
    );

    return (
        $objekTanggal !== false
        && $objekTanggal->format("Y-m-d") === $tanggal
    );
}

/*
|--------------------------------------------------------------------------
| Format tanggal Indonesia sederhana
|--------------------------------------------------------------------------
*/

function formatTanggalLaporan($tanggal): string
{
    if (
        empty($tanggal)
        || $tanggal === "0000-00-00"
    ) {
        return "-";
    }

    $waktu = strtotime($tanggal);

    if ($waktu === false) {
        return amanLaporan($tanggal);
    }

    return date("d-m-Y", $waktu);
}

/*
|--------------------------------------------------------------------------
| Menentukan warna badge status
|--------------------------------------------------------------------------
*/

function kelasStatusLaporan($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    if (
        in_array(
            $status,
            [
                "risiko rendah",
                "normal",
                "normal/sehat",
                "tidak stunting"
            ],
            true
        )
    ) {
        return "badge-success";
    }

    if (
        in_array(
            $status,
            [
                "risiko sedang",
                "risiko stunting",
                "pendek"
            ],
            true
        )
    ) {
        return "badge-warning";
    }

    if (
        in_array(
            $status,
            [
                "stunting berat",
                "sangat pendek",
                "severely stunted"
            ],
            true
        )
    ) {
        return "bg-dark text-white";
    }

    if (
        in_array(
            $status,
            [
                "risiko tinggi",
                "stunting"
            ],
            true
        )
    ) {
        return "badge-danger";
    }

    return "badge-info";
}

/*
|--------------------------------------------------------------------------
| Mengambil master Puskesmas
|--------------------------------------------------------------------------
|
| Dinkes mendapat seluruh daftar Puskesmas untuk filter.
| Petugas Gizi dan Kepala Puskesmas hanya mendapat Puskesmas akunnya.
|
*/

$daftarPuskesmas = [];

if ($roleAktif === "dinkes") {

    $queryPuskesmas = mysqli_query(
        $conn,
        "SELECT
            id_puskesmas,
            nama_puskesmas
         FROM puskesmas
         ORDER BY nama_puskesmas ASC"
    );

    if (!$queryPuskesmas) {
        die(
            "Gagal mengambil data Puskesmas: "
            . mysqli_error($conn)
        );
    }

    while (
        $puskesmas =
            mysqli_fetch_assoc(
                $queryPuskesmas
            )
    ) {
        $daftarPuskesmas[] =
            $puskesmas;
    }

} elseif (
    !$puskesmasBelumTerhubung
) {

    $daftarPuskesmas[] = [
        "id_puskesmas" =>
            $idPuskesmasAkun,
        "nama_puskesmas" =>
            $namaPuskesmasAkun
    ];
}

/*
|--------------------------------------------------------------------------
| Filter laporan
|--------------------------------------------------------------------------
|
| Secara default laporan menampilkan data dari awal bulan berjalan
| sampai tanggal hari ini dan seluruh Puskesmas.
|
*/

$tanggalAwalDefault = date("Y-m-01");
$tanggalAkhirDefault = date("Y-m-d");

$tanggalAwal = trim(
    $_GET["tanggal_awal"]
    ?? $tanggalAwalDefault
);

$tanggalAkhir = trim(
    $_GET["tanggal_akhir"]
    ?? $tanggalAkhirDefault
);

$pesanError = "";

if ($aksesPuskesmasTerbatas) {

    $idPuskesmas =
        $puskesmasBelumTerhubung
            ? 0
            : $idPuskesmasAkun;

    $namaPuskesmasDipilih =
        $puskesmasBelumTerhubung
            ? "Puskesmas belum terhubung"
            : $namaPuskesmasAkun;

} else {

    $idPuskesmas = filter_input(
        INPUT_GET,
        "id_puskesmas",
        FILTER_VALIDATE_INT
    );

    if (
        $idPuskesmas === false
        || $idPuskesmas === null
    ) {
        $idPuskesmas = 0;
    }

    $idPuskesmas =
        (int) $idPuskesmas;

    $namaPuskesmasDipilih =
        "Semua Puskesmas";
}

if (!tanggalLaporanValid($tanggalAwal)) {
    $pesanError =
        "Tanggal awal laporan tidak valid.";

    $tanggalAwal =
        $tanggalAwalDefault;
}

if (!tanggalLaporanValid($tanggalAkhir)) {
    $pesanError =
        "Tanggal akhir laporan tidak valid.";

    $tanggalAkhir =
        $tanggalAkhirDefault;
}

if ($tanggalAwal > $tanggalAkhir) {
    $pesanError =
        "Tanggal awal tidak boleh melebihi tanggal akhir.";

    $tanggalAwal =
        $tanggalAwalDefault;

    $tanggalAkhir =
        $tanggalAkhirDefault;
}

/*
|--------------------------------------------------------------------------
| Memvalidasi Puskesmas yang dipilih
|--------------------------------------------------------------------------
*/

if (
    $roleAktif === "dinkes"
    && $idPuskesmas > 0
) {

    $puskesmasDitemukan = false;

    foreach (
        $daftarPuskesmas
        as $dataPuskesmas
    ) {
        if (
            (int) $dataPuskesmas["id_puskesmas"]
            === $idPuskesmas
        ) {
            $puskesmasDitemukan = true;

            $namaPuskesmasDipilih =
                $dataPuskesmas["nama_puskesmas"];

            break;
        }
    }

    if (!$puskesmasDitemukan) {
        $pesanError =
            "Puskesmas yang dipilih tidak ditemukan.";

        $idPuskesmas = 0;
        $namaPuskesmasDipilih =
            "Semua Puskesmas";
    }
}

/*
|--------------------------------------------------------------------------
| Mengambil data hasil deteksi
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        hd.id_deteksi,
        hd.id_pengukuran,
        hd.status_gizi,
        hd.status_stunting,
        hd.tanggal_deteksi,

        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,

        b.id_balita,
        b.id_puskesmas,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,
        b.nama_posyandu,

        ps.nama_puskesmas

    FROM hasil_deteksi AS hd

    INNER JOIN pengukuran_antropometri AS pa
        ON hd.id_pengukuran = pa.id_pengukuran

    INNER JOIN balita AS b
        ON pa.id_balita = b.id_balita

    LEFT JOIN puskesmas AS ps
        ON b.id_puskesmas = ps.id_puskesmas

    WHERE hd.tanggal_deteksi
        BETWEEN ? AND ?
";

if (
    $aksesPuskesmasTerbatas
    && $puskesmasBelumTerhubung
) {
    $sql .= "
        AND 1 = 0
    ";
} elseif ($idPuskesmas > 0) {
    $sql .= "
        AND b.id_puskesmas = ?
    ";
}

$sql .= "
    ORDER BY
        hd.tanggal_deteksi DESC,
        hd.id_deteksi DESC
";

$stmtLaporan = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmtLaporan) {
    die(
        "Gagal menyiapkan laporan stunting: "
        . mysqli_error($conn)
    );
}

if (
    !$puskesmasBelumTerhubung
    && $idPuskesmas > 0
) {

    mysqli_stmt_bind_param(
        $stmtLaporan,
        "ssi",
        $tanggalAwal,
        $tanggalAkhir,
        $idPuskesmas
    );

} else {

    mysqli_stmt_bind_param(
        $stmtLaporan,
        "ss",
        $tanggalAwal,
        $tanggalAkhir
    );
}

mysqli_stmt_execute($stmtLaporan);

$resultLaporan =
    mysqli_stmt_get_result($stmtLaporan);

/*
|--------------------------------------------------------------------------
| Menyimpan hasil ke array dan menghitung rekap
|--------------------------------------------------------------------------
*/

$dataLaporan = [];

$totalDeteksi = 0;
$totalRisikoRendah = 0;
$totalRisikoSedang = 0;
$totalRisikoTinggi = 0;

/*
|--------------------------------------------------------------------------
| Data agregasi untuk grafik
|--------------------------------------------------------------------------
|
| Grafik dihitung dari hasil laporan yang sudah terkena filter periode
| dan Puskesmas. Tidak ada data tambahan yang disimpan ke database.
|
*/

$grafikStatusGizi = [];
$grafikTrenBulanan = [];

while (
    $data = mysqli_fetch_assoc($resultLaporan)
) {
    $dataLaporan[] = $data;

    $totalDeteksi++;

    $status = strtolower(
        trim(
            $data["status_stunting"] ?? ""
        )
    );

    if (
        in_array(
            $status,
            [
                "risiko rendah",
                "normal",
                "normal/sehat",
                "tidak stunting"
            ],
            true
        )
    ) {
        $totalRisikoRendah++;

    } elseif (
        in_array(
            $status,
            [
                "risiko sedang",
                "risiko stunting",
                "pendek"
            ],
            true
        )
    ) {
        $totalRisikoSedang++;

    } elseif (
        in_array(
            $status,
            [
                "risiko tinggi",
                "stunting",
                "stunting berat",
                "sangat pendek",
                "severely stunted"
            ],
            true
        )
    ) {
        $totalRisikoTinggi++;
    }

    /*
    |--------------------------------------------------------------------------
    | Rekap status gizi
    |--------------------------------------------------------------------------
    */

    $statusGiziGrafik = trim(
        (string) (
            $data["status_gizi"] ?? ""
        )
    );

    if ($statusGiziGrafik === "") {
        $statusGiziGrafik = "Belum Diketahui";
    }

    if (!isset(
        $grafikStatusGizi[$statusGiziGrafik]
    )) {
        $grafikStatusGizi[
            $statusGiziGrafik
        ] = 0;
    }

    $grafikStatusGizi[
        $statusGiziGrafik
    ]++;

    /*
    |--------------------------------------------------------------------------
    | Rekap tren deteksi per bulan
    |--------------------------------------------------------------------------
    */

    $tanggalDeteksiGrafik =
        $data["tanggal_deteksi"] ?? "";

    if (
        $tanggalDeteksiGrafik !== ""
        && $tanggalDeteksiGrafik !==
            "0000-00-00"
    ) {
        $waktuGrafik = strtotime(
            $tanggalDeteksiGrafik
        );

        if ($waktuGrafik !== false) {
            $kunciBulan = date(
                "Y-m",
                $waktuGrafik
            );

            if (!isset(
                $grafikTrenBulanan[
                    $kunciBulan
                ]
            )) {
                $grafikTrenBulanan[
                    $kunciBulan
                ] = 0;
            }

            $grafikTrenBulanan[
                $kunciBulan
            ]++;
        }
    }
}

mysqli_stmt_close($stmtLaporan);

/*
|--------------------------------------------------------------------------
| Menyiapkan data grafik
|--------------------------------------------------------------------------
*/

ksort($grafikStatusGizi);
ksort($grafikTrenBulanan);

$namaBulanIndonesia = [
    1 => "Jan",
    2 => "Feb",
    3 => "Mar",
    4 => "Apr",
    5 => "Mei",
    6 => "Jun",
    7 => "Jul",
    8 => "Agu",
    9 => "Sep",
    10 => "Okt",
    11 => "Nov",
    12 => "Des"
];

$labelTrenBulanan = [];
$dataTrenBulanan = [];

foreach (
    $grafikTrenBulanan
    as $bulan => $jumlah
) {
    [$tahunGrafik, $nomorBulan] =
        array_map(
            "intval",
            explode("-", $bulan)
        );

    $labelTrenBulanan[] =
        (
            $namaBulanIndonesia[
                $nomorBulan
            ] ?? $nomorBulan
        )
        . " "
        . $tahunGrafik;

    $dataTrenBulanan[] =
        (int) $jumlah;
}

$labelStatusGizi =
    array_keys($grafikStatusGizi);

$dataStatusGizi =
    array_values($grafikStatusGizi);

$labelRisiko = [
    "Risiko Rendah",
    "Risiko Sedang",
    "Risiko Tinggi"
];

$dataRisiko = [
    $totalRisikoRendah,
    $totalRisikoSedang,
    $totalRisikoTinggi
];

/*
|--------------------------------------------------------------------------
| URL cetak mengikuti filter aktif
|--------------------------------------------------------------------------
*/

$parameterCetak = http_build_query([
    "tanggal_awal" => $tanggalAwal,
    "tanggal_akhir" => $tanggalAkhir,
    "id_puskesmas" => $idPuskesmas
]);

/*
|--------------------------------------------------------------------------
| Memanggil template aplikasi
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php if ($puskesmasBelumTerhubung): ?>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Akun ini belum terhubung dengan Puskesmas.
                Laporan wilayah tidak dapat ditampilkan sebelum
                Puskesmas akun ditentukan.
            </div>

        <?php endif; ?>

        <!-- Header halaman -->
        <div class="page-header">

            <div>

                <h1 class="page-title">

                    <i class="bi bi-file-earmark-medical me-2"></i>

                    Laporan Stunting

                </h1>

                <p class="page-subtitle">

                    Rekap hasil deteksi risiko stunting berdasarkan
                    periode dan Puskesmas yang dipilih.

                </p>

            </div>

            <div class="d-flex flex-wrap gap-2">

                <a
                    href="../dashboard/dashboard.php"
                    class="btn btn-secondary"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Dashboard
                </a>

                <?php if (!$puskesmasBelumTerhubung): ?>

                    <a
                        href="cetak_laporan.php?<?= amanLaporan(
                            $parameterCetak
                        ); ?>"
                        class="btn btn-primary"
                        target="_blank"
                    >
                        <i class="bi bi-printer"></i>
                        Cetak Laporan
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <?php if ($pesanError !== ""): ?>

            <div
                class="alert alert-warning
                alert-dismissible fade show"
                role="alert"
            >

                <i class="bi bi-exclamation-triangle me-1"></i>

                <?= amanLaporan($pesanError); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>

            </div>

        <?php endif; ?>

        <!-- Filter laporan -->
        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Filter Laporan
                    </h4>

                    <small class="text-muted">
                        Pilih rentang tanggal dan Puskesmas.
                    </small>

                </div>

                <span class="badge badge-info">
                    <i class="bi bi-funnel"></i>
                    Filter
                </span>

            </div>

            <div class="card-body">

                <form
                    method="GET"
                    action="laporan_stunting.php"
                >

                    <div class="row g-3">

                        <div class="col-12 col-lg-4">

                            <label
                                for="tanggal_awal"
                                class="form-label"
                            >
                                Tanggal Awal
                            </label>

                            <input
                                type="date"
                                name="tanggal_awal"
                                id="tanggal_awal"
                                class="form-control"
                                value="<?= amanLaporan(
                                    $tanggalAwal
                                ); ?>"
                                max="<?= date("Y-m-d"); ?>"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-4">

                            <label
                                for="tanggal_akhir"
                                class="form-label"
                            >
                                Tanggal Akhir
                            </label>

                            <input
                                type="date"
                                name="tanggal_akhir"
                                id="tanggal_akhir"
                                class="form-control"
                                value="<?= amanLaporan(
                                    $tanggalAkhir
                                ); ?>"
                                max="<?= date("Y-m-d"); ?>"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-4">

                            <label
                                for="id_puskesmas"
                                class="form-label"
                            >
                                Puskesmas
                            </label>

                            <?php if (
                                $roleAktif === "dinkes"
                            ): ?>

                                <select
                                    name="id_puskesmas"
                                    id="id_puskesmas"
                                    class="form-select"
                                >

                                    <option value="0">
                                        Semua Puskesmas
                                    </option>

                                    <?php foreach (
                                        $daftarPuskesmas
                                        as $puskesmas
                                    ): ?>

                                        <option
                                            value="<?= (int)
                                                $puskesmas[
                                                    "id_puskesmas"
                                                ]; ?>"
                                            <?= $idPuskesmas ===
                                                (int) $puskesmas[
                                                    "id_puskesmas"
                                                ]
                                                ? "selected"
                                                : ""; ?>
                                        >
                                            <?= amanLaporan(
                                                $puskesmas[
                                                    "nama_puskesmas"
                                                ]
                                            ); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            <?php else: ?>

                                <div class="detail-item">

                                    <span class="detail-label">
                                        Puskesmas Akun
                                    </span>

                                    <div class="detail-value">

                                        <?php if (
                                            !$puskesmasBelumTerhubung
                                        ): ?>

                                            <i class="bi bi-hospital me-1"></i>

                                            <?= amanLaporan(
                                                $namaPuskesmasAkun
                                            ); ?>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                bg-warning text-dark"
                                            >
                                                Belum Terhubung
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                                <small class="text-muted">
                                    Wilayah laporan mengikuti
                                    Puskesmas akun dan tidak dapat diubah.
                                </small>

                            <?php endif; ?>

                        </div>

                    </div>

                    <div class="form-actions mt-3">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-funnel"></i>
                            Tampilkan Laporan
                        </button>

                        <a
                            href="laporan_stunting.php"
                            class="btn btn-light"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset Filter
                        </a>

                    </div>

                </form>

            </div>

        </div>

        <!-- Ringkasan statistik -->
        <div class="stat-grid">

            <div class="stat-card stat-info">

                <div class="stat-icon">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Total Deteksi
                    </p>

                    <p class="stat-value">
                        <?= $totalDeteksi; ?>
                    </p>

                </div>

            </div>

            <div class="stat-card stat-success">

                <div class="stat-icon">
                    <i class="bi bi-shield-check"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Risiko Rendah
                    </p>

                    <p class="stat-value">
                        <?= $totalRisikoRendah; ?>
                    </p>

                </div>

            </div>

            <div class="stat-card stat-warning">

                <div class="stat-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Risiko Sedang
                    </p>

                    <p class="stat-value">
                        <?= $totalRisikoSedang; ?>
                    </p>

                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon">
                    <i class="bi bi-exclamation-octagon"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Risiko Tinggi
                    </p>

                    <p class="stat-value">
                        <?= $totalRisikoTinggi; ?>
                    </p>

                </div>

            </div>

        </div>

        <!-- Visualisasi laporan -->
        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Grafik Laporan Stunting
                    </h4>

                    <small class="text-muted">
                        Visualisasi otomatis berdasarkan periode
                        dan Puskesmas yang sedang dipilih.
                    </small>

                </div>

                <span class="badge badge-info">
                    <i class="bi bi-bar-chart-line"></i>
                    Visualisasi
                </span>

            </div>

            <div class="card-body">

                <?php if ($totalDeteksi > 0): ?>

                    <div class="row g-4">

                        <div class="col-12 col-xl-4">

                            <div
                                class="detail-item h-100"
                                style="min-height: 360px;"
                            >

                                <span class="detail-label">
                                    Komposisi Tingkat Risiko
                                </span>

                                <div
                                    style="
                                        position: relative;
                                        height: 300px;
                                    "
                                >
                                    <canvas
                                        id="grafikRisiko"
                                        aria-label="Grafik komposisi tingkat risiko"
                                    ></canvas>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-xl-8">

                            <div
                                class="detail-item h-100"
                                style="min-height: 360px;"
                            >

                                <span class="detail-label">
                                    Tren Jumlah Deteksi per Bulan
                                </span>

                                <div
                                    style="
                                        position: relative;
                                        height: 300px;
                                    "
                                >
                                    <canvas
                                        id="grafikTren"
                                        aria-label="Grafik tren deteksi per bulan"
                                    ></canvas>
                                </div>

                            </div>

                        </div>

                        <div class="col-12">

                            <div
                                class="detail-item"
                                style="min-height: 380px;"
                            >

                                <span class="detail-label">
                                    Distribusi Status Gizi
                                </span>

                                <div
                                    style="
                                        position: relative;
                                        height: 320px;
                                    "
                                >
                                    <canvas
                                        id="grafikStatusGizi"
                                        aria-label="Grafik distribusi status gizi"
                                    ></canvas>
                                </div>

                            </div>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            <i class="bi bi-bar-chart"></i>
                        </div>

                        <h3>
                            Grafik belum dapat ditampilkan
                        </h3>

                        <p>
                            Belum ada hasil deteksi pada periode
                            dan Puskesmas yang dipilih.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

        <!-- Daftar hasil laporan -->
        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Daftar Hasil Deteksi
                    </h4>

                    <small class="text-muted">

                        Periode:

                        <?= formatTanggalLaporan(
                            $tanggalAwal
                        ); ?>

                        sampai

                        <?= formatTanggalLaporan(
                            $tanggalAkhir
                        ); ?>

                        &nbsp;|&nbsp;

                        Puskesmas:

                        <?= amanLaporan(
                            $namaPuskesmasDipilih
                        ); ?>

                    </small>

                </div>

                <span class="badge badge-primary">

                    <?= $totalDeteksi; ?>

                    data

                </span>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th class="text-center">
                                    No
                                </th>

                                <th>
                                    Nama Balita
                                </th>

                                <th>
                                    NIK Balita
                                </th>

                                <th>
                                    Puskesmas Pembina
                                </th>

                                <th class="text-center">
                                    Jenis Kelamin
                                </th>

                                <th class="text-center">
                                    Umur
                                </th>

                                <th class="text-center">
                                    Berat Badan
                                </th>

                                <th class="text-center">
                                    Tinggi Badan
                                </th>

                                <th class="text-center">
                                    Status Gizi
                                </th>

                                <th class="text-center">
                                    Status Risiko
                                </th>

                                <th class="text-center">
                                    Tanggal Deteksi
                                </th>

                                <th class="text-center">
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($totalDeteksi > 0): ?>

                            <?php foreach (
                                $dataLaporan
                                as $nomor => $data
                            ): ?>

                                <?php

                                $idDeteksi =
                                    (int) $data["id_deteksi"];

                                $kelasStatus =
                                    kelasStatusLaporan(
                                        $data["status_stunting"]
                                    );

                                ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $nomor + 1; ?>
                                    </td>

                                    <td>

                                        <div
                                            class="d-flex
                                            align-items-center gap-2"
                                        >

                                            <span
                                                class="badge badge-primary"
                                            >
                                                <i
                                                    class="bi
                                                    bi-person-heart"
                                                ></i>
                                            </span>

                                            <strong>
                                                <?= amanLaporan(
                                                    $data["nama_balita"]
                                                ); ?>
                                            </strong>

                                        </div>

                                    </td>

                                    <td>
                                        <?= amanLaporan(
                                            $data["nik_balita"]
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= amanLaporan(
                                            $data[
                                                "nama_puskesmas"
                                            ]
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= amanLaporan(
                                            $data["jenis_kelamin"]
                                        ); ?>
                                    </td>

                                    <td class="text-center">

                                        <?= amanLaporan(
                                            $data["umur_bulan"]
                                        ); ?>

                                        bulan

                                    </td>

                                    <td class="text-center">

                                        <?= amanLaporan(
                                            $data["berat_badan"]
                                        ); ?>

                                        kg

                                    </td>

                                    <td class="text-center">

                                        <?= amanLaporan(
                                            $data[
                                                "tinggi_panjang_badan"
                                            ]
                                        ); ?>

                                        cm

                                    </td>

                                    <td class="text-center">

                                        <span class="badge badge-info">

                                            <?= amanLaporan(
                                                $data["status_gizi"]
                                            ); ?>

                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge <?= $kelasStatus; ?>"
                                        >

                                            <?= amanLaporan(
                                                $data[
                                                    "status_stunting"
                                                ]
                                            ); ?>

                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <?= formatTanggalLaporan(
                                            $data["tanggal_deteksi"]
                                        ); ?>

                                    </td>

                                    <td>

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

                                            <a
                                                href="detail_laporan.php?id=<?= $idDeteksi; ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Detail
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="12">

                                    <div class="empty-state">

                                        <div class="empty-state-icon">

                                            <i
                                                class="bi
                                                bi-file-earmark-medical"
                                            ></i>

                                        </div>

                                        <h3>
                                            Belum ada data laporan
                                        </h3>

                                        <p>
                                            Tidak ditemukan hasil
                                            deteksi pada periode dan
                                            Puskesmas yang dipilih.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</div>

<?php if ($totalDeteksi > 0): ?>

    <script
        type="application/json"
        id="dataGrafikLaporan"
    ><?= json_encode(
        [
            "risiko" => [
                "labels" => $labelRisiko,
                "data" => $dataRisiko
            ],
            "tren" => [
                "labels" => $labelTrenBulanan,
                "data" => $dataTrenBulanan
            ],
            "statusGizi" => [
                "labels" => $labelStatusGizi,
                "data" => $dataStatusGizi
            ]
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    ); ?></script>

    <script
        src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
    ></script>

    <script
        src="../assets/js/laporan_stunting.js"
    ></script>

<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>