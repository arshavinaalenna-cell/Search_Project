<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

$judulHalaman = "Tambah Data Balita | Sistem Deteksi Stunting";

$pesanError = "";

$idUser = "";
$nikBalita = "";
$namaBalita = "";
$jenisKelamin = "";
$tanggalLahir = "";
$namaIbu = "";
$alamat = "";
$wilayahPosyandu = "";

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
| Memproses penyimpanan balita
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
    } elseif (
        !preg_match("/^[0-9]{16}$/", $nikBalita)
    ) {
        $pesanError = "NIK balita harus terdiri dari 16 angka.";
    } elseif (
        !in_array(
            $jenisKelamin,
            $jenisKelaminDiizinkan,
            true
        )
    ) {
        $pesanError = "Jenis kelamin tidak valid.";
    } elseif (
        strtotime($tanggalLahir) === false
    ) {
        $pesanError = "Tanggal lahir tidak valid.";
    } elseif (
        strtotime($tanggalLahir) > time()
    ) {
        $pesanError = "Tanggal lahir tidak boleh melebihi hari ini.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan ID tersebut benar-benar akun orang tua
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

            if (
                mysqli_num_rows($hasilOrangTua) === 0
            ) {
                $pesanError =
                    "Akun orang tua tidak ditemukan.";
            }

            mysqli_stmt_close($cekOrangTua);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan NIK balita belum digunakan
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $cekNik = mysqli_prepare(
            $conn,
            "SELECT id_balita
             FROM balita
             WHERE nik_balita = ?
             LIMIT 1"
        );

        if (!$cekNik) {
            $pesanError =
                "Terjadi kesalahan saat memeriksa NIK balita.";
        } else {
            mysqli_stmt_bind_param(
                $cekNik,
                "s",
                $nikBalita
            );

            mysqli_stmt_execute($cekNik);

            $hasilNik = mysqli_stmt_get_result($cekNik);

            if (mysqli_num_rows($hasilNik) > 0) {
                $pesanError =
                    "NIK balita sudah terdaftar.";
            }

            mysqli_stmt_close($cekNik);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menghitung umur dalam bulan
    |--------------------------------------------------------------------------
    */

    $umur = 0;

    if ($pesanError === "") {
        try {
            $tanggalLahirObjek = new DateTime(
                $tanggalLahir
            );

            $tanggalHariIni = new DateTime();

            $selisih = $tanggalHariIni->diff(
                $tanggalLahirObjek
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
    | Menyimpan data balita
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $simpan = mysqli_prepare(
            $conn,
            "INSERT INTO balita (
                id_user,
                nik_balita,
                nama_balita,
                jenis_kelamin,
                tanggal_lahir,
                umur,
                nama_ibu,
                alamat,
                wilayah_posyandu
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$simpan) {
            $pesanError =
                "Terjadi kesalahan saat menyiapkan penyimpanan data.";
        } else {
            mysqli_stmt_bind_param(
                $simpan,
                "issssisss",
                $idUser,
                $nikBalita,
                $namaBalita,
                $jenisKelamin,
                $tanggalLahir,
                $umur,
                $namaIbu,
                $alamat,
                $wilayahPosyandu
            );

            if (mysqli_stmt_execute($simpan)) {
                mysqli_stmt_close($simpan);

                header(
                    "Location: data_balita.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data balita gagal disimpan: "
                . mysqli_stmt_error($simpan);

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

        <div
            class="d-flex flex-column flex-md-row
            justify-content-between align-items-md-center
            gap-3 mb-4"
        >
            <div>
                <h2 class="mb-1">
                    Tambah Data Balita
                </h2>

                <p class="text-muted mb-0">
                    Daftarkan profil balita dan hubungkan
                    dengan akun orang tua.
                </p>
            </div>

            <button
type="button"
class="btn btn-success w-auto"
onclick="history.back()">

<i class="bi bi-arrow-left"></i>
Kembali

</button>
        </div>

        <?php if ($pesanError !== ""): ?>

            <div
                class="alert alert-danger
                alert-dismissible fade show"
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

        <?php if (
            mysqli_num_rows($queryOrangTua) === 0
        ): ?>

            <div class="alert alert-warning">
                Belum ada akun dengan role Orang Tua.
                Buat akun Orang Tua terlebih dahulu melalui
                menu Data Pengguna.
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

                            <?php
                            mysqli_data_seek(
                                $queryOrangTua,
                                0
                            );
                            ?>

                            <?php while (
                                $orangTua =
                                    mysqli_fetch_assoc(
                                        $queryOrangTua
                                    )
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $orangTua["id_user"] ?>"
                                    <?= (int) $idUser
                                        ===
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
                            Sistem akan menyimpan ID akun orang
                            tua secara otomatis.
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
                                <?= $jenisKelamin
                                    === "Laki-laki"
                                    ? "selected"
                                    : "" ?>
                            >
                                Laki-laki
                            </option>

                            <option
                                value="Perempuan"
                                <?= $jenisKelamin
                                    === "Perempuan"
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
                            Umur balita dalam bulan akan
                            dihitung otomatis.
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
                            class="btn btn-success"
                            <?= mysqli_num_rows(
                                $queryOrangTua
                            ) === 0
                                ? "disabled"
                                : "" ?>
                        >
                            Simpan Data Balita
                        </button>

                        <a
                            href="data_balita.php"
                             class="btn btn-success"
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