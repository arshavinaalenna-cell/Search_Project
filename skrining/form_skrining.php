<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/* Waktu pencatatan skrining mengikuti WIB / Jakarta. */
date_default_timezone_set("Asia/Jakarta");
@mysqli_query($conn, "SET time_zone = '+07:00'");

cekRole(["kader"]);

$roleAktif = $_SESSION["role"] ?? "";
$idKaderAktif = (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Puskesmas Kader aktif
|--------------------------------------------------------------------------
|
| Puskesmas tidak dipilih dari form. Sistem mengambilnya langsung dari
| pengguna.id_puskesmas milik akun Kader yang sedang login.
|
*/

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
        $dataPuskesmasAktif["id_puskesmas"]
    )
    || empty(
        $dataPuskesmasAktif["nama_puskesmas"]
    )
) {
    $puskesmasBelumTerhubung = true;
} else {
    $idPuskesmasAktif =
        (int) $dataPuskesmasAktif["id_puskesmas"];

    $namaPuskesmasAktif =
        trim(
            (string) $dataPuskesmasAktif[
                "nama_puskesmas"
            ]
        );
}

/*
|--------------------------------------------------------------------------
| Judul halaman
|--------------------------------------------------------------------------
*/

$judulHalaman =
    "Tambah Skrining | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Pastikan kolom tanggal_skrining tersedia
|--------------------------------------------------------------------------
|
| Kolom ini diperlukan agar setiap snapshot Skrining memiliki tanggal yang
| jelas. Jika migration SQL belum dijalankan, form tidak akan menyebabkan
| fatal error; pengguna akan menerima pesan yang jelas.
|
*/

$kolomTanggalSkriningTersedia = false;

$cekKolomTanggalSkrining = mysqli_query(
    $conn,
    "SHOW COLUMNS FROM skrining_awal LIKE 'tanggal_skrining'"
);

if (
    $cekKolomTanggalSkrining
    && mysqli_num_rows($cekKolomTanggalSkrining) > 0
) {
    $kolomTanggalSkriningTersedia = true;
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
| Mode skrining: baru / perbarui
|--------------------------------------------------------------------------
| Skrining tidak wajib diisi dari nol setiap bulan. Jika Kader membuka
| halaman melalui tombol "Perbarui Skrining", nilai dari skrining terakhir
| akan menjadi nilai awal. Saat disimpan, sistem membuat snapshot baru agar
| histori skrining lama tidak ditimpa.
*/

$modeForm = strtolower(
    trim(
        (string) (
            $_POST["mode_form"]
            ?? $_GET["mode"]
            ?? "baru"
        )
    )
);

if (!in_array($modeForm, ["baru", "perbarui"], true)) {
    $modeForm = "baru";
}

$idBalitaDariUrl =
    (int) ($_GET["id_balita"] ?? 0);

$skriningSebelumnya = null;

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
    && !$puskesmasBelumTerhubung
    && $idBalitaDariUrl > 0
) {
    /* Pastikan balita memang berada di Puskesmas akun Kader. */
    $stmtSkriningSebelumnya = mysqli_prepare(
        $conn,
        "SELECT
            s.id_skrining,
            s.id_balita,
            s.tinggi_badan_ibu,
            s.lama_asi_eksklusif,
            s.mpasi,
            s.frekuensi_makan,
            s.protein_hewani,
            s.status_ekonomi,
            s.sanitasi,
            s.air_bersih
         FROM skrining_awal AS s
         INNER JOIN balita AS b
            ON b.id_balita = s.id_balita
         WHERE s.id_balita = ?
           AND b.id_puskesmas = ?
         ORDER BY s.id_skrining DESC
         LIMIT 1"
    );

    if ($stmtSkriningSebelumnya) {
        mysqli_stmt_bind_param(
            $stmtSkriningSebelumnya,
            "ii",
            $idBalitaDariUrl,
            $idPuskesmasAktif
        );

        mysqli_stmt_execute(
            $stmtSkriningSebelumnya
        );

        $hasilSkriningSebelumnya =
            mysqli_stmt_get_result(
                $stmtSkriningSebelumnya
            );

        $skriningSebelumnya =
            mysqli_fetch_assoc(
                $hasilSkriningSebelumnya
            );

        mysqli_stmt_close(
            $stmtSkriningSebelumnya
        );
    }

    /* Balita tetap terpilih walaupun belum mempunyai skrining sebelumnya. */
    $old["id_balita"] =
        (string) $idBalitaDariUrl;

    if ($skriningSebelumnya) {
        $modeForm = "perbarui";

        foreach ([
            "tinggi_badan_ibu",
            "lama_asi_eksklusif",
            "mpasi",
            "frekuensi_makan",
            "protein_hewani",
            "status_ekonomi",
            "sanitasi",
            "air_bersih"
        ] as $kolomSkrining) {
            $old[$kolomSkrining] =
                (string) (
                    $skriningSebelumnya[$kolomSkrining]
                    ?? ""
                );
        }
    }
}

