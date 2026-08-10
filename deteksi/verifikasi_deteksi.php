<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_gizi"]);

$judulHalaman =
    "Verifikasi Hasil Deteksi | Sistem Deteksi Stunting";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header(
        "Location: hasil_deteksi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Petugas Gizi aktif
|--------------------------------------------------------------------------
*/

$stmtPuskesmas = mysqli_prepare(
    $conn,
    "SELECT
        u.id_puskesmas,
        p.nama_puskesmas
     FROM pengguna AS u
     LEFT JOIN puskesmas AS p
        ON u.id_puskesmas = p.id_puskesmas
     WHERE u.id_user = ?
     AND u.role = 'petugas_gizi'
     LIMIT 1"
);

if (!$stmtPuskesmas) {
    die(
        "Gagal memeriksa Puskesmas Petugas Gizi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPuskesmas,
    "i",
    $idUserAktif
);

mysqli_stmt_execute($stmtPuskesmas);

$hasilPuskesmas =
    mysqli_stmt_get_result(
        $stmtPuskesmas
    );

$dataPuskesmas =
    mysqli_fetch_assoc(
        $hasilPuskesmas
    );

mysqli_stmt_close(
    $stmtPuskesmas
);

if (
    !$dataPuskesmas
    || empty(
        $dataPuskesmas["id_puskesmas"]
    )
) {
    header(
        "Location: hasil_deteksi.php?pesan=tidak_ditemukan"
    );
    exit;
}

$idPuskesmasAktif =
    (int) $dataPuskesmas[
        "id_puskesmas"
    ];

$namaPuskesmasAktif =
    trim(
        (string) (
            $dataPuskesmas[
                "nama_puskesmas"
            ]
            ?? ""
        )
    );

/*
|--------------------------------------------------------------------------
| Ambil hasil deteksi yang boleh diverifikasi
|--------------------------------------------------------------------------
*/

$stmtData = mysqli_prepare(
    $conn,
    "SELECT
        hd.id_deteksi,
        hd.status_gizi,
        hd.status_stunting,
        hd.status_verifikasi,
        hd.catatan_verifikasi,

        pa.tanggal_pengukuran,

        b.id_balita,
        b.nama_balita,
        b.nik_balita,

        p.nama_puskesmas

     FROM hasil_deteksi AS hd

     INNER JOIN pengukuran_antropometri AS pa
        ON hd.id_pengukuran = pa.id_pengukuran

     INNER JOIN balita AS b
        ON pa.id_balita = b.id_balita

     LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas

     WHERE hd.id_deteksi = ?
     AND b.id_puskesmas = ?

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
    "ii",
    $id,
    $idPuskesmasAktif
);

mysqli_stmt_execute(
    $stmtData
);

$resultData =
    mysqli_stmt_get_result(
        $stmtData
    );

$dataDeteksi =
    mysqli_fetch_assoc(
        $resultData
    );

mysqli_stmt_close(
    $stmtData
);

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

        if (
            $statusVerifikasi ===
            "Belum diverifikasi"
        ) {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE hasil_deteksi AS hd
                 INNER JOIN pengukuran_antropometri AS pa
                    ON hd.id_pengukuran = pa.id_pengukuran
                 INNER JOIN balita AS b
                    ON pa.id_balita = b.id_balita
                 SET
                    hd.status_verifikasi = ?,
                    hd.catatan_verifikasi = ?,
                    hd.diverifikasi_oleh = NULL,
                    hd.tanggal_verifikasi = NULL
                 WHERE hd.id_deteksi = ?
                 AND b.id_puskesmas = ?"
            );

            if (!$stmt) {
                die(
                    "Gagal menyiapkan verifikasi hasil deteksi: "
                    . mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "ssii",
                $statusVerifikasi,
                $catatanVerifikasi,
                $id,
                $idPuskesmasAktif
            );

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE hasil_deteksi AS hd
                 INNER JOIN pengukuran_antropometri AS pa
                    ON hd.id_pengukuran = pa.id_pengukuran
                 INNER JOIN balita AS b
                    ON pa.id_balita = b.id_balita
                 SET
                    hd.status_verifikasi = ?,
                    hd.catatan_verifikasi = ?,
                    hd.diverifikasi_oleh = ?,
                    hd.tanggal_verifikasi = NOW()
                 WHERE hd.id_deteksi = ?
                 AND b.id_puskesmas = ?"
            );

            if (!$stmt) {
                die(
                    "Gagal menyiapkan verifikasi hasil deteksi: "
                    . mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "ssiii",
                $statusVerifikasi,
                $catatanVerifikasi,
                $idUserAktif,
                $id,
                $idPuskesmasAktif
            );
        }

        if (
            !mysqli_stmt_execute(
                $stmt
            )
        ) {
            $error =
                "Verifikasi hasil deteksi gagal disimpan.";
        }

        $jumlahBerubah =
            mysqli_stmt_affected_rows(
                $stmt
            );

        mysqli_stmt_close(
            $stmt
        );

        if (
            $error === ""
            && $jumlahBerubah >= 0
        ) {
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

                <div class="row g-3 mb-4">

                    <div class="col-12 col-md-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Balita
                            </span>

                            <div class="detail-value">
                                <strong>
                                    <?= htmlspecialchars(
                                        $dataDeteksi["nama_balita"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </strong>
                            </div>

                            <div class="form-text">
                                NIK:
                                <?= htmlspecialchars(
                                    $dataDeteksi["nik_balita"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-md-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Hasil Deteksi
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $dataDeteksi["status_stunting"]
                                        ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                            <div class="form-text">
                                Status gizi:
                                <?= htmlspecialchars(
                                    $dataDeteksi["status_gizi"]
                                        ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-md-4">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Puskesmas
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-hospital me-1"></i>
                                <?= htmlspecialchars(
                                    $dataDeteksi["nama_puskesmas"]
                                        ?? $namaPuskesmasAktif,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Verifikasi ini menetapkan
                    <strong>status hasil deteksi</strong>,
                    bukan memverifikasi identitas atau data balita.
                </div>

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

                        <div class="form-text">
                            Pilih hasil peninjauan Petugas Gizi:
                            Belum diverifikasi, Sudah diverifikasi,
                            atau Perlu pemeriksaan ulang.
                        </div>

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
