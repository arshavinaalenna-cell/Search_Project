<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";


cekRole([
    "petugas_kia"
]);


$judulHalaman = "Tambah Riwayat Kelahiran | Sistem Deteksi Stunting";


// SIMPAN DATA

$pesanError = "";

$id_balita = "";
$berat_lahir = "";
$panjang_lahir = "";
$usia_kehamilan = "";
$jenis_persalinan = "";
$usia_ibu_melahirkan = "";
$riwayat_kehamilan = "";
$komplikasi_kehamilan = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id_balita = filter_input(
        INPUT_POST,
        "id_balita",
        FILTER_VALIDATE_INT
    );

    $berat_lahir = trim(
        $_POST["berat_lahir"] ?? ""
    );

    $panjang_lahir = trim(
        $_POST["panjang_lahir"] ?? ""
    );

    $usia_kehamilan = trim(
        $_POST["usia_kehamilan"] ?? ""
    );

    $jenis_persalinan = trim(
        $_POST["jenis_persalinan"] ?? ""
    );

    $usia_ibu_melahirkan = trim(
        $_POST["usia_ibu_melahirkan"] ?? ""
    );

    $riwayat_kehamilan = trim(
        $_POST["riwayat_kehamilan"] ?? ""
    );

    $komplikasi_kehamilan = trim(
        $_POST["komplikasi_kehamilan"] ?? ""
    );

    if (
        !$id_balita ||
        $berat_lahir === "" ||
        $panjang_lahir === "" ||
        $usia_kehamilan === "" ||
        $jenis_persalinan === "" ||
        $usia_ibu_melahirkan === "" ||
        $riwayat_kehamilan === "" ||
        $komplikasi_kehamilan === ""
    ) {
        $pesanError = "Semua data wajib diisi.";
    } elseif (
        !is_numeric($berat_lahir) ||
        (float) $berat_lahir <= 0
    ) {
        $pesanError = "Berat lahir tidak valid.";
    } elseif (
        !is_numeric($panjang_lahir) ||
        (float) $panjang_lahir <= 0
    ) {
        $pesanError = "Panjang lahir tidak valid.";
    } elseif (
        filter_var(
            $usia_kehamilan,
            FILTER_VALIDATE_INT
        ) === false ||
        (int) $usia_kehamilan <= 0
    ) {
        $pesanError = "Usia kehamilan tidak valid.";
    } elseif (
        filter_var(
            $usia_ibu_melahirkan,
            FILTER_VALIDATE_INT
        ) === false ||
        (int) $usia_ibu_melahirkan <= 0
    ) {
        $pesanError =
            "Usia ibu saat melahirkan tidak valid.";
    }

    if ($pesanError === "") {

        $stmtCekBalita = mysqli_prepare(
            $conn,
            "SELECT id_balita
             FROM balita
             WHERE id_balita = ?
             LIMIT 1"
        );

        if (!$stmtCekBalita) {
            $pesanError =
                "Gagal memeriksa data balita: "
                . mysqli_error($conn);
        } else {

            mysqli_stmt_bind_param(
                $stmtCekBalita,
                "i",
                $id_balita
            );

            mysqli_stmt_execute($stmtCekBalita);

            $hasilCekBalita =
                mysqli_stmt_get_result(
                    $stmtCekBalita
                );

            if (
                mysqli_num_rows($hasilCekBalita) === 0
            ) {
                $pesanError =
                    "Data balita tidak ditemukan.";
            }

            mysqli_stmt_close($stmtCekBalita);
        }
    }

    if ($pesanError === "") {

        $beratLahirFloat =
            (float) $berat_lahir;

        $panjangLahirFloat =
            (float) $panjang_lahir;

        $usiaKehamilanInt =
            (int) $usia_kehamilan;

        $usiaIbuMelahirkanInt =
            (int) $usia_ibu_melahirkan;

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO riwayat_kelahiran
            (
                id_balita,
                berat_lahir,
                panjang_lahir,
                usia_kehamilan,
                jenis_persalinan,
                usia_ibu_melahirkan,
                riwayat_kehamilan,
                komplikasi_kehamilan
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmtSimpan) {
            $pesanError =
                "Gagal menyiapkan penyimpanan data: "
                . mysqli_error($conn);
        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "iddisiss",
                $id_balita,
                $beratLahirFloat,
                $panjangLahirFloat,
                $usiaKehamilanInt,
                $jenis_persalinan,
                $usiaIbuMelahirkanInt,
                $riwayat_kehamilan,
                $komplikasi_kehamilan
            );

            if (mysqli_stmt_execute($stmtSimpan)) {

                mysqli_stmt_close($stmtSimpan);

                header(
                    "Location: riwayat_kelahiran.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data gagal disimpan: "
                . mysqli_stmt_error($stmtSimpan);

            mysqli_stmt_close($stmtSimpan);
        }
    }
}



