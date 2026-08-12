<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Zona waktu aplikasi - WIB / Jakarta
|--------------------------------------------------------------------------
|
| Seluruh tanggal "hari ini" pada menu antropometri mengikuti waktu
| Indonesia Barat (UTC+7), bukan timezone bawaan server/Laragon.
|
*/

date_default_timezone_set("Asia/Jakarta");

// Samakan timezone sesi MySQL jika ada query yang memakai NOW()/CURDATE().
@mysqli_query($conn, "SET time_zone = '+07:00'");

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
*/

cekRole(["kader"]);

$judulHalaman =
    "Tambah Pengukuran | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function amanPengukuran($nilai): string
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
    "id_balita"               => "",
    "tanggal_pengukuran"      => date("Y-m-d"),
    "umur_bulan"              => "",
    "berat_badan"             => "",
    "tinggi_panjang_badan"    => "",
    "lingkar_kepala"          => "",
    "lila"                    => ""
];

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Kader aktif
|--------------------------------------------------------------------------
|
| Kader TIDAK memilih Puskesmas secara manual.
| Puskesmas selalu mengikuti pengguna.id_puskesmas akun Kader yang login.
|
*/

$idKaderAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

$stmtPuskesmasAktif = mysqli_prepare(
    $conn,
    "SELECT
        u.id_puskesmas,
        p.nama_puskesmas
     FROM pengguna AS u
     LEFT JOIN puskesmas AS p
        ON u.id_puskesmas = p.id_puskesmas
     WHERE u.id_user = ?
       AND u.role = 'kader'
     LIMIT 1"
);

