<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_gizi",
    "petugas_kia",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Hasil Deteksi Stunting | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$cari = trim($_GET["cari"] ?? "");

$kataKunci = "%" . $cari . "%";

/*
|--------------------------------------------------------------------------
| Mengambil data hasil deteksi
|--------------------------------------------------------------------------
|
| Orang tua hanya melihat hasil deteksi anak miliknya.
| Role lainnya dapat melihat seluruh hasil deteksi.
|
*/

if ($roleAktif === "orang_tua") {

    if ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                hd.id_deteksi,
                hd.id_pengukuran,
                hd.status_gizi,
                hd.status_stunting,
                hd.tanggal_deteksi,
                pa.tanggal_pengukuran,
                pa.umur_bulan,
                pa.berat_badan,
                pa.tinggi_panjang_badan,
                pa.lingkar_kepala,
                pa.lila,
                b.id_balita,
                b.nama_balita,
                b.nik_balita,
                b.jenis_kelamin
             FROM hasil_deteksi hd
             INNER JOIN pengukuran_antropometri pa
                ON hd.id_pengukuran = pa.id_pengukuran
             INNER JOIN balita b
                ON pa.id_balita = b.id_balita
             WHERE b.id_user = ?
             AND (
                b.nama_balita LIKE ?
                OR b.nik_balita LIKE ?
                OR hd.status_gizi LIKE ?
                OR hd.status_stunting LIKE ?
             )
             ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian hasil deteksi: "
                . mysqli_error($conn)
            );
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
                hd.id_deteksi,
                hd.id_pengukuran,
                hd.status_gizi,
                hd.status_stunting,
                hd.tanggal_deteksi,
                pa.tanggal_pengukuran,
                pa.umur_bulan,
                pa.berat_badan,
                pa.tinggi_panjang_badan,
                pa.lingkar_kepala,
                pa.lila,
                b.id_balita,
                b.nama_balita,
                b.nik_balita,
                b.jenis_kelamin
             FROM hasil_deteksi hd
             INNER JOIN pengukuran_antropometri pa
                ON hd.id_pengukuran = pa.id_pengukuran
             INNER JOIN balita b
                ON pa.id_balita = b.id_balita
             WHERE b.id_user = ?
             ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil hasil deteksi: "
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
                hd.id_deteksi,
                hd.id_pengukuran,
                hd.status_gizi,
                hd.status_stunting,
                hd.tanggal_deteksi,
                pa.tanggal_pengukuran,
                pa.umur_bulan,
                pa.berat_badan,
                pa.tinggi_panjang_badan,
                pa.lingkar_kepala,
                pa.lila,
                b.id_balita,
                b.nama_balita,
                b.nik_balita,
                b.jenis_kelamin
             FROM hasil_deteksi hd
             INNER JOIN pengukuran_antropometri pa
                ON hd.id_pengukuran = pa.id_pengukuran
             INNER JOIN balita b
                ON pa.id_balita = b.id_balita
             WHERE
                b.nama_balita LIKE ?
                OR b.nik_balita LIKE ?
                OR hd.status_gizi LIKE ?
                OR hd.status_stunting LIKE ?
             ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian hasil deteksi: "
                . mysqli_error($conn)
            );
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
                hd.id_deteksi,
                hd.id_pengukuran,
                hd.status_gizi,
                hd.status_stunting,
                hd.tanggal_deteksi,
                pa.tanggal_pengukuran,
                pa.umur_bulan,
                pa.berat_badan,
                pa.tinggi_panjang_badan,
                pa.lingkar_kepala,
                pa.lila,
                b.id_balita,
                b.nama_balita,
                b.nik_balita,
                b.jenis_kelamin
             FROM hasil_deteksi hd
             INNER JOIN pengukuran_antropometri pa
                ON hd.id_pengukuran = pa.id_pengukuran
             INNER JOIN balita b
                ON pa.id_balita = b.id_balita
             ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil hasil deteksi: "
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
                    Hasil Deteksi Stunting
                </h2>

                <p class="text-muted mb-0">
                    Hasil status gizi dan status stunting berdasarkan
                    data pengukuran antropometri.
                </p>
            </div>

            <?php if ($roleAktif === "petugas_gizi"): ?>

                <a href="../skrining/hasil_skrining.php" class="btn btn-success">
                    + Analisis Pengukuran
                </a>

            <?php endif; ?>
        </div>

        <?php if (isset($_GET["pesan"])): ?>

            <?php if ($_GET["pesan"] === "analisis_berhasil"): ?>

                <div class="alert alert-success">
                    Hasil deteksi berhasil disimpan.
                </div>

            <?php elseif ($_GET["pesan"] === "edit_berhasil"): ?>

                <div class="alert alert-success">
                    Hasil deteksi berhasil diperbarui.
                </div>

            <?php elseif ($_GET["pesan"] === "tidak_ditemukan"): ?>

                <div class="alert alert-warning">
                    Data hasil deteksi tidak ditemukan.
                </div>

            <?php elseif ($_GET["pesan"] === "gagal"): ?>

                <div class="alert alert-danger">
                    Proses deteksi gagal dilakukan.
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
                            placeholder="Cari nama, NIK, status gizi, atau status stunting"
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
                            href="hasil_deteksi.php"
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
                                <th>Tanggal Deteksi</th>
                                <th>Nama Balita</th>
                                <th>NIK</th>
                                <th>JK</th>
                                <th>Umur</th>
                                <th>BB</th>
                                <th>TB/PB</th>
                                <th>Status Gizi</th>
                                <th>Status Stunting</th>
                                <th>Aksi</th>
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

                                    <?php
                                    $statusStunting = strtolower(
    trim($data["status_stunting"] ?? "")
);

$kelasStunting = "bg-secondary";

if (
    $statusStunting === "normal" ||
    $statusStunting === "normal/sehat"
) {

    $kelasStunting = "bg-success";

} elseif (
    $statusStunting === "risiko stunting"
) {

    $kelasStunting = "bg-warning text-dark";

} elseif (
    $statusStunting === "stunting"
) {

    $kelasStunting = "bg-danger";

} elseif (
    $statusStunting === "stunting berat"
) {

    $kelasStunting = "bg-danger";
}
                                    ?>

                                    <tr>

                                        <td><?= $no++ ?></td>

                                        <td>
                                            <?= !empty(
                                                $data["tanggal_deteksi"]
                                            )
                                                ? date(
                                                    "d-m-Y",
                                                    strtotime(
                                                        $data[
                                                            "tanggal_deteksi"
                                                        ]
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
                                                $data["jenis_kelamin"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= (int) (
                                                $data["umur_bulan"] ?? 0
                                            ) ?>
                                            bulan
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $data["berat_badan"] ?? "-",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                            kg
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $data[
                                                    "tinggi_panjang_badan"
                                                ] ?? "-",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                            cm
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $data["status_gizi"]
                                                    ?? "Belum tersedia",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <span
                                                class="badge <?= $kelasStunting ?>"
                                            >
                                                <?= htmlspecialchars(
                                                    $data["status_stunting"]
                                                        ?? "Belum tersedia",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <a
                                                href="detail_deteksi.php?id=<?= (int) $data["id_deteksi"] ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                Detail
                                            </a>
                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>
                                    <td
                                        colspan="11"
                                        class="text-center text-muted py-4"
                                    >
                                        Data hasil deteksi belum tersedia.
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