// DATA BALITA

$balita = mysqli_query($conn,"
    SELECT *
    FROM balita
    ORDER BY nama_balita ASC
");



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
                        Tambah Riwayat Kelahiran
                    </h4>

                    <small class="text-muted">
                        Lengkapi data kelahiran balita untuk
                        mendukung riwayat pertumbuhan.
                    </small>

                </div>

                <a
                    href="riwayat_kelahiran.php"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

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

                            <select
                                name="id_balita"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Pilih Balita --
                                </option>

                                <?php while (
                                    $b = mysqli_fetch_assoc($balita)
                                ): ?>

                                    <option
                                        value="<?= $b[
                                            "id_balita"
                                        ]; ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $b["nama_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>

                                        (<?= htmlspecialchars(
                                            $b["nik_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>)
                                    </option>

                                <?php endwhile; ?>

                            </select>

                            <div class="form-text">
                                Pilih balita yang akan dilengkapi
                                riwayat kelahirannya.
                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <label class="form-label">
                                Berat Lahir
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    step="0.01"
                                    id="berat_lahir"
                                    name="berat_lahir"
                                    class="form-control"
                                    placeholder="Contoh: 3.20"
                                    value="<?= htmlspecialchars(
                                        $berat_lahir,
                                        ENT_QUOTES,
                                        "UTF-8"
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
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    step="0.01"
                                    name="panjang_lahir"
                                    class="form-control"
                                    placeholder="Contoh: 49"
                                    value="<?= htmlspecialchars(
                                        $panjang_lahir,
                                        ENT_QUOTES,
                                        "UTF-8"
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
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    id="usia_kehamilan"
                                    name="usia_kehamilan"
                                    class="form-control"
                                    placeholder="Contoh: 39"
                                    value="<?= htmlspecialchars(
                                        $usia_kehamilan,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                    required
                                >

                                <span class="input-group-text">
                                    minggu
                                </span>

                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <label
                                for="jenis_persalinan"
                                class="form-label"
                            >
                                Jenis Persalinan
                            </label>

                            <select
                                id="jenis_persalinan"
                                name="jenis_persalinan"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Pilih Jenis Persalinan --
                                </option>

                                <?php
                                $daftarPersalinan = [
                                    "Normal",
                                    "Caesar",
                                    "Vakum",
                                    "Forceps"
                                ];

                                foreach (
                                    $daftarPersalinan
                                    as $persalinan
                                ):
                                ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $persalinan,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>"
                                        <?= $jenis_persalinan ===
                                            $persalinan
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $persalinan,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-12">

                            <div class="row g-3">

                                <div class="col-12 col-md-6">

                                    <div class="detail-item h-100">

                                        <span class="detail-label">
                                            Status Berat Lahir
                                        </span>

                                        <div class="detail-value">

                                            <span
                                                id="status_bblr"
                                                class="badge bg-secondary"
                                            >
                                                Belum Dinilai
                                            </span>

                                        </div>

                                        <small
                                            class="text-muted d-block mt-2"
                                        >
                                            BBLR apabila berat lahir
                                            kurang dari 2,5 kg.
                                        </small>

                                    </div>

                                </div>

                                <div class="col-12 col-md-6">

                                    <div class="detail-item h-100">

                                        <span class="detail-label">
                                            Status Usia Kehamilan
                                        </span>

                                        <div class="detail-value">

                                            <span
                                                id="status_prematur"
                                                class="badge bg-secondary"
                                            >
                                                Belum Dinilai
                                            </span>

                                        </div>

                                        <small
                                            class="text-muted d-block mt-2"
                                        >
                                            Prematur apabila usia
                                            kehamilan kurang dari 37 minggu.
                                        </small>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <div class="col-12">

                            <hr>

                            <h6 class="mb-1">
                                Data Maternal
                            </h6>

                            <small class="text-muted">
                                Lengkapi riwayat kehamilan ibu
                                yang berkaitan dengan kelahiran balita.
                            </small>

                        </div>

                        <div class="col-12 col-md-4">

                            <label
                                for="usia_ibu_melahirkan"
                                class="form-label"
                            >
                                Usia Ibu Saat Melahirkan
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    min="1"
                                    id="usia_ibu_melahirkan"
                                    name="usia_ibu_melahirkan"
                                    class="form-control"
                                    placeholder="Contoh: 28"
                                    value="<?= htmlspecialchars(
                                        $usia_ibu_melahirkan,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                    required
                                >

                                <span class="input-group-text">
                                    tahun
                                </span>

                            </div>

                        </div>

                        <div class="col-12 col-md-8">

                            <label
                                for="riwayat_kehamilan"
                                class="form-label"
                            >
                                Riwayat Kehamilan
                            </label>

                            <textarea
                                id="riwayat_kehamilan"
                                name="riwayat_kehamilan"
                                class="form-control"
                                rows="3"
                                placeholder="Contoh: Kehamilan pertama, kontrol rutin, tidak ada keluhan khusus"
                                required
                            ><?= htmlspecialchars(
                                $riwayat_kehamilan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                        </div>

                        <div class="col-12">

                            <label
                                for="komplikasi_kehamilan"
                                class="form-label"
                            >
                                Komplikasi Kehamilan / Persalinan
                            </label>

                            <textarea
                                id="komplikasi_kehamilan"
                                name="komplikasi_kehamilan"
                                class="form-control"
                                rows="3"
                                placeholder="Tuliskan komplikasi yang pernah dialami atau isi Tidak ada"
                                required
                            ><?= htmlspecialchars(
                                $komplikasi_kehamilan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Jika tidak ada komplikasi,
                                isi dengan "Tidak ada".
                            </div>

                        </div>

                    </div>

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            name="simpan"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-check-circle"></i>
                            Simpan Riwayat
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

<script>
document.addEventListener("DOMContentLoaded", function () {

    const inputBerat =
        document.getElementById("berat_lahir");

    const inputUsia =
        document.getElementById("usia_kehamilan");

    const statusBBLR =
        document.getElementById("status_bblr");

    const statusPrematur =
        document.getElementById("status_prematur");

    function aturBadge(elemen, teks, kelas) {
        elemen.className = "badge " + kelas;
        elemen.textContent = teks;
    }

    function perbaruiStatus() {

        const berat =
            parseFloat(inputBerat.value);

        const usia =
            parseInt(inputUsia.value, 10);

        if (!Number.isNaN(berat)) {
            if (berat < 2.5) {
                aturBadge(
                    statusBBLR,
                    "BBLR",
                    "bg-danger"
                );
            } else {
                aturBadge(
                    statusBBLR,
                    "Tidak BBLR",
                    "bg-success"
                );
            }
        } else {
            aturBadge(
                statusBBLR,
                "Belum Dinilai",
                "bg-secondary"
            );
        }

        if (!Number.isNaN(usia)) {
            if (usia < 37) {
                aturBadge(
                    statusPrematur,
                    "Prematur",
                    "bg-warning text-dark"
                );
            } else {
                aturBadge(
                    statusPrematur,
                    "Tidak Prematur",
                    "bg-success"
                );
            }
        } else {
            aturBadge(
                statusPrematur,
                "Belum Dinilai",
                "bg-secondary"
            );
        }
    }

    inputBerat.addEventListener(
        "input",
        perbaruiStatus
    );

    inputUsia.addEventListener(
        "input",
        perbaruiStatus
    );

    perbaruiStatus();
});
</script>

<?php require_once "../includes/footer.php"; ?>