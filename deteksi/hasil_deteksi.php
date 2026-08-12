<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

date_default_timezone_set("Asia/Jakarta");
@mysqli_query($conn, "SET time_zone = '+07:00'");

cekRole([
    "petugas_gizi",
    "petugas_kia",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Hasil Deteksi Stunting | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$cari = trim($_GET["cari"] ?? "");

$kataKunci = "%" . $cari . "%";

/*
|--------------------------------------------------------------------------
| Filter khusus Dinkes
|--------------------------------------------------------------------------
|
| Filter Puskesmas, bulan, dan tahun hanya berlaku untuk role Dinkes.
| Role lain tetap memakai batas wilayah masing-masing seperti sebelumnya.
|
*/

$filterPuskesmas = 0;
$filterBulan = 0;
$filterTahun = 0;

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
}

/*
|--------------------------------------------------------------------------
| Daftar pilihan filter Dinkes
|--------------------------------------------------------------------------
*/

$daftarPuskesmasFilter = [];
$daftarTahunFilter = [];

if ($roleAktif === "dinkes") {

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
            YEAR(tanggal_deteksi) AS tahun
         FROM hasil_deteksi
         WHERE tanggal_deteksi IS NOT NULL
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

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

$roleBerbasisPuskesmas =
    in_array(
        $roleAktif,
        [
            "petugas_gizi",
            "petugas_kia",
            "kepala_puskesmas"
        ],
        true
    );

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas akun aktif
|--------------------------------------------------------------------------
*/

if ($roleBerbasisPuskesmas) {

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

    mysqli_stmt_close(
        $stmtPuskesmas
    );

    if (
        !$dataPuskesmas
        || empty(
            $dataPuskesmas["id_puskesmas"]
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
                (string) (
                    $dataPuskesmas[
                        "nama_puskesmas"
                    ]
                    ?? ""
                )
            );
    }
}

/*
|--------------------------------------------------------------------------
| Query dasar hasil deteksi
|--------------------------------------------------------------------------
*/

$selectDeteksi = "
    SELECT
        hd.id_deteksi,
        hd.id_pengukuran,
        hd.status_gizi,
        hd.status_stunting,
        hd.tanggal_deteksi,
        hd.status_verifikasi,

        pa.tanggal_pengukuran,
        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,
        pa.lingkar_kepala,
        pa.lila,

        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,
        b.id_puskesmas,

        p.nama_puskesmas

    FROM hasil_deteksi AS hd

    INNER JOIN pengukuran_antropometri AS pa
        ON hd.id_pengukuran = pa.id_pengukuran

    /*
    |--------------------------------------------------------------------------
    | Hanya hasil terbaru untuk setiap balita pada tabel utama
    |--------------------------------------------------------------------------
    | Riwayat lama tetap tersimpan di hasil_deteksi dan dapat dilihat melalui
    | tombol Riwayat. Pemilihan hasil terbaru mengikuti tanggal pengukuran,
    | kemudian id_deteksi sebagai penentu jika tanggalnya sama.
    */
    INNER JOIN (
        SELECT
            pa_latest.id_balita,
            hd_latest.id_deteksi
        FROM hasil_deteksi AS hd_latest
        INNER JOIN pengukuran_antropometri AS pa_latest
            ON hd_latest.id_pengukuran = pa_latest.id_pengukuran
        WHERE NOT EXISTS (
            SELECT 1
            FROM hasil_deteksi AS hd_newer
            INNER JOIN pengukuran_antropometri AS pa_newer
                ON hd_newer.id_pengukuran = pa_newer.id_pengukuran
            WHERE pa_newer.id_balita = pa_latest.id_balita
              AND (
                    pa_newer.tanggal_pengukuran > pa_latest.tanggal_pengukuran
                    OR (
                        pa_newer.tanggal_pengukuran = pa_latest.tanggal_pengukuran
                        AND hd_newer.id_deteksi > hd_latest.id_deteksi
                    )
              )
        )
    ) AS terbaru
        ON terbaru.id_deteksi = hd.id_deteksi

    INNER JOIN balita AS b
        ON pa.id_balita = b.id_balita

    LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas
";

$filterCari = "
    (
        b.nama_balita LIKE ?
        OR b.nik_balita LIKE ?
        OR hd.status_gizi LIKE ?
        OR hd.status_stunting LIKE ?
        OR hd.status_verifikasi LIKE ?
    )
";

/*
|--------------------------------------------------------------------------
| Mengambil data berdasarkan role
|--------------------------------------------------------------------------
|
| Orang Tua         : anak sendiri.
| Gizi/KIA/Kapus    : Puskesmas akun sendiri.
| Dinkes            : seluruh Puskesmas.
| Status otomatis tetap ditampilkan walaupun belum diverifikasi Ahli Gizi.
|
*/

if ($roleAktif === "orang_tua") {

    if ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE b.id_user = ?
            AND " . $filterCari . "
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian hasil deteksi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssss",
            $idUserAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE b.id_user = ?
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil hasil deteksi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idUserAktif
        );
    }

} elseif ($roleBerbasisPuskesmas) {

    if ($puskesmasBelumTerhubung) {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE 1 = 0
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan hasil deteksi."
            );
        }

    } elseif ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE b.id_puskesmas = ?
            AND " . $filterCari . "
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian hasil deteksi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssss",
            $idPuskesmasAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE b.id_puskesmas = ?
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil hasil deteksi: "
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
    | Dinkes dapat melihat seluruh wilayah dan menggunakan filter:
    | - Puskesmas
    | - Bulan deteksi
    | - Tahun deteksi
    | - Pencarian
    |
    */

    $stmt = mysqli_prepare(
        $conn,
        $selectDeteksi . "
        WHERE
            (? = 0 OR b.id_puskesmas = ?)
        AND
            (? = 0 OR MONTH(hd.tanggal_deteksi) = ?)
        AND
            (? = 0 OR YEAR(hd.tanggal_deteksi) = ?)
        AND
            (
                ? = ''
                OR " . $filterCari . "
            )
        ORDER BY hd.id_deteksi DESC"
    );

    if (!$stmt) {
        die(
            "Gagal menyiapkan filter hasil deteksi Dinkes: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiiiiissssss",
        $filterPuskesmas,
        $filterPuskesmas,
        $filterBulan,
        $filterBulan,
        $filterTahun,
        $filterTahun,
        $cari,
        $kataKunci,
        $kataKunci,
        $kataKunci,
        $kataKunci,
        $kataKunci
    );
}

