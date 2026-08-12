<?php

require_once "../auth/session.php";
require_once "../config/koneksi.php";
require_once "statistik.php";

date_default_timezone_set("Asia/Jakarta");
@mysqli_query($conn, "SET time_zone = '+07:00'");

$rolePengguna = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Wilayah Puskesmas berdasarkan akun
|--------------------------------------------------------------------------
|
| Kader, Petugas KIA, Petugas Gizi, dan Kepala Puskesmas tidak memilih
| Puskesmas secara manual. Dashboard selalu mengikuti id_puskesmas yang
| terhubung dengan akun yang sedang login.
|
*/

$roleTerikatPuskesmas = [
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "kepala_puskesmas"
];

$idPuskesmasAkun = 0;
$namaPuskesmasAkun = "";
$puskesmasBelumTerhubung = false;

if (in_array($rolePengguna, $roleTerikatPuskesmas, true)) {
    $stmtPuskesmasAkun = mysqli_prepare(
        $conn,
        "SELECT
            u.id_puskesmas,
            p.nama_puskesmas
         FROM pengguna AS u
         LEFT JOIN puskesmas AS p
            ON u.id_puskesmas = p.id_puskesmas
         WHERE u.id_user = ?
         LIMIT 1"
    );

    if (!$stmtPuskesmasAkun) {
        die(
            "Gagal memeriksa Puskesmas akun: "
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
        $idPuskesmasAkun =
            (int) $dataPuskesmasAkun["id_puskesmas"];
        $namaPuskesmasAkun = trim(
            (string) (
                $dataPuskesmasAkun["nama_puskesmas"]
                ?? ""
            )
        );
    }
}

/*
|--------------------------------------------------------------------------
| Statistik dashboard khusus wilayah akun
|--------------------------------------------------------------------------
|
| statistik.php tetap dipakai untuk role lain. Untuk role yang terikat
| Puskesmas, nilainya ditimpa dengan perhitungan yang memiliki pembatasan
| b.id_puskesmas = id_puskesmas akun.
|
*/

function hitungStatistikWilayah(
    mysqli $conn,
    string $sql,
    int $idPuskesmas
): int {
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, "i", $idPuskesmas);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }

    $hasil = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($hasil);
    mysqli_stmt_close($stmt);

    return (int) ($row["total"] ?? 0);
}

if (in_array($rolePengguna, $roleTerikatPuskesmas, true)) {
    if ($puskesmasBelumTerhubung || $idPuskesmasAkun <= 0) {
        $totalBalita = 0;
        $totalPengukuran = 0;
        $totalSkrining = 0;
        $totalHasilDeteksi = 0;
        $totalKonsultasi = 0;
    } else {
        $totalBalita = hitungStatistikWilayah(
            $conn,
            "SELECT COUNT(*) AS total
             FROM balita AS b
             WHERE b.id_puskesmas = ?",
            $idPuskesmasAkun
        );

        $totalPengukuran = hitungStatistikWilayah(
            $conn,
            "SELECT COUNT(*) AS total
             FROM pengukuran_antropometri AS pa
             INNER JOIN balita AS b
                ON pa.id_balita = b.id_balita
             WHERE b.id_puskesmas = ?",
            $idPuskesmasAkun
        );

        $totalSkrining = hitungStatistikWilayah(
            $conn,
            "SELECT COUNT(*) AS total
             FROM skrining_awal AS sa
             INNER JOIN balita AS b
                ON sa.id_balita = b.id_balita
             WHERE b.id_puskesmas = ?",
            $idPuskesmasAkun
        );

        $totalHasilDeteksi = hitungStatistikWilayah(
            $conn,
            "SELECT COUNT(*) AS total
             FROM hasil_deteksi AS hd
             INNER JOIN pengukuran_antropometri AS pa
                ON hd.id_pengukuran = pa.id_pengukuran
             INNER JOIN balita AS b
                ON pa.id_balita = b.id_balita
             WHERE b.id_puskesmas = ?",
            $idPuskesmasAkun
        );

        $totalKonsultasi = hitungStatistikWilayah(
            $conn,
            "SELECT COUNT(*) AS total
             FROM konsultasi AS k
             INNER JOIN balita AS b
                ON k.id_balita = b.id_balita
             WHERE b.id_puskesmas = ?",
            $idPuskesmasAkun
        );
    }
}


/*
|--------------------------------------------------------------------------
| Pemantauan antropometri bulanan khusus Kader
|--------------------------------------------------------------------------
|
| Dashboard Kader tidak menampilkan jumlah seluruh riwayat pengukuran sebagai
| indikator utama. Yang lebih penting adalah progres Posyandu bulan berjalan:
| sudah diukur, belum diukur, dan hasil terbaru yang masih menunggu verifikasi.
|
*/

