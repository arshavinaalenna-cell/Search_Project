<?php

$roleSidebar = $_SESSION["role"] ?? "";

/*
|--------------------------------------------------------------------------
| Menentukan halaman aktif
|--------------------------------------------------------------------------
*/

$halamanSekarang = basename(
    parse_url(
        $_SERVER["REQUEST_URI"] ?? "",
        PHP_URL_PATH
    )
);

function menuAktif(array $halaman): string
{
    global $halamanSekarang;

    return in_array(
        $halamanSekarang,
        $halaman,
        true
    ) ? "active" : "";
}

/*
|--------------------------------------------------------------------------
| Daftar menu berdasarkan role
|--------------------------------------------------------------------------
*/

$menuSidebar = [

    "dinkes" => [
        [
            "nama" => "Dashboard",
            "ikon" => "⌂",
            "url" => "../dashboard/dashboard.php",
            "halaman" => ["dashboard.php"]
        ],
        [
            "nama" => "Data Pengguna",
            "ikon" => "◎",
            "url" => "../user/data_user.php",
            "halaman" => [
                "data_user.php",
                "tambah_user.php",
                "edit_user.php"
            ]
        ],
        [
            "nama" => "Data Balita",
            "ikon" => "♡",
            "url" => "../balita/data_balita.php",
            "halaman" => [
                "data_balita.php",
                "detail_balita.php",
                "tambah_balita.php",
                "edit_balita.php"
            ]
        ],
        [
            "nama" => "Data Pengukuran",
            "ikon" => "↗",
            "url" => "../pengukuran/data_pengukuran.php",
            "halaman" => [
                "data_pengukuran.php",
                "tambah_pengukuran.php",
                "edit_pengukuran.php"
            ]
        ],
        [
            "nama" => "Data Skrining",
            "ikon" => "✓",
            "url" => "../skrining/hasil_skrining.php",
            "halaman" => [
                "form_skrining.php",
                "hasil_skrining.php"
            ]
        ],
        [
            "nama" => "Hasil Deteksi",
            "ikon" => "◇",
            "url" => "../deteksi/hasil_deteksi.php",
            "halaman" => [
                "hasil_deteksi.php",
                "detail_deteksi.php",
                "analisis_deteksi.php",
                "analisis_stunting.php"
            ]
        ],
        [
            "nama" => "Monitoring Konsultasi",
            "ikon" => "✦",
            "url" => "../konsultasi/data_konsultasi.php",
            "halaman" => [
                "data_konsultasi.php",
                "tambah_konsultasi.php",
                "detail_konsultasi.php"
            ]
        ],
        [
            "nama" => "Laporan Stunting",
            "ikon" => "▣",
            "url" => "../laporan/laporan_stunting.php",
            "halaman" => [
                "laporan_stunting.php",
                "detail_laporan.php",
                "cetak_laporan.php"
            ]
        ]
    ],

    "kepala_puskesmas" => [
        [
            "nama" => "Dashboard",
            "ikon" => "⌂",
            "url" => "../dashboard/dashboard.php",
            "halaman" => ["dashboard.php"]
        ],
        [
            "nama" => "Data Balita",
            "ikon" => "♡",
            "url" => "../balita/data_balita.php",
            "halaman" => [
                "data_balita.php",
                "detail_balita.php"
            ]
        ],
        [
            "nama" => "Grafik Pertumbuhan",
            "ikon" => "↗",
            "url" => "../pengukuran/data_pengukuran.php",
            "halaman" => ["data_pengukuran.php"]
        ],
        [
            "nama" => "Data Stunting",
            "ikon" => "◇",
            "url" => "../deteksi/hasil_deteksi.php",
            "halaman" => [
                "hasil_deteksi.php",
                "detail_deteksi.php"
            ]
        ],
        [
            "nama" => "Monitoring Konsultasi",
            "ikon" => "✦",
            "url" => "../konsultasi/data_konsultasi.php",
            "halaman" => [
                "data_konsultasi.php",
                "detail_konsultasi.php"
            ]
        ],
        [
            "nama" => "Laporan Stunting",
            "ikon" => "▣",
            "url" => "../laporan/laporan_stunting.php",
            "halaman" => [
                "laporan_stunting.php",
                "detail_laporan.php",
                "cetak_laporan.php"
            ]
        ]
    ],

    "kader" => [
        [
            "nama" => "Dashboard",
            "ikon" => "⌂",
            "url" => "../dashboard/dashboard.php",
            "halaman" => ["dashboard.php"]
        ],
        [
            "nama" => "Data Balita",
            "ikon" => "♡",
            "url" => "../balita/data_balita.php",
            "halaman" => [
                "data_balita.php",
                "detail_balita.php",
                "edit_balita.php"
            ]
        ],
        [
            "nama" => "Tambah Balita",
            "ikon" => "+",
            "url" => "../balita/tambah_balita.php",
            "halaman" => ["tambah_balita.php"]
        ],
        [
            "nama" => "Data Pengukuran",
            "ikon" => "↗",
            "url" => "../pengukuran/data_pengukuran.php",
            "halaman" => [
                "data_pengukuran.php",
                "edit_pengukuran.php"
            ]
        ],
        [
            "nama" => "Input Antropometri",
            "ikon" => "+",
            "url" => "../pengukuran/tambah_pengukuran.php",
            "halaman" => ["tambah_pengukuran.php"]
        ]
    ],

    "petugas_kia" => [
        [
            "nama" => "Dashboard",
            "ikon" => "⌂",
            "url" => "../dashboard/dashboard.php",
            "halaman" => ["dashboard.php"]
        ],
        [
            "nama" => "Data Balita",
            "ikon" => "♡",
            "url" => "../balita/data_balita.php",
            "halaman" => [
                "data_balita.php",
                "detail_balita.php"
            ]
        ],
        [
            "nama" => "Riwayat Kelahiran",
            "ikon" => "○",
            "url" => "../kelahiran/riwayat_kelahiran.php",
            "halaman" => [
                "riwayat_kelahiran.php",
                "tambah_kelahiran.php",
                "edit_kelahiran.php"
            ]
        ],
        [
            "nama" => "Riwayat Kesehatan",
            "ikon" => "✚",
            "url" => "../kesehatan/riwayat_kesehatan.php",
            "halaman" => [
                "riwayat_kesehatan.php",
                "detail_kesehatan.php",
                "tambah_kesehatan.php",
                "edit_kesehatan.php"
            ]
        ],
        [
            "nama" => "Grafik Pertumbuhan",
            "ikon" => "↗",
            "url" => "../pengukuran/data_pengukuran.php",
            "halaman" => ["data_pengukuran.php"]
        ],
        [
            "nama" => "Hasil Deteksi",
            "ikon" => "◇",
            "url" => "../deteksi/hasil_deteksi.php",
            "halaman" => [
                "hasil_deteksi.php",
                "detail_deteksi.php"
            ]
        ]
    ],

    "petugas_gizi" => [
        [
            "nama" => "Dashboard",
            "ikon" => "⌂",
            "url" => "../dashboard/dashboard.php",
            "halaman" => ["dashboard.php"]
        ],
        [
            "nama" => "Data Balita",
            "ikon" => "♡",
            "url" => "../balita/data_balita.php",
            "halaman" => [
                "data_balita.php",
                "detail_balita.php"
            ]
        ],
        [
            "nama" => "Data Pengukuran",
            "ikon" => "↗",
            "url" => "../pengukuran/data_pengukuran.php",
            "halaman" => [
                "data_pengukuran.php",
                "tambah_pengukuran.php",
                "edit_pengukuran.php"
            ]
        ],
        [
            "nama" => "Skrining Awal",
            "ikon" => "✓",
            "url" => "../skrining/form_skrining.php",
            "halaman" => ["form_skrining.php"]
        ],
        [
            "nama" => "Hasil Skrining",
            "ikon" => "▤",
            "url" => "../skrining/hasil_skrining.php",
            "halaman" => ["hasil_skrining.php"]
        ],
        [
            "nama" => "Deteksi Risiko Stunting",
            "ikon" => "◇",
            "url" => "../deteksi/hasil_deteksi.php",
            "halaman" => [
                "hasil_deteksi.php",
                "detail_deteksi.php",
                "analisis_deteksi.php",
                "analisis_stunting.php"
            ]
        ],
        [
            "nama" => "Konsultasi dan Monitoring",
            "ikon" => "✦",
            "url" => "../konsultasi/data_konsultasi.php",
            "halaman" => [
                "data_konsultasi.php",
                "tambah_konsultasi.php",
                "detail_konsultasi.php"
            ]
        ],
        [
            "nama" => "Laporan Stunting",
            "ikon" => "▣",
            "url" => "../laporan/laporan_stunting.php",
            "halaman" => [
                "laporan_stunting.php",
                "detail_laporan.php",
                "cetak_laporan.php"
            ]
        ]
    ],

    "orang_tua" => [
        [
            "nama" => "Dashboard",
            "ikon" => "⌂",
            "url" => "../dashboard/dashboard.php",
            "halaman" => ["dashboard.php"]
        ],
        [
            "nama" => "Data Anak",
            "ikon" => "♡",
            "url" => "../balita/data_balita.php",
            "halaman" => [
                "data_balita.php",
                "detail_balita.php"
            ]
        ],
        [
            "nama" => "Grafik Pertumbuhan",
            "ikon" => "↗",
            "url" => "../pengukuran/data_pengukuran.php",
            "halaman" => ["data_pengukuran.php"]
        ],
        [
            "nama" => "Hasil Deteksi",
            "ikon" => "◇",
            "url" => "../deteksi/hasil_deteksi.php",
            "halaman" => [
                "hasil_deteksi.php",
                "detail_deteksi.php"
            ]
        ],
        [
            "nama" => "Konsultasi",
            "ikon" => "✦",
            "url" => "../konsultasi/data_konsultasi.php",
            "halaman" => [
                "data_konsultasi.php",
                "detail_konsultasi.php"
            ]
        ]
    ]
];

$daftarMenu = $menuSidebar[$roleSidebar] ?? [
    [
        "nama" => "Dashboard",
        "ikon" => "⌂",
        "url" => "../dashboard/dashboard.php",
        "halaman" => ["dashboard.php"]
    ]
];
?>

<aside class="sidebar">

    <div class="sidebar-title">
        Menu Utama
    </div>

    <nav
        class="sidebar-menu"
        aria-label="Navigasi utama"
    >

        <?php foreach ($daftarMenu as $menu): ?>

            <?php
            $classAktif = menuAktif($menu["halaman"]);
            ?>

            <a
                href="<?= htmlspecialchars(
                    $menu["url"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
                class="<?= $classAktif ?>"
                <?= $classAktif === "active"
                    ? 'aria-current="page"'
                    : ""
                ?>
            >
                <span aria-hidden="true">
                    <?= htmlspecialchars(
                        $menu["ikon"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </span>

                <span>
                    <?= htmlspecialchars(
                        $menu["nama"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </span>
            </a>

        <?php endforeach; ?>

    </nav>

</aside>