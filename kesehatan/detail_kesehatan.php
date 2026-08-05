<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "petugas_kia",
    "petugas_gizi",
    "orang_tua",
    "kepala_puskesmas",
    "dinkes"
]);

$judulHalaman = "Detail Riwayat Kesehatan | Sistem Deteksi Stunting";

$idRiwayat = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idRiwayat) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        rk.id_riwayat,
        rk.id_balita,
        rk.riwayat_penyakit,
        rk.riwayat_imunisasi,
        rk.riwayat_perawatan,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu,
        b.id_user
     FROM riwayat_kesehatan rk
     INNER JOIN balita b
        ON rk.id_balita = b.id_balita
     WHERE rk.id_riwayat = ?
     LIMIT 1"
);

if (!$stmt) {
    die(
        "Gagal menyiapkan data detail: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $idRiwayat
);

mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasil);

mysqli_stmt_close($stmt);

if (!$data) {
    header(
        "Location: riwayat_kesehatan.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Orang tua hanya boleh melihat riwayat anak sendiri
|--------------------------------------------------------------------------
*/

if (
    $roleAktif === "orang_tua"
    && (int) $data["id_user"] !== $idUserAktif
) {
    http_response_code(403);

    echo "
        <h2>Akses Ditolak</h2>
        <p>Kamu hanya dapat melihat riwayat kesehatan anakmu sendiri.</p>
        <a href='riwayat_kesehatan.php'>Kembali</a>
    ";

    exit;
}

$bolehEdit = $roleAktif === "petugas_kia";

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
                    Detail Riwayat Kesehatan
                </h2>

                <p class="text-muted mb-0">
                    Informasi lengkap riwayat kesehatan balita.
                </p>
            </div>

            <a
                href="riwayat_kesehatan.php"
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
                            <th width="30%">
                                Nama Balita
                            </th>

                            <td>
                                <?= htmlspecialchars(
                                    $data["nama_balita"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>NIK Balita</th>

                            <td>
                                <?= htmlspecialchars(
                                    $data["nik_balita"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Nama Ibu</th>

                            <td>
                                <?= htmlspecialchars(
                                    $data["nama_ibu"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Riwayat Penyakit</th>

                            <td>
                                <?= nl2br(
                                    htmlspecialchars(
                                        $data["riwayat_penyakit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Riwayat Imunisasi</th>

                            <td>
                                <?= nl2br(
                                    htmlspecialchars(
                                        $data["riwayat_imunisasi"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Riwayat Perawatan</th>

                            <td>
                                <?= nl2br(
                                    htmlspecialchars(
                                        $data["riwayat_perawatan"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ) ?>
                            </td>
                        </tr>

                    </table>

                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">

                    <?php if ($bolehEdit): ?>

                        <a
                            href="edit_kesehatan.php?id=<?= (int) $data["id_riwayat"] ?>"
                            class="btn btn-warning"
                        >
                            Edit
                        </a>

                    <?php endif; ?>

                    <a
                        href="riwayat_kesehatan.php"
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