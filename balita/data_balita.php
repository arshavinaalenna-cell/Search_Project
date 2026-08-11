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
| Wilayah Puskesmas berdasarkan akun
|--------------------------------------------------------------------------
|
| Kader, Petugas KIA, Petugas Gizi, dan Kepala Puskesmas hanya boleh
| melihat balita dari Puskesmas yang terhubung ke akun mereka.
|
| Dinkes dapat melihat seluruh Puskesmas.
| Orang Tua hanya melihat balita miliknya sendiri.
|
*/

$roleTerikatPuskesmas = [
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "kepala_puskesmas"
];

$idPuskesmasAkun = 0;
$namaPuskesmasAkun = "";
$puskesmasBelumTerhubung = false;

if (in_array($roleAktif, $roleTerikatPuskesmas, true)) {

    $stmtPuskesmasAkun = mysqli_prepare(
        $conn,
        "SELECT
            u.id_puskesmas,
            p.nama_puskesmas
         FROM pengguna AS u
         LEFT JOIN puskesmas AS p
            ON u.id_puskesmas = p.id_puskesmas
         WHERE u.id_user = ?
         LIMIT 1"
    );

    if (!$stmtPuskesmasAkun) {
        die(
            "Gagal memeriksa Puskesmas akun: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtPuskesmasAkun,
        "i",
        $idUserAktif
    );

    mysqli_stmt_execute(
        $stmtPuskesmasAkun
    );

    $hasilPuskesmasAkun =
        mysqli_stmt_get_result(
            $stmtPuskesmasAkun
        );

    $dataPuskesmasAkun =
        mysqli_fetch_assoc(
            $hasilPuskesmasAkun
        );

    mysqli_stmt_close(
        $stmtPuskesmasAkun
    );

    if (
        !$dataPuskesmasAkun
        || empty($dataPuskesmasAkun["id_puskesmas"])
    ) {
        $puskesmasBelumTerhubung = true;
    } else {
        $idPuskesmasAkun =
            (int) $dataPuskesmasAkun["id_puskesmas"];

        $namaPuskesmasAkun =
            trim(
                (string) (
                    $dataPuskesmasAkun["nama_puskesmas"]
                    ?? ""
                )
            );
    }
}

/*
|--------------------------------------------------------------------------
| Query dasar
|--------------------------------------------------------------------------
*/

$sql = "SELECT
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
            ot.nama_ibu AS profil_nama_ibu,
            ot.nik_ibu,
            ot.pendidikan_ibu,
            ot.pekerjaan_ibu,
            b.nama_posyandu,
            p.nama_puskesmas
        FROM balita AS b
        LEFT JOIN puskesmas AS p
            ON b.id_puskesmas = p.id_puskesmas
        LEFT JOIN orang_tua AS ot
            ON b.id_user = ot.id_user
        WHERE 1 = 1";

$tipe = "";
$parameter = [];

/* Orang Tua: hanya anak milik akun sendiri */
if ($roleAktif === "orang_tua") {

    $sql .= " AND b.id_user = ? ";
    $tipe .= "i";
    $parameter[] = $idUserAktif;

/* Role wilayah: hanya Puskesmas akun sendiri */
} elseif (
    in_array(
        $roleAktif,
        $roleTerikatPuskesmas,
        true
    )
) {

    if ($puskesmasBelumTerhubung) {
        /* Sengaja tidak menampilkan data jika akun belum punya wilayah */
        $sql .= " AND 1 = 0 ";
    } else {
        $sql .= " AND b.id_puskesmas = ? ";
        $tipe .= "i";
        $parameter[] = $idPuskesmasAkun;
    }

/* Dinkes tidak dibatasi Puskesmas */
} elseif ($roleAktif !== "dinkes") {
    $sql .= " AND 1 = 0 ";
}

/* Pencarian tetap bekerja di dalam cakupan akses role */
if ($cari !== "") {

    $kataKunci = "%" . $cari . "%";

    $sql .= "
        AND (
            b.nik_balita LIKE ?
            OR b.nama_balita LIKE ?
            OR b.nama_ibu LIKE ?
            OR ot.nama_ibu LIKE ?
            OR b.nama_posyandu LIKE ?
            OR p.nama_puskesmas LIKE ?
        )
    ";

    $tipe .= "ssssss";

    for ($i = 0; $i < 6; $i++) {
        $parameter[] = $kataKunci;
    }
}

$sql .= " ORDER BY b.id_balita DESC ";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {
    die(
        "Gagal menyiapkan data balita: "
        . mysqli_error($conn)
    );
}

if ($parameter !== []) {
    mysqli_stmt_bind_param(
        $stmt,
        $tipe,
        ...$parameter
    );
}

if (!mysqli_stmt_execute($stmt)) {
    die("Gagal mengambil data balita: " . mysqli_stmt_error($stmt));
}

$query = mysqli_stmt_get_result($stmt);

/*
|--------------------------------------------------------------------------
| Menghitung balita yang belum terhubung Profil Ibu
|--------------------------------------------------------------------------
*/

$jumlahBelumTerhubung = 0;

if (
    $roleAktif === "kader"
    && !$puskesmasBelumTerhubung
) {

    $stmtBelumTerhubung = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM balita AS b
         LEFT JOIN orang_tua AS ot
            ON b.id_user = ot.id_user
         WHERE b.id_puskesmas = ?
           AND (
                b.id_user IS NULL
                OR ot.id_orang_tua IS NULL
           )"
    );

    if ($stmtBelumTerhubung) {

        mysqli_stmt_bind_param(
            $stmtBelumTerhubung,
            "i",
            $idPuskesmasAkun
        );

        mysqli_stmt_execute(
            $stmtBelumTerhubung
        );

        $hasilBelumTerhubung =
            mysqli_stmt_get_result(
                $stmtBelumTerhubung
            );

        $dataBelumTerhubung =
            mysqli_fetch_assoc(
                $hasilBelumTerhubung
            );

        $jumlahBelumTerhubung =
            (int) (
                $dataBelumTerhubung["total"]
                ?? 0
            );

        mysqli_stmt_close(
            $stmtBelumTerhubung
        );
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php if (
            in_array(
                $roleAktif,
                $roleTerikatPuskesmas,
                true
            )
            && $puskesmasBelumTerhubung
        ): ?>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Akun ini belum terhubung dengan Puskesmas.
                Hubungkan akun melalui menu Data Pengguna terlebih dahulu.
            </div>

        <?php elseif (
            in_array(
                $roleAktif,
                $roleTerikatPuskesmas,
                true
            )
        ): ?>

            <div class="alert alert-info">
                <i class="bi bi-hospital me-1"></i>
                Wilayah data: <strong><?= htmlspecialchars(
                    $namaPuskesmasAkun,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?></strong>.
                Data dari Puskesmas lain tidak ditampilkan.
            </div>

        <?php endif; ?>

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

        <?php if (
            $roleAktif === "kader"
            && $jumlahBelumTerhubung > 0
        ): ?>

            <div class="alert alert-warning">

                <i class="bi bi-exclamation-triangle me-1"></i>

                Ada
                <strong>
                    <?= $jumlahBelumTerhubung; ?>
                </strong>
                data balita lama yang belum terhubung dengan
                Profil Ibu. Data tetap dapat dilihat, tetapi
                sebaiknya hubungkan melalui menu Edit Balita.

            </div>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Daftar Balita
                    </h4>

                    <small class="text-muted">
                        Data balita terhubung ke Profil Ibu melalui akun Orang Tua.
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

                    <div class="col-12 col-lg-8">

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="text"
                                name="cari"
                                class="form-control"
                                placeholder="Cari NIK, nama balita, Profil Ibu, Posyandu, atau Puskesmas"
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

                                    $namaIbuProfil =
                                        trim(
                                            (string) (
                                                $d["profil_nama_ibu"]
                                                ?? ""
                                            )
                                        );

                                    $namaIbuLama =
                                        trim(
                                            (string) (
                                                $d["nama_ibu"]
                                                ?? ""
                                            )
                                        );

                                    $namaIbuTampil =
                                        $namaIbuProfil !== ""
                                            ? $namaIbuProfil
                                            : (
                                                $namaIbuLama !== ""
                                                    ? $namaIbuLama
                                                    : "-"
                                            );

                                    $profilIbuTerhubung =
                                        $namaIbuProfil !== "";
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

                                            <strong class="d-block">
                                                <?= htmlspecialchars(
                                                    $namaIbuTampil,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </strong>

                                            <?php if (
                                                $profilIbuTerhubung
                                            ): ?>

                                                <small class="text-success">
                                                    <i class="bi bi-link-45deg"></i>
                                                    Profil Ibu terhubung
                                                </small>

                                            <?php else: ?>

                                                <small class="text-warning">
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                    Profil Ibu belum terhubung
                                                </small>

                                            <?php endif; ?>

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
