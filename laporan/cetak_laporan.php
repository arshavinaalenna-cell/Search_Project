<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

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
| Mengambil data laporan
|--------------------------------------------------------------------------
*/

$stmtLaporan = mysqli_prepare(
    $conn,
    "SELECT
        hd.id_deteksi,
        hd.status_gizi,
        hd.status_stunting,
        hd.tanggal_deteksi,

        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,

        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin

     FROM hasil_deteksi AS hd

     INNER JOIN pengukuran_antropometri AS pa
        ON hd.id_pengukuran = pa.id_pengukuran

     INNER JOIN balita AS b
        ON pa.id_balita = b.id_balita

     WHERE hd.tanggal_deteksi >= ?
       AND hd.tanggal_deteksi < DATE_ADD(?, INTERVAL 1 DAY)

     ORDER BY
        hd.tanggal_deteksi ASC,
        b.nama_balita ASC,
        hd.id_deteksi ASC"
);

if (!$stmtLaporan) {
    die(
        "Gagal menyiapkan cetak laporan: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtLaporan,
    "ss",
    $tanggalAwal,
    $tanggalAkhir
);

mysqli_stmt_execute($stmtLaporan);

$resultLaporan =
    mysqli_stmt_get_result($stmtLaporan);

$dataLaporan = [];

$totalDeteksi = 0;
$totalRisikoRendah = 0;
$totalRisikoSedang = 0;
$totalRisikoTinggi = 0;

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
                "sangat pendek",
                "severely stunted"
            ],
            true
        )
    ) {
        $totalRisikoTinggi++;
    }
}

mysqli_stmt_close($stmtLaporan);

$namaPencetak = $_SESSION["nama"] ?? "Pengguna";
$rolePencetak = namaRole(
    $_SESSION["role"] ?? ""
);

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
            width: 72px;
            height: 72px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 2px solid #777777;
            border-radius: 50%;

            color: #555555;

            font-size: 11px;
            font-weight: 700;
            text-align: center;
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
            .signature-grid {
                page-break-inside: avoid;
            }

        }

    </style>

</head>

<body>

    <div class="toolbar">

        <a
            href="laporan_stunting.php?tanggal_awal=<?= amanCetak(
                $tanggalAwal
            ); ?>&tanggal_akhir=<?= amanCetak(
                $tanggalAkhir
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

            <div class="report-logo">
                LOGO<br>PUSKESMAS
            </div>

            <div class="report-heading">

                <h1>
                    Puskesmas
                </h1>

                <h2>
                    Sistem Deteksi dan Pemantauan Stunting
                </h2>

                <p>
                    Laporan hasil deteksi risiko stunting balita
                </p>

            </div>

            <div></div>

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

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td
                            colspan="10"
                            class="empty-row"
                        >
                            Tidak ada hasil deteksi pada periode
                            yang dipilih.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p class="report-note">
            Keterangan: laporan ini dihasilkan berdasarkan data
            hasil deteksi yang tersimpan di dalam sistem.
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
            Sistem Deteksi dan Pemantauan Stunting
        </footer>

    </main>

</body>

</html>