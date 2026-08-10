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
    "Edit Pengukuran | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function amanEditPengukuran($nilai): string
{
    return htmlspecialchars(
        (string) ($nilai ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Memeriksa ID pengukuran
|--------------------------------------------------------------------------
*/

$idPengukuran = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (
    !$idPengukuran
    || $idPengukuran < 1
) {
    header(
        "Location: data_pengukuran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data pengukuran yang akan diedit
|--------------------------------------------------------------------------
*/

$stmtData = mysqli_prepare(
    $conn,
    "SELECT
        id_pengukuran,
        id_balita,
        tanggal_pengukuran,
        umur_bulan,
        berat_badan,
        tinggi_panjang_badan,
        lingkar_kepala,
        lila
     FROM pengukuran_antropometri
     WHERE id_pengukuran = ?
     LIMIT 1"
);

if (!$stmtData) {
    die(
        "Gagal menyiapkan data pengukuran: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtData,
    "i",
    $idPengukuran
);

mysqli_stmt_execute($stmtData);

$resultData =
    mysqli_stmt_get_result($stmtData);

$data =
    mysqli_fetch_assoc($resultData);

mysqli_stmt_close($stmtData);

if (!$data) {
    header(
        "Location: data_pengukuran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$error = "";

$old = [
    "id_balita" =>
        $data["id_balita"] ?? "",

    "tanggal_pengukuran" =>
        $data["tanggal_pengukuran"] ?? "",

    "umur_bulan" =>
        $data["umur_bulan"] ?? "",

    "berat_badan" =>
        $data["berat_badan"] ?? "",

    "tinggi_panjang_badan" =>
        $data["tinggi_panjang_badan"] ?? "",

    "lingkar_kepala" =>
        $data["lingkar_kepala"] ?? "",

    "lila" =>
        $data["lila"] ?? ""
];

/*
|--------------------------------------------------------------------------
| Proses update data
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $old["id_balita"] =
        trim($_POST["id_balita"] ?? "");

    $old["tanggal_pengukuran"] =
        trim($_POST["tanggal_pengukuran"] ?? "");

    /*
    | Umur tidak diambil dari input manual.
    | Nilainya dihitung otomatis dari tanggal lahir balita
    | dan tanggal pengukuran.
    */
    $old["umur_bulan"] = "";

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

    $umurBulan = null;

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
    | Validasi input wajib
    |--------------------------------------------------------------------------
    */

    if (
        $old["id_balita"] === ""
        || $old["tanggal_pengukuran"] === ""
        || $old["berat_badan"] === ""
        || $old["tinggi_panjang_badan"] === ""
    ) {
        $error =
            "Nama balita, tanggal, berat badan, dan tinggi badan wajib diisi.";

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
        $tanggalPengukuran > date("Y-m-d")
    ) {
        $error =
            "Tanggal pengukuran tidak boleh melebihi tanggal hari ini.";

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
            "SELECT id_balita, tanggal_lahir
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

            $resultBalita =
                mysqli_stmt_get_result(
                    $stmtCekBalita
                );

            $dataBalita =
                mysqli_fetch_assoc(
                    $resultBalita
                );

            mysqli_stmt_close(
                $stmtCekBalita
            );

            if (!$dataBalita) {
                $error =
                    "Data balita tidak ditemukan.";
            } else {

                /*
                |--------------------------------------------------------------------------
                | Hitung umur otomatis dalam bulan
                |--------------------------------------------------------------------------
                |
                | Umur dihitung dari tanggal lahir balita sampai tanggal pengukuran.
                | Nilai balita.umur tidak digunakan.
                |
                */

                $tanggalLahir = DateTime::createFromFormat(
                    "Y-m-d",
                    (string) $dataBalita["tanggal_lahir"]
                );

                $tanggalUkur = DateTime::createFromFormat(
                    "Y-m-d",
                    $tanggalPengukuran
                );

                if (
                    !$tanggalLahir
                    || !$tanggalUkur
                ) {
                    $error =
                        "Tanggal lahir balita atau tanggal pengukuran tidak valid.";

                } elseif ($tanggalUkur < $tanggalLahir) {
                    $error =
                        "Tanggal pengukuran tidak boleh lebih awal dari tanggal lahir balita.";

                } else {

                    $selisihUmur =
                        $tanggalLahir->diff($tanggalUkur);

                    $umurBulan =
                        ($selisihUmur->y * 12)
                        + $selisihUmur->m;

                    $old["umur_bulan"] =
                        (string) $umurBulan;

                    if (
                        $umurBulan < 0
                        || $umurBulan > 59
                    ) {
                        $error =
                            "Umur balita pada tanggal pengukuran harus berada antara 0 sampai 59 bulan.";
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan perubahan
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        /*
         * Nilai kosong untuk lingkar kepala atau LiLA
         * akan disimpan sebagai NULL.
         */

        $lingkarKepalaInput =
            $old["lingkar_kepala"];

        $lilaInput =
            $old["lila"];

        $stmtUpdate = mysqli_prepare(
            $conn,
            "UPDATE pengukuran_antropometri
             SET
                id_balita = ?,
                tanggal_pengukuran = ?,
                umur_bulan = ?,
                berat_badan = ?,
                tinggi_panjang_badan = ?,
                lingkar_kepala = NULLIF(?, ''),
                lila = NULLIF(?, '')
             WHERE id_pengukuran = ?"
        );

        if (!$stmtUpdate) {
            $error =
                "Sistem gagal menyiapkan perubahan data.";

        } else {

            mysqli_stmt_bind_param(
                $stmtUpdate,
                "isiddssi",
                $idBalita,
                $tanggalPengukuran,
                $umurBulan,
                $beratBadan,
                $tinggiPanjangBadan,
                $lingkarKepalaInput,
                $lilaInput,
                $idPengukuran
            );

            $berhasil =
                mysqli_stmt_execute(
                    $stmtUpdate
                );

            if ($berhasil) {

                mysqli_stmt_close(
                    $stmtUpdate
                );

                header(
                    "Location: data_pengukuran.php?pesan=edit_berhasil"
                );

                exit;
            }

            $error =
                "Data pengukuran gagal diperbarui: "
                . mysqli_stmt_error(
                    $stmtUpdate
                );

            mysqli_stmt_close(
                $stmtUpdate
            );
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
        nama_balita,
        tanggal_lahir
     FROM balita
     ORDER BY nama_balita ASC"
);

if (!$queryBalita) {
    die(
        "Gagal mengambil daftar balita: "
        . mysqli_error($conn)
    );
}

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

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    <i class="bi bi-pencil-square me-2"></i>
                    Edit Pengukuran
                </h1>

                <p class="page-subtitle">
                    Perbarui hasil pengukuran antropometri balita
                    dengan data yang benar.
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

                <?= amanEditPengukuran($error); ?>

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
                        Form Edit Pengukuran
                    </h4>

                    <small class="text-muted">
                        ID Pengukuran:
                        <?= $idPengukuran; ?>
                    </small>

                </div>

                <span class="badge badge-info">
                    <i class="bi bi-rulers"></i>
                    Antropometri
                </span>

            </div>

            <div class="card-body">

                <form
                    method="POST"
                    action="edit_pengukuran.php?id=<?= $idPengukuran; ?>"
                    autocomplete="off"
                >

                    <!-- Nama balita -->
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
                                    data-tanggal-lahir="<?= amanEditPengukuran(
                                        $balita["tanggal_lahir"]
                                    ); ?>"
                                    <?= (
                                        (string) $old["id_balita"]
                                        ===
                                        (string) $balita["id_balita"]
                                    )
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= amanEditPengukuran(
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
                                value="<?= amanEditPengukuran(
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
                                    value="<?= amanEditPengukuran(
                                        $old["umur_bulan"]
                                    ); ?>"
                                    min="0"
                                    max="59"
                                    placeholder="Otomatis"
                                    readonly
                                    required
                                >

                                <span class="input-group-text">
                                    bulan
                                </span>

                            </div>

                            <small class="text-muted">
                                Umur dihitung otomatis dari tanggal lahir balita dan tanggal pengukuran.
                            </small>

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
                                    value="<?= amanEditPengukuran(
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
                                    value="<?= amanEditPengukuran(
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
                                    value="<?= amanEditPengukuran(
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
                                    value="<?= amanEditPengukuran(
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
                            name="update"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-floppy"></i>
                            Simpan Perubahan
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

    </main>

</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const balitaSelect =
        document.getElementById("id_balita");

    const tanggalPengukuran =
        document.getElementById("tanggal_pengukuran");

    const umurBulanInput =
        document.getElementById("umur_bulan");

    if (
        !balitaSelect
        || !tanggalPengukuran
        || !umurBulanInput
    ) {
        return;
    }

    function parseTanggal(tanggal) {
        if (!tanggal) {
            return null;
        }

        const bagian =
            tanggal.split("-").map(Number);

        if (bagian.length !== 3) {
            return null;
        }

        return {
            tahun: bagian[0],
            bulan: bagian[1],
            hari: bagian[2]
        };
    }

    function hitungUmurBulan() {
        const optionBalita =
            balitaSelect.options[
                balitaSelect.selectedIndex
            ];

        if (
            !optionBalita
            || !optionBalita.value
            || !tanggalPengukuran.value
        ) {
            umurBulanInput.value = "";
            return;
        }

        const lahir =
            parseTanggal(
                optionBalita.dataset.tanggalLahir
            );

        const ukur =
            parseTanggal(
                tanggalPengukuran.value
            );

        if (!lahir || !ukur) {
            umurBulanInput.value = "";
            return;
        }

        let umurBulan =
            (ukur.tahun - lahir.tahun) * 12
            + (ukur.bulan - lahir.bulan);

        if (ukur.hari < lahir.hari) {
            umurBulan--;
        }

        if (
            umurBulan < 0
            || umurBulan > 59
        ) {
            umurBulanInput.value = "";
            return;
        }

        umurBulanInput.value =
            umurBulan;
    }

    balitaSelect.addEventListener(
        "change",
        hitungUmurBulan
    );

    tanggalPengukuran.addEventListener(
        "change",
        hitungUmurBulan
    );

    hitungUmurBulan();
});
</script>

<?php require_once "../includes/footer.php"; ?>