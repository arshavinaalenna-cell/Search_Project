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

$judulHalaman = "Hasil Deteksi Stunting | Sistem Deteksi Stunting";

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
            "petugas_gizi",
            "petugas_kia",
            "kepala_puskesmas"
        ],
        true
    );

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas akun aktif
|--------------------------------------------------------------------------
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

    mysqli_stmt_execute($stmtPuskesmas);

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
            $dataPuskesmas["id_puskesmas"]
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
| Query dasar hasil deteksi
|--------------------------------------------------------------------------
*/

$selectDeteksi = "
    SELECT
        hd.id_deteksi,
        hd.id_pengukuran,
        hd.status_gizi,
        hd.status_stunting,
        hd.tanggal_deteksi,
        hd.status_verifikasi,

        pa.tanggal_pengukuran,
        pa.umur_bulan,
        pa.berat_badan,
        pa.tinggi_panjang_badan,
        pa.lingkar_kepala,
        pa.lila,

        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.jenis_kelamin,
        b.id_puskesmas,

        p.nama_puskesmas

    FROM hasil_deteksi AS hd

    INNER JOIN pengukuran_antropometri AS pa
        ON hd.id_pengukuran = pa.id_pengukuran

    INNER JOIN balita AS b
        ON pa.id_balita = b.id_balita

    LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas
";

$filterCari = "
    (
        b.nama_balita LIKE ?
        OR b.nik_balita LIKE ?
        OR hd.status_gizi LIKE ?
        OR hd.status_stunting LIKE ?
        OR hd.status_verifikasi LIKE ?
    )
";

/*
|--------------------------------------------------------------------------
| Mengambil data berdasarkan role
|--------------------------------------------------------------------------
|
| Orang Tua         : anak sendiri.
| Gizi/KIA/Kapus    : Puskesmas akun sendiri.
| Dinkes            : seluruh Puskesmas.
|
*/

if ($roleAktif === "orang_tua") {

    if ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE b.id_user = ?
            AND " . $filterCari . "
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian hasil deteksi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssss",
            $idUserAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE b.id_user = ?
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil hasil deteksi: "
                . mysqli_error($conn)
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
            $selectDeteksi . "
            WHERE 1 = 0
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan hasil deteksi."
            );
        }

    } elseif ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE b.id_puskesmas = ?
            AND " . $filterCari . "
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian hasil deteksi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssss",
            $idPuskesmasAktif,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE b.id_puskesmas = ?
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil hasil deteksi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idPuskesmasAktif
        );
    }

} else {

    if ($cari !== "") {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            WHERE " . $filterCari . "
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal menyiapkan pencarian hasil deteksi: "
                . mysqli_error($conn)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci,
            $kataKunci
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            $selectDeteksi . "
            ORDER BY hd.id_deteksi DESC"
        );

        if (!$stmt) {
            die(
                "Gagal mengambil hasil deteksi: "
                . mysqli_error($conn)
            );
        }
    }
}

mysqli_stmt_execute($stmt);

$query =
    mysqli_stmt_get_result($stmt);

