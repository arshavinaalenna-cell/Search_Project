<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["dinkes"]);

$judulHalaman = "Tambah Pengguna | Sistem Deteksi Stunting";

$nama = "";
$username = "";
$role = "";
$pesanError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $konfirmasiPassword = $_POST["konfirmasi_password"] ?? "";
    $role = $_POST["role"] ?? "";

    $roleDiizinkan = [
        "dinkes",
        "ahli_gizi",
        "orang_tua"
    ];

    /*
    |--------------------------------------------------------------------------
    | Validasi input
    |--------------------------------------------------------------------------
    */

    if (
        $nama === ""
        || $username === ""
        || $password === ""
        || $konfirmasiPassword === ""
        || $role === ""
    ) {
        $pesanError = "Semua kolom wajib diisi.";
    } elseif (strlen($nama) < 3) {
        $pesanError = "Nama minimal terdiri dari 3 karakter.";
    } elseif (strlen($username) < 4) {
        $pesanError = "Username minimal terdiri dari 4 karakter.";
    } elseif (!preg_match("/^[a-zA-Z0-9._]+$/", $username)) {
        $pesanError = "Username hanya boleh berisi huruf, angka, titik, dan garis bawah.";
    } elseif (strlen($password) < 6) {
        $pesanError = "Password minimal terdiri dari 6 karakter.";
    } elseif ($password !== $konfirmasiPassword) {
        $pesanError = "Konfirmasi password tidak sama.";
    } elseif (!in_array($role, $roleDiizinkan, true)) {
        $pesanError = "Role pengguna tidak valid.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memeriksa username
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $cekUsername = mysqli_prepare(
            $conn,
            "SELECT id_user
             FROM pengguna
             WHERE username = ?
             LIMIT 1"
        );

        if (!$cekUsername) {
            $pesanError = "Terjadi kesalahan saat memeriksa username.";
        } else {
            mysqli_stmt_bind_param(
                $cekUsername,
                "s",
                $username
            );

            mysqli_stmt_execute($cekUsername);

            $hasilCek = mysqli_stmt_get_result($cekUsername);

            if (mysqli_num_rows($hasilCek) > 0) {
                $pesanError = "Username sudah digunakan.";
            }

            mysqli_stmt_close($cekUsername);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan pengguna
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $simpan = mysqli_prepare(
            $conn,
            "INSERT INTO pengguna (
                nama,
                username,
                password,
                role
            ) VALUES (?, ?, ?, ?)"
        );

        if (!$simpan) {
            $pesanError = "Terjadi kesalahan saat menyiapkan penyimpanan data.";
        } else {
            mysqli_stmt_bind_param(
                $simpan,
                "ssss",
                $nama,
                $username,
                $passwordHash,
                $role
            );

            if (mysqli_stmt_execute($simpan)) {
                mysqli_stmt_close($simpan);

                header(
                    "Location: data_user.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError = "Data pengguna gagal disimpan.";

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
                <h2 class="mb-1">Tambah Pengguna</h2>

                <p class="text-muted mb-0">
                    Tambahkan akun baru ke dalam sistem.
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
                            autocomplete="username"
                            required
                        >

                        <div class="form-text">
                            Gunakan huruf, angka, titik, atau garis bawah.
                        </div>
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
                                value="dinkes"
                                <?= $role === "dinkes"
                                    ? "selected"
                                    : "" ?>
                            >
                                Dinkes
                            </option>

                            <option
                                value="ahli_gizi"
                                <?= $role === "ahli_gizi"
                                    ? "selected"
                                    : "" ?>
                            >
                                Ahli Gizi
                            </option>

                            <option
                                value="orang_tua"
                                <?= $role === "orang_tua"
                                    ? "selected"
                                    : "" ?>
                            >
                                Orang Tua
                            </option>
                        </select>
                    </div>

                    <div class="row">

                        <div class="col-12 col-md-6 mb-3">
                            <label
                                for="password"
                                class="form-label"
                            >
                                Password
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                minlength="6"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-6 mb-3">
                            <label
                                for="konfirmasi_password"
                                class="form-label"
                            >
                                Konfirmasi password
                            </label>

                            <input
                                type="password"
                                id="konfirmasi_password"
                                name="konfirmasi_password"
                                class="form-control"
                                minlength="6"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-2">

                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            Simpan Pengguna
                        </button>

                        <button
                            type="reset"
                            class="btn btn-outline-secondary"
                        >
                            Reset
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>