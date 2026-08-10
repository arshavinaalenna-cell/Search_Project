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

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Data Konsultasi
                    </h4>

                    <small class="text-muted">

                        <?php if ($modeMonitoring): ?>
                            Pantau riwayat konsultasi gizi dan tindak lanjut balita.
                        <?php elseif ($roleAktif === "orang_tua"): ?>
                            Lihat dan ajukan konsultasi gizi untuk balita Anda.
                        <?php else: ?>
                            Kelola konsultasi gizi dan tindak lanjut balita.
                        <?php endif; ?>

                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if ($bolehTambah): ?>

                        <a
                            href="tambah_konsultasi.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Tambah Konsultasi
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if ($isiPesan !== ""): ?>

                    <div
                        class="alert alert-<?= htmlspecialchars(
                            $jenisAlert,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?> alert-dismissible fade show"
                        role="alert"
                    >

                        <i
                            class="bi <?= $jenisAlert === "success"
                                ? "bi-check-circle"
                                : ($jenisAlert === "danger"
                                    ? "bi-x-circle"
                                    : "bi-exclamation-triangle"); ?> me-1"
                        ></i>

                        <?= htmlspecialchars(
                            $isiPesan,
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

                <form
                    method="GET"
                    class="row g-2 mb-3"
                >

                    <div class="col-12 col-lg-8">

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="text"
                                name="cari"
                                class="form-control"
                                placeholder="Cari balita, NIK, petugas, hasil, atau tindak lanjut"
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >

                        </div>

                    </div>

                    <div class="col-6 col-lg-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            <i class="bi bi-search"></i>
                            Cari
                        </button>

                    </div>

                    <div class="col-6 col-lg-2">

                        <a
                            href="data_konsultasi.php"
                            class="btn btn-outline-secondary w-100"
                        >
                            <i
                                class="bi
                                bi-arrow-counterclockwise"
                            ></i>
                            Reset
                        </a>

                    </div>

                </form>

                <div
                    class="d-flex flex-wrap
                    justify-content-between align-items-center
                    gap-2 mb-3"
                >

                    <span class="text-muted small">
                        Total data:
                        <strong>
                            <?= $totalData; ?>
                        </strong>
                        konsultasi
                    </span>

                    <?php if ($modeMonitoring): ?>

                        <span class="badge bg-secondary">
                            <i class="bi bi-eye"></i>
                            Mode Monitoring
                        </span>

                    <?php elseif (
                        $roleAktif === "orang_tua"
                    ): ?>

                        <span class="badge badge-info">
                            <i class="bi bi-person-heart"></i>
                            Konsultasi Saya
                        </span>

                    <?php else: ?>

                        <span class="badge badge-info">
                            <i class="bi bi-chat-heart"></i>
                            Konsultasi Gizi
                        </span>

                    <?php endif; ?>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th class="text-center">
                                    No
                                </th>

                                <th>
                                    Tanggal
                                </th>

                                <th>
                                    Balita
                                </th>

                                <th>
                                    Petugas Gizi
                                </th>

                                <th>
                                    Hasil Konsultasi
                                </th>

                                <th>
                                    Tindak Lanjut
                                </th>

                                <th class="text-center">
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($totalData > 0): ?>

                            <?php

                            $nomor = 1;

                            while (
                                $data = mysqli_fetch_assoc($query)
                            ):

                                $idKonsultasi =
                                    (int) $data["id_konsultasi"];

                                $tanggalTampil = "-";

                                if (!empty($data["tanggal"])) {

                                    $waktuTanggal =
                                        strtotime($data["tanggal"]);

                                    if ($waktuTanggal !== false) {

                                        $tanggalTampil = date(
                                            "d-m-Y",
                                            $waktuTanggal
                                        );
                                    }
                                }

                                $belumDitangani =
                                    empty($data["id_petugas"]);

                            ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $nomor++; ?>
                                    </td>

                                    <td>

                                        <span class="text-nowrap">
                                            <i
                                                class="bi
                                                bi-calendar3 me-1"
                                            ></i>

                                            <?= amanKonsultasi(
                                                $tanggalTampil
                                            ); ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div
                                            class="d-flex
                                            align-items-center gap-2"
                                        >

                                            <span
                                                class="badge
                                                badge-primary"
                                            >
                                                <i
                                                    class="bi
                                                    bi-person-heart"
                                                ></i>
                                            </span>

                                            <div>

                                                <strong
                                                    class="d-block"
                                                >
                                                    <?= amanKonsultasi(
                                                        $data[
                                                            "nama_balita"
                                                        ]
                                                    ); ?>
                                                </strong>

                                                <small
                                                    class="text-muted"
                                                >
                                                    NIK:
                                                    <?= amanKonsultasi(
                                                        $data[
                                                            "nik_balita"
                                                        ]
                                                    ); ?>
                                                </small>

                                            </div>

                                        </div>

                                    </td>

                                    <td>

                                        <?php if (
                                            !$belumDitangani
                                        ): ?>

                                            <?= amanKonsultasi(
                                                $data[
                                                    "nama_petugas"
                                                ]
                                            ); ?>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                bg-warning text-dark"
                                            >
                                                <i
                                                    class="bi
                                                    bi-hourglass-split
                                                    me-1"
                                                ></i>
                                                Menunggu Petugas
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td
                                        style="
                                            min-width: 220px;
                                            white-space: normal;
                                        "
                                    >

                                        <?php if (
                                            trim(
                                                $data[
                                                    "hasil_konsultasi"
                                                ] ?? ""
                                            ) !== ""
                                        ): ?>

                                            <?= nl2br(
                                                amanKonsultasi(
                                                    $data[
                                                        "hasil_konsultasi"
                                                    ]
                                                )
                                            ); ?>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                bg-warning text-dark"
                                            >
                                                Belum diisi
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td
                                        style="
                                            min-width: 220px;
                                            white-space: normal;
                                        "
                                    >

                                        <?php if (
                                            trim(
                                                $data[
                                                    "tindak_lanjut"
                                                ] ?? ""
                                            ) !== ""
                                        ): ?>

                                            <?= nl2br(
                                                amanKonsultasi(
                                                    $data[
                                                        "tindak_lanjut"
                                                    ]
                                                )
                                            ); ?>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                bg-secondary"
                                            >
                                                Belum ada
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

                                            <a
                                                href="detail_konsultasi.php?id=<?= $idKonsultasi; ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i
                                                    class="bi
                                                    bi-eye"
                                                ></i>
                                                Detail
                                            </a>

                                            <?php if (
                                                $bolehMengelola
                                            ): ?>

                                                <a
                                                    href="edit_konsultasi.php?id=<?= $idKonsultasi; ?>"
                                                    class="btn btn-warning btn-sm"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-chat-left-text"
                                                    ></i>

                                                    <?= $belumDitangani
                                                        ? "Tanggapi"
                                                        : "Edit"; ?>
                                                </a>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="7">

                                    <div class="empty-state">

                                        <div
                                            class="empty-state-icon"
                                        >
                                            <i
                                                class="bi
                                                bi-chat-heart"
                                            ></i>
                                        </div>

                                        <h3>
                                            <?php if (
                                                $cari !== ""
                                            ): ?>
                                                Data konsultasi tidak ditemukan
                                            <?php else: ?>
                                                Belum ada data konsultasi
                                            <?php endif; ?>
                                        </h3>

                                        <p>
                                            <?php if (
                                                $cari !== ""
                                            ): ?>
                                                Coba gunakan kata kunci lain atau reset pencarian.
                                            <?php elseif (
                                                $roleAktif ===
                                                "orang_tua"
                                            ): ?>
                                                Ajukan konsultasi untuk mendapatkan arahan dari Petugas Gizi.
                                            <?php elseif (
                                                $modeMonitoring
                                            ): ?>
                                                Data konsultasi belum tersedia untuk dimonitoring.
                                            <?php else: ?>
                                                Tambahkan atau tanggapi konsultasi gizi balita.
                                            <?php endif; ?>
                                        </p>

                                        <?php if (
                                            $cari !== ""
                                        ): ?>

                                            <a
                                                href="data_konsultasi.php"
                                                class="btn btn-outline-secondary mt-3"
                                            >
                                                <i
                                                    class="bi
                                                    bi-arrow-counterclockwise"
                                                ></i>
                                                Reset Pencarian
                                            </a>

                                        <?php elseif (
                                            $bolehTambah
                                        ): ?>

                                            <a
                                                href="tambah_konsultasi.php"
                                                class="btn btn-primary mt-3"
                                            >
                                                <i
                                                    class="bi
                                                    bi-plus-circle"
                                                ></i>
                                                Tambah Konsultasi
                                            </a>

                                        <?php endif; ?>

                                    </div>

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