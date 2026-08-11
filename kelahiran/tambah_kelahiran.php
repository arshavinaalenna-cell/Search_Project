<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_kia"
]);

$judulHalaman =
    "Tambah Riwayat Kelahiran | Sistem Deteksi Stunting";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

$error = "";

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

mysqli_stmt_execute($stmtPuskesmasAkun);

$hasilPuskesmasAkun =
    mysqli_stmt_get_result($stmtPuskesmasAkun);

$dataPuskesmasAkun =
    mysqli_fetch_assoc($hasilPuskesmasAkun);

mysqli_stmt_close($stmtPuskesmasAkun);

if (
    !$dataPuskesmasAkun
    || empty($dataPuskesmasAkun["id_puskesmas"])
) {
    $puskesmasBelumTerhubung = true;
} else {
    $idPuskesmasAktif =
        (int) $dataPuskesmasAkun["id_puskesmas"];

    $namaPuskesmasAktif =
        trim(
            (string) (
                $dataPuskesmasAkun["nama_puskesmas"]
                ?? ""
            )
        );
}

$old = [
    "id_balita" => "",
    "berat_lahir" => "",
    "panjang_lahir" => "",
    "usia_kehamilan" => "",
    "jenis_persalinan" => "",
    "usia_ibu_melahirkan" => "",
    "riwayat_kehamilan" => "",
    "komplikasi_kehamilan" => ""
];

/*
|--------------------------------------------------------------------------
| Fungsi output aman
|--------------------------------------------------------------------------
*/

