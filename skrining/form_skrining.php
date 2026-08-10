<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["kader"]);

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
                    b.id_balita,
                    b.nama_balita,
                    ot.nama_ibu,
                    ot.pendidikan_ibu,
                    ot.pekerjaan_ibu
                 FROM balita AS b
                 LEFT JOIN orang_tua AS ot
                    ON b.id_user = ot.id_user
                 WHERE b.id_balita = ?
                   AND b.id_puskesmas = ?
                 LIMIT 1"
            );
        } else {
            $stmtCekBalita = mysqli_prepare(
                $conn,
                "SELECT
                    b.id_balita,
                    b.nama_balita,
                    ot.nama_ibu,
                    ot.pendidikan_ibu,
                    ot.pekerjaan_ibu
                 FROM balita AS b
                 LEFT JOIN orang_tua AS ot
                    ON b.id_user = ot.id_user
                 WHERE b.id_balita = ?
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
                $error =
                    "Balita tidak ditemukan pada Puskesmas yang dipilih.";
            } else {

                $namaIbuProfil =
                    trim(
                        (string) (
                            $dataBalita["nama_ibu"]
                            ?? ""
                        )
                    );

                $pendidikanIbuProfil =
                    trim(
                        (string) (
                            $dataBalita["pendidikan_ibu"]
                            ?? ""
                        )
                    );

                $pekerjaanIbuProfil =
                    trim(
                        (string) (
                            $dataBalita["pekerjaan_ibu"]
                            ?? ""
                        )
                    );

                if (
                    $namaIbuProfil === ""
                    || $pendidikanIbuProfil === ""
                    || $pekerjaanIbuProfil === ""
                ) {
                    $error =
                        "Balita sudah ditemukan, tetapi Profil Ibu belum terhubung atau belum lengkap. Hubungkan Profil Ibu melalui Edit Data Balita terlebih dahulu.";
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan data skrining
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        /*
        |--------------------------------------------------------------------------
        | Snapshot Profil Ibu
        |--------------------------------------------------------------------------
        |
        | Pendidikan dan pekerjaan tidak diinput ulang oleh Kader.
        | Nilai diambil langsung dari tabel orang_tua lalu disimpan ke
        | skrining_awal agar histori skrining tetap utuh.
        |
        */

        $pendidikanIbu =
            $pendidikanIbuProfil;

        $pekerjaanIbu =
            $pekerjaanIbuProfil;

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
                | Kader selesai pada tahap skrining
                |--------------------------------------------------------------------------
                |
                | Analisis/deteksi selanjutnya dilakukan Petugas Gizi.
                | Kader tidak diarahkan ke analisis_deteksi.php karena
                | halaman analisis memang memiliki hak akses Petugas Gizi.
                |
                */

                header(
                    "Location: hasil_skrining.php"
                    . "?pesan=tambah_berhasil"
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
        b.id_balita,
        b.id_user,
        b.id_puskesmas,
        b.nama_balita,
        b.nama_ibu AS nama_ibu_lama,
        ot.id_orang_tua,
        ot.nama_ibu AS profil_nama_ibu,
        ot.pendidikan_ibu,
        ot.pekerjaan_ibu
     FROM balita AS b
     LEFT JOIN orang_tua AS ot
        ON b.id_user = ot.id_user
     ORDER BY b.nama_balita ASC"
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
| Menghitung balita yang belum terhubung Profil Ibu
|--------------------------------------------------------------------------
*/

$jumlahBalitaBelumTerhubung = 0;

$queryBelumTerhubung = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM balita AS b
     LEFT JOIN orang_tua AS ot
        ON b.id_user = ot.id_user
     WHERE b.id_user IS NULL
        OR ot.id_orang_tua IS NULL"
);

if ($queryBelumTerhubung) {

    $dataBelumTerhubung =
        mysqli_fetch_assoc(
            $queryBelumTerhubung
        );

    $jumlahBalitaBelumTerhubung =
        (int) (
            $dataBelumTerhubung["total"]
            ?? 0
        );
}

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
                    Pilih data balita, lalu sistem akan membaca
                    Profil Ibu yang terhubung secara otomatis.
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

        <?php if (
            $jumlahBalitaBelumTerhubung > 0
        ): ?>

            <div class="alert alert-warning">

                <i class="bi bi-exclamation-triangle me-1"></i>

                Ada
                <strong>
                    <?= $jumlahBalitaBelumTerhubung; ?>
                </strong>
                data balita yang belum terhubung dengan
                Profil Ibu. Data balita tetap ditampilkan
                pada pilihan, tetapi skrining hanya dapat
                disimpan setelah Profil Ibu dihubungkan.

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
                            Belum ada data balita di dalam sistem.
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
                            Data identitas Ibu diambil otomatis dari Profil Ibu.
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

                                    $namaIbuProfilPilihan =
                                        trim(
                                            (string) (
                                                $balita[
                                                    "profil_nama_ibu"
                                                ]
                                                ?? ""
                                            )
                                        );

                                    $namaIbuLamaPilihan =
                                        trim(
                                            (string) (
                                                $balita[
                                                    "nama_ibu_lama"
                                                ]
                                                ?? ""
                                            )
                                        );

                                    $namaIbuTampilPilihan =
                                        $namaIbuProfilPilihan !== ""
                                            ? $namaIbuProfilPilihan
                                            : (
                                                $namaIbuLamaPilihan !== ""
                                                    ? $namaIbuLamaPilihan
                                                    : "-"
                                            );

                                    $profilIbuTerhubungPilihan =
                                        !empty(
                                            $balita[
                                                "id_orang_tua"
                                            ]
                                        )
                                        && $namaIbuProfilPilihan !== ""
                                        && trim(
                                            (string) (
                                                $balita[
                                                    "pendidikan_ibu"
                                                ]
                                                ?? ""
                                            )
                                        ) !== ""
                                        && trim(
                                            (string) (
                                                $balita[
                                                    "pekerjaan_ibu"
                                                ]
                                                ?? ""
                                            )
                                        ) !== "";
                                ?>

                                    <option
                                        value="<?= (int) $balita["id_balita"]; ?>"
                                        data-puskesmas="<?= (int) $balita["id_puskesmas"]; ?>"
                                        data-nama-ibu="<?= amanFormSkrining(
                                            $namaIbuTampilPilihan
                                        ); ?>"
                                        data-pendidikan-ibu="<?= amanFormSkrining(
                                            $balita["pendidikan_ibu"]
                                            ?? ""
                                        ); ?>"
                                        data-pekerjaan-ibu="<?= amanFormSkrining(
                                            $balita["pekerjaan_ibu"]
                                            ?? ""
                                        ); ?>"
                                        data-profil-terhubung="<?= $profilIbuTerhubungPilihan
                                            ? "1"
                                            : "0"; ?>"
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

                                        <?= $profilIbuTerhubungPilihan
                                            ? " — "
                                                . amanFormSkrining(
                                                    $namaIbuTampilPilihan
                                                )
                                            : " — Profil Ibu belum terhubung"; ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">
                            <i class="bi bi-person-heart me-1"></i>
                            Informasi Ibu
                        </h5>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-1"></i>
                            Nama, pendidikan, dan pekerjaan Ibu berasal
                            dari <strong>Profil Ibu</strong> dan tidak
                            perlu diinput ulang saat skrining.
                        </div>

                        <div class="row g-3 mb-3">

                            <div class="col-12 col-lg-4">

                                <div class="detail-item h-100">

                                    <span class="detail-label">
                                        Nama Ibu
                                    </span>

                                    <div
                                        class="detail-value"
                                        id="profil_nama_ibu"
                                    >
                                        Pilih balita terlebih dahulu
                                    </div>

                                </div>

                            </div>

                            <div class="col-12 col-lg-4">

                                <div class="detail-item h-100">

                                    <span class="detail-label">
                                        Pendidikan Ibu
                                    </span>

                                    <div
                                        class="detail-value"
                                        id="profil_pendidikan_ibu"
                                    >
                                        -
                                    </div>

                                </div>

                            </div>

                            <div class="col-12 col-lg-4">

                                <div class="detail-item h-100">

                                    <span class="detail-label">
                                        Pekerjaan Ibu
                                    </span>

                                    <div
                                        class="detail-value"
                                        id="profil_pekerjaan_ibu"
                                    >
                                        -
                                    </div>

                                </div>

                            </div>

                        </div>

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

                            <small class="text-muted">
                                Tinggi badan Ibu tetap diisi pada skrining
                                karena merupakan variabel faktor risiko,
                                bukan data profil akun.
                            </small>

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