mysqli_stmt_execute($stmt);

$query =
    mysqli_stmt_get_result($stmt);

if (!$query) {
    die(
        "Gagal membaca hasil deteksi."
    );
}

$totalData =
    mysqli_num_rows($query);

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
                        <i class="bi bi-clipboard2-pulse me-2"></i>
                        Hasil Deteksi Stunting
                    </h4>

                    <small class="text-muted">
                        Menampilkan hasil terbaru setiap balita. Hasil bulan sebelumnya
                        tetap tersimpan dan dapat dilihat melalui menu Riwayat.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        $roleBerbasisPuskesmas
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex align-items-center px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= htmlspecialchars(
                                $namaPuskesmasAktif,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </span>

                    <?php endif; ?>

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if (
                        $roleAktif === "petugas_gizi"
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <a
                            href="../skrining/hasil_skrining.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-activity"></i>
                            Pilih Data untuk Analisis
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if (
                    $roleBerbasisPuskesmas
                    && $puskesmasBelumTerhubung
                ): ?>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Akun ini belum terhubung dengan Puskesmas.
                        Data hasil deteksi wilayah tidak dapat ditampilkan.
                    </div>

                <?php endif; ?>

                <?php if (isset($_GET["pesan"])): ?>

                    <?php if ($_GET["pesan"] === "analisis_berhasil"): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Hasil deteksi berhasil disimpan.
                        </div>

                    <?php elseif ($_GET["pesan"] === "verifikasi_berhasil"): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Hasil deteksi berhasil diverifikasi oleh Ahli Gizi.
                        </div>

                    <?php elseif ($_GET["pesan"] === "edit_berhasil"): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Hasil deteksi berhasil diperbarui.
                        </div>

                    <?php elseif ($_GET["pesan"] === "tidak_ditemukan"): ?>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Data hasil deteksi tidak ditemukan.
                        </div>

                    <?php elseif ($_GET["pesan"] === "gagal"): ?>

                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle me-1"></i>
                            Proses deteksi gagal dilakukan.
                        </div>

                    <?php elseif ($_GET["pesan"] === "pemeriksaan_ulang_diperlukan"): ?>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Data antropometri perlu diperiksa ulang oleh Kader. Ahli Gizi tetap dapat melakukan verifikasi dengan memberikan catatan.
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

                <?php if ($roleAktif === "dinkes"): ?>

                    <form
                        method="GET"
                        class="row g-2 mb-3"
                    >

                        <div class="col-12 col-lg-4">

                            <label
                                for="cari"
                                class="form-label small text-muted mb-1"
                            >
                                Pencarian
                            </label>

                            <input
                                type="text"
                                id="cari"
                                name="cari"
                                class="form-control"
                                placeholder="Cari nama, NIK, status gizi, atau status stunting"
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >

                        </div>

                        <div class="col-12 col-md-4 col-lg-3">

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
                                        <?= htmlspecialchars(
                                            $puskesmasFilter[
                                                "nama_puskesmas"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-6 col-md-4 col-lg-2">

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
                                ?>

                                <?php foreach (
                                    $namaBulan
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

                        <div class="col-6 col-md-2 col-lg-1">

                            <label
                                class="form-label small text-muted mb-1 d-block"
                            >
                                &nbsp;
                            </label>

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                                title="Terapkan filter"
                            >
                                <i class="bi bi-funnel"></i>
                            </button>

                        </div>

                        <div class="col-6 col-md-2 col-lg-12">

                            <a
                                href="hasil_deteksi.php"
                                class="btn btn-light"
                            >
                                <i class="bi bi-arrow-counterclockwise"></i>
                                Reset Filter
                            </a>

                        </div>

                    </form>

                <?php else: ?>

                    <form
                        method="GET"
                        class="row g-2 mb-3"
                    >

                        <div class="col-12 col-md-8">

                            <input
                                type="text"
                                name="cari"
                                class="form-control"
                                placeholder="Cari nama, NIK, status gizi, atau status stunting"
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >

                        </div>

                        <div class="col-6 col-md-2">

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                <i class="bi bi-search"></i>
                                Cari
                            </button>

                        </div>

                        <div class="col-6 col-md-2">

                            <a
                                href="hasil_deteksi.php"
                                class="btn btn-light w-100"
                            >
                                <i class="bi bi-arrow-counterclockwise"></i>
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
                        hasil deteksi
                    </span>

                    <?php if ($roleAktif !== "petugas_gizi"): ?>

                        <span class="badge badge-info">
                            <i class="bi bi-eye"></i>
                            Mode lihat
                        </span>

                    <?php endif; ?>

                </div>

                <div
                    class="status-legend d-flex flex-wrap
                    align-items-center gap-2 mb-3"
                >

                    <span class="text-muted small me-1">
                        Keterangan status:
                    </span>

                    <span class="badge bg-success">
                        Normal
                    </span>

                    <span class="badge bg-warning text-dark">
                        Risiko Stunting
                    </span>

                    <span class="badge bg-danger">
                        Stunting
                    </span>

                    <span class="badge bg-dark text-white">
                        Stunting Berat
                    </span>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th class="text-center">
                                    No
                                </th>

                                <th class="text-center">
                                    Pengukuran Terakhir
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

                                <th class="text-center">
                                    JK
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
                                    Status Gizi
                                </th>

                                <th class="text-center">
                                    Status Stunting
                                </th>

                                <th class="text-center">
                                    Verifikasi
                                </th>

                                <th class="text-center">
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($totalData > 0): ?>

                            <?php
                            $no = 1;

                            while ($data = mysqli_fetch_assoc($query)):

                                $statusStunting = strtolower(
                                    trim(
                                        $data["status_stunting"] ?? ""
                                    )
                                );

                                $kelasStunting = "bg-secondary";

                                if (
                                    $statusStunting === "normal"
                                    || $statusStunting === "normal/sehat"
                                    || $statusStunting === "tidak stunting"
                                ) {

                                    $kelasStunting = "bg-success";

                                } elseif (
                                    $statusStunting === "risiko stunting"
                                ) {

                                    $kelasStunting =
                                        "bg-warning text-dark";

                                } elseif (
                                    $statusStunting === "stunting"
                                    || $statusStunting === "pendek"
                                ) {

                                    $kelasStunting = "bg-danger";

                                } elseif (
                                    $statusStunting === "stunting berat"
                                    || $statusStunting === "severely stunted"
                                    || $statusStunting === "sangat pendek"
                                ) {

                                    $kelasStunting =
                                        "bg-dark text-white";
                                }

                                $statusVerifikasi =
                                    trim(
                                        (string) (
                                            $data["status_verifikasi"]
                                            ?? ""
                                        )
                                    );

                                if ($statusVerifikasi === "") {
                                    $statusVerifikasi =
                                        "Belum diverifikasi";
                                }

                                $statusVerifikasiNormal =
                                    strtolower(
                                        $statusVerifikasi
                                    );

                                $kelasVerifikasi =
                                    "bg-secondary";

                                if (
                                    $statusVerifikasiNormal
                                    === "sudah diverifikasi"
                                ) {

                                    $kelasVerifikasi =
                                        "bg-success";

                                } elseif (
                                    $statusVerifikasiNormal
                                    === "perlu pemeriksaan ulang"
                                ) {

                                    $kelasVerifikasi =
                                        "bg-warning text-dark";
                                }

                                $sudahDiverifikasi =
                                    $statusVerifikasiNormal === "sudah diverifikasi";

                                $statusGiziNormal =
                                    strtolower(
                                        trim(
                                            (string) (
                                                $data["status_gizi"]
                                                ?? ""
                                            )
                                        )
                                    );

                                $perluPemeriksaanAntropometri =
                                    in_array(
                                        $statusGiziNormal,
                                        [
                                            "perlu pemeriksaan",
                                            "perlu pemeriksaan ulang",
                                            "data antropometri perlu diperiksa"
                                        ],
                                        true
                                    );
                            ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $no++; ?>
                                    </td>

                                    <td class="text-center">
                                        <?= !empty(
                                            $data["tanggal_pengukuran"]
                                        )
                                            ? date(
                                                "d-m-Y",
                                                strtotime(
                                                    $data[
                                                        "tanggal_pengukuran"
                                                    ]
                                                )
                                            )
                                            : "-"; ?>
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
                                                <?= htmlspecialchars(
                                                    $data[
                                                        "nama_balita"
                                                    ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </strong>

                                        </div>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $data["nik_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $data["nama_puskesmas"]
                                                ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars(
                                            $data["jenis_kelamin"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= (int) (
                                            $data["umur_bulan"] ?? 0
                                        ); ?>
                                        bulan
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars(
                                            $data[
                                                "berat_badan"
                                            ] ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        kg
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars(
                                            $data[
                                                "tinggi_panjang_badan"
                                            ] ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        cm
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars(
                                            $perluPemeriksaanAntropometri
                                                ? "Data Antropometri Perlu Diperiksa"
                                                : ($data["status_gizi"] ?? "Belum tersedia"),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge <?= $kelasStunting; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $data[
                                                    "status_stunting"
                                                ]
                                                    ?? "Belum tersedia",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge <?= $kelasVerifikasi; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $statusVerifikasi,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

                                            <a
                                                href="detail_deteksi.php?id=<?= (int) $data["id_deteksi"]; ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Detail
                                            </a>

                                            <a
                                                href="riwayat_deteksi.php?id_balita=<?= (int) $data["id_balita"]; ?>"
                                                class="btn btn-secondary btn-sm"
                                            >
                                                <i class="bi bi-clock-history"></i>
                                                Riwayat
                                            </a>

                                            <?php if ($roleAktif === "petugas_gizi"): ?>

                                                <?php if ($perluPemeriksaanAntropometri): ?>

                                                    <span
                                                        class="badge bg-warning text-dark"
                                                        title="Data antropometri perlu diperiksa ulang oleh Kader"
                                                    >
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        Perlu Pemeriksaan Ulang
                                                    </span>

                                                <?php endif; ?>

                                                <?php if (!$sudahDiverifikasi): ?>
                                                    <a
                                                        href="verifikasi_deteksi.php?id=<?= (int) $data["id_deteksi"]; ?>&kembali=deteksi"
                                                        class="btn btn-success btn-sm"
                                                    >
                                                        <i class="bi bi-check2-circle"></i>
                                                        Verifikasi
                                                    </a>
                                                <?php endif; ?>

                                                <a
                                                    href="analisis_deteksi.php?id_balita=<?= (int) $data["id_balita"]; ?>&kembali=deteksi"
                                                    class="btn btn-primary btn-sm"
                                                    onclick="return confirm('Analisis ulang akan menghitung kembali status dan mengubah verifikasi menjadi Belum diverifikasi. Lanjutkan?');"
                                                >
                                                    <i class="bi bi-arrow-repeat"></i>
                                                    Analisis Ulang
                                                </a>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="13">

                                    <div class="empty-state">

                                        <div class="empty-state-icon">
                                            <i
                                                class="bi
                                                bi-clipboard2-pulse"
                                            ></i>
                                        </div>

                                        <h3>
                                            Belum ada hasil deteksi
                                        </h3>

                                        <p>
                                            Data hasil deteksi
                                            stunting belum tersedia.
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

<?php

mysqli_stmt_close($stmt);

require_once "../includes/footer.php";

?>
