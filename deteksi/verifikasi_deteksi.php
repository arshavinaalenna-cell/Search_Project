<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_gizi"]);

$judulHalaman =
    "Verifikasi Hasil Deteksi | Sistem Deteksi Stunting";

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    header(
        "Location: hasil_deteksi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data hasil deteksi
|--------------------------------------------------------------------------
*/

$stmtData = mysqli_prepare(
    $conn,
    "SELECT
        hd.id_deteksi,
        hd.status_verifikasi,
        hd.catatan_verifikasi,
        b.nama_balita
     FROM hasil_deteksi hd
     INNER JOIN pengukuran_antropometri pa
        ON hd.id_pengukuran = pa.id_pengukuran
     INNER JOIN balita b
        ON pa.id_balita = b.id_balita
     WHERE hd.id_deteksi = ?
     LIMIT 1"
);

if (!$stmtData) {
    die(
        "Gagal menyiapkan data hasil deteksi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtData,
    "i",
    $id
);

mysqli_stmt_execute($stmtData);

$resultData =
    mysqli_stmt_get_result($stmtData);

$dataDeteksi =
    mysqli_fetch_assoc($resultData);

mysqli_stmt_close($stmtData);

if (!$dataDeteksi) {
    header(
        "Location: hasil_deteksi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Proses verifikasi
|--------------------------------------------------------------------------
*/

$error = "";

$statusVerifikasi =
    $dataDeteksi["status_verifikasi"]
    ?? "Belum diverifikasi";

$catatanVerifikasi =
    $dataDeteksi["catatan_verifikasi"]
    ?? "";

$daftarStatus = [
    "Belum diverifikasi",
    "Sudah diverifikasi",
    "Perlu pemeriksaan ulang"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $statusVerifikasi =
        trim($_POST["status_verifikasi"] ?? "");

    $catatanVerifikasi =
        trim($_POST["catatan_verifikasi"] ?? "");

    if (
        !in_array(
            $statusVerifikasi,
            $daftarStatus,
            true
        )
    ) {
        $error =
            "Status verifikasi tidak valid.";
    }

    if ($error === "") {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE hasil_deteksi SET
                status_verifikasi = ?,
                catatan_verifikasi = ?,
                diverifikasi_oleh = ?,
                tanggal_verifikasi = NOW()
             WHERE id_deteksi = ?"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan verifikasi hasil deteksi: "
                . mysqli_error($conn)
            );
        }

        $idUserAktif =
            (int) ($_SESSION["id_user"] ?? 0);

        mysqli_stmt_bind_param(
            $stmt,
            "ssii",
            $statusVerifikasi,
            $catatanVerifikasi,
            $idUserAktif,
            $id
        );

        if (!mysqli_stmt_execute($stmt)) {
            $error =
                "Verifikasi hasil deteksi gagal disimpan.";
        }

        mysqli_stmt_close($stmt);

        if ($error === "") {
            header(
                "Location: detail_deteksi.php?id="
                . $id
                . "&pesan=verifikasi_berhasil"
            );
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Template
|--------------------------------------------------------------------------
*/

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
                        <i class="bi bi-check2-circle me-2"></i>
                        Verifikasi Hasil Deteksi
                    </h4>

                    <small class="text-muted">
                        Tinjau dan tetapkan status verifikasi
                        hasil deteksi <?= htmlspecialchars(
                            $dataDeteksi["nama_balita"] ?? "balita",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>.
                    </small>

                </div>

                <a
                    href="detail_deteksi.php?id=<?= $id; ?>"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

            </div>

            <div class="card-body">

                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </div>

                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">

                        <label
                            for="status_verifikasi"
                            class="form-label"
                        >
                            Status Verifikasi
                            <span class="text-danger">*</span>
                        </label>

                        <select
                            name="status_verifikasi"
                            id="status_verifikasi"
                            class="form-select"
                            required
                        >

                            <?php foreach ($daftarStatus as $status): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                    <?= $statusVerifikasi === $status
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

                    <div class="form-group">

                        <label
                            for="catatan_verifikasi"
                            class="form-label"
                        >
                            Catatan Petugas Gizi
                        </label>

                        <textarea
                            name="catatan_verifikasi"
                            id="catatan_verifikasi"
                            class="form-control"
                            rows="4"
                            placeholder="Tambahkan catatan verifikasi bila diperlukan."
                        ><?= htmlspecialchars(
                            $catatanVerifikasi,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?></textarea>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            <i class="bi bi-floppy"></i>
                            Simpan Verifikasi
                        </button>

                        <a
                            href="detail_deteksi.php?id=<?= $id; ?>"
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

<?php require_once "../includes/footer.php"; ?>
