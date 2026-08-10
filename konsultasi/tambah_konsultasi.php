<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
|
| Orang Tua dapat mengajukan konsultasi untuk anak miliknya.
| Petugas Gizi dapat mencatat konsultasi yang dilakukan di Puskesmas.
|
*/

cekRole([
    "orang_tua",
    "petugas_gizi"
]);

$judulHalaman =
    "Tambah Konsultasi | Sistem Deteksi Stunting";

$roleAktif =
    $_SESSION["role"] ?? "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$pesanError = "";

$idBalita = "";
$idPetugas = "";
$tanggal = date("Y-m-d");
$keluhan = "";
$hasilKonsultasi = "";
$tindakLanjut = "";

/*
|--------------------------------------------------------------------------
| Mengambil daftar balita
|--------------------------------------------------------------------------
|
| Orang Tua hanya dapat memilih anak miliknya.
| Petugas Gizi dapat memilih balita yang akan dicatat konsultasinya.
|
*/

if ($roleAktif === "orang_tua") {

    $stmtBalita = mysqli_prepare(
        $conn,
        "SELECT
            id_balita,
            nama_balita,
            nik_balita
         FROM balita
         WHERE id_user = ?
         ORDER BY nama_balita ASC"
    );

    if (!$stmtBalita) {
        die(
            "Gagal mengambil data balita: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtBalita,
        "i",
        $idUserAktif
    );

    mysqli_stmt_execute(
        $stmtBalita
    );

    $queryBalita =
        mysqli_stmt_get_result(
            $stmtBalita
        );

} else {

    $queryBalita = mysqli_query(
        $conn,
        "SELECT
            id_balita,
            nama_balita,
            nik_balita
         FROM balita
         ORDER BY nama_balita ASC"
    );

    if (!$queryBalita) {
        die(
            "Gagal mengambil data balita: "
            . mysqli_error($conn)
        );
    }
}

/*
|--------------------------------------------------------------------------
| Mengambil daftar Petugas Gizi
|--------------------------------------------------------------------------
|
| Daftar ini digunakan ketika Orang Tua mengajukan konsultasi langsung
| kepada Petugas Gizi Puskesmas.
|
*/

$queryPetugas = mysqli_query(
    $conn,
    "SELECT
        id_user,
        nama,
        username
     FROM pengguna
     WHERE role = 'petugas_gizi'
     ORDER BY nama ASC"
);

if (!$queryPetugas) {
    die(
        "Gagal mengambil data Petugas Gizi: "
        . mysqli_error($conn)
    );
}

$totalBalita =
    mysqli_num_rows($queryBalita);

$totalPetugas =
    mysqli_num_rows($queryPetugas);

/*
|--------------------------------------------------------------------------
| Memproses penyimpanan konsultasi
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $idBalita = filter_input(
        INPUT_POST,
        "id_balita",
        FILTER_VALIDATE_INT
    );

    $tanggal = trim(
        $_POST["tanggal"] ?? ""
    );

    $keluhan = trim(
        $_POST["keluhan"] ?? ""
    );

    /*
    |--------------------------------------------------------------------------
    | Penentuan Petugas dan isi konsultasi berdasarkan role
    |--------------------------------------------------------------------------
    */

    if ($roleAktif === "orang_tua") {

        $idPetugas = filter_input(
            INPUT_POST,
            "id_petugas",
            FILTER_VALIDATE_INT
        );

        /*
        | Orang Tua hanya mengirim keluhan.
        | Hasil konsultasi dan tindak lanjut diisi oleh Petugas Gizi.
        */
        $hasilKonsultasi = "";
        $tindakLanjut = "";

    } else {

        /*
        | Jika konsultasi dicatat oleh Petugas Gizi,
        | petugas aktif otomatis menjadi petugas yang menangani.
        */
        $idPetugas =
            $idUserAktif;

        $hasilKonsultasi = trim(
            $_POST[
                "hasil_konsultasi"
            ] ?? ""
        );

        $tindakLanjut = trim(
            $_POST[
                "tindak_lanjut"
            ] ?? ""
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi input
    |--------------------------------------------------------------------------
    */

    if (!$idBalita) {

        $pesanError =
            "Silakan pilih balita.";

    } elseif ($tanggal === "") {

        $pesanError =
            "Tanggal konsultasi wajib diisi.";

    } else {

        $objekTanggal =
            DateTime::createFromFormat(
                "Y-m-d",
                $tanggal
            );

        $tanggalValid = (
            $objekTanggal !== false
            && $objekTanggal->format(
                "Y-m-d"
            ) === $tanggal
        );

        if (!$tanggalValid) {

            $pesanError =
                "Tanggal konsultasi tidak valid.";

        } elseif (
            $tanggal > date("Y-m-d")
        ) {

            $pesanError =
                "Tanggal konsultasi tidak boleh melebihi hari ini.";
        }
    }

    if (
        $pesanError === ""
        && $keluhan === ""
    ) {
        $pesanError =
            "Keluhan wajib diisi.";
    }

    if (
        $pesanError === ""
        && $roleAktif === "orang_tua"
        && !$idPetugas
    ) {
        $pesanError =
            "Silakan pilih Petugas Gizi.";
    }

    if (
        $pesanError === ""
        && $roleAktif === "petugas_gizi"
        && (
            $hasilKonsultasi === ""
            || $tindakLanjut === ""
        )
    ) {
        $pesanError =
            "Hasil konsultasi dan tindak lanjut wajib diisi.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan balita boleh dipilih oleh akun aktif
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

        if ($roleAktif === "orang_tua") {

            $cekBalita = mysqli_prepare(
                $conn,
                "SELECT id_balita
                 FROM balita
                 WHERE id_balita = ?
                 AND id_user = ?
                 LIMIT 1"
            );

            if (!$cekBalita) {

                $pesanError =
                    "Gagal memeriksa data balita.";

            } else {

                mysqli_stmt_bind_param(
                    $cekBalita,
                    "ii",
                    $idBalita,
                    $idUserAktif
                );
            }

        } else {

            $cekBalita = mysqli_prepare(
                $conn,
                "SELECT id_balita
                 FROM balita
                 WHERE id_balita = ?
                 LIMIT 1"
            );

            if (!$cekBalita) {

                $pesanError =
                    "Gagal memeriksa data balita.";

            } else {

                mysqli_stmt_bind_param(
                    $cekBalita,
                    "i",
                    $idBalita
                );
            }
        }

        if ($pesanError === "") {

            mysqli_stmt_execute(
                $cekBalita
            );

            $hasilCekBalita =
                mysqli_stmt_get_result(
                    $cekBalita
                );

            if (
                mysqli_num_rows(
                    $hasilCekBalita
                ) === 0
            ) {
                $pesanError =
                    "Data balita tidak ditemukan atau tidak dapat diakses.";
            }

            mysqli_stmt_close(
                $cekBalita
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan Petugas Gizi valid
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

        $cekPetugas = mysqli_prepare(
            $conn,
            "SELECT id_user
             FROM pengguna
             WHERE id_user = ?
             AND role = 'petugas_gizi'
             LIMIT 1"
        );

        if (!$cekPetugas) {

            $pesanError =
                "Gagal memeriksa Petugas Gizi.";

        } else {

            mysqli_stmt_bind_param(
                $cekPetugas,
                "i",
                $idPetugas
            );

            mysqli_stmt_execute(
                $cekPetugas
            );

            $hasilCekPetugas =
                mysqli_stmt_get_result(
                    $cekPetugas
                );

            if (
                mysqli_num_rows(
                    $hasilCekPetugas
                ) === 0
            ) {
                $pesanError =
                    "Petugas Gizi tidak ditemukan.";
            }

            mysqli_stmt_close(
                $cekPetugas
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan konsultasi
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO konsultasi
            (
                id_balita,
                id_petugas,
                tanggal,
                keluhan,
                hasil_konsultasi,
                tindak_lanjut
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                NULLIF(?, ''),
                NULLIF(?, '')
            )"
        );

        if (!$stmtSimpan) {

            $pesanError =
                "Gagal menyiapkan penyimpanan konsultasi: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "iissss",
                $idBalita,
                $idPetugas,
                $tanggal,
                $keluhan,
                $hasilKonsultasi,
                $tindakLanjut
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
                    "Location: data_konsultasi.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data konsultasi gagal disimpan: "
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
                        Tambah Konsultasi
                    </h4>

                    <small class="text-muted">

                        <?php if (
                            $roleAktif ===
                            "orang_tua"
                        ): ?>

                            Sampaikan keluhan mengenai kondisi
                            anak langsung kepada Petugas Gizi.

                        <?php else: ?>

                            Catat konsultasi gizi yang dilakukan
                            bersama orang tua dan balita.

                        <?php endif; ?>

                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        $roleAktif ===
                        "orang_tua"
                    ): ?>

                        <span class="badge badge-info">
                            <i
                                class="bi
                                bi-person-heart"
                            ></i>
                            Orang Tua
                        </span>

                    <?php else: ?>

                        <span class="badge badge-info">
                            <i
                                class="bi
                                bi-heart-pulse"
                            ></i>
                            Petugas Gizi
                        </span>

                    <?php endif; ?>

                    <a
                        href="data_konsultasi.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i
                            class="bi
                            bi-arrow-left"
                        ></i>
                        Kembali
                    </a>

                </div>

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
                            bi-x-circle me-1"
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
                    $totalBalita === 0
                ): ?>

                    <div class="alert alert-warning">

                        <i
                            class="bi
                            bi-exclamation-triangle
                            me-1"
                        ></i>

                        <?php if (
                            $roleAktif ===
                            "orang_tua"
                        ): ?>

                            Belum ada data anak yang
                            terhubung dengan akun Anda.

                        <?php else: ?>

                            Belum ada data balita
                            yang dapat dipilih.

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

                <?php if (
                    $roleAktif === "orang_tua"
                    && $totalPetugas === 0
                ): ?>

                    <div class="alert alert-warning">

                        <i
                            class="bi
                            bi-exclamation-triangle
                            me-1"
                        ></i>

                        Belum ada akun Petugas Gizi
                        yang tersedia untuk konsultasi.

                    </div>

                <?php endif; ?>

                <form method="POST">

                    <div class="row g-3">

                        <div
                            class="col-12
                            <?= $roleAktif ===
                                "orang_tua"
                                ? "col-lg-6"
                                : "col-lg-8"; ?>"
                        >

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
                                required
                            >

                                <option value="">
                                    -- Pilih Balita --
                                </option>

                                <?php
                                mysqli_data_seek(
                                    $queryBalita,
                                    0
                                );
                                ?>

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

                                <?php if (
                                    $roleAktif ===
                                    "orang_tua"
                                ): ?>

                                    Hanya anak yang terhubung
                                    dengan akun Anda yang
                                    dapat dipilih.

                                <?php else: ?>

                                    Pilih balita yang
                                    berkonsultasi di Puskesmas.

                                <?php endif; ?>

                            </div>

                        </div>

                        <?php if (
                            $roleAktif ===
                            "orang_tua"
                        ): ?>

                            <div
                                class="col-12
                                col-lg-6"
                            >

                                <label
                                    for="id_petugas"
                                    class="form-label"
                                >
                                    Petugas Gizi
                                </label>

                                <select
                                    id="id_petugas"
                                    name="id_petugas"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        -- Pilih Petugas Gizi --
                                    </option>

                                    <?php while (
                                        $petugas =
                                            mysqli_fetch_assoc(
                                                $queryPetugas
                                            )
                                    ): ?>

                                        <option
                                            value="<?= (int)
                                                $petugas[
                                                    "id_user"
                                                ]; ?>"
                                            <?= (int) $idPetugas ===
                                                (int) $petugas[
                                                    "id_user"
                                                ]
                                                ? "selected"
                                                : ""; ?>
                                        >
                                            <?= htmlspecialchars(
                                                $petugas[
                                                    "nama"
                                                ]
                                                . " ("
                                                . $petugas[
                                                    "username"
                                                ]
                                                . ")",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </option>

                                    <?php endwhile; ?>

                                </select>

                                <div class="form-text">
                                    Pilih Petugas Gizi
                                    yang akan menerima konsultasi.
                                </div>

                            </div>

                        <?php endif; ?>

                        <div
                            class="col-12
                            <?= $roleAktif ===
                                "orang_tua"
                                ? ""
                                : "col-lg-4"; ?>"
                        >

                            <label
                                for="tanggal"
                                class="form-label"
                            >
                                Tanggal Konsultasi
                            </label>

                            <input
                                type="date"
                                id="tanggal"
                                name="tanggal"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $tanggal,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                max="<?= date(
                                    "Y-m-d"
                                ); ?>"
                                required
                            >

                        </div>

                        <div class="col-12">

                            <label
                                for="keluhan"
                                class="form-label"
                            >
                                Keluhan /
                                Kondisi yang Dikonsultasikan
                            </label>

                            <textarea
                                id="keluhan"
                                name="keluhan"
                                class="form-control"
                                rows="4"
                                placeholder="Tuliskan keluhan atau kondisi yang ingin dikonsultasikan"
                                required
                            ><?= htmlspecialchars(
                                $keluhan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Jelaskan kondisi atau keluhan
                                secara singkat dan jelas.
                            </div>

                        </div>

                        <?php if (
                            $roleAktif ===
                            "petugas_gizi"
                        ): ?>

                            <div
                                class="col-12
                                col-lg-6"
                            >

                                <label
                                    for="hasil_konsultasi"
                                    class="form-label"
                                >
                                    Hasil Konsultasi
                                </label>

                                <textarea
                                    id="hasil_konsultasi"
                                    name="hasil_konsultasi"
                                    class="form-control"
                                    rows="5"
                                    placeholder="Tuliskan hasil asesmen dan konseling gizi"
                                    required
                                ><?= htmlspecialchars(
                                    $hasilKonsultasi,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?></textarea>

                                <div class="form-text">
                                    Catat hasil asesmen,
                                    konseling, atau edukasi gizi.
                                </div>

                            </div>

                            <div
                                class="col-12
                                col-lg-6"
                            >

                                <label
                                    for="tindak_lanjut"
                                    class="form-label"
                                >
                                    Tindak Lanjut
                                </label>

                                <textarea
                                    id="tindak_lanjut"
                                    name="tindak_lanjut"
                                    class="form-control"
                                    rows="5"
                                    placeholder="Tuliskan rencana monitoring atau tindak lanjut"
                                    required
                                ><?= htmlspecialchars(
                                    $tindakLanjut,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?></textarea>

                                <div class="form-text">
                                    Catat jadwal monitoring,
                                    kontrol, atau tindak lanjut.
                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= (
                                $totalBalita === 0
                                || (
                                    $roleAktif ===
                                    "orang_tua"
                                    && $totalPetugas === 0
                                )
                            )
                                ? "disabled"
                                : ""; ?>
                        >
                            <i
                                class="bi
                                bi-check-circle"
                            ></i>
                            Simpan Konsultasi
                        </button>

                        <a
                            href="data_konsultasi.php"
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

<?php

if (
    isset($stmtBalita)
    && $stmtBalita instanceof mysqli_stmt
) {
    mysqli_stmt_close(
        $stmtBalita
    );
}

require_once "../includes/footer.php";

?>