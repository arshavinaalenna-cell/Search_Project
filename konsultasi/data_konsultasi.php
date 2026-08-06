<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua",
    "petugas_gizi",
    "kepala_puskesmas"
]);

$judulHalaman = "Data Konsultasi | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$bolehTambah = in_array(
    $roleAktif,
    ["orang_tua", "petugas_gizi"],
    true
);

$bolehMengelola = $roleAktif === "petugas_gizi";
$modeMonitoring = $roleAktif === "kepala_puskesmas";

$cari = trim($_GET["cari"] ?? "");
$kataKunci = "%" . $cari . "%";

/*
|--------------------------------------------------------------------------
| FUNGSI OUTPUT AMAN
|--------------------------------------------------------------------------
*/
function amanKonsultasi($nilai): string
{
    if ($nilai === null || $nilai === "") {
        return "-";
    }

    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| DATA KONSULTASI
|--------------------------------------------------------------------------
| Orang tua hanya melihat konsultasi milik balitanya sendiri.
| Petugas Gizi dan Kepala Puskesmas dapat melihat seluruh konsultasi.
*/
if ($roleAktif === "orang_tua") {
    if ($cari !== "") {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                k.id_konsultasi,
                k.id_balita,
                k.id_petugas,
                k.tanggal,
                k.hasil_konsultasi,
                k.tindak_lanjut,
                b.nama_balita,
                b.nik_balita,
                p.nama AS nama_petugas
             FROM konsultasi AS k
             INNER JOIN balita AS b
                ON k.id_balita = b.id_balita
             LEFT JOIN pengguna AS p
                ON k.id_petugas = p.id_user
             WHERE b.id_user = ?
               AND (
                    b.nama_balita LIKE ?
                    OR b.nik_balita LIKE ?
                    OR p.nama LIKE ?
                    OR k.hasil_konsultasi LIKE ?
                    OR k.tindak_lanjut LIKE ?
               )
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian konsultasi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssss",
            $idUserAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                k.id_konsultasi,
                k.id_balita,
                k.id_petugas,
                k.tanggal,
                k.hasil_konsultasi,
                k.tindak_lanjut,
                b.nama_balita,
                b.nik_balita,
                p.nama AS nama_petugas
             FROM konsultasi AS k
             INNER JOIN balita AS b
                ON k.id_balita = b.id_balita
             LEFT JOIN pengguna AS p
                ON k.id_petugas = p.id_user
             WHERE b.id_user = ?
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil data konsultasi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idUserAktif
        );
    }
} else {
    if ($cari !== "") {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                k.id_konsultasi,
                k.id_balita,
                k.id_petugas,
                k.tanggal,
                k.hasil_konsultasi,
                k.tindak_lanjut,
                b.nama_balita,
                b.nik_balita,
                p.nama AS nama_petugas
             FROM konsultasi AS k
             INNER JOIN balita AS b
                ON k.id_balita = b.id_balita
             LEFT JOIN pengguna AS p
                ON k.id_petugas = p.id_user
             WHERE (
                    b.nama_balita LIKE ?
                    OR b.nik_balita LIKE ?
                    OR p.nama LIKE ?
                    OR k.hasil_konsultasi LIKE ?
                    OR k.tindak_lanjut LIKE ?
             )
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian konsultasi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                k.id_konsultasi,
                k.id_balita,
                k.id_petugas,
                k.tanggal,
                k.hasil_konsultasi,
                k.tindak_lanjut,
                b.nama_balita,
                b.nik_balita,
                p.nama AS nama_petugas
             FROM konsultasi AS k
             INNER JOIN balita AS b
                ON k.id_balita = b.id_balita
             LEFT JOIN pengguna AS p
                ON k.id_petugas = p.id_user
             ORDER BY k.id_konsultasi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil data konsultasi: "
                . mysqli_error($conn)
            );
        }
    }
}

if (!mysqli_stmt_execute($stmt)) {
    die(
        "Gagal menjalankan data konsultasi: "
        . mysqli_stmt_error($stmt)
    );
}

$query = mysqli_stmt_get_result($stmt);

if (!$query) {
    die(
        "Gagal membaca hasil data konsultasi: "
        . mysqli_stmt_error($stmt)
    );
}

$totalData = mysqli_num_rows($query);

/*
|--------------------------------------------------------------------------
| PESAN HALAMAN
|--------------------------------------------------------------------------
*/
$pesan = $_GET["pesan"] ?? "";
$jenisAlert = "";
$isiPesan = "";

