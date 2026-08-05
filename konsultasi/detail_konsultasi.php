<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua",
    "petugas_gizi",
    "kepala_puskesmas"
]);

$judulHalaman = "Detail Konsultasi | Sistem Deteksi Stunting";

$idKonsultasi = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idKonsultasi) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        k.id_konsultasi,
        k.id_balita,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu,
        b.id_user AS id_orang_tua,
        p.nama AS nama_petugas,
        p.username AS username_petugas
     FROM konsultasi k
     INNER JOIN balita b
        ON k.id_balita = b.id_balita
     LEFT JOIN pengguna p
        ON k.id_petugas = p.id_user
     WHERE k.id_konsultasi = ?
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
| Petugas Gizi hanya boleh mengedit konsultasi yang ditugaskan kepadanya
|--------------------------------------------------------------------------
*/

$bolehEdit = (
    $roleAktif === "petugas_gizi"
    && (int) $data["id_petugas"] === $idUserAktif
);

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

        <div
            class="d-flex flex-column flex-md-row
            justify-content-between align-items-md-center
            gap-3 mb-4"
        >
            <div>
                <h2 class="mb-1">
                    Detail Konsultasi
                </h2>

                <p class="text-muted mb-0">
                    Informasi lengkap konsultasi gizi balita.
                </p>
            </div>

            <a
                href="data_konsultasi.php"
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
                                Tanggal Konsultasi
                            </th>

                            <td>
                                <?= htmlspecialchars(
                                    $tanggalTampil,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Nama Balita</th>

                            <td>
                                <?= htmlspecialchars(
                                    $data["nama_balita"] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>NIK Balita</th>

                            <td>
                                <?= htmlspecialchars(
                                    $data["nik_balita"] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Nama Ibu</th>

                            <td>
                                <?= htmlspecialchars(
                                    $data["nama_ibu"] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Petugas Gizi</th>

                            <td>
                                <?php if (
                                    !empty($data["nama_petugas"])
                                ): ?>

                                    <?= htmlspecialchars(
                                        $data["nama_petugas"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                    <?php if (
                                        !empty(
                                            $data["username_petugas"]
                                        )
                                    ): ?>

                                        <span class="text-muted">
                                            (
                                            <?= htmlspecialchars(
                                                $data[
                                                    "username_petugas"
                                                ],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                            )
                                        </span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Belum ditentukan
                                    </span>

                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Keluhan</th>

                            <td>
                                <?= nl2br(
                                    htmlspecialchars(
                                        $data["keluhan"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Hasil Konsultasi</th>

                            <td>
                                <?php if (
                                    !empty(
                                        trim(
                                            $data[
                                                "hasil_konsultasi"
                                            ] ?? ""
                                        )
                                    )
                                ): ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $data[
                                                "hasil_konsultasi"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Belum diisi oleh Petugas Gizi.
                                    </span>

                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Tindak Lanjut</th>

                            <td>
                                <?php if (
                                    !empty(
                                        trim(
                                            $data[
                                                "tindak_lanjut"
                                            ] ?? ""
                                        )
                                    )
                                ): ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $data[
                                                "tindak_lanjut"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Belum ada tindak lanjut.
                                    </span>

                                <?php endif; ?>
                            </td>
                        </tr>

                    </table>

                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">

                    <?php if ($bolehEdit): ?>

                        <a
                            href="edit_konsultasi.php?id=<?= (int) $data["id_konsultasi"] ?>"
                            class="btn btn-warning"
                        >
                            Isi Hasil Konsultasi
                        </a>

                    <?php endif; ?>

                    <a
                        href="data_konsultasi.php"
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