<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua",
    "petugas_gizi",
    "petugas_kia",
    "kader",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Detail Konsultasi | Sistem Deteksi Stunting";

$idKonsultasi = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

$kembali =
    strtolower(
        trim(
            (string) (
                $_GET["kembali"]
                ?? ""
            )
        )
    );

if (
    !in_array(
        $kembali,
        ["aktif", "riwayat"],
        true
    )
) {
    $kembali = "aktif";
}

$urlKembali =
    $kembali === "riwayat"
        ? "riwayat_konsultasi.php"
        : "data_konsultasi.php";

if (!$idKonsultasi) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = null;

/*
|--------------------------------------------------------------------------
| Puskesmas pengguna aktif
|--------------------------------------------------------------------------
|
| Petugas Gizi dan Kepala Puskesmas hanya boleh melihat konsultasi
| balita dari Puskesmas yang sama dengan akun mereka.
|
*/

if (
    in_array(
        $roleAktif,
        ["petugas_gizi", "petugas_kia", "kader", "kepala_puskesmas"],
        true
    )
) {
    $stmtPuskesmas = mysqli_prepare(
        $conn,
        "SELECT id_puskesmas
         FROM pengguna
         WHERE id_user = ?
         LIMIT 1"
    );

    if (!$stmtPuskesmas) {
        die(
            "Gagal memeriksa Puskesmas pengguna: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtPuskesmas,
        "i",
        $idUserAktif
    );

    mysqli_stmt_execute($stmtPuskesmas);

    $hasilPuskesmas =
        mysqli_stmt_get_result(
            $stmtPuskesmas
        );

    $dataPuskesmas =
        mysqli_fetch_assoc(
            $hasilPuskesmas
        );

    mysqli_stmt_close($stmtPuskesmas);

    if (
        $dataPuskesmas
        && !empty(
            $dataPuskesmas["id_puskesmas"]
        )
    ) {
        $idPuskesmasAktif =
            (int) $dataPuskesmas[
                "id_puskesmas"
            ];
    }
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        k.id_konsultasi,
        k.id_deteksi,
        k.sumber_pengajuan,
        k.id_balita,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        k.status_konsultasi,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu,
        b.id_user AS id_orang_tua,
        b.id_puskesmas,
        ps.nama_puskesmas,
        p.nama AS nama_petugas,
        p.username AS username_petugas,
        hd.status_stunting,
        hd.keputusan_konsultasi
     FROM konsultasi k
     INNER JOIN balita b
        ON k.id_balita = b.id_balita
     LEFT JOIN puskesmas ps
        ON b.id_puskesmas = ps.id_puskesmas
     LEFT JOIN pengguna p
        ON k.id_petugas = p.id_user
     INNER JOIN hasil_deteksi hd
        ON k.id_deteksi = hd.id_deteksi
     WHERE k.id_konsultasi = ?
     AND k.sumber_pengajuan = 'ahli_gizi'
     AND hd.keputusan_konsultasi = 'Perlu konsultasi' 
     LIMIT 1"
);

if (!$stmt) {
    die(
        "Gagal menyiapkan detail konsultasi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $idKonsultasi
);

mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasil);

mysqli_stmt_close($stmt);

if (!$data) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Orang tua hanya boleh melihat konsultasi anak sendiri
|--------------------------------------------------------------------------
*/

if (
    $roleAktif === "orang_tua"
    && (int) $data["id_orang_tua"] !== $idUserAktif
) {
    http_response_code(403);

    echo "
        <h2>Akses Ditolak</h2>
        <p>Kamu hanya dapat melihat konsultasi anakmu sendiri.</p>
        <a href='data_konsultasi.php'>Kembali</a>
    ";

    exit;
}

/*
|--------------------------------------------------------------------------
| Pembatasan wilayah Puskesmas
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $roleAktif,
        ["petugas_gizi", "petugas_kia", "kader", "kepala_puskesmas"],
        true
    )
) {
    $idPuskesmasBalita =
        !empty($data["id_puskesmas"])
            ? (int) $data["id_puskesmas"]
            : null;

    if (
        $idPuskesmasAktif === null
        || $idPuskesmasBalita === null
        || $idPuskesmasAktif !==
            $idPuskesmasBalita
    ) {
        http_response_code(403);

        echo "
            <h2>Akses Ditolak</h2>
            <p>Konsultasi ini bukan bagian dari Puskesmas akun Anda.</p>
            <a href='data_konsultasi.php'>Kembali</a>
        ";

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Petugas Gizi hanya boleh mengedit konsultasi yang ditugaskan kepadanya
|--------------------------------------------------------------------------
*/

$bolehEdit = (
    $roleAktif === "petugas_gizi"
    && (int) $data["id_petugas"] === $idUserAktif
);

$keluhanKosong = (
    trim((string) ($data["keluhan"] ?? "")) === ""
);

/*
|--------------------------------------------------------------------------
| Orang Tua boleh mengirim keluhan (awal maupun tambahan) selama
| konsultasi masih aktif, yaitu selama Petugas Gizi belum menyelesaikan
| konsultasi tersebut.
|--------------------------------------------------------------------------
*/
$bolehMulaiKonsultasi = (
    $roleAktif === "orang_tua"
);

$hasilKosong = (
    trim(
        (string) (
            $data["hasil_konsultasi"] ?? ""
        )
    ) === ""
);

$tindakLanjutKosong = (
    trim(
        (string) (
            $data["tindak_lanjut"] ?? ""
        )
    ) === ""
);

$konsultasiSelesai =
    strtolower(
        trim(
            (string) (
                $data["status_konsultasi"]
                ?? "aktif"
            )
        )
    ) === "selesai";

/*
|--------------------------------------------------------------------------
| Detail Riwayat selalu read-only
|--------------------------------------------------------------------------
|
| Konsultasi yang sudah selesai atau dibuka dari menu Riwayat tidak boleh
| menampilkan tombol Edit Hasil. Riwayat merupakan catatan pelayanan final.
|
*/

if (
    $konsultasiSelesai
    || $kembali === "riwayat"
) {
    $bolehEdit = false;
    $bolehMulaiKonsultasi = false;
}

if ($keluhanKosong) {
    $statusKonsultasi =
        "Menunggu Orang Tua";

    $kelasStatusKonsultasi =
        "bg-warning text-dark";

    $ikonStatusKonsultasi =
        "bi-person-exclamation";
} elseif ($hasilKosong) {
    $statusKonsultasi =
        "Menunggu Ahli Gizi";

    $kelasStatusKonsultasi =
        "bg-info text-dark";

    $ikonStatusKonsultasi =
        "bi-hourglass-split";
} else {
    if ($konsultasiSelesai) {
        $statusKonsultasi =
            "Selesai";

        $kelasStatusKonsultasi =
            "bg-success";

        $ikonStatusKonsultasi =
            "bi-check-circle";
    } else {
        $statusKonsultasi =
            "Tersimpan Sementara";

        $kelasStatusKonsultasi =
            "bg-info";

        $ikonStatusKonsultasi =
            "bi-save";
    }
}

$tanggalTampil = "-";

if (!empty($data["tanggal"])) {
    $tanggalTampil = date(
        "d-m-Y",
        strtotime($data["tanggal"])
    );
}

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
                        Detail Konsultasi
                    </h4>

                    <small class="text-muted">
                        Informasi lengkap konsultasi gizi balita.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        !empty(
                            $data["nama_puskesmas"]
                        )
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex align-items-center px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= htmlspecialchars(
                                $data["nama_puskesmas"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </span>

                    <?php endif; ?>

                    <a
                        href="<?= htmlspecialchars(
                            $urlKembali,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if ($bolehMulaiKonsultasi): ?>

                        <a
                            href="tambah_konsultasi.php?id=<?= (int) $data["id_konsultasi"]; ?>"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-chat-heart"></i>
                            <?= $keluhanKosong
                                ? "Mulai Konsultasi"
                                : "Tambah Keluhan"; ?>
                        </a>

                    <?php endif; ?>

                    <?php if ($bolehEdit): ?>

                        <a
                            href="edit_konsultasi.php?id=<?= (int) $data["id_konsultasi"]; ?>"
                            class="btn btn-warning btn-sm"
                        >
                            <i class="bi bi-chat-left-text"></i>

                            <?= $hasilKosong
                                ? "Tanggapi"
                                : "Edit Hasil"; ?>
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if ($kembali === "riwayat"): ?>

                    <div class="alert alert-success">
                        <i class="bi bi-lock-fill me-1"></i>
                        <strong>Mode Riwayat.</strong>
                        Konsultasi ini bersifat read-only dan tidak dapat diedit.
                    </div>

                <?php endif; ?>

                <?php if (
                    ($_GET["pesan"] ?? "") === "simpan_sementara"
                ): ?>

                    <div class="alert alert-info">
                        <i class="bi bi-save me-1"></i>
                        Hasil konsultasi berhasil disimpan sementara.
                        Konsultasi masih aktif dan masih dapat diedit oleh Ahli Gizi.
                    </div>

                <?php endif; ?>

                <?php if ($konsultasiSelesai): ?>

                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        <strong>Konsultasi selesai.</strong>
                        Data ini sudah menjadi riwayat pelayanan dan
                        tidak dapat diedit kembali.
                    </div>

                <?php endif; ?>

                <div class="alert alert-warning">
                    <i class="bi bi-bell-fill me-1"></i>
                    Konsultasi ini dibuka berdasarkan keputusan
                    <strong>Ahli Gizi</strong> setelah hasil deteksi
                    <strong><?= htmlspecialchars($data["status_stunting"] ?? "-", ENT_QUOTES, "UTF-8"); ?></strong>
                    diverifikasi.
                </div>

                <div class="row g-3 mb-3">

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Tanggal Konsultasi
                            </span>

                            <div class="detail-value">
                                <i
                                    class="bi bi-calendar3 me-1"
                                ></i>

                                <?= htmlspecialchars(
                                    $tanggalTampil,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Balita
                            </span>

                            <div class="detail-value">
                                <i
                                    class="bi bi-person-heart me-1"
                                ></i>

                                <?= htmlspecialchars(
                                    $data["nama_balita"] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                NIK Balita
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $data["nik_balita"] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-3">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Status Konsultasi
                            </span>

                            <div class="detail-value">

                                <span
                                    class="badge
                                    <?= $kelasStatusKonsultasi; ?>"
                                >
                                    <i
                                        class="bi
                                        <?= $ikonStatusKonsultasi; ?>"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $statusKonsultasi,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="row g-3 mb-3">

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Puskesmas
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-hospital me-1"></i>
                                <?= htmlspecialchars(
                                    $data["nama_puskesmas"]
                                        ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Nama Ibu
                            </span>

                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    $data["nama_ibu"] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Petugas Gizi
                            </span>

                            <div class="detail-value">

                                <?php if (
                                    !empty($data["nama_petugas"])
                                ): ?>

                                    <i
                                        class="bi
                                        bi-person-badge me-1"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $data["nama_petugas"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>

                                    <?php if (
                                        !empty(
                                            $data["username_petugas"]
                                        )
                                    ): ?>

                                        <small class="text-muted">
                                            (
                                            <?= htmlspecialchars(
                                                $data[
                                                    "username_petugas"
                                                ],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                            )
                                        </small>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="badge bg-warning text-dark">
                                        <i
                                            class="bi
                                            bi-hourglass-split"
                                        ></i>
                                        Belum Ditentukan
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="row g-3">

                    <div class="col-12">

                        <div class="detail-item">

                            <span class="detail-label">
                                Keluhan Orang Tua
                            </span>

                            <div class="detail-value">
                                <?= nl2br(
                                    htmlspecialchars(
                                        $data["keluhan"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ); ?>
                            </div>

                            <div class="form-text mt-2">
                                <?= $konsultasiSelesai
                                    ? "Konsultasi ini sudah diselesaikan Ahli Gizi, sehingga keluhan tidak dapat ditambah lagi."
                                    : "Orang Tua dapat menambahkan keluhan lain selama konsultasi ini masih aktif (belum diselesaikan Ahli Gizi)."; ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Hasil Konsultasi
                            </span>

                            <div class="detail-value">

                                <?php if (
                                    !$hasilKosong
                                ): ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $data[
                                                "hasil_konsultasi"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    ); ?>

                                <?php else: ?>

                                    <span
                                        class="badge
                                        bg-warning text-dark"
                                    >
                                        <i
                                            class="bi
                                            bi-hourglass-split"
                                        ></i>
                                        Menunggu Tanggapan
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Tindak Lanjut
                            </span>

                            <div class="detail-value">

                                <?php if (
                                    !$tindakLanjutKosong
                                ): ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $data[
                                                "tindak_lanjut"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    ); ?>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        <i
                                            class="bi
                                            bi-clock-history"
                                        ></i>
                                        Belum Ada
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>