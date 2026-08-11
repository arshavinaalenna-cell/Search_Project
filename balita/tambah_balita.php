<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

$judulHalaman = "Tambah Balita | Sistem Deteksi Stunting";

$pesanError = "";

$idUser = "";
$idPuskesmas = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;
$nikBalita = "";
$namaBalita = "";
$jenisKelamin = "";
$tanggalLahir = "";
$namaIbu = "";
$alamat = "";
$namaPosyandu = "";

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Kader aktif
|--------------------------------------------------------------------------
|
| Puskesmas balita tidak lagi dipilih manual. Setiap balita yang ditambahkan
| oleh Kader otomatis mengikuti Puskesmas yang terhubung ke akun Kader.
|
*/

$idKaderAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$stmtPuskesmasAktif = mysqli_prepare(
    $conn,
    "SELECT
        u.id_puskesmas,
        ps.nama_puskesmas
     FROM pengguna AS u
     LEFT JOIN puskesmas AS ps
        ON u.id_puskesmas = ps.id_puskesmas
     WHERE u.id_user = ?
     AND u.role = 'kader'
     LIMIT 1"
);

if (!$stmtPuskesmasAktif) {
    die(
        "Gagal memeriksa Puskesmas Kader: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPuskesmasAktif,
    "i",
    $idKaderAktif
);

mysqli_stmt_execute(
    $stmtPuskesmasAktif
);

$hasilPuskesmasAktif =
    mysqli_stmt_get_result(
        $stmtPuskesmasAktif
    );

$dataPuskesmasAktif =
    mysqli_fetch_assoc(
        $hasilPuskesmasAktif
    );

mysqli_stmt_close(
    $stmtPuskesmasAktif
);

if (
    !$dataPuskesmasAktif
    || empty(
        $dataPuskesmasAktif[
            "id_puskesmas"
        ]
    )
    || empty(
        $dataPuskesmasAktif[
            "nama_puskesmas"
        ]
    )
) {
    $puskesmasBelumTerhubung = true;
} else {
    $idPuskesmas =
        (int) $dataPuskesmasAktif[
            "id_puskesmas"
        ];

    $namaPuskesmasAktif =
        trim(
            (string) $dataPuskesmasAktif[
                "nama_puskesmas"
            ]
        );
}

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

    if ($puskesmasBelumTerhubung) {
        $pesanError =
            "Akun Kader belum terhubung dengan Puskesmas. Hubungkan akun Kader ke Puskesmas terlebih dahulu.";
    } elseif ($jumlahOrangTua === 0) {
        $pesanError =
            "Belum ada profil Ibu yang dapat dipilih. Orang Tua harus melengkapi profil terlebih dahulu.";
    } elseif ($idUser === "" || !ctype_digit($idUser)) {
        $pesanError =
            "Profil Ibu wajib dipilih.";
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
    | Memastikan Puskesmas tetap mengikuti akun Kader
    |--------------------------------------------------------------------------
    */

    if (
        $pesanError === ""
        && $idPuskesmas < 1
    ) {
        $pesanError =
            "Puskesmas akun Kader tidak valid.";
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
        $idPuskesmasInt = $idPuskesmas;
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

        <?php if ($puskesmasBelumTerhubung): ?>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Akun Kader belum terhubung dengan Puskesmas.
                Hubungkan akun Kader melalui menu
                <strong>Data Pengguna</strong>
                sebelum menambahkan balita.
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
                        lengkapi identitas balita. Puskesmas mengikuti
                        akun Kader secara otomatis.
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

                            <?php
                            /*
                            |--------------------------------------------------------------------------
                            | Menentukan label Ibu yang sedang terpilih
                            |--------------------------------------------------------------------------
                            */

                            $labelIbuTerpilih = "Pilih profil Ibu";

                            if ($jumlahOrangTua > 0) {

                                mysqli_data_seek(
                                    $queryOrangTua,
                                    0
                                );

                                while (
                                    $ibuPilihan =
                                        mysqli_fetch_assoc(
                                            $queryOrangTua
                                        )
                                ) {

                                    if (
                                        (string) $idUser ===
                                        (string) $ibuPilihan["id_user"]
                                    ) {
                                        $labelIbuTerpilih =
                                            $ibuPilihan["nama_ibu"]
                                            . " — NIK "
                                            . $ibuPilihan["nik_ibu"]
                                            . " ("
                                            . $ibuPilihan["username"]
                                            . ")";

                                        break;
                                    }
                                }
                            }
                            ?>

                            <div
                                class="ibu-search-select"
                                id="ibuSearchSelect"
                            >

                                <input
                                    type="hidden"
                                    id="id_user"
                                    name="id_user"
                                    value="<?= htmlspecialchars(
                                        (string) $idUser,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                >

                                <button
                                    type="button"
                                    class="form-select text-start ibu-search-trigger"
                                    id="ibuSearchTrigger"
                                    <?= $jumlahOrangTua === 0
                                        ? "disabled"
                                        : ""; ?>
                                >
                                    <span id="ibuSearchSelected">
                                        <?= htmlspecialchars(
                                            $labelIbuTerpilih,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </span>
                                </button>

                                <div
                                    class="ibu-search-panel"
                                    id="ibuSearchPanel"
                                    hidden
                                >

                                    <div class="p-2 border-bottom">

                                        <div class="input-group">

                                            <span class="input-group-text">
                                                <i class="bi bi-search"></i>
                                            </span>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="ibuSearchInput"
                                                placeholder="Cari nama ibu, NIK, atau username..."
                                                autocomplete="off"
                                            >

                                        </div>

                                    </div>

                                    <div
                                        class="ibu-search-list"
                                        id="ibuSearchList"
                                    >

                                        <?php
                                        if ($jumlahOrangTua > 0) {

                                            mysqli_data_seek(
                                                $queryOrangTua,
                                                0
                                            );
                                        }
                                        ?>

                                        <?php while (
                                            $orangTua =
                                                mysqli_fetch_assoc(
                                                    $queryOrangTua
                                                )
                                        ): ?>

                                            <?php
                                            $labelIbu =
                                                $orangTua["nama_ibu"]
                                                . " — NIK "
                                                . $orangTua["nik_ibu"]
                                                . " ("
                                                . $orangTua["username"]
                                                . ")";
                                            ?>

                                            <button
                                                type="button"
                                                class="ibu-search-option"
                                                data-value="<?= (int)
                                                    $orangTua[
                                                        "id_user"
                                                    ]; ?>"
                                                data-search="<?= htmlspecialchars(
                                                    strtolower(
                                                        $orangTua["nama_ibu"]
                                                        . " "
                                                        . $orangTua["nik_ibu"]
                                                        . " "
                                                        . $orangTua["username"]
                                                    ),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>"
                                                data-label="<?= htmlspecialchars(
                                                    $labelIbu,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>"
                                            >
                                                <?= htmlspecialchars(
                                                    $labelIbu,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </button>

                                        <?php endwhile; ?>

                                        <div
                                            class="ibu-search-empty text-muted text-center p-3"
                                            id="ibuSearchEmpty"
                                            hidden
                                        >
                                            Data Ibu tidak ditemukan.
                                        </div>

                                    </div>

                                </div>

                            </div>

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

                            <label class="form-label">
                                Puskesmas
                            </label>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Puskesmas Kader
                                </span>

                                <div class="detail-value">

                                    <?php if (
                                        !$puskesmasBelumTerhubung
                                    ): ?>

                                        <i class="bi bi-hospital me-1"></i>

                                        <?= htmlspecialchars(
                                            $namaPuskesmasAktif,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>

                                    <?php else: ?>

                                        <span
                                            class="badge
                                            bg-warning text-dark"
                                        >
                                            Belum Terhubung
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                            <small class="text-muted">
                                Puskesmas ditentukan otomatis
                                berdasarkan akun Kader dan tidak
                                dapat diubah dari form ini.
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
                                $puskesmasBelumTerhubung
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


<style>
.ibu-search-select {
    position: relative;
}

.ibu-search-trigger {
    min-height: 46px;
}

.ibu-search-panel {
    position: absolute;
    z-index: 2000;
    left: 0;
    right: 0;
    top: calc(100% + 6px);
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    box-shadow: 0 14px 32px rgba(30, 41, 59, 0.16);
    overflow: hidden;
}

.ibu-search-list {
    max-height: 260px;
    overflow-y: auto;
    padding: 6px;
}

.ibu-search-option {
    display: block;
    width: 100%;
    padding: 10px 12px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #334155;
    text-align: left;
    cursor: pointer;
}

.ibu-search-option:hover,
.ibu-search-option:focus {
    background: #f4f7fb;
    outline: none;
}

.ibu-search-option.is-selected {
    background: #eef4ff;
    font-weight: 700;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const wrapper =
        document.getElementById("ibuSearchSelect");

    const trigger =
        document.getElementById("ibuSearchTrigger");

    const panel =
        document.getElementById("ibuSearchPanel");

    const searchInput =
        document.getElementById("ibuSearchInput");

    const hiddenInput =
        document.getElementById("id_user");

    const selectedText =
        document.getElementById("ibuSearchSelected");

    const emptyState =
        document.getElementById("ibuSearchEmpty");

    if (
        !wrapper
        || !trigger
        || !panel
        || !searchInput
        || !hiddenInput
        || !selectedText
    ) {
        return;
    }

    const options = Array.from(
        wrapper.querySelectorAll(
            ".ibu-search-option"
        )
    );

    function normalisasi(teks) {
        return String(teks || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function bukaPanel() {
        if (trigger.disabled) {
            return;
        }

        panel.hidden = false;
        searchInput.value = "";

        options.forEach(function (option) {
            option.hidden = false;
        });

        if (emptyState) {
            emptyState.hidden = true;
        }

        setTimeout(function () {
            searchInput.focus();
        }, 0);
    }

    function tutupPanel() {
        panel.hidden = true;
    }

    trigger.addEventListener(
        "click",
        function () {
            if (panel.hidden) {
                bukaPanel();
            } else {
                tutupPanel();
            }
        }
    );

    searchInput.addEventListener(
        "input",
        function () {

            const keyword =
                normalisasi(
                    searchInput.value
                );

            let jumlahTampil = 0;

            options.forEach(function (option) {

                const dataSearch =
                    normalisasi(
                        option.dataset.search
                    );

                const cocok =
                    keyword === ""
                    || dataSearch.includes(
                        keyword
                    );

                option.hidden = !cocok;

                if (cocok) {
                    jumlahTampil++;
                }
            });

            if (emptyState) {
                emptyState.hidden =
                    jumlahTampil > 0;
            }
        }
    );

    options.forEach(function (option) {

        option.addEventListener(
            "click",
            function () {

                hiddenInput.value =
                    option.dataset.value;

                selectedText.textContent =
                    option.dataset.label;

                options.forEach(function (item) {
                    item.classList.remove(
                        "is-selected"
                    );
                });

                option.classList.add(
                    "is-selected"
                );

                tutupPanel();
            }
        );

        if (
            option.dataset.value
            === hiddenInput.value
        ) {
            option.classList.add(
                "is-selected"
            );
        }
    });

    document.addEventListener(
        "click",
        function (event) {

            if (
                !wrapper.contains(
                    event.target
                )
            ) {
                tutupPanel();
            }
        }
    );
});
</script>

<?php require_once "../includes/footer.php"; ?>