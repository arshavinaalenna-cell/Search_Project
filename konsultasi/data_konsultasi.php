<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua",
    "petugas_gizi",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Data Konsultasi | Sistem Deteksi Stunting";

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
        ["petugas_gizi", "kepala_puskesmas"],
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

$bolehTambah =
    $roleAktif === "orang_tua";

$bolehMengelola = $roleAktif === "petugas_gizi";
$modeMonitoring = in_array(
    $roleAktif,
    ["kepala_puskesmas", "dinkes"],
    true
);

$cari = trim($_GET["cari"] ?? "");
$kataKunci = "%" . $cari . "%";

/*
|--------------------------------------------------------------------------
| Filter khusus Dinkes
|--------------------------------------------------------------------------
|
| Filter berikut hanya tampil dan digunakan pada role Dinkes:
| - Status konsultasi
| - Puskesmas
| - Bulan
| - Tahun
|
*/

$filterStatus = "";
$filterPuskesmas = 0;
$filterBulan = 0;
$filterTahun = 0;

$daftarPuskesmasFilter = [];
$daftarTahunFilter = [];

if ($roleAktif === "dinkes") {

    $filterStatus =
        trim(
            $_GET["status"] ?? ""
        );

    if (
        !in_array(
            $filterStatus,
            [
                "",
                "menunggu",
                "ditanggapi"
            ],
            true
        )
    ) {
        $filterStatus = "";
    }

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

    /*
    |--------------------------------------------------------------------------
    | Pilihan Puskesmas
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Pilihan Tahun
    |--------------------------------------------------------------------------
    */

    $queryTahunFilter = mysqli_query(
        $conn,
        "SELECT DISTINCT
            YEAR(tanggal) AS tahun
         FROM konsultasi
         WHERE tanggal IS NOT NULL
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
| Petugas Gizi    : hanya konsultasi balita di Puskesmas yang sama.
| Kepala Puskesmas: hanya monitoring Puskesmas yang sama.
| Dinkes          : seluruh konsultasi.
*/

$selectKonsultasi = "
    SELECT
        k.id_konsultasi,
        k.id_balita,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        b.nama_balita,
        b.nik_balita,
        b.id_puskesmas,
        ps.nama_puskesmas,
        p.nama AS nama_petugas
    FROM konsultasi AS k
    INNER JOIN balita AS b
        ON k.id_balita = b.id_balita
    LEFT JOIN puskesmas AS ps
        ON b.id_puskesmas = ps.id_puskesmas
    LEFT JOIN pengguna AS p
        ON k.id_petugas = p.id_user
";

if ($roleAktif === "orang_tua") {

    if ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectKonsultasi . "
             WHERE b.id_user = ?
               AND (
                    b.nama_balita LIKE ?
                    OR b.nik_balita LIKE ?
                    OR p.nama LIKE ?
                    OR k.keluhan LIKE ?
                    OR k.hasil_konsultasi LIKE ?
                    OR k.tindak_lanjut LIKE ?
               )
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian konsultasi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "issssss",
            $idUserAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectKonsultasi . "
             WHERE b.id_user = ?
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil data konsultasi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idUserAktif
        );
    }

} elseif (
    in_array(
        $roleAktif,
        ["petugas_gizi", "kepala_puskesmas"],
        true
    )
) {

    if ($puskesmasBelumTerhubung) {

        $stmt = mysqli_prepare(
            $conn,
            $selectKonsultasi . "
             WHERE 1 = 0
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan data konsultasi: "
                . mysqli_error($conn)
            );
        }

    } elseif ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectKonsultasi . "
             WHERE b.id_puskesmas = ?
               AND (
                    b.nama_balita LIKE ?
                    OR b.nik_balita LIKE ?
                    OR p.nama LIKE ?
                    OR k.keluhan LIKE ?
                    OR k.hasil_konsultasi LIKE ?
                    OR k.tindak_lanjut LIKE ?
               )
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian konsultasi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "issssss",
            $idPuskesmasAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectKonsultasi . "
             WHERE b.id_puskesmas = ?
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil data konsultasi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idPuskesmasAktif
        );
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | Dinkes
    |--------------------------------------------------------------------------
    |
    | Dinkes dapat memonitor seluruh Puskesmas dan memfilter data
    | berdasarkan status, Puskesmas, bulan, tahun, serta pencarian.
    |
    */

    $stmt = mysqli_prepare(
        $conn,
        $selectKonsultasi . "
         WHERE
            (? = 0 OR b.id_puskesmas = ?)
         AND
            (? = 0 OR MONTH(k.tanggal) = ?)
         AND
            (? = 0 OR YEAR(k.tanggal) = ?)
         AND
            (
                ? = ''
                OR (
                    ? = 'menunggu'
                    AND TRIM(
                        COALESCE(
                            k.hasil_konsultasi,
                            ''
                        )
                    ) = ''
                )
                OR (
                    ? = 'ditanggapi'
                    AND TRIM(
                        COALESCE(
                            k.hasil_konsultasi,
                            ''
                        )
                    ) <> ''
                )
            )
         AND
            (
                ? = ''
                OR b.nama_balita LIKE ?
                OR b.nik_balita LIKE ?
                OR p.nama LIKE ?
                OR k.keluhan LIKE ?
                OR k.hasil_konsultasi LIKE ?
                OR k.tindak_lanjut LIKE ?
            )
         ORDER BY k.id_konsultasi DESC"
    );

    if (!$stmt) {
        die(
            "Gagal menyiapkan filter konsultasi Dinkes: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiiiiissssssssss",
        $filterPuskesmas,
        $filterPuskesmas,
        $filterBulan,
        $filterBulan,
        $filterTahun,
        $filterTahun,
        $filterStatus,
        $filterStatus,
        $filterStatus,
        $cari,
        $kataKunci,
        $kataKunci,
        $kataKunci,
        $kataKunci,
        $kataKunci,
        $kataKunci
    );
}

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
                        Data Konsultasi
                    </h4>

                    <small class="text-muted">

                        <?php if ($modeMonitoring): ?>
                            Pantau riwayat konsultasi gizi dan tindak lanjut balita.
                        <?php elseif ($roleAktif === "orang_tua"): ?>
                            Lihat dan ajukan konsultasi gizi untuk balita Anda.
                        <?php elseif ($roleAktif === "petugas_gizi"): ?>
                            Tinjau keluhan Orang Tua dan berikan tanggapan gizi.
                        <?php else: ?>
                            Pantau konsultasi gizi dan tindak lanjut balita.
                        <?php endif; ?>

                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        in_array(
                            $roleAktif,
                            ["petugas_gizi", "kepala_puskesmas"],
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

                <?php if ($roleAktif === "dinkes"): ?>

                    <form
                        method="GET"
                        class="row g-2 mb-4 align-items-end"
                    >

                        <div class="col-12 col-lg-4">

                            <label
                                for="cari"
                                class="form-label small text-muted mb-1"
                            >
                                Pencarian
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
                                    placeholder="Cari balita, NIK, petugas, keluhan, hasil, atau tindak lanjut"
                                    value="<?= htmlspecialchars(
                                        $cari,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                >

                            </div>

                        </div>

                        <div class="col-6 col-md-3 col-lg-2">

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
                                    value="menunggu"
                                    <?= $filterStatus === "menunggu"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Menunggu Tanggapan
                                </option>

                                <option
                                    value="ditanggapi"
                                    <?= $filterStatus === "ditanggapi"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Sudah Ditanggapi
                                </option>
                            </select>

                        </div>

                        <div class="col-6 col-md-3 col-lg-2">

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

                        <div class="col-6 col-md-3 col-lg-2">

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

                        <div class="col-6 col-md-3 col-lg-2">

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

                        <div class="col-6 col-lg-2">

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                <i class="bi bi-funnel"></i>
                                Filter
                            </button>

                        </div>

                        <div class="col-6 col-lg-2">

                            <a
                                href="data_konsultasi.php"
                                class="btn btn-outline-secondary w-100"
                            >
                                <i class="bi bi-arrow-counterclockwise"></i>
                                Reset
                            </a>

                        </div>

                    </form>

                <?php else: ?>

                <form
                    method="GET"
                    class="row g-2 mb-3"
                >

                    <div class="col-12 col-lg-8">

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="text"
                                name="cari"
                                class="form-control"
                                placeholder="Cari balita, NIK, petugas, keluhan, hasil, atau tindak lanjut"
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >

                        </div>

                    </div>

                    <div class="col-6 col-lg-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            <i class="bi bi-search"></i>
                            Cari
                        </button>

                    </div>

                    <div class="col-6 col-lg-2">

                        <a
                            href="data_konsultasi.php"
                            class="btn btn-outline-secondary w-100"
                        >
                            <i
                                class="bi
                                bi-arrow-counterclockwise"
                            ></i>
                            Reset
                        </a>

                    </div>

                </form>

                <?php endif; ?>

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

                                        <?php if (
                                            $belumDitangani
                                        ): ?>

                                            <span
                                                class="badge
                                                bg-warning text-dark"
                                            >
                                                <i
                                                    class="bi
                                                    bi-hourglass-split
                                                    me-1"
                                                ></i>
                                                Menunggu Tanggapan
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

                                <td colspan="9">

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