<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

// Gunakan zona waktu aplikasi agar periode laporan mengikuti WIB.
date_default_timezone_set("Asia/Jakarta");

/*
|--------------------------------------------------------------------------
| Hak akses cetak laporan
|--------------------------------------------------------------------------
*/

cekRole([
    "petugas_gizi",
    "kepala_puskesmas",
    "dinkes"
]);

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

/*
|--------------------------------------------------------------------------
| Menentukan Puskesmas akun aktif
|--------------------------------------------------------------------------
|
| Petugas Gizi dan Kepala Puskesmas hanya boleh mencetak laporan
| dari Puskesmas yang terhubung dengan akun mereka.
| Dinkes tetap dapat mencetak seluruh wilayah.
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
        http_response_code(403);

        echo "
            <h2>Akses Ditolak</h2>
            <p>Akun ini belum terhubung dengan Puskesmas.</p>
            <a href='laporan_stunting.php'>Kembali ke Laporan</a>
        ";

        exit;
    }

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

/*
|--------------------------------------------------------------------------
| Fungsi bantuan
|--------------------------------------------------------------------------
*/

function amanCetak($nilai): string
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

function tanggalCetakValid(string $tanggal): bool
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

function formatTanggalCetak($tanggal): string
{
    if (
        $tanggal === null
        || $tanggal === ""
        || $tanggal === "0000-00-00"
    ) {
        return "-";
    }

    $waktu = strtotime((string) $tanggal);

    if ($waktu === false) {
        return amanCetak($tanggal);
    }

    $namaBulan = [
        1  => "Januari",
        2  => "Februari",
        3  => "Maret",
        4  => "April",
        5  => "Mei",
        6  => "Juni",
        7  => "Juli",
        8  => "Agustus",
        9  => "September",
        10 => "Oktober",
        11 => "November",
        12 => "Desember"
    ];

    $tanggalAngka = (int) date("d", $waktu);
    $bulanAngka = (int) date("m", $waktu);
    $tahunAngka = date("Y", $waktu);

    return
        $tanggalAngka
        . " "
        . $namaBulan[$bulanAngka]
        . " "
        . $tahunAngka;
}

function kelasStatusCetak($status): string
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
        return "status-rendah";
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
        return "status-sedang";
    }

    if (
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
        return "status-tinggi";
    }

    return "status-lain";
}

/*
|--------------------------------------------------------------------------
| Filter periode
|--------------------------------------------------------------------------
*/

$tanggalHariIni = date("Y-m-d");

$tanggalAwal = trim(
    $_GET["tanggal_awal"]
    ?? date("Y-m-01")
);

$tanggalAkhir = trim(
    $_GET["tanggal_akhir"]
    ?? $tanggalHariIni
);

if (!tanggalCetakValid($tanggalAwal)) {
    $tanggalAwal = date("Y-m-01");
}

if (!tanggalCetakValid($tanggalAkhir)) {
    $tanggalAkhir = $tanggalHariIni;
}

if ($tanggalAwal > $tanggalAkhir) {
    $tanggalAwal = date("Y-m-01");
    $tanggalAkhir = $tanggalHariIni;
}

/*
|--------------------------------------------------------------------------
| Filter Puskesmas
|--------------------------------------------------------------------------
|
| Dinkes boleh menggunakan filter id_puskesmas dari URL.
| Petugas Gizi dan Kepala Puskesmas selalu dipaksa menggunakan
| Puskesmas akun aktif, sehingga parameter URL tidak dapat mengubah wilayah.
|
*/

