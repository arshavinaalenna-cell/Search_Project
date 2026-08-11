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
| Filter khusus Dinkes
|--------------------------------------------------------------------------
|
| Hanya Dinkes yang dapat memilih Puskesmas, bulan, dan tahun.
| Bulan/tahun memfilter tanggal_pengukuran pada grafik.
|
*/

$filterPuskesmas = 0;
$filterBulan = 0;
$filterTahun = 0;

$daftarPuskesmasFilter = [];
$daftarTahunFilter = [];

if ($roleAktif === "dinkes") {

    $filterPuskesmas =
        filter_input(
            INPUT_GET,
            "puskesmas",
            FILTER_VALIDATE_INT
        ) ?: 0;

    $filterBulan =
        filter_input(
            INPUT_GET,
            "bulan",
            FILTER_VALIDATE_INT
        ) ?: 0;

    $filterTahun =
        filter_input(
            INPUT_GET,
            "tahun",
            FILTER_VALIDATE_INT
        ) ?: 0;

    if (
        $filterBulan < 1
        || $filterBulan > 12
    ) {
        $filterBulan = 0;
    }

    if (
        $filterTahun < 2000
        || $filterTahun > 2100
    ) {
        $filterTahun = 0;
    }

    $queryPuskesmasFilter = mysqli_query(
        $conn,
        "SELECT
            id_puskesmas,
            nama_puskesmas
         FROM puskesmas
         ORDER BY nama_puskesmas ASC"
    );

    if ($queryPuskesmasFilter) {
        while (
            $itemPuskesmas =
                mysqli_fetch_assoc(
                    $queryPuskesmasFilter
                )
        ) {
            $daftarPuskesmasFilter[] =
                $itemPuskesmas;
        }
    }

    $queryTahunFilter = mysqli_query(
        $conn,
        "SELECT DISTINCT
            YEAR(tanggal_pengukuran) AS tahun
         FROM pengukuran_antropometri
         WHERE tanggal_pengukuran IS NOT NULL
         ORDER BY tahun DESC"
    );

    if ($queryTahunFilter) {
        while (
            $itemTahun =
                mysqli_fetch_assoc(
                    $queryTahunFilter
                )
        ) {
            if (!empty($itemTahun["tahun"])) {
                $daftarTahunFilter[] =
                    (int) $itemTahun["tahun"];
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Pembatasan wilayah Puskesmas
|--------------------------------------------------------------------------
*/

$roleTerikatPuskesmas = [
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "kepala_puskesmas"
];

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

if (
    in_array(
        $roleAktif,
        $roleTerikatPuskesmas,
        true
    )
) {

    $stmtPuskesmas = mysqli_prepare(
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

    if (!$stmtPuskesmas) {
        die(
            "Gagal memeriksa Puskesmas pengguna: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtPuskesmas,
        "i",
        $idUserAktif
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

    mysqli_stmt_close(
        $stmtPuskesmas
    );

    if (
        !$dataPuskesmas
        || empty(
            $dataPuskesmas["id_puskesmas"]
        )
        || empty(
            $dataPuskesmas["nama_puskesmas"]
        )
    ) {
        $puskesmasBelumTerhubung = true;
    } else {
        $idPuskesmasAktif =
            (int) $dataPuskesmas[
                "id_puskesmas"
            ];

        $namaPuskesmasAktif =
            trim(
                (string) $dataPuskesmas[
                    "nama_puskesmas"
                ]
            );
    }
}

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

    mysqli_stmt_bind_param(
        $stmtDaftar,
        "i",
        $idUserAktif
    );

    mysqli_stmt_execute(
        $stmtDaftar
    );

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

} elseif (
    in_array(
        $roleAktif,
        $roleTerikatPuskesmas,
        true
    )
) {

    if (!$puskesmasBelumTerhubung) {

        $stmtDaftar = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.nama_balita,
                b.nik_balita
             FROM balita AS b
             WHERE b.id_puskesmas = ?
             ORDER BY
                (
                    SELECT COUNT(*)
                    FROM pengukuran_antropometri AS pa
                    WHERE pa.id_balita = b.id_balita
                ) DESC,
                b.nama_balita ASC"
        );

        mysqli_stmt_bind_param(
            $stmtDaftar,
            "i",
            $idPuskesmasAktif
        );

        mysqli_stmt_execute(
            $stmtDaftar
        );

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
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | Dinkes
    |--------------------------------------------------------------------------
    |
    | Jika Puskesmas dipilih, pilihan balita hanya berasal dari
    | Puskesmas tersebut. Jika "Semua Puskesmas", semua balita tersedia.
    |
    */

    $stmtDaftar = mysqli_prepare(
        $conn,
        "SELECT
            b.id_balita,
            b.nama_balita,
            b.nik_balita
         FROM balita AS b
         WHERE
            (? = 0 OR b.id_puskesmas = ?)
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
            "Gagal menyiapkan daftar balita Dinkes: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtDaftar,
        "ii",
        $filterPuskesmas,
        $filterPuskesmas
    );

    mysqli_stmt_execute(
        $stmtDaftar
    );

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

    if ($roleAktif === "dinkes") {

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
               AND (
                    ? = 0
                    OR MONTH(tanggal_pengukuran) = ?
               )
               AND (
                    ? = 0
                    OR YEAR(tanggal_pengukuran) = ?
               )
             ORDER BY
                tanggal_pengukuran ASC,
                id_pengukuran ASC"
        );

        if (!$stmtRiwayat) {
            die(
                "Gagal menyiapkan riwayat pertumbuhan Dinkes: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmtRiwayat,
            "iiiii",
            $idBalita,
            $filterBulan,
            $filterBulan,
            $filterTahun,
            $filterTahun
        );

    } else {

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
    }

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
                    $puskesmasBelumTerhubung
                    && in_array(
                        $roleAktif,
                        $roleTerikatPuskesmas,
                        true
                    )
                ): ?>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Akun ini belum terhubung dengan Puskesmas.
                    </div>

                <?php elseif (
                    in_array(
                        $roleAktif,
                        $roleTerikatPuskesmas,
                        true
                    )
                ): ?>

                    <div
                        class="mb-4 p-3 rounded border"
                        style="background: #f8fafc;"
                    >
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-hospital"></i>
                            <strong>
                                <?= amanGrafik(
                                    $namaPuskesmasAktif
                                ); ?>
                            </strong>
                        </div>
                        <small class="text-muted">
                            Balita yang tersedia hanya berasal dari
                            Puskesmas akun yang sedang login.
                        </small>
                    </div>

                <?php endif; ?>

                <?php if (
                    count($daftarBalita) > 0
                ): ?>

                    <?php if (
                        count($daftarBalita) > 1
                        || $roleAktif !== "orang_tua"
                    ): ?>

                        <?php if ($roleAktif === "dinkes"): ?>

                            <form
                                method="GET"
                                action="grafik_pertumbuhan.php"
                                class="row g-3 align-items-end mb-4"
                            >

                                <div class="col-12 col-md-4 col-lg-4">

                                    <label
                                        for="puskesmas"
                                        class="form-label"
                                    >
                                        Puskesmas
                                    </label>

                                    <select
                                        id="puskesmas"
                                        name="puskesmas"
                                        class="form-select"
                                    >

                                        <option value="0">
                                            Semua Puskesmas
                                        </option>

                                        <?php foreach (
                                            $daftarPuskesmasFilter
                                            as $puskesmasFilter
                                        ): ?>

                                            <option
                                                value="<?= (int)
                                                    $puskesmasFilter[
                                                        "id_puskesmas"
                                                    ]; ?>"
                                                <?= (
                                                    $filterPuskesmas
                                                    ===
                                                    (int) $puskesmasFilter[
                                                        "id_puskesmas"
                                                    ]
                                                )
                                                    ? "selected"
                                                    : ""; ?>
                                            >
                                                <?= amanGrafik(
                                                    $puskesmasFilter[
                                                        "nama_puskesmas"
                                                    ]
                                                ); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="col-6 col-md-4 col-lg-2">

                                    <label
                                        for="bulan"
                                        class="form-label"
                                    >
                                        Bulan
                                    </label>

                                    <select
                                        id="bulan"
                                        name="bulan"
                                        class="form-select"
                                    >

                                        <option value="0">
                                            Semua Bulan
                                        </option>

                                        <?php
                                        $namaBulanGrafik = [
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
                                        ?>

                                        <?php foreach (
                                            $namaBulanGrafik
                                            as $nomorBulan => $labelBulan
                                        ): ?>

                                            <option
                                                value="<?= $nomorBulan; ?>"
                                                <?= $filterBulan === $nomorBulan
                                                    ? "selected"
                                                    : ""; ?>
                                            >
                                                <?= $labelBulan; ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="col-6 col-md-4 col-lg-2">

                                    <label
                                        for="tahun"
                                        class="form-label"
                                    >
                                        Tahun
                                    </label>

                                    <select
                                        id="tahun"
                                        name="tahun"
                                        class="form-select"
                                    >

                                        <option value="0">
                                            Semua Tahun
                                        </option>

                                        <?php foreach (
                                            $daftarTahunFilter
                                            as $tahunFilter
                                        ): ?>

                                            <option
                                                value="<?= $tahunFilter; ?>"
                                                <?= $filterTahun === $tahunFilter
                                                    ? "selected"
                                                    : ""; ?>
                                            >
                                                <?= $tahunFilter; ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            <div class="col-12 col-lg-6">

                                <label
                                    for="id_balita"
                                    class="form-label"
                                >
                                    Pilih Balita
                                </label>

                                <input
                                    type="hidden"
                                    name="id_balita"
                                    id="id_balita"
                                    value="<?= (int) $idBalita; ?>"
                                >

                                <?php
                                $labelBalitaTerpilih =
                                    "-- Pilih Balita --";

                                foreach (
                                    $daftarBalita
                                    as $balitaPilihan
                                ) {
                                    if (
                                        (int) $balitaPilihan["id_balita"]
                                        === $idBalita
                                    ) {
                                        $labelBalitaTerpilih =
                                            $balitaPilihan["nama_balita"];

                                        if (
                                            !empty(
                                                $balitaPilihan["nik_balita"]
                                            )
                                        ) {
                                            $labelBalitaTerpilih .=
                                                " — "
                                                . $balitaPilihan["nik_balita"];
                                        }

                                        break;
                                    }
                                }
                                ?>

                                <div
                                    class="grafik-balita-search"
                                    id="grafikBalitaSearch"
                                >

                                    <button
                                        type="button"
                                        class="form-select text-start grafik-balita-trigger"
                                        id="grafikBalitaTrigger"
                                    >
                                        <span id="grafikBalitaSelected">
                                            <?= amanGrafik(
                                                $labelBalitaTerpilih
                                            ); ?>
                                        </span>
                                    </button>

                                    <div
                                        class="grafik-balita-panel"
                                        id="grafikBalitaPanel"
                                        hidden
                                    >

                                        <div class="p-2 border-bottom">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="grafikBalitaInput"
                                                    placeholder="Cari nama atau NIK balita..."
                                                    autocomplete="off"
                                                >
                                            </div>
                                        </div>

                                        <div
                                            class="grafik-balita-list"
                                            id="grafikBalitaList"
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

                                                $labelPilihan =
                                                    $itemBalita[
                                                        "nama_balita"
                                                    ];

                                                if (
                                                    !empty(
                                                        $itemBalita[
                                                            "nik_balita"
                                                        ]
                                                    )
                                                ) {
                                                    $labelPilihan .=
                                                        " — "
                                                        . $itemBalita[
                                                            "nik_balita"
                                                        ];
                                                }
                                                ?>

                                                <button
                                                    type="button"
                                                    class="grafik-balita-option"
                                                    data-value="<?= $idPilihan; ?>"
                                                    data-label="<?= amanGrafik(
                                                        $labelPilihan
                                                    ); ?>"
                                                    data-search="<?= amanGrafik(
                                                        strtolower(
                                                            $labelPilihan
                                                        )
                                                    ); ?>"
                                                >
                                                    <?= amanGrafik(
                                                        $labelPilihan
                                                    ); ?>
                                                </button>

                                            <?php endforeach; ?>

                                            <div
                                                class="grafik-balita-empty"
                                                id="grafikBalitaEmpty"
                                                hidden
                                            >
                                                Balita tidak ditemukan.
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>



                                <div class="col-6 col-lg-3">

                                    <button
                                        type="submit"
                                        class="btn btn-primary w-100"
                                    >
                                        <i class="bi bi-funnel"></i>
                                        Tampilkan Grafik
                                    </button>

                                </div>

                                <div class="col-6 col-lg-3">

                                    <a
                                        href="grafik_pertumbuhan.php"
                                        class="btn btn-light w-100"
                                    >
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                        Reset Filter
                                    </a>

                                </div>

                            </form>

                        <?php else: ?>

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

                                <input
                                    type="hidden"
                                    name="id_balita"
                                    id="id_balita"
                                    value="<?= (int) $idBalita; ?>"
                                >

                                <?php
                                $labelBalitaTerpilih =
                                    "-- Pilih Balita --";

                                foreach (
                                    $daftarBalita
                                    as $balitaPilihan
                                ) {
                                    if (
                                        (int) $balitaPilihan["id_balita"]
                                        === $idBalita
                                    ) {
                                        $labelBalitaTerpilih =
                                            $balitaPilihan["nama_balita"];

                                        if (
                                            !empty(
                                                $balitaPilihan["nik_balita"]
                                            )
                                        ) {
                                            $labelBalitaTerpilih .=
                                                " — "
                                                . $balitaPilihan["nik_balita"];
                                        }

                                        break;
                                    }
                                }
                                ?>

                                <div
                                    class="grafik-balita-search"
                                    id="grafikBalitaSearch"
                                >

                                    <button
                                        type="button"
                                        class="form-select text-start grafik-balita-trigger"
                                        id="grafikBalitaTrigger"
                                    >
                                        <span id="grafikBalitaSelected">
                                            <?= amanGrafik(
                                                $labelBalitaTerpilih
                                            ); ?>
                                        </span>
                                    </button>

                                    <div
                                        class="grafik-balita-panel"
                                        id="grafikBalitaPanel"
                                        hidden
                                    >

                                        <div class="p-2 border-bottom">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="grafikBalitaInput"
                                                    placeholder="Cari nama atau NIK balita..."
                                                    autocomplete="off"
                                                >
                                            </div>
                                        </div>

                                        <div
                                            class="grafik-balita-list"
                                            id="grafikBalitaList"
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

                                                $labelPilihan =
                                                    $itemBalita[
                                                        "nama_balita"
                                                    ];

                                                if (
                                                    !empty(
                                                        $itemBalita[
                                                            "nik_balita"
                                                        ]
                                                    )
                                                ) {
                                                    $labelPilihan .=
                                                        " — "
                                                        . $itemBalita[
                                                            "nik_balita"
                                                        ];
                                                }
                                                ?>

                                                <button
                                                    type="button"
                                                    class="grafik-balita-option"
                                                    data-value="<?= $idPilihan; ?>"
                                                    data-label="<?= amanGrafik(
                                                        $labelPilihan
                                                    ); ?>"
                                                    data-search="<?= amanGrafik(
                                                        strtolower(
                                                            $labelPilihan
                                                        )
                                                    ); ?>"
                                                >
                                                    <?= amanGrafik(
                                                        $labelPilihan
                                                    ); ?>
                                                </button>

                                            <?php endforeach; ?>

                                            <div
                                                class="grafik-balita-empty"
                                                id="grafikBalitaEmpty"
                                                hidden
                                            >
                                                Balita tidak ditemukan.
                                            </div>

                                        </div>

                                    </div>

                                </div>

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


<style>
.grafik-balita-search { position: relative; }
.grafik-balita-trigger { min-height: 46px; }
.grafik-balita-panel {
    position: absolute;
    z-index: 2000;
    left: 0;
    right: 0;
    top: calc(100% + 6px);
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    box-shadow: 0 14px 32px rgba(30, 41, 59, .16);
    overflow: hidden;
}
.grafik-balita-panel[hidden] { display: none !important; }
.grafik-balita-list {
    max-height: 260px;
    overflow-y: auto;
    padding: 6px;
}
.grafik-balita-option {
    display: block;
    width: 100%;
    padding: 10px 12px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #334155;
    text-align: left;
    cursor: pointer;
}
.grafik-balita-option:hover,
.grafik-balita-option:focus {
    background: #f4f7fb;
    outline: none;
}
.grafik-balita-option.is-selected {
    background: #eef4ff;
    font-weight: 700;
}
.grafik-balita-empty {
    padding: 12px;
    color: #8a96a6;
    text-align: center;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.getElementById("grafikBalitaSearch");
    const trigger = document.getElementById("grafikBalitaTrigger");
    const panel = document.getElementById("grafikBalitaPanel");
    const searchInput = document.getElementById("grafikBalitaInput");
    const hiddenBalita = document.getElementById("id_balita");
    const selectedText = document.getElementById("grafikBalitaSelected");
    const emptyState = document.getElementById("grafikBalitaEmpty");

    if (!wrapper || !trigger || !panel || !searchInput || !hiddenBalita || !selectedText) {
        return;
    }

    const options = Array.from(
        wrapper.querySelectorAll(".grafik-balita-option")
    );

    function normalisasi(teks) {
        return String(teks || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function bukaPanel() {
        panel.hidden = false;
        searchInput.value = "";
        options.forEach(function (option) {
            option.hidden = false;
        });
        if (emptyState) {
            emptyState.hidden = true;
        }
        setTimeout(function () {
            searchInput.focus();
        }, 0);
    }

    function tutupPanel() {
        panel.hidden = true;
    }

    trigger.addEventListener("click", function () {
        if (panel.hidden) {
            bukaPanel();
        } else {
            tutupPanel();
        }
    });

    searchInput.addEventListener("input", function () {
        const keyword = normalisasi(searchInput.value);
        let tampil = 0;

        options.forEach(function (option) {
            const cocok =
                keyword === ""
                || normalisasi(option.dataset.search).includes(keyword);

            option.hidden = !cocok;

            if (cocok) {
                tampil++;
            }
        });

        if (emptyState) {
            emptyState.hidden = tampil > 0;
        }
    });

    options.forEach(function (option) {
        if (option.dataset.value === hiddenBalita.value) {
            option.classList.add("is-selected");
        }

        option.addEventListener("click", function () {
            hiddenBalita.value = option.dataset.value;
            selectedText.textContent = option.dataset.label;

            options.forEach(function (item) {
                item.classList.remove("is-selected");
            });

            option.classList.add("is-selected");
            tutupPanel();
        });
    });

    document.addEventListener("click", function (event) {
        if (!wrapper.contains(event.target)) {
            tutupPanel();
        }
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>