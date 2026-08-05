<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua",
    "petugas_gizi"
]);

$judulHalaman = "Tambah Konsultasi | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

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
| Orang tua hanya dapat memilih anak miliknya.
| Petugas Gizi dapat memilih semua balita.
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

    mysqli_stmt_execute($stmtBalita);

    $queryBalita = mysqli_stmt_get_result(
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

    if ($roleAktif === "orang_tua") {
        $idPetugas = filter_input(
            INPUT_POST,
            "id_petugas",
            FILTER_VALIDATE_INT
        );

        $hasilKonsultasi = "";
        $tindakLanjut = "";
    } else {
        $idPetugas = $idUserAktif;

        $hasilKonsultasi = trim(
            $_POST["hasil_konsultasi"] ?? ""
        );

        $tindakLanjut = trim(
            $_POST["tindak_lanjut"] ?? ""
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi input
    |--------------------------------------------------------------------------
    */

    if (!$idBalita) {
        $pesanError = "Silakan pilih balita.";
    } elseif ($tanggal === "") {
        $pesanError = "Tanggal konsultasi wajib diisi.";
    } elseif (
        strtotime($tanggal) === false
    ) {
        $pesanError = "Tanggal konsultasi tidak valid.";
    } elseif (
        strtotime($tanggal) > time()
    ) {
        $pesanError =
            "Tanggal konsultasi tidak boleh melebihi hari ini.";
    } elseif ($keluhan === "") {
        $pesanError = "Keluhan wajib diisi.";
    } elseif (
        $roleAktif === "orang_tua"
        && !$idPetugas
    ) {
        $pesanError =
            "Silakan pilih Petugas Gizi.";
    } elseif (
        $roleAktif === "petugas_gizi"
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
            mysqli_stmt_execute($cekBalita);

            $hasilCekBalita =
                mysqli_stmt_get_result(
                    $cekBalita
                );

            if (
                mysqli_num_rows($hasilCekBalita) === 0
            ) {
                $pesanError =
                    "Data balita tidak ditemukan atau tidak dapat diakses.";
            }

            mysqli_stmt_close($cekBalita);
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

            mysqli_stmt_execute($cekPetugas);

            $hasilCekPetugas =
                mysqli_stmt_get_result(
                    $cekPetugas
                );

            if (
                mysqli_num_rows($hasilCekPetugas) === 0
            ) {
                $pesanError =
                    "Petugas Gizi tidak ditemukan.";
            }

            mysqli_stmt_close($cekPetugas);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan data konsultasi
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {
        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO konsultasi (
                id_balita,
                id_petugas,
                tanggal,
                keluhan,
                hasil_konsultasi,
                tindak_lanjut
            ) VALUES (?, ?, ?, ?, ?, ?)"
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
                mysqli_stmt_execute($stmtSimpan)
            ) {
                mysqli_stmt_close($stmtSimpan);

                header(
                    "Location: data_konsultasi.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data konsultasi gagal disimpan: "
                . mysqli_stmt_error($stmtSimpan);

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

        <div
            class="d-flex flex-column flex-md-row
            justify-content-between align-items-md-center
            gap-3 mb-4"
        >
            <div>
                <h2 class="mb-1">
                    Tambah Konsultasi
                </h2>

                <p class="text-muted mb-0">
                    <?php if (
                        $roleAktif === "orang_tua"
                    ): ?>
                        Sampaikan keluhan mengenai kondisi anak
                        kepada Petugas Gizi.
                    <?php else: ?>
                        Catat hasil konsultasi dan tindak lanjut
                        pelayanan gizi balita.
                    <?php endif; ?>
                </p>
            </div>

            <a
                href="data_konsultasi.php"
                class="btn btn-outline-secondary"
            >
                Kembali
            </a>
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
            mysqli_num_rows($queryBalita) === 0
        ): ?>

            <div class="alert alert-warning">
                Tidak ada data balita yang dapat dipilih.
            </div>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-body p-4">

                <form method="POST">

                    <div class="mb-3">

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
                                Pilih balita
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
                                        $balita["id_balita"] ?>"
                                    <?= (int) $idBalita ===
                                        (int) $balita["id_balita"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= htmlspecialchars(
                                        $balita["nama_balita"]
                                        . " - "
                                        . $balita["nik_balita"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                    <?php if (
                        $roleAktif === "orang_tua"
                    ): ?>

                        <div class="mb-3">

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
                                    Pilih Petugas Gizi
                                </option>

                                <?php while (
                                    $petugas =
                                        mysqli_fetch_assoc(
                                            $queryPetugas
                                        )
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $petugas["id_user"] ?>"
                                        <?= (int) $idPetugas ===
                                            (int) $petugas["id_user"]
                                            ? "selected"
                                            : "" ?>
                                    >
                                        <?= htmlspecialchars(
                                            $petugas["nama"]
                                            . " ("
                                            . $petugas["username"]
                                            . ")",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>

                    <?php endif; ?>

                    <div class="mb-3">

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
                            ) ?>"
                            max="<?= date("Y-m-d") ?>"
                            required
                        >

                    </div>

                    <div class="mb-3">

                        <label
                            for="keluhan"
                            class="form-label"
                        >
                            Keluhan
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
                        ) ?></textarea>

                    </div>

                    <?php if (
                        $roleAktif === "petugas_gizi"
                    ): ?>

                        <div class="mb-3">

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
                                rows="4"
                                placeholder="Tuliskan hasil konsultasi gizi"
                                required
                            ><?= htmlspecialchars(
                                $hasilKonsultasi,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?></textarea>

                        </div>

                        <div class="mb-3">

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
                                rows="4"
                                placeholder="Tuliskan rencana tindak lanjut"
                                required
                            ><?= htmlspecialchars(
                                $tindakLanjut,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?></textarea>

                        </div>

                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2">

                        <button
                            type="submit"
                            class="btn btn-success"
                            <?= mysqli_num_rows(
                                $queryBalita
                            ) === 0
                                ? "disabled"
                                : "" ?>
                        >
                            Simpan Konsultasi
                        </button>

                        <a
                            href="data_konsultasi.php"
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

<?php

if (
    isset($stmtBalita)
    && $stmtBalita instanceof mysqli_stmt
) {
    mysqli_stmt_close($stmtBalita);
}

require_once "../includes/footer.php";

?>