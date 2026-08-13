<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_kia"]);

$judulHalaman =
    "Tambah Riwayat Kesehatan | Sistem Deteksi Stunting";

$pesanError = "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

$idBalita = "";

$idBalitaDariUrl = filter_input(
    INPUT_GET,
    "id_balita",
    FILTER_VALIDATE_INT
);

if ($idBalitaDariUrl) {
    $idBalita =
        (int) $idBalitaDariUrl;
}

$balitaDikunciDariUrl = false;
$dataBalitaDikunci = null;

$riwayatPenyakit = "";
$riwayatImunisasi = "";
$riwayatPerawatan = "";

$penyakitPenyerta = "";
$statusRedFlag = "";
$catatanRedFlag = "";
$statusRujukan = "";
$rekomendasiRujukan = "";
$catatanKia = "";

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Petugas KIA aktif
|--------------------------------------------------------------------------
|
| Puskesmas tidak dipilih manual pada form.
| Wilayah otomatis mengikuti id_puskesmas akun Petugas KIA.
|
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
    $puskesmasBelumTerhubung = true;
} else {
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
}

/*
|--------------------------------------------------------------------------
| Mengambil daftar balita
|--------------------------------------------------------------------------
|
| Hanya balita dari Puskesmas akun Petugas KIA yang ditampilkan.
|
*/

$queryBalita = false;
$stmtBalita = null;

