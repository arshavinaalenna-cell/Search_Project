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

/*
|--------------------------------------------------------------------------
| Fungsi bantuan
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
| Mengambil daftar balita yang boleh dilihat
|--------------------------------------------------------------------------
|
| Orang Tua:
| hanya balita yang terhubung melalui balita.id_user.
|
| Role pelayanan/monitoring:
| dapat memilih balita dari daftar yang tersedia.
|
*/

$daftarBalita = [];

if ($roleAktif === "orang_tua") {

    $stmtDaftar = mysqli_prepare(
        $conn,
        "SELECT
            b.id_balita,
            b.nama_balita,
            b.nik_balita
         FROM balita AS b
         WHERE b.id_user = ?
         ORDER BY
            (
                SELECT COUNT(*)
                FROM pengukuran_antropometri AS pa
                WHERE pa.id_balita = b.id_balita
            ) DESC,
            b.nama_balita ASC"
    );

    if (!$stmtDaftar) {
        die(
            "Gagal menyiapkan daftar anak: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtDaftar,
        "i",
        $idUserAktif
    );

    if (!mysqli_stmt_execute($stmtDaftar)) {
        die(
            "Gagal mengambil daftar anak: "
            . mysqli_stmt_error($stmtDaftar)
        );
    }

    $hasilDaftar =
        mysqli_stmt_get_result(
            $stmtDaftar
        );

    while (
        $item =
            mysqli_fetch_assoc(
                $hasilDaftar
            )
    ) {
        $daftarBalita[] = $item;
    }

    mysqli_stmt_close(
        $stmtDaftar
    );

} else {

    $queryDaftar = mysqli_query(
        $conn,
        "SELECT
            b.id_balita,
            b.nama_balita,
            b.nik_balita
         FROM balita AS b
         ORDER BY
            (
                SELECT COUNT(*)
                FROM pengukuran_antropometri AS pa
                WHERE pa.id_balita = b.id_balita
            ) DESC,
            b.nama_balita ASC"
    );

    if (!$queryDaftar) {
        die(
            "Gagal mengambil daftar balita: "
            . mysqli_error($conn)
        );
    }

    while (
        $item =
            mysqli_fetch_assoc(
                $queryDaftar
            )
    ) {
        $daftarBalita[] = $item;
    }
}

/*
|--------------------------------------------------------------------------
| Menentukan balita aktif
|--------------------------------------------------------------------------
|
| Jika halaman dibuka dari sidebar tanpa id_balita,
| sistem otomatis memilih balita pertama yang dapat diakses.
|
*/

$idBalita = filter_input(
    INPUT_GET,
    "id_balita",
    FILTER_VALIDATE_INT
);

if (
    $idBalita === false
    || $idBalita === null
    || $idBalita < 1
) {
    $idBalita = 0;
}

$idBalita = (int) $idBalita;

$idBalitaDiizinkan = [];

foreach (
    $daftarBalita
    as $itemBalita
) {
    $idBalitaDiizinkan[] =
        (int) $itemBalita[
            "id_balita"
        ];
}

if (
    $idBalita > 0
    && !in_array(
        $idBalita,
        $idBalitaDiizinkan,
        true
    )
) {
    $idBalita = 0;
}

if (
    $idBalita === 0
    && count($daftarBalita) > 0
) {
    $idBalita =
        (int) $daftarBalita[0][
            "id_balita"
        ];
}

/*
|--------------------------------------------------------------------------
| Nilai default halaman
|--------------------------------------------------------------------------
*/

$balita = null;
$riwayat = [];

$labelGrafik = [];
$dataBeratBadan = [];
$dataTinggiBadan = [];

$totalPengukuran = 0;

$pengukuranTerakhir = null;
$umurTerakhir = null;
$beratTerakhir = null;
$tinggiTerakhir = null;
$tanggalTerakhir = null;

/*
|--------------------------------------------------------------------------
| Mengambil identitas balita aktif
|--------------------------------------------------------------------------
*/

if ($idBalita > 0) {

    $stmtBalita = mysqli_prepare(
        $conn,
        "SELECT
            b.id_balita,
            b.id_user,
            b.nama_balita,
            b.nik_balita,
            b.jenis_kelamin,
            b.tanggal_lahir,
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

    if (!mysqli_stmt_execute($stmtBalita)) {
        die(
            "Gagal mengambil identitas balita: "
            . mysqli_stmt_error($stmtBalita)
        );
    }

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
}

/*
|--------------------------------------------------------------------------
| Pemeriksaan kepemilikan Orang Tua
|--------------------------------------------------------------------------
*/

if (
    $balita
    && $roleAktif === "orang_tua"
    && (int) $balita["id_user"] !==
        $idUserAktif
) {
    http_response_code(403);

    die(
        "Akses ditolak. Anda hanya dapat melihat grafik pertumbuhan anak sendiri."
    );
}

/*
|--------------------------------------------------------------------------
| Mengambil riwayat pengukuran balita aktif
|--------------------------------------------------------------------------
*/

if ($balita) {

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

    if (!mysqli_stmt_execute($stmtRiwayat)) {
        die(
            "Gagal mengambil riwayat pertumbuhan: "
            . mysqli_stmt_error($stmtRiwayat)
        );
    }

    $hasilRiwayat =
        mysqli_stmt_get_result(
            $stmtRiwayat
        );

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
    | Menyiapkan data grafik
    |--------------------------------------------------------------------------
    */

    foreach (
        $riwayat
        as $item
    ) {
        $umur =
            (int) (
                $item[
                    "umur_bulan"
                ] ?? 0
            );

        $tanggal =
            formatTanggalGrafik(
                $item[
                    "tanggal_pengukuran"
                ]
            );

        $labelGrafik[] =
            $umur
            . " bln"
            . " • "
            . $tanggal;

        $dataBeratBadan[] =
            $item["berat_badan"] !== null
            && $item["berat_badan"] !== ""
                ? (float) $item[
                    "berat_badan"
                ]
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
}

/*
|--------------------------------------------------------------------------
| Template aplikasi
|--------------------------------------------------------------------------
*/

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
                        Pantau tren berat badan dan tinggi/panjang
                        badan berdasarkan seluruh riwayat pengukuran.
                    </small>

                </div>

                <?php if ($roleAktif === "kader"): ?>

                    <a
                        href="data_pengukuran.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Riwayat Pengukuran
                    </a>

                <?php endif; ?>

            </div>

            <div class="card-body">

                <?php if (
                    count($daftarBalita) > 0
                ): ?>

                    <?php if (
                        count($daftarBalita) > 1
                        || $roleAktif !== "orang_tua"
                    ): ?>

                        <form
                            method="GET"
                            action="grafik_pertumbuhan.php"
                            class="row g-3 align-items-end mb-4"
                        >

                            <div class="col-12 col-lg-9">

                                <label
                                    for="id_balita"
                                    class="form-label"
                                >
                                    Pilih Balita
                                </label>

                                <select
                                    name="id_balita"
                                    id="id_balita"
                                    class="form-select"
                                    onchange="this.form.submit();"
                                >

                                    <?php foreach (
                                        $daftarBalita
                                        as $itemBalita
                                    ): ?>

                                        <?php
                                        $idPilihan =
                                            (int) $itemBalita[
                                                "id_balita"
                                            ];
                                        ?>

                                        <option
                                            value="<?= $idPilihan; ?>"
                                            <?= $idPilihan ===
                                                $idBalita
                                                ? "selected"
                                                : ""; ?>
                                        >
                                            <?= amanGrafik(
                                                $itemBalita[
                                                    "nama_balita"
                                                ]
                                            ); ?>

                                            <?php if (
                                                !empty(
                                                    $itemBalita[
                                                        "nik_balita"
                                                    ]
                                                )
                                            ): ?>
                                                —
                                                <?= amanGrafik(
                                                    $itemBalita[
                                                        "nik_balita"
                                                    ]
                                                ); ?>
                                            <?php endif; ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-12 col-lg-3">

                                <button
                                    type="submit"
                                    class="btn btn-primary w-100"
                                >
                                    <i class="bi bi-graph-up-arrow"></i>
                                    Tampilkan Grafik
                                </button>

                            </div>

                        </form>

                    <?php endif; ?>

                    <?php if ($balita): ?>

                        <div class="row g-3 mb-4">

                            <div class="col-12 col-lg-4">

                                <div class="detail-item h-100">

                                    <span class="detail-label">
                                        Balita
                                    </span>

                                    <div class="detail-value">
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

                            <div class="col-12 col-md-6 col-lg-2">

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

                            <div class="col-12 col-md-6 col-lg-2">

                                <div class="detail-item h-100">

                                    <span class="detail-label">
                                        Total Riwayat
                                    </span>

                                    <div class="detail-value">
                                        <?= $totalPengukuran; ?>
                                    </div>

                                    <small class="text-muted">
                                        pengukuran
                                    </small>

                                </div>

                            </div>

                        </div>

                        <?php if (
                            $totalPengukuran > 0
                        ): ?>

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

                                                <span class="detail-label">
                                                    Tren Berat Badan
                                                </span>

                                                <small
                                                    class="text-muted d-block"
                                                >
                                                    Perubahan berat badan
                                                    dari setiap pengukuran.
                                                </small>

                                            </div>

                                            <span class="badge badge-info">
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

                                                <span class="detail-label">
                                                    Tren Tinggi /
                                                    Panjang Badan
                                                </span>

                                                <small
                                                    class="text-muted d-block"
                                                >
                                                    Perubahan tinggi atau
                                                    panjang badan dari
                                                    setiap pengukuran.
                                                </small>

                                            </div>

                                            <span class="badge badge-info">
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

                            <div class="mt-4">

                                <div class="d-flex flex-wrap
                                    justify-content-between
                                    align-items-center gap-2 mb-3"
                                >

                                    <div>

                                        <h5 class="mb-1">
                                            Riwayat Pengukuran
                                        </h5>

                                        <small class="text-muted">
                                            Data yang membentuk grafik
                                            pertumbuhan di atas.
                                        </small>

                                    </div>

                                    <span class="badge badge-info">
                                        <?= $totalPengukuran; ?>
                                        data
                                    </span>

                                </div>

                                <div class="table-responsive">

                                    <table
                                        class="table
                                        table-hover
                                        align-middle"
                                    >

                                        <thead>

                                            <tr>

                                                <th class="text-center">
                                                    No
                                                </th>

                                                <th class="text-center">
                                                    Tanggal
                                                </th>

                                                <th class="text-center">
                                                    Umur
                                                </th>

                                                <th class="text-center">
                                                    BB
                                                </th>

                                                <th class="text-center">
                                                    TB/PB
                                                </th>

                                                <th class="text-center">
                                                    Lingkar Kepala
                                                </th>

                                                <th class="text-center">
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

                                                    <td class="text-center">
                                                        <?= $nomor + 1; ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <?= formatTanggalGrafik(
                                                            $item[
                                                                "tanggal_pengukuran"
                                                            ]
                                                        ); ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <?= amanGrafik(
                                                            $item[
                                                                "umur_bulan"
                                                            ]
                                                        ); ?>
                                                        bulan
                                                    </td>

                                                    <td class="text-center">
                                                        <?= amanGrafik(
                                                            $item[
                                                                "berat_badan"
                                                            ]
                                                        ); ?>
                                                        kg
                                                    </td>

                                                    <td class="text-center">
                                                        <?= amanGrafik(
                                                            $item[
                                                                "tinggi_panjang_badan"
                                                            ]
                                                        ); ?>
                                                        cm
                                                    </td>

                                                    <td class="text-center">
                                                        <?= amanGrafik(
                                                            $item[
                                                                "lingkar_kepala"
                                                            ]
                                                        ); ?>
                                                        cm
                                                    </td>

                                                    <td class="text-center">
                                                        <?= amanGrafik(
                                                            $item[
                                                                "lila"
                                                            ]
                                                        ); ?>
                                                        cm
                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        <?php else: ?>

                            <div class="empty-state">

                                <div class="empty-state-icon">
                                    <i class="bi bi-graph-up"></i>
                                </div>

                                <h3>
                                    Belum ada data pertumbuhan
                                </h3>

                                <p>
                                    Balita ini belum memiliki
                                    pengukuran antropometri.
                                    Grafik akan muncul setelah
                                    data pengukuran tersedia.
                                </p>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            <i class="bi bi-person-x"></i>
                        </div>

                        <h3>
                            Belum ada balita
                        </h3>

                        <p>
                            Belum ada data balita yang dapat
                            ditampilkan pada grafik pertumbuhan.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php if (
    $balita
    && $totalPengukuran > 0
): ?>

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