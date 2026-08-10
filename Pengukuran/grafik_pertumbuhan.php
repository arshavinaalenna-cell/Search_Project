<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman =
    "Grafik Pertumbuhan Balita | Sistem Deteksi Stunting";

$roleAktif =
    $_SESSION["role"] ?? "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idBalita = filter_input(
    INPUT_GET,
    "id_balita",
    FILTER_VALIDATE_INT
);

if (!$idBalita || $idBalita < 1) {
    header(
        "Location: data_pengukuran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil identitas balita
|--------------------------------------------------------------------------
*/

$stmtBalita = mysqli_prepare(
    $conn,
    "SELECT
        b.id_balita,
        b.id_user,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,
        b.tanggal_lahir,
        b.nama_ibu,
        b.nama_posyandu,
        ps.nama_puskesmas
     FROM balita AS b
     LEFT JOIN puskesmas AS ps
        ON b.id_puskesmas = ps.id_puskesmas
     WHERE b.id_balita = ?
     LIMIT 1"
);

if (!$stmtBalita) {
    die(
        "Gagal menyiapkan identitas balita: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtBalita,
    "i",
    $idBalita
);

mysqli_stmt_execute(
    $stmtBalita
);

$hasilBalita =
    mysqli_stmt_get_result(
        $stmtBalita
    );

$balita =
    mysqli_fetch_assoc(
        $hasilBalita
    );

mysqli_stmt_close(
    $stmtBalita
);

if (!$balita) {
    header(
        "Location: data_pengukuran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Orang Tua hanya boleh membuka grafik anak sendiri
|--------------------------------------------------------------------------
*/

if (
    $roleAktif === "orang_tua"
    && (int) $balita["id_user"] !==
        $idUserAktif
) {
    http_response_code(403);

    echo "
        <h2>Akses Ditolak</h2>
        <p>Kamu hanya dapat melihat grafik pertumbuhan anakmu sendiri.</p>
        <a href='data_pengukuran.php'>Kembali</a>
    ";

    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil seluruh riwayat pengukuran balita
|--------------------------------------------------------------------------
*/

$stmtRiwayat = mysqli_prepare(
    $conn,
    "SELECT
        id_pengukuran,
        tanggal_pengukuran,
        umur_bulan,
        berat_badan,
        tinggi_panjang_badan,
        lingkar_kepala,
        lila
     FROM pengukuran_antropometri
     WHERE id_balita = ?
     ORDER BY
        tanggal_pengukuran ASC,
        id_pengukuran ASC"
);

if (!$stmtRiwayat) {
    die(
        "Gagal menyiapkan riwayat pertumbuhan: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtRiwayat,
    "i",
    $idBalita
);

mysqli_stmt_execute(
    $stmtRiwayat
);

$hasilRiwayat =
    mysqli_stmt_get_result(
        $stmtRiwayat
    );

$riwayat = [];

while (
    $data =
        mysqli_fetch_assoc(
            $hasilRiwayat
        )
) {
    $riwayat[] = $data;
}

mysqli_stmt_close(
    $stmtRiwayat
);

/*
|--------------------------------------------------------------------------
| Fungsi output
|--------------------------------------------------------------------------
*/

function amanGrafik($nilai): string
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

function formatTanggalGrafik($tanggal): string
{
    if (
        empty($tanggal)
        || $tanggal === "0000-00-00"
    ) {
        return "-";
    }

    $waktu = strtotime(
        (string) $tanggal
    );

    if ($waktu === false) {
        return amanGrafik(
            $tanggal
        );
    }

    return date(
        "d-m-Y",
        $waktu
    );
}

/*
|--------------------------------------------------------------------------
| Menyiapkan data grafik dan ringkasan
|--------------------------------------------------------------------------
*/

$labelGrafik = [];
$dataBeratBadan = [];
$dataTinggiBadan = [];

foreach ($riwayat as $item) {
    $umur =
        (int) (
            $item["umur_bulan"] ?? 0
        );

    $tanggal =
        formatTanggalGrafik(
            $item["tanggal_pengukuran"]
        );

    $labelGrafik[] =
        $umur
        . " bln"
        . " • "
        . $tanggal;

    $dataBeratBadan[] =
        $item["berat_badan"] !== null
        && $item["berat_badan"] !== ""
            ? (float) $item["berat_badan"]
            : null;

    $dataTinggiBadan[] =
        $item[
            "tinggi_panjang_badan"
        ] !== null
        && $item[
            "tinggi_panjang_badan"
        ] !== ""
            ? (float) $item[
                "tinggi_panjang_badan"
            ]
            : null;
}

$totalPengukuran =
    count($riwayat);

$pengukuranTerakhir =
    $totalPengukuran > 0
        ? $riwayat[
            $totalPengukuran - 1
        ]
        : null;

$umurTerakhir =
    $pengukuranTerakhir[
        "umur_bulan"
    ] ?? null;

$beratTerakhir =
    $pengukuranTerakhir[
        "berat_badan"
    ] ?? null;

$tinggiTerakhir =
    $pengukuranTerakhir[
        "tinggi_panjang_badan"
    ] ?? null;

$tanggalTerakhir =
    $pengukuranTerakhir[
        "tanggal_pengukuran"
    ] ?? null;

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Grafik Pertumbuhan Balita
                    </h4>

                    <small class="text-muted">
                        Tren berat badan dan tinggi/panjang badan
                        berdasarkan riwayat pengukuran balita.
                    </small>

                </div>

                <a
                    href="data_pengukuran.php"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

            </div>

            <div class="card-body">

                <div class="row g-3 mb-4">

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Balita
                            </span>

                            <div class="detail-value">
                                <i
                                    class="bi
                                    bi-person-heart me-1"
                                ></i>

                                <?= amanGrafik(
                                    $balita[
                                        "nama_balita"
                                    ]
                                ); ?>
                            </div>

                            <small class="text-muted">
                                NIK:
                                <?= amanGrafik(
                                    $balita[
                                        "nik_balita"
                                    ]
                                ); ?>
                            </small>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Pengukuran Terakhir
                            </span>

                            <div class="detail-value">
                                <?= formatTanggalGrafik(
                                    $tanggalTerakhir
                                ); ?>
                            </div>

                            <small class="text-muted">
                                <?= $umurTerakhir !== null
                                    ? (int) $umurTerakhir
                                        . " bulan"
                                    : "-"; ?>
                            </small>

                        </div>

                    </div>

                    <div class="col-6 col-lg-2">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                BB Terakhir
                            </span>

                            <div class="detail-value">
                                <?= $beratTerakhir !== null
                                    && $beratTerakhir !== ""
                                    ? amanGrafik(
                                        $beratTerakhir
                                    ) . " kg"
                                    : "-"; ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-6 col-lg-2">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                TB/PB Terakhir
                            </span>

                            <div class="detail-value">
                                <?= $tinggiTerakhir !== null
                                    && $tinggiTerakhir !== ""
                                    ? amanGrafik(
                                        $tinggiTerakhir
                                    ) . " cm"
                                    : "-"; ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-2">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Total Riwayat
                            </span>

                            <div class="detail-value">
                                <?= $totalPengukuran; ?>
                                pengukuran
                            </div>

                        </div>

                    </div>

                </div>

                <?php if ($totalPengukuran > 0): ?>

                    <div class="row g-4">

                        <div class="col-12">

                            <div class="detail-item">

                                <div
                                    class="d-flex flex-wrap
                                    justify-content-between
                                    align-items-center
                                    gap-2 mb-3"
                                >

                                    <div>
                                        <span
                                            class="detail-label"
                                        >
                                            Tren Berat Badan
                                        </span>

                                        <small
                                            class="text-muted
                                            d-block"
                                        >
                                            Perubahan berat badan
                                            dari setiap pengukuran.
                                        </small>
                                    </div>

                                    <span
                                        class="badge
                                        badge-info"
                                    >
                                        kg
                                    </span>

                                </div>

                                <div
                                    style="
                                        position: relative;
                                        height: 330px;
                                    "
                                >
                                    <canvas
                                        id="grafikBeratBadan"
                                        aria-label="Grafik tren berat badan balita"
                                    ></canvas>
                                </div>

                            </div>

                        </div>

                        <div class="col-12">

                            <div class="detail-item">

                                <div
                                    class="d-flex flex-wrap
                                    justify-content-between
                                    align-items-center
                                    gap-2 mb-3"
                                >

                                    <div>
                                        <span
                                            class="detail-label"
                                        >
                                            Tren Tinggi /
                                            Panjang Badan
                                        </span>

                                        <small
                                            class="text-muted
                                            d-block"
                                        >
                                            Perubahan tinggi atau
                                            panjang badan dari
                                            setiap pengukuran.
                                        </small>
                                    </div>

                                    <span
                                        class="badge
                                        badge-info"
                                    >
                                        cm
                                    </span>

                                </div>

                                <div
                                    style="
                                        position: relative;
                                        height: 330px;
                                    "
                                >
                                    <canvas
                                        id="grafikTinggiBadan"
                                        aria-label="Grafik tren tinggi atau panjang badan balita"
                                    ></canvas>
                                </div>

                            </div>

                        </div>

                    </div>

                    <hr>

                    <div class="table-responsive">

                        <table
                            class="table
                            table-hover
                            align-middle"
                        >

                            <thead>

                                <tr>

                                    <th
                                        class="text-center"
                                    >
                                        No
                                    </th>

                                    <th
                                        class="text-center"
                                    >
                                        Tanggal
                                    </th>

                                    <th
                                        class="text-center"
                                    >
                                        Umur
                                    </th>

                                    <th
                                        class="text-center"
                                    >
                                        BB
                                    </th>

                                    <th
                                        class="text-center"
                                    >
                                        TB/PB
                                    </th>

                                    <th
                                        class="text-center"
                                    >
                                        Lingkar Kepala
                                    </th>

                                    <th
                                        class="text-center"
                                    >
                                        LiLA
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach (
                                    $riwayat
                                    as $nomor => $item
                                ): ?>

                                    <tr>

                                        <td
                                            class="text-center"
                                        >
                                            <?= $nomor + 1; ?>
                                        </td>

                                        <td
                                            class="text-center"
                                        >
                                            <?= formatTanggalGrafik(
                                                $item[
                                                    "tanggal_pengukuran"
                                                ]
                                            ); ?>
                                        </td>

                                        <td
                                            class="text-center"
                                        >
                                            <?= amanGrafik(
                                                $item[
                                                    "umur_bulan"
                                                ]
                                            ); ?>
                                            bulan
                                        </td>

                                        <td
                                            class="text-center"
                                        >
                                            <?= amanGrafik(
                                                $item[
                                                    "berat_badan"
                                                ]
                                            ); ?>
                                            kg
                                        </td>

                                        <td
                                            class="text-center"
                                        >
                                            <?= amanGrafik(
                                                $item[
                                                    "tinggi_panjang_badan"
                                                ]
                                            ); ?>
                                            cm
                                        </td>

                                        <td
                                            class="text-center"
                                        >
                                            <?= amanGrafik(
                                                $item[
                                                    "lingkar_kepala"
                                                ]
                                            ); ?>
                                            cm
                                        </td>

                                        <td
                                            class="text-center"
                                        >
                                            <?= amanGrafik(
                                                $item["lila"]
                                            ); ?>
                                            cm
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            <i
                                class="bi
                                bi-graph-up"
                            ></i>
                        </div>

                        <h3>
                            Belum ada data pertumbuhan
                        </h3>

                        <p>
                            Grafik akan tersedia setelah
                            balita memiliki data pengukuran
                            antropometri.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php if ($totalPengukuran > 0): ?>

    <script
        type="application/json"
        id="dataGrafikPertumbuhan"
    ><?= json_encode(
        [
            "labels" =>
                $labelGrafik,

            "beratBadan" =>
                $dataBeratBadan,

            "tinggiBadan" =>
                $dataTinggiBadan
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
        src="../assets/js/grafik_pertumbuhan.js"
    ></script>

<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>