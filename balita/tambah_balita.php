<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

$judulHalaman = "Tambah Balita | Sistem Deteksi Stunting";

$pesanError = "";

$idUser = "";
$idPuskesmas = "";
$nikBalita = "";
$namaBalita = "";
$jenisKelamin = "";
$tanggalLahir = "";
$namaIbu = "";
$alamat = "";
$namaPosyandu = "";

/*
|--------------------------------------------------------------------------
| Mengambil master Puskesmas untuk dropdown
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
| Mengambil profil Ibu yang sudah lengkap
|--------------------------------------------------------------------------
|
| Kader hanya dapat memilih akun Orang Tua yang sudah memiliki profil
| pada tabel orang_tua. Nama ibu dan alamat tidak diketik ulang.
|
*/

$queryOrangTua = mysqli_query(
    $conn,
    "SELECT
        ot.id_orang_tua,
        ot.id_user,
        ot.nik_ibu,
        ot.nama_ibu,
        ot.no_hp,
        ot.alamat,
        ot.pendidikan_ibu,
        ot.pekerjaan_ibu,
        p.username
     FROM orang_tua AS ot
     INNER JOIN pengguna AS p
        ON ot.id_user = p.id_user
     WHERE p.role = 'orang_tua'
     ORDER BY ot.nama_ibu ASC"
);

if (!$queryOrangTua) {
    die(
        "Gagal mengambil profil Ibu: "
        . mysqli_error($conn)
    );
}

$jumlahOrangTua =
    mysqli_num_rows($queryOrangTua);

/*
|--------------------------------------------------------------------------
| Fungsi menghitung umur dalam bulan
|--------------------------------------------------------------------------
*/

function hitungUmurBulan(string $tanggalLahir): int
{
    try {
        $lahir = new DateTime($tanggalLahir);
        $hariIni = new DateTime("today");

        if ($lahir > $hariIni) {
            return -1;
        }

        $selisih = $lahir->diff($hariIni);

        return ((int) $selisih->y * 12) + (int) $selisih->m;
    } catch (Exception $e) {
        return -1;
    }
}