$periodeAktif = date("Y-m");
$namaBulanDashboard = [
    1 => "Januari",
    2 => "Februari",
    3 => "Maret",
    4 => "April",
    5 => "Mei",
    6 => "Juni",
    7 => "Juli",
    8 => "Agustus",
    9 => "September",
    10 => "Oktober",
    11 => "November",
    12 => "Desember"
];
$labelPeriodeAktif =
    ($namaBulanDashboard[(int) date("n")] ?? date("m"))
    . " "
    . date("Y");

$totalSudahDiukurBulanIni = 0;
$totalBelumDiukurBulanIni = 0;
$totalPerluVerifikasi = 0;
$monitoringAntropometriKader = [];

if (
    $rolePengguna === "kader"
    && !$puskesmasBelumTerhubung
    && $idPuskesmasAkun > 0
) {
    $stmtSudahDiukur = mysqli_prepare(
        $conn,
        "SELECT COUNT(DISTINCT pa.id_balita) AS total
         FROM pengukuran_antropometri AS pa
         INNER JOIN balita AS b
            ON pa.id_balita = b.id_balita
         WHERE b.id_puskesmas = ?
           AND SUBSTRING(CAST(pa.tanggal_pengukuran AS CHAR), 1, 7) = ?"
    );

    if ($stmtSudahDiukur) {
        mysqli_stmt_bind_param(
            $stmtSudahDiukur,
            "is",
            $idPuskesmasAkun,
            $periodeAktif
        );
        mysqli_stmt_execute($stmtSudahDiukur);
        $hasilSudahDiukur = mysqli_stmt_get_result($stmtSudahDiukur);
        $rowSudahDiukur = mysqli_fetch_assoc($hasilSudahDiukur);
        $totalSudahDiukurBulanIni =
            (int) ($rowSudahDiukur["total"] ?? 0);
        mysqli_stmt_close($stmtSudahDiukur);
    }

    $totalBelumDiukurBulanIni =
        max(0, (int) ($totalBalita ?? 0) - $totalSudahDiukurBulanIni);

    /*
    | Hitung balita yang hasil DETEKSI TERBARUNYA belum diverifikasi Ahli Gizi.
    | Hasil lama tidak ikut menambah angka jika balita sudah memiliki hasil
    | yang lebih baru.
    */
    $stmtPerluVerifikasi = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM balita AS b
         WHERE b.id_puskesmas = ?
           AND EXISTS (
                SELECT 1
                FROM hasil_deteksi AS hd
                INNER JOIN pengukuran_antropometri AS pa
                    ON hd.id_pengukuran = pa.id_pengukuran
                WHERE pa.id_balita = b.id_balita
                  AND hd.id_deteksi = (
                        SELECT MAX(hd2.id_deteksi)
                        FROM hasil_deteksi AS hd2
                        INNER JOIN pengukuran_antropometri AS pa2
                            ON hd2.id_pengukuran = pa2.id_pengukuran
                        WHERE pa2.id_balita = b.id_balita
                  )
                  AND LOWER(
                        TRIM(
                            COALESCE(
                                hd.status_verifikasi,
                                'Belum diverifikasi'
                            )
                        )
                  ) <> 'sudah diverifikasi'
           )"
    );

    if ($stmtPerluVerifikasi) {
        mysqli_stmt_bind_param(
            $stmtPerluVerifikasi,
            "i",
            $idPuskesmasAkun
        );
        mysqli_stmt_execute($stmtPerluVerifikasi);
        $hasilPerluVerifikasi =
            mysqli_stmt_get_result($stmtPerluVerifikasi);
        $rowPerluVerifikasi =
            mysqli_fetch_assoc($hasilPerluVerifikasi);
        $totalPerluVerifikasi =
            (int) ($rowPerluVerifikasi["total"] ?? 0);
        mysqli_stmt_close($stmtPerluVerifikasi);
    }

    /*
    | Satu balita = satu baris pemantauan. Riwayat lengkap tetap berada
    | pada menu Antropometri.
    */
    $stmtMonitoring = mysqli_prepare(
        $conn,
        "SELECT
            b.id_balita,
            b.nama_balita,
            (
                SELECT CAST(pa.tanggal_pengukuran AS CHAR)
                FROM pengukuran_antropometri AS pa
                WHERE pa.id_balita = b.id_balita
                ORDER BY
                    CAST(pa.tanggal_pengukuran AS CHAR) DESC,
                    pa.id_pengukuran DESC
                LIMIT 1
            ) AS tanggal_pengukuran_terakhir
         FROM balita AS b
         WHERE b.id_puskesmas = ?
         ORDER BY b.nama_balita ASC"
    );

    if ($stmtMonitoring) {
        mysqli_stmt_bind_param(
            $stmtMonitoring,
            "i",
            $idPuskesmasAkun
        );
        mysqli_stmt_execute($stmtMonitoring);
        $hasilMonitoring = mysqli_stmt_get_result($stmtMonitoring);

        while ($rowMonitoring = mysqli_fetch_assoc($hasilMonitoring)) {
            $monitoringAntropometriKader[] = $rowMonitoring;
        }

        mysqli_stmt_close($stmtMonitoring);
    }
}

