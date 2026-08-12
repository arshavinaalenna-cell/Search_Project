<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

date_default_timezone_set("Asia/Jakarta");
@mysqli_query($conn, "SET time_zone = '+07:00'");

cekRole([
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$bolehKelolaPengukuran =
    $roleAktif === "kader";

/*
|--------------------------------------------------------------------------
| Puskesmas yang terhubung dengan akun Kader
|--------------------------------------------------------------------------
|
| Kader tidak memilih Puskesmas secara manual. Seluruh data antropometri
| yang ditampilkan otomatis dibatasi berdasarkan pengguna.id_puskesmas
| milik akun Kader yang sedang login.
|
*/

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

if ($roleAktif === "kader") {
    $stmtPuskesmasAktif = mysqli_prepare(
        $conn,
        "SELECT
            u.id_puskesmas,
            p.nama_puskesmas
         FROM pengguna AS u
         LEFT JOIN puskesmas AS p
            ON u.id_puskesmas = p.id_puskesmas
         WHERE u.id_user = ?
           AND u.role = 'kader'
         LIMIT 1"
    );

    if (!$stmtPuskesmasAktif) {
        die(
            "Gagal menyiapkan data Puskesmas akun: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtPuskesmasAktif,
        "i",
        $idUserAktif
    );

    if (!mysqli_stmt_execute($stmtPuskesmasAktif)) {
        die(
            "Gagal mengambil data Puskesmas akun: "
            . mysqli_stmt_error($stmtPuskesmasAktif)
        );
    }

    $hasilPuskesmasAktif =
        mysqli_stmt_get_result($stmtPuskesmasAktif);

    $dataPuskesmasAktif =
        mysqli_fetch_assoc($hasilPuskesmasAktif);

    if (
        !$dataPuskesmasAktif
        || empty($dataPuskesmasAktif["id_puskesmas"])
    ) {
        $puskesmasBelumTerhubung = true;
    } else {
        $idPuskesmasAktif =
            (int) $dataPuskesmasAktif["id_puskesmas"];

        $namaPuskesmasAktif = trim(
            (string) ($dataPuskesmasAktif["nama_puskesmas"] ?? "")
        );
    }

    mysqli_stmt_close($stmtPuskesmasAktif);
}

/*
|--------------------------------------------------------------------------
| Judul halaman
|--------------------------------------------------------------------------
*/

$judulHalaman =
    "Data Pengukuran Antropometri | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Filter data pengukuran
|--------------------------------------------------------------------------
|
| Filter tidak mengubah pembatasan wilayah. Khusus Kader, data tetap hanya
| berasal dari Puskesmas yang terhubung dengan akun yang sedang login.
|
*/

$kataKunci = trim((string) ($_GET["cari"] ?? ""));
$bulanFilter = (int) ($_GET["bulan"] ?? 0);
$tahunFilter = (int) ($_GET["tahun"] ?? 0);
$urutanFilter = (string) ($_GET["urutan"] ?? "terbaru");

$tampilanData = (string) ($_GET["tampilan"] ?? (
    $roleAktif === "kader" ? "terbaru" : "riwayat"
));

if (!in_array($tampilanData, ["terbaru", "riwayat"], true)) {
    $tampilanData = $roleAktif === "kader" ? "terbaru" : "riwayat";
}

if ($bulanFilter < 1 || $bulanFilter > 12) {
    $bulanFilter = 0;
}

if ($tahunFilter < 1900 || $tahunFilter > 2100) {
    $tahunFilter = 0;
}

if (!in_array($urutanFilter, ["terbaru", "terlama"], true)) {
    $urutanFilter = "terbaru";
}

$filterAktif =
    $kataKunci !== ""
    || $bulanFilter > 0
    || $tahunFilter > 0
    || $urutanFilter === "terlama";

/*
|--------------------------------------------------------------------------
| Mengambil data pengukuran beserta nama balita
|--------------------------------------------------------------------------
|
| Orang Tua hanya dapat melihat data pengukuran anak yang terhubung
| dengan akun miliknya melalui balita.id_user.
|
| Kader hanya dapat melihat data balita dari Puskesmas yang terhubung
| dengan akun miliknya melalui pengguna.id_puskesmas.
|
| Filter pencarian, bulan, tahun, dan urutan diterapkan setelah pembatasan
| akses wilayah/akun tersebut.
|
*/

$whereAkses = [];

if ($roleAktif === "orang_tua") {
    $whereAkses[] = "b.id_user = " . $idUserAktif;
} elseif ($roleAktif === "kader") {
    $whereAkses[] = "b.id_puskesmas = " . $idPuskesmasAktif;
}

$whereFilter = $whereAkses;

if ($kataKunci !== "") {
    $kataKunciSql = mysqli_real_escape_string($conn, $kataKunci);
    $whereFilter[] = "b.nama_balita LIKE '%" . $kataKunciSql . "%'";
}

if ($bulanFilter > 0) {
    // Gunakan representasi teks agar aman jika database lama masih memiliki
    // tanggal nol (0000-00-00) dan MySQL berjalan dalam strict mode.
    $bulanSql = str_pad((string) $bulanFilter, 2, '0', STR_PAD_LEFT);
    $whereFilter[] = "SUBSTRING(CAST(p.tanggal_pengukuran AS CHAR), 6, 2) = '" . $bulanSql . "'";
}

if ($tahunFilter > 0) {
    // Hindari YEAR() pada nilai tanggal nol. Bandingkan bagian tahun sebagai teks.
    $whereFilter[] = "SUBSTRING(CAST(p.tanggal_pengukuran AS CHAR), 1, 4) = '" . $tahunFilter . "'";
}

/*
| Untuk Kader, tampilan default adalah satu pengukuran TERBARU per balita.
| Riwayat lengkap tetap bisa dibuka melalui tombol Riwayat Semua Pengukuran.
*/
if ($roleAktif === "kader" && $tampilanData === "terbaru") {
    $whereFilter[] = "p.id_pengukuran = (
        SELECT p2.id_pengukuran
        FROM pengukuran_antropometri AS p2
        WHERE p2.id_balita = p.id_balita
        ORDER BY
            CAST(p2.tanggal_pengukuran AS CHAR) DESC,
            p2.id_pengukuran DESC
        LIMIT 1
    )";
}

$whereSql = "";

if (!empty($whereFilter)) {
    $whereSql = " WHERE " . implode(" AND ", $whereFilter);
}

$orderSql = $urutanFilter === "terlama"
    ? "ASC"
    : "DESC";

$sql = "
    SELECT
        p.id_pengukuran,
        p.id_balita,
        p.tanggal_pengukuran,
        p.umur_bulan,
        p.berat_badan,
        p.tinggi_panjang_badan,
        p.lingkar_kepala,
        p.lila,
        b.nama_balita,
        EXISTS (
            SELECT 1
            FROM hasil_deteksi AS hd
            WHERE hd.id_pengukuran = p.id_pengukuran
        ) AS digunakan_deteksi
    FROM pengukuran_antropometri AS p
    INNER JOIN balita AS b
        ON p.id_balita = b.id_balita
    " . $whereSql . "
    ORDER BY
        p.tanggal_pengukuran " . $orderSql . ",
        p.id_pengukuran " . $orderSql;

$query = mysqli_query($conn, $sql);

if (!$query) {
    die(
        "Gagal mengambil data pengukuran: "
        . mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| Daftar tahun untuk dropdown filter
|--------------------------------------------------------------------------
|
| Tahun yang ditampilkan hanya tahun yang memiliki data pada cakupan akun.
| Untuk Kader berarti hanya tahun dari data balita Puskesmas terhubung.
|
*/

$whereTahun = $whereAkses;
$whereTahun[] = "p.tanggal_pengukuran IS NOT NULL";

// Jangan memakai YEAR(), perbandingan DATE, atau literal 0000-00-00 di SQL.
// Beberapa instalasi MySQL/MariaDB dengan strict mode akan melempar
// mysqli_sql_exception ketika data lama masih berisi tanggal nol.
$sqlTahun = "
    SELECT DISTINCT
        CAST(p.tanggal_pengukuran AS CHAR) AS tanggal_filter
    FROM pengukuran_antropometri AS p
    INNER JOIN balita AS b
        ON p.id_balita = b.id_balita
    WHERE " . implode(" AND ", $whereTahun) . "
";

$queryTahun = mysqli_query($conn, $sqlTahun);
$daftarTahun = [];

if ($queryTahun) {
    while ($rowTahun = mysqli_fetch_assoc($queryTahun)) {
        $tanggalFilter = (string) ($rowTahun["tanggal_filter"] ?? "");

        // Hanya ambil tahun valid 1000-9999. Nilai 0000-00-00 otomatis diabaikan.
        if (preg_match('/^([1-9][0-9]{3})-[0-9]{2}-[0-9]{2}$/', $tanggalFilter, $cocok)) {
            $daftarTahun[(int) $cocok[1]] = (int) $cocok[1];
        }
    }
}

rsort($daftarTahun, SORT_NUMERIC);

$namaBulan = [
    1 => "Januari",
    2 => "Februari",
    3 => "Maret",
    4 => "April",
    5 => "Mei",
    6 => "Juni",
    7 => "Juli",
    8 => "Agustus",
    9 => "September",
    10 => "Oktober",
    11 => "November",
    12 => "Desember"
];

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function aman($nilai): string
{
    return htmlspecialchars(
        (string) ($nilai ?? "-"),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Fungsi menampilkan angka dan satuan
|--------------------------------------------------------------------------
*/

function tampilkanUkuran($nilai, string $satuan): string
{
    if (
        $nilai === null
        || $nilai === ""
    ) {
        return "-";
    }

    return aman($nilai) . " " . $satuan;
}

/*
|--------------------------------------------------------------------------
| Fungsi menampilkan tanggal
|--------------------------------------------------------------------------
*/

function formatTanggal($tanggal): string
{
    if (
        empty($tanggal)
        || $tanggal === "0000-00-00"
    ) {
        return "-";
    }

    $waktu = strtotime($tanggal);

    if ($waktu === false) {
        return aman($tanggal);
    }

    return date("d-m-Y", $waktu);
}

function statusPemantauanPengukuran($tanggal): array
{
    $tanggal = trim((string) $tanggal);

    if (
        $tanggal === ""
        || !preg_match(
            '/^([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})$/',
            $tanggal,
            $cocok
        )
    ) {
        return [
            "label" => "Belum pernah diukur",
            "kelas" => "danger"
        ];
    }

    $tahun = (int) $cocok[1];
    $bulan = (int) $cocok[2];

    if (
        $tahun === (int) date("Y")
        && $bulan === (int) date("n")
    ) {
        return [
            "label" => "Sudah diukur bulan ini",
            "kelas" => "success"
        ];
    }

    $indeksSekarang = ((int) date("Y") * 12) + (int) date("n");
    $indeksTerakhir = ($tahun * 12) + $bulan;
    $selisihBulan = max(0, $indeksSekarang - $indeksTerakhir);

    if ($selisihBulan >= 2) {
        return [
            "label" => "Belum diukur ≥2 bulan",
            "kelas" => "danger"
        ];
    }

    return [
        "label" => "Belum diukur bulan ini",
        "kelas" => "warning"
    ];
}

/*
|--------------------------------------------------------------------------
| Pengaturan tampilan berdasarkan role
|--------------------------------------------------------------------------
|
| Hanya Kader yang melihat fitur tambah, edit, dan hapus.
| Role lain melihat data pengukuran dalam mode read-only.
| Orang Tua hanya melihat data anak yang terhubung dengan akunnya.
|
*/

/*
|--------------------------------------------------------------------------
| Memanggil template utama
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php

        $pesan = $_GET["pesan"] ?? "";

        $jenisAlert = "";
        $isiPesan = "";

        switch ($pesan) {
            case "tambah_berhasil":
                $jenisAlert = "success";
                $isiPesan =
                    "Data pengukuran berhasil ditambahkan.";
                break;

            case "edit_berhasil":
                $jenisAlert = "success";
                $isiPesan =
                    "Data pengukuran berhasil diperbarui.";
                break;

            case "hapus_berhasil":
                $jenisAlert = "success";
                $isiPesan =
                    "Data pengukuran berhasil dihapus.";
                break;

            case "tidak_ditemukan":
                $jenisAlert = "warning";
                $isiPesan =
                    "Data pengukuran tidak ditemukan.";
                break;

            case "id_tidak_valid":
                $jenisAlert = "warning";
                $isiPesan =
                    "ID pengukuran tidak valid.";
                break;

            case "data_digunakan":
                $jenisAlert = "warning";
                $isiPesan =
                    "Data pengukuran tidak dapat dihapus karena sudah digunakan pada hasil deteksi.";
                break;

            case "gagal_hapus":
                $jenisAlert = "danger";
                $isiPesan =
                    "Data pengukuran gagal dihapus.";
                break;

            case "akses_tidak_valid":
                $jenisAlert = "danger";
                $isiPesan =
                    "Permintaan penghapusan tidak valid.";
                break;
        }

        ?>

        <?php if ($isiPesan !== ""): ?>

            <div
                class="alert alert-<?= htmlspecialchars(
                    $jenisAlert,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?> alert-dismissible fade show"
                role="alert"
            >
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

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        <?= $bolehKelolaPengukuran
                            ? "Input Antropometri"
                            : "Grafik dan Riwayat Pertumbuhan"; ?>
                    </h4>

                    <small class="text-muted">
                        <?php if ($bolehKelolaPengukuran): ?>
                            Kelola hasil pengukuran fisik balita dari kegiatan Posyandu.
                        <?php elseif ($roleAktif === "orang_tua"): ?>
                            Lihat riwayat pengukuran pertumbuhan anak yang terhubung dengan akun Anda.
                        <?php else: ?>
                            Lihat riwayat pengukuran pertumbuhan balita.
                        <?php endif; ?>
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if ($bolehKelolaPengukuran): ?>

                        <a
                            href="tambah_pengukuran.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Tambah Pengukuran
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if ($roleAktif === "kader"): ?>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a
                            href="data_pengukuran.php?tampilan=terbaru"
                            class="btn btn-sm <?= $tampilanData === "terbaru" ? "btn-primary" : "btn-light"; ?>"
                        >
                            <i class="bi bi-person-check"></i>
                            Pengukuran Terbaru
                        </a>

                        <a
                            href="data_pengukuran.php?tampilan=riwayat"
                            class="btn btn-sm <?= $tampilanData === "riwayat" ? "btn-primary" : "btn-light"; ?>"
                        >
                            <i class="bi bi-clock-history"></i>
                            Riwayat Semua Pengukuran
                        </a>
                    </div>

                <?php endif; ?>

                <div
                    class="d-flex flex-wrap
                    justify-content-between align-items-center
                    gap-2 mb-3"
                >
                    <span class="text-muted small">
                        <?= ($roleAktif === "kader" && $tampilanData === "terbaru")
                            ? "Balita dengan pengukuran terbaru:"
                            : "Total data:"; ?>
                        <strong>
                            <?= mysqli_num_rows($query); ?>
                        </strong>
                        <?= ($roleAktif === "kader" && $tampilanData === "terbaru")
                            ? "balita"
                            : "pengukuran"; ?>
                    </span>

                    <?php if (!$bolehKelolaPengukuran): ?>

                        <span class="badge badge-info">
                            <i class="bi bi-eye"></i>
                            Mode lihat
                        </span>

                    <?php endif; ?>

                </div>

                <?php if ($roleAktif === "kader"): ?>

                    <?php if ($puskesmasBelumTerhubung): ?>

                        <div class="alert alert-warning mb-4" role="alert">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-exclamation-triangle"></i>
                                <div>
                                    <strong>Puskesmas belum terhubung.</strong>
                                    <div class="small mt-1">
                                        Data balita tidak ditampilkan karena akun Kader ini belum memiliki Puskesmas.
                                        Hubungkan akun Kader ke Puskesmas terlebih dahulu.
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="alert alert-info mb-4" role="alert">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <span class="small text-muted d-block">
                                        Data otomatis mengikuti Puskesmas akun Kader
                                    </span>
                                    <strong>
                                        <i class="bi bi-hospital"></i>
                                        <?= aman(
                                            $namaPuskesmasAktif !== ""
                                                ? $namaPuskesmasAktif
                                                : "Puskesmas terhubung"
                                        ); ?>
                                    </strong>
                                </div>
                                <span class="badge badge-info">
                                    <i class="bi bi-lock"></i>
                                    Wilayah terkunci
                                </span>
                            </div>
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

                <form method="GET" class="mb-4">

                    <?php if ($roleAktif === "kader"): ?>
                        <input
                            type="hidden"
                            name="tampilan"
                            value="<?= aman($tampilanData); ?>"
                        >
                    <?php endif; ?>

                    <div class="row g-2 align-items-end">

                        <div class="col-12 col-lg-4">
                            <label for="cari" class="form-label small text-muted mb-1">
                                Cari Balita
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="cari"
                                    name="cari"
                                    value="<?= aman($kataKunci); ?>"
                                    placeholder="Ketik nama balita..."
                                >
                            </div>
                        </div>

                        <div class="col-6 col-md-3 col-lg-2">
                            <label for="bulan" class="form-label small text-muted mb-1">
                                Bulan
                            </label>
                            <select
                                class="form-select"
                                id="bulan"
                                name="bulan"
                            >
                                <option value="0">Semua Bulan</option>
                                <?php foreach ($namaBulan as $nomorBulan => $labelBulan): ?>
                                    <option
                                        value="<?= $nomorBulan; ?>"
                                        <?= $bulanFilter === $nomorBulan ? "selected" : ""; ?>
                                    >
                                        <?= aman($labelBulan); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-3 col-lg-2">
                            <label for="tahun" class="form-label small text-muted mb-1">
                                Tahun
                            </label>
                            <select
                                class="form-select"
                                id="tahun"
                                name="tahun"
                            >
                                <option value="0">Semua Tahun</option>

                                <?php foreach ($daftarTahun as $tahun): ?>
                                    <option
                                        value="<?= $tahun; ?>"
                                        <?= $tahunFilter === $tahun ? "selected" : ""; ?>
                                    >
                                        <?= $tahun; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-3 col-lg-2">
                            <label for="urutan" class="form-label small text-muted mb-1">
                                Urutan
                            </label>
                            <select
                                class="form-select"
                                id="urutan"
                                name="urutan"
                            >
                                <option
                                    value="terbaru"
                                    <?= $urutanFilter === "terbaru" ? "selected" : ""; ?>
                                >
                                    Terbaru
                                </option>
                                <option
                                    value="terlama"
                                    <?= $urutanFilter === "terlama" ? "selected" : ""; ?>
                                >
                                    Terlama
                                </option>
                            </select>
                        </div>

                        <div class="col-12 col-md-3 col-lg-2">
                            <div class="d-flex gap-2">
                                <button
                                    type="submit"
                                    class="btn btn-primary flex-fill"
                                >
                                    <i class="bi bi-funnel"></i>
                                    Filter
                                </button>

                                <a
                                    href="data_pengukuran.php<?= $roleAktif === "kader" ? "?tampilan=" . urlencode($tampilanData) : ""; ?>"
                                    class="btn btn-light"
                                    title="Reset filter"
                                >
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            </div>
                        </div>

                    </div>

                </form>

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

                                <th class="text-center">
                                    Tanggal
                                </th>

                                <?php if ($roleAktif === "kader" && $tampilanData === "terbaru"): ?>
                                    <th class="text-center">
                                        Status Pemantauan
                                    </th>
                                <?php endif; ?>

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

                                <?php if ($bolehKelolaPengukuran): ?>

                                    <th class="text-center">
                                        Aksi
                                    </th>

                                <?php endif; ?>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (mysqli_num_rows($query) > 0): ?>

                            <?php
                            $nomor = 1;

                            while (
                                $data = mysqli_fetch_assoc($query)
                            ):
                                $idPengukuran =
                                    (int) $data["id_pengukuran"];

                                $digunakanDeteksi =
                                    (int) (
                                        $data["digunakan_deteksi"]
                                        ?? 0
                                    ) === 1;
                            ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $nomor++; ?>
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
                                                <?= aman(
                                                    $data["nama_balita"]
                                                ); ?>
                                            </strong>

                                        </div>

                                    </td>

                                    <td class="text-center">
                                        <?= formatTanggal(
                                            $data[
                                                "tanggal_pengukuran"
                                            ]
                                        ); ?>
                                    </td>

                                    <?php if ($roleAktif === "kader" && $tampilanData === "terbaru"): ?>
                                        <?php
                                        $statusPantau = statusPemantauanPengukuran(
                                            $data["tanggal_pengukuran"] ?? null
                                        );
                                        ?>
                                        <td class="text-center">
                                            <span class="badge bg-<?= aman($statusPantau["kelas"]); ?>">
                                                <?= aman($statusPantau["label"]); ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>

                                    <td class="text-center">
                                        <?= aman(
                                            $data["umur_bulan"]
                                        ); ?>
                                        bulan
                                    </td>

                                    <td class="text-center">
                                        <?= tampilkanUkuran(
                                            $data["berat_badan"],
                                            "kg"
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= tampilkanUkuran(
                                            $data[
                                                "tinggi_panjang_badan"
                                            ],
                                            "cm"
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= tampilkanUkuran(
                                            $data["lingkar_kepala"],
                                            "cm"
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= tampilkanUkuran(
                                            $data["lila"],
                                            "cm"
                                        ); ?>
                                    </td>

                                    <?php if (
                                        $bolehKelolaPengukuran
                                    ): ?>

                                        <td>

                                            <div
                                                class="table-actions
                                                justify-content-center"
                                            >

                                                <a
                                                    href="edit_pengukuran.php?id=<?= $idPengukuran; ?>"
                                                    class="btn btn-warning btn-sm"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-pencil-square"
                                                    ></i>
                                                    Edit
                                                </a>

                                                <?php if ($digunakanDeteksi): ?>

                                                    <button
                                                        type="button"
                                                        class="btn btn-light btn-sm"
                                                        disabled
                                                        title="Pengukuran sudah digunakan pada hasil deteksi dan tidak dapat dihapus."
                                                    >
                                                        <i
                                                            class="bi
                                                            bi-lock"
                                                        ></i>
                                                        Dipakai Deteksi
                                                    </button>

                                                <?php else: ?>

                                                    <form
                                                        action="hapus_pengukuran.php"
                                                        method="POST"
                                                        class="d-inline"
                                                        onsubmit="return confirm(
                                                            'Yakin ingin menghapus data pengukuran ini?'
                                                        );"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_pengukuran"
                                                            value="<?= $idPengukuran; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger btn-sm"
                                                        >
                                                            <i
                                                                class="bi
                                                                bi-trash3"
                                                            ></i>
                                                            Hapus
                                                        </button>
                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    <?php endif; ?>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="<?= $bolehKelolaPengukuran
                                        ? (($roleAktif === "kader" && $tampilanData === "terbaru") ? "10" : "9")
                                        : "8"; ?>"
                                >

                                    <div class="empty-state">

                                        <div class="empty-state-icon">
                                            <i
                                                class="bi
                                                bi-clipboard2-pulse"
                                            ></i>
                                        </div>

                                        <h3>
                                            <?= $filterAktif
                                                ? "Data tidak ditemukan"
                                                : "Belum ada data pengukuran"; ?>
                                        </h3>

                                        <p>
                                            <?php if ($filterAktif): ?>
                                                Tidak ada data yang sesuai dengan pencarian atau filter yang dipilih.
                                            <?php elseif ($bolehKelolaPengukuran): ?>
                                                Tambahkan pengukuran pertama untuk mulai memantau pertumbuhan balita.
                                            <?php elseif ($roleAktif === "orang_tua"): ?>
                                                Belum ada data pengukuran untuk anak yang terhubung dengan akun Anda.
                                            <?php else: ?>
                                                Data pengukuran pertumbuhan balita belum tersedia.
                                            <?php endif; ?>
                                        </p>

                                        <?php if (
                                            $bolehKelolaPengukuran
                                            && !$filterAktif
                                        ): ?>

                                            <a
                                                href="tambah_pengukuran.php"
                                                class="btn btn-primary mt-3"
                                            >
                                                <i
                                                    class="bi
                                                    bi-plus-circle"
                                                ></i>
                                                Tambah Pengukuran
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

require_once "../includes/footer.php";

?>