if (!$puskesmasBelumTerhubung) {

    $stmtBalita = mysqli_prepare(
        $conn,
        "SELECT
            id_balita,
            nik_balita,
            nama_balita
         FROM balita
         WHERE id_puskesmas = ?
         ORDER BY nama_balita ASC"
    );

    if (!$stmtBalita) {
        die(
            "Gagal menyiapkan data balita: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtBalita,
        "i",
        $idPuskesmasAktif
    );

    mysqli_stmt_execute(
        $stmtBalita
    );

    $queryBalita =
        mysqli_stmt_get_result(
            $stmtBalita
        );

    if (!$queryBalita) {
        die(
            "Gagal mengambil data balita."
        );
    }
}

$jumlahBalita =
    $queryBalita
        ? mysqli_num_rows($queryBalita)
        : 0;

/*
|--------------------------------------------------------------------------
| Balita dari tombol Tambah Riwayat
|--------------------------------------------------------------------------
|
| Jika halaman dibuka dari tombol Tambah Riwayat pada satu balita,
| identitas balita dikunci agar catatan tidak salah masuk ke anak lain.
|
*/

if (
    $idBalita
    && !$puskesmasBelumTerhubung
) {

    $stmtBalitaDikunci = mysqli_prepare(
        $conn,
        "SELECT
            id_balita,
            nik_balita,
            nama_balita
         FROM balita
         WHERE id_balita = ?
           AND id_puskesmas = ?
         LIMIT 1"
    );

    if ($stmtBalitaDikunci) {

        mysqli_stmt_bind_param(
            $stmtBalitaDikunci,
            "ii",
            $idBalita,
            $idPuskesmasAktif
        );

        mysqli_stmt_execute(
            $stmtBalitaDikunci
        );

        $hasilBalitaDikunci =
            mysqli_stmt_get_result(
                $stmtBalitaDikunci
            );

        $dataBalitaDikunci =
            mysqli_fetch_assoc(
                $hasilBalitaDikunci
            );

        mysqli_stmt_close(
            $stmtBalitaDikunci
        );

        if ($dataBalitaDikunci) {
            $balitaDikunciDariUrl = true;
        } elseif (
            $_SERVER["REQUEST_METHOD"]
            !== "POST"
        ) {
            $idBalita = "";
        }
    }
}


/*
|--------------------------------------------------------------------------
| Kompatibilitas kolom red_flag lama
|--------------------------------------------------------------------------
|
| Kolom lama belum langsung dihapus agar transisi aman.
| File ini tetap dapat digunakan setelah kolom red_flag lama dihapus.
|
*/

$queryKolomRedFlagLama = mysqli_query(
    $conn,
    "SHOW COLUMNS
     FROM riwayat_kesehatan
     LIKE 'red_flag'"
);

$kolomRedFlagLamaAda =
    $queryKolomRedFlagLama
    && mysqli_num_rows(
        $queryKolomRedFlagLama
    ) > 0;

/*
|--------------------------------------------------------------------------
| Memproses penyimpanan data
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && !$puskesmasBelumTerhubung
) {

    $idBalita = filter_input(
        INPUT_POST,
        "id_balita",
        FILTER_VALIDATE_INT
    );

    $riwayatPenyakit = trim(
        $_POST["riwayat_penyakit"] ?? ""
    );

    $riwayatImunisasi = trim(
        $_POST["riwayat_imunisasi"] ?? ""
    );

    $riwayatPerawatan = trim(
        $_POST["riwayat_perawatan"] ?? ""
    );

    $penyakitPenyerta = trim(
        $_POST["penyakit_penyerta"] ?? ""
    );

    $statusRedFlag = trim(
        $_POST["status_red_flag"] ?? ""
    );

    $catatanRedFlag = trim(
        $_POST["catatan_red_flag"] ?? ""
    );

    $statusRujukan = trim(
        $_POST["status_rujukan"] ?? ""
    );

    $rekomendasiRujukan = trim(
        $_POST["rekomendasi_rujukan"] ?? ""
    );

    $catatanKia = trim(
        $_POST["catatan_kia"] ?? ""
    );

    /*
    |--------------------------------------------------------------------------
    | Validasi input
    |--------------------------------------------------------------------------
    */

    if (
        !$idBalita
        || $riwayatPenyakit === ""
        || $riwayatImunisasi === ""
        || $riwayatPerawatan === ""
        || $penyakitPenyerta === ""
        || $statusRedFlag === ""
        || $statusRujukan === ""
        || $rekomendasiRujukan === ""
        || $catatanKia === ""
    ) {
        $pesanError =
            "Semua data wajib diisi.";
    } elseif (
        !in_array(
            $statusRedFlag,
            [
                "Tidak ada",
                "Ada"
            ],
            true
        )
    ) {
        $pesanError =
            "Status red flag tidak valid.";
    } elseif (
        $statusRedFlag === "Ada"
        && $catatanRedFlag === ""
    ) {
        $pesanError =
            "Jelaskan temuan red flag yang ditemukan.";
    }

    if (
        $statusRedFlag !== "Ada"
    ) {
        $catatanRedFlag = "";
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan balita tersedia
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

        $cekBalita = mysqli_prepare(
            $conn,
            "SELECT id_balita
             FROM balita
             WHERE id_balita = ?
             AND id_puskesmas = ?
             LIMIT 1"
        );

        if (!$cekBalita) {

            $pesanError =
                "Gagal memeriksa data balita: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $cekBalita,
                "ii",
                $idBalita,
                $idPuskesmasAktif
            );

            mysqli_stmt_execute(
                $cekBalita
            );

            $hasilBalita =
                mysqli_stmt_get_result(
                    $cekBalita
                );

            if (
                mysqli_num_rows(
                    $hasilBalita
                ) === 0
            ) {
                $pesanError =
                    "Data balita tidak ditemukan atau tidak termasuk Puskesmas akun Petugas KIA.";
            }

            mysqli_stmt_close(
                $cekBalita
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan riwayat kesehatan
    |--------------------------------------------------------------------------
    |
    | status_red_flag dan catatan_red_flag disimpan terpisah.
    | penilai_red_flag otomatis menggunakan akun Petugas KIA aktif.
    | tanggal_penilaian menggunakan waktu database.
    |
    */

    if ($pesanError === "") {

        $redFlagLama =
            $statusRedFlag === "Ada"
                ? $catatanRedFlag
                : "Tidak ada";

        if ($kolomRedFlagLamaAda) {

            $sqlSimpan = "
                INSERT INTO riwayat_kesehatan
                (
                    id_balita,
                    riwayat_penyakit,
                    riwayat_imunisasi,
                    riwayat_perawatan,
                    penyakit_penyerta,
                    status_red_flag,
                    catatan_red_flag,
                    penilai_red_flag,
                    tanggal_penilaian,
                    red_flag,
                    status_rujukan,
                    rekomendasi_rujukan,
                    catatan_kia
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NULLIF(?, ''),
                    ?,
                    NOW(),
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

        } else {

            $sqlSimpan = "
                INSERT INTO riwayat_kesehatan
                (
                    id_balita,
                    riwayat_penyakit,
                    riwayat_imunisasi,
                    riwayat_perawatan,
                    penyakit_penyerta,
                    status_red_flag,
                    catatan_red_flag,
                    penilai_red_flag,
                    tanggal_penilaian,
                    status_rujukan,
                    rekomendasi_rujukan,
                    catatan_kia
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NULLIF(?, ''),
                    ?,
                    NOW(),
                    ?,
                    ?,
                    ?
                )
            ";
        }

        $stmtSimpan = mysqli_prepare(
            $conn,
            $sqlSimpan
        );

        if (!$stmtSimpan) {

            $pesanError =
                "Gagal menyiapkan penyimpanan data: "
                . mysqli_error($conn);

        } else {

            if ($kolomRedFlagLamaAda) {

                mysqli_stmt_bind_param(
                    $stmtSimpan,
                    "issssssissss",
                    $idBalita,
                    $riwayatPenyakit,
                    $riwayatImunisasi,
                    $riwayatPerawatan,
                    $penyakitPenyerta,
                    $statusRedFlag,
                    $catatanRedFlag,
                    $idUserAktif,
                    $redFlagLama,
                    $statusRujukan,
                    $rekomendasiRujukan,
                    $catatanKia
                );

            } else {

                mysqli_stmt_bind_param(
                    $stmtSimpan,
                    "issssssisss",
                    $idBalita,
                    $riwayatPenyakit,
                    $riwayatImunisasi,
                    $riwayatPerawatan,
                    $penyakitPenyerta,
                    $statusRedFlag,
                    $catatanRedFlag,
                    $idUserAktif,
                    $statusRujukan,
                    $rekomendasiRujukan,
                    $catatanKia
                );
            }

            if (
                mysqli_stmt_execute(
                    $stmtSimpan
                )
            ) {

                mysqli_stmt_close(
                    $stmtSimpan
                );

                header(
                    "Location: riwayat_kesehatan.php?id_balita="
                    . (int) $idBalita
                    . "&pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data gagal disimpan: "
                . mysqli_stmt_error(
                    $stmtSimpan
                );

            mysqli_stmt_close(
                $stmtSimpan
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
                        Tambah Riwayat Kesehatan
                    </h4>

                    <small class="text-muted">
                        Lengkapi riwayat kesehatan, red flag,
                        rujukan, dan catatan KIA balita.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        !$puskesmasBelumTerhubung
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex
                            align-items-center
                            px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= htmlspecialchars(
                                $namaPuskesmasAktif,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </span>

                    <?php endif; ?>

                    <a
                        href="<?= $idBalita
                            ? "riwayat_kesehatan.php?id_balita=" . (int) $idBalita
                            : "riwayat_kesehatan.php"; ?>"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

            </div>

            <div class="card-body">

                <?php if (
                    $puskesmasBelumTerhubung
                ): ?>

                    <div class="alert alert-warning">
                        <i
                            class="bi bi-exclamation-triangle me-1"
                        ></i>

                        Akun Petugas KIA belum terhubung dengan
                        Puskesmas. Hubungkan akun ke Puskesmas
                        terlebih dahulu sebelum menambah
                        riwayat kesehatan.
                    </div>

                <?php endif; ?>

                <?php if ($pesanError !== ""): ?>

                    <div
                        class="alert alert-danger
                        alert-dismissible fade show"
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

                <?php if (
                    $jumlahBalita === 0
                ): ?>

                    <div class="alert alert-warning">

                        <i
                            class="bi
                            bi-exclamation-triangle me-1"
                        ></i>

                        Belum ada data balita
                        yang dapat dipilih.

                    </div>

                <?php endif; ?>

                <?php if (
                    !$puskesmasBelumTerhubung
                ): ?>

                <div class="detail-item mb-4">

                    <span class="detail-label">
                        Puskesmas
                    </span>

                    <div class="detail-value mt-1">
                        <i class="bi bi-hospital me-1"></i>
                        <?= htmlspecialchars(
                            $namaPuskesmasAktif,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </div>

                    <div class="form-text mt-1">
                        Balita yang dapat dipilih otomatis dibatasi
                        berdasarkan Puskesmas akun Petugas KIA.
                    </div>

                </div>

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12">

                            <?php if (
                                $balitaDikunciDariUrl
                                && $dataBalitaDikunci
                            ): ?>

                                <label class="form-label">
                                    Nama Balita
                                </label>

                                <input
                                    type="hidden"
                                    name="id_balita"
                                    value="<?= (int) $dataBalitaDikunci["id_balita"]; ?>"
                                >

                                <div class="detail-item">

                                    <span class="detail-label">
                                        Balita Pemilik Riwayat
                                    </span>

                                    <div class="detail-value">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $dataBalitaDikunci[
                                                    "nama_balita"
                                                ],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </strong>

                                        <span class="text-muted">
                                            —
                                            <?= htmlspecialchars(
                                                $dataBalitaDikunci[
                                                    "nik_balita"
                                                ],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </div>

                                </div>

                                <div class="form-text">
                                    Balita dikunci karena penambahan
                                    dilakukan dari halaman riwayat anak.
                                </div>

                            <?php else: ?>

                                <label
                                    for="id_balita"
                                    class="form-label"
                                >
                                    Nama Balita
                                </label>

                                <select
                                    id="id_balita"
                                    name="id_balita"
                                    class="form-select"
                                >

                                    <option value="">
                                        -- Pilih Balita --
                                    </option>

                                    <?php while (
                                        $balita =
                                            mysqli_fetch_assoc(
                                                $queryBalita
                                            )
                                    ): ?>

                                        <option
                                            value="<?= (int)
                                                $balita[
                                                    "id_balita"
                                                ]; ?>"
                                            <?= (int) $idBalita ===
                                                (int) $balita[
                                                    "id_balita"
                                                ]
                                                ? "selected"
                                                : ""; ?>
                                        >
                                            <?= htmlspecialchars(
                                                $balita[
                                                    "nama_balita"
                                                ]
                                                . " - "
                                                . $balita[
                                                    "nik_balita"
                                                ],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </option>

                                    <?php endwhile; ?>

                                </select>

                                <div class="form-text">
                                    Pilih balita yang akan
                                    dilengkapi riwayat kesehatannya.
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="col-12">

                            <h6 class="mt-2 mb-1">
                                Riwayat Kesehatan Dasar
                            </h6>

                            <small class="text-muted">
                                Catat penyakit, imunisasi,
                                dan riwayat perawatan balita.
                            </small>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="riwayat_penyakit"
                                class="form-label"
                            >
                                Riwayat Penyakit
                            </label>

                            <textarea
                                id="riwayat_penyakit"
                                name="riwayat_penyakit"
                                class="form-control"
                                rows="5"
                                placeholder="Tuliskan penyakit yang pernah dialami atau isi Tidak ada"
                                required
                            ><?= htmlspecialchars(
                                $riwayatPenyakit,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="riwayat_imunisasi"
                                class="form-label"
                            >
                                Riwayat Imunisasi
                            </label>

                            <textarea
                                id="riwayat_imunisasi"
                                name="riwayat_imunisasi"
                                class="form-control"
                                rows="5"
                                placeholder="Tuliskan imunisasi yang sudah diterima"
                                required
                            ><?= htmlspecialchars(
                                $riwayatImunisasi,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                        <div class="col-12">

                            <label
                                for="riwayat_perawatan"
                                class="form-label"
                            >
                                Riwayat Perawatan
                            </label>

                            <textarea
                                id="riwayat_perawatan"
                                name="riwayat_perawatan"
                                class="form-control"
                                rows="4"
                                placeholder="Tuliskan riwayat rawat jalan, rawat inap, atau perawatan lain"
                                required
                            ><?= htmlspecialchars(
                                $riwayatPerawatan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                        <div class="col-12">

                            <hr>

                            <h6 class="mb-1">
                                Evaluasi KIA
                            </h6>

                            <small class="text-muted">
                                Lengkapi penyakit penyerta,
                                red flag, dan kebutuhan rujukan.
                            </small>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="penyakit_penyerta"
                                class="form-label"
                            >
                                Penyakit Penyerta
                            </label>

                            <textarea
                                id="penyakit_penyerta"
                                name="penyakit_penyerta"
                                class="form-control"
                                rows="4"
                                placeholder="Isi Tidak ada jika tidak terdapat penyakit penyerta"
                                required
                            ><?= htmlspecialchars(
                                $penyakitPenyerta,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Contoh: kelainan jantung,
                                gangguan saluran cerna, atau
                                penyakit kronis lain.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="status_red_flag"
                                class="form-label"
                            >
                                Status Red Flag
                            </label>

                            <select
                                id="status_red_flag"
                                name="status_red_flag"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    -- Pilih Hasil Penilaian --
                                </option>

                                <option
                                    value="Tidak ada"
                                    <?= $statusRedFlag === "Tidak ada"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Tidak ada
                                </option>

                                <option
                                    value="Ada"
                                    <?= $statusRedFlag === "Ada"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Ada
                                </option>
                            </select>

                            <div class="form-text">
                                Status ditetapkan oleh Petugas KIA
                                berdasarkan hasil penilaian klinis,
                                bukan dihitung otomatis oleh sistem.
                            </div>

                            <div
                                id="bagian_catatan_red_flag"
                                class="mt-3"
                                <?= $statusRedFlag === "Ada"
                                    ? ""
                                    : "hidden"; ?>
                            >

                                <label
                                    for="catatan_red_flag"
                                    class="form-label"
                                >
                                    Catatan / Temuan Red Flag
                                </label>

                                <textarea
                                    id="catatan_red_flag"
                                    name="catatan_red_flag"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Jelaskan tanda atau kondisi yang ditemukan"
                                ><?= htmlspecialchars(
                                    $catatanRedFlag,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?></textarea>

                            </div>

                        </div>

                        <div class="col-12 col-lg-4">

                            <label
                                for="status_rujukan"
                                class="form-label"
                            >
                                Status Rujukan
                            </label>

                            <select
                                id="status_rujukan"
                                name="status_rujukan"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Pilih Status --
                                </option>

                                <?php
                                $daftarStatusRujukan = [
                                    "Tidak Perlu",
                                    "Direkomendasikan",
                                    "Dirujuk"
                                ];

                                foreach (
                                    $daftarStatusRujukan
                                    as $status
                                ):
                                ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $status,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>"
                                        <?= $statusRujukan ===
                                            $status
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $status,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-12 col-lg-8">

                            <label
                                for="rekomendasi_rujukan"
                                class="form-label"
                            >
                                Rekomendasi Rujukan
                            </label>

                            <textarea
                                id="rekomendasi_rujukan"
                                name="rekomendasi_rujukan"
                                class="form-control"
                                rows="3"
                                placeholder="Contoh: Pemeriksaan dokter anak atau isi Tidak ada"
                                required
                            ><?= htmlspecialchars(
                                $rekomendasiRujukan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                        <div class="col-12">

                            <label
                                for="catatan_kia"
                                class="form-label"
                            >
                                Catatan KIA
                            </label>

                            <textarea
                                id="catatan_kia"
                                name="catatan_kia"
                                class="form-control"
                                rows="4"
                                placeholder="Tambahkan catatan Petugas KIA"
                                required
                            ><?= htmlspecialchars(
                                $catatanKia,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                    </div>

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= $jumlahBalita === 0
                                ? "disabled"
                                : ""; ?>
                        >
                            <i class="bi bi-check-circle"></i>
                            Simpan Riwayat
                        </button>

                        <a
                            href="<?= $idBalita
                                ? "riwayat_kesehatan.php?id_balita=" . (int) $idBalita
                                : "riwayat_kesehatan.php"; ?>"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-x-circle"></i>
                            Batal
                        </a>

                    </div>

                </form>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>


<style>
.balita-native-select-search {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

.balita-search-select {
    position: relative;
    width: 100%;
}

.balita-search-trigger {
    width: 100%;
    min-height: 46px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: .65rem .85rem;
    border: 1px solid #dfe3ea;
    border-radius: 12px;
    background: #fff;
    color: #334155;
    text-align: left;
    font: inherit;
    cursor: pointer;
}

.balita-search-trigger:disabled {
    background: #f4f6f8;
    color: #98a2b0;
    cursor: not-allowed;
}

.balita-search-trigger.is-placeholder {
    color: #7d8998;
}

.balita-search-panel {
    position: absolute;
    z-index: 2000;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    padding: 8px;
    border: 1px solid #e1e5eb;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 14px 32px rgba(30, 41, 59, .16);
}

.balita-search-panel[hidden] {
    display: none !important;
}

.balita-search-list {
    max-height: 260px;
    overflow-y: auto;
    margin-top: 7px;
}

.balita-search-option {
    width: 100%;
    display: block;
    padding: 10px 12px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #334155;
    text-align: left;
    cursor: pointer;
}

.balita-search-option:hover,
.balita-search-option:focus {
    background: #f4f7fb;
    outline: none;
}

.balita-search-option.is-selected {
    background: #eef4ff;
    font-weight: 700;
}

.balita-search-empty {
    padding: 12px;
    color: #8a96a6;
    text-align: center;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const select =
        document.getElementById("id_balita");

    if (!select) {
        return;
    }

    select.classList.add(
        "balita-native-select-search"
    );

    const wrapper =
        document.createElement("div");

    wrapper.className =
        "balita-search-select";

    const trigger =
        document.createElement("button");

    trigger.type = "button";
    trigger.className =
        "balita-search-trigger";

    const triggerText =
        document.createElement("span");

    const chevron =
        document.createElement("i");

    chevron.className =
        "bi bi-chevron-down";

    trigger.appendChild(triggerText);
    trigger.appendChild(chevron);

    const panel =
        document.createElement("div");

    panel.className =
        "balita-search-panel";

    panel.hidden = true;

    const searchGroup =
        document.createElement("div");

    searchGroup.className =
        "input-group";

    const searchIcon =
        document.createElement("span");

    searchIcon.className =
        "input-group-text";

    searchIcon.innerHTML =
        '<i class="bi bi-search"></i>';

    const searchInput =
        document.createElement("input");

    searchInput.type = "text";
    searchInput.className =
        "form-control";
    searchInput.placeholder =
        "Cari nama atau NIK balita...";
    searchInput.autocomplete =
        "off";

    searchGroup.appendChild(
        searchIcon
    );
    searchGroup.appendChild(
        searchInput
    );

    const list =
        document.createElement("div");

    list.className =
        "balita-search-list";

    panel.appendChild(searchGroup);
    panel.appendChild(list);

    wrapper.appendChild(trigger);
    wrapper.appendChild(panel);

    select.insertAdjacentElement(
        "afterend",
        wrapper
    );

    function normalisasi(value) {
        return String(value || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(
                /[\u0300-\u036f]/g,
                ""
            )
            .trim();
    }

    function optionAktif() {
        return Array.from(
            select.options
        ).filter(function (option) {
            return (
                option.value !== ""
                && !option.disabled
                && !option.hidden
            );
        });
    }

    function placeholder() {
        const kosong =
            Array.from(
                select.options
            ).find(function (option) {
                return option.value === "";
            });

        return kosong
            ? kosong.textContent.trim()
            : "-- Pilih Balita --";
    }

    function sinkronTrigger() {
        const option =
            select.options[
                select.selectedIndex
            ];

        const adaPilihan =
            option
            && option.value !== "";

        triggerText.textContent =
            adaPilihan
                ? option.textContent.trim()
                : placeholder();

        trigger.classList.toggle(
            "is-placeholder",
            !adaPilihan
        );

        trigger.disabled =
            select.disabled
            || optionAktif().length === 0;
    }

    function render(keyword = "") {
        list.innerHTML = "";

        const kata =
            normalisasi(keyword);

        const hasil =
            optionAktif().filter(
                function (option) {
                    if (kata === "") {
                        return true;
                    }

                    return normalisasi(
                        option.textContent
                        + " "
                        + option.value
                    ).includes(kata);
                }
            );

        if (hasil.length === 0) {
            const kosong =
                document.createElement("div");

            kosong.className =
                "balita-search-empty";

            kosong.textContent =
                "Balita tidak ditemukan.";

            list.appendChild(kosong);
            return;
        }

        hasil.forEach(
            function (option) {
                const item =
                    document.createElement(
                        "button"
                    );

                item.type = "button";
                item.className =
                    "balita-search-option";
                item.textContent =
                    option.textContent.trim();

                if (
                    option.value
                    === select.value
                ) {
                    item.classList.add(
                        "is-selected"
                    );
                }

                item.addEventListener(
                    "click",
                    function () {
                        select.value =
                            option.value;

                        select.dispatchEvent(
                            new Event(
                                "change",
                                {
                                    bubbles: true
                                }
                            )
                        );

                        sinkronTrigger();
                        panel.hidden = true;
                    }
                );

                list.appendChild(item);
            }
        );
    }

    trigger.addEventListener(
        "click",
        function () {
            if (trigger.disabled) {
                return;
            }

            panel.hidden =
                !panel.hidden;

            if (!panel.hidden) {
                searchInput.value = "";
                render("");

                setTimeout(
                    function () {
                        searchInput.focus();
                    },
                    0
                );
            }
        }
    );

    searchInput.addEventListener(
        "input",
        function () {
            render(
                searchInput.value
            );
        }
    );

    searchInput.addEventListener(
        "keydown",
        function (event) {
            if (event.key === "Escape") {
                panel.hidden = true;
                trigger.focus();
            }

            if (event.key === "Enter") {
                const pertama =
                    list.querySelector(
                        ".balita-search-option"
                    );

                if (pertama) {
                    event.preventDefault();
                    pertama.click();
                }
            }
        }
    );

    select.addEventListener(
        "change",
        sinkronTrigger
    );

    document.addEventListener(
        "click",
        function (event) {
            if (
                !wrapper.contains(
                    event.target
                )
            ) {
                panel.hidden = true;
            }
        }
    );

    sinkronTrigger();
});
</script>


<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        const status =
            document.getElementById(
                "status_red_flag"
            );

        const bagianCatatan =
            document.getElementById(
                "bagian_catatan_red_flag"
            );

        const catatan =
            document.getElementById(
                "catatan_red_flag"
            );

        function aturRedFlag() {

            if (
                !status
                || !bagianCatatan
                || !catatan
            ) {
                return;
            }

            const adaRedFlag =
                status.value === "Ada";

            bagianCatatan.hidden =
                !adaRedFlag;

            catatan.required =
                adaRedFlag;

            if (!adaRedFlag) {
                catatan.value = "";
            }
        }

        if (status) {
            status.addEventListener(
                "change",
                aturRedFlag
            );

            aturRedFlag();
        }
    }
);
</script>

<?php

if ($stmtBalita instanceof mysqli_stmt) {
    mysqli_stmt_close($stmtBalita);
}

require_once "../includes/footer.php";

?>