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

$judulHalaman = "Riwayat Deteksi Stunting | Sistem Deteksi Stunting";
$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$idBalita = filter_input(INPUT_GET, "id_balita", FILTER_VALIDATE_INT);

if (!$idBalita || $idBalita < 1) {
    header("Location: hasil_deteksi.php?pesan=tidak_ditemukan");
    exit;
}

$idPuskesmasAktif = 0;
$roleBerbasisPuskesmas = in_array(
    $roleAktif,
    ["petugas_gizi", "petugas_kia", "kepala_puskesmas"],
    true
);

if ($roleBerbasisPuskesmas) {
    $stmtWilayah = mysqli_prepare(
        $conn,
        "SELECT id_puskesmas FROM pengguna WHERE id_user = ? LIMIT 1"
    );

    if (!$stmtWilayah) {
        die("Gagal memeriksa wilayah akun: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmtWilayah, "i", $idUserAktif);
    mysqli_stmt_execute($stmtWilayah);
    $hasilWilayah = mysqli_stmt_get_result($stmtWilayah);
    $dataWilayah = mysqli_fetch_assoc($hasilWilayah);
    mysqli_stmt_close($stmtWilayah);

    $idPuskesmasAktif = (int) ($dataWilayah["id_puskesmas"] ?? 0);

    if ($idPuskesmasAktif <= 0) {
        header("Location: hasil_deteksi.php?pesan=tidak_ditemukan");
        exit;
    }
}

$sqlBalita = "
    SELECT
        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,
        b.id_puskesmas,
        b.id_user,
        p.nama_puskesmas
    FROM balita AS b
    LEFT JOIN puskesmas AS p
        ON p.id_puskesmas = b.id_puskesmas
    WHERE b.id_balita = ?
";

if ($roleAktif === "orang_tua") {
    $sqlBalita .= " AND b.id_user = ? ";
    $stmtBalita = mysqli_prepare($conn, $sqlBalita . " LIMIT 1");
    mysqli_stmt_bind_param($stmtBalita, "ii", $idBalita, $idUserAktif);
} elseif ($roleBerbasisPuskesmas) {
    $sqlBalita .= " AND b.id_puskesmas = ? ";
    $stmtBalita = mysqli_prepare($conn, $sqlBalita . " LIMIT 1");
    mysqli_stmt_bind_param($stmtBalita, "ii", $idBalita, $idPuskesmasAktif);
} else {
    $stmtBalita = mysqli_prepare($conn, $sqlBalita . " LIMIT 1");
    mysqli_stmt_bind_param($stmtBalita, "i", $idBalita);
}

if (!$stmtBalita) {
    die("Gagal menyiapkan data balita: " . mysqli_error($conn));
}

mysqli_stmt_execute($stmtBalita);
$hasilBalita = mysqli_stmt_get_result($stmtBalita);
$balita = mysqli_fetch_assoc($hasilBalita);
mysqli_stmt_close($stmtBalita);

if (!$balita) {
    header("Location: hasil_deteksi.php?pesan=tidak_ditemukan");
    exit;
}

// Filter riwayat deteksi
$keyword = trim((string) ($_GET["q"] ?? ""));
$filterBulan = trim((string) ($_GET["bulan"] ?? ""));
$filterTahun = trim((string) ($_GET["tahun"] ?? ""));
$filterStatus = trim((string) ($_GET["status_stunting"] ?? ""));
$filterVerifikasi = trim((string) ($_GET["verifikasi"] ?? ""));
$urutan = strtolower(trim((string) ($_GET["urutan"] ?? "terbaru")));

$bulanValid = range(1, 12);
$tahunSekarang = (int) date("Y");

if ($filterBulan !== "" && !in_array((int) $filterBulan, $bulanValid, true)) {
    $filterBulan = "";
}

if ($filterTahun !== "" && (!ctype_digit($filterTahun) || (int) $filterTahun < 2000 || (int) $filterTahun > $tahunSekarang + 1)) {
    $filterTahun = "";
}

$statusStuntingValid = [
    "Normal",
    "Risiko Stunting",
    "Stunting",
    "Stunting Berat"
];

if ($filterStatus !== "" && !in_array($filterStatus, $statusStuntingValid, true)) {
    $filterStatus = "";
}

$verifikasiValid = [
    "Belum diverifikasi",
    "Sudah diverifikasi",
    "Perlu pemeriksaan ulang"
];

if ($filterVerifikasi !== "" && !in_array($filterVerifikasi, $verifikasiValid, true)) {
    $filterVerifikasi = "";
}

if (!in_array($urutan, ["terbaru", "terlama"], true)) {
    $urutan = "terbaru";
}

// Daftar tahun berasal dari riwayat balita ini.
// CAST ke CHAR dipakai agar aman untuk database lama yang masih memiliki tanggal 0000-00-00.
$daftarTahun = [];
$sqlTahun = "
    SELECT DISTINCT SUBSTRING(CAST(pa.tanggal_pengukuran AS CHAR), 1, 4) AS tahun
    FROM hasil_deteksi AS hd
    INNER JOIN pengukuran_antropometri AS pa
        ON pa.id_pengukuran = hd.id_pengukuran
    WHERE pa.id_balita = " . (int) $idBalita . "
    ORDER BY tahun DESC
";

$queryTahun = mysqli_query($conn, $sqlTahun);
if ($queryTahun) {
    while ($rowTahun = mysqli_fetch_assoc($queryTahun)) {
        $tahun = (string) ($rowTahun["tahun"] ?? "");
        if (ctype_digit($tahun) && (int) $tahun >= 2000 && (int) $tahun <= $tahunSekarang + 1) {
            $daftarTahun[] = $tahun;
        }
    }
}
$daftarTahun = array_values(array_unique($daftarTahun));

$kondisiRiwayat = [
    "pa.id_balita = " . (int) $idBalita
];

if ($keyword !== "") {
    $keywordEsc = mysqli_real_escape_string($conn, $keyword);
    $kondisiRiwayat[] = "(
        COALESCE(hd.status_gizi, '') LIKE '%{$keywordEsc}%'
        OR COALESCE(hd.status_stunting, '') LIKE '%{$keywordEsc}%'
        OR COALESCE(hd.status_verifikasi, '') LIKE '%{$keywordEsc}%'
        OR CAST(pa.tanggal_pengukuran AS CHAR) LIKE '%{$keywordEsc}%'
    )";
}

if ($filterBulan !== "") {
    $bulanSql = str_pad((string) ((int) $filterBulan), 2, "0", STR_PAD_LEFT);
    $kondisiRiwayat[] = "SUBSTRING(CAST(pa.tanggal_pengukuran AS CHAR), 6, 2) = '{$bulanSql}'";
}

if ($filterTahun !== "") {
    $tahunSql = mysqli_real_escape_string($conn, $filterTahun);
    $kondisiRiwayat[] = "SUBSTRING(CAST(pa.tanggal_pengukuran AS CHAR), 1, 4) = '{$tahunSql}'";
}

if ($filterStatus !== "") {
    $statusSql = mysqli_real_escape_string($conn, $filterStatus);
    $kondisiRiwayat[] = "LOWER(TRIM(COALESCE(hd.status_stunting, ''))) = LOWER('{$statusSql}')";
}

if ($filterVerifikasi !== "") {
    $verifikasiSql = mysqli_real_escape_string($conn, $filterVerifikasi);
    $kondisiRiwayat[] = "LOWER(TRIM(COALESCE(NULLIF(hd.status_verifikasi, ''), 'Belum diverifikasi'))) = LOWER('{$verifikasiSql}')";
}

$orderSql = $urutan === "terlama"
    ? "ORDER BY pa.tanggal_pengukuran ASC, hd.id_deteksi ASC"
    : "ORDER BY pa.tanggal_pengukuran DESC, hd.id_deteksi DESC";

$sqlRiwayat = "
    SELECT
        hd.id_deteksi,
        hd.status_gizi,
        hd.status_stunting,
        hd.status_verifikasi,
        hd.tanggal_deteksi,
        hd.tanggal_verifikasi,
        pa.id_pengukuran,
        pa.tanggal_pengukuran,
        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,
        pa.lingkar_kepala,
        pa.lila
    FROM hasil_deteksi AS hd
    INNER JOIN pengukuran_antropometri AS pa
        ON pa.id_pengukuran = hd.id_pengukuran
    WHERE " . implode(" AND ", $kondisiRiwayat) . "
    {$orderSql}
";

$queryRiwayat = mysqli_query($conn, $sqlRiwayat);

if (!$queryRiwayat) {
    die("Gagal mengambil riwayat deteksi: " . mysqli_error($conn));
}

$totalRiwayat = mysqli_num_rows($queryRiwayat);
$filterAktif = $keyword !== ""
    || $filterBulan !== ""
    || $filterTahun !== ""
    || $filterStatus !== ""
    || $filterVerifikasi !== ""
    || $urutan !== "terbaru";

function kelasStatusStuntingRiwayat(string $status): string
{
    $status = strtolower(trim($status));

    if (in_array($status, ["normal", "normal/sehat", "tidak stunting"], true)) {
        return "bg-success";
    }

    if ($status === "risiko stunting") {
        return "bg-warning text-dark";
    }

    if (in_array($status, ["stunting", "pendek"], true)) {
        return "bg-danger";
    }

    if (in_array($status, ["stunting berat", "severely stunted", "sangat pendek"], true)) {
        return "bg-dark text-white";
    }

    return "bg-secondary";
}

function kelasVerifikasiRiwayat(string $status): string
{
    $status = strtolower(trim($status));

    if ($status === "sudah diverifikasi") {
        return "bg-success";
    }

    if ($status === "perlu pemeriksaan ulang") {
        return "bg-warning text-dark";
    }

    return "bg-secondary";
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
                        <i class="bi bi-clock-history me-2"></i>
                        Riwayat Deteksi Balita
                    </h4>
                    <small class="text-muted">
                        Seluruh hasil deteksi bulanan tetap disimpan sebagai riwayat.
                    </small>
                </div>

                <a href="hasil_deteksi.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Hasil Terbaru
                </a>
            </div>

            <div class="card-body">
                <div class="alert alert-light border mb-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Nama Balita</small>
                            <strong><?= htmlspecialchars($balita["nama_balita"], ENT_QUOTES, "UTF-8"); ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">NIK</small>
                            <strong><?= htmlspecialchars($balita["nik_balita"] ?? "-", ENT_QUOTES, "UTF-8"); ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Puskesmas</small>
                            <strong><?= htmlspecialchars($balita["nama_puskesmas"] ?? "-", ENT_QUOTES, "UTF-8"); ?></strong>
                        </div>
                    </div>
                </div>

                <form method="get" class="mb-4">
                    <input type="hidden" name="id_balita" value="<?= (int) $idBalita; ?>">

                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label for="q" class="form-label">Pencarian</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="q"
                                    name="q"
                                    value="<?= htmlspecialchars($keyword, ENT_QUOTES, "UTF-8"); ?>"
                                    placeholder="Cari status gizi, stunting, verifikasi..."
                                >
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label for="bulan" class="form-label">Bulan</label>
                            <select class="form-select" id="bulan" name="bulan">
                                <option value="">Semua Bulan</option>
                                <?php
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
                                ?>
                                <?php foreach ($namaBulan as $nomorBulan => $labelBulan): ?>
                                    <option
                                        value="<?= $nomorBulan; ?>"
                                        <?= (string) $filterBulan === (string) $nomorBulan ? "selected" : ""; ?>
                                    >
                                        <?= htmlspecialchars($labelBulan, ENT_QUOTES, "UTF-8"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-3 col-6">
                            <label for="tahun" class="form-label">Tahun</label>
                            <select class="form-select" id="tahun" name="tahun">
                                <option value="">Semua Tahun</option>
                                <?php foreach ($daftarTahun as $tahun): ?>
                                    <option
                                        value="<?= htmlspecialchars($tahun, ENT_QUOTES, "UTF-8"); ?>"
                                        <?= $filterTahun === $tahun ? "selected" : ""; ?>
                                    >
                                        <?= htmlspecialchars($tahun, ENT_QUOTES, "UTF-8"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label for="status_stunting" class="form-label">Status Stunting</label>
                            <select class="form-select" id="status_stunting" name="status_stunting">
                                <option value="">Semua Status</option>
                                <?php foreach ($statusStuntingValid as $statusPilihan): ?>
                                    <option
                                        value="<?= htmlspecialchars($statusPilihan, ENT_QUOTES, "UTF-8"); ?>"
                                        <?= $filterStatus === $statusPilihan ? "selected" : ""; ?>
                                    >
                                        <?= htmlspecialchars($statusPilihan, ENT_QUOTES, "UTF-8"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label for="verifikasi" class="form-label">Verifikasi</label>
                            <select class="form-select" id="verifikasi" name="verifikasi">
                                <option value="">Semua Verifikasi</option>
                                <?php foreach ($verifikasiValid as $verifikasiPilihan): ?>
                                    <option
                                        value="<?= htmlspecialchars($verifikasiPilihan, ENT_QUOTES, "UTF-8"); ?>"
                                        <?= $filterVerifikasi === $verifikasiPilihan ? "selected" : ""; ?>
                                    >
                                        <?= htmlspecialchars($verifikasiPilihan, ENT_QUOTES, "UTF-8"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label for="urutan" class="form-label">Urutan</label>
                            <select class="form-select" id="urutan" name="urutan">
                                <option value="terbaru" <?= $urutan === "terbaru" ? "selected" : ""; ?>>
                                    Terbaru
                                </option>
                                <option value="terlama" <?= $urutan === "terlama" ? "selected" : ""; ?>>
                                    Terlama
                                </option>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i>
                                Filter
                            </button>
                        </div>

                        <div class="col-lg-3 col-md-6 d-grid">
                            <a href="riwayat_deteksi.php?id_balita=<?= (int) $idBalita; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Reset Filter
                            </a>
                        </div>
                    </div>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted small">
                        <?= $filterAktif ? "Hasil filter" : "Total riwayat"; ?>:
                        <strong><?= (int) $totalRiwayat; ?></strong> periode
                    </span>
                    <span class="badge badge-info">
                        <i class="bi bi-archive"></i>
                        Riwayat tidak dihapus
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th class="text-center">Tanggal Pengukuran</th>
                                <th class="text-center">Umur</th>
                                <th class="text-center">BB</th>
                                <th class="text-center">TB/PB</th>
                                <th class="text-center">Status Gizi</th>
                                <th class="text-center">Status Stunting</th>
                                <th class="text-center">Verifikasi Ahli Gizi</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($totalRiwayat > 0): ?>
                                <?php $no = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($queryRiwayat)): ?>
                                    <?php
                                    $statusVerifikasi = trim((string) ($row["status_verifikasi"] ?? ""));
                                    if ($statusVerifikasi === "") {
                                        $statusVerifikasi = "Belum diverifikasi";
                                    }
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td class="text-center">
                                            <?= !empty($row["tanggal_pengukuran"])
                                                ? date("d-m-Y", strtotime($row["tanggal_pengukuran"]))
                                                : "-"; ?>
                                        </td>
                                        <td class="text-center"><?= (int) ($row["umur_bulan"] ?? 0); ?> bulan</td>
                                        <td class="text-center"><?= htmlspecialchars($row["berat_badan"] ?? "-", ENT_QUOTES, "UTF-8"); ?> kg</td>
                                        <td class="text-center"><?= htmlspecialchars($row["tinggi_panjang_badan"] ?? "-", ENT_QUOTES, "UTF-8"); ?> cm</td>
                                        <td class="text-center">
                                            <?= htmlspecialchars($row["status_gizi"] ?? "Belum tersedia", ENT_QUOTES, "UTF-8"); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= kelasStatusStuntingRiwayat((string) ($row["status_stunting"] ?? "")); ?>">
                                                <?= htmlspecialchars($row["status_stunting"] ?? "Belum tersedia", ENT_QUOTES, "UTF-8"); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= kelasVerifikasiRiwayat($statusVerifikasi); ?>">
                                                <?= htmlspecialchars($statusVerifikasi, ENT_QUOTES, "UTF-8"); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="detail_deteksi.php?id=<?= (int) $row["id_deteksi"]; ?>" class="btn btn-info btn-sm">
                                                <i class="bi bi-eye"></i>
                                                Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <?= $filterAktif ? "Tidak ada riwayat yang sesuai dengan filter/pencarian." : "Belum ada riwayat deteksi untuk balita ini."; ?>
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
