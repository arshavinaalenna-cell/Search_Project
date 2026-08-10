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
| Filter Puskesmas khusus Kader
|--------------------------------------------------------------------------
*/

$filterPuskesmas = $roleAktif === "kader"
    ? max(0, (int) ($_GET["puskesmas"] ?? 0))
    : 0;

$daftarPuskesmas = [];

if ($roleAktif === "kader") {
    $queryPuskesmas = mysqli_query(
        $conn,
        "SELECT id_puskesmas, nama_puskesmas
         FROM puskesmas
         ORDER BY nama_puskesmas ASC"
    );

    if ($queryPuskesmas) {
        while ($puskesmas = mysqli_fetch_assoc($queryPuskesmas)) {
            $daftarPuskesmas[] = $puskesmas;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Mengambil data balita
|--------------------------------------------------------------------------
|
| Orang tua hanya melihat anak yang terhubung dengan akunnya.
| Kader dapat memfilter data berdasarkan Puskesmas.
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
                b.id_puskesmas,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.nama_posyandu,
                p.nama_puskesmas
             FROM balita b
             LEFT JOIN puskesmas p
                ON b.id_puskesmas = p.id_puskesmas
             WHERE b.id_user = ?
             AND (
                b.nik_balita LIKE ?
                OR b.nama_balita LIKE ?
                OR b.nama_ibu LIKE ?
                OR b.nama_posyandu LIKE ?
                OR p.nama_puskesmas LIKE ?
             )
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal menyiapkan pencarian data balita.");
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
                b.id_balita,
                b.id_user,
                b.id_puskesmas,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.nama_posyandu,
                p.nama_puskesmas
             FROM balita b
             LEFT JOIN puskesmas p
                ON b.id_puskesmas = p.id_puskesmas
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
    if ($cari !== "" && $filterPuskesmas > 0) {
        $kataKunci = "%" . $cari . "%";

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.id_user,
                b.id_puskesmas,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.nama_posyandu,
                p.nama_puskesmas
             FROM balita b
             LEFT JOIN puskesmas p
                ON b.id_puskesmas = p.id_puskesmas
             WHERE b.id_puskesmas = ?
             AND (
                b.nik_balita LIKE ?
                OR b.nama_balita LIKE ?
                OR b.nama_ibu LIKE ?
                OR b.nama_posyandu LIKE ?
                OR p.nama_puskesmas LIKE ?
             )
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal menyiapkan filter data balita.");
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssss",
            $filterPuskesmas,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );
    } elseif ($cari !== "") {
        $kataKunci = "%" . $cari . "%";

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.id_user,
                b.id_puskesmas,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.nama_posyandu,
                p.nama_puskesmas
             FROM balita b
             LEFT JOIN puskesmas p
                ON b.id_puskesmas = p.id_puskesmas
             WHERE
                b.nik_balita LIKE ?
                OR b.nama_balita LIKE ?
                OR b.nama_ibu LIKE ?
                OR b.nama_posyandu LIKE ?
                OR p.nama_puskesmas LIKE ?
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal menyiapkan pencarian data balita.");
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
    } elseif ($filterPuskesmas > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.id_user,
                b.id_puskesmas,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.nama_posyandu,
                p.nama_puskesmas
             FROM balita b
             LEFT JOIN puskesmas p
                ON b.id_puskesmas = p.id_puskesmas
             WHERE b.id_puskesmas = ?
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal menyiapkan filter data balita.");
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $filterPuskesmas
        );
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                b.id_balita,
                b.id_user,
                b.id_puskesmas,
                b.nik_balita,
                b.nama_balita,
                b.jenis_kelamin,
                b.tanggal_lahir,
                b.umur,
                b.nama_ibu,
                b.alamat,
                b.nama_posyandu,
                p.nama_puskesmas
             FROM balita b
             LEFT JOIN puskesmas p
                ON b.id_puskesmas = p.id_puskesmas
             ORDER BY b.id_balita DESC"
        );

        if (!$stmt) {
            die("Gagal mengambil data balita.");
        }
    }
}

if (!mysqli_stmt_execute($stmt)) {
    die("Gagal mengambil data balita: " . mysqli_stmt_error($stmt));
}

$query = mysqli_stmt_get_result($stmt);

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

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

                <div class="d-flex flex-wrap gap-2">

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if ($roleAktif === "kader"): ?>

                        <a
                            href="tambah_balita.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-person-plus"></i>
                            Tambah Balita
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <form
                    method="GET"
                    class="row g-2 mb-3"
                >

                    <?php if ($filterPuskesmas > 0): ?>
                        <input
                            type="hidden"
                            name="puskesmas"
                            value="<?= $filterPuskesmas; ?>"
                        >
                    <?php endif; ?>

                    <div class="col-12 col-lg-8">

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="text"
                                name="cari"
                                class="form-control"
                                placeholder="Cari NIK, nama balita, nama ibu, Posyandu, atau Puskesmas"
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

                <?php if ($roleAktif === "kader"): ?>

                    <form
                        method="GET"
                        class="row g-2 mb-4 align-items-end"
                    >

                        <?php if ($cari !== ""): ?>
                            <input
                                type="hidden"
                                name="cari"
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >
                        <?php endif; ?>

                        <div class="col-12 col-lg-8">

                            <label
                                for="filter_puskesmas"
                                class="form-label small text-muted mb-1"
                            >
                                Filter berdasarkan Puskesmas
                            </label>

                            <select
                                id="filter_puskesmas"
                                name="puskesmas"
                                class="form-select"
                            >
                                <option value="0">
                                    Semua Puskesmas
                                </option>

                                <?php foreach ($daftarPuskesmas as $puskesmas): ?>
                                    <option
                                        value="<?= (int) $puskesmas["id_puskesmas"]; ?>"
                                        <?= $filterPuskesmas === (int) $puskesmas["id_puskesmas"]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $puskesmas["nama_puskesmas"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        </div>

                        <div class="col-6 col-lg-2">
                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                <i class="bi bi-funnel"></i>
                                Filter
                            </button>
                        </div>

                        <div class="col-6 col-lg-2">
                            <a
                                href="data_balita.php<?= $cari !== ""
                                    ? "?cari=" . urlencode($cari)
                                    : ""; ?>"
                                class="btn btn-light w-100"
                            >
                                <i class="bi bi-x-circle"></i>
                                Hapus Filter
                            </a>
                        </div>

                    </form>

                <?php endif; ?>

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
                                    Posyandu
                                </th>

                                <th>
                                    Puskesmas Pembina
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
                                            <?= trim(
                                                (string) (
                                                    $d["nama_posyandu"]
                                                    ?? ""
                                                )
                                            ) !== ""
                                                ? htmlspecialchars(
                                                    $d["nama_posyandu"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                                : "-"; ?>
                                        </td>

                                        <td>
                                            <?= trim(
                                                (string) (
                                                    $d["nama_puskesmas"]
                                                    ?? ""
                                                )
                                            ) !== ""
                                                ? htmlspecialchars(
                                                    $d["nama_puskesmas"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                                : "-"; ?>
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

                                    <td colspan="10">

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