<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
*/

cekRole([
    "kader",
    "petugas_kia"
]);

$judulHalaman =
    "Detail Riwayat Kelahiran | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Validasi ID
|--------------------------------------------------------------------------
*/

$idRiwayat = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idRiwayat || $idRiwayat < 1) {
    header(
        "Location: riwayat_kelahiran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mendeteksi primary key tabel riwayat_kelahiran
|--------------------------------------------------------------------------
*/

$queryPrimaryKey = mysqli_query(
    $conn,
    "SHOW KEYS
     FROM riwayat_kelahiran
     WHERE Key_name = 'PRIMARY'"
);

$dataPrimaryKey = $queryPrimaryKey
    ? mysqli_fetch_assoc($queryPrimaryKey)
    : null;

$kolomPrimaryKey =
    $dataPrimaryKey["Column_name"] ?? "";

if ($kolomPrimaryKey === "") {
    die(
        "Primary key tabel riwayat_kelahiran tidak ditemukan."
    );
}

/*
|--------------------------------------------------------------------------
| Mengambil detail riwayat kelahiran
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        rk.*,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,
        b.tanggal_lahir,
        b.nama_ibu,
        b.alamat,
        b.nama_posyandu,
        p.nama_puskesmas
    FROM riwayat_kelahiran AS rk
    INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita
    LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas
    WHERE rk.`$kolomPrimaryKey` = ?
    LIMIT 1
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {
    die(
        "Gagal menyiapkan detail riwayat kelahiran: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $idRiwayat
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$data) {
    header(
        "Location: riwayat_kelahiran.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Fungsi output aman
|--------------------------------------------------------------------------
*/

function amanDetailKelahiran($nilai): string
{
    if (
        $nilai === null
        || $nilai === ""
    ) {
        return "-";
    }

    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}

function formatTanggalKelahiran($tanggal): string
{
    if (
        $tanggal === null
        || $tanggal === ""
    ) {
        return "-";
    }

    $waktu = strtotime(
        (string) $tanggal
    );

    if ($waktu === false) {
        return amanDetailKelahiran($tanggal);
    }

    return date(
        "d-m-Y",
        $waktu
    );
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    <i class="bi bi-file-earmark-medical me-2"></i>
                    Detail Riwayat Kelahiran
                </h1>

                <p class="page-subtitle">
                    Informasi lengkap riwayat kelahiran dan
                    identitas balita.
                </p>

            </div>

            <a
                href="riwayat_kelahiran.php"
                class="btn btn-secondary btn-sm"
            >
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>

        </div>

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        <?= amanDetailKelahiran(
                            $data["nama_balita"]
                        ); ?>
                    </h4>

                    <small class="text-muted">
                        NIK:
                        <?= amanDetailKelahiran(
                            $data["nik_balita"]
                        ); ?>
                    </small>

                </div>

                <span class="badge badge-info">
                    <i class="bi bi-balloon-heart"></i>
                    Riwayat Kelahiran
                </span>

            </div>

            <div class="card-body">

                <h5 class="mb-3">
                    <i class="bi bi-person-vcard me-2"></i>
                    Identitas Balita
                </h5>

                <div class="table-responsive mb-4">

                    <table class="table table-bordered align-middle mb-0">

                        <tbody>

                            <tr>
                                <th style="width: 28%;">
                                    Nama Balita
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["nama_balita"]
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    NIK
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["nik_balita"]
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Jenis Kelamin
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["jenis_kelamin"]
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Tanggal Lahir
                                </th>
                                <td>
                                    <?= formatTanggalKelahiran(
                                        $data["tanggal_lahir"]
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Nama Ibu
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["nama_ibu"]
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Posyandu
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["nama_posyandu"]
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Puskesmas
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["nama_puskesmas"]
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Alamat
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["alamat"]
                                    ); ?>
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

                <h5 class="mb-3">
                    <i class="bi bi-heart-pulse me-2"></i>
                    Data Kelahiran
                </h5>

                <div class="table-responsive">

                    <table class="table table-bordered align-middle mb-0">

                        <tbody>

                            <tr>
                                <th style="width: 28%;">
                                    Berat Lahir
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["berat_lahir"]
                                        ?? null
                                    ); ?>
                                    <?= isset($data["berat_lahir"])
                                        && $data["berat_lahir"] !== null
                                        && $data["berat_lahir"] !== ""
                                        ? " kg"
                                        : ""; ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Panjang Lahir
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["panjang_lahir"]
                                        ?? null
                                    ); ?>
                                    <?= isset($data["panjang_lahir"])
                                        && $data["panjang_lahir"] !== null
                                        && $data["panjang_lahir"] !== ""
                                        ? " cm"
                                        : ""; ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Usia Kehamilan
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["usia_kehamilan"]
                                        ?? null
                                    ); ?>
                                    <?= isset($data["usia_kehamilan"])
                                        && $data["usia_kehamilan"] !== null
                                        && $data["usia_kehamilan"] !== ""
                                        ? " minggu"
                                        : ""; ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Jenis Persalinan
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["jenis_persalinan"]
                                        ?? null
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Usia Ibu Saat Melahirkan
                                </th>
                                <td>
                                    <?= amanDetailKelahiran(
                                        $data["usia_ibu_melahirkan"]
                                        ?? null
                                    ); ?>
                                    <?= isset($data["usia_ibu_melahirkan"])
                                        && $data["usia_ibu_melahirkan"] !== null
                                        && $data["usia_ibu_melahirkan"] !== ""
                                        ? " tahun"
                                        : ""; ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Riwayat Kehamilan
                                </th>
                                <td>
                                    <?= nl2br(
                                        amanDetailKelahiran(
                                            $data["riwayat_kehamilan"]
                                            ?? null
                                        )
                                    ); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Komplikasi Kehamilan
                                </th>
                                <td>
                                    <?= nl2br(
                                        amanDetailKelahiran(
                                            $data["komplikasi_kehamilan"]
                                            ?? null
                                        )
                                    ); ?>
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

                <div class="form-actions mt-4">

                    <a
                        href="edit_kelahiran.php?id=<?= $idRiwayat; ?>"
                        class="btn btn-warning"
                    >
                        <i class="bi bi-pencil-square"></i>
                        Edit Riwayat
                    </a>

                    <a
                        href="riwayat_kelahiran.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali ke Daftar
                    </a>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>