function amanTambahKelahiran($nilai): string
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Simpan data
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && !$puskesmasBelumTerhubung
) {

    $old["id_balita"] =
        trim($_POST["id_balita"] ?? "");

    $old["berat_lahir"] =
        trim($_POST["berat_lahir"] ?? "");

    $old["panjang_lahir"] =
        trim($_POST["panjang_lahir"] ?? "");

    $old["usia_kehamilan"] =
        trim($_POST["usia_kehamilan"] ?? "");

    $old["jenis_persalinan"] =
        trim($_POST["jenis_persalinan"] ?? "");

    $old["usia_ibu_melahirkan"] =
        trim($_POST["usia_ibu_melahirkan"] ?? "");

    $old["riwayat_kehamilan"] =
        trim($_POST["riwayat_kehamilan"] ?? "");

    $old["komplikasi_kehamilan"] =
        trim($_POST["komplikasi_kehamilan"] ?? "");

    $idBalita = filter_var(
        $old["id_balita"],
        FILTER_VALIDATE_INT
    );

    $beratLahir = filter_var(
        $old["berat_lahir"],
        FILTER_VALIDATE_FLOAT
    );

    $panjangLahir = filter_var(
        $old["panjang_lahir"],
        FILTER_VALIDATE_FLOAT
    );

    $usiaKehamilan = filter_var(
        $old["usia_kehamilan"],
        FILTER_VALIDATE_INT
    );

    $usiaIbuMelahirkan = filter_var(
        $old["usia_ibu_melahirkan"],
        FILTER_VALIDATE_INT
    );

    if (
        $old["id_balita"] === ""
        || $old["berat_lahir"] === ""
        || $old["panjang_lahir"] === ""
        || $old["usia_kehamilan"] === ""
        || $old["jenis_persalinan"] === ""
        || $old["usia_ibu_melahirkan"] === ""
    ) {
        $error =
            "Nama balita, berat lahir, panjang lahir, usia kehamilan, jenis persalinan, dan usia ibu saat melahirkan wajib diisi.";

    } elseif (
        $idBalita === false
        || $idBalita < 1
    ) {
        $error =
            "Data balita tidak valid.";

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

        /*
        |--------------------------------------------------------------------------
        | Pastikan balita ada
        |--------------------------------------------------------------------------
        */

        $stmtBalita = mysqli_prepare(
            $conn,
            "SELECT id_balita
             FROM balita
             WHERE id_balita = ?
             AND id_puskesmas = ?
             LIMIT 1"
        );

        if (!$stmtBalita) {
            die(
                "Gagal memeriksa data balita: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmtBalita,
            "ii",
            $idBalita,
            $idPuskesmasAktif
        );

        mysqli_stmt_execute(
            $stmtBalita
        );

        $hasilBalita =
            mysqli_stmt_get_result(
                $stmtBalita
            );

        $dataBalita =
            mysqli_fetch_assoc(
                $hasilBalita
            );

        mysqli_stmt_close(
            $stmtBalita
        );

        if (!$dataBalita) {
            $error =
                "Balita tidak ditemukan atau tidak termasuk Puskesmas akun Petugas KIA.";

        } else {

            $stmtDuplikat = mysqli_prepare(
                $conn,
                "SELECT 1
                 FROM riwayat_kelahiran
                 WHERE id_balita = ?
                 LIMIT 1"
            );

            if (!$stmtDuplikat) {
                die(
                    "Gagal memeriksa riwayat kelahiran: "
                    . mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $stmtDuplikat,
                "i",
                $idBalita
            );

            mysqli_stmt_execute($stmtDuplikat);

            $hasilDuplikat =
                mysqli_stmt_get_result($stmtDuplikat);

            $sudahAdaRiwayat =
                mysqli_fetch_assoc($hasilDuplikat);

            mysqli_stmt_close($stmtDuplikat);

            if ($sudahAdaRiwayat) {

                $error =
                    "Balita yang dipilih sudah memiliki riwayat kelahiran.";

            } else {

            $stmt = mysqli_prepare(
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

            if (!$stmt) {
                die(
                    "Gagal menyiapkan penyimpanan data: "
                    . mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "iddisiss",
                $idBalita,
                $beratLahir,
                $panjangLahir,
                $usiaKehamilan,
                $old["jenis_persalinan"],
                $usiaIbuMelahirkan,
                $old["riwayat_kehamilan"],
                $old["komplikasi_kehamilan"]
            );

            if (
                mysqli_stmt_execute($stmt)
            ) {
                mysqli_stmt_close(
                    $stmt
                );

                header(
                    "Location: riwayat_kelahiran.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $error =
                "Data gagal disimpan.";

            mysqli_stmt_close(
                $stmt
            );
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Data balita
|--------------------------------------------------------------------------
|
| Dropdown hanya menampilkan balita dari Puskesmas Petugas KIA aktif
| yang belum memiliki riwayat kelahiran.
|
*/

if ($puskesmasBelumTerhubung) {

    $balita = false;

} else {

    $stmtDaftarBalita = mysqli_prepare(
        $conn,
        "SELECT
            b.id_balita,
            b.nik_balita,
            b.nama_balita
         FROM balita AS b
         LEFT JOIN riwayat_kelahiran AS rk
            ON rk.id_balita = b.id_balita
         WHERE b.id_puskesmas = ?
         AND rk.id_balita IS NULL
         ORDER BY b.nama_balita ASC"
    );

    if (!$stmtDaftarBalita) {
        die(
            "Gagal menyiapkan data balita: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtDaftarBalita,
        "i",
        $idPuskesmasAktif
    );

    mysqli_stmt_execute($stmtDaftarBalita);

    $balita =
        mysqli_stmt_get_result($stmtDaftarBalita);

    if (!$balita) {
        mysqli_stmt_close($stmtDaftarBalita);
        die("Gagal mengambil data balita.");
    }
}

$adaBalitaTersedia =
    $balita
    && mysqli_num_rows($balita) > 0;

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

                <div class="d-flex flex-wrap gap-2">

                    <?php if (!$puskesmasBelumTerhubung): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex align-items-center px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= amanTambahKelahiran(
                                $namaPuskesmasAktif
                            ); ?>
                        </span>

                    <?php endif; ?>

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

                <?php if ($puskesmasBelumTerhubung): ?>

                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Akun Petugas KIA belum terhubung dengan
                        Puskesmas. Hubungkan akun ke Puskesmas
                        terlebih dahulu sebelum menambah
                        riwayat kelahiran.
                    </div>

                <?php endif; ?>

                <?php if ($error !== ""): ?>

                    <div
                        class="alert alert-danger"
                        role="alert"
                    >
                        <?= amanTambahKelahiran(
                            $error
                        ); ?>
                    </div>

                <?php endif; ?>

                <?php if (!$puskesmasBelumTerhubung): ?>

                <div class="detail-item mb-4">
                    <span class="detail-label">
                        Puskesmas
                    </span>

                    <span class="detail-value">
                        <i class="bi bi-hospital me-1"></i>
                        <?= amanTambahKelahiran(
                            $namaPuskesmasAktif
                        ); ?>
                    </span>

                    <div class="form-text mt-1">
                        Wilayah otomatis mengikuti akun
                        Petugas KIA dan tidak dapat diubah.
                    </div>
                </div>

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12">

                            <label class="form-label">
                                Nama Balita
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                id="id_balita"
                                name="id_balita"
                                class="form-select"
                                <?= !$adaBalitaTersedia
                                    ? "disabled"
                                    : ""; ?>
                            >

                                <option value="">
                                    -- Pilih Balita --
                                </option>

                                <?php if ($adaBalitaTersedia): ?>

                                    <?php while (
                                        $b = mysqli_fetch_assoc($balita)
                                    ): ?>

                                        <option
                                            value="<?= (int) $b["id_balita"]; ?>"
                                            <?= (
                                                (string) $old["id_balita"]
                                                ===
                                                (string) $b["id_balita"]
                                            )
                                                ? "selected"
                                                : ""; ?>
                                        >
                                            <?= amanTambahKelahiran(
                                                $b["nama_balita"]
                                            ); ?>

                                            (<?= amanTambahKelahiran(
                                                $b["nik_balita"]
                                            ); ?>)
                                        </option>

                                    <?php endwhile; ?>

                                <?php endif; ?>

                            </select>

                            <div class="form-text">
                                Hanya balita dari Puskesmas akun
                                yang belum memiliki riwayat kelahiran
                                yang ditampilkan.
                            </div>

                            <?php if (!$adaBalitaTersedia): ?>

                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Tidak ada balita yang dapat dipilih.
                                    Semua balita pada Puskesmas ini
                                    mungkin sudah memiliki riwayat
                                    kelahiran atau belum ada data balita.
                                </div>

                            <?php endif; ?>

                        </div>

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
                                    value="<?= amanTambahKelahiran(
                                        $old["berat_lahir"]
                                    ); ?>"
                                    placeholder="Contoh: 3.20"
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
                                    value="<?= amanTambahKelahiran(
                                        $old["panjang_lahir"]
                                    ); ?>"
                                    placeholder="Contoh: 49"
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
                                    value="<?= amanTambahKelahiran(
                                        $old["usia_kehamilan"]
                                    ); ?>"
                                    placeholder="Contoh: 39"
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

                                <option value="">
                                    -- Pilih Jenis Persalinan --
                                </option>

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
                                        value="<?= amanTambahKelahiran(
                                            $jenis
                                        ); ?>"
                                        <?= $old["jenis_persalinan"]
                                            === $jenis
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanTambahKelahiran(
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
                                    value="<?= amanTambahKelahiran(
                                        $old["usia_ibu_melahirkan"]
                                    ); ?>"
                                    placeholder="Contoh: 27"
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
                            ><?= amanTambahKelahiran(
                                $old["riwayat_kehamilan"]
                            ); ?></textarea>

                            <div class="form-text">
                                Opsional. Isi informasi penting
                                selama masa kehamilan.
                            </div>

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
                            ><?= amanTambahKelahiran(
                                $old["komplikasi_kehamilan"]
                            ); ?></textarea>

                            <div class="form-text">
                                Opsional. Isi jika terdapat
                                komplikasi selama kehamilan.
                            </div>

                        </div>

                    </div>

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= !$adaBalitaTersedia
                                ? "disabled"
                                : ""; ?>
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


<?php
if (isset($stmtDaftarBalita)) {
    mysqli_stmt_close($stmtDaftarBalita);
}
require_once "../includes/footer.php";
?>