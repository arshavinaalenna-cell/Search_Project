<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua"
]);

$judulHalaman =
    "Lengkapi Profil Ibu | Sistem Deteksi Stunting";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Pilihan dropdown sesuai ENUM database
|--------------------------------------------------------------------------
*/

$daftarPendidikan = [
    "Tidak Sekolah",
    "SD",
    "SMP",
    "SMA/SMK",
    "Diploma",
    "D4/S1",
    "S2",
    "S3"
];

$daftarPekerjaan = [
    "Ibu Rumah Tangga",
    "PNS/TNI/POLRI",
    "Pegawai Swasta",
    "Wiraswasta",
    "Buruh",
    "Petani",
    "Nelayan",
    "Tenaga Kesehatan",
    "Guru/Dosen",
    "Lainnya"
];

/*
|--------------------------------------------------------------------------
| Fungsi bantuan
|--------------------------------------------------------------------------
*/

function amanEditIbu($nilai): string
{
    return htmlspecialchars(
        (string) ($nilai ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Mengambil data lama jika profil sudah pernah dibuat
|--------------------------------------------------------------------------
*/

$stmtLama = mysqli_prepare(
    $conn,
    "SELECT
        id_orang_tua,
        nik_ibu,
        nama_ibu,
        no_hp,
        alamat,
        pendidikan_ibu,
        pekerjaan_ibu
     FROM orang_tua
     WHERE id_user = ?
     LIMIT 1"
);

if (!$stmtLama) {
    die(
        "Gagal menyiapkan profil ibu: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtLama,
    "i",
    $idUserAktif
);

mysqli_stmt_execute(
    $stmtLama
);

$hasilLama =
    mysqli_stmt_get_result(
        $stmtLama
    );

$dataLama =
    mysqli_fetch_assoc(
        $hasilLama
    );

mysqli_stmt_close(
    $stmtLama
);

$profilSudahAda =
    $dataLama !== null;

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$nikIbu =
    $dataLama["nik_ibu"] ?? "";

$namaIbu =
    $dataLama["nama_ibu"] ?? "";

$noHp =
    $dataLama["no_hp"] ?? "";

$alamat =
    $dataLama["alamat"] ?? "";

$pendidikanIbu =
    $dataLama["pendidikan_ibu"] ?? "";

$pekerjaanIbu =
    $dataLama["pekerjaan_ibu"] ?? "";

$pesanError = "";

/*
|--------------------------------------------------------------------------
| Proses simpan
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nikIbu =
        trim(
            $_POST["nik_ibu"] ?? ""
        );

    $namaIbu =
        trim(
            $_POST["nama_ibu"] ?? ""
        );

    $noHp =
        trim(
            $_POST["no_hp"] ?? ""
        );

    $alamat =
        trim(
            $_POST["alamat"] ?? ""
        );

    $pendidikanIbu =
        trim(
            $_POST[
                "pendidikan_ibu"
            ] ?? ""
        );

    $pekerjaanIbu =
        trim(
            $_POST[
                "pekerjaan_ibu"
            ] ?? ""
        );

    /*
    |--------------------------------------------------------------------------
    | Validasi
    |--------------------------------------------------------------------------
    */

    if (
        $nikIbu === ""
        || $namaIbu === ""
        || $noHp === ""
        || $alamat === ""
        || $pendidikanIbu === ""
        || $pekerjaanIbu === ""
    ) {
        $pesanError =
            "Semua data profil ibu wajib diisi.";

    } elseif (
        !preg_match(
            '/^[0-9]{16}$/',
            $nikIbu
        )
    ) {
        $pesanError =
            "NIK ibu harus terdiri dari 16 digit angka.";

    } elseif (
        mb_strlen($namaIbu) > 100
    ) {
        $pesanError =
            "Nama ibu maksimal 100 karakter.";

    } elseif (
        !preg_match(
            '/^[0-9+\-\s]{8,20}$/',
            $noHp
        )
    ) {
        $pesanError =
            "Nomor HP tidak valid.";

    } elseif (
        !in_array(
            $pendidikanIbu,
            $daftarPendidikan,
            true
        )
    ) {
        $pesanError =
            "Pilihan pendidikan ibu tidak valid.";

    } elseif (
        !in_array(
            $pekerjaanIbu,
            $daftarPekerjaan,
            true
        )
    ) {
        $pesanError =
            "Pilihan pekerjaan ibu tidak valid.";
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan profil
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO orang_tua (
                id_user,
                nik_ibu,
                nama_ibu,
                no_hp,
                alamat,
                pendidikan_ibu,
                pekerjaan_ibu
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)

            ON DUPLICATE KEY UPDATE
                nik_ibu = VALUES(nik_ibu),
                nama_ibu = VALUES(nama_ibu),
                no_hp = VALUES(no_hp),
                alamat = VALUES(alamat),
                pendidikan_ibu =
                    VALUES(pendidikan_ibu),
                pekerjaan_ibu =
                    VALUES(pekerjaan_ibu)"
        );

        if (!$stmtSimpan) {
            $pesanError =
                "Gagal menyiapkan penyimpanan profil ibu: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "issssss",
                $idUserAktif,
                $nikIbu,
                $namaIbu,
                $noHp,
                $alamat,
                $pendidikanIbu,
                $pekerjaanIbu
            );

            if (
                mysqli_stmt_execute(
                    $stmtSimpan
                )
            ) {
                mysqli_stmt_close(
                    $stmtSimpan
                );

                header(
                    "Location: profil_orang_tua.php?pesan="
                    . (
                        $profilSudahAda
                            ? "edit_berhasil"
                            : "simpan_berhasil"
                    )
                );

                exit;

            } else {

                $pesanError =
                    "Profil ibu gagal disimpan: "
                    . mysqli_stmt_error(
                        $stmtSimpan
                    );

                mysqli_stmt_close(
                    $stmtSimpan
                );
            }
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
                        <?= $profilSudahAda
                            ? "Edit Profil Ibu"
                            : "Lengkapi Profil Ibu"; ?>
                    </h4>

                    <small class="text-muted">
                        Lengkapi data identitas ibu.
                        Pendidikan dan pekerjaan dipilih dari
                        daftar agar data tetap konsisten.
                    </small>

                </div>

                <a
                    href="profil_orang_tua.php"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

            </div>

            <div class="card-body">

                <?php if ($pesanError !== ""): ?>

                    <div
                        class="alert alert-danger"
                        role="alert"
                    >
                        <i
                            class="bi
                            bi-exclamation-circle me-1"
                        ></i>

                        <?= amanEditIbu(
                            $pesanError
                        ); ?>

                    </div>

                <?php endif; ?>

                <form
                    method="POST"
                    action=""
                    autocomplete="off"
                >

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">

                            <label
                                for="nik_ibu"
                                class="form-label"
                            >
                                NIK Ibu
                            </label>

                            <input
                                type="text"
                                name="nik_ibu"
                                id="nik_ibu"
                                class="form-control"
                                value="<?= amanEditIbu(
                                    $nikIbu
                                ); ?>"
                                maxlength="16"
                                inputmode="numeric"
                                pattern="[0-9]{16}"
                                placeholder="16 digit NIK ibu"
                                required
                            >

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
                                name="nama_ibu"
                                id="nama_ibu"
                                class="form-control"
                                value="<?= amanEditIbu(
                                    $namaIbu
                                ); ?>"
                                maxlength="100"
                                placeholder="Nama lengkap ibu"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="no_hp"
                                class="form-label"
                            >
                                Nomor HP
                            </label>

                            <input
                                type="text"
                                name="no_hp"
                                id="no_hp"
                                class="form-control"
                                value="<?= amanEditIbu(
                                    $noHp
                                ); ?>"
                                maxlength="20"
                                placeholder="Contoh: 081234567890"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="pendidikan_ibu"
                                class="form-label"
                            >
                                Pendidikan Ibu
                            </label>

                            <select
                                name="pendidikan_ibu"
                                id="pendidikan_ibu"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Pilih pendidikan ibu
                                </option>

                                <?php foreach (
                                    $daftarPendidikan
                                    as $pendidikan
                                ): ?>

                                    <option
                                        value="<?= amanEditIbu(
                                            $pendidikan
                                        ); ?>"
                                        <?= $pendidikanIbu ===
                                            $pendidikan
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanEditIbu(
                                            $pendidikan
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="pekerjaan_ibu"
                                class="form-label"
                            >
                                Pekerjaan Ibu
                            </label>

                            <select
                                name="pekerjaan_ibu"
                                id="pekerjaan_ibu"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Pilih pekerjaan ibu
                                </option>

                                <?php foreach (
                                    $daftarPekerjaan
                                    as $pekerjaan
                                ): ?>

                                    <option
                                        value="<?= amanEditIbu(
                                            $pekerjaan
                                        ); ?>"
                                        <?= $pekerjaanIbu ===
                                            $pekerjaan
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanEditIbu(
                                            $pekerjaan
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-12">

                            <label
                                for="alamat"
                                class="form-label"
                            >
                                Alamat
                            </label>

                            <textarea
                                name="alamat"
                                id="alamat"
                                class="form-control"
                                rows="4"
                                placeholder="Alamat tempat tinggal"
                                required
                            ><?= amanEditIbu(
                                $alamat
                            ); ?></textarea>

                        </div>

                    </div>

                    <div class="form-actions mt-4">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-check-circle"></i>

                            <?= $profilSudahAda
                                ? "Simpan Perubahan"
                                : "Simpan Profil"; ?>
                        </button>

                        <a
                            href="profil_orang_tua.php"
                            class="btn btn-light"
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