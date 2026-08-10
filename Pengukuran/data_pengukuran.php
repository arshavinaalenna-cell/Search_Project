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

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$bolehKelolaPengukuran =
    $roleAktif === "kader";

/*
|--------------------------------------------------------------------------
| Filter Puskesmas khusus Kader
|--------------------------------------------------------------------------
*/

$filterPuskesmas = $roleAktif === "kader"
    ? max(0, (int) ($_GET["puskesmas"] ?? 0))
    : 0;

$daftarPuskesmas = [];

if ($roleAktif === "kader") {
    $queryPuskesmas = mysqli_query(
        $conn,
        "SELECT id_puskesmas, nama_puskesmas
         FROM puskesmas
         ORDER BY nama_puskesmas ASC"
    );

    if ($queryPuskesmas) {
        while ($puskesmas = mysqli_fetch_assoc($queryPuskesmas)) {
            $daftarPuskesmas[] = $puskesmas;
        }
    }
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
| Mengambil data pengukuran beserta nama balita
|--------------------------------------------------------------------------
|
| Orang Tua hanya dapat melihat data pengukuran anak yang terhubung
| dengan akun miliknya melalui balita.id_user.
|
| Role lain tetap dapat melihat seluruh data sesuai kebutuhan
| monitoring/pelayanan.
|
*/

$stmtPengukuran = null;

if ($roleAktif === "orang_tua") {

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
        WHERE b.id_user = ?
        ORDER BY
            p.tanggal_pengukuran DESC,
            p.id_pengukuran DESC
    ";

    $stmtPengukuran = mysqli_prepare(
        $conn,
        $sql
    );

    if (!$stmtPengukuran) {
        die(
            "Gagal menyiapkan data pengukuran: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtPengukuran,
        "i",
        $idUserAktif
    );

    if (!mysqli_stmt_execute($stmtPengukuran)) {
        die(
            "Gagal mengambil data pengukuran: "
            . mysqli_stmt_error($stmtPengukuran)
        );
    }

    $query =
        mysqli_stmt_get_result($stmtPengukuran);

} elseif ($roleAktif === "kader" && $filterPuskesmas > 0) {

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
        WHERE b.id_puskesmas = ?
        ORDER BY
            p.tanggal_pengukuran DESC,
            p.id_pengukuran DESC
    ";

    $stmtPengukuran = mysqli_prepare(
        $conn,
        $sql
    );

    if (!$stmtPengukuran) {
        die(
            "Gagal menyiapkan filter data pengukuran: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtPengukuran,
        "i",
        $filterPuskesmas
    );

    if (!mysqli_stmt_execute($stmtPengukuran)) {
        die(
            "Gagal mengambil data pengukuran: "
            . mysqli_stmt_error($stmtPengukuran)
        );
    }

    $query = mysqli_stmt_get_result($stmtPengukuran);

} else {

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
        ORDER BY
            p.tanggal_pengukuran DESC,
            p.id_pengukuran DESC
    ";

    $query = mysqli_query(
        $conn,
        $sql
    );

    if (!$query) {
        die(
            "Gagal mengambil data pengukuran: "
            . mysqli_error($conn)
        );
    }
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

                <?php if ($roleAktif === "kader"): ?>

                    <form
                        method="GET"
                        class="row g-2 mb-4 align-items-end"
                    >

                        <div class="col-12 col-lg-8">

                            <label
                                for="filter_puskesmas"
                                class="form-label small text-muted mb-1"
                            >
                                Filter berdasarkan Puskesmas
                            </label>

                            <select
                                id="filter_puskesmas"
                                name="puskesmas"
                                class="form-select"
                            >
                                <option value="0">
                                    Semua Puskesmas
                                </option>

                                <?php foreach ($daftarPuskesmas as $puskesmas): ?>
                                    <option
                                        value="<?= (int) $puskesmas["id_puskesmas"]; ?>"
                                        <?= $filterPuskesmas === (int) $puskesmas["id_puskesmas"]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $puskesmas["nama_puskesmas"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
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
                                href="data_pengukuran.php"
                                class="btn btn-light w-100"
                            >
                                <i class="bi bi-x-circle"></i>
                                Hapus Filter
                            </a>
                        </div>

                    </form>

                <?php endif; ?>

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
                                            <?php if ($bolehKelolaPengukuran): ?>
                                                Tambahkan pengukuran pertama untuk mulai memantau pertumbuhan balita.
                                            <?php elseif ($roleAktif === "orang_tua"): ?>
                                                Belum ada data pengukuran untuk anak yang terhubung dengan akun Anda.
                                            <?php else: ?>
                                                Data pengukuran pertumbuhan balita belum tersedia.
                                            <?php endif; ?>
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

<?php

if ($stmtPengukuran instanceof mysqli_stmt) {
    mysqli_stmt_close($stmtPengukuran);
}

require_once "../includes/footer.php";

?>