/*
|--------------------------------------------------------------------------
| Proses penyimpanan
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $idUser = trim($_POST["id_user"] ?? "");
    $idPuskesmas = trim($_POST["id_puskesmas"] ?? "");
    $nikBalita = preg_replace(
        "/\D/",
        "",
        trim($_POST["nik_balita"] ?? "")
    );
    $namaBalita = trim($_POST["nama_balita"] ?? "");
    $jenisKelamin = trim($_POST["jenis_kelamin"] ?? "");
    $tanggalLahir = trim($_POST["tanggal_lahir"] ?? "");
    $namaPosyandu = trim($_POST["nama_posyandu"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | Nama ibu dan alamat diambil dari profil Orang Tua
    |--------------------------------------------------------------------------
    */

    $namaIbu = "";
    $alamat = "";

    /*
    |--------------------------------------------------------------------------
    | Validasi dasar
    |--------------------------------------------------------------------------
    */

    if ($jumlahPuskesmas === 0) {
        $pesanError =
            "Master Puskesmas masih kosong. Isi data Puskesmas terlebih dahulu.";
    } elseif ($jumlahOrangTua === 0) {
        $pesanError =
            "Belum ada profil Ibu yang dapat dipilih. Orang Tua harus melengkapi profil terlebih dahulu.";
    } elseif ($idUser === "" || !ctype_digit($idUser)) {
        $pesanError =
            "Profil Ibu wajib dipilih.";
    } elseif ($idPuskesmas === "" || !ctype_digit($idPuskesmas)) {
        $pesanError = "Puskesmas wajib dipilih.";
    } elseif ($nikBalita === "") {
        $pesanError = "NIK balita wajib diisi.";
    } elseif (strlen($nikBalita) !== 16) {
        $pesanError = "NIK balita harus terdiri dari 16 digit angka.";
    } elseif ($namaBalita === "") {
        $pesanError = "Nama balita wajib diisi.";
    } elseif (!in_array(
        $jenisKelamin,
        ["Laki-laki", "Perempuan"],
        true
    )) {
        $pesanError = "Jenis kelamin tidak valid.";
    } elseif ($tanggalLahir === "") {
        $pesanError = "Tanggal lahir wajib diisi.";
    } elseif (hitungUmurBulan($tanggalLahir) < 0) {
        $pesanError = "Tanggal lahir tidak boleh melebihi tanggal hari ini.";
    } elseif ($namaPosyandu === "") {
        $pesanError = "Nama Posyandu wajib diisi.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan Puskesmas valid
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $idPuskesmasInt = (int) $idPuskesmas;

        $stmtPuskesmas = mysqli_prepare(
            $conn,
            "SELECT id_puskesmas
             FROM puskesmas
             WHERE id_puskesmas = ?
             LIMIT 1"
        );

        if (!$stmtPuskesmas) {
            $pesanError = "Gagal memeriksa data Puskesmas.";
        } else {
            mysqli_stmt_bind_param(
                $stmtPuskesmas,
                "i",
                $idPuskesmasInt
            );
            mysqli_stmt_execute($stmtPuskesmas);

            $hasilPuskesmas = mysqli_stmt_get_result(
                $stmtPuskesmas
            );

            if (mysqli_num_rows($hasilPuskesmas) === 0) {
                $pesanError = "Puskesmas yang dipilih tidak ditemukan.";
            }

            mysqli_stmt_close($stmtPuskesmas);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan profil Ibu valid dan mengambil data otomatis
    |--------------------------------------------------------------------------
    */

    $idUserSimpan = null;

    if ($pesanError === "") {

        $idUserInt =
            (int) $idUser;

        $stmtOrangTua = mysqli_prepare(
            $conn,
            "SELECT
                ot.id_user,
                ot.nama_ibu,
                ot.alamat
             FROM orang_tua AS ot
             INNER JOIN pengguna AS p
                ON ot.id_user = p.id_user
             WHERE ot.id_user = ?
             AND p.role = 'orang_tua'
             LIMIT 1"
        );

        if (!$stmtOrangTua) {

            $pesanError =
                "Gagal memeriksa profil Ibu.";

        } else {

            mysqli_stmt_bind_param(
                $stmtOrangTua,
                "i",
                $idUserInt
            );

            mysqli_stmt_execute(
                $stmtOrangTua
            );

            $hasilOrangTua =
                mysqli_stmt_get_result(
                    $stmtOrangTua
                );

            $profilIbu =
                mysqli_fetch_assoc(
                    $hasilOrangTua
                );

            if (!$profilIbu) {

                $pesanError =
                    "Profil Ibu tidak ditemukan atau belum lengkap.";

            } else {

                $idUserSimpan =
                    (int) $profilIbu[
                        "id_user"
                    ];

                $namaIbu =
                    trim(
                        (string) $profilIbu[
                            "nama_ibu"
                        ]
                    );

                $alamat =
                    trim(
                        (string) $profilIbu[
                            "alamat"
                        ]
                    );

                if (
                    $namaIbu === ""
                    || $alamat === ""
                ) {
                    $pesanError =
                        "Profil Ibu belum lengkap. Lengkapi nama dan alamat terlebih dahulu.";
                }
            }

            mysqli_stmt_close(
                $stmtOrangTua
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cek NIK duplikat
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $stmtNik = mysqli_prepare(
            $conn,
            "SELECT id_balita
             FROM balita
             WHERE nik_balita = ?
             LIMIT 1"
        );

        if (!$stmtNik) {
            $pesanError = "Gagal memeriksa NIK balita.";
        } else {
            mysqli_stmt_bind_param(
                $stmtNik,
                "s",
                $nikBalita
            );
            mysqli_stmt_execute($stmtNik);

            $hasilNik = mysqli_stmt_get_result($stmtNik);

            if (mysqli_num_rows($hasilNik) > 0) {
                $pesanError =
                    "NIK balita sudah terdaftar dalam sistem.";
            }

            mysqli_stmt_close($stmtNik);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan ke tabel balita
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $idPuskesmasInt = (int) $idPuskesmas;
        $umur = hitungUmurBulan($tanggalLahir);

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO balita
            (
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
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmtSimpan) {
            $pesanError =
                "Gagal menyiapkan penyimpanan data balita: "
                . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmtSimpan,
                "iissssisss",
                $idUserSimpan,
                $idPuskesmasInt,
                $nikBalita,
                $namaBalita,
                $jenisKelamin,
                $tanggalLahir,
                $umur,
                $namaIbu,
                $alamat,
                $namaPosyandu
            );

            if (mysqli_stmt_execute($stmtSimpan)) {
                mysqli_stmt_close($stmtSimpan);

                header(
                    "Location: data_balita.php?pesan=tambah_berhasil"
                );
                exit;
            }

            if (mysqli_stmt_errno($stmtSimpan) === 1062) {
                $pesanError =
                    "NIK balita sudah terdaftar dalam sistem.";
            } else {
                $pesanError =
                    "Data balita gagal disimpan: "
                    . mysqli_stmt_error($stmtSimpan);
            }

            mysqli_stmt_close($stmtSimpan);
        }
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php if ($pesanError !== ""): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-exclamation-circle me-1"></i>

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

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Belum ada data pada master Puskesmas. Dropdown Puskesmas
                belum dapat digunakan sampai tabel
                <strong>puskesmas</strong> diisi.
            </div>

        <?php endif; ?>

        <?php if ($jumlahOrangTua === 0): ?>

            <div class="alert alert-warning">
                <i class="bi bi-person-exclamation me-1"></i>
                Belum ada profil Ibu yang dapat dipilih.
                Orang Tua harus login dan melengkapi
                <strong>Profil Ibu</strong> terlebih dahulu.
            </div>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-header">

                <div>
                    <h4 class="mb-1">
                        Tambah Data Balita
                    </h4>

                    <small class="text-muted">
                        Pilih profil Ibu yang sudah terdaftar, lalu
                        lengkapi identitas balita dan fasilitas pembina.
                    </small>
                </div>

                <a
                    href="data_balita.php"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

            </div>

            <div class="card-body">

                <form method="POST" autocomplete="off">

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">
                            <label
                                for="nik_balita"
                                class="form-label"
                            >
                                NIK Balita
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="nik_balita"
                                name="nik_balita"
                                class="form-control"
                                maxlength="16"
                                inputmode="numeric"
                                pattern="[0-9]{16}"
                                placeholder="Masukkan 16 digit NIK"
                                value="<?= htmlspecialchars(
                                    $nikBalita,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-lg-6">
                            <label
                                for="nama_balita"
                                class="form-label"
                            >
                                Nama Balita
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="nama_balita"
                                name="nama_balita"
                                class="form-control"
                                maxlength="50"
                                placeholder="Masukkan nama balita"
                                value="<?= htmlspecialchars(
                                    $namaBalita,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-lg-6">
                            <label
                                for="jenis_kelamin"
                                class="form-label"
                            >
                                Jenis Kelamin
                                <span class="text-danger">*</span>
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
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                id="tanggal_lahir"
                                name="tanggal_lahir"
                                class="form-control"
                                max="<?= date("Y-m-d"); ?>"
                                value="<?= htmlspecialchars(
                                    $tanggalLahir,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                required
                            >
                        </div>

                        <div class="col-12">

                            <label
                                for="id_user"
                                class="form-label"
                            >
                                Pilih Ibu / Orang Tua
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                id="id_user"
                                name="id_user"
                                class="form-select"
                                <?= $jumlahOrangTua === 0
                                    ? "disabled"
                                    : "required"; ?>
                            >
                                <option value="">
                                    Pilih profil Ibu
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
                                            $orangTua[
                                                "id_user"
                                            ]; ?>"
                                        <?= (string) $idUser ===
                                            (string) $orangTua[
                                                "id_user"
                                            ]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $orangTua[
                                                "nama_ibu"
                                            ]
                                            . " — NIK "
                                            . $orangTua[
                                                "nik_ibu"
                                            ]
                                            . " ("
                                            . $orangTua[
                                                "username"
                                            ]
                                            . ")",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                            <small class="text-muted">
                                Nama Ibu dan alamat diambil otomatis
                                dari Profil Ibu. Kader tidak perlu
                                menginput ulang data Orang Tua.
                            </small>

                        </div>

                        <div class="col-12 col-lg-6">
                            <label
                                for="nama_posyandu"
                                class="form-label"
                            >
                                Nama Posyandu
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="nama_posyandu"
                                name="nama_posyandu"
                                class="form-control"
                                maxlength="100"
                                placeholder="Contoh: Posyandu Melati"
                                value="<?= htmlspecialchars(
                                    $namaPosyandu,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                required
                            >

                            <small class="text-muted">
                                Nama Posyandu diisi secara manual.
                            </small>
                        </div>

                        <div class="col-12 col-lg-6">
                            <label
                                for="id_puskesmas"
                                class="form-label"
                            >
                                Puskesmas
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                id="id_puskesmas"
                                name="id_puskesmas"
                                class="form-select"
                                <?= $jumlahPuskesmas === 0
                                    ? "disabled"
                                    : "required"; ?>
                            >
                                <option value="">
                                    Pilih Puskesmas
                                </option>

                                <?php
                                mysqli_data_seek($queryPuskesmas, 0);
                                ?>

                                <?php while (
                                    $puskesmas = mysqli_fetch_assoc(
                                        $queryPuskesmas
                                    )
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $puskesmas["id_puskesmas"]; ?>"
                                        <?= (string) $idPuskesmas ===
                                            (string) $puskesmas["id_puskesmas"]
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

                            <small class="text-muted">
                            </small>
                        </div>

                        <div class="col-12">

                            <div class="alert alert-info mb-0">

                                <i class="bi bi-info-circle me-1"></i>

                                <strong>Data Ibu tidak diinput ulang.</strong>
                                Nama Ibu dan alamat akan otomatis
                                menggunakan data dari Profil Ibu yang
                                dipilih di atas.

                            </div>

                        </div>

                    </div>

                    <div
                        class="d-flex flex-wrap justify-content-end gap-2 mt-4"
                    >
                        <a
                            href="data_balita.php"
                            class="btn btn-light"
                        >
                            Batal
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= (
                                $jumlahPuskesmas === 0
                                || $jumlahOrangTua === 0
                            )
                                ? "disabled"
                                : ""; ?>
                        >
                            <i class="bi bi-save"></i>
                            Simpan Data Balita
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>