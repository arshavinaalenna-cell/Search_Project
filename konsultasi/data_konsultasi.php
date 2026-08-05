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
$cari = trim($_GET["cari"] ?? "");

$kataKunci = "%" . $cari . "%";

/*
|--------------------------------------------------------------------------
| Mengambil data konsultasi
|--------------------------------------------------------------------------
|
| Orang tua hanya melihat konsultasi anak miliknya.
| Petugas Gizi dan Kepala Puskesmas dapat melihat semua konsultasi.
|
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
                k.keluhan,
                k.hasil_konsultasi,
                k.tindak_lanjut,
                b.nama_balita,
                b.nik_balita,
                p.nama AS nama_petugas
             FROM konsultasi k
             INNER JOIN balita b
                ON k.id_balita = b.id_balita
             LEFT JOIN pengguna p
                ON k.id_petugas = p.id_user
             WHERE b.id_user = ?
             AND (
                b.nama_balita LIKE ?
                OR b.nik_balita LIKE ?
                OR p.nama LIKE ?
                OR k.keluhan LIKE ?
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
            "issssss",
            $idUserAktif,
            $kataKunci,
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
                k.keluhan,
                k.hasil_konsultasi,
                k.tindak_lanjut,
                b.nama_balita,
                b.nik_balita,
                p.nama AS nama_petugas
             FROM konsultasi k
             INNER JOIN balita b
                ON k.id_balita = b.id_balita
             LEFT JOIN pengguna p
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
                k.keluhan,
                k.hasil_konsultasi,
                k.tindak_lanjut,
                b.nama_balita,
                b.nik_balita,
                p.nama AS nama_petugas
             FROM konsultasi k
             INNER JOIN balita b
                ON k.id_balita = b.id_balita
             LEFT JOIN pengguna p
                ON k.id_petugas = p.id_user
             WHERE
                b.nama_balita LIKE ?
                OR b.nik_balita LIKE ?
                OR p.nama LIKE ?
                OR k.keluhan LIKE ?
                OR k.hasil_konsultasi LIKE ?
                OR k.tindak_lanjut LIKE ?
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
            "ssssss",
            $kataKunci,
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
                k.keluhan,
                k.hasil_konsultasi,
                k.tindak_lanjut,
                b.nama_balita,
                b.nik_balita,
                p.nama AS nama_petugas
             FROM konsultasi k
             INNER JOIN balita b
                ON k.id_balita = b.id_balita
             LEFT JOIN pengguna p
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
                    Data Konsultasi
                </h2>

                <p class="text-muted mb-0">
                    Riwayat konsultasi gizi dan tindak lanjut balita.
                </p>
            </div>

            <?php if (
                in_array(
                    $roleAktif,
                    [
                        "orang_tua",
                        "petugas_gizi"
                    ],
                    true
                )
            ): ?>

                <a
                    href="tambah_konsultasi.php"
                    class="btn btn-success"
                >
                    + Tambah Konsultasi
                </a>

            <?php endif; ?>
        </div>

        <?php if (isset($_GET["pesan"])): ?>

            <?php if ($_GET["pesan"] === "tambah_berhasil"): ?>

                <div class="alert alert-success">
                    Data konsultasi berhasil ditambahkan.
                </div>

            <?php elseif ($_GET["pesan"] === "edit_berhasil"): ?>

                <div class="alert alert-success">
                    Data konsultasi berhasil diperbarui.
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_berhasil"): ?>

                <div class="alert alert-success">
                    Data konsultasi berhasil dihapus.
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_gagal"): ?>

                <div class="alert alert-danger">
                    Data konsultasi gagal dihapus.
                </div>

            <?php elseif ($_GET["pesan"] === "tidak_ditemukan"): ?>

                <div class="alert alert-warning">
                    Data konsultasi tidak ditemukan.
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
                            placeholder="Cari balita, NIK, petugas, keluhan, hasil, atau tindak lanjut"
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
                            href="data_konsultasi.php"
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
                                <th>Tanggal</th>
                                <th>Nama Balita</th>
                                <th>NIK</th>
                                <th>Petugas Gizi</th>
                                <th>Keluhan</th>
                                <th>Hasil Konsultasi</th>
                                <th>Tindak Lanjut</th>

                                <th style="min-width: 180px;">
                                    Aksi
                                </th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (mysqli_num_rows($query) > 0): ?>

                                <?php
                                $no = 1;

                                while (
                                    $data = mysqli_fetch_assoc($query)
                                ):
                                ?>

                                    <tr>

                                        <td><?= $no++ ?></td>

                                        <td>
                                            <?= !empty($data["tanggal"])
                                                ? date(
                                                    "d-m-Y",
                                                    strtotime(
                                                        $data["tanggal"]
                                                    )
                                                )
                                                : "-" ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $data["nama_balita"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $data["nik_balita"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $data["nama_petugas"]
                                                    ?? "Belum ditentukan",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?php if (
                                                trim(
                                                    $data["keluhan"] ?? ""
                                                ) !== ""
                                            ): ?>

                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $data["keluhan"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    )
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    Tidak ada keluhan.
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if (
                                                trim(
                                                    $data[
                                                        "hasil_konsultasi"
                                                    ] ?? ""
                                                ) !== ""
                                            ): ?>

                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $data[
                                                            "hasil_konsultasi"
                                                        ],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    )
                                                ) ?>

                                            <?php else: ?>

                                                <span
                                                    class="badge bg-warning text-dark"
                                                >
                                                    Belum diisi
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if (
                                                trim(
                                                    $data[
                                                        "tindak_lanjut"
                                                    ] ?? ""
                                                ) !== ""
                                            ): ?>

                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $data[
                                                            "tindak_lanjut"
                                                        ],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    )
                                                ) ?>

                                            <?php else: ?>

                                                <span
                                                    class="badge bg-secondary"
                                                >
                                                    Belum ada
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>

                                            <div
                                                class="d-flex flex-wrap gap-1"
                                            >

                                                <a
                                                    href="detail_konsultasi.php?id=<?= (int) $data["id_konsultasi"] ?>"
                                                    class="btn btn-info btn-sm"
                                                >
                                                    Detail
                                                </a>

                                                <?php if (
                                                    $roleAktif ===
                                                        "petugas_gizi"
                                                    &&
                                                    (int) $data[
                                                        "id_petugas"
                                                    ] === $idUserAktif
                                                ): ?>

                                                    <a
                                                        href="edit_konsultasi.php?id=<?= (int) $data["id_konsultasi"] ?>"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        Edit
                                                    </a>

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
                                        Data konsultasi belum tersedia.
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