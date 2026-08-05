<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["dinkes"]);

$judulHalaman = "Data Pengguna | Sistem Deteksi Stunting";

$queryPengguna = mysqli_query(
    $conn,
    "SELECT id_user, nama, username, role
     FROM pengguna
     ORDER BY id_user DESC"
);

if (!$queryPengguna) {
    die("Gagal mengambil data pengguna: " . mysqli_error($conn));
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

            <div>
                <h2 class="mb-1">Data Pengguna</h2>

                <p class="text-muted mb-0">
                    Kelola akun Dinkes, ahli gizi, dan orang tua.
                </p>
            </div>

            <a
                href="tambah_user.php"
                class="btn btn-success"
            >
                Tambah Pengguna
            </a>

        </div>

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

        <div class="card content-card">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-success">
                            <tr>
                                <th width="70">No.</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th width="180">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (mysqli_num_rows($queryPengguna) > 0): ?>

                                <?php $nomor = 1; ?>

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
                                    ?>

                                    <tr>
                                        <td><?= $nomor ?></td>

                                        <td><?= $nama ?></td>

                                        <td><?= $username ?></td>

                                        <td>
                                            <span class="badge bg-success">
                                                <?= htmlspecialchars(
                                                    $role,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="d-flex flex-wrap gap-2">

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

                                    <?php $nomor++; ?>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-4"
                                    >
                                        Belum ada data pengguna.
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

<?php require_once "../includes/footer.php"; ?>