if (!$query) {
    die(
        "Gagal membaca hasil deteksi."
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
                        <i class="bi bi-clipboard2-pulse me-2"></i>
                        Hasil Deteksi Stunting
                    </h4>

                    <small class="text-muted">
                        Hasil status gizi dan status stunting berdasarkan
                        data pengukuran antropometri.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        $roleBerbasisPuskesmas
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex align-items-center px-3"
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
                        $roleAktif === "petugas_gizi"
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <a
                            href="../skrining/hasil_skrining.php"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="bi bi-activity"></i>
                            Pilih Data untuk Analisis
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
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Akun ini belum terhubung dengan Puskesmas.
                        Data hasil deteksi wilayah tidak dapat ditampilkan.
                    </div>

                <?php endif; ?>

                <?php if (isset($_GET["pesan"])): ?>

                    <?php if ($_GET["pesan"] === "analisis_berhasil"): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Hasil deteksi berhasil disimpan.
                        </div>

                    <?php elseif ($_GET["pesan"] === "edit_berhasil"): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Hasil deteksi berhasil diperbarui.
                        </div>

                    <?php elseif ($_GET["pesan"] === "tidak_ditemukan"): ?>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Data hasil deteksi tidak ditemukan.
                        </div>

                    <?php elseif ($_GET["pesan"] === "gagal"): ?>

                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle me-1"></i>
                            Proses deteksi gagal dilakukan.
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

                <form
                    method="GET"
                    class="row g-2 mb-3"
                >

                    <div class="col-12 col-md-8">

                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari nama, NIK, status gizi, atau status stunting"
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
                            class="btn btn-primary w-100"
                        >
                            <i class="bi bi-search"></i>
                            Cari
                        </button>

                    </div>

                    <div class="col-6 col-md-2">

                        <a
                            href="hasil_deteksi.php"
                            class="btn btn-light w-100"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset
                        </a>

                    </div>

                </form>

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
                        hasil deteksi
                    </span>

                    <?php if ($roleAktif !== "petugas_gizi"): ?>

                        <span class="badge badge-info">
                            <i class="bi bi-eye"></i>
                            Mode lihat
                        </span>

                    <?php endif; ?>

                </div>

                <div
                    class="status-legend d-flex flex-wrap
                    align-items-center gap-2 mb-3"
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

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th class="text-center">
                                    No
                                </th>

                                <th class="text-center">
                                    Tanggal Deteksi
                                </th>

                                <th>
                                    Nama Balita
                                </th>

                                <th>
                                    NIK
                                </th>

                                <th>
                                    Puskesmas
                                </th>

                                <th class="text-center">
                                    JK
                                </th>

                                <th class="text-center">
                                    Umur
                                </th>

                                <th class="text-center">
                                    BB
                                </th>

                                <th class="text-center">
                                    TB/PB
                                </th>

                                <th class="text-center">
                                    Status Gizi
                                </th>

                                <th class="text-center">
                                    Status Stunting
                                </th>

                                <th class="text-center">
                                    Verifikasi
                                </th>

                                <th class="text-center">
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($totalData > 0): ?>

                            <?php
                            $no = 1;

                            while ($data = mysqli_fetch_assoc($query)):

                                $statusStunting = strtolower(
                                    trim(
                                        $data["status_stunting"] ?? ""
                                    )
                                );

                                $kelasStunting = "bg-secondary";

                                if (
                                    $statusStunting === "normal"
                                    || $statusStunting === "normal/sehat"
                                    || $statusStunting === "tidak stunting"
                                ) {

                                    $kelasStunting = "bg-success";

                                } elseif (
                                    $statusStunting === "risiko stunting"
                                ) {

                                    $kelasStunting =
                                        "bg-warning text-dark";

                                } elseif (
                                    $statusStunting === "stunting"
                                    || $statusStunting === "pendek"
                                ) {

                                    $kelasStunting = "bg-danger";

                                } elseif (
                                    $statusStunting === "stunting berat"
                                    || $statusStunting === "severely stunted"
                                    || $statusStunting === "sangat pendek"
                                ) {

                                    $kelasStunting =
                                        "bg-dark text-white";
                                }

                                $statusVerifikasi =
                                    trim(
                                        (string) (
                                            $data["status_verifikasi"]
                                            ?? ""
                                        )
                                    );

                                if ($statusVerifikasi === "") {
                                    $statusVerifikasi =
                                        "Belum diverifikasi";
                                }

                                $statusVerifikasiNormal =
                                    strtolower(
                                        $statusVerifikasi
                                    );

                                $kelasVerifikasi =
                                    "bg-secondary";

                                if (
                                    $statusVerifikasiNormal
                                    === "sudah diverifikasi"
                                ) {

                                    $kelasVerifikasi =
                                        "bg-success";

                                } elseif (
                                    $statusVerifikasiNormal
                                    === "perlu pemeriksaan ulang"
                                ) {

                                    $kelasVerifikasi =
                                        "bg-warning text-dark";
                                }
                            ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $no++; ?>
                                    </td>

                                    <td class="text-center">
                                        <?= !empty(
                                            $data["tanggal_deteksi"]
                                        )
                                            ? date(
                                                "d-m-Y",
                                                strtotime(
                                                    $data[
                                                        "tanggal_deteksi"
                                                    ]
                                                )
                                            )
                                            : "-"; ?>
                                    </td>

                                    <td>

                                        <div
                                            class="d-flex
                                            align-items-center gap-2"
                                        >

                                            <span
                                                class="badge badge-primary"
                                            >
                                                <i
                                                    class="bi
                                                    bi-person-heart"
                                                ></i>
                                            </span>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $data[
                                                        "nama_balita"
                                                    ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </strong>

                                        </div>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $data["nik_balita"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $data["nama_puskesmas"]
                                                ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars(
                                            $data["jenis_kelamin"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <?= (int) (
                                            $data["umur_bulan"] ?? 0
                                        ); ?>
                                        bulan
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars(
                                            $data[
                                                "berat_badan"
                                            ] ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        kg
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars(
                                            $data[
                                                "tinggi_panjang_badan"
                                            ] ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                        cm
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars(
                                            $data["status_gizi"]
                                                ?? "Belum tersedia",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge <?= $kelasStunting; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $data[
                                                    "status_stunting"
                                                ]
                                                    ?? "Belum tersedia",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <span
                                            class="badge <?= $kelasVerifikasi; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $statusVerifikasi,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

                                            <a
                                                href="detail_deteksi.php?id=<?= (int) $data["id_deteksi"]; ?>"
                                                class="btn btn-info btn-sm"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Detail
                                            </a>

                                            <?php if (
                                                $roleAktif === "petugas_gizi"
                                            ): ?>

                                                <a
                                                    href="verifikasi_deteksi.php?id=<?= (int) $data["id_deteksi"]; ?>"
                                                    class="btn btn-success btn-sm"
                                                >
                                                    <i class="bi bi-check2-circle"></i>
                                                    Verifikasi
                                                </a>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="13">

                                    <div class="empty-state">

                                        <div class="empty-state-icon">
                                            <i
                                                class="bi
                                                bi-clipboard2-pulse"
                                            ></i>
                                        </div>

                                        <h3>
                                            Belum ada hasil deteksi
                                        </h3>

                                        <p>
                                            Data hasil deteksi
                                            stunting belum tersedia.
                                        </p>

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

<?php

mysqli_stmt_close($stmt);

require_once "../includes/footer.php";

?>
