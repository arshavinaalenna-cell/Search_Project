<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_kia"]);

$judulHalaman = "Edit Riwayat Kesehatan | Sistem Deteksi Stunting";
$pesanError = "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Petugas KIA aktif
|--------------------------------------------------------------------------
*/

$stmtPuskesmas = mysqli_prepare(
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

if (!$stmtPuskesmas) {
    die(
        "Gagal memeriksa Puskesmas Petugas KIA: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPuskesmas,
    "i",
    $idUserAktif
);

mysqli_stmt_execute($stmtPuskesmas);

$hasilPuskesmas =
    mysqli_stmt_get_result($stmtPuskesmas);

$dataPuskesmas =
    mysqli_fetch_assoc($hasilPuskesmas);

mysqli_stmt_close($stmtPuskesmas);

if (
    !$dataPuskesmas
    || empty($dataPuskesmas["id_puskesmas"])
) {
    header(
        "Location: riwayat_kesehatan.php"
    );
    exit;
}

$idPuskesmasAktif =
    (int) $dataPuskesmas["id_puskesmas"];

$namaPuskesmasAktif =
    trim(
        (string) (
            $dataPuskesmas["nama_puskesmas"]
            ?? ""
        )
    );

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

$idRiwayat = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idRiwayat) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data riwayat kesehatan
|--------------------------------------------------------------------------
*/

$stmtRiwayat = mysqli_prepare(
    $conn,
    "SELECT
        rk.id_riwayat,
        rk.id_balita,
        rk.riwayat_penyakit,
        rk.riwayat_imunisasi,
        rk.riwayat_perawatan,
        rk.penyakit_penyerta,
        rk.status_red_flag,
        rk.catatan_red_flag,
        rk.penilai_red_flag,
        rk.tanggal_penilaian,
        rk.status_rujukan,
        rk.rekomendasi_rujukan,
        rk.catatan_kia,
        b.nik_balita,
        b.nama_balita,
        p.nama_puskesmas,
        penilai.nama AS nama_penilai_red_flag
     FROM riwayat_kesehatan AS rk
     INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita
     LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas
     LEFT JOIN pengguna AS penilai
        ON rk.penilai_red_flag = penilai.id_user
     WHERE rk.id_riwayat = ?
     AND b.id_puskesmas = ?
     LIMIT 1"
);

if (!$stmtRiwayat) {
    die(
        "Gagal menyiapkan data riwayat kesehatan: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtRiwayat,
    "ii",
    $idRiwayat,
    $idPuskesmasAktif
);

mysqli_stmt_execute($stmtRiwayat);

$hasilRiwayat = mysqli_stmt_get_result($stmtRiwayat);
$dataRiwayat = mysqli_fetch_assoc($hasilRiwayat);

mysqli_stmt_close($stmtRiwayat);

if (!$dataRiwayat) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$idBalita = (int) $dataRiwayat["id_balita"];

$riwayatPenyakit =
    $dataRiwayat["riwayat_penyakit"] ?? "";

$riwayatImunisasi =
    $dataRiwayat["riwayat_imunisasi"] ?? "";

$riwayatPerawatan =
    $dataRiwayat["riwayat_perawatan"] ?? "";

$penyakitPenyerta =
    $dataRiwayat["penyakit_penyerta"] ?? "";

$statusRedFlagLama =
    trim(
        (string) (
            $dataRiwayat["status_red_flag"]
            ?? "Belum dinilai"
        )
    );

$catatanRedFlagLama =
    trim(
        (string) (
            $dataRiwayat["catatan_red_flag"]
            ?? ""
        )
    );

$statusRedFlag =
    $statusRedFlagLama === "Belum dinilai"
        ? ""
        : $statusRedFlagLama;

$catatanRedFlag =
    $catatanRedFlagLama;

$penilaiRedFlagLama =
    $dataRiwayat["penilai_red_flag"]
        !== null
        ? (int) $dataRiwayat["penilai_red_flag"]
        : null;

$tanggalPenilaianLama =
    $dataRiwayat["tanggal_penilaian"]
        ?? null;

$statusRujukan =
    $dataRiwayat["status_rujukan"] ?? "";

$rekomendasiRujukan =
    $dataRiwayat["rekomendasi_rujukan"] ?? "";

$catatanKia =
    $dataRiwayat["catatan_kia"] ?? "";

/*
|--------------------------------------------------------------------------
| Identitas balita pemilik riwayat
|--------------------------------------------------------------------------
|
| Balita dikunci saat edit agar riwayat kesehatan tidak dapat dipindahkan
| ke balita lain.
|
*/

$dataBalita = [
    "id_balita" =>
        (int) $dataRiwayat["id_balita"],
    "nik_balita" =>
        (string) ($dataRiwayat["nik_balita"] ?? ""),
    "nama_balita" =>
        (string) ($dataRiwayat["nama_balita"] ?? "")
];

$namaPuskesmasRiwayat =
    trim(
        (string) (
            $dataRiwayat["nama_puskesmas"]
            ?? $namaPuskesmasAktif
        )
    );

/*
|--------------------------------------------------------------------------
| Memproses perubahan data
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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

    if (
        $riwayatPenyakit === ""
        || $riwayatImunisasi === ""
        || $riwayatPerawatan === ""
        || $penyakitPenyerta === ""
        || $statusRedFlag === ""
        || $statusRujukan === ""
        || $rekomendasiRujukan === ""
        || $catatanKia === ""
    ) {
        $pesanError = "Semua data wajib diisi.";
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
    | Memperbarui riwayat kesehatan
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

        $redFlagBerubah =
            $statusRedFlag !== $statusRedFlagLama
            || $catatanRedFlag !== $catatanRedFlagLama;

        $penilaiRedFlag =
            $penilaiRedFlagLama;

        $tanggalPenilaian =
            $tanggalPenilaianLama;

        if ($redFlagBerubah) {

            $penilaiRedFlag =
                $idUserAktif;

            $hasilWaktu = mysqli_query(
                $conn,
                "SELECT NOW() AS waktu_penilaian"
            );

            $dataWaktu =
                $hasilWaktu
                    ? mysqli_fetch_assoc(
                        $hasilWaktu
                    )
                    : null;

            $tanggalPenilaian =
                $dataWaktu[
                    "waktu_penilaian"
                ] ?? null;
        }

        $redFlagLama =
            $statusRedFlag === "Ada"
                ? $catatanRedFlag
                : "Tidak ada";

        if ($kolomRedFlagLamaAda) {

            $sqlUpdate = "
                UPDATE riwayat_kesehatan
                SET
                    riwayat_penyakit = ?,
                    riwayat_imunisasi = ?,
                    riwayat_perawatan = ?,
                    penyakit_penyerta = ?,
                    status_red_flag = ?,
                    catatan_red_flag = NULLIF(?, ''),
                    penilai_red_flag = ?,
                    tanggal_penilaian = ?,
                    red_flag = ?,
                    status_rujukan = ?,
                    rekomendasi_rujukan = ?,
                    catatan_kia = ?
                WHERE id_riwayat = ?
                AND id_balita = ?
            ";

        } else {

            $sqlUpdate = "
                UPDATE riwayat_kesehatan
                SET
                    riwayat_penyakit = ?,
                    riwayat_imunisasi = ?,
                    riwayat_perawatan = ?,
                    penyakit_penyerta = ?,
                    status_red_flag = ?,
                    catatan_red_flag = NULLIF(?, ''),
                    penilai_red_flag = ?,
                    tanggal_penilaian = ?,
                    status_rujukan = ?,
                    rekomendasi_rujukan = ?,
                    catatan_kia = ?
                WHERE id_riwayat = ?
                AND id_balita = ?
            ";
        }

        $stmtUpdate = mysqli_prepare(
            $conn,
            $sqlUpdate
        );

        if (!$stmtUpdate) {

            $pesanError =
                "Gagal menyiapkan perubahan data: "
                . mysqli_error($conn);

        } else {

            if ($kolomRedFlagLamaAda) {

                mysqli_stmt_bind_param(
                    $stmtUpdate,
                    "ssssssisssssii",
                    $riwayatPenyakit,
                    $riwayatImunisasi,
                    $riwayatPerawatan,
                    $penyakitPenyerta,
                    $statusRedFlag,
                    $catatanRedFlag,
                    $penilaiRedFlag,
                    $tanggalPenilaian,
                    $redFlagLama,
                    $statusRujukan,
                    $rekomendasiRujukan,
                    $catatanKia,
                    $idRiwayat,
                    $idBalita
                );

            } else {

                mysqli_stmt_bind_param(
                    $stmtUpdate,
                    "ssssssissssii",
                    $riwayatPenyakit,
                    $riwayatImunisasi,
                    $riwayatPerawatan,
                    $penyakitPenyerta,
                    $statusRedFlag,
                    $catatanRedFlag,
                    $penilaiRedFlag,
                    $tanggalPenilaian,
                    $statusRujukan,
                    $rekomendasiRujukan,
                    $catatanKia,
                    $idRiwayat,
                    $idBalita
                );
            }

            if (
                mysqli_stmt_execute(
                    $stmtUpdate
                )
            ) {
                mysqli_stmt_close(
                    $stmtUpdate
                );

                header(
                    "Location: riwayat_kesehatan.php?id_balita="
                    . (int) $idBalita
                    . "&pesan=edit_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data gagal diperbarui: "
                . mysqli_stmt_error(
                    $stmtUpdate
                );

            mysqli_stmt_close(
                $stmtUpdate
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
                        Edit Riwayat Kesehatan
                    </h4>

                    <small class="text-muted">
                        Perbarui riwayat kesehatan, red flag,
                        rujukan, dan catatan KIA balita.
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
                        <?= htmlspecialchars(
                            $namaPuskesmasRiwayat,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </span>

                    <a
                        href="riwayat_kesehatan.php?id_balita=<?= (int) $idBalita; ?>"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

            </div>

            <div class="card-body">

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

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12">

                            <label class="form-label">
                                Nama Balita
                            </label>

                            <div class="detail-item">

                                <span class="detail-label">
                                    Balita Pemilik Riwayat
                                </span>

                                <div class="detail-value">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $dataBalita["nama_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </strong>

                                    <span class="text-muted">
                                        —
                                        <?= htmlspecialchars(
                                            $dataBalita["nik_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </span>

                                </div>

                            </div>

                            <div class="form-text">
                                Balita tidak dapat diubah saat mengedit
                                riwayat kesehatan agar data tidak berpindah
                                ke balita lain.
                            </div>

                        </div>

                        <div class="col-12">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Puskesmas
                                </span>

                                <div class="detail-value">
                                    <i class="bi bi-hospital me-1"></i>
                                    <?= htmlspecialchars(
                                        $namaPuskesmasRiwayat,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </div>

                                <div class="form-text">
                                    Wilayah mengikuti Puskesmas
                                    balita dan tidak dapat diubah.
                                </div>

                            </div>

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
                                required
                            ><?= htmlspecialchars(
                                $riwayatPenyakit,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Perbarui penyakit yang pernah
                                dialami atau isi "Tidak ada".
                            </div>

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
                                required
                            ><?= htmlspecialchars(
                                $riwayatImunisasi,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Perbarui daftar imunisasi yang
                                sudah diterima balita.
                            </div>

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
                                required
                            ><?= htmlspecialchars(
                                $riwayatPerawatan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Perbarui riwayat rawat jalan,
                                rawat inap, atau perawatan lain.
                            </div>

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

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="status_red_flag"
                                class="form-label"
                            >
                                Status Red Flag
                            </label>

                            <?php if (
                                $statusRedFlagLama === "Belum dinilai"
                            ): ?>

                                <div class="alert alert-warning py-2">
                                    <i
                                        class="bi bi-exclamation-triangle me-1"
                                    ></i>
                                    Red flag pada riwayat ini belum dinilai.
                                    Pilih hasil penilaian sebelum menyimpan.
                                </div>

                            <?php endif; ?>

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
                                berdasarkan hasil penilaian klinis.
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
                                        <?= $statusRujukan === $status
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
                        >
                            <i class="bi bi-check-circle"></i>
                            Simpan Perubahan
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

            </div>

        </div>

    </main>

</div>

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

<?php require_once "../includes/footer.php"; ?>