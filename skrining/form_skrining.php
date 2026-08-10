<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_gizi", "kader"]);

$roleAktif = $_SESSION["role"] ?? "";
$idPuskesmasDipilih = $roleAktif === "kader"
    ? trim($_POST["id_puskesmas"] ?? "")
    : "";

/*
|--------------------------------------------------------------------------
| Judul halaman
|--------------------------------------------------------------------------
*/

$judulHalaman =
    "Tambah Skrining Awal | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Flash hasil analisis
|--------------------------------------------------------------------------
|
| Setelah skrining disimpan, analisis_deteksi.php akan menghitung
| hasil menggunakan rumus yang sudah ada, lalu mengembalikan hasil
| ke halaman form ini melalui session.
|
| Data popup hanya ditampilkan satu kali.
|
*/

$hasilAnalisisPopup =
    $_SESSION["hasil_analisis_form"] ?? null;

if ($hasilAnalisisPopup !== null) {
    unset($_SESSION["hasil_analisis_form"]);
}

/*
|--------------------------------------------------------------------------
| Flash data skrining baru
|--------------------------------------------------------------------------
*/

$skriningBaru =
    $_SESSION["skrining_baru"] ?? null;

if ($hasilAnalisisPopup !== null && $skriningBaru !== null) {
    unset($_SESSION["skrining_baru"]);
}

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function amanFormSkrining($nilai): string
{
    return htmlspecialchars(
        (string) ($nilai ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$error = "";

$old = [
    "id_balita"          => "",
    "tinggi_badan_ibu"   => "",
    "pendidikan_ibu"     => "",
    "pekerjaan_ibu"      => "",
    "lama_asi_eksklusif" => "",
    "mpasi"              => "",
    "frekuensi_makan"    => "",
    "protein_hewani"     => "",
    "status_ekonomi"     => "",
    "sanitasi"           => "",
    "air_bersih"         => ""
];

/*
|--------------------------------------------------------------------------
| Pilihan yang diizinkan
|--------------------------------------------------------------------------
*/

$daftarPendidikan = [
    "SD",
    "SMP",
    "SMA",
    "Diploma",
    "Sarjana"
];

$daftarPekerjaan = [
    "Ibu Rumah Tangga",
    "Petani",
    "Pedagang",
    "Karyawan Swasta",
    "PNS",
    "Wiraswasta",
    "Lainnya"
];

$daftarFrekuensi = [
    "Kurang dari 3 kali",
    "3 kali",
    "Lebih dari 3 kali"
];

$daftarEkonomi = [
    "Rendah",
    "Sedang",
    "Tinggi"
];

/*
|--------------------------------------------------------------------------
| Daftar Puskesmas untuk Kader
|--------------------------------------------------------------------------
*/

$queryPuskesmas = null;

if ($roleAktif === "kader") {
    $queryPuskesmas = mysqli_query(
        $conn,
        "SELECT
            id_puskesmas,
            nama_puskesmas
         FROM puskesmas
         ORDER BY nama_puskesmas ASC"
    );

    if (!$queryPuskesmas) {
        die(
            "Gagal mengambil daftar Puskesmas: "
            . mysqli_error($conn)
        );
    }
}

/*
|--------------------------------------------------------------------------
| Proses penyimpanan
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($old as $namaKolom => $nilai) {
        $old[$namaKolom] =
            trim($_POST[$namaKolom] ?? "");
    }

    if ($roleAktif === "kader") {
        $idPuskesmasDipilih =
            trim($_POST["id_puskesmas"] ?? "");
    }

    /*
    |--------------------------------------------------------------------------
    | Mengubah tipe input
    |--------------------------------------------------------------------------
    */

    $idBalita = filter_var(
        $old["id_balita"],
        FILTER_VALIDATE_INT
    );

    $tinggiBadanIbu = filter_var(
        $old["tinggi_badan_ibu"],
        FILTER_VALIDATE_FLOAT
    );

    $lamaAsiEksklusif = filter_var(
        $old["lama_asi_eksklusif"],
        FILTER_VALIDATE_INT
    );

    /*
    |--------------------------------------------------------------------------
    | Validasi kolom wajib
    |--------------------------------------------------------------------------
    */

    if (
        $roleAktif === "kader"
        && (
            $idPuskesmasDipilih === ""
            || filter_var(
                $idPuskesmasDipilih,
                FILTER_VALIDATE_INT
            ) === false
            || (int) $idPuskesmasDipilih < 1
        )
    ) {
        $error = "Puskesmas wajib dipilih.";
    }

    foreach ($old as $nilai) {
        if ($error === "" && $nilai === "") {
            $error =
                "Semua kolom skrining wajib diisi.";
            break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi masing-masing data
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
        && (
            $idBalita === false
            || $idBalita < 1
        )
    ) {
        $error =
            "Data balita yang dipilih tidak valid.";
    }

    if (
        $error === ""
        && (
            $tinggiBadanIbu === false
            || $tinggiBadanIbu < 100
            || $tinggiBadanIbu > 220
        )
    ) {
        $error =
            "Tinggi badan ibu harus berada antara 100 sampai 220 cm.";
    }

    if (
        $error === ""
        && !in_array(
            $old["pendidikan_ibu"],
            $daftarPendidikan,
            true
        )
    ) {
        $error =
            "Pilihan pendidikan ibu tidak valid.";
    }

    if (
        $error === ""
        && !in_array(
            $old["pekerjaan_ibu"],
            $daftarPekerjaan,
            true
        )
    ) {
        $error =
            "Pilihan pekerjaan ibu tidak valid.";
    }

    if (
        $error === ""
        && (
            $lamaAsiEksklusif === false
            || $lamaAsiEksklusif < 0
            || $lamaAsiEksklusif > 24
        )
    ) {
        $error =
            "Lama ASI eksklusif harus berada antara 0 sampai 24 bulan.";
    }

    if (
        $error === ""
        && !in_array(
            $old["mpasi"],
            ["Ya", "Tidak"],
            true
        )
    ) {
        $error =
            "Pilihan MPASI tidak valid.";
    }

    if (
        $error === ""
        && !in_array(
            $old["frekuensi_makan"],
            $daftarFrekuensi,
            true
        )
    ) {
        $error =
            "Pilihan frekuensi makan tidak valid.";
    }

    if (
        $error === ""
        && !in_array(
            $old["protein_hewani"],
            ["Ya", "Tidak"],
            true
        )
    ) {
        $error =
            "Pilihan protein hewani tidak valid.";
    }

    if (
        $error === ""
        && !in_array(
            $old["status_ekonomi"],
            $daftarEkonomi,
            true
        )
    ) {
        $error =
            "Pilihan status ekonomi tidak valid.";
    }

    if (
        $error === ""
        && !in_array(
            $old["sanitasi"],
            ["Baik", "Kurang"],
            true
        )
    ) {
        $error =
            "Pilihan kondisi sanitasi tidak valid.";
    }

    if (
        $error === ""
        && !in_array(
            $old["air_bersih"],
            ["Ya", "Tidak"],
            true
        )
    ) {
        $error =
            "Pilihan ketersediaan air bersih tidak valid.";
    }

    /*
    |--------------------------------------------------------------------------
    | Memastikan balita tersedia
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        if ($roleAktif === "kader") {
            $stmtCekBalita = mysqli_prepare(
                $conn,
                "SELECT
                    id_balita,
                    nama_balita
                 FROM balita
                 WHERE id_balita = ?
                   AND id_puskesmas = ?
                 LIMIT 1"
            );
        } else {
            $stmtCekBalita = mysqli_prepare(
                $conn,
                "SELECT
                    id_balita,
                    nama_balita
                 FROM balita
                 WHERE id_balita = ?
                 LIMIT 1"
            );
        }

        if (!$stmtCekBalita) {
            $error =
                "Sistem gagal memeriksa data balita.";

        } else {

            if ($roleAktif === "kader") {
                $idPuskesmasValid = (int) $idPuskesmasDipilih;

                mysqli_stmt_bind_param(
                    $stmtCekBalita,
                    "ii",
                    $idBalita,
                    $idPuskesmasValid
                );
            } else {
                mysqli_stmt_bind_param(
                    $stmtCekBalita,
                    "i",
                    $idBalita
                );
            }

            mysqli_stmt_execute($stmtCekBalita);

            $hasilBalita =
                mysqli_stmt_get_result($stmtCekBalita);

            $dataBalita =
                mysqli_fetch_assoc($hasilBalita);

            mysqli_stmt_close($stmtCekBalita);

            if (!$dataBalita) {
                $error = $roleAktif === "kader"
                    ? "Balita tidak ditemukan pada Puskesmas yang dipilih."
                    : "Data balita tidak ditemukan.";
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan data skrining
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $pendidikanIbu =
            $old["pendidikan_ibu"];

        $pekerjaanIbu =
            $old["pekerjaan_ibu"];

        $mpasi =
            $old["mpasi"];

        $frekuensiMakan =
            $old["frekuensi_makan"];

        $proteinHewani =
            $old["protein_hewani"];

        $statusEkonomi =
            $old["status_ekonomi"];

        $sanitasi =
            $old["sanitasi"];

        $airBersih =
            $old["air_bersih"];

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO skrining_awal
            (
                id_balita,
                tinggi_badan_ibu,
                pendidikan_ibu,
                pekerjaan_ibu,
                lama_asi_eksklusif,
                mpasi,
                frekuensi_makan,
                protein_hewani,
                status_ekonomi,
                sanitasi,
                air_bersih
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmtSimpan) {
            $error =
                "Sistem gagal menyiapkan penyimpanan skrining.";

        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "idssissssss",
                $idBalita,
                $tinggiBadanIbu,
                $pendidikanIbu,
                $pekerjaanIbu,
                $lamaAsiEksklusif,
                $mpasi,
                $frekuensiMakan,
                $proteinHewani,
                $statusEkonomi,
                $sanitasi,
                $airBersih
            );

            $berhasil =
                mysqli_stmt_execute($stmtSimpan);

            if ($berhasil) {

                $idSkriningBaru =
                    (int) mysqli_insert_id($conn);

                $_SESSION["skrining_baru"] = [
                    "id_skrining" => $idSkriningBaru,
                    "id_balita" => (int) $idBalita,
                    "nama_balita" =>
                        $dataBalita["nama_balita"]
                        ?? "Balita",
                    "tinggi_badan_ibu" =>
                        $tinggiBadanIbu,
                    "pendidikan_ibu" =>
                        $pendidikanIbu,
                    "pekerjaan_ibu" =>
                        $pekerjaanIbu,
                    "lama_asi_eksklusif" =>
                        $lamaAsiEksklusif,
                    "mpasi" =>
                        $mpasi,
                    "frekuensi_makan" =>
                        $frekuensiMakan,
                    "protein_hewani" =>
                        $proteinHewani,
                    "status_ekonomi" =>
                        $statusEkonomi,
                    "sanitasi" =>
                        $sanitasi,
                    "air_bersih" =>
                        $airBersih
                ];

                mysqli_stmt_close($stmtSimpan);

                /*
                |--------------------------------------------------------------------------
                | Jalankan analisis lalu kembali ke form
                |--------------------------------------------------------------------------
                |
                | Parameter sumber=form_skrining dipakai agar
                | analisis_deteksi.php tahu bahwa hasil harus
                | dikembalikan ke halaman ini untuk popup.
                |
                */

                header(
                    "Location: ../deteksi/analisis_deteksi.php"
                    . "?id_balita="
                    . (int) $idBalita
                    . "&sumber=form_skrining"
                );

                exit;
            }

            $error =
                "Data skrining gagal disimpan.";

            mysqli_stmt_close($stmtSimpan);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Mengambil daftar balita
|--------------------------------------------------------------------------
*/

$queryBalita = mysqli_query(
    $conn,
    "SELECT
        id_balita,
        id_puskesmas,
        nama_balita
     FROM balita
     ORDER BY nama_balita ASC"
);

if (!$queryBalita) {
    die(
        "Gagal mengambil daftar balita: "
        . mysqli_error($conn)
    );
}

$jumlahBalita =
    mysqli_num_rows($queryBalita);

/*
|--------------------------------------------------------------------------
| Template utama
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <!-- Header halaman -->
        <div class="page-header">

            <div>

                <h1 class="page-title">
                    <i class="bi bi-clipboard2-heart me-2"></i>
                    Tambah Skrining Awal
                </h1>

                <p class="page-subtitle">
                    Lengkapi informasi keluarga, pola pemberian makan,
                    sanitasi, dan faktor risiko awal balita.
                </p>

            </div>

            <a
                href="hasil_skrining.php"
                class="btn btn-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Kembali ke Hasil Skrining
            </a>

        </div>

        <!-- Pesan error -->
        <?php if ($error !== ""): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-exclamation-circle me-1"></i>

                <?= amanFormSkrining($error); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>

        <?php endif; ?>

        <?php if ($jumlahBalita < 1): ?>

            <div class="card content-card">

                <div class="card-body">

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>

                        <h3>
                            Belum ada data balita
                        </h3>

                        <p>
                            Tambahkan data balita terlebih dahulu
                            sebelum mengisi skrining awal.
                        </p>

                        <a
                            href="../dashboard/dashboard.php"
                            class="btn btn-secondary mt-3"
                        >
                            <i class="bi bi-house-heart"></i>
                            Kembali ke Dashboard
                        </a>

                    </div>

                </div>

            </div>

        <?php else: ?>

            <div class="card content-card">

                <div class="card-header">

                    <div>

                        <h4 class="mb-1">
                            Form Skrining Awal Stunting
                        </h4>

                        <small class="text-muted">
                            Semua kolom pada formulir wajib diisi.
                        </small>

                    </div>

                    <span class="badge badge-info">
                        <i class="bi bi-heart-pulse"></i>
                        Skrining
                    </span>

                </div>

                <div class="card-body">

                    <form
                        method="POST"
                        action=""
                        autocomplete="off"
                    >

                        <?php if ($roleAktif === "kader"): ?>

                            <!-- Puskesmas -->
                            <div class="form-group">

                                <label
                                    for="id_puskesmas"
                                    class="form-label"
                                >
                                    Puskesmas
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="id_puskesmas"
                                    id="id_puskesmas"
                                    class="form-select"
                                    required
                                >
                                    <option value="">
                                        -- Pilih Puskesmas --
                                    </option>

                                    <?php while ($puskesmas = mysqli_fetch_assoc($queryPuskesmas)): ?>
                                        <option
                                            value="<?= (int) $puskesmas["id_puskesmas"]; ?>"
                                            <?= (
                                                (string) $idPuskesmasDipilih
                                                === (string) $puskesmas["id_puskesmas"]
                                            ) ? "selected" : ""; ?>
                                        >
                                            <?= amanFormSkrining(
                                                $puskesmas["nama_puskesmas"]
                                            ); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>

                                <small class="text-muted">
                                    Pilih Puskesmas terlebih dahulu. Daftar balita akan menyesuaikan otomatis.
                                </small>

                            </div>

                        <?php endif; ?>

                        <!-- Data balita -->
                        <div class="form-group">

                            <label
                                for="id_balita"
                                class="form-label"
                            >
                                Nama Balita
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="id_balita"
                                id="id_balita"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Pilih Balita --
                                </option>

                                <?php
                                while (
                                    $balita =
                                        mysqli_fetch_assoc(
                                            $queryBalita
                                        )
                                ):
                                ?>

                                    <option
                                        value="<?= (int) $balita["id_balita"]; ?>"
                                        data-puskesmas="<?= (int) $balita["id_puskesmas"]; ?>"
                                        <?= (
                                            (string) $old["id_balita"]
                                            ===
                                            (string) $balita["id_balita"]
                                        )
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanFormSkrining(
                                            $balita["nama_balita"]
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">
                            <i class="bi bi-person-heart me-1"></i>
                            Informasi Ibu
                        </h5>

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    for="tinggi_badan_ibu"
                                    class="form-label"
                                >
                                    Tinggi Badan Ibu
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="tinggi_badan_ibu"
                                        id="tinggi_badan_ibu"
                                        class="form-control"
                                        value="<?= amanFormSkrining(
                                            $old["tinggi_badan_ibu"]
                                        ); ?>"
                                        min="100"
                                        max="220"
                                        step="0.01"
                                        placeholder="Contoh: 155"
                                        required
                                    >

                                    <span class="input-group-text">
                                        cm
                                    </span>

                                </div>

                            </div>

                            <div class="form-group">

                                <label
                                    for="pendidikan_ibu"
                                    class="form-label"
                                >
                                    Pendidikan Ibu
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="pendidikan_ibu"
                                    id="pendidikan_ibu"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        -- Pilih Pendidikan --
                                    </option>

                                    <?php
                                    foreach (
                                        $daftarPendidikan
                                        as $pendidikan
                                    ):
                                    ?>

                                        <option
                                            value="<?= amanFormSkrining(
                                                $pendidikan
                                            ); ?>"
                                            <?= (
                                                $old["pendidikan_ibu"]
                                                === $pendidikan
                                            )
                                                ? "selected"
                                                : ""; ?>
                                        >
                                            <?= amanFormSkrining(
                                                $pendidikan
                                            ); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                        <div class="form-group">

                            <label
                                for="pekerjaan_ibu"
                                class="form-label"
                            >
                                Pekerjaan Ibu
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="pekerjaan_ibu"
                                id="pekerjaan_ibu"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Pilih Pekerjaan --
                                </option>

                                <?php
                                foreach (
                                    $daftarPekerjaan
                                    as $pekerjaan
                                ):
                                ?>

                                    <option
                                        value="<?= amanFormSkrining(
                                            $pekerjaan
                                        ); ?>"
                                        <?= (
                                            $old["pekerjaan_ibu"]
                                            === $pekerjaan
                                        )
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanFormSkrining(
                                            $pekerjaan
                                        ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">
                            <i class="bi bi-cup-straw me-1"></i>
                            Pola Pemberian Makan
                        </h5>

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    for="lama_asi_eksklusif"
                                    class="form-label"
                                >
                                    Lama ASI Eksklusif
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="lama_asi_eksklusif"
                                        id="lama_asi_eksklusif"
                                        class="form-control"
                                        value="<?= amanFormSkrining(
                                            $old[
                                                "lama_asi_eksklusif"
                                            ]
                                        ); ?>"
                                        min="0"
                                        max="24"
                                        placeholder="Contoh: 6"
                                        required
                                    >

                                    <span class="input-group-text">
                                        bulan
                                    </span>

                                </div>

                            </div>

                            <div class="form-group">

                                <label class="form-label">
                                    Pemberian MPASI
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="option-group">

                                    <label class="option-item">

                                        <input
                                            type="radio"
                                            name="mpasi"
                                            value="Ya"
                                            <?= (
                                                $old["mpasi"] === "Ya"
                                            )
                                                ? "checked"
                                                : ""; ?>
                                            required
                                        >

                                        <span>
                                            Ya
                                        </span>

                                    </label>

                                    <label class="option-item">

                                        <input
                                            type="radio"
                                            name="mpasi"
                                            value="Tidak"
                                            <?= (
                                                $old["mpasi"] === "Tidak"
                                            )
                                                ? "checked"
                                                : ""; ?>
                                        >

                                        <span>
                                            Tidak
                                        </span>

                                    </label>

                                </div>

                            </div>

                        </div>

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    for="frekuensi_makan"
                                    class="form-label"
                                >
                                    Frekuensi Makan
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="frekuensi_makan"
                                    id="frekuensi_makan"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        -- Pilih Frekuensi --
                                    </option>

                                    <?php
                                    foreach (
                                        $daftarFrekuensi
                                        as $frekuensi
                                    ):
                                    ?>

                                        <option
                                            value="<?= amanFormSkrining(
                                                $frekuensi
                                            ); ?>"
                                            <?= (
                                                $old["frekuensi_makan"]
                                                === $frekuensi
                                            )
                                                ? "selected"
                                                : ""; ?>
                                        >
                                            <?= amanFormSkrining(
                                                $frekuensi
                                            ); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="form-group">

                                <label class="form-label">
                                    Konsumsi Protein Hewani
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="option-group">

                                    <label class="option-item">

                                        <input
                                            type="radio"
                                            name="protein_hewani"
                                            value="Ya"
                                            <?= (
                                                $old["protein_hewani"]
                                                === "Ya"
                                            )
                                                ? "checked"
                                                : ""; ?>
                                            required
                                        >

                                        <span>
                                            Ya
                                        </span>

                                    </label>

                                    <label class="option-item">

                                        <input
                                            type="radio"
                                            name="protein_hewani"
                                            value="Tidak"
                                            <?= (
                                                $old["protein_hewani"]
                                                === "Tidak"
                                            )
                                                ? "checked"
                                                : ""; ?>
                                        >

                                        <span>
                                            Tidak
                                        </span>

                                    </label>

                                </div>

                            </div>

                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">
                            <i class="bi bi-house-heart me-1"></i>
                            Kondisi Lingkungan
                        </h5>

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    for="status_ekonomi"
                                    class="form-label"
                                >
                                    Status Ekonomi
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="status_ekonomi"
                                    id="status_ekonomi"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        -- Pilih Status Ekonomi --
                                    </option>

                                    <?php
                                    foreach (
                                        $daftarEkonomi
                                        as $ekonomi
                                    ):
                                    ?>

                                        <option
                                            value="<?= amanFormSkrining(
                                                $ekonomi
                                            ); ?>"
                                            <?= (
                                                $old["status_ekonomi"]
                                                === $ekonomi
                                            )
                                                ? "selected"
                                                : ""; ?>
                                        >
                                            <?= amanFormSkrining(
                                                $ekonomi
                                            ); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="form-group">

                                <label class="form-label">
                                    Kondisi Sanitasi
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="option-group">

                                    <label class="option-item">

                                        <input
                                            type="radio"
                                            name="sanitasi"
                                            value="Baik"
                                            <?= (
                                                $old["sanitasi"] === "Baik"
                                            )
                                                ? "checked"
                                                : ""; ?>
                                            required
                                        >

                                        <span>
                                            Baik
                                        </span>

                                    </label>

                                    <label class="option-item">

                                        <input
                                            type="radio"
                                            name="sanitasi"
                                            value="Kurang"
                                            <?= (
                                                $old["sanitasi"] === "Kurang"
                                            )
                                                ? "checked"
                                                : ""; ?>
                                        >

                                        <span>
                                            Kurang
                                        </span>

                                    </label>

                                </div>

                            </div>

                        </div>

                        <div class="form-group">

                            <label class="form-label">
                                Ketersediaan Air Bersih
                                <span class="text-danger">*</span>
                            </label>

                            <div class="option-group">

                                <label class="option-item">

                                    <input
                                        type="radio"
                                        name="air_bersih"
                                        value="Ya"
                                        <?= (
                                            $old["air_bersih"] === "Ya"
                                        )
                                            ? "checked"
                                            : ""; ?>
                                        required
                                    >

                                    <span>
                                        Tersedia
                                    </span>

                                </label>

                                <label class="option-item">

                                    <input
                                        type="radio"
                                        name="air_bersih"
                                        value="Tidak"
                                        <?= (
                                            $old["air_bersih"] === "Tidak"
                                        )
                                            ? "checked"
                                            : ""; ?>
                                    >

                                    <span>
                                        Tidak tersedia
                                    </span>

                                </label>

                            </div>

                        </div>

                        <div class="form-actions">

                            <button
                                type="submit"
                                name="simpan"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-floppy"></i>
                                Simpan Skrining
                            </button>

                            <a
                                href="hasil_skrining.php"
                                class="btn btn-light"
                            >
                                <i class="bi bi-x-circle"></i>
                                Batal
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<?php if (is_array($hasilAnalisisPopup)): ?>

    <?php
    $popupNamaBalita =
        amanFormSkrining(
            $hasilAnalisisPopup["nama_balita"]
            ?? "Balita"
        );

    $popupIdBalita =
        (int) (
            $hasilAnalisisPopup["id_balita"]
            ?? 0
        );

    $popupZScoreTbu =
        $hasilAnalisisPopup["z_score_tbu"]
        ?? null;

    $popupZScoreBbu =
        $hasilAnalisisPopup["z_score_bbu"]
        ?? null;

    $statusStuntingRaw =
        trim(
            (string) (
                $hasilAnalisisPopup["status_stunting"]
                ?? ""
            )
        );

    $popupStatusStunting =
        amanFormSkrining(
            $statusStuntingRaw !== ""
                ? $statusStuntingRaw
                : "-"
        );

    /*
    |--------------------------------------------------------------------------
    | Tingkat risiko stunting untuk tampilan popup
    |--------------------------------------------------------------------------
    |
    | Ini hanya ringkasan UI berdasarkan status hasil analisis yang
    | sudah dihitung. Tidak mengubah rumus WHO maupun hasil deteksi.
    |
    */

    switch ($statusStuntingRaw) {

        case "Normal":
            $tingkatRisikoStunting = "Normal";
            $kelasRisikoStunting = "badge-success";
            $ikonRisikoStunting = "bi-shield-check";
            break;

        case "Risiko Stunting":
            $tingkatRisikoStunting = "Sedang";
            $kelasRisikoStunting = "badge-warning";
            $ikonRisikoStunting = "bi-exclamation-triangle";
            break;

        case "Stunting":
        case "Stunting Berat":
            $tingkatRisikoStunting = "Tinggi";
            $kelasRisikoStunting = "badge-danger";
            $ikonRisikoStunting = "bi-exclamation-octagon";
            break;

        default:
            $tingkatRisikoStunting =
                "Belum Dapat Dinilai";
            $kelasRisikoStunting =
                "badge-secondary";
            $ikonRisikoStunting =
                "bi-question-circle";
            break;
    }

    $popupStatusGizi =
        amanFormSkrining(
            $hasilAnalisisPopup["status_gizi"]
            ?? "-"
        );

    $kelasStunting =
        amanFormSkrining(
            $hasilAnalisisPopup["kelas_status_stunting"]
            ?? "badge-secondary"
        );

    $kelasGizi =
        amanFormSkrining(
            $hasilAnalisisPopup["kelas_status_gizi"]
            ?? "badge-secondary"
        );

    $keteranganStunting =
        amanFormSkrining(
            $hasilAnalisisPopup["keterangan_stunting"]
            ?? ""
        );

    $keteranganGizi =
        amanFormSkrining(
            $hasilAnalisisPopup["keterangan_gizi"]
            ?? ""
        );
    ?>

    <div
        class="modal fade"
        id="modalHasilSkrining"
        tabindex="-1"
        aria-labelledby="modalHasilSkriningLabel"
        aria-hidden="true"
        data-bs-backdrop="static"
    >
        <div
            class="modal-dialog
            modal-dialog-centered
            modal-lg"
        >

            <div class="modal-content">

                <div class="modal-header">

                    <div>

                        <h5
                            class="modal-title"
                            id="modalHasilSkriningLabel"
                        >
                            <i class="bi bi-clipboard2-pulse me-2"></i>
                            Hasil Analisis
                        </h5>

                        <small class="text-muted">
                            Skrining berhasil disimpan dan
                            analisis pertumbuhan telah selesai.
                        </small>

                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup"
                    ></button>

                </div>

                <div class="modal-body">

                    <div class="text-center mb-4">

                        <div
                            class="empty-state-icon
                            mx-auto mb-2"
                            style="
                                width: 64px;
                                height: 64px;
                            "
                        >
                            <i class="bi bi-person-heart"></i>
                        </div>

                        <h4 class="mb-1">
                            <?= $popupNamaBalita; ?>
                        </h4>

                        <p class="text-muted mb-0">
                            Hasil berdasarkan data antropometri
                            terbaru balita.
                        </p>

                    </div>

                    <div
                        class="card border-0 bg-light mb-3"
                    >
                        <div
                            class="card-body
                            d-flex flex-column
                            flex-md-row
                            justify-content-between
                            align-items-md-center
                            gap-3"
                        >

                            <div>

                                <small class="text-muted">
                                    Tingkat Risiko Stunting
                                </small>

                                <h4 class="mb-1">
                                    <?= amanFormSkrining(
                                        $tingkatRisikoStunting
                                    ); ?>
                                </h4>

                                <small class="text-muted">
                                    Ringkasan berdasarkan status
                                    analisis TB/U.
                                </small>

                            </div>

                            <span
                                class="badge
                                <?= amanFormSkrining(
                                    $kelasRisikoStunting
                                ); ?>"
                                style="
                                    font-size: 0.95rem;
                                    padding: 0.75rem 1rem;
                                "
                            >
                                <i
                                    class="bi
                                    <?= amanFormSkrining(
                                        $ikonRisikoStunting
                                    ); ?>
                                    me-1"
                                ></i>

                                <?= amanFormSkrining(
                                    $tingkatRisikoStunting
                                ); ?>
                            </span>

                        </div>
                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-md-6">

                            <div
                                class="card h-100
                                border-0 bg-light"
                            >
                                <div class="card-body">

                                    <small class="text-muted">
                                        Z-Score TB/U
                                    </small>

                                    <h2 class="mb-2">
                                        <?= $popupZScoreTbu !== null
                                            ? amanFormSkrining(
                                                number_format(
                                                    (float) $popupZScoreTbu,
                                                    2,
                                                    ".",
                                                    ""
                                                )
                                            )
                                            : "-"; ?>
                                    </h2>

                                    <span
                                        class="badge
                                        <?= $kelasStunting; ?>"
                                    >
                                        <?= $popupStatusStunting; ?>
                                    </span>

                                    <?php if (
                                        $keteranganStunting !== ""
                                    ): ?>

                                        <p
                                            class="small
                                            text-muted mt-3 mb-0"
                                        >
                                            <?= $keteranganStunting; ?>
                                        </p>

                                    <?php endif; ?>

                                </div>
                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <div
                                class="card h-100
                                border-0 bg-light"
                            >
                                <div class="card-body">

                                    <small class="text-muted">
                                        Z-Score BB/U
                                    </small>

                                    <h2 class="mb-2">
                                        <?= $popupZScoreBbu !== null
                                            ? amanFormSkrining(
                                                number_format(
                                                    (float) $popupZScoreBbu,
                                                    2,
                                                    ".",
                                                    ""
                                                )
                                            )
                                            : "-"; ?>
                                    </h2>

                                    <span
                                        class="badge
                                        <?= $kelasGizi; ?>"
                                    >
                                        <?= $popupStatusGizi; ?>
                                    </span>

                                    <?php if (
                                        $keteranganGizi !== ""
                                    ): ?>

                                        <p
                                            class="small
                                            text-muted mt-3 mb-0"
                                        >
                                            <?= $keteranganGizi; ?>
                                        </p>

                                    <?php endif; ?>

                                </div>
                            </div>

                        </div>

                    </div>

                    <div
                        class="alert alert-info
                        mt-3 mb-0"
                    >
                        <i class="bi bi-info-circle me-1"></i>
                        Hasil ini berasal dari proses analisis
                        antropometri yang digunakan sistem.
                    </div>

                </div>

                <div class="modal-footer">

                    <a
                        href="hasil_skrining.php"
                        class="btn btn-light"
                    >
                        <i class="bi bi-table"></i>
                        Lihat Daftar Skrining
                    </a>

                    <a
                        href="../deteksi/hasil_deteksi.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-clipboard2-data"></i>
                        Lihat Hasil Deteksi
                    </a>

                </div>

            </div>

        </div>
    </div>

    <script>
        window.addEventListener(
            "load",
            function () {
                const elemenModal =
                    document.getElementById(
                        "modalHasilSkrining"
                    );

                if (
                    elemenModal
                    && typeof bootstrap !== "undefined"
                ) {
                    const modalHasil =
                        new bootstrap.Modal(
                            elemenModal
                        );

                    modalHasil.show();
                }
            }
        );
    </script>

<?php endif; ?>

<?php if ($roleAktif === "kader"): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const puskesmasSelect = document.getElementById("id_puskesmas");
    const balitaSelect = document.getElementById("id_balita");

    if (!puskesmasSelect || !balitaSelect) {
        return;
    }

    function filterBalita(resetPilihan = false) {
        const idPuskesmas = puskesmasSelect.value;
        const pilihanSekarang = balitaSelect.value;
        let pilihanMasihValid = false;

        Array.from(balitaSelect.options).forEach(function (option, index) {
            if (index === 0) {
                return;
            }

            const cocok =
                idPuskesmas !== ""
                && option.dataset.puskesmas === idPuskesmas;

            option.hidden = !cocok;
            option.disabled = !cocok;

            if (cocok && option.value === pilihanSekarang) {
                pilihanMasihValid = true;
            }
        });

        if (resetPilihan || !pilihanMasihValid) {
            balitaSelect.value = "";
        }

        balitaSelect.disabled = idPuskesmas === "";
        balitaSelect.options[0].textContent = idPuskesmas === ""
            ? "-- Pilih Puskesmas terlebih dahulu --"
            : "-- Pilih Balita --";
    }

    puskesmasSelect.addEventListener("change", function () {
        filterBalita(true);
    });

    filterBalita(false);
});
</script>
<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>