<?php

session_start();

require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Judul halaman
|--------------------------------------------------------------------------
*/

$judulHalaman =
    "Data Pengukuran Antropometri | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Mengambil data pengukuran beserta nama balita
|--------------------------------------------------------------------------
*/

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
        b.nama_balita
    FROM pengukuran_antropometri AS p
    INNER JOIN balita AS b
        ON p.id_balita = b.id_balita
    ORDER BY
        p.tanggal_pengukuran DESC,
        p.id_pengukuran DESC
";

$query = mysqli_query($conn, $sql);

if (!$query) {
    die(
        "Gagal mengambil data pengukuran: "
        . mysqli_error($conn)
    );
}

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

/*
|--------------------------------------------------------------------------
| Pengaturan tampilan berdasarkan role
|--------------------------------------------------------------------------
|
| Hanya Kader yang melihat fitur tambah, edit, dan hapus.
| Role lain melihat data pengukuran dalam mode read-only.
|
*/

$roleAktif = $_SESSION["role"] ?? "";
$bolehKelolaPengukuran = $roleAktif === "kader";

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
                        <?= $bolehKelolaPengukuran
                            ? "Kelola hasil pengukuran fisik balita dari kegiatan Posyandu."
                            : "Lihat riwayat pengukuran pertumbuhan balita."; ?>
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

                <div
                    class="d-flex flex-wrap
                    justify-content-between align-items-center
                    gap-2 mb-3"
                >
                    <span class="text-muted small">
                        Total data:
                        <strong>
                            <?= mysqli_num_rows($query); ?>
                        </strong>
                        pengukuran
                    </span>

                    <?php if (!$bolehKelolaPengukuran): ?>

                        <span class="badge badge-info">
                            <i class="bi bi-eye"></i>
                            Mode lihat
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
                                    Nama Balita
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

                                            </div>

                                        </td>

                                    <?php endif; ?>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="<?= $bolehKelolaPengukuran
                                        ? "9"
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
                                            Belum ada data pengukuran
                                        </h3>

                                        <p>
                                            <?= $bolehKelolaPengukuran
                                                ? "Tambahkan pengukuran pertama untuk mulai memantau pertumbuhan balita."
                                                : "Data pengukuran pertumbuhan balita belum tersedia."; ?>
                                        </p>

                                        <?php if (
                                            $bolehKelolaPengukuran
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

<?php require_once "../includes/footer.php"; ?>