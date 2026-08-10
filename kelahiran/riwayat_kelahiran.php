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
    "petugas_kia",
    "petugas_gizi"
]);

$roleAktif =
    $_SESSION["role"] ?? "";

$bolehKelolaKelahiran =
    $roleAktif === "petugas_kia";

$judulHalaman =
    "Riwayat Kelahiran | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Mendeteksi primary key tabel riwayat_kelahiran
|--------------------------------------------------------------------------
|
| Kode ini tetap bekerja jika primary key bernama:
| id_kelahiran, id_riwayat, atau id_riwayat_kelahiran.
|
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
| Mengambil data riwayat kelahiran dan data balita
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        rk.*,
        b.nama_balita,
        b.nik_balita
    FROM riwayat_kelahiran AS rk
    INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita
    ORDER BY rk.$kolomPrimaryKey DESC
";

$query = mysqli_query($conn, $sql);

if (!$query) {
    die(
        "Gagal mengambil data riwayat kelahiran: "
        . mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function amanKelahiran($nilai): string
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

/*
|--------------------------------------------------------------------------
| Pesan halaman
|--------------------------------------------------------------------------
*/

$pesan = $_GET["pesan"] ?? "";

$jenisAlert = "";
$isiPesan = "";

switch ($pesan) {

    case "tambah_berhasil":
        $jenisAlert = "success";
        $isiPesan =
            "Data riwayat kelahiran berhasil ditambahkan.";
        break;

    case "edit_berhasil":
        $jenisAlert = "success";
        $isiPesan =
            "Data riwayat kelahiran berhasil diperbarui.";
        break;

    case "hapus_berhasil":
        $jenisAlert = "success";
        $isiPesan =
            "Data riwayat kelahiran berhasil dihapus.";
        break;

    case "tidak_ditemukan":
        $jenisAlert = "warning";
        $isiPesan =
            "Data riwayat kelahiran tidak ditemukan.";
        break;

    case "gagal_hapus":
        $jenisAlert = "danger";
        $isiPesan =
            "Data riwayat kelahiran gagal dihapus.";
        break;
}

/*
|--------------------------------------------------------------------------
| Template aplikasi
|--------------------------------------------------------------------------
*/

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
                        Riwayat Kelahiran
                    </h4>

                    <small class="text-muted">
                        <?php if ($bolehKelolaKelahiran): ?>
                            Kelola data kelahiran, status BBLR,
                            prematur, dan riwayat maternal balita.
                        <?php else: ?>
                            Lihat data kelahiran, status BBLR,
                            prematur, dan riwayat maternal sebagai
                            pendukung asesmen gizi balita.
                        <?php endif; ?>
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if ($bolehKelolaKelahiran): ?>

                        <a
                            href="tambah_kelahiran.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Tambah Riwayat
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if ($isiPesan !== ""): ?>

                    <div
                        class="alert alert-<?= amanKelahiran(
                            $jenisAlert
                        ); ?> alert-dismissible fade show"
                        role="alert"
                    >
                        <i
                            class="bi <?= $jenisAlert === "success"
                                ? "bi-check-circle"
                                : ($jenisAlert === "danger"
                                    ? "bi-x-circle"
                                    : "bi-exclamation-triangle"); ?> me-1"
                        ></i>

                        <?= amanKelahiran($isiPesan); ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>
                    </div>

                <?php endif; ?>

                <div
                    class="d-flex flex-wrap
                    justify-content-between align-items-center
                    gap-2 mb-3"
                >

                    <span class="text-muted small">
                        Total data:
                        <strong>
                            <?= mysqli_num_rows($query); ?>
                        </strong>
                        riwayat kelahiran
                    </span>

                    <?php if ($bolehKelolaKelahiran): ?>

                        <span class="badge badge-info">
                            <i class="bi bi-heart-pulse"></i>
                            Data KIA
                        </span>

                    <?php else: ?>

                        <span class="badge badge-info">
                            <i class="bi bi-eye"></i>
                            Mode lihat
                        </span>

                    <?php endif; ?>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>
                                <th class="text-center">
                                    No
                                </th>

                                <th>
                                    Balita
                                </th>

                                <th class="text-center">
                                    Data Lahir
                                </th>

                                <th class="text-center">
                                    Status BBLR
                                </th>

                                <th class="text-center">
                                    Status Prematur
                                </th>

                                <th>
                                    Data Maternal
                                </th>

                                <th class="text-center">
                                    Persalinan
                                </th>

                                <?php if ($bolehKelolaKelahiran): ?>

                                    <th
                                        class="text-center"
                                        style="min-width: 170px;"
                                    >
                                        Aksi
                                    </th>

                                <?php endif; ?>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if (mysqli_num_rows($query) > 0): ?>

                            <?php
                            $nomor = 1;

                            while (
                                $data = mysqli_fetch_assoc($query)
                            ):

                                $idRiwayat =
                                    (int) $data[$kolomPrimaryKey];

                                $beratLahir =
                                    is_numeric(
                                        $data["berat_lahir"] ?? null
                                    )
                                        ? (float) $data["berat_lahir"]
                                        : null;

                                $usiaKehamilan =
                                    is_numeric(
                                        $data["usia_kehamilan"] ?? null
                                    )
                                        ? (int) $data["usia_kehamilan"]
                                        : null;

                                if ($beratLahir === null) {
                                    $statusBBLR = "Belum Dinilai";
                                    $kelasBBLR = "bg-secondary";
                                } elseif ($beratLahir < 2.5) {
                                    $statusBBLR = "BBLR";
                                    $kelasBBLR = "bg-danger";
                                } else {
                                    $statusBBLR = "Tidak BBLR";
                                    $kelasBBLR = "bg-success";
                                }

                                if ($usiaKehamilan === null) {
                                    $statusPrematur = "Belum Dinilai";
                                    $kelasPrematur = "bg-secondary";
                                } elseif ($usiaKehamilan < 37) {
                                    $statusPrematur = "Prematur";
                                    $kelasPrematur =
                                        "bg-warning text-dark";
                                } else {
                                    $statusPrematur =
                                        "Tidak Prematur";
                                    $kelasPrematur =
                                        "bg-success";
                                }

                                $usiaIbu =
                                    $data[
                                        "usia_ibu_melahirkan"
                                    ] ?? null;

                                $komplikasi =
                                    trim(
                                        (string) (
                                            $data[
                                                "komplikasi_kehamilan"
                                            ] ?? ""
                                        )
                                    );
                            ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $nomor++; ?>
                                    </td>

                                    <td>

                                        <div
                                            class="d-flex
                                            align-items-center gap-2"
                                        >

                                            <span
                                                class="badge
                                                badge-primary"
                                            >
                                                <i
                                                    class="bi
                                                    bi-person-heart"
                                                ></i>
                                            </span>

                                            <div>

                                                <strong class="d-block">
                                                    <?= amanKelahiran(
                                                        $data[
                                                            "nama_balita"
                                                        ]
                                                    ); ?>
                                                </strong>

                                                <small
                                                    class="text-muted"
                                                >
                                                    NIK:
                                                    <?= amanKelahiran(
                                                        $data[
                                                            "nik_balita"
                                                        ]
                                                    ); ?>
                                                </small>

                                            </div>

                                        </div>

                                    </td>

                                    <td class="text-center">

                                        <div class="small">
                                            <strong>
                                                <?= amanKelahiran(
                                                    $data["berat_lahir"]
                                                ); ?>
                                            </strong>
                                            kg
                                        </div>

                                        <div class="small text-muted">
                                            <?= amanKelahiran(
                                                $data["panjang_lahir"]
                                            ); ?>
                                            cm
                                            &nbsp;•&nbsp;
                                            <?= amanKelahiran(
                                                $data["usia_kehamilan"]
                                            ); ?>
                                            minggu
                                        </div>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge
                                            <?= $kelasBBLR; ?>"
                                        >
                                            <?= amanKelahiran(
                                                $statusBBLR
                                            ); ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge
                                            <?= $kelasPrematur; ?>"
                                        >
                                            <?= amanKelahiran(
                                                $statusPrematur
                                            ); ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div>
                                            <strong>
                                                <?= amanKelahiran(
                                                    $usiaIbu
                                                ); ?>
                                            </strong>
                                            <span class="text-muted">
                                                tahun
                                            </span>
                                        </div>

                                        <small class="text-muted">

                                            <?php if (
                                                $komplikasi === ""
                                            ): ?>

                                                Komplikasi: -

                                            <?php else: ?>

                                                Komplikasi:
                                                <?= amanKelahiran(
                                                    mb_strlen(
                                                        $komplikasi
                                                    ) > 45
                                                        ? mb_substr(
                                                            $komplikasi,
                                                            0,
                                                            45
                                                        ) . "..."
                                                        : $komplikasi
                                                ); ?>

                                            <?php endif; ?>

                                        </small>

                                    </td>

                                    <td class="text-center">

                                        <span class="badge badge-info">
                                            <i
                                                class="bi
                                                bi-hospital me-1"
                                            ></i>

                                            <?= amanKelahiran(
                                                $data[
                                                    "jenis_persalinan"
                                                ]
                                            ); ?>
                                        </span>

                                    </td>

                                    <?php if ($bolehKelolaKelahiran): ?>

                                        <td>

                                            <div
                                                class="table-actions
                                                justify-content-center"
                                            >

                                                <a
                                                    href="edit_kelahiran.php?id=<?= $idRiwayat; ?>"
                                                    class="btn btn-warning btn-sm"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-pencil-square"
                                                    ></i>
                                                    Edit
                                                </a>

                                                <form
                                                    action="hapus_kelahiran.php"
                                                    method="POST"
                                                    class="d-inline"
                                                    onsubmit="return confirm(
                                                        'Yakin ingin menghapus riwayat kelahiran ini?'
                                                    );"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= $idRiwayat; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                    >
                                                        <i
                                                            class="bi
                                                            bi-trash3"
                                                        ></i>
                                                        Hapus
                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    <?php endif; ?>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="<?= $bolehKelolaKelahiran
                                        ? "8"
                                        : "7"; ?>"
                                >

                                    <div class="empty-state">

                                        <div class="empty-state-icon">
                                            <i
                                                class="bi
                                                bi-balloon-heart"
                                            ></i>
                                        </div>

                                        <h3>
                                            Belum ada riwayat kelahiran
                                        </h3>

                                        <p>
                                            <?php if ($bolehKelolaKelahiran): ?>
                                                Tambahkan data kelahiran
                                                balita untuk melengkapi
                                                informasi awal pertumbuhan.
                                            <?php else: ?>
                                                Data riwayat kelahiran balita
                                                belum tersedia untuk ditinjau.
                                            <?php endif; ?>
                                        </p>

                                        <?php if ($bolehKelolaKelahiran): ?>

                                            <a
                                                href="tambah_kelahiran.php"
                                                class="btn btn-primary mt-3"
                                            >
                                                <i
                                                    class="bi
                                                    bi-plus-circle"
                                                ></i>
                                                Tambah Riwayat
                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>