function statusPemantauanBulananDashboard(?string $tanggal): array
{
    $tanggal = trim((string) $tanggal);

    if (
        $tanggal === ""
        || !preg_match(
            '/^([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})$/',
            $tanggal,
            $cocok
        )
    ) {
        return [
            "label" => "Belum pernah diukur",
            "kelas" => "danger",
            "ikon" => "bi-exclamation-circle"
        ];
    }

    $tahun = (int) $cocok[1];
    $bulan = (int) $cocok[2];

    if (
        $tahun === (int) date("Y")
        && $bulan === (int) date("n")
    ) {
        return [
            "label" => "Sudah diukur bulan ini",
            "kelas" => "success",
            "ikon" => "bi-check-circle-fill"
        ];
    }

    $indeksSekarang =
        ((int) date("Y") * 12) + (int) date("n");
    $indeksTerakhir =
        ($tahun * 12) + $bulan;
    $selisihBulan =
        max(0, $indeksSekarang - $indeksTerakhir);

    if ($selisihBulan >= 2) {
        return [
            "label" => "Belum diukur ≥2 bulan",
            "kelas" => "danger",
            "ikon" => "bi-exclamation-triangle-fill"
        ];
    }

    return [
        "label" => "Belum diukur bulan ini",
        "kelas" => "warning",
        "ikon" => "bi-clock-history"
    ];
}

function formatTanggalDashboard(?string $tanggal): string
{
    $tanggal = trim((string) $tanggal);

    if (
        $tanggal === ""
        || $tanggal === "0000-00-00"
        || !preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/', $tanggal)
    ) {
        return "-";
    }

    $waktu = strtotime($tanggal);

    return $waktu !== false
        ? date("d-m-Y", $waktu)
        : htmlspecialchars($tanggal, ENT_QUOTES, "UTF-8");
}

$judulHalaman =
    "Dashboard | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Pemberitahuan konsultasi yang telah disetujui Ahli Gizi
|--------------------------------------------------------------------------
*/

$totalPerluKonsultasi = 0;
$daftarNamaPerluKonsultasi = [];
$idPuskesmasNotifikasi =
    in_array($rolePengguna, ["kader", "petugas_kia"], true)
        ? $idPuskesmasAkun
        : 0;

if ($rolePengguna === "orang_tua") {
    $stmtNotifikasi = mysqli_prepare(
        $conn,
        "SELECT b.nama_balita
         FROM konsultasi k
         INNER JOIN balita b
            ON k.id_balita = b.id_balita
         INNER JOIN hasil_deteksi hd
            ON k.id_deteksi = hd.id_deteksi
         WHERE b.id_user = ?
         AND k.sumber_pengajuan = 'ahli_gizi'
         AND hd.keputusan_konsultasi = 'Perlu konsultasi'
         AND (k.hasil_konsultasi IS NULL OR TRIM(k.hasil_konsultasi) = '')
         GROUP BY b.id_balita, b.nama_balita
         ORDER BY MAX(k.id_konsultasi) DESC"
    );

    if ($stmtNotifikasi) {
        mysqli_stmt_bind_param($stmtNotifikasi, "i", $idUserAktif);
    }

} elseif (
    in_array($rolePengguna, ["kader", "petugas_kia"], true)
    && $idPuskesmasNotifikasi > 0
) {
    $stmtNotifikasi = mysqli_prepare(
        $conn,
        "SELECT b.nama_balita
         FROM konsultasi k
         INNER JOIN balita b
            ON k.id_balita = b.id_balita
         INNER JOIN hasil_deteksi hd
            ON k.id_deteksi = hd.id_deteksi
         WHERE b.id_puskesmas = ?
         AND k.sumber_pengajuan = 'ahli_gizi'
         AND hd.keputusan_konsultasi = 'Perlu konsultasi'
         AND (k.keluhan IS NULL OR TRIM(k.keluhan) = '')
         GROUP BY b.id_balita, b.nama_balita
         ORDER BY MAX(k.id_konsultasi) DESC"
    );

    if ($stmtNotifikasi) {
        mysqli_stmt_bind_param(
            $stmtNotifikasi,
            "i",
            $idPuskesmasNotifikasi
        );
    }
} else {
    $stmtNotifikasi = null;
}

if ($stmtNotifikasi) {
    mysqli_stmt_execute($stmtNotifikasi);
    $hasilNotifikasi = mysqli_stmt_get_result($stmtNotifikasi);

    while ($rowNotifikasi = mysqli_fetch_assoc($hasilNotifikasi)) {
        $daftarNamaPerluKonsultasi[] =
            (string) ($rowNotifikasi["nama_balita"] ?? "");
    }

    mysqli_stmt_close($stmtNotifikasi);
    $totalPerluKonsultasi = count($daftarNamaPerluKonsultasi);
}

