<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_kia"]);

$judulHalaman = "Tambah Riwayat Kesehatan | Sistem Deteksi Stunting";

$pesanError = "";

$idBalita = "";
$riwayatPenyakit = "";
$riwayatImunisasi = "";
$riwayatPerawatan = "";

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
        nik_balita
     FROM balita
     ORDER BY nama_balita ASC"
);

if (!$queryBalita) {
    die(
        "Gagal mengambil data balita: "
        . mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| Memproses penyimpanan data
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $idBalita = filter_input(
        INPUT_POST,
        "id_balita",
        FILTER_VALIDATE_INT
    );

    $riwayatPenyakit = trim(
        $_POST["riwayat_penyakit"] ?? ""
    );

    $riwayatImunisasi = trim(
        $_POST["riwayat_imunisasi"] ?? ""
    );

    $riwayatPerawatan = trim(
        $_POST["riwayat_perawatan"] ?? ""
    );

    /*
    |--------------------------------------------------------------------------
    | Validasi input
    |--------------------------------------------------------------------------
    */

    if (!$idBalita) {
        $pesanError = "Silakan pilih balita.";
    } elseif (
        $riwayatPenyakit === ""
        || $riwayatImunisasi === ""
        || $riwayatPerawatan === ""
    ) {
        $pesanError = "Semua data riwayat kesehatan wajib diisi.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan balita tersedia
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $cekBalita = mysqli_prepare(
            $conn,
            "SELECT id_balita
             FROM balita
             WHERE id_balita = ?
             LIMIT 1"
        );

        if (!$cekBalita) {
            $pesanError =
                "Gagal memeriksa data balita.";
        } else {
            mysqli_stmt_bind_param(
                $cekBalita,
                "i",
                $idBalita
            );

            mysqli_stmt_execute($cekBalita);

            $hasilBalita = mysqli_stmt_get_result(
                $cekBalita
            );

            if (mysqli_num_rows($hasilBalita) === 0) {
                $pesanError =
                    "Data balita tidak ditemukan.";
            }

            mysqli_stmt_close($cekBalita);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan riwayat kesehatan
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO riwayat_kesehatan (
                id_balita,
                riwayat_penyakit,
                riwayat_imunisasi,
                riwayat_perawatan
            ) VALUES (?, ?, ?, ?)"
        );

        if (!$stmtSimpan) {
            $pesanError =
                "Gagal menyiapkan penyimpanan data: "
                . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmtSimpan,
                "isss",
                $idBalita,
                $riwayatPenyakit,
                $riwayatImunisasi,
                $riwayatPerawatan
            );

            if (mysqli_stmt_execute($stmtSimpan)) {
                mysqli_stmt_close($stmtSimpan);

                header(
                    "Location: riwayat_kesehatan.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data gagal ditambahkan: "
                . mysqli_stmt_error($stmtSimpan);

            mysqli_stmt_close($stmtSimpan);
        }
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div
            class="d-flex flex-column flex-md-row
            justify-content-between align-items-md-center
            gap-3 mb-4"
        >
            <div>
                <h2 class="mb-1">
                    Tambah Riwayat Kesehatan
                </h2>

                <p class="text-muted mb-0">
                    Tambahkan riwayat penyakit, imunisasi,
                    dan perawatan balita.
                </p>
            </div>

            <a
                href="riwayat_kesehatan.php"
                class="btn btn-outline-secondary"
            >
                Kembali
            </a>
        </div>

        <?php if ($pesanError !== ""): ?>

            <div
                class="alert alert-danger
                alert-dismissible fade show"
                role="alert"
            >
                <?= htmlspecialchars(
                    $pesanError,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>

        <?php endif; ?>

        <?php if (
            mysqli_num_rows($queryBalita) === 0
        ): ?>

            <div class="alert alert-warning">
                Belum ada data balita. Tambahkan data balita
                terlebih dahulu melalui akun Kader.
            </div>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-body p-4">

                <form method="POST">

                    <div class="mb-3">

                        <label
                            for="id_balita"
                            class="form-label"
                        >
                            Nama Balita
                        </label>

                        <select
                            id="id_balita"
                            name="id_balita"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Pilih balita
                            </option>

                            <?php
                            mysqli_data_seek(
                                $queryBalita,
                                0
                            );
                            ?>

                            <?php while (
                                $balita =
                                    mysqli_fetch_assoc(
                                        $queryBalita
                                    )
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $balita["id_balita"] ?>"
                                    <?= (int) $idBalita ===
                                        (int) $balita["id_balita"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= htmlspecialchars(
                                        $balita["nama_balita"]
                                        . " - "
                                        . $balita["nik_balita"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label
                            for="riwayat_penyakit"
                            class="form-label"
                        >
                            Riwayat Penyakit
                        </label>

                        <textarea
                            id="riwayat_penyakit"
                            name="riwayat_penyakit"
                            class="form-control"
                            rows="3"
                            placeholder="Contoh: ISPA, diare, demam, atau tidak ada"
                            required
                        ><?= htmlspecialchars(
                            $riwayatPenyakit,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>

                    </div>

                    <div class="mb-3">

                        <label
                            for="riwayat_imunisasi"
                            class="form-label"
                        >
                            Riwayat Imunisasi
                        </label>

                        <textarea
                            id="riwayat_imunisasi"
                            name="riwayat_imunisasi"
                            class="form-control"
                            rows="3"
                            placeholder="Contoh: BCG, DPT, Polio, Campak"
                            required
                        ><?= htmlspecialchars(
                            $riwayatImunisasi,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>

                    </div>

                    <div class="mb-3">

                        <label
                            for="riwayat_perawatan"
                            class="form-label"
                        >
                            Riwayat Perawatan
                        </label>

                        <textarea
                            id="riwayat_perawatan"
                            name="riwayat_perawatan"
                            class="form-control"
                            rows="3"
                            placeholder="Contoh: Rawat jalan, rawat inap, atau tidak ada"
                            required
                        ><?= htmlspecialchars(
                            $riwayatPerawatan,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>

                    </div>

                    <div class="d-flex flex-wrap gap-2">

                        <button
                            type="submit"
                            class="btn btn-success"
                            <?= mysqli_num_rows(
                                $queryBalita
                            ) === 0
                                ? "disabled"
                                : "" ?>
                        >
                            Simpan Data
                        </button>

                        <a
                            href="riwayat_kesehatan.php"
                            class="btn btn-outline-secondary"
                        >
                            Batal
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>