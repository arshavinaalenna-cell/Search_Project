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
                    Data Balita
                </h2>

                <p class="text-muted mb-0">
                    Daftar balita yang terdaftar dalam sistem.
                </p>
            </div>
<?php if ($roleAktif === "kader"): ?>

    <div class="d-flex justify-content-end align-items-center gap-2">

        <button
            type="button"
            class="btn btn-success"
            onclick="history.back()"
        >
            <i class="bi bi-arrow-left"></i>
            Kembali
        </button>

        <a
            href="tambah_balita.php"
            class="btn btn-success"
        >
            + Tambah Balita
        </a>

    </div>

<?php endif; ?>
        </div>

        <?php if (isset($_GET["pesan"])): ?>

            <?php if ($_GET["pesan"] === "tambah_berhasil"): ?>

                <div class="alert alert-success">
                    Data balita berhasil ditambahkan.
                </div>

            <?php elseif ($_GET["pesan"] === "edit_berhasil"): ?>

                <div class="alert alert-success">
                    Data balita berhasil diperbarui.
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_berhasil"): ?>

                <div class="alert alert-success">
                    Data balita berhasil dihapus.
                </div>

            <?php elseif ($_GET["pesan"] === "masih_digunakan"): ?>

                <div class="alert alert-warning">
                    Data balita tidak dapat dihapus karena masih
                    terhubung dengan data lain.
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_gagal"): ?>

                <div class="alert alert-danger">
                    Data balita gagal dihapus.
                </div>

            <?php elseif ($_GET["pesan"] === "tidak_ditemukan"): ?>

                <div class="alert alert-warning">
                    Data balita tidak ditemukan.
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-body p-4">

                <form
                    method="GET"
                    class="row g-2 mb-4"
                >
                    <div class="col-12 col-md-7">

                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari NIK, nama balita, nama ibu, atau wilayah"
                            value="<?= htmlspecialchars(
                                $cari,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                    </div>

                    <div class="col-6 col-md-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Cari
                        </button>

                    </div>

                    <div class="col-6 col-md-2">

                        <a
                            href="data_balita.php"
                            class="btn btn-success"
                        >
                            Reset
                        </a>

                    </div>
                </form>

                <div class="table-responsive">

                    <table
                        class="table table-bordered table-striped
                        table-hover align-middle"
                    >

                        <thead class="table-dark">

                            <tr>
                                <th>No.</th>
                                <th>NIK</th>
                                <th>Nama Balita</th>
                                <th>JK</th>
                                <th>Tanggal Lahir</th>
                                <th>Umur</th>
                                <th>Nama Ibu</th>
                                <th>Wilayah</th>
                                <th style="min-width: 210px;">
                                    Aksi
                                </th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (mysqli_num_rows($query) > 0): ?>

                                <?php
                                $no = 1;

                                while (
                                    $d = mysqli_fetch_assoc($query)
                                ):
                                ?>

                                    <tr>

                                        <td><?= $no++ ?></td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["nik_balita"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["nama_balita"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["jenis_kelamin"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= !empty(
                                                $d["tanggal_lahir"]
                                            )
                                                ? date(
                                                    "d-m-Y",
                                                    strtotime(
                                                        $d["tanggal_lahir"]
                                                    )
                                                )
                                                : "-" ?>
                                        </td>

                                        <td>
                                            <?= (int) $d["umur"] ?>
                                            bulan
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["nama_ibu"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["wilayah_posyandu"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>

                                            <div
                                                class="d-flex flex-wrap gap-1"
                                            >

                                                <a
                                                    href="detail_balita.php?id=<?= (int) $d["id_balita"] ?>"
                                                    class="btn btn-info btn-sm"
                                                >
                                                    Detail
                                                </a>

                                                <?php if (
                                                    $roleAktif === "kader"
                                                ): ?>

                                                    <a
                                                        href="edit_balita.php?id=<?= (int) $d["id_balita"] ?>"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        Edit
                                                    </a>

                                                    <form
                                                        action="hapus_balita.php"
                                                        method="POST"
                                                        class="d-inline form-hapus-balita"
                                                        data-nama="<?= htmlspecialchars(
                                                            $d["nama_balita"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_balita"
                                                            value="<?= (int) $d["id_balita"] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger btn-sm"
                                                        >
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
                                    <td
                                        colspan="9"
                                        class="text-center text-muted py-4"
                                    >
                                        Data balita tidak ditemukan.
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