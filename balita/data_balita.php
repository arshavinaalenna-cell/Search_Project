<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Data Balita | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$cari = trim($_GET["cari"] ?? "");

/*
|--------------------------------------------------------------------------
| Mengambil data balita
|--------------------------------------------------------------------------
|
| Orang tua hanya melihat anak yang terhubung dengan akunnya.
| Role lain dapat melihat seluruh data balita.
|
*/

if ($roleAktif === "orang_tua") {
    if ($cari !== "") {
        $kataKunci = "%" . $cari . "%";

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.id_user,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.wilayah_posyandu
             FROM balita b
             WHERE b.id_user = ?
             AND (
                b.nik_balita LIKE ?
                OR b.nama_balita LIKE ?
                OR b.nama_ibu LIKE ?
                OR b.wilayah_posyandu LIKE ?
             )
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal menyiapkan pencarian data balita.");
        }

        mysqli_stmt_bind_param(
            $stmt,
            "issss",
            $idUserAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.id_user,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.wilayah_posyandu
             FROM balita b
             WHERE b.id_user = ?
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal mengambil data balita.");
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idUserAktif
        );
    }
} else {
    if ($cari !== "") {
        $kataKunci = "%" . $cari . "%";

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.id_user,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.wilayah_posyandu
             FROM balita b
             WHERE
                b.nik_balita LIKE ?
                OR b.nama_balita LIKE ?
                OR b.nama_ibu LIKE ?
                OR b.wilayah_posyandu LIKE ?
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal menyiapkan pencarian data balita.");
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssss",
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.id_user,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.wilayah_posyandu
             FROM balita b
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal mengambil data balita.");
        }
    }
}

mysqli_stmt_execute($stmt);

$query = mysqli_stmt_get_result($stmt);

require_once "../includes/header.php";
require_once "../includes/navbar.php";

/*
|--------------------------------------------------------------------------
| Teks interface berdasarkan role
|--------------------------------------------------------------------------
|
| Hanya mengubah tampilan halaman. Query, proses, dan hak akses tidak diubah.
|
*/

$judulDataBalita = "Data Balita";
$deskripsiDataBalita =
    "Daftar balita yang terdaftar dalam sistem.";