$judulFormSkrining =
    $modeForm === "perbarui"
        ? "Perbarui Skrining"
        : "Tambah Skrining";

$pesanForm = trim(
    (string) ($_GET["pesan"] ?? "")
);

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

    if ($puskesmasBelumTerhubung) {
        $error =
            "Akun Kader belum terhubung dengan Puskesmas. Hubungkan akun Kader ke Puskesmas terlebih dahulu.";
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

            $hasilBalita =
                mysqli_stmt_get_result(
                    $stmtCekBalita
                );

            $dataBalita =
                mysqli_fetch_assoc(
                    $hasilBalita
                );

            mysqli_stmt_close(
                $stmtCekBalita
            );

            if (!$dataBalita) {
                $error =
                    "Balita tidak ditemukan pada Puskesmas akun Kader.";
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

    if (
        $error === ""
        && !$kolomTanggalSkriningTersedia
    ) {
        $error =
            "Kolom tanggal_skrining belum tersedia pada database. "
            . "Jalankan file update_tanggal_skrining.sql terlebih dahulu.";
    }

    if ($error === "") {

        /*
        |--------------------------------------------------------------------------
        | Tanggal Skrining WIB
        |--------------------------------------------------------------------------
        |
        | Tanggal berasal dari PHP Asia/Jakarta, bukan NOW() MySQL, sehingga
        | tidak bergantung pada timezone server database.
        |
        */

        $tanggalSkrining =
            date("Y-m-d");

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
                tanggal_skrining,
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
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmtSimpan) {
            $error =
                "Sistem gagal menyiapkan penyimpanan skrining.";

        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "isdssissssss",
                $idBalita,
                $tanggalSkrining,
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

                $sumberAnalisis =
                    $modeForm === "perbarui"
                        ? "skrining_diperbarui"
                        : "form_skrining";

                header(
                    "Location: ../deteksi/analisis_deteksi.php"
                    . "?id_balita=" . (int) $idBalita
                    . "&sumber=" . urlencode($sumberAnalisis)
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

$daftarBalita = [];

if (!$puskesmasBelumTerhubung) {

    $stmtDaftarBalita = mysqli_prepare(
        $conn,
        "SELECT
            b.id_balita,
            b.id_user,
            b.id_puskesmas,
            b.nik_balita,
            b.nama_balita,
            b.nama_ibu AS nama_ibu_lama,
            ot.id_orang_tua,
            ot.nama_ibu AS profil_nama_ibu,
            ot.pendidikan_ibu,
            ot.pekerjaan_ibu
         FROM balita AS b
         LEFT JOIN orang_tua AS ot
            ON b.id_user = ot.id_user
         WHERE b.id_puskesmas = ?
         ORDER BY b.nama_balita ASC"
    );

    if (!$stmtDaftarBalita) {
        die(
            "Gagal mengambil daftar balita: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtDaftarBalita,
        "i",
        $idPuskesmasAktif
    );

    mysqli_stmt_execute(
        $stmtDaftarBalita
    );

    $hasilDaftarBalita =
        mysqli_stmt_get_result(
            $stmtDaftarBalita
        );

    while (
        $balita =
            mysqli_fetch_assoc(
                $hasilDaftarBalita
            )
    ) {
        $daftarBalita[] = $balita;
    }

    mysqli_stmt_close(
        $stmtDaftarBalita
    );
}

$jumlahBalita =
    count($daftarBalita);

/*
|--------------------------------------------------------------------------
| Menghitung balita yang belum terhubung Profil Ibu
|--------------------------------------------------------------------------
*/

$jumlahBalitaBelumTerhubung = 0;

if (!$puskesmasBelumTerhubung) {

    $stmtBelumTerhubung = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM balita AS b
         LEFT JOIN orang_tua AS ot
            ON b.id_user = ot.id_user
         WHERE b.id_puskesmas = ?
           AND (
                b.id_user IS NULL
                OR ot.id_orang_tua IS NULL
           )"
    );

    if ($stmtBelumTerhubung) {

        mysqli_stmt_bind_param(
            $stmtBelumTerhubung,
            "i",
            $idPuskesmasAktif
        );

        mysqli_stmt_execute(
            $stmtBelumTerhubung
        );

        $hasilBelumTerhubung =
            mysqli_stmt_get_result(
                $stmtBelumTerhubung
            );

        $dataBelumTerhubung =
            mysqli_fetch_assoc(
                $hasilBelumTerhubung
            );

        $jumlahBalitaBelumTerhubung =
            (int) (
                $dataBelumTerhubung["total"]
                ?? 0
            );

        mysqli_stmt_close(
            $stmtBelumTerhubung
        );
    }
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

        <?php if ($jumlahBalita < 1): ?>

            <div class="card content-card">

                <div class="card-header">

                    <div>

                        <h4 class="mb-1">
                            <i class="bi bi-clipboard2-heart me-2"></i>
                            <?= amanFormSkrining($judulFormSkrining); ?>
                        </h4>

                        <small class="text-muted">
                            <?= $modeForm === "perbarui"
                                ? "Data skrining terakhir sudah diisi otomatis. Ubah hanya informasi yang memang berubah pada kunjungan ini."
                                : "Pilih data balita, lalu sistem akan membaca Profil Ibu yang terhubung secara otomatis."; ?>
                        </small>

                    </div>

                    <a
                        href="hasil_skrining.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

                <div class="card-body">

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
                            sebelum mengisi skrining.
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
                            <i class="bi bi-clipboard2-heart me-2"></i>
                            <?= amanFormSkrining($judulFormSkrining); ?>
                        </h4>

                        <small class="text-muted">
                            <?= $modeForm === "perbarui"
                                ? "Data skrining terakhir sudah diisi otomatis. Ubah hanya informasi yang memang berubah pada kunjungan ini."
                                : "Pilih data balita, lalu sistem akan membaca Profil Ibu yang terhubung secara otomatis."; ?>
                        </small>

                    </div>

                    <a
                        href="hasil_skrining.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

                <div class="card-body">

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

                    <?php if ($pesanForm === "lengkapi_skrining"): ?>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-1"></i>
                            Pengukuran antropometri sudah tersimpan. Balita ini belum pernah mempunyai skrining, jadi lengkapi skrining satu kali agar sistem dapat menghasilkan status.
                        </div>

                    <?php elseif ($skriningSebelumnya): ?>

                        <div class="alert alert-info">
                            <i class="bi bi-arrow-repeat me-1"></i>
                            Data di bawah diambil dari <strong>skrining terakhir</strong>. Perbarui hanya faktor yang berubah. Saat disimpan, data lama tetap tersimpan sebagai riwayat.
                        </div>

                    <?php endif; ?>

                    <form
                        method="POST"
                        action=""
                        autocomplete="off"
                    >

                        <input
                            type="hidden"
                            name="mode_form"
                            value="<?= amanFormSkrining($modeForm); ?>"
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
                                        <?= amanFormSkrining(
                                            $namaPuskesmasAktif
                                        ); ?>
                                    </strong>
                                </div>

                                <small class="text-muted">
                                    Puskesmas mengikuti akun Kader secara
                                    otomatis dan tidak dapat diubah dari
                                    form skrining.
                                </small>
                            </div>

                        </div>

                        <!-- Balita dengan pencarian -->
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
                                value="<?= amanFormSkrining(
                                    $old["id_balita"]
                                ); ?>"
                            >

                            <?php
                            $labelBalitaTerpilih =
                                "-- Pilih Balita --";

                            foreach (
                                $daftarBalita
                                as $balitaTerpilih
                            ) {
                                if (
                                    (string) $old["id_balita"]
                                    ===
                                    (string) $balitaTerpilih["id_balita"]
                                ) {
                                    $labelBalitaTerpilih =
                                        $balitaTerpilih["nama_balita"]
                                        . " — NIK "
                                        . $balitaTerpilih["nik_balita"];

                                    break;
                                }
                            }
                            ?>

                            <div
                                class="balita-search-select"
                                id="balitaSearchSelect"
                            >

                                <button
                                    type="button"
                                    id="balitaSearchTrigger"
                                    class="form-select text-start balita-search-trigger"
                                >
                                    <span id="balitaSearchSelected">
                                        <?= amanFormSkrining(
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
                                                data-search="<?= amanFormSkrining(
                                                    strtolower(
                                                        $balita["nama_balita"]
                                                        . " "
                                                        . $balita["nik_balita"]
                                                    )
                                                ); ?>"
                                                data-label="<?= amanFormSkrining(
                                                    $labelBalita
                                                ); ?>"
                                                data-nama-ibu="<?= amanFormSkrining(
                                                    $namaIbuTampilPilihan
                                                ); ?>"
                                                data-pendidikan-ibu="<?= amanFormSkrining(
                                                    $balita[
                                                        "pendidikan_ibu"
                                                    ]
                                                    ?? ""
                                                ); ?>"
                                                data-pekerjaan-ibu="<?= amanFormSkrining(
                                                    $balita[
                                                        "pekerjaan_ibu"
                                                    ]
                                                    ?? ""
                                                ); ?>"
                                                data-profil-terhubung="<?= $profilIbuTerhubungPilihan
                                                    ? "1"
                                                    : "0"; ?>"
                                            >
                                                <?= amanFormSkrining(
                                                    $labelBalita
                                                ); ?>

                                                <?= $profilIbuTerhubungPilihan
                                                    ? " — "
                                                        . amanFormSkrining(
                                                            $namaIbuTampilPilihan
                                                        )
                                                    : " — Profil Ibu belum terhubung"; ?>
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
                                <?= amanFormSkrining(
                                    $namaPuskesmasAktif
                                ); ?>.
                            </small>

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
    max-height: 280px;
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

        const namaIbu =
            document.getElementById(
                "profil_nama_ibu"
            );

        const pendidikanIbu =
            document.getElementById(
                "profil_pendidikan_ibu"
            );

        const pekerjaanIbu =
            document.getElementById(
                "profil_pekerjaan_ibu"
            );

        if (
            !wrapper
            || !trigger
            || !panel
            || !searchInput
            || !hiddenBalita
            || !selectedText
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

        function resetProfilIbu() {
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
        }

        function tampilkanProfilDariOption(
            option
        ) {
            if (!option) {
                resetProfilIbu();
                return;
            }

            const profilTerhubung =
                option.dataset.profilTerhubung
                === "1";

            if (!profilTerhubung) {

                if (namaIbu) {
                    namaIbu.textContent =
                        option.dataset.namaIbu
                        && option.dataset.namaIbu
                            !== "-"
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
                    option.dataset.namaIbu
                    || "-";
            }

            if (pendidikanIbu) {
                pendidikanIbu.textContent =
                    option.dataset
                        .pendidikanIbu
                    || "-";
            }

            if (pekerjaanIbu) {
                pekerjaanIbu.textContent =
                    option.dataset
                        .pekerjaanIbu
                    || "-";
            }
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
                                option.dataset.search
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

                        tampilkanProfilDariOption(
                            option
                        );

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

                    tampilkanProfilDariOption(
                        option
                    );
                }
            }
        );

        if (!hiddenBalita.value) {
            resetProfilIbu();
        }

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
    }
);
</script>

<?php require_once "../includes/footer.php"; ?>