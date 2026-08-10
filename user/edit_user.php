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
    header(
        "Location: data_user.php?pesan=tidak_ditemukan"
    );
    exit;
}

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
| Mengambil data pengguna
|--------------------------------------------------------------------------
*/

$stmtPengguna = mysqli_prepare(
    $conn,
    "SELECT
        id_user,
        nama,
        username,
        role,
        id_puskesmas
     FROM pengguna
     WHERE id_user = ?
     LIMIT 1"
);

if (!$stmtPengguna) {
    die(
        "Terjadi kesalahan saat mengambil data pengguna: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPengguna,
    "i",
    $idUser
);

mysqli_stmt_execute($stmtPengguna);

$hasilPengguna =
    mysqli_stmt_get_result(
        $stmtPengguna
    );

$dataPengguna =
    mysqli_fetch_assoc(
        $hasilPengguna
    );

mysqli_stmt_close(
    $stmtPengguna
);

if (!$dataPengguna) {
    header(
        "Location: data_user.php?pesan=tidak_ditemukan"
    );
    exit;
}

$nama =
    $dataPengguna["nama"] ?? "";

$username =
    $dataPengguna["username"] ?? "";

$role =
    $dataPengguna["role"] ?? "";

$roleAwal = $role;

$idPuskesmas =
    $dataPengguna["id_puskesmas"] !== null
        ? (int) $dataPengguna["id_puskesmas"]
        : null;

$idPuskesmasAwal =
    $idPuskesmas;

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
| Memproses perubahan data
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama = trim(
        $_POST["nama"] ?? ""
    );

    $username = trim(
        $_POST["username"] ?? ""
    );

    $role =
        $_POST["role"] ?? "";

    $passwordBaru =
        $_POST["password_baru"] ?? "";

    $konfirmasiPassword =
        $_POST["konfirmasi_password"] ?? "";

    $idPuskesmasInput = filter_input(
        INPUT_POST,
        "id_puskesmas",
        FILTER_VALIDATE_INT
    );

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
        $pesanError =
            "Nama, username, dan role wajib diisi.";
    } elseif (
        strlen($nama) < 3
    ) {
        $pesanError =
            "Nama minimal terdiri dari 3 karakter.";
    } elseif (
        strlen($username) < 4
    ) {
        $pesanError =
            "Username minimal terdiri dari 4 karakter.";
    } elseif (
        !preg_match(
            "/^[a-zA-Z0-9._]+$/",
            $username
        )
    ) {
        $pesanError =
            "Username hanya boleh berisi huruf, angka, titik, dan garis bawah.";
    } elseif (
        !in_array(
            $role,
            $roleDiizinkan,
            true
        )
    ) {
        $pesanError =
            "Role pengguna tidak valid.";
    } elseif (
        $passwordBaru !== ""
        && strlen($passwordBaru) < 6
    ) {
        $pesanError =
            "Password baru minimal terdiri dari 6 karakter.";
    } elseif (
        $passwordBaru !== ""
        && $passwordBaru !==
            $konfirmasiPassword
    ) {
        $pesanError =
            "Konfirmasi password baru tidak sama.";
    }

    /*
    |--------------------------------------------------------------------------
    | Cegah akun Dinkes aktif mengubah role dirinya sendiri
    |--------------------------------------------------------------------------
    */

    if (
        $pesanError === ""
        && $idUser ===
            (int) ($_SESSION["id_user"] ?? 0)
        && $role !== $roleAwal
    ) {
        $pesanError =
            "Role akun yang sedang digunakan tidak dapat diubah.";
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
            | Orang Tua dan Dinkes tidak diikat
            | ke satu Puskesmas di tabel pengguna.
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

            mysqli_stmt_execute(
                $cekPuskesmas
            );

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
            $pesanError =
                "Terjadi kesalahan saat memeriksa username.";
        } else {
            mysqli_stmt_bind_param(
                $cekUsername,
                "si",
                $username,
                $idUser
            );

            mysqli_stmt_execute(
                $cekUsername
            );

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
                    "Username sudah digunakan oleh pengguna lain.";
            }

            mysqli_stmt_close(
                $cekUsername
            );
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
                 SET
                    nama = ?,
                    username = ?,
                    password = ?,
                    role = ?,
                    id_puskesmas = ?
                 WHERE id_user = ?"
            );

            if ($simpan) {
                mysqli_stmt_bind_param(
                    $simpan,
                    "ssssii",
                    $nama,
                    $username,
                    $passwordHash,
                    $role,
                    $idPuskesmas,
                    $idUser
                );
            }

        } else {

            $simpan = mysqli_prepare(
                $conn,
                "UPDATE pengguna
                 SET
                    nama = ?,
                    username = ?,
                    role = ?,
                    id_puskesmas = ?
                 WHERE id_user = ?"
            );

            if ($simpan) {
                mysqli_stmt_bind_param(
                    $simpan,
                    "sssii",
                    $nama,
                    $username,
                    $role,
                    $idPuskesmas,
                    $idUser
                );
            }
        }

        if (!$simpan) {

            $pesanError =
                "Terjadi kesalahan saat menyiapkan perubahan data: "
                . mysqli_error($conn);

        } else {

            if (
                mysqli_stmt_execute(
                    $simpan
                )
            ) {
                mysqli_stmt_close(
                    $simpan
                );

                /*
                |--------------------------------------------------------------------------
                | Jika akun aktif diedit, perbarui session yang relevan
                |--------------------------------------------------------------------------
                */

                if (
                    $idUser ===
                    (int) (
                        $_SESSION["id_user"]
                        ?? 0
                    )
                ) {
                    $_SESSION["nama"] =
                        $nama;

                    $_SESSION["username"] =
                        $username;

                    $_SESSION["role"] =
                        $role;
                }

                header(
                    "Location: data_user.php?pesan=edit_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data pengguna gagal diperbarui: "
                . mysqli_stmt_error(
                    $simpan
                );

            mysqli_stmt_close(
                $simpan
            );
        }
    }

    /*
    | Jika validasi gagal dan role berubah menjadi role tanpa Puskesmas,
    | nilai pilihan dikembalikan agar form tidak menampilkan data lama.
    */
    if (
        !in_array(
            $role,
            $roleWajibPuskesmas,
            true
        )
    ) {
        $idPuskesmas = null;
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
                        Edit Pengguna
                    </h4>

                    <small class="text-muted">
                        Perbarui akun, role, dan penempatan
                        Puskesmas pengguna.
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

                <form
                    method="POST"
                    autocomplete="off"
                >

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
                                required
                            >

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

                            <?php if (
                                $idUser ===
                                (int) (
                                    $_SESSION["id_user"]
                                    ?? 0
                                )
                            ): ?>

                                <div class="form-text">
                                    Role akun yang sedang digunakan
                                    tidak dapat diubah.
                                </div>

                            <?php endif; ?>

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
                                        <?= $idPuskesmas !== null
                                            && (int) $idPuskesmas ===
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

                        <div class="col-12">

                            <hr>

                            <h6 class="mb-1">
                                Ubah Password
                            </h6>

                            <small class="text-muted">
                                Kosongkan bagian ini apabila
                                password tidak ingin diubah.
                            </small>

                        </div>

                        <div class="col-12 col-md-6">

                            <label
                                for="password_baru"
                                class="form-label"
                            >
                                Password Baru
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

                        <div class="col-12 col-md-6">

                            <label
                                for="konfirmasi_password"
                                class="form-label"
                            >
                                Konfirmasi Password Baru
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

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-check-circle"></i>
                            Simpan Perubahan
                        </button>

                        <a
                            href="data_user.php"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-x-circle"></i>
                            Batal
                        </a>

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