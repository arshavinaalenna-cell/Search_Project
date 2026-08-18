<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Zona waktu aplikasi - WIB / Jakarta
|--------------------------------------------------------------------------
|
| Penanda waktu pada keluhan tambahan mengikuti waktu Indonesia Barat
| (UTC+7), bukan timezone bawaan server.
|
*/

date_default_timezone_set("Asia/Jakarta");

// Samakan timezone sesi MySQL jika ada query yang memakai NOW()/CURDATE().
@mysqli_query($conn, "SET time_zone = '+07:00'");

cekRole(["orang_tua"]);

$judulHalaman = "Mulai Konsultasi | Sistem Deteksi Stunting";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$idKonsultasi = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$pesanError = "";
$keluhan = "";

if (!$idKonsultasi) {
    header("Location: data_konsultasi.php?pesan=pengajuan_dikunci");
    exit;
}

/*
|--------------------------------------------------------------------------
| Tiket harus dibuat oleh keputusan Ahli Gizi dan milik anak akun ini
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        k.id_konsultasi,
        k.id_deteksi,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        k.status_konsultasi,
        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu,
        ps.nama_puskesmas,
        pg.nama AS nama_petugas,
        hd.status_stunting,
        hd.keputusan_konsultasi
     FROM konsultasi k
     INNER JOIN balita b
        ON k.id_balita = b.id_balita
     INNER JOIN hasil_deteksi hd
        ON k.id_deteksi = hd.id_deteksi
     LEFT JOIN puskesmas ps
        ON b.id_puskesmas = ps.id_puskesmas
     LEFT JOIN pengguna pg
        ON k.id_petugas = pg.id_user
     WHERE k.id_konsultasi = ?
     AND b.id_user = ?
     AND k.sumber_pengajuan = 'ahli_gizi'
     AND hd.keputusan_konsultasi = 'Perlu konsultasi'
     LIMIT 1"
);

if (!$stmt) {
    die("Gagal menyiapkan data konsultasi: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "ii", $idKonsultasi, $idUserAktif);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasil);
mysqli_stmt_close($stmt);

if (!$data) {
    header("Location: data_konsultasi.php?pesan=tidak_ditemukan");
    exit;
}

$keluhanTersimpan = trim((string) ($data["keluhan"] ?? ""));

$statusKonsultasiSaatIni =
    strtolower(
        trim(
            (string) ($data["status_konsultasi"] ?? "aktif")
        )
    );

$konsultasiSudahSelesai = $statusKonsultasiSaatIni === "selesai";

$sudahPernahMengirimKeluhan = $keluhanTersimpan !== "";

/*
|--------------------------------------------------------------------------
| Keluhan boleh dikirim berkali-kali selama Ahli Gizi belum menyelesaikan
| konsultasi. Setelah konsultasi berstatus "selesai", tiket dikunci dan
| Orang Tua tidak dapat lagi menambahkan keluhan.
|--------------------------------------------------------------------------
*/
if ($konsultasiSudahSelesai) {
    header(
        "Location: detail_konsultasi.php?id=" . $idKonsultasi
        . "&kembali=riwayat"
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $keluhan = trim($_POST["keluhan"] ?? "");

    if ($keluhan === "") {
        $pesanError = "Tuliskan keluhan atau hal yang ingin dikonsultasikan.";
    } elseif (mb_strlen($keluhan) < 5) {
        $pesanError = "Keluhan terlalu singkat. Jelaskan kondisi anak dengan lebih jelas.";
    }

    if ($pesanError === "") {

        /*
        |----------------------------------------------------------------
        | Jika sudah pernah mengirim keluhan sebelumnya, keluhan baru
        | ditambahkan ke bawah keluhan lama (bukan menimpa) supaya
        | riwayat keluhan Orang Tua tetap tersimpan.
        |----------------------------------------------------------------
        */
        if ($sudahPernahMengirimKeluhan) {
            $keluhanGabungan =
                $keluhanTersimpan
                . "\n\n----------\n"
                . "Keluhan tambahan (" . date("d-m-Y H:i") . "):\n"
                . $keluhan;
        } else {
            $keluhanGabungan = $keluhan;
        }

        $stmtUpdate = mysqli_prepare(
            $conn,
            "UPDATE konsultasi k
             INNER JOIN balita b
                ON k.id_balita = b.id_balita
             INNER JOIN hasil_deteksi hd
                ON k.id_deteksi = hd.id_deteksi
             SET
                k.keluhan = ?,
                k.tanggal = CURDATE()
             WHERE k.id_konsultasi = ?
             AND b.id_user = ?
             AND k.sumber_pengajuan = 'ahli_gizi'
             AND hd.keputusan_konsultasi = 'Perlu konsultasi'
             AND LOWER(TRIM(COALESCE(k.status_konsultasi, 'aktif'))) <> 'selesai'"
        );

        if (!$stmtUpdate) {
            $pesanError = "Gagal menyiapkan konsultasi: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmtUpdate,
                "sii",
                $keluhanGabungan,
                $idKonsultasi,
                $idUserAktif
            );

            mysqli_stmt_execute($stmtUpdate);
            $jumlahBerubah = mysqli_stmt_affected_rows($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);

            if ($jumlahBerubah > 0) {
                header(
                    "Location: data_konsultasi.php?pesan="
                    . ($sudahPernahMengirimKeluhan
                        ? "keluhan_ditambahkan"
                        : "keluhan_berhasil")
                );
                exit;
            }

            $pesanError =
                "Keluhan tidak dapat dikirim. Konsultasi mungkin sudah diselesaikan oleh Ahli Gizi atau keputusan Ahli Gizi telah berubah.";
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
                        <i class="bi bi-chat-heart me-2"></i>
                        <?= $sudahPernahMengirimKeluhan
                            ? "Tambah Keluhan Konsultasi"
                            : "Mulai Konsultasi Ahli Gizi"; ?>
                    </h4>
                    <small class="text-muted">
                        <?= $sudahPernahMengirimKeluhan
                            ? "Konsultasi masih berjalan. Anda dapat menambahkan keluhan lain selama Ahli Gizi belum menyelesaikan konsultasi ini."
                            : "Konsultasi ini tersedia karena Ahli Gizi telah menetapkan anak perlu konsultasi."; ?>
                    </small>
                </div>

                <a href="data_konsultasi.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
            </div>

            <div class="card-body">

                <div class="alert alert-warning">
                    <i class="bi bi-bell-fill me-1"></i>
                    Hasil deteksi <strong><?= htmlspecialchars(
                        $data["status_stunting"] ?? "-",
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?></strong> telah diverifikasi dan Ahli Gizi menyetujui konsultasi untuk anak Anda.
                </div>

                <?php if ($pesanError !== ""): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <?= htmlspecialchars($pesanError, ENT_QUOTES, "UTF-8"); ?>
                    </div>
                <?php endif; ?>

                <?php if ($sudahPernahMengirimKeluhan): ?>
                    <div class="detail-item mb-4">
                        <span class="detail-label">Keluhan Sebelumnya</span>
                        <div class="detail-value">
                            <?= nl2br(
                                htmlspecialchars(
                                    $keluhanTersimpan,
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                            ); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="detail-item h-100">
                            <span class="detail-label">Nama Balita</span>
                            <div class="detail-value">
                                <strong><?= htmlspecialchars(
                                    $data["nama_balita"] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?></strong>
                            </div>
                            <div class="form-text">
                                NIK: <?= htmlspecialchars($data["nik_balita"] ?? "-", ENT_QUOTES, "UTF-8"); ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="detail-item h-100">
                            <span class="detail-label">Ahli Gizi</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($data["nama_petugas"] ?? "-", ENT_QUOTES, "UTF-8"); ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="detail-item h-100">
                            <span class="detail-label">Puskesmas</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($data["nama_puskesmas"] ?? "-", ENT_QUOTES, "UTF-8"); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="keluhan" class="form-label">
                            <?= $sudahPernahMengirimKeluhan
                                ? "Keluhan Tambahan"
                                : "Keluhan / Hal yang Ingin Dikonsultasikan"; ?>
                            <span class="text-danger">*</span>
                        </label>

                        <textarea
                            name="keluhan"
                            id="keluhan"
                            class="form-control"
                            rows="6"
                            required
                            placeholder="Tuliskan kondisi anak, keluhan, pola makan, atau hal yang ingin ditanyakan kepada Ahli Gizi."
                        ><?= htmlspecialchars($keluhan, ENT_QUOTES, "UTF-8"); ?></textarea>

                        <div class="form-text">
                            <?= $sudahPernahMengirimKeluhan
                                ? "Keluhan tambahan ini akan ditambahkan ke keluhan sebelumnya dan tetap dapat dikirim selama konsultasi belum diselesaikan Ahli Gizi."
                                : "Setelah dikirim, Ahli Gizi akan dapat memberikan hasil konsultasi dan tindak lanjut."; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i>
                            <?= $sudahPernahMengirimKeluhan
                                ? "Kirim Keluhan Tambahan"
                                : "Mulai Konsultasi"; ?>
                        </button>

                        <a href="data_konsultasi.php" class="btn btn-light">
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