if ($aksesPuskesmasTerbatas) {

    $idPuskesmas =
        $idPuskesmasAkun;

    $namaPuskesmasDipilih =
        $namaPuskesmasAkun;

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

    if ($idPuskesmas > 0) {

        $stmtPuskesmas = mysqli_prepare(
            $conn,
            "SELECT
                id_puskesmas,
                nama_puskesmas
             FROM puskesmas
             WHERE id_puskesmas = ?
             LIMIT 1"
        );

        if ($stmtPuskesmas) {

            mysqli_stmt_bind_param(
                $stmtPuskesmas,
                "i",
                $idPuskesmas
            );

            mysqli_stmt_execute(
                $stmtPuskesmas
            );

            $hasilPuskesmas =
                mysqli_stmt_get_result(
                    $stmtPuskesmas
                );

            $dataPuskesmas =
                mysqli_fetch_assoc(
                    $hasilPuskesmas
                );

            if ($dataPuskesmas) {

                $namaPuskesmasDipilih =
                    $dataPuskesmas[
                        "nama_puskesmas"
                    ];

            } else {

                $idPuskesmas = 0;
                $namaPuskesmasDipilih =
                    "Semua Puskesmas";
            }

            mysqli_stmt_close(
                $stmtPuskesmas
            );

        } else {

            $idPuskesmas = 0;
            $namaPuskesmasDipilih =
                "Semua Puskesmas";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Mengambil data laporan
|--------------------------------------------------------------------------
*/

$sqlLaporan = "
    SELECT
        hd.id_deteksi,
        hd.status_gizi,
        hd.status_stunting,
        hd.tanggal_deteksi,
        hd.status_verifikasi,

        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,

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

if ($idPuskesmas > 0) {
    $sqlLaporan .= "
        AND b.id_puskesmas = ?
    ";
}

$sqlLaporan .= "
     ORDER BY
        hd.tanggal_deteksi ASC,
        b.nama_balita ASC,
        hd.id_deteksi ASC
";

$stmtLaporan = mysqli_prepare(
    $conn,
    $sqlLaporan
);

if (!$stmtLaporan) {
    die(
        "Gagal menyiapkan cetak laporan: "
        . mysqli_error($conn)
    );
}

if ($idPuskesmas > 0) {

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

$dataLaporan = [];

$totalDeteksi = 0;
$totalRisikoRendah = 0;
$totalRisikoSedang = 0;
$totalRisikoTinggi = 0;

$totalSudahDiverifikasi = 0;
$totalPerluPemeriksaanUlang = 0;
$totalBelumDiverifikasi = 0;

/*
|--------------------------------------------------------------------------
| Data agregasi grafik
|--------------------------------------------------------------------------
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
            (string) (
                $data["status_stunting"] ?? ""
            )
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

    $statusVerifikasi =
        strtolower(
            trim(
                (string) (
                    $data["status_verifikasi"]
                    ?? "Belum diverifikasi"
                )
            )
        );

    if (
        $statusVerifikasi ===
        "sudah diverifikasi"
    ) {
        $totalSudahDiverifikasi++;
    } elseif (
        $statusVerifikasi ===
        "perlu pemeriksaan ulang"
    ) {
        $totalPerluPemeriksaanUlang++;
    } else {
        $totalBelumDiverifikasi++;
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
        $statusGiziGrafik =
            "Belum Diketahui";
    }

    if (!isset(
        $grafikStatusGizi[
            $statusGiziGrafik
        ]
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
| Menyiapkan data grafik untuk versi cetak
|--------------------------------------------------------------------------
*/

ksort($grafikStatusGizi);
ksort($grafikTrenBulanan);

$namaBulanSingkat = [
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
            $namaBulanSingkat[
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

$namaPencetak = $_SESSION["nama"] ?? "Pengguna";
$rolePencetak = namaRole(
    $_SESSION["role"] ?? ""
);

$parameterKembali = http_build_query([
    "tanggal_awal" => $tanggalAwal,
    "tanggal_akhir" => $tanggalAkhir,
    "id_puskesmas" => $idPuskesmas
]);

/*
|--------------------------------------------------------------------------
| Format kop surat berdasarkan role
|--------------------------------------------------------------------------
|
| Dinkes:
|   kiri  = Logo Dinas Kesehatan
|   kanan = kosong
|
| Puskesmas (Petugas Gizi / Kepala Puskesmas):
|   kiri  = Logo Dinas Kesehatan
|   kanan = Logo Puskesmas
|
| Dengan begitu kop Puskesmas tetap menunjukkan pembinaan Dinas Kesehatan
| sekaligus identitas Puskesmas yang menerbitkan laporan.
|
*/

$formatCetakDinkes =
    $roleAktif === "dinkes";

/*
|--------------------------------------------------------------------------
| Logo kiri: Dinas Kesehatan untuk semua format
|--------------------------------------------------------------------------
*/

$logoKiriSrc =
    "../assets/img/logo_dinkes.png";

$logoKiriServer =
    __DIR__
    . "/../assets/img/logo_dinkes.png";

$logoKiriAda =
    file_exists(
        $logoKiriServer
    );

$labelLogoKiri =
    "LOGO<br>DINAS<br>KESEHATAN";

/*
|--------------------------------------------------------------------------
| Logo kanan: khusus laporan Puskesmas
|--------------------------------------------------------------------------
*/

$logoKananSrc =
    "../assets/img/logo_puskesmas.png";

$logoKananServer =
    __DIR__
    . "/../assets/img/logo_puskesmas.png";

$logoKananAda =
    !$formatCetakDinkes
    && file_exists(
        $logoKananServer
    );

$labelLogoKanan =
    "LOGO<br>PUSKESMAS";

if ($formatCetakDinkes) {

    $judulKopUtama =
        "DINAS KESEHATAN";

    $judulKopKedua =
        "LAPORAN PEMANTAUAN STUNTING";

    $subjudulKop =
        "Sistem Deteksi dan Pemantauan Stunting";

    $footerInstansi =
        "Dinas Kesehatan";

} else {

    $judulKopUtama =
        "PUSKESMAS";

    $judulKopKedua =
        $namaPuskesmasAkun !== ""
            ? strtoupper(
                $namaPuskesmasAkun
            )
            : strtoupper(
                $namaPuskesmasDipilih
            );

    $subjudulKop =
        "Sistem Deteksi dan Pemantauan Stunting";

    $footerInstansi =
        $namaPuskesmasAkun !== ""
            ? "Puskesmas "
                . $namaPuskesmasAkun
            : "Puskesmas";
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Cetak Laporan Stunting
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;

            color: #222222;
            background: #f1f3f5;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 12px;
            line-height: 1.5;
        }

        .print-sheet {
            width: 100%;
            max-width: 1200px;

            margin: 0 auto;
            padding: 34px 38px;

            background: #ffffff;

            border: 1px solid #d9d9d9;

            box-shadow:
                0 10px 35px rgba(0, 0, 0, .08);
        }

        .toolbar {
            max-width: 1200px;

            display: flex;
            justify-content: flex-end;
            gap: 8px;

            margin: 0 auto 15px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 38px;
            padding: 8px 15px;

            border: 0;
            border-radius: 8px;

            color: #ffffff;
            background: #6a8fb3;

            font: inherit;
            font-weight: 700;

            cursor: pointer;
            text-decoration: none;
        }

        .button-secondary {
            color: #333333;
            background: #ffffff;
            border: 1px solid #cccccc;
        }

        .report-header {
            display: grid;
            grid-template-columns: 90px 1fr 90px;
            align-items: center;

            padding-bottom: 18px;

            border-bottom: 3px double #333333;
        }

        .report-logo {
            width: 78px;
            height: 78px;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #555555;

            font-size: 10px;
            font-weight: 700;
            text-align: center;
        }

        .report-logo img {
            display: block;

            width: 72px;
            height: 72px;

            object-fit: contain;
        }

        .report-logo-placeholder {
            width: 72px;
            height: 72px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 2px solid #777777;
            border-radius: 50%;
        }

        .report-heading {
            text-align: center;
        }

        .report-heading h1,
        .report-heading h2,
        .report-heading p {
            margin: 0;
        }

        .report-heading h1 {
            font-size: 19px;
            text-transform: uppercase;
        }

        .report-heading h2 {
            margin-top: 3px;

            font-size: 16px;
            text-transform: uppercase;
        }

        .report-heading p {
            margin-top: 6px;

            color: #555555;

            font-size: 11px;
        }

        .report-title {
            margin: 26px 0 20px;

            text-align: center;
        }

        .report-title h3 {
            display: inline-block;

            margin: 0;
            padding-bottom: 3px;

            border-bottom: 1px solid #222222;

            font-size: 15px;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .report-title p {
            margin: 5px 0 0;

            font-size: 12px;
        }

        .report-meta {
            width: 100%;

            margin-bottom: 18px;

            border-collapse: collapse;
        }

        .report-meta td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .report-meta td:first-child {
            width: 145px;
            font-weight: 700;
        }

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 10px;

            margin-bottom: 20px;
        }

        .summary-item {
            padding: 12px;

            border: 1px solid #bcbcbc;
            border-radius: 7px;

            text-align: center;
        }

        .summary-label {
            display: block;

            margin-bottom: 4px;

            color: #555555;

            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary-value {
            display: block;

            font-size: 22px;
            font-weight: 800;
        }

        .chart-section {
            margin: 22px 0 24px;
        }

        .chart-section-title {
            margin: 0 0 10px;

            font-size: 13px;
            text-align: center;
            text-transform: uppercase;
        }

        .chart-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 14px;

            margin-bottom: 14px;
        }

        .chart-card {
            padding: 12px;

            border: 1px solid #bcbcbc;
            border-radius: 7px;

            background: #ffffff;

            page-break-inside: avoid;
            break-inside: avoid;
        }

        .chart-card-full {
            grid-column: 1 / -1;
        }

        .chart-title {
            display: block;

            margin-bottom: 8px;

            color: #444444;

            font-size: 10px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
        }

        .chart-box {
            position: relative;

            height: 240px;
        }

        .chart-card-full .chart-box {
            height: 260px;
        }

        .report-table {
            width: 100%;

            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 7px 6px;

            border: 1px solid #555555;

            vertical-align: middle;
        }

        .report-table th {
            background: #e9ecef;

            font-size: 10px;
            text-align: center;
            text-transform: uppercase;
        }

        .report-table td {
            font-size: 10px;
        }

        .text-center {
            text-align: center;
        }

        .status-label {
            display: inline-block;

            padding: 3px 7px;

            border: 1px solid #777777;
            border-radius: 999px;

            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-rendah {
            background: #e7f5ec;
        }

        .status-sedang {
            background: #fff3cd;
        }

        .status-tinggi {
            background: #f8d7da;
        }

        .status-lain {
            background: #e2e3e5;
        }

        .empty-row {
            padding: 30px !important;

            color: #777777;

            text-align: center;
        }

        .report-note {
            margin-top: 14px;

            color: #555555;

            font-size: 10px;
        }

        .signature-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 100px;

            margin-top: 38px;
        }

        .signature-box {
            text-align: center;
        }

        .signature-box p {
            margin: 0;
        }

        .signature-space {
            height: 75px;
        }

        .signature-name {
            display: inline-block;

            min-width: 190px;
            padding-top: 2px;

            border-top: 1px solid #222222;

            font-weight: 700;
        }

        .report-footer {
            margin-top: 28px;
            padding-top: 10px;

            border-top: 1px solid #bbbbbb;

            color: #777777;

            font-size: 9px;
            text-align: center;
        }

        @media (max-width: 800px) {

            body {
                padding: 10px;
            }

            .print-sheet {
                padding: 20px 16px;
            }

            .summary-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .report-table {
                min-width: 900px;
            }

            .table-wrapper {
                overflow-x: auto;
            }

            .signature-grid {
                gap: 30px;
            }

            .chart-grid {
                grid-template-columns: 1fr;
            }

            .chart-card-full {
                grid-column: auto;
            }

        }

        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        @media print {

            body {
                padding: 0;

                background: #ffffff;

                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .toolbar {
                display: none !important;
            }

            .print-sheet {
                width: 100%;
                max-width: none;

                margin: 0;
                padding: 0;

                border: 0;

                box-shadow: none;
            }

            .report-table {
                page-break-inside: auto;
            }

            .report-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .report-table thead {
                display: table-header-group;
            }

            .report-table tfoot {
                display: table-footer-group;
            }

            .summary-item,
            .signature-grid,
            .chart-section,
            .chart-card {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .chart-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .chart-card-full {
                grid-column: 1 / -1;
            }

            .chart-box {
                height: 210px;
            }

            .chart-card-full .chart-box {
                height: 230px;
            }

        }

    </style>

</head>

<body>

    <div class="toolbar">

        <a
            href="laporan_stunting.php?<?= amanCetak(
                $parameterKembali
            ); ?>"
            class="button button-secondary"
        >
            Kembali
        </a>

        <button
            type="button"
            class="button"
            onclick="window.print();"
        >
            Cetak Sekarang
        </button>

    </div>

    <main class="print-sheet">

        <header class="report-header">

            <!-- Logo kiri: Dinas Kesehatan -->
            <div class="report-logo">

                <?php if ($logoKiriAda): ?>

                    <img
                        src="<?= amanCetak(
                            $logoKiriSrc
                        ); ?>"
                        alt="Logo Dinas Kesehatan"
                    >

                <?php else: ?>

                    <div
                        class="report-logo-placeholder"
                    >
                        <?= $labelLogoKiri; ?>
                    </div>

                <?php endif; ?>

            </div>

            <!-- Identitas instansi -->
            <div class="report-heading">

                <h1>
                    <?= amanCetak(
                        $judulKopUtama
                    ); ?>
                </h1>

                <h2>
                    <?= amanCetak(
                        $judulKopKedua
                    ); ?>
                </h2>

                <p>
                    <?= amanCetak(
                        $subjudulKop
                    ); ?>
                </p>

            </div>

            <!--
                Dinkes: kosong.
                Puskesmas: Logo Puskesmas.
            -->
            <div class="report-logo">

                <?php if (!$formatCetakDinkes): ?>

                    <?php if ($logoKananAda): ?>

                        <img
                            src="<?= amanCetak(
                                $logoKananSrc
                            ); ?>"
                            alt="Logo Puskesmas"
                        >

                    <?php else: ?>

                        <div
                            class="report-logo-placeholder"
                        >
                            <?= $labelLogoKanan; ?>
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </header>

        <section class="report-title">

            <h3>
                Laporan Hasil Deteksi Risiko Stunting
            </h3>

            <p>
                Periode
                <?= formatTanggalCetak($tanggalAwal); ?>
                sampai
                <?= formatTanggalCetak($tanggalAkhir); ?>
                <br>
                Puskesmas:
                <?= amanCetak($namaPuskesmasDipilih); ?>
            </p>

        </section>

        <table class="report-meta">

            <tr>
                <td>
                    Tanggal cetak
                </td>

                <td>
                    :
                    <?= formatTanggalCetak(
                        date("Y-m-d")
                    ); ?>
                </td>
            </tr>

            <tr>
                <td>
                    Dicetak oleh
                </td>

                <td>
                    :
                    <?= amanCetak($namaPencetak); ?>
                </td>
            </tr>

            <tr>
                <td>
                    Jabatan/Role
                </td>

                <td>
                    :
                    <?= amanCetak($rolePencetak); ?>
                </td>
            </tr>

            <tr>
                <td>
                    Puskesmas
                </td>

                <td>
                    :
                    <?= amanCetak(
                        $namaPuskesmasDipilih
                    ); ?>
                </td>
            </tr>

        </table>

        <section class="summary-grid">

            <div class="summary-item">
                <span class="summary-label">
                    Total Deteksi
                </span>

                <span class="summary-value">
                    <?= $totalDeteksi; ?>
                </span>
            </div>

            <div class="summary-item">
                <span class="summary-label">
                    Risiko Rendah
                </span>

                <span class="summary-value">
                    <?= $totalRisikoRendah; ?>
                </span>
            </div>

            <div class="summary-item">
                <span class="summary-label">
                    Risiko Sedang
                </span>

                <span class="summary-value">
                    <?= $totalRisikoSedang; ?>
                </span>
            </div>

            <div class="summary-item">
                <span class="summary-label">
                    Risiko Tinggi
                </span>

                <span class="summary-value">
                    <?= $totalRisikoTinggi; ?>
                </span>
            </div>

        </section>

        <div
            class="report-meta"
            style="margin-top: 10px;"
        >
            <strong>Status Verifikasi:</strong>
            Sudah diverifikasi
            <?= $totalSudahDiverifikasi; ?>
            &nbsp;|&nbsp;
            Perlu pemeriksaan ulang
            <?= $totalPerluPemeriksaanUlang; ?>
            &nbsp;|&nbsp;
            Belum diverifikasi
            <?= $totalBelumDiverifikasi; ?>
        </div>

        <?php if ($totalDeteksi > 0): ?>

            <section class="chart-section">

                <h4 class="chart-section-title">
                    Visualisasi Hasil Deteksi
                </h4>

                <div class="chart-grid">

                    <div class="chart-card">

                        <span class="chart-title">
                            Komposisi Tingkat Risiko
                        </span>

                        <div class="chart-box">

                            <canvas
                                id="grafikRisiko"
                                aria-label="Grafik komposisi tingkat risiko"
                            ></canvas>

                        </div>

                    </div>

                    <div class="chart-card">

                        <span class="chart-title">
                            Tren Jumlah Deteksi per Bulan
                        </span>

                        <div class="chart-box">

                            <canvas
                                id="grafikTren"
                                aria-label="Grafik tren deteksi per bulan"
                            ></canvas>

                        </div>

                    </div>

                    <div
                        class="chart-card
                        chart-card-full"
                    >

                        <span class="chart-title">
                            Distribusi Status Gizi
                        </span>

                        <div class="chart-box">

                            <canvas
                                id="grafikStatusGizi"
                                aria-label="Grafik distribusi status gizi"
                            ></canvas>

                        </div>

                    </div>

                </div>

            </section>

        <?php endif; ?>

        <div class="table-wrapper">

            <table class="report-table">

                <thead>

                    <tr>
                        <th>
                            No
                        </th>

                        <th>
                            Tanggal Deteksi
                        </th>

                        <th>
                            Nama Balita
                        </th>

                        <th>
                            NIK
                        </th>

                        <th>
                            Puskesmas
                        </th>

                        <th>
                            JK
                        </th>

                        <th>
                            Umur
                        </th>

                        <th>
                            BB
                        </th>

                        <th>
                            TB/PB
                        </th>

                        <th>
                            Status Gizi
                        </th>

                        <th>
                            Status Risiko
                        </th>

                        <th>
                            Verifikasi
                        </th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($totalDeteksi > 0): ?>

                    <?php foreach (
                        $dataLaporan
                        as $nomor => $data
                    ): ?>

                        <tr>

                            <td class="text-center">
                                <?= $nomor + 1; ?>
                            </td>

                            <td class="text-center">
                                <?= formatTanggalCetak(
                                    $data["tanggal_deteksi"]
                                ); ?>
                            </td>

                            <td>
                                <?= amanCetak(
                                    $data["nama_balita"]
                                ); ?>
                            </td>

                            <td>
                                <?= amanCetak(
                                    $data["nik_balita"]
                                ); ?>
                            </td>

                            <td>
                                <?= amanCetak(
                                    $data["nama_puskesmas"]
                                ); ?>
                            </td>

                            <td class="text-center">
                                <?= amanCetak(
                                    $data["jenis_kelamin"]
                                ); ?>
                            </td>

                            <td class="text-center">
                                <?= amanCetak(
                                    $data["umur_bulan"]
                                ); ?>
                                bulan
                            </td>

                            <td class="text-center">
                                <?= amanCetak(
                                    $data["berat_badan"]
                                ); ?>
                                kg
                            </td>

                            <td class="text-center">
                                <?= amanCetak(
                                    $data[
                                        "tinggi_panjang_badan"
                                    ]
                                ); ?>
                                cm
                            </td>

                            <td class="text-center">
                                <?= amanCetak(
                                    $data["status_gizi"]
                                ); ?>
                            </td>

                            <td class="text-center">

                                <span
                                    class="status-label <?= kelasStatusCetak(
                                        $data[
                                            "status_stunting"
                                        ]
                                    ); ?>"
                                >
                                    <?= amanCetak(
                                        $data[
                                            "status_stunting"
                                        ]
                                    ); ?>
                                </span>

                            </td>

                            <td class="text-center">
                                <?= amanCetak(
                                    $data[
                                        "status_verifikasi"
                                    ]
                                    ?? "Belum diverifikasi"
                                ); ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td
                            colspan="12"
                            class="empty-row"
                        >
                            Tidak ada hasil deteksi pada periode
                            dan Puskesmas yang dipilih.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p class="report-note">
            Keterangan: laporan ini dihasilkan berdasarkan data
            hasil deteksi yang tersimpan di dalam sistem sesuai
            periode dan filter Puskesmas yang dipilih.
        </p>

        <section class="signature-grid">

            <div class="signature-box">

                <p>
                    Petugas Gizi
                </p>

                <div class="signature-space"></div>

                <p class="signature-name">
                    ........................................
                </p>

            </div>

            <div class="signature-box">

                <p>
                    Mengetahui,
                    <br>
                    Kepala Puskesmas
                </p>

                <div class="signature-space"></div>

                <p class="signature-name">
                    ........................................
                </p>

            </div>

        </section>

        <footer class="report-footer">
            <?= amanCetak(
                $footerInstansi
            ); ?>
            · Sistem Deteksi dan Pemantauan Stunting
        </footer>

    </main>

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

</body>

</html>