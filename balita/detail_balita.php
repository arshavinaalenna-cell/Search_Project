<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "kepala_puskesmas",
    "dinkes",
    "orang_tua"
]);

$judulHalaman = "Detail Data Balita | Sistem Deteksi Stunting";

$idBalita = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idBalita) {
    header("Location: data_balita.php");
    exit;
}

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Mengambil detail balita dan data orang tua
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        b.id_balita,
        b.id_user,
        b.id_puskesmas,
        b.nik_balita,
        b.nama_balita,
        b.jenis_kelamin,
        b.tanggal_lahir,
        b.umur,
        b.nama_ibu,
        b.alamat,
        b.nama_posyandu,
        p.nama AS nama_orang_tua,
        p.username AS username_orang_tua,
        ps.nama_puskesmas
    FROM balita b
    LEFT JOIN pengguna p
        ON b.id_user = p.id_user
    LEFT JOIN puskesmas ps
        ON b.id_puskesmas = ps.id_puskesmas
    WHERE b.id_balita = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Terjadi kesalahan saat mengambil data balita.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $idBalita
);

mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasil);

mysqli_stmt_close($stmt);

if (!$data) {
    header("Location: data_balita.php?pesan=tidak_ditemukan");
    exit;
}

/*
|--------------------------------------------------------------------------
| Orang tua hanya boleh melihat anak miliknya
|--------------------------------------------------------------------------
*/

if (
    $roleAktif === "orang_tua"
    && (int) $data["id_user"] !== $idUserAktif
) {
    http_response_code(403);

    echo "
        <h2>Akses Ditolak</h2>
        <p>Kamu hanya dapat melihat data anak yang terhubung dengan akunmu.</p>
        <a href='data_balita.php'>Kembali</a>
    ";

    exit;
}

$namaBalita = htmlspecialchars(
    $data["nama_balita"] ?? "-",
    ENT_QUOTES,
    "UTF-8"
);

$nikBalita = htmlspecialchars(
    $data["nik_balita"] ?? "-",
    ENT_QUOTES,
    "UTF-8"
);

$jenisKelamin = htmlspecialchars(
    $data["jenis_kelamin"] ?? "-",
    ENT_QUOTES,
    "UTF-8"
);

$namaIbu = htmlspecialchars(
    $data["nama_ibu"] ?? "-",
    ENT_QUOTES,
    "UTF-8"
);

$alamat = nl2br(
    htmlspecialchars(
        $data["alamat"] ?? "-",
        ENT_QUOTES,
        "UTF-8"
    )
);

$namaPosyandu = htmlspecialchars(
    $data["nama_posyandu"] ?? "-",
    ENT_QUOTES,
    "UTF-8"
);

$namaPuskesmas = htmlspecialchars(
    $data["nama_puskesmas"] ?? "-",
    ENT_QUOTES,
    "UTF-8"
);

$namaOrangTua = htmlspecialchars(
    $data["nama_orang_tua"] ?? "Belum terhubung",
    ENT_QUOTES,
    "UTF-8"
);

$usernameOrangTua = htmlspecialchars(
    $data["username_orang_tua"] ?? "-",
    ENT_QUOTES,
    "UTF-8"
);

$tanggalLahirTampil = "-";

if (!empty($data["tanggal_lahir"])) {
    $tanggalLahirTampil = date(
        "d-m-Y",
        strtotime($data["tanggal_lahir"])
    );
}

$bolehEdit = ($roleAktif === "kader");

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
                    Detail Data Balita
                </h2>

                <p class="text-muted mb-0">
                    Informasi lengkap profil balita.
                </p>
            </div>

            <a
                href="data_balita.php"
                class="btn btn-outline-secondary"
            >
                Kembali
            </a>
        </div>

        <div class="card content-card">

            <div class="card-body p-4">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <tr>
                            <th width="30%">ID Balita</th>
                            <td><?= (int) $data["id_balita"] ?></td>
                        </tr>

                        <tr>
                            <th>Orang Tua</th>
                            <td>
                                <?= $namaOrangTua ?>

                                <?php if ($usernameOrangTua !== "-"): ?>
                                    <span class="text-muted">
                                        (<?= $usernameOrangTua ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>NIK Balita</th>
                            <td><?= $nikBalita ?></td>
                        </tr>

                        <tr>
                            <th>Nama Balita</th>
                            <td><?= $namaBalita ?></td>
                        </tr>

                        <tr>
                            <th>Jenis Kelamin</th>
                            <td><?= $jenisKelamin ?></td>
                        </tr>

                        <tr>
                            <th>Tanggal Lahir</th>
                            <td><?= $tanggalLahirTampil ?></td>
                        </tr>

                        <tr>
                            <th>Umur</th>
                            <td>
                                <?= (int) ($data["umur"] ?? 0) ?>
                                bulan
                            </td>
                        </tr>

                        <tr>
                            <th>Nama Ibu</th>
                            <td><?= $namaIbu ?></td>
                        </tr>

                        <tr>
                            <th>Alamat</th>
                            <td><?= $alamat ?></td>
                        </tr>

                        <tr>
                            <th>Nama Posyandu</th>
                            <td><?= $namaPosyandu ?></td>
                        </tr>

                        <tr>
                            <th>Puskesmas Pembina</th>
                            <td><?= $namaPuskesmas ?></td>
                        </tr>

                    </table>

                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">

                    <?php if ($bolehEdit): ?>

                        <a
                            href="edit_balita.php?id=<?= (int) $data["id_balita"] ?>"
                            class="btn btn-warning"
                        >
                            Edit
                        </a>

                    <?php endif; ?>

                    <a
                        href="data_balita.php"
                        class="btn btn-secondary"
                    >
                        Kembali
                    </a>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>