switch ($pesan) {
    case "tambah_berhasil":
        $jenisAlert = "success";
        $isiPesan = "Data konsultasi berhasil ditambahkan.";
        break;

    case "edit_berhasil":
        $jenisAlert = "success";
        $isiPesan = "Data konsultasi berhasil diperbarui.";
        break;

    case "hapus_berhasil":
        $jenisAlert = "success";
        $isiPesan = "Data konsultasi berhasil dihapus.";
        break;

    case "hapus_gagal":
        $jenisAlert = "danger";
        $isiPesan = "Data konsultasi gagal dihapus.";
        break;

    case "tidak_ditemukan":
        $jenisAlert = "warning";
        $isiPesan = "Data konsultasi tidak ditemukan.";
        break;
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

            <div>
                <h2 class="mb-1">
                    Data Konsultasi
                </h2>

                <p class="text-muted mb-0">
                    <?php if ($modeMonitoring): ?>
                        Monitoring riwayat konsultasi gizi dan tindak lanjut balita.
                    <?php elseif ($roleAktif === "orang_tua"): ?>
                        Riwayat konsultasi gizi dan tindak lanjut balita Anda.
                    <?php else: ?>
                        Kelola konsultasi gizi dan tindak lanjut balita.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($bolehTambah): ?>

                <a
                    href="tambah_konsultasi.php"
                    class="btn btn-success"
                >
                    <i class="bi bi-plus-circle me-1"></i>
                    Tambah Konsultasi
                </a>

            <?php endif; ?>

        </div>

        <?php if ($isiPesan !== ""): ?>

            <div
                class="alert alert-<?= amanKonsultasi($jenisAlert); ?> alert-dismissible fade show"
                role="alert"
            >
                <?= amanKonsultasi($isiPesan); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-body p-4">

                <form method="GET" class="row g-2 mb-4">

                    <div class="col-12 col-md-7">
                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari balita, NIK, petugas, hasil, atau tindak lanjut"
                            value="<?= amanKonsultasi($cari === "" ? "" : $cari); ?>"
                        >
                    </div>

                    <div class="col-6 col-md-2">
                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            <i class="bi bi-search me-1"></i>
                            Cari
                        </button>
                    </div>

                    <div class="col-6 col-md-2">
                        <a
                            href="data_konsultasi.php"
                            class="btn btn-outline-secondary w-100"
                        >
                            Reset
                        </a>
                    </div>

                </form>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <small class="text-muted">
                        Total data: <?= $totalData; ?> konsultasi
                    </small>

                    <?php if ($modeMonitoring): ?>
                        <span class="badge bg-secondary">
                            <i class="bi bi-eye me-1"></i>
                            Mode Monitoring
                        </span>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped table-hover align-middle">

                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">No.</th>
                                <th>Tanggal</th>
                                <th>Nama Balita</th>
                                <th>NIK</th>
                                <th>Petugas Gizi</th>
                                <th>Hasil Konsultasi</th>
                                <th>Tindak Lanjut</th>
                                <th class="text-center" style="min-width: 190px;">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if ($totalData > 0): ?>

                            <?php
                            $no = 1;

                            while ($data = mysqli_fetch_assoc($query)):
                                $idKonsultasi = (int) $data["id_konsultasi"];
                                $tanggalTampil = "-";

                                if (!empty($data["tanggal"])) {
                                    $waktuTanggal = strtotime($data["tanggal"]);

                                    if ($waktuTanggal !== false) {
                                        $tanggalTampil = date(
                                            "d-m-Y",
                                            $waktuTanggal
                                        );
                                    }
                                }
                            ?>

                                <tr>
                                    <td class="text-center">
                                        <?= $no++; ?>
                                    </td>

                                    <td>
                                        <?= amanKonsultasi($tanggalTampil); ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= amanKonsultasi($data["nama_balita"]); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= amanKonsultasi($data["nik_balita"]); ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($data["nama_petugas"])): ?>
                                            <?= amanKonsultasi($data["nama_petugas"]); ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                Belum ditentukan
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (trim($data["hasil_konsultasi"] ?? "") !== ""): ?>
                                            <?= nl2br(
                                                amanKonsultasi(
                                                    $data["hasil_konsultasi"]
                                                )
                                            ); ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                Belum diisi
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (trim($data["tindak_lanjut"] ?? "") !== ""): ?>
                                            <?= nl2br(
                                                amanKonsultasi(
                                                    $data["tindak_lanjut"]
                                                )
                                            ); ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                Belum ada
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="d-flex flex-wrap justify-content-center gap-1">

                                            <a
                                                href="detail_konsultasi.php?id=<?= $idKonsultasi; ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i class="bi bi-eye me-1"></i>
                                                Detail
                                            </a>

                                            <?php if ($bolehMengelola): ?>

                                                <a
                                                    href="edit_konsultasi.php?id=<?= $idKonsultasi; ?>"
                                                    class="btn btn-warning btn-sm"
                                                >
                                                    <i class="bi bi-chat-left-text me-1"></i>
                                                    Tanggapi / Edit
                                                </a>

                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td
                                    colspan="8"
                                    class="text-center text-muted py-4"
                                >
                                    <?php if ($cari !== ""): ?>
                                        Data konsultasi yang dicari tidak ditemukan.
                                    <?php elseif ($roleAktif === "orang_tua"): ?>
                                        Belum ada riwayat konsultasi untuk balita Anda.
                                    <?php else: ?>
                                        Data konsultasi belum tersedia.
                                    <?php endif; ?>
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

mysqli_stmt_close($stmt);

require_once "../includes/footer.php";

?>