<?php if ($roleAktif === "kader"): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const puskesmasSelect =
        document.getElementById("id_puskesmas");

    const balitaSelect =
        document.getElementById("id_balita");

    const namaIbu =
        document.getElementById("profil_nama_ibu");

    const pendidikanIbu =
        document.getElementById("profil_pendidikan_ibu");

    const pekerjaanIbu =
        document.getElementById("profil_pekerjaan_ibu");

    if (!puskesmasSelect || !balitaSelect) {
        return;
    }

    function tampilkanProfilIbu() {
        const option =
            balitaSelect.options[
                balitaSelect.selectedIndex
            ];

        if (
            !option
            || option.value === ""
        ) {
            if (namaIbu) {
                namaIbu.textContent =
                    "Pilih balita terlebih dahulu";
            }

            if (pendidikanIbu) {
                pendidikanIbu.textContent = "-";
            }

            if (pekerjaanIbu) {
                pekerjaanIbu.textContent = "-";
            }

            return;
        }

        const profilTerhubung =
            option.dataset.profilTerhubung === "1";

        if (!profilTerhubung) {

            if (namaIbu) {
                namaIbu.textContent =
                    option.dataset.namaIbu
                    && option.dataset.namaIbu !== "-"
                        ? option.dataset.namaIbu
                            + " (profil belum terhubung)"
                        : "Profil Ibu belum terhubung";
            }

            if (pendidikanIbu) {
                pendidikanIbu.textContent =
                    "Belum tersedia";
            }

            if (pekerjaanIbu) {
                pekerjaanIbu.textContent =
                    "Belum tersedia";
            }

            return;
        }

        if (namaIbu) {
            namaIbu.textContent =
                option.dataset.namaIbu || "-";
        }

        if (pendidikanIbu) {
            pendidikanIbu.textContent =
                option.dataset.pendidikanIbu || "-";
        }

        if (pekerjaanIbu) {
            pekerjaanIbu.textContent =
                option.dataset.pekerjaanIbu || "-";
        }
    }

    function filterBalita(resetPilihan = false) {
        const idPuskesmas =
            puskesmasSelect.value;

        const pilihanSekarang =
            balitaSelect.value;

        let pilihanMasihValid = false;

        Array.from(
            balitaSelect.options
        ).forEach(function (option, index) {

            if (index === 0) {
                return;
            }

            const cocok =
                idPuskesmas !== ""
                && option.dataset.puskesmas
                    === idPuskesmas;

            option.hidden = !cocok;
            option.disabled = !cocok;

            if (
                cocok
                && option.value
                    === pilihanSekarang
            ) {
                pilihanMasihValid = true;
            }
        });

        if (
            resetPilihan
            || !pilihanMasihValid
        ) {
            balitaSelect.value = "";
        }

        balitaSelect.disabled =
            idPuskesmas === "";

        balitaSelect.options[0].textContent =
            idPuskesmas === ""
                ? "-- Pilih Puskesmas terlebih dahulu --"
                : "-- Pilih Balita --";

        tampilkanProfilIbu();
    }

    puskesmasSelect.addEventListener(
        "change",
        function () {
            filterBalita(true);
        }
    );

    balitaSelect.addEventListener(
        "change",
        tampilkanProfilIbu
    );

    filterBalita(false);
    tampilkanProfilIbu();
});
</script>
<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>