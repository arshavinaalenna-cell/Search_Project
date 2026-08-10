<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_gizi",
    "petugas_kia",
    "kader",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Detail Hasil Deteksi | Sistem Deteksi Stunting";

$roleAktif   = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$idDeteksi = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idDeteksi || $idDeteksi < 1) {
    header("Location: hasil_deteksi.php?pesan=tidak_ditemukan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil detail hasil deteksi
|--------------------------------------------------------------------------
|
| Orang tua hanya boleh melihat hasil deteksi anak miliknya.
|
*/

if ($roleAktif === "orang_tua") {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            hd.id_deteksi,
            hd.id_pengukuran,
            hd.status_gizi,
            hd.status_stunting,
            hd.tanggal_deteksi,
            hd.status_verifikasi,
            hd.catatan_verifikasi,
            hd.tanggal_verifikasi,

            pa.tanggal_pengukuran,
            pa.umur_bulan,
            pa.berat_badan,
            pa.tinggi_panjang_badan,
            pa.lingkar_kepala,
            pa.lila,

            b.id_balita,
            b.nama_balita,
            b.nik_balita,
            b.jenis_kelamin

         FROM hasil_deteksi hd

         INNER JOIN pengukuran_antropometri pa
            ON hd.id_pengukuran = pa.id_pengukuran

         INNER JOIN balita b
            ON pa.id_balita = b.id_balita

         WHERE hd.id_deteksi = ?
         AND b.id_user = ?

         LIMIT 1"
    );

    if (!$stmt) {
        die(
            "Gagal menyiapkan detail hasil deteksi: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $idDeteksi,
        $idUserAktif
    );

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            hd.id_deteksi,
            hd.id_pengukuran,
            hd.status_gizi,
            hd.status_stunting,
            hd.tanggal_deteksi,
            hd.status_verifikasi,
            hd.catatan_verifikasi,
            hd.tanggal_verifikasi,

            pa.tanggal_pengukuran,
            pa.umur_bulan,
            pa.berat_badan,
            pa.tinggi_panjang_badan,
            pa.lingkar_kepala,
            pa.lila,

            b.id_balita,
            b.nama_balita,
            b.nik_balita,
            b.jenis_kelamin

         FROM hasil_deteksi hd

         INNER JOIN pengukuran_antropometri pa
            ON hd.id_pengukuran = pa.id_pengukuran

         INNER JOIN balita b
            ON pa.id_balita = b.id_balita

         WHERE hd.id_deteksi = ?

         LIMIT 1"
    );

    if (!$stmt) {
        die(
            "Gagal menyiapkan detail hasil deteksi: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $idDeteksi
    );
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data = mysqli_fetch_assoc($result);

if (!$data) {
    mysqli_stmt_close($stmt);

    header("Location: hasil_deteksi.php?pesan=tidak_ditemukan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Menentukan warna status stunting
|--------------------------------------------------------------------------
*/

$statusStunting = strtolower(
    trim($data["status_stunting"] ?? "")
);

$kelasStunting = "bg-secondary";

if (
    in_array(
        $statusStunting,
        [
            "normal",
            "normal/sehat",
            "tidak stunting"
        ],
        true
    )
) {
    $kelasStunting = "bg-success";

} elseif (
    $statusStunting === "risiko stunting"
) {
    $kelasStunting = "bg-warning text-dark";

} elseif (
    in_array(
        $statusStunting,
        [
            "stunting",
            "pendek"
        ],
        true
    )
) {
    $kelasStunting = "bg-danger";

} elseif (
    in_array(
        $statusStunting,
        [
            "stunting berat",
            "severely stunted",
            "sangat pendek"
        ],
        true
    )
) {
    $kelasStunting = "bg-dark text-white";
}

/*
|--------------------------------------------------------------------------
| Menentukan warna status verifikasi
|--------------------------------------------------------------------------
*/

$statusVerifikasi = trim(
    (string) (
        $data["status_verifikasi"]
        ?? ""
    )
);

if ($statusVerifikasi === "") {
    $statusVerifikasi = "Belum diverifikasi";
}

$statusVerifikasiNormal =
    strtolower($statusVerifikasi);

$kelasVerifikasi = "bg-secondary";

if (
    $statusVerifikasiNormal
    === "sudah diverifikasi"
) {
    $kelasVerifikasi = "bg-success";

} elseif (
    $statusVerifikasiNormal
    === "perlu pemeriksaan ulang"
) {
    $kelasVerifikasi = "bg-warning text-dark";
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
                        <i class="bi bi-clipboard2-pulse me-2"></i>
                        Detail Hasil Deteksi
                    </h4>

                    <small class="text-muted">
                        Tinjau identitas balita, pengukuran antropometri,
                        hasil deteksi, dan status verifikasi.
                    </small>

                </div>

                <a
                    href="hasil_deteksi.php"
                    class="btn btn-secondary btn-sm"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

            </div>

            <div class="card-body">

                <!-- =================================================
                     IDENTITAS BALITA
                ================================================== -->

                <div class="mb-4">

                    <div class="d-flex align-items-center gap-2 mb-3">

                        <span class="badge badge-primary">
                            <i class="bi bi-person-heart"></i>
                        </span>

                        <div>

                            <h5 class="mb-0">
                                Identitas Balita
                            </h5>

                            <small class="text-muted">
                                Informasi balita yang dianalisis.
                            </small>

                        </div>

                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Nama Balita
                                </span>

                                <div class="detail-value">
                                    <strong>
                                        <?= htmlspecialchars(
                                            $data["nama_balita"] ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </strong>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-6">

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

                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Jenis Kelamin
                                </span>

                                <div class="detail-value">
                                    <?= htmlspecialchars(
                                        $data["jenis_kelamin"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Umur Saat Pengukuran
                                </span>

                                <div class="detail-value">
                                    <?= (int) (
                                        $data["umur_bulan"] ?? 0
                                    ); ?>
                                    bulan
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <hr class="my-4">

                <!-- =================================================
                     PENGUKURAN ANTROPOMETRI
                ================================================== -->

                <div class="mb-4">

                    <div class="d-flex align-items-center gap-2 mb-3">

                        <span class="badge badge-info">
                            <i class="bi bi-rulers"></i>
                        </span>

                        <div>

                            <h5 class="mb-0">
                                Pengukuran Antropometri
                            </h5>

                            <small class="text-muted">
                                Data pengukuran yang digunakan dalam deteksi.
                            </small>

                        </div>

                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Tanggal Pengukuran
                                </span>

                                <div class="detail-value">
                                    <?= !empty(
                                        $data["tanggal_pengukuran"]
                                    )
                                        ? date(
                                            "d-m-Y",
                                            strtotime(
                                                $data["tanggal_pengukuran"]
                                            )
                                        )
                                        : "-"; ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Berat Badan
                                </span>

                                <div class="detail-value">
                                    <?= htmlspecialchars(
                                        $data["berat_badan"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                    kg
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Tinggi/Panjang Badan
                                </span>

                                <div class="detail-value">
                                    <?= htmlspecialchars(
                                        $data[
                                            "tinggi_panjang_badan"
                                        ] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                    cm
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Lingkar Kepala
                                </span>

                                <div class="detail-value">
                                    <?= htmlspecialchars(
                                        $data["lingkar_kepala"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                    cm
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    LILA
                                </span>

                                <div class="detail-value">
                                    <?= htmlspecialchars(
                                        $data["lila"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                    cm
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <hr class="my-4">

                <!-- =================================================
                     HASIL DETEKSI
                ================================================== -->

                <div class="mb-4">

                    <div class="d-flex align-items-center gap-2 mb-3">

                        <span class="badge badge-primary">
                            <i class="bi bi-activity"></i>
                        </span>

                        <div>

                            <h5 class="mb-0">
                                Hasil Deteksi
                            </h5>

                            <small class="text-muted">
                                Status gizi dan status stunting hasil analisis.
                            </small>

                        </div>

                    </div>

                    <div class="status-legend d-flex flex-wrap align-items-center gap-2 mb-3">

                        <span class="text-muted small me-1">
                            Keterangan status:
                        </span>

                        <span class="badge bg-success">
                            Normal
                        </span>

                        <span class="badge bg-warning text-dark">
                            Risiko Stunting
                        </span>

                        <span class="badge bg-danger">
                            Stunting
                        </span>

                        <span class="badge bg-dark text-white">
                            Stunting Berat
                        </span>

                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Tanggal Deteksi
                                </span>

                                <div class="detail-value">
                                    <?= !empty(
                                        $data["tanggal_deteksi"]
                                    )
                                        ? date(
                                            "d-m-Y",
                                            strtotime(
                                                $data["tanggal_deteksi"]
                                            )
                                        )
                                        : "-"; ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Status Gizi
                                </span>

                                <div class="detail-value">
                                    <?= htmlspecialchars(
                                        $data["status_gizi"]
                                            ?? "Belum tersedia",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Status Stunting
                                </span>

                                <div class="detail-value">

                                    <span
                                        class="badge <?= $kelasStunting; ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $data["status_stunting"]
                                                ?? "Belum tersedia",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <hr class="my-4">

                <!-- =================================================
                     STATUS VERIFIKASI
                ================================================== -->

                <div>

                    <div class="d-flex align-items-center gap-2 mb-3">

                        <span class="badge badge-info">
                            <i class="bi bi-check2-circle"></i>
                        </span>

                        <div>

                            <h5 class="mb-0">
                                Verifikasi
                            </h5>

                            <small class="text-muted">
                                Status dan catatan verifikasi hasil deteksi.
                            </small>

                        </div>

                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Status Verifikasi
                                </span>

                                <div class="detail-value">

                                    <span
                                        class="badge <?= $kelasVerifikasi; ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $statusVerifikasi,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Tanggal Verifikasi
                                </span>

                                <div class="detail-value">
                                    <?= !empty(
                                        $data["tanggal_verifikasi"]
                                    )
                                        ? date(
                                            "d-m-Y",
                                            strtotime(
                                                $data["tanggal_verifikasi"]
                                            )
                                        )
                                        : "-"; ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-md-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Catatan Verifikasi
                                </span>

                                <div class="detail-value">
                                    <?= htmlspecialchars(
                                        $data["catatan_verifikasi"]
                                            ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php

mysqli_stmt_close($stmt);

require_once "../includes/footer.php";

?>
