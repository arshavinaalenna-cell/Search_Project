<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["dinkes"]);

$judulHalaman = "Tambah Pengguna | Sistem Deteksi Stunting";

$nama = "";
$username = "";
$role = "";
$idPuskesmas = "";
$pesanError = "";

$roleDiizinkan = [
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
];

$roleWajibPuskesmas = [
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "kepala_puskesmas"
];

/*
|--------------------------------------------------------------------------
| Mengambil daftar Puskesmas
|--------------------------------------------------------------------------
*/

$queryPuskesmas = mysqli_query(
    $conn,
    "SELECT
        id_puskesmas,
        nama_puskesmas
     FROM puskesmas
     ORDER BY nama_puskesmas ASC"
);

if (!$queryPuskesmas) {
    die(
        "Gagal mengambil data Puskesmas: "
        . mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| Memproses penyimpanan pengguna
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $konfirmasiPassword = $_POST["konfirmasi_password"] ?? "";
    $role = $_POST["role"] ?? "";

    $idPuskesmasInput = filter_input(
        INPUT_POST,
        "id_puskesmas",
        FILTER_VALIDATE_INT
    );

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
        $pesanError =
            "Username hanya boleh berisi huruf, angka, titik, dan garis bawah.";
    } elseif (strlen($password) < 6) {
        $pesanError = "Password minimal terdiri dari 6 karakter.";
    } elseif ($password !== $konfirmasiPassword) {
        $pesanError = "Konfirmasi password tidak sama.";
    } elseif (!in_array($role, $roleDiizinkan, true)) {
        $pesanError = "Role pengguna tidak valid.";
    }

    /*
    |--------------------------------------------------------------------------
    | Menentukan kebutuhan Puskesmas berdasarkan role
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

        if (
            in_array(
                $role,
                $roleWajibPuskesmas,
                true
            )
        ) {
            if (
                !$idPuskesmasInput
                || $idPuskesmasInput < 1
            ) {
                $pesanError =
                    "Silakan pilih Puskesmas untuk role tersebut.";
            } else {
                $idPuskesmas =
                    (int) $idPuskesmasInput;
            }
        } else {
            /*
            | Orang Tua dan Dinkes tidak diikat ke satu Puskesmas
            | pada tabel pengguna.
            */
            $idPuskesmas = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan Puskesmas valid
    |--------------------------------------------------------------------------
    */

    if (
        $pesanError === ""
        && $idPuskesmas !== null
    ) {
        $cekPuskesmas = mysqli_prepare(
            $conn,
            "SELECT id_puskesmas
             FROM puskesmas
             WHERE id_puskesmas = ?
             LIMIT 1"
        );

        if (!$cekPuskesmas) {
            $pesanError =
                "Terjadi kesalahan saat memeriksa Puskesmas.";
        } else {
            mysqli_stmt_bind_param(
                $cekPuskesmas,
                "i",
                $idPuskesmas
            );

            mysqli_stmt_execute($cekPuskesmas);

            $hasilPuskesmas =
                mysqli_stmt_get_result(
                    $cekPuskesmas
                );

            if (
                mysqli_num_rows(
                    $hasilPuskesmas
                ) === 0
            ) {
                $pesanError =
                    "Puskesmas yang dipilih tidak ditemukan.";
            }

            mysqli_stmt_close(
                $cekPuskesmas
            );
        }
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
            $pesanError =
                "Terjadi kesalahan saat memeriksa username.";
        } else {
            mysqli_stmt_bind_param(
                $cekUsername,
                "s",
                $username
            );

            mysqli_stmt_execute($cekUsername);

            $hasilCek =
                mysqli_stmt_get_result(
                    $cekUsername
                );

            if (
                mysqli_num_rows(
                    $hasilCek
                ) > 0
            ) {
                $pesanError =
                    "Username sudah digunakan.";
            }

            mysqli_stmt_close(
                $cekUsername
            );
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
                role,
                id_puskesmas
            ) VALUES (?, ?, ?, ?, ?)"
        );

        if (!$simpan) {
            $pesanError =
                "Terjadi kesalahan saat menyiapkan penyimpanan data.";
        } else {
            mysqli_stmt_bind_param(
                $simpan,
                "ssssi",
                $nama,
                $username,
                $passwordHash,
                $role,
                $idPuskesmas
            );

            if (
                mysqli_stmt_execute(
                    $simpan
                )
            ) {
                mysqli_stmt_close(
                    $simpan
                );

                header(
                    "Location: data_user.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data pengguna gagal disimpan: "
                . mysqli_stmt_error(
                    $simpan
                );

            mysqli_stmt_close(
                $simpan
            );
        }
    }
}

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
                        Tambah Pengguna
                    </h4>

                    <small class="text-muted">
                        Tambahkan akun baru dan tentukan Puskesmas
                        sesuai peran pengguna.
                    </small>
                </div>

                <a
                    href="data_user.php"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

            </div>

            <div class="card-body">

                <?php if ($pesanError !== ""): ?>

                    <div
                        class="alert alert-danger alert-dismissible fade show"
                        role="alert"
                    >
                        <i class="bi bi-x-circle me-1"></i>

                        <?= htmlspecialchars(
                            $pesanError,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>
                    </div>

                <?php endif; ?>

                <form method="POST" autocomplete="off">

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">

                            <label
                                for="nama"
                                class="form-label"
                            >
                                Nama Lengkap
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
                                ); ?>"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-6">

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
                                ); ?>"
                                maxlength="50"
                                autocomplete="username"
                                required
                            >

                            <div class="form-text">
                                Gunakan huruf, angka, titik,
                                atau garis bawah.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

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
                                    -- Pilih Role --
                                </option>

                                <option
                                    value="kader"
                                    <?= $role === "kader"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Kader
                                </option>

                                <option
                                    value="petugas_kia"
                                    <?= $role === "petugas_kia"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Petugas KIA
                                </option>

                                <option
                                    value="petugas_gizi"
                                    <?= $role === "petugas_gizi"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Petugas Gizi
                                </option>

                                <option
                                    value="orang_tua"
                                    <?= $role === "orang_tua"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Orang Tua
                                </option>

                                <option
                                    value="kepala_puskesmas"
                                    <?= $role === "kepala_puskesmas"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Kepala Puskesmas
                                </option>

                                <option
                                    value="dinkes"
                                    <?= $role === "dinkes"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Dinas Kesehatan
                                </option>
                            </select>

                        </div>

                        <div
                            class="col-12 col-lg-6"
                            id="wrapperPuskesmas"
                        >

                            <label
                                for="id_puskesmas"
                                class="form-label"
                            >
                                Puskesmas
                            </label>

                            <select
                                id="id_puskesmas"
                                name="id_puskesmas"
                                class="form-select"
                            >
                                <option value="">
                                    -- Pilih Puskesmas --
                                </option>

                                <?php
                                mysqli_data_seek(
                                    $queryPuskesmas,
                                    0
                                );
                                ?>

                                <?php while (
                                    $puskesmas =
                                        mysqli_fetch_assoc(
                                            $queryPuskesmas
                                        )
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $puskesmas[
                                                "id_puskesmas"
                                            ]; ?>"
                                        <?= (int) $idPuskesmas ===
                                            (int) $puskesmas[
                                                "id_puskesmas"
                                            ]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $puskesmas[
                                                "nama_puskesmas"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                            <div class="form-text">
                                Wajib untuk Kader, Petugas KIA,
                                Petugas Gizi, dan Kepala Puskesmas.
                            </div>

                        </div>

                        <div class="col-12 col-md-6">

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

                        <div class="col-12 col-md-6">

                            <label
                                for="konfirmasi_password"
                                class="form-label"
                            >
                                Konfirmasi Password
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

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-check-circle"></i>
                            Simpan Pengguna
                        </button>

                        <button
                            type="reset"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        const roleSelect =
            document.getElementById("role");

        const wrapperPuskesmas =
            document.getElementById(
                "wrapperPuskesmas"
            );

        const puskesmasSelect =
            document.getElementById(
                "id_puskesmas"
            );

        const roleWajibPuskesmas = [
            "kader",
            "petugas_kia",
            "petugas_gizi",
            "kepala_puskesmas"
        ];

        function aturPuskesmas() {

            const role =
                roleSelect.value;

            const wajib =
                roleWajibPuskesmas.includes(
                    role
                );

            wrapperPuskesmas.style.display =
                wajib
                    ? ""
                    : "none";

            puskesmasSelect.required =
                wajib;

            if (!wajib) {
                puskesmasSelect.value = "";
            }
        }

        roleSelect.addEventListener(
            "change",
            aturPuskesmas
        );

        aturPuskesmas();
    }
);
</script>

<?php require_once "../includes/footer.php"; ?>