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
        id_puskesmas,
        nik_balita,
        nama_balita,
        jenis_kelamin,
        tanggal_lahir,
        umur,
        nama_ibu,
        alamat,
        nama_posyandu
     FROM balita
     WHERE id_balita = ?
     LIMIT 1"
);

if (!$stmtBalita) {
    die(
        "Terjadi kesalahan saat mengambil data balita: "
        . mysqli_error($conn)
    );
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

$idUser = (int) ($dataBalita["id_user"] ?? 0);
$idPuskesmas = (int) ($dataBalita["id_puskesmas"] ?? 0);

$nikBalita = $dataBalita["nik_balita"] ?? "";
$namaBalita = $dataBalita["nama_balita"] ?? "";
$jenisKelamin = $dataBalita["jenis_kelamin"] ?? "";
$tanggalLahir = $dataBalita["tanggal_lahir"] ?? "";
$namaIbu = $dataBalita["nama_ibu"] ?? "";
$alamat = $dataBalita["alamat"] ?? "";
$namaPosyandu = $dataBalita["nama_posyandu"] ?? "";

/*
|--------------------------------------------------------------------------
| Mengambil daftar akun orang tua
|--------------------------------------------------------------------------
*/

$queryOrangTua = mysqli_query(
    $conn,
    "SELECT
        id_user,
        nama,
        username
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

$jumlahPuskesmas = mysqli_num_rows($queryPuskesmas);

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

    $idPuskesmas = filter_input(
        INPUT_POST,
        "id_puskesmas",
        FILTER_VALIDATE_INT
    );

    $nikBalita = trim($_POST["nik_balita"] ?? "");
    $namaBalita = trim($_POST["nama_balita"] ?? "");
    $jenisKelamin = $_POST["jenis_kelamin"] ?? "";
    $tanggalLahir = $_POST["tanggal_lahir"] ?? "";
    $namaIbu = trim($_POST["nama_ibu"] ?? "");
    $alamat = trim($_POST["alamat"] ?? "");
    $namaPosyandu = trim($_POST["nama_posyandu"] ?? "");

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
        || !$idPuskesmas
        || $nikBalita === ""
        || $namaBalita === ""
        || $jenisKelamin === ""
        || $tanggalLahir === ""
        || $namaIbu === ""
        || $alamat === ""
        || $namaPosyandu === ""
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
        $pesanError =
            "Tanggal lahir tidak boleh melebihi hari ini.";
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
    | Memastikan Puskesmas tersedia
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

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

            $hasilPuskesmas = mysqli_stmt_get_result(
                $cekPuskesmas
            );

            if (mysqli_num_rows($hasilPuskesmas) === 0) {
                $pesanError =
                    "Puskesmas yang dipilih tidak ditemukan.";
            }

            mysqli_stmt_close($cekPuskesmas);
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
                id_puskesmas = ?,
                nik_balita = ?,
                nama_balita = ?,
                jenis_kelamin = ?,
                tanggal_lahir = ?,
                umur = ?,
                nama_ibu = ?,
                alamat = ?,
                nama_posyandu = ?
             WHERE id_balita = ?"
        );

        if (!$update) {

            $pesanError =
                "Terjadi kesalahan saat menyiapkan perubahan data: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $update,
                "iissssisssi",
                $idUser,
                $idPuskesmas,
                $nikBalita,
                $namaBalita,
                $jenisKelamin,
                $tanggalLahir,
                $umur,
                $namaIbu,
                $alamat,
                $namaPosyandu,
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

/*
|--------------------------------------------------------------------------
| Memanggil template aplikasi
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <div>

                <h1 class="page-title">

                    <i class="bi bi-pencil-square me-2"></i>

                    Edit Data Balita

                </h1>

                <p class="page-subtitle">

                    Perbarui profil balita, Posyandu, Puskesmas
                    pembina, dan akun orang tua yang terhubung.

                </p>

            </div>

            <div class="d-flex flex-wrap gap-2">

                <a
                    href="data_balita.php"
                    class="btn btn-secondary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Kembali

                </a>

            </div>

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
                ); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>

            </div>

        <?php endif; ?>

        <?php if ($jumlahPuskesmas === 0): ?>

            <div
                class="alert alert-warning"
                role="alert"
            >

                <strong>Data Puskesmas belum tersedia.</strong>

                Tambahkan data ke tabel
                <code>puskesmas</code>
                terlebih dahulu sebelum menyimpan perubahan.

            </div>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Form Edit Balita
                    </h4>

                    <small class="text-muted">
                        Data bertanda wajib harus dilengkapi.
                    </small>

                </div>

                <span class="badge badge-primary">

                    <i class="bi bi-person-heart"></i>

                    Data Balita

                </span>

            </div>

            <div class="card-body p-4">

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">

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
                                            $orangTua["id_user"]; ?>"
                                        <?= (int) $idUser ===
                                            (int) $orangTua["id_user"]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $orangTua["nama"]
                                            . " ("
                                            . $orangTua["username"]
                                            . ")",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                            <div class="form-text">
                                Akun harus memiliki role Orang Tua.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="id_puskesmas"
                                class="form-label"
                            >
                                Puskesmas Pembina
                            </label>

                            <select
                                id="id_puskesmas"
                                name="id_puskesmas"
                                class="form-select"
                                required
                                <?= $jumlahPuskesmas === 0
                                    ? "disabled"
                                    : ""; ?>
                            >

                                <option value="">
                                    Pilih Puskesmas
                                </option>

                                <?php while (
                                    $puskesmas = mysqli_fetch_assoc(
                                        $queryPuskesmas
                                    )
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $puskesmas["id_puskesmas"]; ?>"
                                        <?= (int) $idPuskesmas ===
                                            (int) $puskesmas["id_puskesmas"]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $puskesmas["nama_puskesmas"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                            <div class="form-text">
                                Pilihan diambil dari master Puskesmas.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

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
                                ); ?>"
                                minlength="16"
                                maxlength="16"
                                inputmode="numeric"
                                pattern="[0-9]{16}"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-6">

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
                                ); ?>"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-6">

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
                                        : ""; ?>
                                >
                                    Laki-laki
                                </option>

                                <option
                                    value="Perempuan"
                                    <?= $jenisKelamin === "Perempuan"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Perempuan
                                </option>

                            </select>

                        </div>

                        <div class="col-12 col-lg-6">

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
                                ); ?>"
                                max="<?= date("Y-m-d"); ?>"
                                required
                            >

                            <div class="form-text">
                                Umur dihitung ulang otomatis dalam bulan.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

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
                                ); ?>"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="nama_posyandu"
                                class="form-label"
                            >
                                Nama Posyandu
                            </label>

                            <input
                                type="text"
                                id="nama_posyandu"
                                name="nama_posyandu"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $namaPosyandu,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                maxlength="150"
                                placeholder="Contoh: Posyandu Melati"
                                required
                            >

                            <div class="form-text">
                                Nama Posyandu diisi secara manual.
                            </div>

                        </div>

                        <div class="col-12">

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
                            ); ?></textarea>

                        </div>

                        <div class="col-12">

                            <div class="d-flex flex-wrap gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    <?= $jumlahPuskesmas === 0
                                        ? "disabled"
                                        : ""; ?>
                                >

                                    <i class="bi bi-check-circle"></i>

                                    Simpan Perubahan

                                </button>

                                <a
                                    href="data_balita.php"
                                    class="btn btn-secondary"
                                >

                                    Batal

                                </a>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>