<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["dinkes"]);

$judulHalaman = "Data Pengguna | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Filter & pencarian
|--------------------------------------------------------------------------
*/

$cari = trim($_GET["cari"] ?? "");
$filterRole = trim($_GET["role"] ?? "");
$filterPuskesmas = trim($_GET["puskesmas"] ?? "");

/*
|--------------------------------------------------------------------------
| Daftar role
|--------------------------------------------------------------------------
*/

$daftarRole = [];

$queryRole = mysqli_query(
    $conn,
    "SELECT DISTINCT role
     FROM pengguna
     WHERE role IS NOT NULL
       AND role <> ''
     ORDER BY role ASC"
);

if ($queryRole) {
    while ($dataRole = mysqli_fetch_assoc($queryRole)) {
        $daftarRole[] = $dataRole["role"];
    }
}

/*
|--------------------------------------------------------------------------
| Daftar Puskesmas
|--------------------------------------------------------------------------
*/

$daftarPuskesmas = [];

$queryPuskesmas = mysqli_query(
    $conn,
    "SELECT id_puskesmas, nama_puskesmas
     FROM puskesmas
     ORDER BY nama_puskesmas ASC"
);

if ($queryPuskesmas) {
    while ($dataPuskesmas = mysqli_fetch_assoc($queryPuskesmas)) {
        $daftarPuskesmas[] = $dataPuskesmas;
    }
}

