<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_gizi"]);

$judulHalaman = "Edit Konsultasi | Sistem Deteksi Stunting";

$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$pesanError = "";

$idKonsultasi = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idKonsultasi) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data konsultasi
|--------------------------------------------------------------------------
|
| Petugas Gizi hanya dapat membuka konsultasi yang ditugaskan kepadanya.
|
*/

$stmtKonsultasi = mysqli_prepare(
    $conn,
    "SELECT
        k.id_konsultasi,
        k.id_balita,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu
     FROM konsultasi k
     INNER JOIN balita b
        ON k.id_balita = b.id_balita
     WHERE k.id_konsultasi = ?
     AND k.id_petugas = ?
     LIMIT 1"
);

if (!$stmtKonsultasi) {
    die(
        "Gagal menyiapkan data konsultasi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtKonsultasi,
    "ii",
    $idKonsultasi,
    $idUserAktif
);

mysqli_stmt_execute($stmtKonsultasi);

$hasilKonsultasi = mysqli_stmt_get_result(
    $stmtKonsultasi
);

$dataKonsultasi = mysqli_fetch_assoc(
    $hasilKonsultasi
);

mysqli_stmt_close($stmtKonsultasi);

if (!$dataKonsultasi) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$hasilKonsultasiForm =
    $dataKonsultasi["hasil_konsultasi"] ?? "";

$tindakLanjut =
    $dataKonsultasi["tindak_lanjut"] ?? "";

/*
|--------------------------------------------------------------------------
| Memproses perubahan konsultasi
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hasilKonsultasiForm = trim(
        $_POST["hasil_konsultasi"] ?? ""
    );

    $tindakLanjut = trim(
        $_POST["tindak_lanjut"] ?? ""
    );

    if ($hasilKonsultasiForm === "") {
        $pesanError =
            "Hasil konsultasi wajib diisi.";
    } elseif ($tindakLanjut === "") {
        $pesanError =
            "Tindak lanjut wajib diisi.";
    }

    if ($pesanError === "") {
        $stmtUpdate = mysqli_prepare(
            $conn,
            "UPDATE konsultasi
             SET
                hasil_konsultasi = ?,
                tindak_lanjut = ?
             WHERE id_konsultasi = ?
             AND id_petugas = ?"
        );

        if (!$stmtUpdate) {
            $pesanError =
                "Gagal menyiapkan perubahan konsultasi: "
                . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmtUpdate,
                "ssii",
                $hasilKonsultasiForm,
                $tindakLanjut,
                $idKonsultasi,
                $idUserAktif
            );

            if (mysqli_stmt_execute($stmtUpdate)) {
                mysqli_stmt_close($stmtUpdate);

                header(
                    "Location: data_konsultasi.php?pesan=edit_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data konsultasi gagal diperbarui: "
                . mysqli_stmt_error($stmtUpdate);

            mysqli_stmt_close($stmtUpdate);
        }
    }
}

$tanggalTampil = "-";

if (!empty($dataKonsultasi["tanggal"])) {
    $tanggalTampil = date(
        "d-m-Y",
        strtotime(
            $dataKonsultasi["tanggal"]
        )
    );
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="card content-card mb-4">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Isi Hasil Konsultasi
                    </h4>

                    <small class="text-muted">
                        Tinjau keluhan orang tua lalu isi hasil
                        konsultasi dan tindak lanjut.
                    </small>

                </div>

                <a
                    href="data_konsultasi.php"
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

                <div class="row g-3">

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Tanggal Konsultasi
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-calendar3 me-1"></i>

                                <?= htmlspecialchars(
                                    $tanggalTampil,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Balita
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-person-heart me-1"></i>

                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nama_balita"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                NIK Balita
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nik_balita"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Ibu
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nama_ibu"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12">

                        <div class="detail-item">

                            <span class="detail-label">
                                Keluhan
                            </span>

                            <div class="detail-value">
                                <?= nl2br(
                                    htmlspecialchars(
                                        $dataKonsultasi[
                                            "keluhan"
                                        ] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ); ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Hasil & Tindak Lanjut
                    </h4>

                    <small class="text-muted">
                        Lengkapi hasil konsultasi dan rencana
                        tindak lanjut balita.
                    </small>

                </div>

                <span class="badge badge-info">
                    <i class="bi bi-heart-pulse"></i>
                    Petugas Gizi
                </span>

            </div>

            <div class="card-body">

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">

                            <label
                                for="hasil_konsultasi"
                                class="form-label"
                            >
                                Hasil Konsultasi
                            </label>

                            <textarea
                                id="hasil_konsultasi"
                                name="hasil_konsultasi"
                                class="form-control"
                                rows="6"
                                placeholder="Tuliskan hasil pemeriksaan dan saran gizi"
                                required
                            ><?= htmlspecialchars(
                                $hasilKonsultasiForm,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Catat hasil konsultasi, edukasi,
                                atau saran gizi yang diberikan.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="tindak_lanjut"
                                class="form-label"
                            >
                                Tindak Lanjut
                            </label>

                            <textarea
                                id="tindak_lanjut"
                                name="tindak_lanjut"
                                class="form-control"
                                rows="6"
                                placeholder="Tuliskan rencana pemantauan atau tindakan berikutnya"
                                required
                            ><?= htmlspecialchars(
                                $tindakLanjut,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Catat rencana pemantauan,
                                kunjungan ulang, atau tindakan berikutnya.
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
                            Simpan Hasil Konsultasi
                        </button>

                        <a
                            href="detail_konsultasi.php?id=<?= (int) $idKonsultasi; ?>"
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