if (!$stmtPuskesmasAktif) {
    die(
        "Gagal memeriksa Puskesmas Kader: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPuskesmasAktif,
    "i",
    $idKaderAktif
);

mysqli_stmt_execute(
    $stmtPuskesmasAktif
);

$hasilPuskesmasAktif =
    mysqli_stmt_get_result(
        $stmtPuskesmasAktif
    );

$dataPuskesmasAktif =
    mysqli_fetch_assoc(
        $hasilPuskesmasAktif
    );

mysqli_stmt_close(
    $stmtPuskesmasAktif
);

if (
    !$dataPuskesmasAktif
    || empty(
        $dataPuskesmasAktif[
            "id_puskesmas"
        ]
    )
    || empty(
        $dataPuskesmasAktif[
            "nama_puskesmas"
        ]
    )
) {
    $puskesmasBelumTerhubung = true;
} else {
    $idPuskesmasAktif =
        (int) $dataPuskesmasAktif[
            "id_puskesmas"
        ];

    $namaPuskesmasAktif =
        trim(
            (string) $dataPuskesmasAktif[
                "nama_puskesmas"
            ]
        );
}

/*
|--------------------------------------------------------------------------
| Mengambil balita khusus Puskesmas Kader
|--------------------------------------------------------------------------
*/

$daftarBalita = [];

if (!$puskesmasBelumTerhubung) {

    $stmtBalita = mysqli_prepare(
        $conn,
        "SELECT
            id_balita,
            nik_balita,
            nama_balita,
            tanggal_lahir
         FROM balita
         WHERE id_puskesmas = ?
         ORDER BY nama_balita ASC"
    );

    if (!$stmtBalita) {
        die(
            "Gagal mengambil daftar balita: "
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

    $hasilBalita =
        mysqli_stmt_get_result(
            $stmtBalita
        );

    while (
        $balita =
            mysqli_fetch_assoc(
                $hasilBalita
            )
    ) {
        $daftarBalita[] = $balita;
    }

    mysqli_stmt_close(
        $stmtBalita
    );
}

$jumlahBalita =
    count($daftarBalita);

/*
|--------------------------------------------------------------------------
| Pilih otomatis balita dari Dashboard Kader
|--------------------------------------------------------------------------
| Parameter id_balita hanya dipakai jika balita tersebut memang berada pada
| Puskesmas akun Kader. Validasi server-side ini mencegah manipulasi URL.
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $idBalitaDariDashboard =
        (int) ($_GET["id_balita"] ?? 0);

    if ($idBalitaDariDashboard > 0) {
        foreach ($daftarBalita as $balitaTersedia) {
            if (
                (int) ($balitaTersedia["id_balita"] ?? 0)
                === $idBalitaDariDashboard
            ) {
                $old["id_balita"] =
                    (string) $idBalitaDariDashboard;
                break;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Proses penyimpanan
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $old["id_balita"] =
        trim($_POST["id_balita"] ?? "");

    $old["tanggal_pengukuran"] =
        trim($_POST["tanggal_pengukuran"] ?? "");

    /*
    | Umur tidak diambil dari input manual.
    | Backend menghitung ulang dari tanggal_lahir balita
    | dan tanggal_pengukuran.
    */
    $old["umur_bulan"] = "";

    $old["berat_badan"] =
        trim($_POST["berat_badan"] ?? "");

    $old["tinggi_panjang_badan"] =
        trim($_POST["tinggi_panjang_badan"] ?? "");

    $old["lingkar_kepala"] =
        trim($_POST["lingkar_kepala"] ?? "");

    $old["lila"] =
        trim($_POST["lila"] ?? "");

    $idBalita = filter_var(
        $old["id_balita"],
        FILTER_VALIDATE_INT
    );

    $tanggalPengukuran =
        $old["tanggal_pengukuran"];

    $umurBulan = null;

    $beratBadan = filter_var(
        $old["berat_badan"],
        FILTER_VALIDATE_FLOAT
    );

    $tinggiPanjangBadan = filter_var(
        $old["tinggi_panjang_badan"],
        FILTER_VALIDATE_FLOAT
    );

    $lingkarKepala =
        $old["lingkar_kepala"] === ""
            ? null
            : filter_var(
                $old["lingkar_kepala"],
                FILTER_VALIDATE_FLOAT
            );

    $lila =
        $old["lila"] === ""
            ? null
            : filter_var(
                $old["lila"],
                FILTER_VALIDATE_FLOAT
            );

    $objekTanggal = DateTime::createFromFormat(
        "Y-m-d",
        $tanggalPengukuran
    );

    $tanggalValid =
        $objekTanggal !== false
        && $objekTanggal->format("Y-m-d")
            === $tanggalPengukuran;

    /*
    |--------------------------------------------------------------------------
    | Validasi
    |--------------------------------------------------------------------------
    */

    if ($puskesmasBelumTerhubung) {
        $error =
            "Akun Kader belum terhubung dengan Puskesmas. Hubungkan akun Kader ke Puskesmas terlebih dahulu.";

    } elseif ($idPuskesmasAktif < 1) {
        $error =
            "Puskesmas akun Kader tidak valid.";

    } elseif (
        $old["id_balita"] === ""
        || $old["tanggal_pengukuran"] === ""
        || $old["berat_badan"] === ""
        || $old["tinggi_panjang_badan"] === ""
    ) {
        $error =
            "Nama balita, tanggal, berat badan, dan tinggi badan wajib diisi.";

    } elseif (
        $idBalita === false
        || $idBalita < 1
    ) {
        $error =
            "Data balita yang dipilih tidak valid.";

    } elseif (!$tanggalValid) {
        $error =
            "Tanggal pengukuran tidak valid.";

    } elseif (
        $beratBadan === false
        || $beratBadan <= 0
        || $beratBadan > 100
    ) {
        $error =
            "Berat badan harus lebih dari 0 dan maksimal 100 kg.";

    } elseif (
        $tinggiPanjangBadan === false
        || $tinggiPanjangBadan <= 0
        || $tinggiPanjangBadan > 200
    ) {
        $error =
            "Tinggi atau panjang badan harus lebih dari 0 dan maksimal 200 cm.";

    } elseif (
        $old["lingkar_kepala"] !== ""
        && (
            $lingkarKepala === false
            || $lingkarKepala <= 0
            || $lingkarKepala > 100
        )
    ) {
        $error =
            "Lingkar kepala harus lebih dari 0 dan maksimal 100 cm.";

    } elseif (
        $old["lila"] !== ""
        && (
            $lila === false
            || $lila <= 0
            || $lila > 100
        )
    ) {
        $error =
            "Nilai LiLA harus lebih dari 0 dan maksimal 100 cm.";
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi balita harus benar-benar berasal dari Puskesmas Kader
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $stmtCekBalita = mysqli_prepare(
            $conn,
            "SELECT
                id_balita,
                tanggal_lahir
             FROM balita
             WHERE id_balita = ?
               AND id_puskesmas = ?
             LIMIT 1"
        );

        if (!$stmtCekBalita) {
            $error =
                "Sistem gagal memeriksa data balita.";

        } else {

            mysqli_stmt_bind_param(
                $stmtCekBalita,
                "ii",
                $idBalita,
                $idPuskesmasAktif
            );

            mysqli_stmt_execute(
                $stmtCekBalita
            );

            $hasilCekBalita =
                mysqli_stmt_get_result(
                    $stmtCekBalita
                );

            $dataBalita =
                mysqli_fetch_assoc(
                    $hasilCekBalita
                );

            mysqli_stmt_close(
                $stmtCekBalita
            );

            if (!$dataBalita) {
                $error =
                    "Balita tidak ditemukan pada Puskesmas akun Kader.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Hitung umur otomatis dalam bulan
                |--------------------------------------------------------------------------
                */

                $tanggalLahir = DateTime::createFromFormat(
                    "Y-m-d",
                    (string) $dataBalita[
                        "tanggal_lahir"
                    ]
                );

                $tanggalUkur = DateTime::createFromFormat(
                    "Y-m-d",
                    $tanggalPengukuran
                );

                if (
                    !$tanggalLahir
                    || !$tanggalUkur
                ) {
                    $error =
                        "Tanggal lahir balita atau tanggal pengukuran tidak valid.";

                } elseif (
                    $tanggalUkur
                    < $tanggalLahir
                ) {
                    $error =
                        "Tanggal pengukuran tidak boleh lebih awal dari tanggal lahir balita.";

                } else {

                    $selisihUmur =
                        $tanggalLahir->diff(
                            $tanggalUkur
                        );

                    $umurBulan =
                        ($selisihUmur->y * 12)
                        + $selisihUmur->m;

                    $old["umur_bulan"] =
                        (string) $umurBulan;

                    if (
                        $umurBulan < 0
                        || $umurBulan > 59
                    ) {
                        $error =
                            "Umur balita pada tanggal pengukuran harus berada antara 0 sampai 59 bulan.";
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan pengukuran
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO pengukuran_antropometri
            (
                id_balita,
                tanggal_pengukuran,
                umur_bulan,
                berat_badan,
                tinggi_panjang_badan,
                lingkar_kepala,
                lila
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmtSimpan) {
            $error =
                "Sistem gagal menyiapkan penyimpanan data.";

        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "isidddd",
                $idBalita,
                $tanggalPengukuran,
                $umurBulan,
                $beratBadan,
                $tinggiPanjangBadan,
                $lingkarKepala,
                $lila
            );

            $berhasil =
                mysqli_stmt_execute(
                    $stmtSimpan
                );

            if ($berhasil) {

                $idPengukuranBaru =
                    (int) mysqli_insert_id($conn);

                mysqli_stmt_close(
                    $stmtSimpan
                );

                /*
                |------------------------------------------------------------------
                | Alur kunjungan Posyandu bulanan
                |------------------------------------------------------------------
                | Antropometri wajib dicatat setiap kunjungan. Skrining tidak perlu
                | diisi ulang jika balita sudah mempunyai skrining sebelumnya.
                | Sistem memakai snapshot skrining terakhir dan langsung menghitung
                | hasil deteksi untuk pengukuran yang baru disimpan.
                */

                $stmtSkriningTerakhir = mysqli_prepare(
                    $conn,
                    "SELECT id_skrining
                     FROM skrining_awal
                     WHERE id_balita = ?
                     ORDER BY id_skrining DESC
                     LIMIT 1"
                );

                if (!$stmtSkriningTerakhir) {
                    die(
                        "Gagal memeriksa skrining terakhir: "
                        . mysqli_error($conn)
                    );
                }

                mysqli_stmt_bind_param(
                    $stmtSkriningTerakhir,
                    "i",
                    $idBalita
                );

                mysqli_stmt_execute(
                    $stmtSkriningTerakhir
                );

                $hasilSkriningTerakhir =
                    mysqli_stmt_get_result(
                        $stmtSkriningTerakhir
                    );

                $skriningTerakhir =
                    mysqli_fetch_assoc(
                        $hasilSkriningTerakhir
                    );

                mysqli_stmt_close(
                    $stmtSkriningTerakhir
                );

                if ($skriningTerakhir) {
                    header(
                        "Location: ../deteksi/analisis_deteksi.php"
                        . "?id_balita=" . (int) $idBalita
                        . "&sumber=pengukuran_bulanan"
                    );
                    exit;
                }

                /*
                | Belum pernah skrining: Kader cukup mengisi skrining pertama kali.
                | Balita langsung dipilih otomatis di form skrining.
                */
                header(
                    "Location: ../skrining/form_skrining.php"
                    . "?id_balita=" . (int) $idBalita
                    . "&mode=baru"
                    . "&pesan=lengkapi_skrining"
                );

                exit;
            }

            $error =
                "Data pengukuran gagal disimpan: "
                . mysqli_stmt_error(
                    $stmtSimpan
                );

            mysqli_stmt_close(
                $stmtSimpan
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Menentukan label balita terpilih jika form kembali karena error
|--------------------------------------------------------------------------
*/

$labelBalitaTerpilih =
    "-- Pilih Balita --";

$tanggalLahirTerpilih = "";

foreach (
    $daftarBalita as $balita
) {
    if (
        (string) $old["id_balita"]
        ===
        (string) $balita["id_balita"]
    ) {
        $labelBalitaTerpilih =
            $balita["nama_balita"]
            . " — NIK "
            . $balita["nik_balita"];

        $tanggalLahirTerpilih =
            (string) $balita[
                "tanggal_lahir"
            ];

        break;
    }
}

/*
|--------------------------------------------------------------------------
| Template
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    <i class="bi bi-clipboard2-pulse me-2"></i>
                    Tambah Pengukuran
                </h1>

                <p class="page-subtitle">
                    Masukkan hasil pengukuran antropometri balita
                    secara lengkap dan benar.
                </p>

            </div>

            <a
                href="data_pengukuran.php"
                class="btn btn-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Kembali ke Data Pengukuran
            </a>

        </div>

        <?php if ($puskesmasBelumTerhubung): ?>

            <div
                class="alert alert-warning"
                role="alert"
            >
                <i class="bi bi-exclamation-triangle me-1"></i>

                Akun Kader belum terhubung dengan Puskesmas.
                Hubungkan akun Kader melalui menu Data Pengguna
                terlebih dahulu.
            </div>

        <?php endif; ?>

        <?php if ($error !== ""): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-exclamation-circle me-1"></i>

                <?= amanPengukuran($error); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>

        <?php endif; ?>

        <?php if (
            !$puskesmasBelumTerhubung
            && $jumlahBalita < 1
        ): ?>

            <div class="card content-card">

                <div class="card-body">

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>

                        <h3>
                            Belum ada balita di
                            <?= amanPengukuran(
                                $namaPuskesmasAktif
                            ); ?>
                        </h3>

                        <p>
                            Tambahkan data balita pada Puskesmas ini
                            terlebih dahulu sebelum membuat pengukuran.
                        </p>

                        <a
                            href="../balita/tambah_balita.php"
                            class="btn btn-primary mt-3"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Tambah Data Balita
                        </a>

                    </div>

                </div>

            </div>

        <?php elseif (
            !$puskesmasBelumTerhubung
        ): ?>

            <div class="card content-card">

                <div class="card-header">

                    <div>

                        <h4 class="mb-1">
                            Form Pengukuran Antropometri
                        </h4>

                        <small class="text-muted">
                            Kolom bertanda wajib harus diisi.
                        </small>

                    </div>

                </div>

                <div class="card-body">

                    <form
                        method="POST"
                        action=""
                        autocomplete="off"
                    >

                        <!-- Puskesmas otomatis -->
                        <div class="form-group">

                            <label class="form-label">
                                Puskesmas
                            </label>

                            <div
                                class="p-3 rounded border"
                                style="background: #f8fafc;"
                            >
                                <div
                                    class="d-flex align-items-center gap-2"
                                >
                                    <i class="bi bi-hospital"></i>

                                    <strong>
                                        <?= amanPengukuran(
                                            $namaPuskesmasAktif
                                        ); ?>
                                    </strong>
                                </div>

                                <small class="text-muted">
                                    Puskesmas mengikuti akun Kader
                                    secara otomatis dan tidak dapat
                                    diubah dari form ini.
                                </small>
                            </div>

                        </div>

                        <!-- Balita searchable -->
                        <div class="form-group">

                            <label
                                for="balitaSearchTrigger"
                                class="form-label"
                            >
                                Nama Balita
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="hidden"
                                name="id_balita"
                                id="id_balita"
                                value="<?= amanPengukuran(
                                    $old["id_balita"]
                                ); ?>"
                            >

                            <div
                                class="balita-search-select"
                                id="balitaSearchSelect"
                            >

                                <button
                                    type="button"
                                    id="balitaSearchTrigger"
                                    class="form-select text-start balita-search-trigger"
                                    data-tanggal-lahir="<?= amanPengukuran(
                                        $tanggalLahirTerpilih
                                    ); ?>"
                                >
                                    <span id="balitaSearchSelected">
                                        <?= amanPengukuran(
                                            $labelBalitaTerpilih
                                        ); ?>
                                    </span>
                                </button>

                                <div
                                    class="balita-search-panel"
                                    id="balitaSearchPanel"
                                    hidden
                                >

                                    <div class="p-2 border-bottom">

                                        <div class="input-group">

                                            <span class="input-group-text">
                                                <i class="bi bi-search"></i>
                                            </span>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="balitaSearchInput"
                                                placeholder="Cari nama atau NIK balita..."
                                                autocomplete="off"
                                            >

                                        </div>

                                    </div>

                                    <div
                                        class="balita-search-list"
                                        id="balitaSearchList"
                                    >

                                        <?php foreach (
                                            $daftarBalita
                                            as $balita
                                        ): ?>

                                            <?php
                                            $labelBalita =
                                                $balita["nama_balita"]
                                                . " — NIK "
                                                . $balita["nik_balita"];
                                            ?>

                                            <button
                                                type="button"
                                                class="balita-search-option"
                                                data-value="<?= (int)
                                                    $balita[
                                                        "id_balita"
                                                    ]; ?>"
                                                data-tanggal-lahir="<?= amanPengukuran(
                                                    $balita[
                                                        "tanggal_lahir"
                                                    ]
                                                ); ?>"
                                                data-search="<?= amanPengukuran(
                                                    strtolower(
                                                        $balita[
                                                            "nama_balita"
                                                        ]
                                                        . " "
                                                        . $balita[
                                                            "nik_balita"
                                                        ]
                                                    )
                                                ); ?>"
                                                data-label="<?= amanPengukuran(
                                                    $labelBalita
                                                ); ?>"
                                            >
                                                <?= amanPengukuran(
                                                    $labelBalita
                                                ); ?>
                                            </button>

                                        <?php endforeach; ?>

                                        <div
                                            class="balita-search-empty text-muted text-center p-3"
                                            id="balitaSearchEmpty"
                                            hidden
                                        >
                                            Balita tidak ditemukan.
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <small class="text-muted">
                                Daftar hanya menampilkan balita dari
                                Puskesmas
                                <?= amanPengukuran(
                                    $namaPuskesmasAktif
                                ); ?>.
                            </small>

                        </div>

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    for="tanggal_pengukuran"
                                    class="form-label"
                                >
                                    Tanggal Pengukuran
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="date"
                                    name="tanggal_pengukuran"
                                    id="tanggal_pengukuran"
                                    class="form-control"
                                    value="<?= amanPengukuran(
                                        $old[
                                            "tanggal_pengukuran"
                                        ]
                                    ); ?>"
                                    max="<?= date("Y-m-d"); ?>"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label
                                    for="umur_bulan"
                                    class="form-label"
                                >
                                    Umur Balita
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="umur_bulan"
                                        id="umur_bulan"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old["umur_bulan"]
                                        ); ?>"
                                        min="0"
                                        max="59"
                                        placeholder="Otomatis"
                                        readonly
                                        required
                                    >

                                    <span class="input-group-text">
                                        bulan
                                    </span>

                                </div>

                                <small class="text-muted">
                                    Umur dihitung otomatis dari tanggal lahir
                                    balita dan tanggal pengukuran.
                                </small>

                            </div>

                        </div>

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    for="berat_badan"
                                    class="form-label"
                                >
                                    Berat Badan
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="berat_badan"
                                        id="berat_badan"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old["berat_badan"]
                                        ); ?>"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        placeholder="Contoh: 12.50"
                                        required
                                    >

                                    <span class="input-group-text">
                                        kg
                                    </span>

                                </div>

                            </div>

                            <div class="form-group">

                                <label
                                    for="tinggi_panjang_badan"
                                    class="form-label"
                                >
                                    Tinggi/Panjang Badan
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="tinggi_panjang_badan"
                                        id="tinggi_panjang_badan"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old[
                                                "tinggi_panjang_badan"
                                            ]
                                        ); ?>"
                                        min="0.01"
                                        max="200"
                                        step="0.01"
                                        placeholder="Contoh: 85.50"
                                        required
                                    >

                                    <span class="input-group-text">
                                        cm
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    for="lingkar_kepala"
                                    class="form-label"
                                >
                                    Lingkar Kepala
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="lingkar_kepala"
                                        id="lingkar_kepala"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old[
                                                "lingkar_kepala"
                                            ]
                                        ); ?>"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        placeholder="Opsional"
                                    >

                                    <span class="input-group-text">
                                        cm
                                    </span>

                                </div>

                            </div>

                            <div class="form-group">

                                <label
                                    for="lila"
                                    class="form-label"
                                >
                                    Lingkar Lengan Atas (LiLA)
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="lila"
                                        id="lila"
                                        class="form-control"
                                        value="<?= amanPengukuran(
                                            $old["lila"]
                                        ); ?>"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        placeholder="Opsional"
                                    >

                                    <span class="input-group-text">
                                        cm
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="form-actions">

                            <button
                                type="submit"
                                name="simpan"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-floppy"></i>
                                Simpan Pengukuran
                            </button>

                            <a
                                href="data_pengukuran.php"
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

<style>
.balita-search-select {
    position: relative;
}

.balita-search-trigger {
    min-height: 46px;
}

.balita-search-panel {
    position: absolute;
    z-index: 2000;
    left: 0;
    right: 0;
    top: calc(100% + 6px);
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    box-shadow: 0 14px 32px rgba(30, 41, 59, 0.16);
    overflow: hidden;
}

.balita-search-list {
    max-height: 260px;
    overflow-y: auto;
    padding: 6px;
}

.balita-search-option {
    display: block;
    width: 100%;
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
</style>

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        const wrapper =
            document.getElementById(
                "balitaSearchSelect"
            );

        const trigger =
            document.getElementById(
                "balitaSearchTrigger"
            );

        const panel =
            document.getElementById(
                "balitaSearchPanel"
            );

        const searchInput =
            document.getElementById(
                "balitaSearchInput"
            );

        const hiddenBalita =
            document.getElementById(
                "id_balita"
            );

        const selectedText =
            document.getElementById(
                "balitaSearchSelected"
            );

        const emptyState =
            document.getElementById(
                "balitaSearchEmpty"
            );

        const tanggalPengukuran =
            document.getElementById(
                "tanggal_pengukuran"
            );

        const umurBulanInput =
            document.getElementById(
                "umur_bulan"
            );

        if (
            !wrapper
            || !trigger
            || !panel
            || !searchInput
            || !hiddenBalita
            || !selectedText
            || !tanggalPengukuran
            || !umurBulanInput
        ) {
            return;
        }

        const options = Array.from(
            wrapper.querySelectorAll(
                ".balita-search-option"
            )
        );

        function normalisasi(teks) {
            return String(teks || "")
                .toLowerCase()
                .normalize("NFD")
                .replace(
                    /[\u0300-\u036f]/g,
                    ""
                )
                .trim();
        }

        function parseTanggal(tanggal) {
            if (!tanggal) {
                return null;
            }

            const bagian =
                tanggal.split("-").map(Number);

            if (bagian.length !== 3) {
                return null;
            }

            return {
                tahun: bagian[0],
                bulan: bagian[1],
                hari: bagian[2]
            };
        }

        function hitungUmurBulan() {

            const lahir =
                parseTanggal(
                    trigger.dataset
                        .tanggalLahir
                );

            const ukur =
                parseTanggal(
                    tanggalPengukuran.value
                );

            if (!lahir || !ukur) {
                umurBulanInput.value = "";
                return;
            }

            let umurBulan =
                (
                    ukur.tahun
                    - lahir.tahun
                ) * 12
                + (
                    ukur.bulan
                    - lahir.bulan
                );

            if (
                ukur.hari
                < lahir.hari
            ) {
                umurBulan--;
            }

            if (
                umurBulan < 0
                || umurBulan > 59
            ) {
                umurBulanInput.value = "";
                return;
            }

            umurBulanInput.value =
                umurBulan;
        }

        function bukaPanel() {

            panel.hidden = false;
            searchInput.value = "";

            options.forEach(
                function (option) {
                    option.hidden = false;
                }
            );

            if (emptyState) {
                emptyState.hidden = true;
            }

            setTimeout(
                function () {
                    searchInput.focus();
                },
                0
            );
        }

        function tutupPanel() {
            panel.hidden = true;
        }

        trigger.addEventListener(
            "click",
            function () {
                if (panel.hidden) {
                    bukaPanel();
                } else {
                    tutupPanel();
                }
            }
        );

        searchInput.addEventListener(
            "input",
            function () {

                const keyword =
                    normalisasi(
                        searchInput.value
                    );

                let jumlahTampil = 0;

                options.forEach(
                    function (option) {

                        const dataSearch =
                            normalisasi(
                                option.dataset
                                    .search
                            );

                        const cocok =
                            keyword === ""
                            || dataSearch.includes(
                                keyword
                            );

                        option.hidden =
                            !cocok;

                        if (cocok) {
                            jumlahTampil++;
                        }
                    }
                );

                if (emptyState) {
                    emptyState.hidden =
                        jumlahTampil > 0;
                }
            }
        );

        options.forEach(
            function (option) {

                option.addEventListener(
                    "click",
                    function () {

                        hiddenBalita.value =
                            option.dataset.value;

                        selectedText.textContent =
                            option.dataset.label;

                        trigger.dataset
                            .tanggalLahir =
                            option.dataset
                                .tanggalLahir;

                        options.forEach(
                            function (item) {
                                item.classList
                                    .remove(
                                        "is-selected"
                                    );
                            }
                        );

                        option.classList.add(
                            "is-selected"
                        );

                        hitungUmurBulan();
                        tutupPanel();
                    }
                );

                if (
                    option.dataset.value
                    === hiddenBalita.value
                ) {
                    option.classList.add(
                        "is-selected"
                    );
                }
            }
        );

        tanggalPengukuran.addEventListener(
            "change",
            hitungUmurBulan
        );

        document.addEventListener(
            "click",
            function (event) {

                if (
                    !wrapper.contains(
                        event.target
                    )
                ) {
                    tutupPanel();
                }
            }
        );

        hitungUmurBulan();
    }
);
</script>

<?php require_once "../includes/footer.php"; ?>
