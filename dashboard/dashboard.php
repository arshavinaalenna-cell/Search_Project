<?php

require_once "../auth/session.php";
require_once "../config/koneksi.php";
require_once "statistik.php";

$namaPengguna = htmlspecialchars(
    $_SESSION["nama"] ?? "Pengguna",
    ENT_QUOTES,
    "UTF-8"
);

$rolePengguna = $_SESSION["role"] ?? "";

$namaRole = [
    "kader" => "Kader",
    "petugas_kia" => "Petugas KIA",
    "petugas_gizi" => "Petugas Gizi",
    "orang_tua" => "Orang Tua",
    "kepala_puskesmas" => "Kepala Puskesmas",
    "dinkes" => "Dinas Kesehatan"
];

$roleTampil = $namaRole[$rolePengguna] ?? "Pengguna";

$judulHalaman =
    "Dashboard | Sistem Deteksi Stunting";

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
                "label" => "Pengukuran Antropometri",
                "nilai" => $totalPengukuran ?? 0,
                "ikon" => "bi-rulers",
                "kelas" => "stat-success"
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
                "label" => "Skrining Awal",
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
                "label" => "Total Balita",
                "nilai" => $totalBalita ?? 0,
                "ikon" => "bi-people",
                "kelas" => "stat-info"
            ],
            [
                "label" => "Total Pengukuran",
                "nilai" => $totalPengukuran ?? 0,
                "ikon" => "bi-graph-up-arrow",
                "kelas" => "stat-success"
            ],
            [
                "label" => "Hasil Deteksi",
                "nilai" => $totalHasilDeteksi ?? 0,
                "ikon" => "bi-clipboard2-data",
                "kelas" => "stat-warning"
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
                    "Sampaikan keluhan dan berdiskusi dengan Petugas Gizi.",
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

/*
|--------------------------------------------------------------------------
| Deskripsi dashboard berdasarkan role
|--------------------------------------------------------------------------
*/

$deskripsiRole = [
    "kader" =>
        "Kelola pendaftaran balita dan pencatatan pengukuran antropometri dari kegiatan Posyandu.",

    "petugas_kia" =>
        "Kelola rekam medis dasar balita serta tinjau pertumbuhan dan hasil deteksi risiko stunting.",

    "petugas_gizi" =>
        "Validasi data balita, tinjau hasil pengukuran, lakukan deteksi risiko, dan berikan konsultasi gizi.",

    "orang_tua" =>
        "Pantau grafik pertumbuhan, hasil deteksi, dan konsultasi terkait kondisi gizi anak.",

    "kepala_puskesmas" =>
        "Pantau statistik Puskesmas, aktivitas konsultasi, serta laporan periodik pelayanan stunting.",

    "dinkes" =>
        "Pantau data agregat wilayah dan akses rekapitulasi laporan stunting untuk mendukung kebijakan."
];

$teksDeskripsi =
    $deskripsiRole[$rolePengguna]
    ?? "Selamat datang di Sistem Deteksi Stunting.";

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    <i class="bi bi-grid-1x2 me-2"></i>
                    Dashboard
                </h1>

                <p class="page-subtitle">
                    Ringkasan informasi untuk
                    <?= htmlspecialchars(
                        $roleTampil,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>.
                </p>

            </div>

        </div>

        <div class="card welcome-card mb-4">

            <div class="card-body">

                <h4 class="mb-2">
                    Selamat Datang, <?= $namaPengguna; ?>
                </h4>

                <p class="mb-0 text-muted">
                    <?= htmlspecialchars(
                        $teksDeskripsi,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </p>

            </div>

        </div>

        <div class="stat-grid">

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

                    <div class="row g-3">

                        <?php foreach (
                            $aksiDashboard
                            as $aksi
                        ): ?>

                            <div class="col-12 col-md-6 col-xl-4">

                                <div
                                    class="card h-100 border-0
                                    bg-light"
                                >

                                    <div class="card-body">

                                        <div
                                            class="d-flex
                                            align-items-start gap-3"
                                        >

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

                                            <div class="flex-grow-1">

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

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>