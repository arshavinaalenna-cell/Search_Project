<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_kia"]);

$judulHalaman = "Edit Riwayat Kesehatan | Sistem Deteksi Stunting";
$pesanError = "";

$idRiwayat = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idRiwayat) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data riwayat kesehatan
|--------------------------------------------------------------------------
*/

$stmtRiwayat = mysqli_prepare(
    $conn,
    "SELECT
        id_riwayat,
        id_balita,
        riwayat_penyakit,
        riwayat_imunisasi,
        riwayat_perawatan
     FROM riwayat_kesehatan
     WHERE id_riwayat = ?
     LIMIT 1"
);

if (!$stmtRiwayat) {
    die(
        "Gagal menyiapkan data riwayat kesehatan: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtRiwayat,
    "i",
    $idRiwayat
);

mysqli_stmt_execute($stmtRiwayat);

$hasilRiwayat = mysqli_stmt_get_result($stmtRiwayat);
$dataRiwayat = mysqli_fetch_assoc($hasilRiwayat);

mysqli_stmt_close($stmtRiwayat);

if (!$dataRiwayat) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$idBalita = (int) $dataRiwayat["id_balita"];

$riwayatPenyakit =
    $dataRiwayat["riwayat_penyakit"] ?? "";

$riwayatImunisasi =
    $dataRiwayat["riwayat_imunisasi"] ?? "";

$riwayatPerawatan =
    $dataRiwayat["riwayat_perawatan"] ?? "";

/*
|--------------------------------------------------------------------------
| Mengambil daftar balita
|--------------------------------------------------------------------------
*/

$queryBalita = mysqli_query(
    $conn,
    "SELECT
        id_balita,
        nik_balita,
        nama_balita
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
| Memproses perubahan data
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

    if (
        !$idBalita
        || $riwayatPenyakit === ""
        || $riwayatImunisasi === ""
        || $riwayatPerawatan === ""
    ) {
        $pesanError = "Semua data wajib diisi.";
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

            if (
                mysqli_num_rows($hasilBalita) === 0
            ) {
                $pesanError =
                    "Data balita tidak ditemukan.";
            }

            mysqli_stmt_close($cekBalita);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memperbarui riwayat kesehatan
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $stmtUpdate = mysqli_prepare(
            $conn,
            "UPDATE riwayat_kesehatan
             SET
                id_balita = ?,
                riwayat_penyakit = ?,
                riwayat_imunisasi = ?,
                riwayat_perawatan = ?
             WHERE id_riwayat = ?"
        );

        if (!$stmtUpdate) {
            $pesanError =
                "Gagal menyiapkan perubahan data: "
                . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmtUpdate,
                "isssi",
                $idBalita,
                $riwayatPenyakit,
                $riwayatImunisasi,
                $riwayatPerawatan,
                $idRiwayat
            );

            if (mysqli_stmt_execute($stmtUpdate)) {
                mysqli_stmt_close($stmtUpdate);

                header(
                    "Location: riwayat_kesehatan.php?pesan=edit_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data gagal diperbarui: "
                . mysqli_stmt_error($stmtUpdate);

            mysqli_stmt_close($stmtUpdate);
        }
    }
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
                        Edit Riwayat Kesehatan
                    </h4>

                    <small class="text-muted">
                        Perbarui riwayat penyakit, imunisasi,
                        dan perawatan balita.
                    </small>

                </div>

                <a
                    href="riwayat_kesehatan.php"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

            </div>

            <div class="card-body">

                <?php if ($pesanError !== ""): ?>

                    <div
                        class="alert alert-danger
                        alert-dismissible fade show"
                        role="alert"
                    >
                        <i class="bi bi-x-circle me-1"></i>

                        <?= htmlspecialchars(
                            $pesanError,
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

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12">

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
                                    -- Pilih Balita --
                                </option>

                                <?php while (
                                    $balita =
                                        mysqli_fetch_assoc(
                                            $queryBalita
                                        )
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $balita["id_balita"]; ?>"
                                        <?= $idBalita ===
                                            (int) $balita["id_balita"]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $balita["nama_balita"]
                                            . " - "
                                            . $balita["nik_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                            <div class="form-text">
                                Pilih balita yang riwayat
                                kesehatannya akan diperbarui.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

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
                                rows="5"
                                required
                            ><?= htmlspecialchars(
                                $riwayatPenyakit,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Perbarui penyakit yang pernah
                                dialami atau isi "Tidak ada".
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

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
                                rows="5"
                                required
                            ><?= htmlspecialchars(
                                $riwayatImunisasi,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Perbarui daftar imunisasi yang
                                sudah diterima balita.
                            </div>

                        </div>

                        <div class="col-12">

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
                                rows="4"
                                required
                            ><?= htmlspecialchars(
                                $riwayatPerawatan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Perbarui riwayat rawat jalan,
                                rawat inap, atau perawatan lain.
                            </div>

                        </div>

                    </div>

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-check-circle"></i>
                            Simpan Perubahan
                        </button>

                        <a
                            href="riwayat_kesehatan.php"
                            class="btn btn-outline-secondary"
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

<?php require_once "../includes/footer.php"; ?>