/*
|--------------------------------------------------------------------------
| Statistik yang ditampilkan berdasarkan role
|--------------------------------------------------------------------------
|
| Bagian ini hanya mengatur tampilan dashboard.
| Query dan perhitungan tetap menggunakan statistik.php.
|
*/

$statistikDashboard = [];

switch ($rolePengguna) {
    case "kader":
        $statistikDashboard = [
            [
                "label" => "Total Balita",
                "nilai" => $totalBalita ?? 0,
                "ikon" => "bi-person-heart",
                "kelas" => "stat-info"
            ],
            [
                "label" => "Sudah Diukur Bulan Ini",
                "nilai" => $totalSudahDiukurBulanIni,
                "ikon" => "bi-check-circle",
                "kelas" => "stat-success"
            ],
            [
                "label" => "Belum Diukur Bulan Ini",
                "nilai" => $totalBelumDiukurBulanIni,
                "ikon" => "bi-calendar-x",
                "kelas" => "stat-warning"
            ],
            [
                "label" => "Perlu Verifikasi Ahli Gizi",
                "nilai" => $totalPerluVerifikasi,
                "ikon" => "bi-patch-question",
                "kelas" => "stat-info"
            ]
        ];
        break;

    case "petugas_kia":
        $statistikDashboard = [
            [
                "label" => "Total Balita",
                "nilai" => $totalBalita ?? 0,
                "ikon" => "bi-person-heart",
                "kelas" => "stat-info"
            ],
            [
                "label" => "Riwayat Pengukuran",
                "nilai" => $totalPengukuran ?? 0,
                "ikon" => "bi-graph-up-arrow",
                "kelas" => "stat-success"
            ],
            [
                "label" => "Hasil Deteksi",
                "nilai" => $totalHasilDeteksi ?? 0,
                "ikon" => "bi-clipboard2-pulse",
                "kelas" => "stat-warning"
            ]
        ];
        break;

    case "petugas_gizi":
        $statistikDashboard = [
            [
                "label" => "Data Balita",
                "nilai" => $totalBalita ?? 0,
                "ikon" => "bi-person-check",
                "kelas" => "stat-info"
            ],
            [
                "label" => "Pengukuran",
                "nilai" => $totalPengukuran ?? 0,
                "ikon" => "bi-graph-up-arrow",
                "kelas" => "stat-success"
            ],
            [
                "label" => "Skrining",
                "nilai" => $totalSkrining ?? 0,
                "ikon" => "bi-clipboard2-check",
                "kelas" => "stat-warning"
            ],
            [
                "label" => "Hasil Deteksi",
                "nilai" => $totalHasilDeteksi ?? 0,
                "ikon" => "bi-heart-pulse",
                "kelas" => "stat-info"
            ],
            [
                "label" => "Konsultasi",
                "nilai" => $totalKonsultasi ?? 0,
                "ikon" => "bi-chat-heart",
                "kelas" => "stat-success"
            ]
        ];
        break;

    case "orang_tua":
        $statistikDashboard = [
            [
                "label" => "Data Anak",
                "nilai" => $totalBalita ?? 0,
                "ikon" => "bi-person-heart",
                "kelas" => "stat-info"
            ],
            [
                "label" => "Riwayat Pertumbuhan",
                "nilai" => $totalPengukuran ?? 0,
                "ikon" => "bi-graph-up-arrow",
                "kelas" => "stat-success"
            ],
            [
                "label" => "Hasil Deteksi",
                "nilai" => $totalHasilDeteksi ?? 0,
                "ikon" => "bi-heart-pulse",
                "kelas" => "stat-warning"
            ],
            [
                "label" => "Konsultasi",
                "nilai" => $totalKonsultasi ?? 0,
                "ikon" => "bi-chat-heart",
                "kelas" => "stat-info"
            ]
        ];
        break;

    case "kepala_puskesmas":
        $statistikDashboard = [
            [
                "label" => "Total Balita",
                "nilai" => $totalBalita ?? 0,
                "ikon" => "bi-people",
                "kelas" => "stat-info"
            ],
            [
                "label" => "Pengukuran",
                "nilai" => $totalPengukuran ?? 0,
                "ikon" => "bi-graph-up-arrow",
                "kelas" => "stat-success"
            ],
            [
                "label" => "Hasil Deteksi",
                "nilai" => $totalHasilDeteksi ?? 0,
                "ikon" => "bi-clipboard2-pulse",
                "kelas" => "stat-warning"
            ],
            [
                "label" => "Konsultasi",
                "nilai" => $totalKonsultasi ?? 0,
                "ikon" => "bi-chat-dots",
                "kelas" => "stat-info"
            ]
        ];
        break;

    case "dinkes":
        $statistikDashboard = [
            [
                "label" => "Total Pengguna",
                "nilai" => $totalPengguna ?? 0,
                "ikon" => "bi-people-fill",
                "kelas" => "stat-info"
            ],
            [
                "label" => "Total Balita",
                "nilai" => $totalBalita ?? 0,
                "ikon" => "bi-person-heart",
                "kelas" => "stat-success"
            ],
            [
                "label" => "Total Pengukuran",
                "nilai" => $totalPengukuran ?? 0,
                "ikon" => "bi-graph-up-arrow",
                "kelas" => "stat-warning"
            ],
            [
                "label" => "Hasil Deteksi",
                "nilai" => $totalHasilDeteksi ?? 0,
                "ikon" => "bi-clipboard2-data",
                "kelas" => "stat-info"
            ]
        ];
        break;

    default:
        $statistikDashboard = [
            [
                "label" => "Total Balita",
                "nilai" => $totalBalita ?? 0,
                "ikon" => "bi-person-heart",
                "kelas" => "stat-info"
            ]
        ];
        break;
}

