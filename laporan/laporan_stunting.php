<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses laporan
|--------------------------------------------------------------------------
*/

cekRole([
    "petugas_gizi",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman =
    "Laporan Stunting | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Fungsi keamanan output
|--------------------------------------------------------------------------
*/

function amanLaporan($nilai): string
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
| Memvalidasi tanggal
|--------------------------------------------------------------------------
*/

function tanggalLaporanValid(string $tanggal): bool
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

/*
|--------------------------------------------------------------------------
| Format tanggal Indonesia sederhana
|--------------------------------------------------------------------------
*/

function formatTanggalLaporan($tanggal): string
{
    if (
        empty($tanggal)
        || $tanggal === "0000-00-00"
    ) {
        return "-";
    }

    $waktu = strtotime($tanggal);

    if ($waktu === false) {
        return amanLaporan($tanggal);
    }

    return date("d-m-Y", $waktu);
}

/*
|--------------------------------------------------------------------------
| Menentukan warna badge status
|--------------------------------------------------------------------------
*/

function kelasStatusLaporan($status): string
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
        return "badge-success";
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
        return "badge-warning";
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
        return "badge-danger";
    }

    return "badge-info";
}

/*
|--------------------------------------------------------------------------
| Filter tanggal
|--------------------------------------------------------------------------
|
| Secara default laporan menampilkan data dari awal bulan berjalan
| sampai tanggal hari ini.
|
*/

$tanggalAwalDefault = date("Y-m-01");
$tanggalAkhirDefault = date("Y-m-d");

$tanggalAwal = trim(
    $_GET["tanggal_awal"]
    ?? $tanggalAwalDefault
);

$tanggalAkhir = trim(
    $_GET["tanggal_akhir"]
    ?? $tanggalAkhirDefault
);

$pesanError = "";

if (!tanggalLaporanValid($tanggalAwal)) {
    $pesanError =
        "Tanggal awal laporan tidak valid.";

    $tanggalAwal =
        $tanggalAwalDefault;
}

if (!tanggalLaporanValid($tanggalAkhir)) {
    $pesanError =
        "Tanggal akhir laporan tidak valid.";

    $tanggalAkhir =
        $tanggalAkhirDefault;
}

if ($tanggalAwal > $tanggalAkhir) {
    $pesanError =
        "Tanggal awal tidak boleh melebihi tanggal akhir.";

    $tanggalAwal =
        $tanggalAwalDefault;

    $tanggalAkhir =
        $tanggalAkhirDefault;
}

/*
|--------------------------------------------------------------------------
| Mengambil data hasil deteksi
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        hd.id_deteksi,
        hd.id_pengukuran,
        hd.status_gizi,
        hd.status_stunting,
        hd.tanggal_deteksi,

        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,

        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin

    FROM hasil_deteksi AS hd

    INNER JOIN pengukuran_antropometri AS pa
        ON hd.id_pengukuran = pa.id_pengukuran

    INNER JOIN balita AS b
        ON pa.id_balita = b.id_balita

    WHERE hd.tanggal_deteksi
        BETWEEN ? AND ?

    ORDER BY
        hd.tanggal_deteksi DESC,
        hd.id_deteksi DESC
";

$stmtLaporan = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmtLaporan) {
    die(
        "Gagal menyiapkan laporan stunting: "
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

/*
|--------------------------------------------------------------------------
| Menyimpan hasil ke array dan menghitung rekap
|--------------------------------------------------------------------------
*/

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
            $data["status_stunting"] ?? ""
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

