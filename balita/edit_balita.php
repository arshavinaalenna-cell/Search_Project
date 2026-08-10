<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

$judulHalaman = "Edit Data Balita | Sistem Deteksi Stunting";
$pesanError = "";

$idKaderAktif =
    (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Kader aktif
|--------------------------------------------------------------------------
|
| Kader hanya boleh mengedit balita dari Puskesmas yang sama
| dengan akun Kader.
|
*/

$stmtKader = mysqli_prepare(
    $conn,
    "SELECT
        u.id_puskesmas,
        p.nama_puskesmas
     FROM pengguna AS u
     LEFT JOIN puskesmas AS p
        ON u.id_puskesmas = p.id_puskesmas
     WHERE u.id_user = ?
     AND u.role = 'kader'
     LIMIT 1"
);

if (!$stmtKader) {
    die(
        "Gagal memeriksa Puskesmas Kader: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtKader,
    "i",
    $idKaderAktif
);

mysqli_stmt_execute($stmtKader);

$hasilKader =
    mysqli_stmt_get_result(
        $stmtKader
    );

$dataKader =
    mysqli_fetch_assoc(
        $hasilKader
    );

mysqli_stmt_close($stmtKader);

$idPuskesmasKader =
    !empty($dataKader["id_puskesmas"])
        ? (int) $dataKader["id_puskesmas"]
        : 0;

$namaPuskesmasKader =
    trim(
        (string) (
            $dataKader["nama_puskesmas"]
            ?? ""
        )
    );

if (
    $idPuskesmasKader < 1
    || $namaPuskesmasKader === ""
) {
    http_response_code(403);

    echo "
        <h2>Akses Ditolak</h2>
        <p>Akun Kader belum terhubung dengan Puskesmas.</p>
        <a href='data_balita.php'>Kembali</a>
    ";

    exit;
}

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
    header(
        "Location: data_balita.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data balita
|--------------------------------------------------------------------------
|
| Balita harus berada pada Puskesmas yang sama dengan akun Kader.
|
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
     AND id_puskesmas = ?
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
    "ii",
    $idBalita,
    $idPuskesmasKader
);

mysqli_stmt_execute($stmtBalita);

$hasilBalita =
    mysqli_stmt_get_result(
        $stmtBalita
    );

$dataBalita =
    mysqli_fetch_assoc(
        $hasilBalita
    );

mysqli_stmt_close($stmtBalita);

if (!$dataBalita) {
    header(
        "Location: data_balita.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$idUser =
    (int) (
        $dataBalita["id_user"]
        ?? 0
    );

$idPuskesmas =
    (int) (
        $dataBalita["id_puskesmas"]
        ?? 0
    );

$nikBalita =
    $dataBalita["nik_balita"]
    ?? "";

$namaBalita =
    $dataBalita["nama_balita"]
    ?? "";

$jenisKelamin =
    $dataBalita["jenis_kelamin"]
    ?? "";

$tanggalLahir =
    $dataBalita["tanggal_lahir"]
    ?? "";

$namaIbu =
    $dataBalita["nama_ibu"]
    ?? "";

$alamat =
    $dataBalita["alamat"]
    ?? "";

$namaPosyandu =
    $dataBalita["nama_posyandu"]
    ?? "";

/*
|--------------------------------------------------------------------------
| Mengambil Profil Ibu pemilik balita
|--------------------------------------------------------------------------
|
| Pemilik balita dikunci. id_user tidak dapat diubah dari halaman edit.
| Nama ibu dan alamat selalu disegarkan dari Profil Ibu pemilik yang sama.
|
*/

$stmtOrangTua = mysqli_prepare(
    $conn,
    "SELECT
        ot.id_user,
        ot.nik_ibu,
        ot.nama_ibu,
        ot.alamat,
        p.username
     FROM orang_tua AS ot
     INNER JOIN pengguna AS p
        ON ot.id_user = p.id_user
     WHERE ot.id_user = ?
     AND p.role = 'orang_tua'
     LIMIT 1"
);

if (!$stmtOrangTua) {
    die(
        "Gagal mengambil Profil Ibu: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtOrangTua,
    "i",
    $idUser
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

mysqli_stmt_close(
    $stmtOrangTua
);

$profilIbuTersedia =
    $profilIbu !== null;

if ($profilIbuTersedia) {

    $namaIbuProfil =
        trim(
            (string) (
                $profilIbu["nama_ibu"]
                ?? ""
            )
        );

    $alamatProfil =
        trim(
            (string) (
                $profilIbu["alamat"]
                ?? ""
            )
        );

} else {

    $namaIbuProfil = "";
    $alamatProfil = "";
}

/*
|--------------------------------------------------------------------------
| Memproses perubahan data
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    | id_user dan id_puskesmas sengaja tidak dibaca dari POST.
    | Keduanya dikunci berdasarkan data yang sudah tersimpan.
    */

    $nikBalita =
        preg_replace(
            "/\D/",
            "",
            trim(
                $_POST["nik_balita"]
                ?? ""
            )
        );

    $namaBalita =
        trim(
            $_POST["nama_balita"]
            ?? ""
        );

    $jenisKelamin =
        $_POST["jenis_kelamin"]
        ?? "";

    $tanggalLahir =
        $_POST["tanggal_lahir"]
        ?? "";

    $namaPosyandu =
        trim(
            $_POST["nama_posyandu"]
            ?? ""
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

    if (!$profilIbuTersedia) {

        $pesanError =
            "Profil Ibu pemilik balita tidak ditemukan.";

    } elseif (
        $namaIbuProfil === ""
        || $alamatProfil === ""
    ) {

        $pesanError =
            "Profil Ibu belum lengkap. Lengkapi nama dan alamat terlebih dahulu.";

    } elseif (
        $nikBalita === ""
        || $namaBalita === ""
        || $jenisKelamin === ""
        || $tanggalLahir === ""
        || $namaPosyandu === ""
    ) {

        $pesanError =
            "Semua data wajib diisi.";

    } elseif (
        !preg_match(
            "/^[0-9]{16}$/",
            $nikBalita
        )
    ) {

        $pesanError =
            "NIK balita harus terdiri dari 16 angka.";

    } elseif (
        !in_array(
            $jenisKelamin,
            $jenisKelaminDiizinkan,
            true
        )
    ) {

        $pesanError =
            "Jenis kelamin tidak valid.";

    } elseif (
        strtotime(
            $tanggalLahir
        ) === false
    ) {

        $pesanError =
            "Tanggal lahir tidak valid.";

    } elseif (
        strtotime(
            $tanggalLahir
        ) > time()
    ) {

        $pesanError =
            "Tanggal lahir tidak boleh melebihi hari ini.";
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

            mysqli_stmt_execute(
                $cekNik
            );

            $hasilNik =
                mysqli_stmt_get_result(
                    $cekNik
                );

            if (
                mysqli_num_rows(
                    $hasilNik
                ) > 0
            ) {
                $pesanError =
                    "NIK balita sudah digunakan oleh data lain.";
            }

            mysqli_stmt_close(
                $cekNik
            );
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

            $tanggalLahirObjek =
                new DateTime(
                    $tanggalLahir
                );

            $tanggalHariIni =
                new DateTime(
                    "today"
                );

            $selisih =
                $tanggalLahirObjek->diff(
                    $tanggalHariIni
                );

            $umur = (
                $selisih->y * 12
            ) + $selisih->m;

        } catch (
            Exception $exception
        ) {

            $pesanError =
                "Tanggal lahir tidak dapat diproses.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memperbarui data balita
    |--------------------------------------------------------------------------
    |
    | id_user dan id_puskesmas tidak diubah.
    | Nama ibu dan alamat disinkronkan dari Profil Ibu pemilik balita.
    |
    */

    if ($pesanError === "") {

        $namaIbu =
            $namaIbuProfil;

        $alamat =
            $alamatProfil;

        $update = mysqli_prepare(
            $conn,
            "UPDATE balita
             SET
                nik_balita = ?,
                nama_balita = ?,
                jenis_kelamin = ?,
                tanggal_lahir = ?,
                umur = ?,
                nama_ibu = ?,
                alamat = ?,
                nama_posyandu = ?
             WHERE id_balita = ?
             AND id_user = ?
             AND id_puskesmas = ?"
        );

        if (!$update) {

            $pesanError =
                "Terjadi kesalahan saat menyiapkan perubahan data: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $update,
                "ssssisssiii",
                $nikBalita,
                $namaBalita,
                $jenisKelamin,
                $tanggalLahir,
                $umur,
                $namaIbu,
                $alamat,
                $namaPosyandu,
                $idBalita,
                $idUser,
                $idPuskesmasKader
            );

            if (
                mysqli_stmt_execute(
                    $update
                )
            ) {

                mysqli_stmt_close(
                    $update
                );

                header(
                    "Location: data_balita.php?pesan=edit_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data balita gagal diperbarui: "
                . mysqli_stmt_error(
                    $update
                );

            mysqli_stmt_close(
                $update
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
                        Edit Data Balita
                    </h4>

                    <small class="text-muted">
                        Perbarui identitas balita.
                        Pemilik dan Puskesmas dikunci
                        agar relasi data tetap aman.
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

                <?php if (
                    $pesanError !== ""
                ): ?>

                    <div
                        class="alert alert-danger
                        alert-dismissible fade show"
                        role="alert"
                    >

                        <i
                            class="bi
                            bi-exclamation-circle me-1"
                        ></i>

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

                <?php if (
                    !$profilIbuTersedia
                ): ?>

                    <div class="alert alert-warning">

                        <i
                            class="bi
                            bi-person-exclamation me-1"
                        ></i>

                        Profil Ibu pemilik balita
                        tidak ditemukan. Data balita
                        tidak dapat diperbarui sampai
                        profil Orang Tua tersedia.

                    </div>

                <?php endif; ?>

                <form
                    method="POST"
                    autocomplete="off"
                >

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">

                            <label class="form-label">
                                Profil Ibu / Orang Tua
                            </label>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Pemilik Balita
                                </span>

                                <div class="detail-value">

                                    <?php if (
                                        $profilIbuTersedia
                                    ): ?>

                                        <i
                                            class="bi
                                            bi-person-heart me-1"
                                        ></i>

                                        <?= htmlspecialchars(
                                            $profilIbu[
                                                "nama_ibu"
                                            ] ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>

                                    <?php else: ?>

                                        <span
                                            class="badge
                                            bg-warning text-dark"
                                        >
                                            Profil Tidak Ditemukan
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <?php if (
                                    $profilIbuTersedia
                                ): ?>

                                    <small class="text-muted">

                                        NIK:
                                        <?= htmlspecialchars(
                                            $profilIbu[
                                                "nik_ibu"
                                            ] ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>

                                        <?php if (
                                            !empty(
                                                $profilIbu[
                                                    "username"
                                                ]
                                            )
                                        ): ?>

                                            · Username:
                                            <?= htmlspecialchars(
                                                $profilIbu[
                                                    "username"
                                                ],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>

                                        <?php endif; ?>

                                    </small>

                                <?php endif; ?>

                            </div>

                            <div class="form-text">
                                Pemilik balita tidak dapat
                                diganti dari halaman edit.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label class="form-label">
                                Puskesmas Pembina
                            </label>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Puskesmas Kader
                                </span>

                                <div class="detail-value">

                                    <i
                                        class="bi
                                        bi-hospital me-1"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $namaPuskesmasKader,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>

                                </div>

                            </div>

                            <div class="form-text">
                                Puskesmas mengikuti wilayah
                                akun Kader dan tidak dapat
                                dipindahkan dari halaman ini.
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
                                    <?= $jenisKelamin ===
                                        "Laki-laki"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Laki-laki
                                </option>

                                <option
                                    value="Perempuan"
                                    <?= $jenisKelamin ===
                                        "Perempuan"
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
                                Umur dihitung ulang
                                otomatis dalam bulan.
                            </div>

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
                                Nama Posyandu diisi manual.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <div
                                class="alert alert-info
                                h-100 mb-0"
                            >

                                <i
                                    class="bi
                                    bi-info-circle me-1"
                                ></i>

                                <strong>
                                    Data Ibu otomatis.
                                </strong>

                                Nama Ibu dan alamat mengikuti
                                Profil Ibu pemilik balita yang
                                sudah terhubung.

                            </div>

                        </div>

                        <div class="col-12">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Data Ibu yang Akan Digunakan
                                </span>

                                <div class="detail-value">

                                    <?= htmlspecialchars(
                                        $namaIbuProfil !== ""
                                            ? $namaIbuProfil
                                            : "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>

                                </div>

                                <small class="text-muted">

                                    Alamat:
                                    <?= htmlspecialchars(
                                        $alamatProfil !== ""
                                            ? $alamatProfil
                                            : "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>

                                </small>

                            </div>

                        </div>

                    </div>

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= (
                                !$profilIbuTersedia
                                || $namaIbuProfil === ""
                                || $alamatProfil === ""
                            )
                                ? "disabled"
                                : ""; ?>
                        >

                            <i
                                class="bi
                                bi-check-circle"
                            ></i>

                            Simpan Perubahan

                        </button>

                        <a
                            href="data_balita.php"
                            class="btn btn-outline-secondary"
                        >

                            <i
                                class="bi
                                bi-x-circle"
                            ></i>

                            Batal

                        </a>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>
