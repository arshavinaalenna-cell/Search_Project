<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_gizi",
    "petugas_kia",
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

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div
            class="d-flex flex-column flex-md-row
            justify-content-between align-items-md-center
            gap-3 mb-4"
        >

            <div>
                <h2 class="mb-1">
                    Detail Hasil Deteksi
                </h2>

                <p class="text-muted mb-0">
                    Informasi lengkap hasil deteksi stunting balita.
                </p>
            </div>

            <a
                href="hasil_deteksi.php"
                class="btn btn-secondary"
            >
                ← Kembali ke Hasil Deteksi
            </a>

        </div>

        <div class="row g-4">

            <!-- Data Balita -->
            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            Data Balita
                        </h5>
                    </div>

                    <div class="card-body p-4">

                        <table class="table table-bordered mb-0">

                            <tr>
                                <th width="40%">
                                    ID Balita
                                </th>

                                <td>
                                    <?= (int) $data["id_balita"] ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Nama Balita
                                </th>

                                <td>
                                    <?= htmlspecialchars(
                                        $data["nama_balita"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    NIK Balita
                                </th>

                                <td>
                                    <?= htmlspecialchars(
                                        $data["nik_balita"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Jenis Kelamin
                                </th>

                                <td>
                                    <?= htmlspecialchars(
                                        $data["jenis_kelamin"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Umur Saat Pengukuran
                                </th>

                                <td>
                                    <?= (int) (
                                        $data["umur_bulan"] ?? 0
                                    ) ?>
                                    bulan
                                </td>
                            </tr>

                        </table>

                    </div>

                </div>

            </div>

            <!-- Data Pengukuran -->
            <div class="col-12 col-lg-6">

                <div class="card content-card h-100">

                    <div class="card-header bg-info text-dark">
                        <h5 class="mb-0">
                            Data Pengukuran Antropometri
                        </h5>
                    </div>

                    <div class="card-body p-4">

                        <table class="table table-bordered mb-0">

                            <tr>
                                <th width="45%">
                                    Tanggal Pengukuran
                                </th>

                                <td>
                                    <?= !empty(
                                        $data["tanggal_pengukuran"]
                                    )
                                        ? date(
                                            "d-m-Y",
                                            strtotime(
                                                $data["tanggal_pengukuran"]
                                            )
                                        )
                                        : "-" ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Berat Badan
                                </th>

                                <td>
                                    <?= htmlspecialchars(
                                        $data["berat_badan"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                    kg
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Tinggi/Panjang Badan
                                </th>

                                <td>
                                    <?= htmlspecialchars(
                                        $data[
                                            "tinggi_panjang_badan"
                                        ] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                    cm
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Lingkar Kepala
                                </th>

                                <td>
                                    <?= htmlspecialchars(
                                        $data["lingkar_kepala"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                    cm
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    LILA
                                </th>

                                <td>
                                    <?= htmlspecialchars(
                                        $data["lila"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                    cm
                                </td>
                            </tr>

                        </table>

                    </div>

                </div>

            </div>

            <!-- Hasil Deteksi -->
            <div class="col-12">

                <div class="card content-card">

                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            Hasil Deteksi Stunting
                        </h5>
                    </div>

                    <div class="card-body p-4">

                        <div
                            class="d-flex flex-wrap
                            align-items-center gap-2 mb-4"
                        >
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

                        <div class="row g-4">

                            <div class="col-12 col-md-4">

                                <div
                                    class="border rounded p-3
                                    text-center h-100"
                                >

                                    <p class="text-muted mb-2">
                                        Tanggal Deteksi
                                    </p>

                                    <h5 class="mb-0">
                                        <?= !empty(
                                            $data["tanggal_deteksi"]
                                        )
                                            ? date(
                                                "d-m-Y",
                                                strtotime(
                                                    $data["tanggal_deteksi"]
                                                )
                                            )
                                            : "-" ?>
                                    </h5>

                                </div>

                            </div>

                            <div class="col-12 col-md-4">

                                <div
                                    class="border rounded p-3
                                    text-center h-100"
                                >

                                    <p class="text-muted mb-2">
                                        Status Gizi
                                    </p>

                                    <h5 class="mb-0">
                                        <?= htmlspecialchars(
                                            $data["status_gizi"]
                                                ?? "Belum tersedia",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </h5>

                                </div>

                            </div>

                            <div class="col-12 col-md-4">

                                <div
                                    class="border rounded p-3
                                    text-center h-100"
                                >

                                    <p class="text-muted mb-2">
                                        Status Stunting
                                    </p>

                                    <span
                                        class="badge <?= $kelasStunting ?>
                                        fs-6 px-3 py-2"
                                    >
                                        <?= htmlspecialchars(
                                            $data["status_stunting"]
                                                ?? "Belum tersedia",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>

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