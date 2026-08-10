<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
*/

cekRole([
    "petugas_kia"
]);

$judulHalaman =
    "Edit Riwayat Kelahiran | Sistem Deteksi Stunting";

$error = "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";

/*
|--------------------------------------------------------------------------
| Fungsi output aman
|--------------------------------------------------------------------------
*/

function amanEditKelahiran($nilai): string
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Petugas KIA aktif
|--------------------------------------------------------------------------
*/

$stmtPuskesmasAkun = mysqli_prepare(
    $conn,
    "SELECT
        u.id_puskesmas,
        p.nama_puskesmas
     FROM pengguna AS u
     LEFT JOIN puskesmas AS p
        ON u.id_puskesmas = p.id_puskesmas
     WHERE u.id_user = ?
     AND u.role = 'petugas_kia'
     LIMIT 1"
);

if (!$stmtPuskesmasAkun) {
    die(
        "Gagal memeriksa Puskesmas Petugas KIA: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPuskesmasAkun,
    "i",
    $idUserAktif
);

mysqli_stmt_execute(
    $stmtPuskesmasAkun
);

$hasilPuskesmasAkun =
    mysqli_stmt_get_result(
        $stmtPuskesmasAkun
    );

$dataPuskesmasAkun =
    mysqli_fetch_assoc(
        $hasilPuskesmasAkun
    );

mysqli_stmt_close(
    $stmtPuskesmasAkun
);

if (
    !$dataPuskesmasAkun
    || empty(
        $dataPuskesmasAkun["id_puskesmas"]
    )
) {
    header(
        "Location: riwayat_kelahiran.php"
    );
    exit;
}

$idPuskesmasAktif =
    (int) $dataPuskesmasAkun[
        "id_puskesmas"
    ];

$namaPuskesmasAktif =
    trim(
        (string) (
            $dataPuskesmasAkun[
                "nama_puskesmas"
            ]
            ?? ""
        )
    );

/*
|--------------------------------------------------------------------------
| Validasi ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header(
        "Location: riwayat_kelahiran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data lama
|--------------------------------------------------------------------------
|
| Riwayat hanya dapat dibuka jika balita berasal dari Puskesmas
| yang sama dengan akun Petugas KIA.
|
*/

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        rk.*,
        b.nama_balita,
        b.nik_balita,
        b.id_puskesmas,
        p.nama_puskesmas
     FROM riwayat_kelahiran AS rk
     INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita
     LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas
     WHERE rk.id_kelahiran = ?
     AND b.id_puskesmas = ?
     LIMIT 1"
);