/*
|--------------------------------------------------------------------------
| Query data pengguna
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.id_user,
        u.nama,
        u.username,
        u.role,
        u.password,
        u.id_puskesmas,
        p.nama_puskesmas
    FROM pengguna u
    LEFT JOIN puskesmas p
        ON u.id_puskesmas = p.id_puskesmas
    WHERE 1 = 1
";

$tipe = "";
$parameter = [];

if ($cari !== "") {
    $sql .= "
        AND (
            u.nama LIKE ?
            OR u.username LIKE ?
        )
    ";

    $kataKunci = "%" . $cari . "%";

    $tipe .= "ss";
    $parameter[] = $kataKunci;
    $parameter[] = $kataKunci;
}

if ($filterRole !== "") {
    $sql .= " AND u.role = ? ";

    $tipe .= "s";
    $parameter[] = $filterRole;
}

if ($filterPuskesmas === "tanpa") {
    $sql .= " AND u.id_puskesmas IS NULL ";
} elseif (
    $filterPuskesmas !== ""
    && ctype_digit($filterPuskesmas)
    && (int) $filterPuskesmas > 0
) {
    $sql .= " AND u.id_puskesmas = ? ";

    $tipe .= "i";
    $parameter[] = (int) $filterPuskesmas;
}

$sql .= " ORDER BY u.id_user DESC ";

$stmtPengguna = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmtPengguna) {
    die(
        "Gagal menyiapkan data pengguna: "
        . mysqli_error($conn)
    );
}

if ($parameter !== []) {

    mysqli_stmt_bind_param(
        $stmtPengguna,
        $tipe,
        ...$parameter
    );
}

if (!mysqli_stmt_execute($stmtPengguna)) {
    die(
        "Gagal mengambil data pengguna: "
        . mysqli_stmt_error($stmtPengguna)
    );
}

$queryPengguna = mysqli_stmt_get_result(
    $stmtPengguna
);

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
                        Data Pengguna
                    </h4>

                    <small class="text-muted">
                        Kelola akun pengguna dan penempatan Puskesmas.
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

                    <a
                        href="tambah_user.php"
                        class="btn btn-primary btn-sm"
                    >
                        <i class="bi bi-plus-circle"></i>
                        Tambah Pengguna
                    </a>

                </div>

            </div>

            <div class="card-body">

        <?php if (isset($_GET["pesan"])): ?>

            <?php if ($_GET["pesan"] === "tambah_berhasil"): ?>

                <div class="alert alert-success alert-dismissible fade show">
                    Data pengguna berhasil ditambahkan.

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>

            <?php elseif ($_GET["pesan"] === "edit_berhasil"): ?>

                <div class="alert alert-success alert-dismissible fade show">
                    Data pengguna berhasil diperbarui.

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_berhasil"): ?>

                <div class="alert alert-success alert-dismissible fade show">
                    Data pengguna berhasil dihapus.

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_gagal"): ?>

                <div class="alert alert-danger alert-dismissible fade show">
                    Data pengguna gagal dihapus.

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>

            <?php elseif ($_GET["pesan"] === "hapus_sendiri"): ?>

                <div class="alert alert-warning alert-dismissible fade show">
                    Akun yang sedang digunakan tidak dapat dihapus.

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>

            <?php elseif ($_GET["pesan"] === "tidak_ditemukan"): ?>

                <div class="alert alert-warning alert-dismissible fade show">
                    Data pengguna tidak ditemukan.

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>

            <?php endif; ?>

        <?php endif; ?>

                <!-- Filter Data Pengguna -->
                <form
                    method="GET"
                    class="row g-2 align-items-end mb-4"
                >

                    <div class="col-12 col-lg-4">

                        <label
                            for="cari"
                            class="form-label small text-muted mb-1"
                        >
                            Pencarian
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="text"
                                id="cari"
                                name="cari"
                                class="form-control"
                                placeholder="Cari nama atau username"
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >

                        </div>

                    </div>

                    <div class="col-12 col-md-6 col-lg-2">

                        <label
                            for="filter_role"
                            class="form-label small text-muted mb-1"
                        >
                            Role
                        </label>

                        <select
                            id="filter_role"
                            name="role"
                            class="form-select"
                        >

                            <option value="">
                                Semua Role
                            </option>

                            <?php foreach ($daftarRole as $roleItem): ?>

                                <?php
                                $labelRole = ucwords(
                                    str_replace(
                                        "_",
                                        " ",
                                        $roleItem
                                    )
                                );
                                ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $roleItem,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                    <?= $filterRole === $roleItem
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= htmlspecialchars(
                                        $labelRole,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-12 col-md-6 col-lg-3">

                        <label
                            for="filter_puskesmas"
                            class="form-label small text-muted mb-1"
                        >
                            Puskesmas
                        </label>

                        <select
                            id="filter_puskesmas"
                            name="puskesmas"
                            class="form-select"
                        >

                            <option value="">
                                Semua Puskesmas
                            </option>

                            <option
                                value="tanpa"
                                <?= $filterPuskesmas === "tanpa"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Tidak Terikat Puskesmas
                            </option>

                            <?php foreach ($daftarPuskesmas as $puskesmas): ?>

                                <option
                                    value="<?= (int) $puskesmas["id_puskesmas"]; ?>"
                                    <?= (
                                        $filterPuskesmas !== "tanpa"
                                        && (string) $filterPuskesmas
                                            ===
                                            (string) $puskesmas["id_puskesmas"]
                                    )
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

                    <div class="col-6 col-lg-1">

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
                            href="data_user.php"
                            class="btn btn-light w-100"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset
                        </a>

                    </div>

                </form>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th width="70">No.</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Puskesmas</th>
                                <th width="180">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (mysqli_num_rows($queryPengguna) > 0): ?>

                                <?php $nomor = 1; ?>
                                <?php $modalDetailPengguna = ""; ?>

                                <?php while ($pengguna = mysqli_fetch_assoc($queryPengguna)): ?>

                                    <?php
                                    $idUser = (int) $pengguna["id_user"];

                                    $nama = htmlspecialchars(
                                        $pengguna["nama"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    $username = htmlspecialchars(
                                        $pengguna["username"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    $role = ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $pengguna["role"]
                                        )
                                    );

                                    $namaPuskesmas = trim(
                                        (string) (
                                            $pengguna["nama_puskesmas"]
                                            ?? ""
                                        )
                                    );
                                    ?>

                                    <tr>
                                        <td><?= $nomor ?></td>

                                        <td><?= $nama ?></td>

                                        <td><?= $username ?></td>

                                        <td>
                                            <span class="badge badge-info">
                                                <?= htmlspecialchars(
                                                    $role,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($namaPuskesmas !== ""): ?>

                                                <span class="badge bg-light text-dark">
                                                    <i class="bi bi-hospital me-1"></i>
                                                    <?= htmlspecialchars(
                                                        $namaPuskesmas,
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                </span>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    Tidak terikat Puskesmas
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="d-flex flex-wrap gap-2">

                                                <button
                                                    type="button"
                                                    class="btn btn-info btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalDetailUser<?= $idUser ?>"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                    Detail
                                                </button>

                                                <a
                                                    href="edit_user.php?id=<?= $idUser ?>"
                                                    class="btn btn-warning btn-sm"
                                                >
                                                    Edit
                                                </a>

                                                <?php if ($idUser !== (int) $_SESSION["id_user"]): ?>

                                                    <form
                                                        action="hapus_user.php"
                                                        method="POST"
                                                        class="d-inline form-hapus"
                                                        data-nama="<?= $nama ?>"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_user"
                                                            value="<?= $idUser ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger btn-sm"
                                                        >
                                                            Hapus
                                                        </button>
                                                    </form>

                                                <?php else: ?>

                                                    <button
                                                        type="button"
                                                        class="btn btn-secondary btn-sm"
                                                        disabled
                                                        title="Akun yang sedang digunakan tidak dapat dihapus"
                                                    >
                                                        Hapus
                                                    </button>

                                                <?php endif; ?>

                                            </div>
                                        </td>
                                    </tr>

                                    <?php
                                    /*
                                    |------------------------------------------------------------
                                    | Modal Detail Pengguna
                                    |------------------------------------------------------------
                                    |
                                    | Ditampung dalam variabel dan dicetak setelah tabel selesai
                                    | (di luar <table>) agar struktur HTML tetap valid.
                                    |
                                    */
                                    ob_start();
                                    ?>

                                    <div
                                        class="modal fade"
                                        id="modalDetailUser<?= $idUser ?>"
                                        tabindex="-1"
                                        aria-labelledby="modalDetailUser<?= $idUser ?>Label"
                                        aria-hidden="true"
                                    >
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5
                                                        class="modal-title"
                                                        id="modalDetailUser<?= $idUser ?>Label"
                                                    >
                                                        <i class="bi bi-person-badge me-1"></i>
                                                        Detail Pengguna
                                                    </h5>
                                                    <button
                                                        type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal"
                                                        aria-label="Tutup"
                                                    ></button>
                                                </div>

                                                <div class="modal-body">

                                                    <div class="detail-item mb-3">
                                                        <span class="detail-label">Nama Lengkap</span>
                                                        <div class="detail-value"><?= $nama ?></div>
                                                    </div>

                                                    <div class="detail-item mb-3">
                                                        <span class="detail-label">Username</span>
                                                        <div class="detail-value"><?= $username ?></div>
                                                    </div>

                                                    <div class="detail-item mb-3">
                                                        <span class="detail-label">Role</span>
                                                        <div class="detail-value">
                                                            <span class="badge badge-info">
                                                                <?= htmlspecialchars($role, ENT_QUOTES, "UTF-8") ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <span class="detail-label">Password</span>
                                                        <div class="detail-value">
                                                            <span class="text-muted">
                                                                &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;
                                                            </span>
                                                        </div>
                                                        <div class="form-text mt-1">
                                                            <i class="bi bi-shield-lock me-1"></i>
                                                            Password disimpan dalam bentuk terenkripsi (hash)
                                                            demi keamanan, sehingga tidak dapat ditampilkan
                                                            sebagai teks asli. Gunakan tombol
                                                            <strong>Edit</strong> untuk mengatur password baru
                                                            bagi pengguna ini.
                                                        </div>
                                                    </div>

                                                </div>

                                                <div class="modal-footer">
                                                    <button
                                                        type="button"
                                                        class="btn btn-secondary btn-sm"
                                                        data-bs-dismiss="modal"
                                                    >
                                                        Tutup
                                                    </button>

                                                    <a
                                                        href="edit_user.php?id=<?= $idUser ?>"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        <i class="bi bi-pencil"></i>
                                                        Edit
                                                    </a>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                    $modalDetailPengguna .= ob_get_clean();
                                    ?>

                                    <?php $nomor++; ?>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>
                                    <td
                                        colspan="6"
                                        class="text-center text-muted py-4"
                                    >
                                        <?= (
                                            $cari !== ""
                                            || $filterRole !== ""
                                            || $filterPuskesmas !== ""
                                        )
                                            ? "Data pengguna tidak ditemukan sesuai filter."
                                            : "Belum ada data pengguna."; ?>
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <?php if (!empty($modalDetailPengguna)): ?>
                    <?= $modalDetailPengguna; ?>
                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>