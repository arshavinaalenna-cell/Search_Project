<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

$judulHalaman = "Edit Data Balita | Sistem Deteksi Stunting";
$pesanError = "";

/*
|--------------------------------------------------------------------------
| Mengambil ID balita
|--------------------------------------------------------------------------
*/

$idBalita = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idBalita) {
    header("Location: data_balita.php?pesan=tidak_ditemukan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data balita
|--------------------------------------------------------------------------
*/

$stmtBalita = mysqli_prepare(
    $conn,
    "SELECT
        id_balita,
        id_user,
        nik_balita,
        nama_balita,
        jenis_kelamin,
        tanggal_lahir,
        umur,
        nama_ibu,
        alamat,
        wilayah_posyandu
     FROM balita
     WHERE id_balita = ?
     LIMIT 1"
);

if (!$stmtBalita) {
    die("Terjadi kesalahan saat mengambil data balita.");
}

mysqli_stmt_bind_param(
    $stmtBalita,
    "i",
    $idBalita
);

mysqli_stmt_execute($stmtBalita);

$hasilBalita = mysqli_stmt_get_result($stmtBalita);
$dataBalita = mysqli_fetch_assoc($hasilBalita);

mysqli_stmt_close($stmtBalita);

if (!$dataBalita) {
    header("Location: data_balita.php?pesan=tidak_ditemukan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$idUser = (int) $dataBalita["id_user"];
$nikBalita = $dataBalita["nik_balita"];
$namaBalita = $dataBalita["nama_balita"];
$jenisKelamin = $dataBalita["jenis_kelamin"];
$tanggalLahir = $dataBalita["tanggal_lahir"];
$namaIbu = $dataBalita["nama_ibu"];
$alamat = $dataBalita["alamat"];
$wilayahPosyandu = $dataBalita["wilayah_posyandu"];

/*
|--------------------------------------------------------------------------
| Mengambil daftar akun orang tua
|--------------------------------------------------------------------------
*/

$queryOrangTua = mysqli_query(
    $conn,
    "SELECT id_user, nama, username
     FROM pengguna
     WHERE role = 'orang_tua'
     ORDER BY nama ASC"
);

if (!$queryOrangTua) {
    die(
        "Gagal mengambil data orang tua: "
        . mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| Memproses perubahan data
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $idUser = filter_input(
        INPUT_POST,
        "id_user",
        FILTER_VALIDATE_INT
    );

    $nikBalita = trim($_POST["nik_balita"] ?? "");
    $namaBalita = trim($_POST["nama_balita"] ?? "");
    $jenisKelamin = $_POST["jenis_kelamin"] ?? "";
    $tanggalLahir = $_POST["tanggal_lahir"] ?? "";
    $namaIbu = trim($_POST["nama_ibu"] ?? "");
    $alamat = trim($_POST["alamat"] ?? "");
    $wilayahPosyandu = trim(
        $_POST["wilayah_posyandu"] ?? ""
    );

    $jenisKelaminDiizinkan = [
        "Laki-laki",
        "Perempuan"
    ];

    /*
    |--------------------------------------------------------------------------
    | Validasi input
    |--------------------------------------------------------------------------
    */

    if (
        !$idUser
        || $nikBalita === ""
        || $namaBalita === ""
        || $jenisKelamin === ""
        || $tanggalLahir === ""
        || $namaIbu === ""
        || $alamat === ""
        || $wilayahPosyandu === ""
    ) {
        $pesanError = "Semua data wajib diisi.";
    } elseif (!preg_match("/^[0-9]{16}$/", $nikBalita)) {
        $pesanError = "NIK balita harus terdiri dari 16 angka.";
    } elseif (
        !in_array(
            $jenisKelamin,
            $jenisKelaminDiizinkan,
            true
        )
    ) {
        $pesanError = "Jenis kelamin tidak valid.";
    } elseif (strtotime($tanggalLahir) === false) {
        $pesanError = "Tanggal lahir tidak valid.";
    } elseif (strtotime($tanggalLahir) > time()) {
        $pesanError = "Tanggal lahir tidak boleh melebihi hari ini.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan akun yang dipilih adalah Orang Tua
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $cekOrangTua = mysqli_prepare(
            $conn,
            "SELECT id_user
             FROM pengguna
             WHERE id_user = ?
             AND role = 'orang_tua'
             LIMIT 1"
        );

        if (!$cekOrangTua) {
            $pesanError =
                "Terjadi kesalahan saat memeriksa akun orang tua.";
        } else {
            mysqli_stmt_bind_param(
                $cekOrangTua,
                "i",
                $idUser
            );

            mysqli_stmt_execute($cekOrangTua);

            $hasilOrangTua = mysqli_stmt_get_result(
                $cekOrangTua
            );

            if (mysqli_num_rows($hasilOrangTua) === 0) {
                $pesanError =
                    "Akun orang tua tidak ditemukan.";
            }

            mysqli_stmt_close($cekOrangTua);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memeriksa NIK duplikat
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $cekNik = mysqli_prepare(
            $conn,
            "SELECT id_balita
             FROM balita
             WHERE nik_balita = ?
             AND id_balita != ?
             LIMIT 1"
        );

        if (!$cekNik) {
            $pesanError =
                "Terjadi kesalahan saat memeriksa NIK balita.";
        } else {
            mysqli_stmt_bind_param(
                $cekNik,
                "si",
                $nikBalita,
                $idBalita
            );

            mysqli_stmt_execute($cekNik);

            $hasilNik = mysqli_stmt_get_result($cekNik);

            if (mysqli_num_rows($hasilNik) > 0) {
                $pesanError =
                    "NIK balita sudah digunakan oleh data lain.";
            }

            mysqli_stmt_close($cekNik);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menghitung ulang umur dalam bulan
    |--------------------------------------------------------------------------
    */

    $umur = 0;

    if ($pesanError === "") {
        try {
            $tanggalLahirObjek = new DateTime(
                $tanggalLahir
            );

            $tanggalHariIni = new DateTime();

            $selisih = $tanggalLahirObjek->diff(
                $tanggalHariIni
            );

            $umur = (
                $selisih->y * 12
            ) + $selisih->m;
        } catch (Exception $exception) {
            $pesanError =
                "Tanggal lahir tidak dapat diproses.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memperbarui data balita
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $update = mysqli_prepare(
            $conn,
            "UPDATE balita
             SET
                id_user = ?,
                nik_balita = ?,
                nama_balita = ?,
                jenis_kelamin = ?,
                tanggal_lahir = ?,
                umur = ?,
                nama_ibu = ?,
                alamat = ?,
                wilayah_posyandu = ?
             WHERE id_balita = ?"
        );

        if (!$update) {
            $pesanError =
                "Terjadi kesalahan saat menyiapkan perubahan data.";
        } else {
            mysqli_stmt_bind_param(
                $update,
                "issssisssi",
                $idUser,
                $nikBalita,
                $namaBalita,
                $jenisKelamin,
                $tanggalLahir,
                $umur,
                $namaIbu,
                $alamat,
                $wilayahPosyandu,
                $idBalita
            );

            if (mysqli_stmt_execute($update)) {
                mysqli_stmt_close($update);

                header(
                    "Location: data_balita.php?pesan=edit_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data balita gagal diperbarui: "
                . mysqli_stmt_error($update);

            mysqli_stmt_close($update);
        }
    }
}

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
                    Edit Data Balita
                </h2>

                <p class="text-muted mb-0">
                    Perbarui profil balita dan akun orang tua
                    yang terhubung.
                </p>
            </div>

            <a
                href="data_balita.php"
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

                <form method="POST">

                    <div class="mb-3">
                        <label
                            for="id_user"
                            class="form-label"
                        >
                            Orang Tua
                        </label>

                        <select
                            id="id_user"
                            name="id_user"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Pilih akun orang tua
                            </option>

                            <?php while (
                                $orangTua = mysqli_fetch_assoc(
                                    $queryOrangTua
                                )
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $orangTua["id_user"] ?>"
                                    <?= (int) $idUser ===
                                        (int) $orangTua["id_user"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= htmlspecialchars(
                                        $orangTua["nama"]
                                        . " ("
                                        . $orangTua["username"]
                                        . ")",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </option>

                            <?php endwhile; ?>

                        </select>

                        <div class="form-text">
                            ID akun orang tua disimpan otomatis
                            oleh sistem.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label
                            for="nik_balita"
                            class="form-label"
                        >
                            NIK Balita
                        </label>

                        <input
                            type="text"
                            id="nik_balita"
                            name="nik_balita"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $nikBalita,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            minlength="16"
                            maxlength="16"
                            inputmode="numeric"
                            pattern="[0-9]{16}"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label
                            for="nama_balita"
                            class="form-label"
                        >
                            Nama Balita
                        </label>

                        <input
                            type="text"
                            id="nama_balita"
                            name="nama_balita"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $namaBalita,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label
                            for="jenis_kelamin"
                            class="form-label"
                        >
                            Jenis Kelamin
                        </label>

                        <select
                            id="jenis_kelamin"
                            name="jenis_kelamin"
                            class="form-select"
                            required
                        >
                            <option value="">
                                Pilih jenis kelamin
                            </option>

                            <option
                                value="Laki-laki"
                                <?= $jenisKelamin === "Laki-laki"
                                    ? "selected"
                                    : "" ?>
                            >
                                Laki-laki
                            </option>

                            <option
                                value="Perempuan"
                                <?= $jenisKelamin === "Perempuan"
                                    ? "selected"
                                    : "" ?>
                            >
                                Perempuan
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label
                            for="tanggal_lahir"
                            class="form-label"
                        >
                            Tanggal Lahir
                        </label>

                        <input
                            type="date"
                            id="tanggal_lahir"
                            name="tanggal_lahir"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $tanggalLahir,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            max="<?= date("Y-m-d") ?>"
                            required
                        >

                        <div class="form-text">
                            Umur dalam bulan akan dihitung ulang
                            secara otomatis.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label
                            for="nama_ibu"
                            class="form-label"
                        >
                            Nama Ibu
                        </label>

                        <input
                            type="text"
                            id="nama_ibu"
                            name="nama_ibu"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $namaIbu,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label
                            for="alamat"
                            class="form-label"
                        >
                            Alamat
                        </label>

                        <textarea
                            id="alamat"
                            name="alamat"
                            class="form-control"
                            rows="3"
                            required
                        ><?= htmlspecialchars(
                            $alamat,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label
                            for="wilayah_posyandu"
                            class="form-label"
                        >
                            Wilayah Posyandu
                        </label>

                        <input
                            type="text"
                            id="wilayah_posyandu"
                            name="wilayah_posyandu"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $wilayahPosyandu,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="d-flex flex-wrap gap-2">

                        <button
                            type="submit"
                            class="btn btn-warning"
                        >
                            Simpan Perubahan
                        </button>

                        <a
                            href="data_balita.php"
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