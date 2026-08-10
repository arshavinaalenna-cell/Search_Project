<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua"
]);

$judulHalaman =
    "Profil Ibu | Sistem Deteksi Stunting";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

/*
|--------------------------------------------------------------------------
| Fungsi bantuan
|--------------------------------------------------------------------------
*/

function amanProfilIbu($nilai): string
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
| Mengambil profil ibu
|--------------------------------------------------------------------------
*/

$stmtProfil = mysqli_prepare(
    $conn,
    "SELECT
        ot.id_orang_tua,
        ot.id_user,
        ot.nik_ibu,
        ot.nama_ibu,
        ot.no_hp,
        ot.alamat,
        ot.pendidikan_ibu,
        ot.pekerjaan_ibu,
        p.username
     FROM orang_tua AS ot
     INNER JOIN pengguna AS p
        ON ot.id_user = p.id_user
     WHERE ot.id_user = ?
     LIMIT 1"
);

if (!$stmtProfil) {
    die(
        "Gagal menyiapkan profil ibu: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtProfil,
    "i",
    $idUserAktif
);

if (!mysqli_stmt_execute($stmtProfil)) {
    die(
        "Gagal mengambil profil ibu: "
        . mysqli_stmt_error($stmtProfil)
    );
}

$hasilProfil =
    mysqli_stmt_get_result(
        $stmtProfil
    );

$profil =
    mysqli_fetch_assoc(
        $hasilProfil
    );

mysqli_stmt_close(
    $stmtProfil
);

/*
|--------------------------------------------------------------------------
| Pesan halaman
|--------------------------------------------------------------------------
*/

$pesan =
    $_GET["pesan"] ?? "";

$jenisAlert = "";
$isiPesan = "";

switch ($pesan) {

    case "simpan_berhasil":
        $jenisAlert = "success";
        $isiPesan =
            "Profil ibu berhasil disimpan.";
        break;

    case "edit_berhasil":
        $jenisAlert = "success";
        $isiPesan =
            "Profil ibu berhasil diperbarui.";
        break;

}

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <?php if ($isiPesan !== ""): ?>

            <div
                class="alert alert-<?= amanProfilIbu(
                    $jenisAlert
                ); ?> alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-check-circle me-1"></i>

                <?= amanProfilIbu($isiPesan); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>

            </div>

        <?php endif; ?>

        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Profil Ibu
                    </h4>

                    <small class="text-muted">
                        Data identitas ibu yang digunakan sebagai
                        referensi data balita dan skrining awal.
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

                    <?php if ($profil): ?>

                        <a
                            href="edit_profil.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-pencil-square"></i>
                            Edit Profil
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if ($profil): ?>

                    <div class="row g-4">

                        <div class="col-12 col-lg-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Nama Ibu
                                </span>

                                <div class="detail-value">
                                    <i
                                        class="bi
                                        bi-person-heart me-1"
                                    ></i>

                                    <?= amanProfilIbu(
                                        $profil[
                                            "nama_ibu"
                                        ]
                                    ); ?>
                                </div>

                                <small class="text-muted">
                                    Username:
                                    <?= amanProfilIbu(
                                        $profil[
                                            "username"
                                        ]
                                    ); ?>
                                </small>

                            </div>

                        </div>

                        <div class="col-12 col-lg-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    NIK Ibu
                                </span>

                                <div class="detail-value">
                                    <?= amanProfilIbu(
                                        $profil[
                                            "nik_ibu"
                                        ]
                                    ); ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-lg-4">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Nomor HP
                                </span>

                                <div class="detail-value">
                                    <?= amanProfilIbu(
                                        $profil[
                                            "no_hp"
                                        ]
                                    ); ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Pendidikan Ibu
                                </span>

                                <div class="detail-value">
                                    <?= amanProfilIbu(
                                        $profil[
                                            "pendidikan_ibu"
                                        ]
                                    ); ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <div class="detail-item h-100">

                                <span class="detail-label">
                                    Pekerjaan Ibu
                                </span>

                                <div class="detail-value">
                                    <?= amanProfilIbu(
                                        $profil[
                                            "pekerjaan_ibu"
                                        ]
                                    ); ?>
                                </div>

                            </div>

                        </div>

                        <div class="col-12">

                            <div class="detail-item">

                                <span class="detail-label">
                                    Alamat
                                </span>

                                <div class="detail-value">
                                    <?= nl2br(
                                        amanProfilIbu(
                                            $profil[
                                                "alamat"
                                            ]
                                        )
                                    ); ?>
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="alert alert-info mt-4 mb-0">

                        <i class="bi bi-info-circle me-1"></i>

                        Data pendidikan dan pekerjaan ibu
                        nantinya dapat digunakan otomatis pada
                        proses skrining sehingga tidak perlu
                        diinput ulang oleh Kader.

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            <i
                                class="bi
                                bi-person-vcard"
                            ></i>
                        </div>

                        <h3>
                            Profil ibu belum lengkap
                        </h3>

                        <p>
                            Lengkapi identitas ibu terlebih dahulu
                            agar data dapat digunakan saat
                            pendaftaran balita dan skrining awal.
                        </p>

                        <a
                            href="edit_profil.php"
                            class="btn btn-primary mt-3"
                        >
                            <i class="bi bi-person-plus"></i>
                            Lengkapi Profil Ibu
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>