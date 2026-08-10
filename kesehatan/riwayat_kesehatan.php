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

$judulHalaman = "Riwayat Kesehatan | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$cari = trim($_GET["cari"] ?? "");

$kataKunci = "%" . $cari . "%";

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

$roleBerbasisPuskesmas =
    in_array(
        $roleAktif,
        [
            "petugas_kia",
            "petugas_gizi",
            "kepala_puskesmas"
        ],
        true
    );

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas akun aktif
|--------------------------------------------------------------------------
|
| Petugas KIA, Petugas Gizi, dan Kepala Puskesmas hanya boleh melihat
| riwayat kesehatan dari Puskesmas akun masing-masing.
| Orang Tua hanya melihat anak sendiri.
| Dinkes dapat melihat seluruh wilayah.
|
*/

if ($roleBerbasisPuskesmas) {

    $stmtPuskesmas = mysqli_prepare(
        $conn,
        "SELECT
            u.id_puskesmas,
            p.nama_puskesmas
         FROM pengguna AS u
         LEFT JOIN puskesmas AS p
            ON u.id_puskesmas = p.id_puskesmas
         WHERE u.id_user = ?
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

    mysqli_stmt_execute(
        $stmtPuskesmas
    );

    $hasilPuskesmas =
        mysqli_stmt_get_result(
            $stmtPuskesmas
        );

    $dataPuskesmas =
        mysqli_fetch_assoc(
            $hasilPuskesmas
        );

    mysqli_stmt_close(
        $stmtPuskesmas
    );

    if (
        !$dataPuskesmas
        || empty(
            $dataPuskesmas[
                "id_puskesmas"
            ]
        )
    ) {
        $puskesmasBelumTerhubung = true;
    } else {
        $idPuskesmasAktif =
            (int) $dataPuskesmas[
                "id_puskesmas"
            ];

        $namaPuskesmasAktif =
            trim(
                (string) (
                    $dataPuskesmas[
                        "nama_puskesmas"
                    ]
                    ?? ""
                )
            );
    }
}

/*
|--------------------------------------------------------------------------
| Kolom SELECT bersama
|--------------------------------------------------------------------------
*/

$selectRiwayat = "
    SELECT
        rk.id_riwayat,
        rk.id_balita,
        rk.riwayat_penyakit,
        rk.riwayat_imunisasi,
        rk.riwayat_perawatan,
        rk.penyakit_penyerta,
        rk.status_red_flag,
        rk.catatan_red_flag,
        rk.status_rujukan,
        rk.rekomendasi_rujukan,
        rk.catatan_kia,

        b.nama_balita,
        b.nik_balita,
        b.id_puskesmas,

        p.nama_puskesmas

    FROM riwayat_kesehatan AS rk

    INNER JOIN balita AS b
        ON rk.id_balita = b.id_balita

    LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas
";

$filterPencarian = "
    (
        b.nama_balita LIKE ?
        OR b.nik_balita LIKE ?
        OR rk.riwayat_penyakit LIKE ?
        OR rk.riwayat_imunisasi LIKE ?
        OR rk.riwayat_perawatan LIKE ?
        OR rk.penyakit_penyerta LIKE ?
        OR rk.status_red_flag LIKE ?
        OR rk.catatan_red_flag LIKE ?
        OR rk.status_rujukan LIKE ?
        OR rk.rekomendasi_rujukan LIKE ?
        OR rk.catatan_kia LIKE ?
    )
";

/*
|--------------------------------------------------------------------------
| Mengambil data riwayat kesehatan berdasarkan role
|--------------------------------------------------------------------------
*/

if ($roleAktif === "orang_tua") {

    if ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectRiwayat . "
            WHERE b.id_user = ?
            AND " . $filterPencarian . "
            ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian riwayat kesehatan."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssssssssss",
            $idUserAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectRiwayat . "
            WHERE b.id_user = ?
            ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil riwayat kesehatan."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idUserAktif
        );
    }

} elseif ($roleBerbasisPuskesmas) {

    if ($puskesmasBelumTerhubung) {

        $stmt = mysqli_prepare(
            $conn,
            $selectRiwayat . "
            WHERE 1 = 0
            ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan data riwayat kesehatan."
            );
        }

    } elseif ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectRiwayat . "
            WHERE b.id_puskesmas = ?
            AND " . $filterPencarian . "
            ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian riwayat kesehatan."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssssssssss",
            $idPuskesmasAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectRiwayat . "
            WHERE b.id_puskesmas = ?
            ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil riwayat kesehatan."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idPuskesmasAktif
        );
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | Dinkes
    |--------------------------------------------------------------------------
    */

    if ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectRiwayat . "
            WHERE " . $filterPencarian . "
            ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian riwayat kesehatan."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sssssssssss",
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectRiwayat . "
            ORDER BY rk.id_riwayat DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil riwayat kesehatan."
            );
        }
    }
}

