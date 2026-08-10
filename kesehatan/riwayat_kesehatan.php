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

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Riwayat Kesehatan Anak
                    </h4>

                    <small class="text-muted">
                        Data riwayat penyakit, imunisasi,
                        dan perawatan balita.
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

                    <?php if (
                        $roleAktif === "petugas_kia"
                    ): ?>

                        <a
                            href="tambah_kesehatan.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Tambah Data
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if (isset($_GET["pesan"])): ?>

                    <?php if (
                        $_GET["pesan"] === "tambah_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Riwayat kesehatan berhasil ditambahkan.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] === "edit_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Riwayat kesehatan berhasil diperbarui.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] === "hapus_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Riwayat kesehatan berhasil dihapus.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] === "hapus_gagal"
                    ): ?>

                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle me-1"></i>
                            Riwayat kesehatan gagal dihapus.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] === "tidak_ditemukan"
                    ): ?>

                        <div class="alert alert-warning">
                            <i
                                class="bi
                                bi-exclamation-triangle me-1"
                            ></i>
                            Riwayat kesehatan tidak ditemukan.
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

                <form
                    method="GET"
                    class="row g-2 mb-3"
                >

                    <div class="col-12 col-md-8">

                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari nama balita, NIK, penyakit, imunisasi, atau perawatan"
                            value="<?= htmlspecialchars(
                                $cari,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                        >

                    </div>

                    <div class="col-6 col-md-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            <i class="bi bi-search"></i>
                            Cari
                        </button>

                    </div>

                    <div class="col-6 col-md-2">

                        <a
                            href="riwayat_kesehatan.php"
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
                            <?= mysqli_num_rows($query); ?>
                        </strong>
                        riwayat kesehatan
                    </span>

                    <?php if (
                        $roleAktif !== "petugas_kia"
                    ): ?>

                        <span class="badge badge-info">
                            <i class="bi bi-eye"></i>
                            Mode lihat
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
                                    Nama Balita
                                </th>

                                <th>
                                    NIK Balita
                                </th>

                                <th>
                                    Riwayat Penyakit
                                </th>

                                <th>
                                    Riwayat Imunisasi
                                </th>

                                <th>
                                    Riwayat Perawatan
                                </th>

                                <th
                                    class="text-center"
                                    style="min-width: 210px;"
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
                                    $d = mysqli_fetch_assoc(
                                        $query
                                    )
                                ):
                                ?>

                                    <tr>

                                        <td class="text-center">
                                            <?= $no++; ?>
                                        </td>

                                        <td>

                                            <div
                                                class="d-flex
                                                align-items-center
                                                gap-2"
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

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $d[
                                                            "nama_balita"
                                                        ],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                </strong>

                                            </div>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d[
                                                    "nik_balita"
                                                ],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $d[
                                                        "riwayat_penyakit"
                                                    ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $d[
                                                        "riwayat_imunisasi"
                                                    ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $d[
                                                        "riwayat_perawatan"
                                                    ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                            ); ?>
                                        </td>

                                        <td>

                                            <div
                                                class="table-actions
                                                justify-content-center"
                                            >

                                                <a
                                                    href="detail_kesehatan.php?id=<?= (int) $d["id_riwayat"]; ?>"
                                                    class="btn btn-info btn-sm"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-eye"
                                                    ></i>
                                                    Detail
                                                </a>

                                                <?php if (
                                                    $roleAktif ===
                                                    "petugas_kia"
                                                ): ?>

                                                    <a
                                                        href="edit_kesehatan.php?id=<?= (int) $d["id_riwayat"]; ?>"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        <i
                                                            class="bi
                                                            bi-pencil-square"
                                                        ></i>
                                                        Edit
                                                    </a>

                                                    <form
                                                        action="hapus_kesehatan.php"
                                                        method="POST"
                                                        class="d-inline
                                                        form-hapus-kesehatan"
                                                        data-nama="<?= htmlspecialchars(
                                                            $d[
                                                                "nama_balita"
                                                            ],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ); ?>"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_riwayat"
                                                            value="<?= (int) $d["id_riwayat"]; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger btn-sm"
                                                        >
                                                            <i
                                                                class="bi
                                                                bi-trash3"
                                                            ></i>
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

                                    <td colspan="7">

                                        <div class="empty-state">

                                            <div
                                                class="empty-state-icon"
                                            >
                                                <i
                                                    class="bi
                                                    bi-heart-pulse"
                                                ></i>
                                            </div>

                                            <h3>
                                                Belum ada riwayat kesehatan
                                            </h3>

                                            <p>
                                                Data riwayat kesehatan
                                                balita belum tersedia.
                                            </p>

                                            <?php if (
                                                $roleAktif ===
                                                "petugas_kia"
                                            ): ?>

                                                <a
                                                    href="tambah_kesehatan.php"
                                                    class="btn btn-primary mt-3"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-plus-circle"
                                                    ></i>
                                                    Tambah Data
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