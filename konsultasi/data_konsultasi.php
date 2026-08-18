<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua",
    "petugas_gizi",
    "petugas_kia",
    "kader",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Konsultasi Aktif | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = null;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

/*
|--------------------------------------------------------------------------
| PUSKESMAS PENGGUNA AKTIF
|--------------------------------------------------------------------------
| Petugas Gizi dan Kepala Puskesmas hanya melihat konsultasi balita
| yang berasal dari Puskesmas yang sama dengan akun mereka.
*/
if (
    in_array(
        $roleAktif,
        ["petugas_gizi", "petugas_kia", "kader", "kepala_puskesmas"],
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

    mysqli_stmt_execute($stmtPuskesmas);

    $hasilPuskesmas =
        mysqli_stmt_get_result(
            $stmtPuskesmas
        );

    $dataPuskesmas =
        mysqli_fetch_assoc(
            $hasilPuskesmas
        );

    mysqli_stmt_close($stmtPuskesmas);

    if (
        !$dataPuskesmas
        || empty($dataPuskesmas["id_puskesmas"])
    ) {
        $puskesmasBelumTerhubung = true;
    } else {
        $idPuskesmasAktif =
            (int) $dataPuskesmas["id_puskesmas"];

        $namaPuskesmasAktif =
            trim(
                (string) (
                    $dataPuskesmas["nama_puskesmas"]
                    ?? ""
                )
            );
    }
}

/*
|--------------------------------------------------------------------------
| Konsultasi baru hanya diajukan oleh Orang Tua
|--------------------------------------------------------------------------
|
| Petugas Gizi tidak membuat keluhan baru. Petugas Gizi hanya menanggapi
| konsultasi yang sudah diajukan Orang Tua.
|
*/

/* Orang Tua tidak dapat mengajukan konsultasi sendiri. */
$bolehTambah = false;

$bolehMengelola = $roleAktif === "petugas_gizi";
$modeMonitoring = in_array(
    $roleAktif,
    ["petugas_kia", "kader", "kepala_puskesmas", "dinkes"],
    true
);

/*
|--------------------------------------------------------------------------
| FILTER KONSULTASI - SEMUA ROLE
|--------------------------------------------------------------------------
| Semua akun yang mempunyai menu Konsultasi mendapat filter:
| - Pencarian
| - Bulan
| - Tahun
| - Status konsultasi
| - Urutan terbaru / terlama
|
| Khusus Dinkes tetap mendapat filter Puskesmas lintas wilayah.
| Role wilayah lainnya tetap terkunci pada Puskesmas akun.
*/

$cari = trim($_GET["cari"] ?? "");
$kataKunci = "%" . $cari . "%";

$filterStatus = trim($_GET["status"] ?? "");
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

$filterUrutan =
    trim($_GET["urutan"] ?? "terbaru");

$filterPuskesmas = 0;

if (
    !in_array(
        $filterStatus,
        [
            "",
            "menunggu_orang_tua",
            "menunggu_ahli_gizi"
        ],
        true
    )
) {
    $filterStatus = "";
}

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

if (
    !in_array(
        $filterUrutan,
        ["terbaru", "terlama"],
        true
    )
) {
    $filterUrutan = "terbaru";
}

/*
|--------------------------------------------------------------------------
| Filter Puskesmas hanya untuk Dinkes
|--------------------------------------------------------------------------
*/

$daftarPuskesmasFilter = [];

if ($roleAktif === "dinkes") {

    $filterPuskesmas =
        filter_input(
            INPUT_GET,
            "puskesmas",
            FILTER_VALIDATE_INT
        ) ?: 0;

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
}

/*
|--------------------------------------------------------------------------
| Pilihan Tahun
|--------------------------------------------------------------------------
| Dibaca sebagai karakter agar database lama yang masih memiliki
| tanggal 0000-00-00 tidak memicu error DATE pada MySQL strict mode.
*/

$daftarTahunFilter = [];

$queryTahunFilter = mysqli_query(
    $conn,
    "SELECT DISTINCT
        LEFT(CAST(tanggal AS CHAR), 4) AS tahun
     FROM konsultasi
     WHERE tanggal IS NOT NULL
       AND LEFT(CAST(tanggal AS CHAR), 4) <> '0000'
     ORDER BY tahun DESC"
);

if ($queryTahunFilter) {

    while (
        $itemTahun =
            mysqli_fetch_assoc(
                $queryTahunFilter
            )
    ) {
        $tahunItem =
            (int) (
                $itemTahun["tahun"]
                ?? 0
            );

        if (
            $tahunItem >= 2000
            && $tahunItem <= 2100
        ) {
            $daftarTahunFilter[] =
                $tahunItem;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Helper bind parameter dinamis
|--------------------------------------------------------------------------
*/

function bindParameterKonsultasi(
    mysqli_stmt $stmt,
    string $tipe,
    array &$parameter
): void {
    if ($tipe === "" || empty($parameter)) {
        return;
    }

    $argumen = [$tipe];

    foreach ($parameter as &$nilaiParameter) {
        $argumen[] = &$nilaiParameter;
    }

    call_user_func_array(
        [$stmt, "bind_param"],
        $argumen
    );
}

/*
|--------------------------------------------------------------------------
| FUNGSI OUTPUT AMAN
|--------------------------------------------------------------------------
*/
function amanKonsultasi($nilai): string
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
| DATA KONSULTASI
|--------------------------------------------------------------------------
| Orang Tua       : hanya konsultasi anak miliknya.
| Petugas wilayah : hanya konsultasi balita di Puskesmas akun.
| Dinkes          : seluruh konsultasi, dapat filter Puskesmas.
|
| Filter Bulan/Tahun/Status/Urutan/Pencarian berlaku untuk semua role.
*/

$selectKonsultasi = "
    SELECT
        k.id_konsultasi,
        k.id_deteksi,
        k.sumber_pengajuan,
        k.id_balita,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        k.status_konsultasi,
        k.tanggal_selesai,
        b.nama_balita,
        b.nik_balita,
        b.id_puskesmas,
        ps.nama_puskesmas,
        p.nama AS nama_petugas,
        hd.status_stunting,
        hd.keputusan_konsultasi
    FROM konsultasi AS k
    INNER JOIN balita AS b
        ON k.id_balita = b.id_balita
    LEFT JOIN puskesmas AS ps
        ON b.id_puskesmas = ps.id_puskesmas
    LEFT JOIN pengguna AS p
        ON k.id_petugas = p.id_user
    INNER JOIN hasil_deteksi AS hd
        ON k.id_deteksi = hd.id_deteksi
";

$whereKonsultasi = [
    "k.sumber_pengajuan = 'ahli_gizi'",
    "hd.keputusan_konsultasi = 'Perlu konsultasi'",
    "LOWER(TRIM(COALESCE(k.status_konsultasi, 'aktif'))) <> 'selesai'"
];

$tipeParameter = "";
$parameterKonsultasi = [];

/*
|--------------------------------------------------------------------------
| Batas data berdasarkan role
|--------------------------------------------------------------------------
*/

if ($roleAktif === "orang_tua") {

    $whereKonsultasi[] = "b.id_user = ?";
    $tipeParameter .= "i";
    $parameterKonsultasi[] = $idUserAktif;

} elseif (
    in_array(
        $roleAktif,
        ["petugas_gizi", "petugas_kia", "kader", "kepala_puskesmas"],
        true
    )
) {

    if ($puskesmasBelumTerhubung) {
        $whereKonsultasi[] = "1 = 0";
    } else {
        $whereKonsultasi[] = "b.id_puskesmas = ?";
        $tipeParameter .= "i";
        $parameterKonsultasi[] = $idPuskesmasAktif;
    }

} elseif ($roleAktif === "dinkes") {

    if ($filterPuskesmas > 0) {
        $whereKonsultasi[] = "b.id_puskesmas = ?";
        $tipeParameter .= "i";
        $parameterKonsultasi[] = $filterPuskesmas;
    }
}

/*
|--------------------------------------------------------------------------
| Filter bulan dan tahun
|--------------------------------------------------------------------------
| Menggunakan potongan karakter dari tanggal supaya aman jika database lama
| memiliki nilai tanggal 0000-00-00.
*/

if ($filterBulan > 0) {

    $bulanDuaDigit =
        str_pad(
            (string) $filterBulan,
            2,
            "0",
            STR_PAD_LEFT
        );

    $whereKonsultasi[] =
        "SUBSTRING(CAST(k.tanggal AS CHAR), 6, 2) = ?";

    $tipeParameter .= "s";
    $parameterKonsultasi[] = $bulanDuaDigit;
}

if ($filterTahun > 0) {

    $tahunEmpatDigit =
        (string) $filterTahun;

    $whereKonsultasi[] =
        "LEFT(CAST(k.tanggal AS CHAR), 4) = ?";

    $tipeParameter .= "s";
    $parameterKonsultasi[] = $tahunEmpatDigit;
}

/*
|--------------------------------------------------------------------------
| Filter status konsultasi
|--------------------------------------------------------------------------
*/

if ($filterStatus === "menunggu_orang_tua") {

    $whereKonsultasi[] =
        "TRIM(COALESCE(k.keluhan, '')) = ''";

} elseif ($filterStatus === "menunggu_ahli_gizi") {

    $whereKonsultasi[] =
        "TRIM(COALESCE(k.keluhan, '')) <> ''
         AND TRIM(COALESCE(k.hasil_konsultasi, '')) = ''";

} elseif ($filterStatus === "ditanggapi") {

    $whereKonsultasi[] =
        "TRIM(COALESCE(k.hasil_konsultasi, '')) <> ''";
}

/*
|--------------------------------------------------------------------------
| Pencarian
|--------------------------------------------------------------------------
*/

if ($cari !== "") {

    $whereKonsultasi[] = "
        (
            b.nama_balita LIKE ?
            OR b.nik_balita LIKE ?
            OR p.nama LIKE ?
            OR ps.nama_puskesmas LIKE ?
            OR hd.status_stunting LIKE ?
            OR k.keluhan LIKE ?
            OR k.hasil_konsultasi LIKE ?
            OR k.tindak_lanjut LIKE ?
        )
    ";

    $tipeParameter .= "ssssssss";

    for ($iCari = 0; $iCari < 8; $iCari++) {
        $parameterKonsultasi[] = $kataKunci;
    }
}

$arahUrutan =
    $filterUrutan === "terlama"
        ? "ASC"
        : "DESC";

$sqlKonsultasi =
    $selectKonsultasi
    . " WHERE "
    . implode(
        " AND ",
        $whereKonsultasi
    )
    . " ORDER BY
        k.tanggal {$arahUrutan},
        k.id_konsultasi {$arahUrutan}";

$stmt =
    mysqli_prepare(
        $conn,
        $sqlKonsultasi
    );

if (!$stmt) {
    die(
        "Gagal menyiapkan data konsultasi: "
        . mysqli_error($conn)
    );
}

bindParameterKonsultasi(
    $stmt,
    $tipeParameter,
    $parameterKonsultasi
);

if (!mysqli_stmt_execute($stmt)) {
    die(
        "Gagal menjalankan data konsultasi: "
        . mysqli_stmt_error($stmt)
    );
}

$query = mysqli_stmt_get_result($stmt);

if (!$query) {
    die(
        "Gagal membaca hasil data konsultasi: "
        . mysqli_stmt_error($stmt)
    );
}

$totalData = mysqli_num_rows($query);

/*
|--------------------------------------------------------------------------
| PESAN HALAMAN
|--------------------------------------------------------------------------
*/
$pesan = $_GET["pesan"] ?? "";
$jenisAlert = "";
$isiPesan = "";

switch ($pesan) {
    case "tambah_berhasil":
        $jenisAlert = "success";
        $isiPesan = "Data konsultasi berhasil ditambahkan.";
        break;

    case "edit_berhasil":
        $jenisAlert = "success";
        $isiPesan = "Data konsultasi berhasil diperbarui.";
        break;

    case "hapus_berhasil":
        $jenisAlert = "success";
        $isiPesan = "Data konsultasi berhasil dihapus.";
        break;

    case "hapus_gagal":
        $jenisAlert = "danger";
        $isiPesan = "Data konsultasi gagal dihapus.";
        break;

    case "tidak_ditemukan":
        $jenisAlert = "warning";
        $isiPesan = "Data konsultasi tidak ditemukan.";
        break;

    case "pengajuan_dikunci":
        $jenisAlert = "info";
        $isiPesan = "Orang Tua tidak dapat mengajukan konsultasi sendiri. Konsultasi tersedia setelah Ahli Gizi menetapkan anak perlu konsultasi.";
        break;

    case "keluhan_berhasil":
        $jenisAlert = "success";
        $isiPesan = "Keluhan berhasil dikirim. Ahli Gizi dapat menindaklanjuti konsultasi Anda.";
        break;

    case "keluhan_ditambahkan":
        $jenisAlert = "success";
        $isiPesan = "Keluhan tambahan berhasil dikirim. Ahli Gizi dapat menindaklanjuti keluhan terbaru Anda.";
        break;

    case "menunggu_orang_tua":
        $jenisAlert = "warning";
        $isiPesan = "Konsultasi sudah disetujui, tetapi Orang Tua belum mengisi keluhan atau hal yang ingin dikonsultasikan.";
        break;
}

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
                        Konsultasi Aktif
                    </h4>

                    <small class="text-muted">

                        <?php if ($modeMonitoring): ?>
                            Pantau konsultasi yang masih berjalan dan tindak lanjut balita.
                        <?php elseif ($roleAktif === "orang_tua"): ?>
                            Lihat konsultasi yang telah direkomendasikan Ahli Gizi untuk balita Anda.
                        <?php elseif ($roleAktif === "petugas_gizi"): ?>
                            Tindak lanjuti konsultasi yang telah Anda tetapkan untuk balita berisiko.
                        <?php else: ?>
                            Pantau konsultasi gizi dan tindak lanjut balita.
                        <?php endif; ?>

                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        in_array(
                            $roleAktif,
                            ["petugas_gizi", "petugas_kia", "kader", "kepala_puskesmas"],
                            true
                        )
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex align-items-center px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= amanKonsultasi(
                                $namaPuskesmasAktif
                            ); ?>
                        </span>

                    <?php elseif ($roleAktif === "dinkes"): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex align-items-center px-3"
                        >
                            <i class="bi bi-buildings me-1"></i>
                            Semua Puskesmas
                        </span>

                    <?php endif; ?>

                    <a
                        href="riwayat_konsultasi.php"
                        class="btn btn-outline-primary btn-sm"
                    >
                        <i class="bi bi-clock-history"></i>
                        Riwayat Konsultasi
                    </a>

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if ($bolehTambah): ?>

                        <a
                            href="tambah_konsultasi.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Ajukan Konsultasi
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if ($roleAktif === "orang_tua"): ?>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        Konsultasi tidak diajukan oleh Orang Tua. Menu ini akan
                        menampilkan konsultasi setelah <strong>Ahli Gizi</strong>
                        memverifikasi hasil deteksi dan menetapkan bahwa anak
                        <strong>perlu konsultasi</strong>.
                    </div>

                <?php elseif (in_array($roleAktif, ["kader", "petugas_kia"], true)): ?>

                    <div class="alert alert-warning">
                        <i class="bi bi-bell-fill me-1"></i>
                        Daftar berikut berisi balita yang telah ditetapkan oleh
                        <strong>Ahli Gizi</strong> sebagai perlu konsultasi.
                        Mohon informasikan kepada Orang Tua agar melakukan
                        konsultasi dengan Ahli Gizi Puskesmas.
                    </div>

                <?php endif; ?>

                <?php if ($puskesmasBelumTerhubung): ?>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Akun ini belum terhubung dengan Puskesmas.
                        Hubungkan akun ke Puskesmas melalui menu Data Pengguna
                        sebelum mengakses data konsultasi wilayah.
                    </div>

                <?php endif; ?>

                <?php if ($isiPesan !== ""): ?>

                    <div
                        class="alert alert-<?= htmlspecialchars(
                            $jenisAlert,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?> alert-dismissible fade show"
                        role="alert"
                    >

                        <i
                            class="bi <?= $jenisAlert === "success"
                                ? "bi-check-circle"
                                : ($jenisAlert === "danger"
                                    ? "bi-x-circle"
                                    : "bi-exclamation-triangle"); ?> me-1"
                        ></i>

                        <?= htmlspecialchars(
                            $isiPesan,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>

                    </div>

                <?php endif; ?>

                <form
                    method="GET"
                    class="row g-2 mb-4 align-items-end"
                >

                    <div class="col-12 col-xl-4">

                        <label
                            for="cari"
                            class="form-label small text-muted mb-1"
                        >
                            Cari
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="text"
                                id="cari"
                                name="cari"
                                class="form-control"
                                placeholder="Cari balita, NIK, petugas, status, hasil, atau tindak lanjut"
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >

                        </div>

                    </div>

                    <div class="col-6 col-md-4 col-xl-2">

                        <label
                            for="status"
                            class="form-label small text-muted mb-1"
                        >
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select"
                        >
                            <option value="">
                                Semua Status
                            </option>

                            <option
                                value="menunggu_orang_tua"
                                <?= $filterStatus === "menunggu_orang_tua"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Menunggu Orang Tua
                            </option>

                            <option
                                value="menunggu_ahli_gizi"
                                <?= $filterStatus === "menunggu_ahli_gizi"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Menunggu Ahli Gizi
                            </option>
                        </select>

                    </div>

                    <div class="col-6 col-md-4 col-xl-2">

                        <label
                            for="bulan"
                            class="form-label small text-muted mb-1"
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
                            $namaBulanKonsultasi = [
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
                                $namaBulanKonsultasi
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

                    <div class="col-6 col-md-4 col-xl-2">

                        <label
                            for="tahun"
                            class="form-label small text-muted mb-1"
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

                    <div class="col-6 col-md-4 col-xl-2">

                        <label
                            for="urutan"
                            class="form-label small text-muted mb-1"
                        >
                            Urutan
                        </label>

                        <select
                            id="urutan"
                            name="urutan"
                            class="form-select"
                        >
                            <option
                                value="terbaru"
                                <?= $filterUrutan === "terbaru"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Terbaru
                            </option>

                            <option
                                value="terlama"
                                <?= $filterUrutan === "terlama"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Terlama
                            </option>
                        </select>

                    </div>

                    <?php if ($roleAktif === "dinkes"): ?>

                        <div class="col-12 col-md-6 col-xl-4">

                            <label
                                for="puskesmas"
                                class="form-label small text-muted mb-1"
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
                                        <?= amanKonsultasi(
                                            $puskesmasFilter[
                                                "nama_puskesmas"
                                            ]
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    <?php endif; ?>

                    <div class="col-6 col-md-3 col-xl-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            <i class="bi bi-funnel"></i>
                            Filter
                        </button>

                    </div>

                    <div class="col-6 col-md-3 col-xl-2">

                        <a
                            href="data_konsultasi.php"
                            class="btn btn-outline-secondary w-100"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset
                        </a>

                    </div>

                </form>

                <div
                    class="d-flex flex-wrap
                    justify-content-between align-items-center
                    gap-2 mb-3"
                >

                    <span class="text-muted small">
                        Total data:
                        <strong>
                            <?= $totalData; ?>
                        </strong>
                        konsultasi
                    </span>

                    <?php if ($modeMonitoring): ?>

                        <span class="badge bg-secondary">
                            <i class="bi bi-eye"></i>
                            Mode Monitoring
                        </span>

                    <?php elseif (
                        $roleAktif === "orang_tua"
                    ): ?>

                        <span class="badge badge-info">
                            <i class="bi bi-person-heart"></i>
                            Konsultasi Saya
                        </span>

                    <?php else: ?>

                        <span class="badge badge-info">
                            <i class="bi bi-chat-heart"></i>
                            Konsultasi Gizi
                        </span>

                    <?php endif; ?>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th class="text-center">
                                    No
                                </th>

                                <th>
                                    Tanggal
                                </th>

                                <th>
                                    Balita
                                </th>

                                <th>
                                    Puskesmas
                                </th>

                                <th class="text-center">
                                    Status Stunting
                                </th>

                                <th>
                                    Petugas Gizi
                                </th>

                                <th class="text-center">
                                    Status
                                </th>

                                <th>
                                    Hasil Konsultasi
                                </th>

                                <th>
                                    Tindak Lanjut
                                </th>

                                <th class="text-center">
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($totalData > 0): ?>

                            <?php

                            $nomor = 1;

                            while (
                                $data = mysqli_fetch_assoc($query)
                            ):

                                $idKonsultasi =
                                    (int) $data["id_konsultasi"];

                                $tanggalTampil = "-";

                                if (!empty($data["tanggal"])) {

                                    $waktuTanggal =
                                        strtotime($data["tanggal"]);

                                    if ($waktuTanggal !== false) {

                                        $tanggalTampil = date(
                                            "d-m-Y",
                                            $waktuTanggal
                                        );
                                    }
                                }

                                $keluhanKosong =
                                    trim(
                                        (string) (
                                            $data[
                                                "keluhan"
                                            ] ?? ""
                                        )
                                    ) === "";

                                $hasilKosong =
                                    trim(
                                        (string) (
                                            $data[
                                                "hasil_konsultasi"
                                            ] ?? ""
                                        )
                                    ) === "";

                                $tindakLanjutKosong =
                                    trim(
                                        (string) (
                                            $data[
                                                "tindak_lanjut"
                                            ] ?? ""
                                        )
                                    ) === "";

                                $belumDitangani =
                                    $hasilKosong;

                                $sudahDitangani =
                                    !$hasilKosong;

                                $adaTindakLanjut =
                                    !$tindakLanjutKosong;

                                $petugasDitugaskanKeAkunIni =
                                    (
                                        $roleAktif ===
                                        "petugas_gizi"
                                        && (int) (
                                            $data[
                                                "id_petugas"
                                            ] ?? 0
                                        ) ===
                                        $idUserAktif
                                    );

                            ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $nomor++; ?>
                                    </td>

                                    <td>

                                        <span class="text-nowrap">
                                            <i
                                                class="bi
                                                bi-calendar3 me-1"
                                            ></i>

                                            <?= amanKonsultasi(
                                                $tanggalTampil
                                            ); ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div
                                            class="d-flex
                                            align-items-center gap-2"
                                        >

                                            <span
                                                class="badge
                                                badge-primary"
                                            >
                                                <i
                                                    class="bi
                                                    bi-person-heart"
                                                ></i>
                                            </span>

                                            <div>

                                                <strong
                                                    class="d-block"
                                                >
                                                    <?= amanKonsultasi(
                                                        $data[
                                                            "nama_balita"
                                                        ]
                                                    ); ?>
                                                </strong>

                                                <small
                                                    class="text-muted"
                                                >
                                                    NIK:
                                                    <?= amanKonsultasi(
                                                        $data[
                                                            "nik_balita"
                                                        ]
                                                    ); ?>
                                                </small>

                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        <?= amanKonsultasi(
                                            $data[
                                                "nama_puskesmas"
                                            ] ?? null
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?php
                                            $statusStuntingTabel = strtolower(
                                                trim((string) ($data["status_stunting"] ?? ""))
                                            );
                                            $kelasStuntingTabel =
                                                $statusStuntingTabel === "risiko stunting"
                                                    ? "bg-warning text-dark"
                                                    : "bg-danger";
                                        ?>
                                        <span class="badge <?= $kelasStuntingTabel; ?>">
                                            <?= amanKonsultasi($data["status_stunting"] ?? "-"); ?>
                                        </span>
                                    </td>

                                    <td>

                                        <?php if (
                                            !empty(
                                                $data[
                                                    "nama_petugas"
                                                ]
                                            )
                                        ): ?>

                                            <?= amanKonsultasi(
                                                $data[
                                                    "nama_petugas"
                                                ]
                                            ); ?>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                bg-secondary"
                                            >
                                                Belum Ditentukan
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td class="text-center">

                                        <?php if ($keluhanKosong): ?>

                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-person-exclamation me-1"></i>
                                                Menunggu Orang Tua
                                            </span>

                                        <?php elseif ($belumDitangani): ?>

                                            <span class="badge bg-info text-dark">
                                                <i class="bi bi-hourglass-split me-1"></i>
                                                Menunggu Ahli Gizi
                                            </span>

                                        <?php elseif (
                                            $adaTindakLanjut
                                        ): ?>

                                            <span
                                                class="badge
                                                bg-success"
                                            >
                                                <i
                                                    class="bi
                                                    bi-check-circle
                                                    me-1"
                                                ></i>
                                                Sudah Ditanggapi
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                bg-info"
                                            >
                                                Sudah Ditanggapi
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td
                                        style="
                                            min-width: 220px;
                                            white-space: normal;
                                        "
                                    >

                                        <?php if (
                                            !$hasilKosong
                                        ): ?>

                                            <?= nl2br(
                                                amanKonsultasi(
                                                    $data[
                                                        "hasil_konsultasi"
                                                    ]
                                                )
                                            ); ?>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                bg-warning text-dark"
                                            >
                                                Belum diisi
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td
                                        style="
                                            min-width: 220px;
                                            white-space: normal;
                                        "
                                    >

                                        <?php if (
                                            !$tindakLanjutKosong
                                        ): ?>

                                            <?= nl2br(
                                                amanKonsultasi(
                                                    $data[
                                                        "tindak_lanjut"
                                                    ]
                                                )
                                            ); ?>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                bg-secondary"
                                            >
                                                Belum ada
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

                                            <a
                                                href="detail_konsultasi.php?id=<?= $idKonsultasi; ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i
                                                    class="bi
                                                    bi-eye"
                                                ></i>
                                                Detail
                                            </a>

                                            <?php if (
                                                $roleAktif === "orang_tua"
                                            ): ?>

                                                <a
                                                    href="tambah_konsultasi.php?id=<?= $idKonsultasi; ?>"
                                                    class="btn btn-primary btn-sm"
                                                >
                                                    <i class="bi bi-chat-heart"></i>
                                                    <?= $keluhanKosong
                                                        ? "Mulai Konsultasi"
                                                        : "Tambah Keluhan"; ?>
                                                </a>

                                            <?php endif; ?>

                                            <?php if (
                                                $bolehMengelola
                                                && $petugasDitugaskanKeAkunIni
                                            ): ?>

                                                <a
                                                    href="edit_konsultasi.php?id=<?= $idKonsultasi; ?>"
                                                    class="btn btn-warning btn-sm"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-chat-left-text"
                                                    ></i>

                                                    <?= $belumDitangani
                                                        ? "Tanggapi"
                                                        : "Edit Hasil"; ?>
                                                </a>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="10">

                                    <div class="empty-state">

                                        <div
                                            class="empty-state-icon"
                                        >
                                            <i
                                                class="bi
                                                bi-chat-heart"
                                            ></i>
                                        </div>

                                        <h3>
                                            <?php if (
                                                $cari !== ""
                                            ): ?>
                                                Data konsultasi tidak ditemukan
                                            <?php else: ?>
                                                Belum ada data konsultasi
                                            <?php endif; ?>
                                        </h3>

                                        <p>
                                            <?php if (
                                                $cari !== ""
                                            ): ?>
                                                Coba gunakan kata kunci lain atau reset pencarian.
                                            <?php elseif (
                                                $roleAktif ===
                                                "orang_tua"
                                            ): ?>
                                                Ajukan konsultasi untuk mendapatkan arahan dari Petugas Gizi.
                                            <?php elseif (
                                                $modeMonitoring
                                            ): ?>
                                                Data konsultasi belum tersedia untuk dimonitoring.
                                            <?php elseif (
                                                $roleAktif === "petugas_gizi"
                                            ): ?>
                                                Belum ada keluhan Orang Tua yang perlu ditanggapi.
                                            <?php else: ?>
                                                Data konsultasi belum tersedia.
                                            <?php endif; ?>
                                        </p>

                                        <?php if (
                                            $cari !== ""
                                        ): ?>

                                            <a
                                                href="data_konsultasi.php"
                                                class="btn btn-outline-secondary mt-3"
                                            >
                                                <i
                                                    class="bi
                                                    bi-arrow-counterclockwise"
                                                ></i>
                                                Reset Pencarian
                                            </a>

                                        <?php elseif (
                                            $bolehTambah
                                        ): ?>

                                            <a
                                                href="tambah_konsultasi.php"
                                                class="btn btn-primary mt-3"
                                            >
                                                <i
                                                    class="bi
                                                    bi-plus-circle"
                                                ></i>
                                                Tambah Konsultasi
                                            </a>

                                        <?php endif; ?>

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

<?php

mysqli_stmt_close($stmt);

require_once "../includes/footer.php";

?>