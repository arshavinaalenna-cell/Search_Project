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
        riwayat_perawatan,
        penyakit_penyerta,
        red_flag,
        status_rujukan,
        rekomendasi_rujukan,
        catatan_kia
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

$penyakitPenyerta =
    $dataRiwayat["penyakit_penyerta"] ?? "";

$redFlag =
    $dataRiwayat["red_flag"] ?? "";

$statusRujukan =
    $dataRiwayat["status_rujukan"] ?? "";

$rekomendasiRujukan =
    $dataRiwayat["rekomendasi_rujukan"] ?? "";

$catatanKia =
    $dataRiwayat["catatan_kia"] ?? "";

/*
|--------------------------------------------------------------------------
| Mengambil identitas balita pemilik riwayat
|--------------------------------------------------------------------------
|
| Balita dikunci saat edit agar riwayat kesehatan tidak dapat dipindahkan
| ke balita lain.
|
*/

$stmtBalita = mysqli_prepare(
    $conn,
    "SELECT
        id_balita,
        nik_balita,
        nama_balita
     FROM balita
     WHERE id_balita = ?
     LIMIT 1"
);

if (!$stmtBalita) {
    die(
        "Gagal mengambil data balita: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtBalita,
    "i",
    $idBalita
);

mysqli_stmt_execute($stmtBalita);

$hasilBalita =
    mysqli_stmt_get_result($stmtBalita);

$dataBalita =
    mysqli_fetch_assoc($hasilBalita);

mysqli_stmt_close($stmtBalita);

if (!$dataBalita) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Memproses perubahan data
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $riwayatPenyakit = trim(
        $_POST["riwayat_penyakit"] ?? ""
    );

    $riwayatImunisasi = trim(
        $_POST["riwayat_imunisasi"] ?? ""
    );

    $riwayatPerawatan = trim(
        $_POST["riwayat_perawatan"] ?? ""
    );

    $penyakitPenyerta = trim(
        $_POST["penyakit_penyerta"] ?? ""
    );

    $redFlag = trim(
        $_POST["red_flag"] ?? ""
    );

    $statusRujukan = trim(
        $_POST["status_rujukan"] ?? ""
    );

    $rekomendasiRujukan = trim(
        $_POST["rekomendasi_rujukan"] ?? ""
    );

    $catatanKia = trim(
        $_POST["catatan_kia"] ?? ""
    );

    if (
        $riwayatPenyakit === ""
        || $riwayatImunisasi === ""
        || $riwayatPerawatan === ""
        || $penyakitPenyerta === ""
        || $redFlag === ""
        || $statusRujukan === ""
        || $rekomendasiRujukan === ""
        || $catatanKia === ""
    ) {
        $pesanError = "Semua data wajib diisi.";
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
                riwayat_penyakit = ?,
                riwayat_imunisasi = ?,
                riwayat_perawatan = ?,
                penyakit_penyerta = ?,
                red_flag = ?,
                status_rujukan = ?,
                rekomendasi_rujukan = ?,
                catatan_kia = ?
             WHERE id_riwayat = ?"
        );

        if (!$stmtUpdate) {
            $pesanError =
                "Gagal menyiapkan perubahan data: "
                . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmtUpdate,
                "ssssssssi",
                $riwayatPenyakit,
                $riwayatImunisasi,
                $riwayatPerawatan,
                $penyakitPenyerta,
                $redFlag,
                $statusRujukan,
                $rekomendasiRujukan,
                $catatanKia,
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
                        Perbarui riwayat kesehatan, red flag,
                        rujukan, dan catatan KIA balita.
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

                            <label class="form-label">
                                Nama Balita
                            </label>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Balita Pemilik Riwayat
                                </span>

                                <div class="detail-value">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $dataBalita["nama_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </strong>

                                    <span class="text-muted">
                                        —
                                        <?= htmlspecialchars(
                                            $dataBalita["nik_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </span>

                                </div>

                            </div>

                            <div class="form-text">
                                Balita tidak dapat diubah saat mengedit
                                riwayat kesehatan agar data tidak berpindah
                                ke balita lain.
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

                        <div class="col-12">

                            <hr>

                            <h6 class="mb-1">
                                Evaluasi KIA
                            </h6>

                            <small class="text-muted">
                                Lengkapi penyakit penyerta,
                                red flag, dan kebutuhan rujukan.
                            </small>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="penyakit_penyerta"
                                class="form-label"
                            >
                                Penyakit Penyerta
                            </label>

                            <textarea
                                id="penyakit_penyerta"
                                name="penyakit_penyerta"
                                class="form-control"
                                rows="4"
                                placeholder="Isi Tidak ada jika tidak terdapat penyakit penyerta"
                                required
                            ><?= htmlspecialchars(
                                $penyakitPenyerta,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="red_flag"
                                class="form-label"
                            >
                                Red Flag Kesehatan
                            </label>

                            <textarea
                                id="red_flag"
                                name="red_flag"
                                class="form-control"
                                rows="4"
                                placeholder="Isi Tidak ada atau jelaskan red flag yang ditemukan"
                                required
                            ><?= htmlspecialchars(
                                $redFlag,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Jika tidak ada red flag,
                                isi dengan "Tidak ada".
                            </div>

                        </div>

                        <div class="col-12 col-lg-4">

                            <label
                                for="status_rujukan"
                                class="form-label"
                            >
                                Status Rujukan
                            </label>

                            <select
                                id="status_rujukan"
                                name="status_rujukan"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    -- Pilih Status --
                                </option>

                                <?php
                                $daftarStatusRujukan = [
                                    "Tidak Perlu",
                                    "Direkomendasikan",
                                    "Dirujuk"
                                ];

                                foreach (
                                    $daftarStatusRujukan
                                    as $status
                                ):
                                ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $status,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>"
                                        <?= $statusRujukan === $status
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $status,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-12 col-lg-8">

                            <label
                                for="rekomendasi_rujukan"
                                class="form-label"
                            >
                                Rekomendasi Rujukan
                            </label>

                            <textarea
                                id="rekomendasi_rujukan"
                                name="rekomendasi_rujukan"
                                class="form-control"
                                rows="3"
                                placeholder="Contoh: Pemeriksaan dokter anak atau isi Tidak ada"
                                required
                            ><?= htmlspecialchars(
                                $rekomendasiRujukan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                        <div class="col-12">

                            <label
                                for="catatan_kia"
                                class="form-label"
                            >
                                Catatan KIA
                            </label>

                            <textarea
                                id="catatan_kia"
                                name="catatan_kia"
                                class="form-control"
                                rows="4"
                                placeholder="Tambahkan catatan Petugas KIA"
                                required
                            ><?= htmlspecialchars(
                                $catatanKia,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

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