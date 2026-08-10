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

$riwayatPenyakit = "";
$riwayatImunisasi = "";
$riwayatPerawatan = "";

$penyakitPenyerta = "";
$redFlag = "";
$redFlagPilihan = "";
$redFlagDetail = "";
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

    $redFlagPilihan = trim(
        $_POST["red_flag_pilihan"] ?? ""
    );

    $redFlagDetail = trim(
        $_POST["red_flag_detail"] ?? ""
    );

    $redFlag = "";

    if ($redFlagPilihan === "Tidak Ada") {
        $redFlag = "Tidak ada";
    } elseif ($redFlagPilihan === "Ada") {
        $redFlag = $redFlagDetail;
    }

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
        || $redFlagPilihan === ""
        || $statusRujukan === ""
        || $rekomendasiRujukan === ""
        || $catatanKia === ""
    ) {
        $pesanError =
            "Semua data wajib diisi.";
    } elseif (
        !in_array(
            $redFlagPilihan,
            [
                "Tidak Ada",
                "Ada"
            ],
            true
        )
    ) {
        $pesanError =
            "Pilihan red flag tidak valid.";
    } elseif (
        $redFlagPilihan === "Ada"
        && $redFlagDetail === ""
    ) {
        $pesanError =
            "Jelaskan red flag yang ditemukan.";
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
    */

    if ($pesanError === "") {

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO riwayat_kesehatan
            (
                id_balita,
                riwayat_penyakit,
                riwayat_imunisasi,
                riwayat_perawatan,
                penyakit_penyerta,
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
                ?,
                ?,
                ?
            )"
        );

        if (!$stmtSimpan) {

            $pesanError =
                "Gagal menyiapkan penyimpanan data: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "issssssss",
                $idBalita,
                $riwayatPenyakit,
                $riwayatImunisasi,
                $riwayatPerawatan,
                $penyakitPenyerta,
                $redFlag,
                $statusRujukan,
                $rekomendasiRujukan,
                $catatanKia
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
                    "Location: riwayat_kesehatan.php?pesan=tambah_berhasil"
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
                        href="riwayat_kesehatan.php"
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
                                for="red_flag_pilihan"
                                class="form-label"
                            >
                                Red Flag Kesehatan
                            </label>

                            <select
                                id="red_flag_pilihan"
                                name="red_flag_pilihan"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    -- Pilih Hasil Evaluasi --
                                </option>

                                <option
                                    value="Tidak Ada"
                                    <?= $redFlagPilihan === "Tidak Ada"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Tidak Ada
                                </option>

                                <option
                                    value="Ada"
                                    <?= $redFlagPilihan === "Ada"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Ada Red Flag
                                </option>
                            </select>

                            <div class="form-text">
                                Penetapan red flag dilakukan oleh
                                Petugas KIA berdasarkan hasil
                                pemeriksaan. Sistem hanya mencatat
                                hasil evaluasi.
                            </div>

                            <div
                                id="bagian_detail_red_flag"
                                class="mt-3"
                                <?= $redFlagPilihan === "Ada"
                                    ? ""
                                    : "hidden"; ?>
                            >

                                <label
                                    for="red_flag_detail"
                                    class="form-label"
                                >
                                    Temuan Red Flag
                                </label>

                                <textarea
                                    id="red_flag_detail"
                                    name="red_flag_detail"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Jelaskan red flag yang ditemukan"
                                ><?= htmlspecialchars(
                                    $redFlagDetail,
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
                            href="riwayat_kesehatan.php"
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

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        const pilihan =
            document.getElementById(
                "red_flag_pilihan"
            );

        const bagianDetail =
            document.getElementById(
                "bagian_detail_red_flag"
            );

        const detail =
            document.getElementById(
                "red_flag_detail"
            );

        function aturRedFlag() {

            if (
                !pilihan
                || !bagianDetail
                || !detail
            ) {
                return;
            }

            const adaRedFlag =
                pilihan.value === "Ada";

            bagianDetail.hidden =
                !adaRedFlag;

            detail.required =
                adaRedFlag;

            if (!adaRedFlag) {
                detail.value = "";
            }
        }

        if (pilihan) {
            pilihan.addEventListener(
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