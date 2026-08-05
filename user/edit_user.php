<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["dinkes"]);

$judulHalaman = "Edit Pengguna | Sistem Deteksi Stunting";
$pesanError = "";

$idUser = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idUser) {
    header("Location: data_user.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data pengguna
|--------------------------------------------------------------------------
*/

$stmtPengguna = mysqli_prepare(
    $conn,
    "SELECT id_user, nama, username, role
     FROM pengguna
     WHERE id_user = ?
     LIMIT 1"
);

if (!$stmtPengguna) {
    die("Terjadi kesalahan saat mengambil data pengguna.");
}

mysqli_stmt_bind_param(
    $stmtPengguna,
    "i",
    $idUser
);

mysqli_stmt_execute($stmtPengguna);

$hasilPengguna = mysqli_stmt_get_result($stmtPengguna);
$dataPengguna = mysqli_fetch_assoc($hasilPengguna);

mysqli_stmt_close($stmtPengguna);

if (!$dataPengguna) {
    header("Location: data_user.php");
    exit;
}

$nama = $dataPengguna["nama"];
$username = $dataPengguna["username"];
$role = $dataPengguna["role"];

/*
|--------------------------------------------------------------------------
| Memproses perubahan data
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $role = $_POST["role"] ?? "";

    $passwordBaru = $_POST["password_baru"] ?? "";
    $konfirmasiPassword = $_POST["konfirmasi_password"] ?? "";

$roleDiizinkan = [
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
];
    /*
    |--------------------------------------------------------------------------
    | Validasi data
    |--------------------------------------------------------------------------
    */

    if (
        $nama === ""
        || $username === ""
        || $role === ""
    ) {
        $pesanError = "Nama, username, dan role wajib diisi.";
    } elseif (strlen($nama) < 3) {
        $pesanError = "Nama minimal terdiri dari 3 karakter.";
    } elseif (strlen($username) < 4) {
        $pesanError = "Username minimal terdiri dari 4 karakter.";
    } elseif (!preg_match("/^[a-zA-Z0-9._]+$/", $username)) {
        $pesanError = "Username hanya boleh berisi huruf, angka, titik, dan garis bawah.";
    } elseif (!in_array($role, $roleDiizinkan, true)) {
        $pesanError = "Role pengguna tidak valid.";
    } elseif (
        $passwordBaru !== ""
        && strlen($passwordBaru) < 6
    ) {
        $pesanError = "Password baru minimal terdiri dari 6 karakter.";
    } elseif (
        $passwordBaru !== ""
        && $passwordBaru !== $konfirmasiPassword
    ) {
        $pesanError = "Konfirmasi password baru tidak sama.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memeriksa username duplikat
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $cekUsername = mysqli_prepare(
            $conn,
            "SELECT id_user
             FROM pengguna
             WHERE username = ?
             AND id_user != ?
             LIMIT 1"
        );

        if (!$cekUsername) {
            $pesanError = "Terjadi kesalahan saat memeriksa username.";
        } else {
            mysqli_stmt_bind_param(
                $cekUsername,
                "si",
                $username,
                $idUser
            );

            mysqli_stmt_execute($cekUsername);

            $hasilCek = mysqli_stmt_get_result($cekUsername);

            if (mysqli_num_rows($hasilCek) > 0) {
                $pesanError = "Username sudah digunakan oleh pengguna lain.";
            }

            mysqli_stmt_close($cekUsername);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memperbarui data pengguna
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        if ($passwordBaru !== "") {
            $passwordHash = password_hash(
                $passwordBaru,
                PASSWORD_DEFAULT
            );

            $simpan = mysqli_prepare(
                $conn,
                "UPDATE pengguna
                 SET nama = ?,
                     username = ?,
                     password = ?,
                     role = ?
                 WHERE id_user = ?"
            );

            if ($simpan) {
                mysqli_stmt_bind_param(
                    $simpan,
                    "ssssi",
                    $nama,
                    $username,
                    $passwordHash,
                    $role,
                    $idUser
                );
            }
        } else {
            $simpan = mysqli_prepare(
                $conn,
                "UPDATE pengguna
                 SET nama = ?,
                     username = ?,
                     role = ?
                 WHERE id_user = ?"
            );

            if ($simpan) {
                mysqli_stmt_bind_param(
                    $simpan,
                    "sssi",
                    $nama,
                    $username,
                    $role,
                    $idUser
                );
            }
        }

        if (!$simpan) {
            $pesanError = "Terjadi kesalahan saat menyiapkan perubahan data.";
        } else {
            if (mysqli_stmt_execute($simpan)) {
                mysqli_stmt_close($simpan);

                /*
                 * Jika pengguna mengedit akun yang sedang dipakai,
                 * data session ikut diperbarui.
                 */
                if ($idUser === (int) $_SESSION["id_user"]) {
                    $_SESSION["nama"] = $nama;
                    $_SESSION["username"] = $username;
                    $_SESSION["role"] = $role;
                }

                header(
                    "Location: data_user.php?pesan=edit_berhasil"
                );
                exit;
            }

            $pesanError = "Data pengguna gagal diperbarui.";

            mysqli_stmt_close($simpan);
        }
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

            <div>
                <h2 class="mb-1">Edit Pengguna</h2>

                <p class="text-muted mb-0">
                    Perbarui informasi akun pengguna.
                </p>
            </div>

            <a
                href="data_user.php"
                class="btn btn-outline-secondary"
            >
                Kembali
            </a>

        </div>

        <?php if ($pesanError !== ""): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >
                <?= htmlspecialchars(
                    $pesanError,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-body p-4">

                <form method="POST" autocomplete="off">

                    <div class="mb-3">
                        <label
                            for="nama"
                            class="form-label"
                        >
                            Nama lengkap
                        </label>

                        <input
                            type="text"
                            id="nama"
                            name="nama"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $nama,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label
                            for="username"
                            class="form-label"
                        >
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $username,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            maxlength="50"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label
                            for="role"
                            class="form-label"
                        >
                            Role
                        </label>

                        <select
    id="role"
    name="role"
    class="form-select"
    required
>
    <option value="">
        Pilih role
    </option>

    <option
        value="kader"
        <?= $role === "kader" ? "selected" : "" ?>
    >
        Kader
    </option>

    <option
        value="petugas_kia"
        <?= $role === "petugas_kia" ? "selected" : "" ?>
    >
        Petugas KIA
    </option>

    <option
        value="petugas_gizi"
        <?= $role === "petugas_gizi" ? "selected" : "" ?>
    >
        Petugas Gizi
    </option>

    <option
        value="orang_tua"
        <?= $role === "orang_tua" ? "selected" : "" ?>
    >
        Orang Tua
    </option>

    <option
        value="kepala_puskesmas"
        <?= $role === "kepala_puskesmas" ? "selected" : "" ?>
    >
        Kepala Puskesmas
    </option>

    <option
        value="dinkes"
        <?= $role === "dinkes" ? "selected" : "" ?>
    >
        Dinas Kesehatan
    </option>
</select>
                    </div>

                    <hr class="my-4">

                    <h5>Ubah Password</h5>

                    <p class="text-muted">
                        Kosongkan bagian ini apabila password tidak ingin diubah.
                    </p>

                    <div class="row">

                        <div class="col-12 col-md-6 mb-3">
                            <label
                                for="password_baru"
                                class="form-label"
                            >
                                Password baru
                            </label>

                            <input
                                type="password"
                                id="password_baru"
                                name="password_baru"
                                class="form-control"
                                minlength="6"
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="col-12 col-md-6 mb-3">
                            <label
                                for="konfirmasi_password"
                                class="form-label"
                            >
                                Konfirmasi password baru
                            </label>

                            <input
                                type="password"
                                id="konfirmasi_password"
                                name="konfirmasi_password"
                                class="form-control"
                                minlength="6"
                                autocomplete="new-password"
                            >
                        </div>

                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-2">

                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            Simpan Perubahan
                        </button>

                        <a
                            href="data_user.php"
                            class="btn btn-outline-secondary"
                        >
                            Batal
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>