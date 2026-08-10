<?php

require_once "../auth/session.php";
require_once "../config/koneksi.php";
require_once "statistik.php";

$rolePengguna = $_SESSION["role"] ?? "";

$filterPuskesmas = $rolePengguna === "kader"
    ? max(0, (int) ($_GET["puskesmas"] ?? 0))
    : 0;

$daftarPuskesmas = [];

if ($rolePengguna === "kader") {
    $queryPuskesmas = mysqli_query(
        $conn,
        "SELECT id_puskesmas, nama_puskesmas
         FROM puskesmas
         ORDER BY nama_puskesmas ASC"
    );

    if ($queryPuskesmas) {
        while ($puskesmas = mysqli_fetch_assoc($queryPuskesmas)) {
            $daftarPuskesmas[] = $puskesmas;
        }
    }
}

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

if ($rolePengguna === "kader" && $filterPuskesmas > 0) {
    foreach ($aksiDashboard as &$aksi) {
        if (
            strpos($aksi["url"], "data_balita.php") !== false
            || strpos($aksi["url"], "data_pengukuran.php") !== false
        ) {
            $aksi["url"] .= "?puskesmas=" . $filterPuskesmas;
        }
    }
    unset($aksi);
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

        <?php if ($rolePengguna === "kader"): ?>

            <div class="card content-card mb-4">

                <div class="card-body">

                    <form
                        method="GET"
                        class="row g-2 align-items-end"
                    >

                        <div class="col-12 col-lg-8">

                            <label
                                for="filter_puskesmas"
                                class="form-label small text-muted mb-1"
                            >
                                Filter data dashboard berdasarkan Puskesmas
                            </label>

                            <select
                                id="filter_puskesmas"
                                name="puskesmas"
                                class="form-select"
                            >
                                <option value="0">
                                    Semua Puskesmas
                                </option>

                                <?php foreach ($daftarPuskesmas as $puskesmas): ?>
                                    <option
                                        value="<?= (int) $puskesmas["id_puskesmas"]; ?>"
                                        <?= $filterPuskesmas === (int) $puskesmas["id_puskesmas"]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $puskesmas["nama_puskesmas"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        </div>

                        <div class="col-6 col-lg-2">
                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                <i class="bi bi-funnel"></i>
                                Filter
                            </button>
                        </div>

                        <div class="col-6 col-lg-2">
                            <a
                                href="dashboard.php"
                                class="btn btn-light w-100"
                            >
                                <i class="bi bi-x-circle"></i>
                                Hapus Filter
                            </a>
                        </div>

                    </form>

                </div>

            </div>

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