/*
|--------------------------------------------------------------------------
| Memanggil template aplikasi
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <!-- Header halaman -->
        <div class="page-header">

            <div>

                <h1 class="page-title">

                    <i class="bi bi-file-earmark-medical me-2"></i>

                    Laporan Stunting

                </h1>

                <p class="page-subtitle">

                    Rekap hasil deteksi risiko stunting berdasarkan
                    periode tanggal yang dipilih.

                </p>

            </div>

            <div class="d-flex flex-wrap gap-2">

                <a
                    href="../dashboard/dashboard.php"
                    class="btn btn-secondary"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Dashboard
                </a>

<a
    href="cetak_laporan.php?tanggal_awal=<?= urlencode(
        $tanggalAwal
    ); ?>&tanggal_akhir=<?= urlencode(
        $tanggalAkhir
    ); ?>"
    class="btn btn-primary"
    target="_blank"
>
    <i class="bi bi-printer"></i>
    Cetak Laporan
</a>

            </div>

        </div>

        <?php if ($pesanError !== ""): ?>

            <div
                class="alert alert-warning
                alert-dismissible fade show"
                role="alert"
            >

                <i class="bi bi-exclamation-triangle me-1"></i>

                <?= amanLaporan($pesanError); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>

            </div>

        <?php endif; ?>

        <!-- Filter periode -->
        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Filter Periode Laporan
                    </h4>

                    <small class="text-muted">
                        Pilih rentang tanggal hasil deteksi.
                    </small>

                </div>

                <span class="badge badge-info">
                    <i class="bi bi-calendar-range"></i>
                    Periode
                </span>

            </div>

            <div class="card-body">

                <form
                    method="GET"
                    action="laporan_stunting.php"
                >

                    <div class="form-row">

                        <div class="form-group">

                            <label
                                for="tanggal_awal"
                                class="form-label"
                            >
                                Tanggal Awal
                            </label>

                            <input
                                type="date"
                                name="tanggal_awal"
                                id="tanggal_awal"
                                class="form-control"
                                value="<?= amanLaporan(
                                    $tanggalAwal
                                ); ?>"
                                max="<?= date("Y-m-d"); ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label
                                for="tanggal_akhir"
                                class="form-label"
                            >
                                Tanggal Akhir
                            </label>

                            <input
                                type="date"
                                name="tanggal_akhir"
                                id="tanggal_akhir"
                                class="form-control"
                                value="<?= amanLaporan(
                                    $tanggalAkhir
                                ); ?>"
                                max="<?= date("Y-m-d"); ?>"
                                required
                            >

                        </div>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-funnel"></i>
                            Tampilkan Laporan
                        </button>

                        <a
                            href="laporan_stunting.php"
                            class="btn btn-light"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset Periode
                        </a>

                    </div>

                </form>

            </div>

        </div>

        <!-- Ringkasan statistik -->
        <div class="stat-grid">

            <div class="stat-card stat-info">

                <div class="stat-icon">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Total Deteksi
                    </p>

                    <p class="stat-value">
                        <?= $totalDeteksi; ?>
                    </p>

                </div>

            </div>

            <div class="stat-card stat-success">

                <div class="stat-icon">
                    <i class="bi bi-shield-check"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Risiko Rendah
                    </p>

                    <p class="stat-value">
                        <?= $totalRisikoRendah; ?>
                    </p>

                </div>

            </div>

            <div class="stat-card stat-warning">

                <div class="stat-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Risiko Sedang
                    </p>

                    <p class="stat-value">
                        <?= $totalRisikoSedang; ?>
                    </p>

                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon">
                    <i class="bi bi-exclamation-octagon"></i>
                </div>

                <div class="stat-content">

                    <p class="stat-label">
                        Risiko Tinggi
                    </p>

                    <p class="stat-value">
                        <?= $totalRisikoTinggi; ?>
                    </p>

                </div>

            </div>

        </div>

        <!-- Daftar hasil laporan -->
        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Daftar Hasil Deteksi
                    </h4>

                    <small class="text-muted">

                        Periode:

                        <?= formatTanggalLaporan(
                            $tanggalAwal
                        ); ?>

                        sampai

                        <?= formatTanggalLaporan(
                            $tanggalAkhir
                        ); ?>

                    </small>

                </div>

                <span class="badge badge-primary">

                    <?= $totalDeteksi; ?>

                    data

                </span>

            </div>

            <div class="card-body">

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

                                <th>
                                    NIK Balita
                                </th>

                                <th class="text-center">
                                    Jenis Kelamin
                                </th>

                                <th class="text-center">
                                    Umur
                                </th>

                                <th class="text-center">
                                    Berat Badan
                                </th>

                                <th class="text-center">
                                    Tinggi Badan
                                </th>

                                <th class="text-center">
                                    Status Gizi
                                </th>

                                <th class="text-center">
                                    Status Risiko
                                </th>

                                <th class="text-center">
                                    Tanggal Deteksi
                                </th>

                                <th class="text-center">
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($totalDeteksi > 0): ?>

                            <?php foreach (
                                $dataLaporan
                                as $nomor => $data
                            ): ?>

                                <?php

                                $idDeteksi =
                                    (int) $data["id_deteksi"];

                                $kelasStatus =
                                    kelasStatusLaporan(
                                        $data["status_stunting"]
                                    );

                                ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $nomor + 1; ?>
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
                                                <?= amanLaporan(
                                                    $data["nama_balita"]
                                                ); ?>
                                            </strong>

                                        </div>

                                    </td>

                                    <td>
                                        <?= amanLaporan(
                                            $data["nik_balita"]
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= amanLaporan(
                                            $data["jenis_kelamin"]
                                        ); ?>
                                    </td>

                                    <td class="text-center">

                                        <?= amanLaporan(
                                            $data["umur_bulan"]
                                        ); ?>

                                        bulan

                                    </td>

                                    <td class="text-center">

                                        <?= amanLaporan(
                                            $data["berat_badan"]
                                        ); ?>

                                        kg

                                    </td>

                                    <td class="text-center">

                                        <?= amanLaporan(
                                            $data[
                                                "tinggi_panjang_badan"
                                            ]
                                        ); ?>

                                        cm

                                    </td>

                                    <td class="text-center">

                                        <span class="badge badge-info">

                                            <?= amanLaporan(
                                                $data["status_gizi"]
                                            ); ?>

                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge <?= $kelasStatus; ?>"
                                        >

                                            <?= amanLaporan(
                                                $data[
                                                    "status_stunting"
                                                ]
                                            ); ?>

                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <?= formatTanggalLaporan(
                                            $data["tanggal_deteksi"]
                                        ); ?>

                                    </td>

                                    <td>

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

<a
    href="detail_laporan.php?id=<?= $idDeteksi; ?>"
    class="btn btn-info btn-sm"
>
    <i class="bi bi-eye"></i>
    Detail
</a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="11">

                                    <div class="empty-state">

                                        <div class="empty-state-icon">

                                            <i
                                                class="bi
                                                bi-file-earmark-medical"
                                            ></i>

                                        </div>

                                        <h3>
                                            Belum ada data laporan
                                        </h3>

                                        <p>
                                            Tidak ditemukan hasil
                                            deteksi pada periode tanggal
                                            yang dipilih.
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

<?php require_once "../includes/footer.php"; ?>