if (!$stmt) {
    die(
        "Gagal menyiapkan data riwayat kelahiran: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $id,
    $idPuskesmasAktif
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$data =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$data) {
    header(
        "Location: riwayat_kelahiran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Relasi balita dikunci
|--------------------------------------------------------------------------
|
| Edit riwayat kelahiran tidak boleh memindahkan riwayat ke balita lain.
|
*/

$idBalitaTetap =
    (int) $data["id_balita"];

$namaBalita =
    (string) ($data["nama_balita"] ?? "");

$nikBalita =
    (string) ($data["nik_balita"] ?? "");

/*
|--------------------------------------------------------------------------
| Data form
|--------------------------------------------------------------------------
*/

$form = [
    "berat_lahir" =>
        (string) ($data["berat_lahir"] ?? ""),
    "panjang_lahir" =>
        (string) ($data["panjang_lahir"] ?? ""),
    "usia_kehamilan" =>
        (string) ($data["usia_kehamilan"] ?? ""),
    "jenis_persalinan" =>
        (string) ($data["jenis_persalinan"] ?? ""),
    "usia_ibu_melahirkan" =>
        (string) ($data["usia_ibu_melahirkan"] ?? ""),
    "riwayat_kehamilan" =>
        (string) ($data["riwayat_kehamilan"] ?? ""),
    "komplikasi_kehamilan" =>
        (string) ($data["komplikasi_kehamilan"] ?? "")
];

/*
|--------------------------------------------------------------------------
| Proses update
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $form["berat_lahir"] =
        trim($_POST["berat_lahir"] ?? "");

    $form["panjang_lahir"] =
        trim($_POST["panjang_lahir"] ?? "");

    $form["usia_kehamilan"] =
        trim($_POST["usia_kehamilan"] ?? "");

    $form["jenis_persalinan"] =
        trim($_POST["jenis_persalinan"] ?? "");

    $form["usia_ibu_melahirkan"] =
        trim($_POST["usia_ibu_melahirkan"] ?? "");

    $form["riwayat_kehamilan"] =
        trim($_POST["riwayat_kehamilan"] ?? "");

    $form["komplikasi_kehamilan"] =
        trim($_POST["komplikasi_kehamilan"] ?? "");

    $beratLahir = filter_var(
        $form["berat_lahir"],
        FILTER_VALIDATE_FLOAT
    );

    $panjangLahir = filter_var(
        $form["panjang_lahir"],
        FILTER_VALIDATE_FLOAT
    );

    $usiaKehamilan = filter_var(
        $form["usia_kehamilan"],
        FILTER_VALIDATE_INT
    );

    $usiaIbuMelahirkan = filter_var(
        $form["usia_ibu_melahirkan"],
        FILTER_VALIDATE_INT
    );

    if (
        $form["berat_lahir"] === ""
        || $form["panjang_lahir"] === ""
        || $form["usia_kehamilan"] === ""
        || $form["jenis_persalinan"] === ""
        || $form["usia_ibu_melahirkan"] === ""
    ) {
        $error =
            "Berat lahir, panjang lahir, usia kehamilan, jenis persalinan, dan usia ibu saat melahirkan wajib diisi.";

    } elseif (
        $beratLahir === false
        || $beratLahir <= 0
    ) {
        $error =
            "Berat lahir harus lebih dari 0 kg.";

    } elseif (
        $panjangLahir === false
        || $panjangLahir <= 0
    ) {
        $error =
            "Panjang lahir harus lebih dari 0 cm.";

    } elseif (
        $usiaKehamilan === false
        || $usiaKehamilan < 20
        || $usiaKehamilan > 45
    ) {
        $error =
            "Usia kehamilan harus berada antara 20 sampai 45 minggu.";

    } elseif (
        $usiaIbuMelahirkan === false
        || $usiaIbuMelahirkan < 10
        || $usiaIbuMelahirkan > 60
    ) {
        $error =
            "Usia ibu saat melahirkan harus berada antara 10 sampai 60 tahun.";

    } else {

        $update = mysqli_prepare(
            $conn,
            "UPDATE riwayat_kelahiran
             SET
                berat_lahir = ?,
                panjang_lahir = ?,
                usia_kehamilan = ?,
                jenis_persalinan = ?,
                usia_ibu_melahirkan = ?,
                riwayat_kehamilan = ?,
                komplikasi_kehamilan = ?
             WHERE id_kelahiran = ?
             AND id_balita = ?"
        );

        if (!$update) {
            die(
                "Gagal menyiapkan perubahan data: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $update,
            "ddisissii",
            $beratLahir,
            $panjangLahir,
            $usiaKehamilan,
            $form["jenis_persalinan"],
            $usiaIbuMelahirkan,
            $form["riwayat_kehamilan"],
            $form["komplikasi_kehamilan"],
            $id,
            $idBalitaTetap
        );

        if (
            mysqli_stmt_execute($update)
        ) {
            mysqli_stmt_close(
                $update
            );

            header(
                "Location: riwayat_kelahiran.php?pesan=edit_berhasil"
            );
            exit;
        }

        $error =
            "Data gagal diperbarui.";

        mysqli_stmt_close(
            $update
        );
    }
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="card content-card">

            <div
                class="card-header
                d-flex
                flex-wrap
                justify-content-between
                align-items-center
                gap-3"
            >

                <div>

                    <h4 class="mb-1">
                        <i class="bi bi-pencil-square me-2"></i>
                        Edit Riwayat Kelahiran
                    </h4>

                    <small class="text-muted">
                        Perbarui data kelahiran tanpa mengubah
                        balita dan Puskesmas yang terhubung.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <span
                        class="badge badge-info
                        d-inline-flex
                        align-items-center
                        px-3"
                    >
                        <i class="bi bi-hospital me-1"></i>
                        <?= amanEditKelahiran(
                            $namaPuskesmasAktif
                        ); ?>
                    </span>

                    <a
                        href="riwayat_kelahiran.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

            </div>

            <div class="card-body">

                <?php if ($error !== ""): ?>

                    <div
                        class="alert alert-danger"
                        role="alert"
                    >
                        <?= amanEditKelahiran(
                            $error
                        ); ?>
                    </div>

                <?php endif; ?>

                <div class="row g-3 mb-4">

                    <div class="col-12 col-md-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Balita
                            </span>

                            <div class="detail-value mt-1">
                                <strong>
                                    <?= amanEditKelahiran(
                                        $namaBalita
                                    ); ?>
                                </strong>
                            </div>

                            <div class="form-text">
                                NIK:
                                <?= amanEditKelahiran(
                                    $nikBalita
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-md-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Puskesmas
                            </span>

                            <div class="detail-value mt-1">
                                <i class="bi bi-hospital me-1"></i>
                                <?= amanEditKelahiran(
                                    $namaPuskesmasAktif
                                ); ?>
                            </div>

                            <div class="form-text">
                                Balita dan Puskesmas dikunci agar
                                riwayat tidak berpindah ke data lain.
                            </div>

                        </div>

                    </div>

                </div>

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12 col-md-6">

                            <label class="form-label">
                                Berat Lahir
                                <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="berat_lahir"
                                    class="form-control"
                                    value="<?= amanEditKelahiran(
                                        $form["berat_lahir"]
                                    ); ?>"
                                    required
                                >

                                <span class="input-group-text">
                                    kg
                                </span>

                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <label class="form-label">
                                Panjang Lahir
                                <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="panjang_lahir"
                                    class="form-control"
                                    value="<?= amanEditKelahiran(
                                        $form["panjang_lahir"]
                                    ); ?>"
                                    required
                                >

                                <span class="input-group-text">
                                    cm
                                </span>

                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <label class="form-label">
                                Usia Kehamilan
                                <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    name="usia_kehamilan"
                                    class="form-control"
                                    min="20"
                                    max="45"
                                    value="<?= amanEditKelahiran(
                                        $form["usia_kehamilan"]
                                    ); ?>"
                                    required
                                >

                                <span class="input-group-text">
                                    minggu
                                </span>

                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <label class="form-label">
                                Jenis Persalinan
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="jenis_persalinan"
                                class="form-select"
                                required
                            >

                                <?php
                                $jenisPersalinan = [
                                    "Normal",
                                    "Caesar",
                                    "Vakum",
                                    "Forceps"
                                ];

                                foreach (
                                    $jenisPersalinan
                                    as $jenis
                                ):
                                ?>

                                    <option
                                        value="<?= amanEditKelahiran(
                                            $jenis
                                        ); ?>"
                                        <?= $form["jenis_persalinan"]
                                            === $jenis
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanEditKelahiran(
                                            $jenis
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-12 col-md-6">

                            <label class="form-label">
                                Usia Ibu Saat Melahirkan
                                <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    name="usia_ibu_melahirkan"
                                    class="form-control"
                                    min="10"
                                    max="60"
                                    value="<?= amanEditKelahiran(
                                        $form["usia_ibu_melahirkan"]
                                    ); ?>"
                                    required
                                >

                                <span class="input-group-text">
                                    tahun
                                </span>

                            </div>

                        </div>

                        <div class="col-12">

                            <label class="form-label">
                                Riwayat Kehamilan
                            </label>

                            <textarea
                                name="riwayat_kehamilan"
                                class="form-control"
                                rows="3"
                                placeholder="Contoh: Kehamilan pertama, kontrol rutin, tidak ada keluhan khusus."
                            ><?= amanEditKelahiran(
                                $form["riwayat_kehamilan"]
                            ); ?></textarea>

                        </div>

                        <div class="col-12">

                            <label class="form-label">
                                Komplikasi Kehamilan
                            </label>

                            <textarea
                                name="komplikasi_kehamilan"
                                class="form-control"
                                rows="3"
                                placeholder="Contoh: Tidak ada komplikasi."
                            ><?= amanEditKelahiran(
                                $form["komplikasi_kehamilan"]
                            ); ?></textarea>

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
                            href="riwayat_kelahiran.php"
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

<?php require_once "../includes/footer.php"; ?>
