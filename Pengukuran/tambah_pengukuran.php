<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
*/

cekRole(["kader"]);

/*
|--------------------------------------------------------------------------
| Judul halaman
|--------------------------------------------------------------------------
*/

$judulHalaman =
    "Tambah Pengukuran | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function amanPengukuran($nilai): string
{
    return htmlspecialchars(
        (string) ($nilai ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$error = "";

$old = [
    "id_balita"               => "",
    "tanggal_pengukuran"      => date("Y-m-d"),
    "umur_bulan"              => "",
    "berat_badan"             => "",
    "tinggi_panjang_badan"    => "",
    "lingkar_kepala"          => "",
    "lila"                    => ""
];

/*
|--------------------------------------------------------------------------
| Proses penyimpanan
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $old["id_balita"] =
        trim($_POST["id_balita"] ?? "");

    $old["tanggal_pengukuran"] =
        trim($_POST["tanggal_pengukuran"] ?? "");

    $old["umur_bulan"] =
        trim($_POST["umur_bulan"] ?? "");

    $old["berat_badan"] =
        trim($_POST["berat_badan"] ?? "");

    $old["tinggi_panjang_badan"] =
        trim($_POST["tinggi_panjang_badan"] ?? "");

    $old["lingkar_kepala"] =
        trim($_POST["lingkar_kepala"] ?? "");

    $old["lila"] =
        trim($_POST["lila"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | Mengubah dan memvalidasi input
    |--------------------------------------------------------------------------
    */

    $idBalita = filter_var(
        $old["id_balita"],
        FILTER_VALIDATE_INT
    );

    $tanggalPengukuran =
        $old["tanggal_pengukuran"];

    $umurBulan = filter_var(
        $old["umur_bulan"],
        FILTER_VALIDATE_INT
    );

    $beratBadan = filter_var(
        $old["berat_badan"],
        FILTER_VALIDATE_FLOAT
    );

    $tinggiPanjangBadan = filter_var(
        $old["tinggi_panjang_badan"],
        FILTER_VALIDATE_FLOAT
    );

    $lingkarKepala =
        $old["lingkar_kepala"] === ""
            ? null
            : filter_var(
                $old["lingkar_kepala"],
                FILTER_VALIDATE_FLOAT
            );

    $lila =
        $old["lila"] === ""
            ? null
            : filter_var(
                $old["lila"],
                FILTER_VALIDATE_FLOAT
            );

    /*
    |--------------------------------------------------------------------------
    | Validasi tanggal
    |--------------------------------------------------------------------------
    */

    $objekTanggal = DateTime::createFromFormat(
        "Y-m-d",
        $tanggalPengukuran
    );

    $tanggalValid =
        $objekTanggal !== false
        && $objekTanggal->format("Y-m-d")
            === $tanggalPengukuran;

    /*
    |--------------------------------------------------------------------------
    | Validasi seluruh data
    |--------------------------------------------------------------------------
    */

    if (
        $old["id_balita"] === ""
        || $old["tanggal_pengukuran"] === ""
        || $old["umur_bulan"] === ""
        || $old["berat_badan"] === ""
        || $old["tinggi_panjang_badan"] === ""
    ) {
        $error =
            "Nama balita, tanggal, umur, berat badan, dan tinggi badan wajib diisi.";

    } elseif (
        $idBalita === false
        || $idBalita < 1
    ) {
        $error =
            "Data balita yang dipilih tidak valid.";

    } elseif (!$tanggalValid) {
        $error =
            "Tanggal pengukuran tidak valid.";

    } elseif (
        $umurBulan === false
        || $umurBulan < 0
        || $umurBulan > 59
    ) {
        $error =
            "Umur balita harus berada antara 0 sampai 59 bulan.";

    } elseif (
        $beratBadan === false
        || $beratBadan <= 0
        || $beratBadan > 100
    ) {
        $error =
            "Berat badan harus lebih dari 0 dan maksimal 100 kg.";

    } elseif (
        $tinggiPanjangBadan === false
        || $tinggiPanjangBadan <= 0
        || $tinggiPanjangBadan > 200
    ) {
        $error =
            "Tinggi atau panjang badan harus lebih dari 0 dan maksimal 200 cm.";

    } elseif (
        $old["lingkar_kepala"] !== ""
        && (
            $lingkarKepala === false
            || $lingkarKepala <= 0
            || $lingkarKepala > 100
        )
    ) {
        $error =
            "Lingkar kepala harus lebih dari 0 dan maksimal 100 cm.";

    } elseif (
        $old["lila"] !== ""
        && (
            $lila === false
            || $lila <= 0
            || $lila > 100
        )
    ) {
        $error =
            "Nilai LiLA harus lebih dari 0 dan maksimal 100 cm.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan balita tersedia
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $stmtCekBalita = mysqli_prepare(
            $conn,
            "SELECT id_balita
             FROM balita
             WHERE id_balita = ?
             LIMIT 1"
        );

        if (!$stmtCekBalita) {
            $error =
                "Sistem gagal memeriksa data balita.";

        } else {

            mysqli_stmt_bind_param(
                $stmtCekBalita,
                "i",
                $idBalita
            );

            mysqli_stmt_execute($stmtCekBalita);

            $hasilCekBalita =
                mysqli_stmt_get_result($stmtCekBalita);

            $dataBalita =
                mysqli_fetch_assoc($hasilCekBalita);

            mysqli_stmt_close($stmtCekBalita);

            if (!$dataBalita) {
                $error =
                    "Data balita tidak ditemukan.";
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan dengan prepared statement
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO pengukuran_antropometri
            (
                id_balita,
                tanggal_pengukuran,
                umur_bulan,
                berat_badan,
                tinggi_panjang_badan,
                lingkar_kepala,
                lila
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmtSimpan) {
            $error =
                "Sistem gagal menyiapkan penyimpanan data.";

        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "isidddd",
                $idBalita,
                $tanggalPengukuran,
                $umurBulan,
                $beratBadan,
                $tinggiPanjangBadan,
                $lingkarKepala,
                $lila
            );

            $berhasil =
                mysqli_stmt_execute($stmtSimpan);

            if ($berhasil) {

                mysqli_stmt_close($stmtSimpan);

                header(
                    "Location: data_pengukuran.php?pesan=tambah_berhasil"
                );

                exit;
            }

            $error =
                "Data pengukuran gagal disimpan: "
                . mysqli_stmt_error($stmtSimpan);

            mysqli_stmt_close($stmtSimpan);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Mengambil daftar balita
|--------------------------------------------------------------------------
*/

$queryBalita = mysqli_query(
    $conn,
    "SELECT
        id_balita,
        nama_balita
     FROM balita
     ORDER BY nama_balita ASC"
);

if (!$queryBalita) {
    die(
        "Gagal mengambil daftar balita: "
        . mysqli_error($conn)
    );
}

$jumlahBalita =
    mysqli_num_rows($queryBalita);

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

        <!-- Judul halaman -->
        <div class="page-header">

            <div>

                <h1 class="page-title">
                    <i class="bi bi-clipboard2-pulse me-2"></i>
                    Tambah Pengukuran
                </h1>

                <p class="page-subtitle">
                    Masukkan hasil pengukuran antropometri balita
                    secara lengkap dan benar.
                </p>

            </div>

            <a
                href="data_pengukuran.php"
                class="btn btn-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Kembali ke Data Pengukuran
            </a>

        </div>

        <?php if ($error !== ""): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-exclamation-circle me-1"></i>

                <?= amanPengukuran($error); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>

        <?php endif; ?>

        <?php if ($jumlahBalita < 1): ?>

            <div class="card content-card">

                <div class="card-body">

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>

                        <h3>
                            Belum ada data balita
                        </h3>

                        <p>
                            Tambahkan data balita terlebih dahulu
                            sebelum membuat data pengukuran.
                        </p>

                        <a
                            href="../balita/tambah_balita.php"
                            class="btn btn-primary mt-3"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Tambah Data Balita
                        </a>

                    </div>

                </div>

            </div>

        <?php else: ?>

            <div class="card content-card">

                <div class="card-header">

                    <div>

                        <h4 class="mb-1">
                            Form Pengukuran Antropometri
                        </h4>

                        <small class="text-muted">
                            Kolom bertanda wajib harus diisi.
                        </small>

                    </div>

                </div>

                <div class="card-body">

                    <form
                        method="POST"
                        action=""
                        autocomplete="off"
                    >

                        <!-- Balita -->
                        <div class="form-group">

                            <label
                                for="id_balita"
                                class="form-label"
                            >
                                Nama Balita
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="id_balita"
                                id="id_balita"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Pilih Balita --
                                </option>

                                <?php
                                while (
                                    $balita =
                                        mysqli_fetch_assoc(
                                            $queryBalita
                                        )
                                ):
                                ?>

                                    <option
                                        value="<?= (int) $balita["id_balita"]; ?>"
                                        <?= (
                                            (string) $old["id_balita"]
                                            ===
                                            (string) $balita["id_balita"]
                                        )
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanPengukuran(
                                            $balita["nama_balita"]
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>

                        <div class="form-row">

                            <!-- Tanggal -->
                            <div class="form-group">

                                <label
                                    for="tanggal_pengukuran"
                                    class="form-label"
                                >
                                    Tanggal Pengukuran
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="date"
                                    name="tanggal_pengukuran"
                                    id="tanggal_pengukuran"
                                    class="form-control"
                                    value="<?= amanPengukuran(
                                        $old["tanggal_pengukuran"]
                                    ); ?>"
                                    max="<?= date("Y-m-d"); ?>"
                                    required
                                >

                            </div>

                            <!-- Umur -->
                            <div class="form-group">

                                <label
                                    for="umur_bulan"
                                    class="form-label"
                                >
                                    Umur Balita
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="umur_bulan"
                                        id="umur_bulan"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old["umur_bulan"]
                                        ); ?>"
                                        min="0"
                                        max="59"
                                        placeholder="Contoh: 24"
                                        required
                                    >

                                    <span class="input-group-text">
                                        bulan
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="form-row">

                            <!-- Berat badan -->
                            <div class="form-group">

                                <label
                                    for="berat_badan"
                                    class="form-label"
                                >
                                    Berat Badan
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="berat_badan"
                                        id="berat_badan"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old["berat_badan"]
                                        ); ?>"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        placeholder="Contoh: 12.50"
                                        required
                                    >

                                    <span class="input-group-text">
                                        kg
                                    </span>

                                </div>

                            </div>

                            <!-- Tinggi badan -->
                            <div class="form-group">

                                <label
                                    for="tinggi_panjang_badan"
                                    class="form-label"
                                >
                                    Tinggi/Panjang Badan
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="tinggi_panjang_badan"
                                        id="tinggi_panjang_badan"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old[
                                                "tinggi_panjang_badan"
                                            ]
                                        ); ?>"
                                        min="0.01"
                                        max="200"
                                        step="0.01"
                                        placeholder="Contoh: 85.50"
                                        required
                                    >

                                    <span class="input-group-text">
                                        cm
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="form-row">

                            <!-- Lingkar kepala -->
                            <div class="form-group">

                                <label
                                    for="lingkar_kepala"
                                    class="form-label"
                                >
                                    Lingkar Kepala
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="lingkar_kepala"
                                        id="lingkar_kepala"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old["lingkar_kepala"]
                                        ); ?>"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        placeholder="Opsional"
                                    >

                                    <span class="input-group-text">
                                        cm
                                    </span>

                                </div>

                            </div>

                            <!-- LiLA -->
                            <div class="form-group">

                                <label
                                    for="lila"
                                    class="form-label"
                                >
                                    Lingkar Lengan Atas (LiLA)
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="lila"
                                        id="lila"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old["lila"]
                                        ); ?>"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        placeholder="Opsional"
                                    >

                                    <span class="input-group-text">
                                        cm
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="form-actions">

                            <button
                                type="submit"
                                name="simpan"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-floppy"></i>
                                Simpan Pengukuran
                            </button>

                            <a
                                href="data_pengukuran.php"
                                class="btn btn-light"
                            >
                                <i class="bi bi-x-circle"></i>
                                Batal
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>