if ($roleAktif === "kader") {
    $judulDataBalita = "Kelola Data Balita";
    $deskripsiDataBalita =
        "Daftarkan balita baru dan kelola data profil dasar balita.";
} elseif ($roleAktif === "petugas_kia") {
    $judulDataBalita = "Data Balita";
    $deskripsiDataBalita =
        "Pilih data balita untuk meninjau identitas dan melengkapi riwayat medis.";
} elseif ($roleAktif === "petugas_gizi") {
    $judulDataBalita = "Verifikasi Data Balita";
    $deskripsiDataBalita =
        "Tinjau kelengkapan data balita sebelum proses deteksi dan konsultasi gizi.";
} elseif ($roleAktif === "orang_tua") {
    $judulDataBalita = "Data Anak";
    $deskripsiDataBalita =
        "Lihat data anak yang terhubung dengan akunmu.";
} elseif ($roleAktif === "kepala_puskesmas") {
    $judulDataBalita = "Data Balita";
    $deskripsiDataBalita =
        "Tinjau data balita sebagai informasi monitoring tingkat Puskesmas.";
} elseif ($roleAktif === "dinkes") {
    $judulDataBalita = "Data Balita Wilayah";
    $deskripsiDataBalita =
        "Tinjau data balita sebagai informasi monitoring tingkat wilayah.";
}

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    <i class="bi bi-person-heart me-2"></i>
                    <?= htmlspecialchars(
                        $judulDataBalita,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </h1>

                <p class="page-subtitle">
                    <?= htmlspecialchars(
                        $deskripsiDataBalita,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </p>

            </div>

            <div class="d-flex flex-wrap gap-2">

                <a
                    href="../dashboard/dashboard.php"
                    class="btn btn-secondary"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

                <?php if ($roleAktif === "kader"): ?>

                    <a
                        href="tambah_balita.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-person-plus"></i>
                        Tambah Balita
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <?php if (isset($_GET["pesan"])): ?>

            <?php if ($_GET["pesan"] === "tambah_berhasil"): ?>

                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Data balita berhasil ditambahkan.
                </div>

            <?php elseif ($_GET["pesan"] === "edit_berhasil"): ?>

                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Data balita berhasil diperbarui.
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_berhasil"): ?>

                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-1"></i>
                    Data balita berhasil dihapus.
                </div>

            <?php elseif ($_GET["pesan"] === "masih_digunakan"): ?>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Data balita tidak dapat dihapus karena masih
                    terhubung dengan data lain.
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_gagal"): ?>

                <div class="alert alert-danger">
                    <i class="bi bi-x-circle me-1"></i>
                    Data balita gagal dihapus.
                </div>

            <?php elseif ($_GET["pesan"] === "tidak_ditemukan"): ?>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Data balita tidak ditemukan.
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Daftar Balita
                    </h4>

                    <small class="text-muted">
                        Gunakan pencarian untuk menemukan data lebih cepat.
                    </small>

                </div>

                <span class="badge badge-primary">
                    <i class="bi bi-people"></i>
                    Data
                </span>

            </div>

            <div class="card-body">

                <form
                    method="GET"
                    class="row g-2 mb-4"
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
                                placeholder="Cari NIK, nama balita, nama ibu, atau wilayah"
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
                            href="data_balita.php"
                            class="btn btn-light w-100"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset
                        </a>

                    </div>

                </form>

                <div class="table-responsive">

                    <table
                        class="table table-hover align-middle"
                    >

                        <thead>

                            <tr>
                                <th class="text-center">
                                    No.
                                </th>

                                <th>
                                    NIK
                                </th>

                                <th>
                                    Nama Balita
                                </th>

                                <th class="text-center">
                                    JK
                                </th>

                                <th class="text-center">
                                    Tanggal Lahir
                                </th>

                                <th class="text-center">
                                    Umur
                                </th>

                                <th>
                                    Nama Ibu
                                </th>

                                <th>
                                    Wilayah
                                </th>

                                <th
                                    class="text-center"
                                    style="min-width: <?= $roleAktif === "kader"
                                        ? "210px"
                                        : "110px"; ?>;"
                                >
                                    Aksi
                                </th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (
                                mysqli_num_rows($query) > 0
                            ): ?>

                                <?php
                                $no = 1;

                                while (
                                    $d = mysqli_fetch_assoc($query)
                                ):
                                ?>

                                    <tr>

                                        <td class="text-center">
                                            <?= $no++; ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["nik_balita"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </td>

                                        <td>

                                            <div
                                                class="d-flex
                                                align-items-center gap-2"
                                            >

                                                <span
                                                    class="badge badge-primary"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-person-heart"
                                                    ></i>
                                                </span>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $d["nama_balita"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                </strong>

                                            </div>

                                        </td>

                                        <td class="text-center">
                                            <?= htmlspecialchars(
                                                $d["jenis_kelamin"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </td>

                                        <td class="text-center">
                                            <?= !empty(
                                                $d["tanggal_lahir"]
                                            )
                                                ? date(
                                                    "d-m-Y",
                                                    strtotime(
                                                        $d["tanggal_lahir"]
                                                    )
                                                )
                                                : "-"; ?>
                                        </td>

                                        <td class="text-center">
                                            <?= (int) $d["umur"]; ?>
                                            bulan
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["nama_ibu"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["wilayah_posyandu"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </td>

                                        <td>

                                            <div
                                                class="table-actions
                                                justify-content-center"
                                            >

                                                <a
                                                    href="detail_balita.php?id=<?= (int) $d["id_balita"]; ?>"
                                                    class="btn btn-info btn-sm"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                    Detail
                                                </a>

                                                <?php if (
                                                    $roleAktif === "kader"
                                                ): ?>

                                                    <a
                                                        href="edit_balita.php?id=<?= (int) $d["id_balita"]; ?>"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        <i class="bi bi-pencil-square"></i>
                                                        Edit
                                                    </a>

                                                    <form
                                                        action="hapus_balita.php"
                                                        method="POST"
                                                        class="d-inline
                                                        form-hapus-balita"
                                                        data-nama="<?= htmlspecialchars(
                                                            $d["nama_balita"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ); ?>"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_balita"
                                                            value="<?= (int) $d["id_balita"]; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger btn-sm"
                                                        >
                                                            <i class="bi bi-trash"></i>
                                                            Hapus
                                                        </button>
                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="9">

                                        <div class="empty-state">

                                            <div class="empty-state-icon">
                                                <i class="bi bi-person-x"></i>
                                            </div>

                                            <h3>
                                                Data balita tidak ditemukan
                                            </h3>

                                            <p>
                                                Coba gunakan kata kunci lain
                                                atau reset pencarian.
                                            </p>

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