mysqli_stmt_execute($stmt);

$query =
    mysqli_stmt_get_result($stmt);

if (!$query) {
    die(
        "Gagal membaca hasil riwayat kesehatan."
    );
}

$totalData =
    mysqli_num_rows($query);

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
                        Riwayat Kesehatan Anak
                    </h4>

                    <small class="text-muted">
                        Kelola riwayat kesehatan, penyakit penyerta,
                        red flag, rujukan, dan catatan KIA balita.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        $roleBerbasisPuskesmas
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex
                            align-items-center
                            px-3"
                        >
                            <i class="bi bi-hospital me-1"></i>
                            <?= htmlspecialchars(
                                $namaPuskesmasAktif,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </span>

                    <?php endif; ?>

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <?php if (
                        $roleAktif === "petugas_kia"
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <a
                            href="tambah_kesehatan.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Tambah Data
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body">

                <?php if (
                    $roleBerbasisPuskesmas
                    && $puskesmasBelumTerhubung
                ): ?>

                    <div class="alert alert-warning">
                        <i
                            class="bi bi-exclamation-triangle me-1"
                        ></i>

                        Akun ini belum terhubung dengan Puskesmas.
                        Hubungkan akun ke Puskesmas terlebih dahulu
                        agar riwayat kesehatan wilayah dapat
                        ditampilkan.
                    </div>

                <?php endif; ?>

                <?php if (isset($_GET["pesan"])): ?>

                    <?php if (
                        $_GET["pesan"] === "tambah_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Riwayat kesehatan berhasil ditambahkan.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] === "edit_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Riwayat kesehatan berhasil diperbarui.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] === "hapus_berhasil"
                    ): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Riwayat kesehatan berhasil dihapus.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] === "hapus_gagal"
                    ): ?>

                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle me-1"></i>
                            Riwayat kesehatan gagal dihapus.
                        </div>

                    <?php elseif (
                        $_GET["pesan"] === "tidak_ditemukan"
                    ): ?>

                        <div class="alert alert-warning">
                            <i
                                class="bi
                                bi-exclamation-triangle me-1"
                            ></i>
                            Riwayat kesehatan tidak ditemukan.
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

                <div
                    class="detail-item mb-3"
                >

                <form
                    method="GET"
                    class="row g-2 align-items-end"
                >

                    <div class="col-12 col-md-8">

                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari balita, NIK, penyakit, red flag, rujukan, atau catatan KIA"
                            value="<?= htmlspecialchars(
                                $cari,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                        >

                    </div>

                    <div class="col-6 col-md-2">

                        <button
                            type="submit"
                            class="btn btn-primary btn-sm w-100"
                        >
                            <i class="bi bi-search"></i>
                            Cari
                        </button>

                    </div>

                    <div class="col-6 col-md-2">

                        <a
                            href="riwayat_kesehatan.php"
                            class="btn btn-outline-secondary btn-sm w-100"
                        >
                            <i
                                class="bi
                                bi-arrow-counterclockwise"
                            ></i>
                            Reset
                        </a>

                    </div>

                </form>

                </div>

                <div
                    class="d-flex flex-wrap
                    justify-content-between align-items-center
                    gap-2 mb-3"
                >

                    <span class="text-muted small">
                        Total data:
                        <strong>
                            <?= $totalData; ?>
                        </strong>
                        riwayat kesehatan
                    </span>

                    <div class="d-flex flex-wrap gap-2">

                        <?php if (
                            $roleAktif === "petugas_kia"
                        ): ?>

                            <span class="badge badge-primary">
                                <i class="bi bi-pencil-square"></i>
                                Kelola KIA
                            </span>

                        <?php else: ?>

                            <span class="badge badge-info">
                                <i class="bi bi-eye"></i>
                                Mode lihat
                            </span>

                        <?php endif; ?>

                        <?php if (
                            $roleAktif === "dinkes"
                        ): ?>

                            <span class="badge badge-info">
                                <i class="bi bi-buildings"></i>
                                Semua Puskesmas
                            </span>

                        <?php endif; ?>

                    </div>

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

                                <th>
                                    Riwayat Kesehatan
                                </th>

                                <th>
                                    Penyakit Penyerta
                                </th>

                                <th>
                                    Puskesmas
                                </th>

                                <th class="text-center">
                                    Red Flag
                                </th>

                                <th class="text-center">
                                    Status Rujukan
                                </th>

                                <th
                                    class="text-center"
                                    style="min-width: 210px;"
                                >
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (
                                $totalData > 0
                            ): ?>

                                <?php
                                $no = 1;

                                while (
                                    $d = mysqli_fetch_assoc(
                                        $query
                                    )
                                ):
                                ?>

                                    <tr>

                                        <td class="text-center">
                                            <?= $no++; ?>
                                        </td>

                                        <?php

                                        $statusRedFlag =
                                            trim(
                                                (string) (
                                                    $d[
                                                        "status_red_flag"
                                                    ]
                                                    ?? "Belum dinilai"
                                                )
                                            );

                                        $catatanRedFlag =
                                            trim(
                                                (string) (
                                                    $d[
                                                        "catatan_red_flag"
                                                    ]
                                                    ?? ""
                                                )
                                            );

                                        if (
                                            $statusRedFlag === "Ada"
                                        ) {
                                            $kelasRedFlag =
                                                "bg-danger";
                                            $ikonRedFlag =
                                                "bi-exclamation-octagon";
                                        } elseif (
                                            $statusRedFlag === "Tidak ada"
                                        ) {
                                            $kelasRedFlag =
                                                "bg-success";
                                            $ikonRedFlag =
                                                "bi-check-circle";
                                        } else {
                                            $kelasRedFlag =
                                                "bg-warning text-dark";
                                            $ikonRedFlag =
                                                "bi-clock-history";
                                        }

                                        $statusRujukan =
                                            trim(
                                                (string) (
                                                    $d[
                                                        "status_rujukan"
                                                    ] ?? ""
                                                )
                                            );

                                        $statusRujukanLower =
                                            strtolower($statusRujukan);

                                        if (
                                            $statusRujukanLower === ""
                                            || $statusRujukanLower ===
                                                "tidak perlu"
                                        ) {
                                            $kelasRujukan =
                                                "bg-success";
                                            $labelRujukan =
                                                $statusRujukan !== ""
                                                    ? $statusRujukan
                                                    : "Tidak Perlu";
                                        } elseif (
                                            str_contains(
                                                $statusRujukanLower,
                                                "rekomend"
                                            )
                                        ) {
                                            $kelasRujukan =
                                                "bg-warning text-dark";
                                            $labelRujukan =
                                                $statusRujukan;
                                        } elseif (
                                            str_contains(
                                                $statusRujukanLower,
                                                "rujuk"
                                            )
                                        ) {
                                            $kelasRujukan =
                                                "bg-danger";
                                            $labelRujukan =
                                                $statusRujukan;
                                        } else {
                                            $kelasRujukan =
                                                "bg-info";
                                            $labelRujukan =
                                                $statusRujukan;
                                        }

                                        ?>

                                        <td>

                                            <div
                                                class="d-flex
                                                align-items-center
                                                gap-2"
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

                                                    <strong
                                                        class="d-block"
                                                    >
                                                        <?= htmlspecialchars(
                                                            $d[
                                                                "nama_balita"
                                                            ],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ); ?>
                                                    </strong>

                                                    <small
                                                        class="text-muted"
                                                    >
                                                        NIK:
                                                        <?= htmlspecialchars(
                                                            $d[
                                                                "nik_balita"
                                                            ],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ); ?>
                                                    </small>

                                                </div>

                                            </div>

                                        </td>

                                        <td>

                                            <small
                                                class="text-muted
                                                d-block"
                                            >
                                                Penyakit:
                                            </small>

                                            <span class="d-block mb-1">
                                                <?= htmlspecialchars(
                                                    mb_strlen(
                                                        $d[
                                                            "riwayat_penyakit"
                                                        ]
                                                    ) > 45
                                                        ? mb_substr(
                                                            $d[
                                                                "riwayat_penyakit"
                                                            ],
                                                            0,
                                                            45
                                                        ) . "..."
                                                        : $d[
                                                            "riwayat_penyakit"
                                                        ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </span>

                                            <small class="text-muted">
                                                Imunisasi dan perawatan
                                                tersedia di Detail.
                                            </small>

                                        </td>

                                        <td>

                                            <?php if (
                                                trim(
                                                    (string) (
                                                        $d[
                                                            "penyakit_penyerta"
                                                        ] ?? ""
                                                    )
                                                ) !== ""
                                            ): ?>

                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $d[
                                                            "penyakit_penyerta"
                                                        ],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    )
                                                ); ?>

                                            <?php else: ?>

                                                <span
                                                    class="text-muted"
                                                >
                                                    Tidak ada
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $d["nama_puskesmas"]
                                                    ?? "-",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </td>

                                        <td class="text-center">

                                            <span
                                                class="badge
                                                <?= $kelasRedFlag; ?>"
                                                <?php if (
                                                    $statusRedFlag === "Ada"
                                                    && $catatanRedFlag !== ""
                                                ): ?>
                                                    title="<?= htmlspecialchars(
                                                        $catatanRedFlag,
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>"
                                                <?php endif; ?>
                                            >
                                                <i
                                                    class="bi
                                                    <?= $ikonRedFlag; ?>"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    $statusRedFlag,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </span>

                                        </td>

                                        <td class="text-center">

                                            <span
                                                class="badge
                                                <?= $kelasRujukan; ?>"
                                            >
                                                <?= htmlspecialchars(
                                                    $labelRujukan,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </span>

                                        </td>

                                        <td>

                                            <div
                                                class="table-actions
                                                justify-content-center"
                                            >

                                                <a
                                                    href="detail_kesehatan.php?id=<?= (int) $d["id_riwayat"]; ?>"
                                                    class="btn btn-info btn-sm"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-eye"
                                                    ></i>
                                                    Detail
                                                </a>

                                                <?php if (
                                                    $roleAktif ===
                                                    "petugas_kia"
                                                ): ?>

                                                    <a
                                                        href="edit_kesehatan.php?id=<?= (int) $d["id_riwayat"]; ?>"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        <i
                                                            class="bi
                                                            bi-pencil-square"
                                                        ></i>
                                                        Edit
                                                    </a>

                                                    <form
                                                        action="hapus_kesehatan.php"
                                                        method="POST"
                                                        class="d-inline
                                                        form-hapus-kesehatan"
                                                        data-nama="<?= htmlspecialchars(
                                                            $d[
                                                                "nama_balita"
                                                            ],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ); ?>"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_riwayat"
                                                            value="<?= (int) $d["id_riwayat"]; ?>"
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

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="8">

                                        <div class="empty-state">

                                            <div
                                                class="empty-state-icon"
                                            >
                                                <i
                                                    class="bi
                                                    bi-heart-pulse"
                                                ></i>
                                            </div>

                                            <h3>
                                                Belum ada riwayat kesehatan
                                            </h3>

                                            <p>
                                                Data riwayat kesehatan
                                                balita belum tersedia.
                                            </p>

                                            <?php if (
                                                $roleAktif ===
                                                "petugas_kia"
                                                && !$puskesmasBelumTerhubung
                                            ): ?>

                                                <a
                                                    href="tambah_kesehatan.php"
                                                    class="btn btn-primary mt-3"
                                                >
                                                    <i
                                                        class="bi
                                                        bi-plus-circle"
                                                    ></i>
                                                    Tambah Data
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

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        const formHapus =
            document.querySelectorAll(
                ".form-hapus-kesehatan"
            );

        formHapus.forEach(
            function (form) {

                form.addEventListener(
                    "submit",
                    function (event) {

                        const namaBalita =
                            form.dataset.nama
                            || "balita ini";

                        const yakin =
                            window.confirm(
                                "Yakin ingin menghapus riwayat kesehatan "
                                + namaBalita
                                + "?\n\nData yang sudah dihapus tidak dapat dikembalikan."
                            );

                        if (!yakin) {
                            event.preventDefault();
                        }
                    }
                );
            }
        );
    }
);
</script>

<?php

mysqli_stmt_close($stmt);

require_once "../includes/footer.php";

?>