/*
|--------------------------------------------------------------------------
| Akses cepat berdasarkan role
|--------------------------------------------------------------------------
*/

$aksiDashboard = [];

switch ($rolePengguna) {
    case "kader":
        $aksiDashboard = [
            [
                "judul" => "Kelola Data Balita",
                "deskripsi" =>
                    "Daftarkan balita baru dan kelola profil dasar balita.",
                "ikon" => "bi-person-plus",
                "url" => "../balita/data_balita.php",
                "tombol" => "Buka Data Balita"
            ],
            [
                "judul" => "Input Antropometri",
                "deskripsi" =>
                    "Catat berat badan, tinggi atau panjang badan, dan lingkar kepala.",
                "ikon" => "bi-rulers",
                "url" => "../pengukuran/data_pengukuran.php",
                "tombol" => "Buka Pengukuran"
            ],
            [
                "judul" => "Skrining",
                "deskripsi" =>
                    "Isi dan kelola data skrining faktor risiko balita.",
                "ikon" => "bi-clipboard2-heart",
                "url" => "../skrining/hasil_skrining.php",
                "tombol" => "Buka Skrining"
            ],
            [
                "judul" => "Perlu Konsultasi",
                "deskripsi" =>
                    "Lihat balita yang telah ditetapkan Ahli Gizi perlu konsultasi dan informasikan kepada Orang Tua.",
                "ikon" => "bi-bell",
                "url" => "../konsultasi/data_konsultasi.php",
                "tombol" => "Lihat Daftar"
            ]
        ];
        break;

    case "petugas_kia":
        $aksiDashboard = [
            [
                "judul" => "Kelola Data Balita",
                "deskripsi" =>
                    "Tinjau data dasar balita sebelum melengkapi rekam medis.",
                "ikon" => "bi-person-vcard",
                "url" => "../balita/data_balita.php",
                "tombol" => "Lihat Data Balita"
            ],
            [
                "judul" => "Riwayat Kelahiran",
                "deskripsi" =>
                    "Kelola berat lahir, panjang lahir, dan riwayat persalinan.",
                "ikon" => "bi-balloon-heart",
                "url" => "../kelahiran/riwayat_kelahiran.php",
                "tombol" => "Buka Riwayat"
            ],
            [
                "judul" => "Riwayat Kesehatan",
                "deskripsi" =>
                    "Kelola catatan kesehatan dasar dan kondisi medis balita.",
                "ikon" => "bi-clipboard2-heart",
                "url" => "../kesehatan/riwayat_kesehatan.php",
                "tombol" => "Buka Kesehatan"
            ],
            [
                "judul" => "Grafik Pertumbuhan",
                "deskripsi" =>
                    "Tinjau perkembangan pertumbuhan berdasarkan riwayat pengukuran.",
                "ikon" => "bi-graph-up-arrow",
                "url" => "../pengukuran/data_pengukuran.php",
                "tombol" => "Lihat Grafik"
            ],
            [
                "judul" => "Hasil Deteksi",
                "deskripsi" =>
                    "Tinjau hasil analisis risiko stunting sebagai bahan tindak lanjut.",
                "ikon" => "bi-heart-pulse",
                "url" => "../deteksi/hasil_deteksi.php",
                "tombol" => "Lihat Hasil"
            ],
            [
                "judul" => "Perlu Konsultasi",
                "deskripsi" =>
                    "Lihat balita yang telah ditetapkan Ahli Gizi perlu konsultasi dan bantu menginformasikan Orang Tua.",
                "ikon" => "bi-bell",
                "url" => "../konsultasi/data_konsultasi.php",
                "tombol" => "Lihat Daftar"
            ]
        ];
        break;

    case "petugas_gizi":
        $aksiDashboard = [
            [
                "judul" => "Verifikasi Data Balita",
                "deskripsi" =>
                    "Tinjau dan validasi kelengkapan data balita.",
                "ikon" => "bi-person-check",
                "url" => "../balita/data_balita.php",
                "tombol" => "Buka Data Balita"
            ],
            [
                "judul" => "Deteksi Risiko Stunting",
                "deskripsi" =>
                    "Tinjau skrining dan lakukan analisis risiko stunting.",
                "ikon" => "bi-heart-pulse",
                "url" => "../skrining/hasil_skrining.php",
                "tombol" => "Buka Deteksi"
            ],
            [
                "judul" => "Grafik Pertumbuhan",
                "deskripsi" =>
                    "Pantau tren berat dan tinggi badan balita.",
                "ikon" => "bi-graph-up-arrow",
                "url" => "../pengukuran/data_pengukuran.php",
                "tombol" => "Lihat Grafik"
            ],
            [
                "judul" => "Konsultasi & Monitoring",
                "deskripsi" =>
                    "Berikan konsultasi gizi dan pantau tindak lanjut.",
                "ikon" => "bi-chat-heart",
                "url" => "../konsultasi/data_konsultasi.php",
                "tombol" => "Buka Konsultasi"
            ]
        ];
        break;

    case "orang_tua":
        $aksiDashboard = [
            [
                "judul" => "Hasil Deteksi Anak",
                "deskripsi" =>
                    "Lihat status gizi dan tingkat risiko stunting anak.",
                "ikon" => "bi-heart-pulse",
                "url" => "../deteksi/hasil_deteksi.php",
                "tombol" => "Lihat Hasil"
            ],
            [
                "judul" => "Grafik Pertumbuhan Anak",
                "deskripsi" =>
                    "Pantau perkembangan pertumbuhan anak dari waktu ke waktu.",
                "ikon" => "bi-graph-up-arrow",
                "url" => "../pengukuran/data_pengukuran.php",
                "tombol" => "Lihat Grafik"
            ],
            [
                "judul" => "Konsultasi & Monitoring",
                "deskripsi" =>
                    "Lihat konsultasi yang telah direkomendasikan Ahli Gizi dan hasil tindak lanjutnya.",
                "ikon" => "bi-chat-heart",
                "url" => "../konsultasi/data_konsultasi.php",
                "tombol" => "Buka Konsultasi"
            ]
        ];
        break;

    case "kepala_puskesmas":
        $aksiDashboard = [
            [
                "judul" => "Grafik Pertumbuhan",
                "deskripsi" =>
                    "Pantau perkembangan pertumbuhan balita di wilayah Puskesmas.",
                "ikon" => "bi-graph-up-arrow",
                "url" => "../pengukuran/data_pengukuran.php",
                "tombol" => "Lihat Grafik"
            ],
            [
                "judul" => "Monitoring Konsultasi",
                "deskripsi" =>
                    "Pantau keterlaksanaan pelayanan konsultasi gizi.",
                "ikon" => "bi-chat-dots",
                "url" => "../konsultasi/data_konsultasi.php",
                "tombol" => "Lihat Monitoring"
            ],
            [
                "judul" => "Laporan Stunting",
                "deskripsi" =>
                    "Lihat, filter, dan cetak laporan periodik Puskesmas.",
                "ikon" => "bi-file-earmark-medical",
                "url" => "../laporan/laporan_stunting.php",
                "tombol" => "Buka Laporan"
            ]
        ];
        break;

    case "dinkes":
        $aksiDashboard = [
            [
                "judul" => "Kelola Pengguna",
                "deskripsi" =>
                    "Tambah, lihat, edit, dan hapus akun pengguna sistem.",
                "ikon" => "bi-people",
                "url" => "../user/data_user.php",
                "tombol" => "Kelola Pengguna"
            ],
            [
                "judul" => "Grafik Pertumbuhan Agregat",
                "deskripsi" =>
                    "Pantau tren pertumbuhan balita berdasarkan data wilayah.",
                "ikon" => "bi-graph-up-arrow",
                "url" => "../pengukuran/data_pengukuran.php",
                "tombol" => "Lihat Grafik"
            ],
            [
                "judul" => "Laporan Stunting",
                "deskripsi" =>
                    "Akses rekapitulasi data stunting untuk kebutuhan kebijakan.",
                "ikon" => "bi-file-earmark-bar-graph",
                "url" => "../laporan/laporan_stunting.php",
                "tombol" => "Buka Laporan"
            ]
        ];
        break;
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<style>
    .dashboard-stat-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px;
        width: 100%;
        margin-bottom: 24px;
    }

    .dashboard-stat-grid .stat-card {
        width: 100%;
        min-width: 0;
        margin: 0;
    }

    .dashboard-feature-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(300px, 1fr));
        gap: 16px;
        width: 100%;
    }

    .dashboard-feature-card {
        width: 100%;
        min-width: 0;
        height: 100%;
        display: flex;
        border: 1px solid rgba(124, 135, 151, .12);
        border-radius: 18px;
        background:
            linear-gradient(
                145deg,
                rgba(255, 255, 255, .96),
                rgba(250, 247, 252, .92)
            );
        box-shadow:
            0 8px 22px rgba(70, 83, 102, .06);
    }

    .dashboard-feature-card .card-body {
        width: 100%;
        display: flex;
        padding: 20px;
    }

    .dashboard-feature-content {
        width: 100%;
        display: flex;
        align-items: flex-start;
        gap: 15px;
    }

    .dashboard-feature-text {
        min-width: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .dashboard-feature-text p {
        flex: 1;
    }

    .dashboard-feature-text .btn {
        align-self: flex-start;
    }

    @media (max-width: 575.98px) {
        .dashboard-stat-grid,
        .dashboard-feature-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-feature-card .card-body {
            padding: 17px;
        }
    }
</style>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php if ($totalPerluKonsultasi > 0): ?>

            <div class="alert alert-warning mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="fw-bold mb-1">
                            <i class="bi bi-bell-fill me-1"></i>
                            <?= $totalPerluKonsultasi; ?> balita perlu konsultasi Ahli Gizi
                        </div>

                        <div>
                            <?php if (in_array($rolePengguna, ["kader", "petugas_kia"], true)): ?>
                                Ahli Gizi telah menetapkan
                                <strong><?= htmlspecialchars(implode(", ", $daftarNamaPerluKonsultasi), ENT_QUOTES, "UTF-8"); ?></strong>
                                perlu konsultasi. Mohon informasikan kepada Orang Tua agar segera menghubungi Ahli Gizi Puskesmas.
                            <?php elseif ($rolePengguna === "orang_tua"): ?>
                                Ahli Gizi telah menetapkan bahwa
                                <strong><?= htmlspecialchars(implode(", ", $daftarNamaPerluKonsultasi), ENT_QUOTES, "UTF-8"); ?></strong>
                                perlu konsultasi. Silakan buka menu konsultasi untuk melihat informasi dan tindak lanjut.
                            <?php endif; ?>
                        </div>
                    </div>

                    <a
                        href="../konsultasi/data_konsultasi.php"
                        class="btn btn-warning btn-sm"
                    >
                        <i class="bi bi-chat-heart me-1"></i>
                        Buka Konsultasi
                    </a>
                </div>
            </div>

        <?php endif; ?>

        <?php if (in_array($rolePengguna, $roleTerikatPuskesmas, true)): ?>

            <?php if ($puskesmasBelumTerhubung): ?>

                <div class="alert alert-danger mb-4">
                    <div class="fw-bold mb-1">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Puskesmas belum terhubung
                    </div>
                    <div>
                        Akun ini belum memiliki wilayah Puskesmas. Data dashboard tidak ditampilkan sampai Puskesmas akun ditetapkan.
                    </div>
                </div>

            <?php else: ?>

                <div class="card content-card mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <div class="small text-muted mb-1">
                                    Data otomatis mengikuti Puskesmas akun
                                </div>
                                <div class="fw-bold">
                                    <i class="bi bi-hospital me-1"></i>
                                    <?= htmlspecialchars(
                                        $namaPuskesmasAkun !== ""
                                            ? $namaPuskesmasAkun
                                            : "Puskesmas terhubung",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </div>
                            </div>

                            <span class="badge badge-info">
                                <i class="bi bi-lock me-1"></i>
                                Wilayah terkunci
                            </span>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <div class="dashboard-stat-grid">

            <?php foreach (
                $statistikDashboard
                as $statistik
            ): ?>

                <div
                    class="stat-card <?= htmlspecialchars(
                        $statistik["kelas"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                >

                    <div class="stat-icon">

                        <i
                            class="bi <?= htmlspecialchars(
                                $statistik["ikon"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                        ></i>

                    </div>

                    <div class="stat-content">

                        <p class="stat-label">
                            <?= htmlspecialchars(
                                $statistik["label"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </p>

                        <p class="stat-value">
                            <?= (int) $statistik["nilai"]; ?>
                        </p>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <?php if (
            $rolePengguna === "kader"
            && !$puskesmasBelumTerhubung
        ): ?>

            <div class="card content-card mb-4">

                <div class="card-header">
                    <div>
                        <h4 class="mb-1">
                            Pemantauan Antropometri <?= htmlspecialchars(
                                $labelPeriodeAktif,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </h4>
                        <small class="text-muted">
                            Satu balita ditampilkan satu kali. Gunakan daftar ini
                            untuk melihat siapa yang sudah dan belum diukur pada
                            periode Posyandu bulan berjalan.
                        </small>
                    </div>

                    <a
                        href="../pengukuran/data_pengukuran.php?tampilan=terbaru"
                        class="btn btn-primary btn-sm"
                    >
                        <i class="bi bi-rulers me-1"></i>
                        Buka Antropometri
                    </a>
                </div>

                <div class="card-body">

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 70px;" class="text-center">
                                        No
                                    </th>
                                    <th>Nama Balita</th>
                                    <th class="text-center">
                                        Pengukuran Terakhir
                                    </th>
                                    <th class="text-center">
                                        Status Bulan Ini
                                    </th>
                                    <th class="text-center" style="width: 180px;">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>

                            <?php if (count($monitoringAntropometriKader) > 0): ?>

                                <?php foreach (
                                    $monitoringAntropometriKader
                                    as $indexMonitoring => $dataMonitoring
                                ): ?>
                                    <?php
                                    $statusMonitoring =
                                        statusPemantauanBulananDashboard(
                                            $dataMonitoring[
                                                "tanggal_pengukuran_terakhir"
                                            ] ?? null
                                        );

                                    $sudahBulanIni =
                                        $statusMonitoring["kelas"] === "success";
                                    ?>

                                    <tr>
                                        <td class="text-center">
                                            <?= $indexMonitoring + 1; ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $dataMonitoring["nama_balita"]
                                                        ?? "-"
                                                    ),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </strong>
                                        </td>

                                        <td class="text-center">
                                            <?= formatTanggalDashboard(
                                                $dataMonitoring[
                                                    "tanggal_pengukuran_terakhir"
                                                ] ?? null
                                            ); ?>
                                        </td>

                                        <td class="text-center">
                                            <span class="badge bg-<?= htmlspecialchars(
                                                $statusMonitoring["kelas"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>">
                                                <i class="bi <?= htmlspecialchars(
                                                    $statusMonitoring["ikon"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?> me-1"></i>
                                                <?= htmlspecialchars(
                                                    $statusMonitoring["label"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </span>
                                        </td>

                                        <td class="text-center">
                                            <?php if ($sudahBulanIni): ?>
                                                <a
                                                    href="../pengukuran/data_pengukuran.php?tampilan=riwayat&cari=<?= urlencode(
                                                        (string) (
                                                            $dataMonitoring["nama_balita"]
                                                            ?? ""
                                                        )
                                                    ); ?>"
                                                    class="btn btn-light btn-sm"
                                                >
                                                    <i class="bi bi-clock-history me-1"></i>
                                                    Riwayat
                                                </a>
                                            <?php else: ?>
                                                <a
                                                    href="../pengukuran/tambah_pengukuran.php?id_balita=<?= (int) (
                                                        $dataMonitoring["id_balita"]
                                                        ?? 0
                                                    ); ?>"
                                                    class="btn btn-primary btn-sm"
                                                >
                                                    <i class="bi bi-plus-circle me-1"></i>
                                                    Input Antropometri
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="5">
                                        <div class="text-center text-muted py-4">
                                            Belum ada balita pada wilayah Puskesmas akun Kader.
                                        </div>
                                    </td>
                                </tr>

                            <?php endif; ?>

                            </tbody>
                        </table>
                    </div>

                </div>

            </div>

        <?php endif; ?>

        <?php if (count($aksiDashboard) > 0): ?>

            <div class="card content-card">

                <div class="card-header">

                    <div>

                        <h4 class="mb-1">
                            Menu Utama
                        </h4>

                        <small class="text-muted">
                            Fitur yang tersedia sesuai tugas
                            dan hak akses akunmu.
                        </small>

                    </div>

                    <span class="badge badge-primary">
                        <?= count($aksiDashboard); ?> fitur
                    </span>

                </div>

                <div class="card-body">

                    <div class="dashboard-feature-grid">

                        <?php foreach (
                            $aksiDashboard
                            as $aksi
                        ): ?>

                            <div class="dashboard-feature-card">

                                <div class="card-body">

                                    <div class="dashboard-feature-content">

                                            <div
                                                class="stat-icon
                                                flex-shrink-0"
                                            >
                                                <i
                                                    class="bi <?= htmlspecialchars(
                                                        $aksi["ikon"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>"
                                                ></i>
                                            </div>

                                        <div class="dashboard-feature-text">

                                                <h5 class="mb-2">
                                                    <?= htmlspecialchars(
                                                        $aksi["judul"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                </h5>

                                                <p
                                                    class="text-muted
                                                    small mb-3"
                                                >
                                                    <?= htmlspecialchars(
                                                        $aksi["deskripsi"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                </p>

                                                <a
                                                    href="<?= htmlspecialchars(
                                                        $aksi["url"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>"
                                                    class="btn
                                                    btn-primary btn-sm"
                                                >
                                                    <?= htmlspecialchars(
                                                        $aksi["tombol"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>

                                                    <i
                                                        class="bi
                                                        bi-arrow-right"
                                                    ></i>
                                                </a>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>