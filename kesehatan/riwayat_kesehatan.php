<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Riwayat Kesehatan | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$cari = trim($_GET["cari"] ?? "");

$kataKunci = "%" . $cari . "%";

/*
|--------------------------------------------------------------------------
| Mengambil data riwayat kesehatan
|--------------------------------------------------------------------------
|
| Orang tua hanya melihat riwayat kesehatan anak miliknya.
| Role lainnya dapat melihat seluruh data.
|
*/

if ($roleAktif === "orang_tua") {
    if ($cari !== "") {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                rk.id_riwayat,
                rk.id_balita,
                rk.riwayat_penyakit,
                rk.riwayat_imunisasi,
                rk.riwayat_perawatan,
                b.nama_balita,
                b.nik_balita
             FROM riwayat_kesehatan rk
             INNER JOIN balita b
                ON rk.id_balita = b.id_balita
             WHERE b.id_user = ?
             AND (
                b.nama_balita LIKE ?
                OR b.nik_balita LIKE ?
                OR rk.riwayat_penyakit LIKE ?
                OR rk.riwayat_imunisasi LIKE ?
                OR rk.riwayat_perawatan LIKE ?
             )
             ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die("Gagal menyiapkan pencarian riwayat kesehatan.");
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
                rk.id_riwayat,
                rk.id_balita,
                rk.riwayat_penyakit,
                rk.riwayat_imunisasi,
                rk.riwayat_perawatan,
                b.nama_balita,
                b.nik_balita
             FROM riwayat_kesehatan rk
             INNER JOIN balita b
                ON rk.id_balita = b.id_balita
             WHERE b.id_user = ?
             ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die("Gagal mengambil riwayat kesehatan.");
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
                rk.id_riwayat,
                rk.id_balita,
                rk.riwayat_penyakit,
                rk.riwayat_imunisasi,
                rk.riwayat_perawatan,
                b.nama_balita,
                b.nik_balita
             FROM riwayat_kesehatan rk
             INNER JOIN balita b
                ON rk.id_balita = b.id_balita
             WHERE
                b.nama_balita LIKE ?
                OR b.nik_balita LIKE ?
                OR rk.riwayat_penyakit LIKE ?
                OR rk.riwayat_imunisasi LIKE ?
                OR rk.riwayat_perawatan LIKE ?
             ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die("Gagal menyiapkan pencarian riwayat kesehatan.");
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
                rk.id_riwayat,
                rk.id_balita,
                rk.riwayat_penyakit,
                rk.riwayat_imunisasi,
                rk.riwayat_perawatan,
                b.nama_balita,
                b.nik_balita
             FROM riwayat_kesehatan rk
             INNER JOIN balita b
                ON rk.id_balita = b.id_balita
             ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die("Gagal mengambil riwayat kesehatan.");
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
                    Riwayat Kesehatan Anak
                </h2>

                <p class="text-muted mb-0">
                    Data riwayat penyakit, imunisasi,
                    dan perawatan balita.
                </p>
            </div>

            <?php if ($roleAktif === "petugas_kia"): ?>

                <a
                    href="tambah_kesehatan.php"
                    class="btn btn-success"
                >
                    + Tambah Data
                </a>

            <?php endif; ?>
        </div>

        <?php if (isset($_GET["pesan"])): ?>

            <?php if ($_GET["pesan"] === "tambah_berhasil"): ?>

                <div class="alert alert-success">
                    Riwayat kesehatan berhasil ditambahkan.
                </div>

            <?php elseif ($_GET["pesan"] === "edit_berhasil"): ?>

                <div class="alert alert-success">
                    Riwayat kesehatan berhasil diperbarui.
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_berhasil"): ?>

                <div class="alert alert-success">
                    Riwayat kesehatan berhasil dihapus.
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_gagal"): ?>

                <div class="alert alert-danger">
                    Riwayat kesehatan gagal dihapus.
                </div>

            <?php elseif ($_GET["pesan"] === "tidak_ditemukan"): ?>

                <div class="alert alert-warning">
                    Riwayat kesehatan tidak ditemukan.
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
                            placeholder="Cari nama balita, NIK, penyakit, imunisasi, atau perawatan"
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
                            href="riwayat_kesehatan.php"
                            class="btn btn-outline-secondary w-100"
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
                                <th>Nama Balita</th>
                                <th>NIK Balita</th>
                                <th>Riwayat Penyakit</th>
                                <th>Riwayat Imunisasi</th>
                                <th>Riwayat Perawatan</th>
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
                                                $d["nama_balita"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["nik_balita"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $d["riwayat_penyakit"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $d["riwayat_imunisasi"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $d["riwayat_perawatan"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                            ) ?>
                                        </td>

                                        <td>

                                            <div class="d-flex flex-wrap gap-1">

                                                <a
                                                    href="detail_kesehatan.php?id=<?= (int) $d["id_riwayat"] ?>"
                                                    class="btn btn-info btn-sm"
                                                >
                                                    Detail
                                                </a>

                                                <?php if (
                                                    $roleAktif === "petugas_kia"
                                                ): ?>

                                                    <a
                                                        href="edit_kesehatan.php?id=<?= (int) $d["id_riwayat"] ?>"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        Edit
                                                    </a>

                                                    <form
                                                        action="hapus_kesehatan.php"
                                                        method="POST"
                                                        class="d-inline form-hapus-kesehatan"
                                                        data-nama="<?= htmlspecialchars(
                                                            $d["nama_balita"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_riwayat"
                                                            value="<?= (int) $d["id_riwayat"] ?>"
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
                                        colspan="7"
                                        class="text-center text-muted py-4"
                                    >
                                        Data riwayat kesehatan belum tersedia.
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