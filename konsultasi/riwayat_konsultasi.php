<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "orang_tua",
    "petugas_gizi",
    "petugas_kia",
    "kader",
    "kepala_puskesmas",
    "dinkes"
]);

date_default_timezone_set("Asia/Jakarta");

$judulHalaman = "Riwayat Konsultasi | Sistem Deteksi Stunting";

$roleAktif = $_SESSION["role"] ?? "";
$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = null;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

if (
    in_array(
        $roleAktif,
        ["petugas_gizi", "petugas_kia", "kader", "kepala_puskesmas"],
        true
    )
) {
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
        mysqli_stmt_get_result($stmtPuskesmas);

    $dataPuskesmas =
        mysqli_fetch_assoc($hasilPuskesmas);

    mysqli_stmt_close($stmtPuskesmas);

    if (
        !$dataPuskesmas
        || empty($dataPuskesmas["id_puskesmas"])
    ) {
        $puskesmasBelumTerhubung = true;
    } else {
        $idPuskesmasAktif =
            (int) $dataPuskesmas["id_puskesmas"];

        $namaPuskesmasAktif =
            trim(
                (string) (
                    $dataPuskesmas["nama_puskesmas"]
                    ?? ""
                )
            );
    }
}

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$cari = trim((string) ($_GET["cari"] ?? ""));

$filterBulan =
    filter_input(
        INPUT_GET,
        "bulan",
        FILTER_VALIDATE_INT
    ) ?: 0;

$filterTahun =
    filter_input(
        INPUT_GET,
        "tahun",
        FILTER_VALIDATE_INT
    ) ?: 0;

$filterUrutan =
    strtolower(
        trim(
            (string) (
                $_GET["urutan"]
                ?? "terbaru"
            )
        )
    );

$filterStatusStunting =
    trim(
        (string) (
            $_GET["status_stunting"]
            ?? ""
        )
    );

$filterPuskesmas = 0;

if (
    $filterBulan < 1
    || $filterBulan > 12
) {
    $filterBulan = 0;
}

if (
    $filterTahun < 2000
    || $filterTahun > 2100
) {
    $filterTahun = 0;
}

if (
    !in_array(
        $filterUrutan,
        ["terbaru", "terlama"],
        true
    )
) {
    $filterUrutan = "terbaru";
}

$statusStuntingValid = [
    "",
    "Normal",
    "Risiko Stunting",
    "Stunting",
    "Stunting Berat"
];

if (
    !in_array(
        $filterStatusStunting,
        $statusStuntingValid,
        true
    )
) {
    $filterStatusStunting = "";
}

$daftarPuskesmasFilter = [];

if ($roleAktif === "dinkes") {

    $filterPuskesmas =
        filter_input(
            INPUT_GET,
            "puskesmas",
            FILTER_VALIDATE_INT
        ) ?: 0;

    $queryPuskesmas = mysqli_query(
        $conn,
        "SELECT id_puskesmas, nama_puskesmas
         FROM puskesmas
         ORDER BY nama_puskesmas ASC"
    );

    if ($queryPuskesmas) {
        while (
            $rowPuskesmas =
                mysqli_fetch_assoc($queryPuskesmas)
        ) {
            $daftarPuskesmasFilter[] =
                $rowPuskesmas;
        }
    }
}

$daftarTahunFilter = [];

$queryTahun = mysqli_query(
    $conn,
    "SELECT DISTINCT
        LEFT(CAST(tanggal AS CHAR), 4) AS tahun
     FROM konsultasi
     WHERE tanggal IS NOT NULL
       AND LEFT(CAST(tanggal AS CHAR), 4) <> '0000'
     ORDER BY tahun DESC"
);

if ($queryTahun) {
    while (
        $rowTahun =
            mysqli_fetch_assoc($queryTahun)
    ) {
        $tahun =
            (int) ($rowTahun["tahun"] ?? 0);

        if (
            $tahun >= 2000
            && $tahun <= 2100
        ) {
            $daftarTahunFilter[] = $tahun;
        }
    }
}

function bindRiwayatKonsultasi(
    mysqli_stmt $stmt,
    string $tipe,
    array &$parameter
): void {
    if ($tipe === "" || empty($parameter)) {
        return;
    }

    $argumen = [$tipe];

    foreach ($parameter as &$nilai) {
        $argumen[] = &$nilai;
    }

    call_user_func_array(
        [$stmt, "bind_param"],
        $argumen
    );
}

function amanRiwayatKonsultasi($nilai): string
{
    if ($nilai === null || $nilai === "") {
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
| DATA RIWAYAT
|--------------------------------------------------------------------------
|
| Selesai = hasil konsultasi terisi + tindak lanjut terisi.
| Data tetap tersimpan di tabel konsultasi, hanya dipisah secara tampilan.
|
*/

$sqlDasar = "
    SELECT
        k.id_konsultasi,
        k.id_deteksi,
        k.id_balita,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        k.status_konsultasi,
        k.tanggal_selesai,
        b.nama_balita,
        b.nik_balita,
        b.id_puskesmas,
        ps.nama_puskesmas,
        p.nama AS nama_petugas,
        hd.status_gizi,
        hd.status_stunting,
        hd.status_verifikasi
    FROM konsultasi AS k
    INNER JOIN balita AS b
        ON k.id_balita = b.id_balita
    LEFT JOIN puskesmas AS ps
        ON b.id_puskesmas = ps.id_puskesmas
    LEFT JOIN pengguna AS p
        ON k.id_petugas = p.id_user
    INNER JOIN hasil_deteksi AS hd
        ON k.id_deteksi = hd.id_deteksi
";

$where = [
    "k.sumber_pengajuan = 'ahli_gizi'",
    "LOWER(TRIM(COALESCE(k.status_konsultasi, 'aktif'))) = 'selesai'"
];

$tipe = "";
$parameter = [];

if ($roleAktif === "orang_tua") {

    $where[] = "b.id_user = ?";
    $tipe .= "i";
    $parameter[] = $idUserAktif;

} elseif (
    in_array(
        $roleAktif,
        ["petugas_gizi", "petugas_kia", "kader", "kepala_puskesmas"],
        true
    )
) {

    if ($puskesmasBelumTerhubung) {
        $where[] = "1 = 0";
    } else {
        $where[] = "b.id_puskesmas = ?";
        $tipe .= "i";
        $parameter[] = $idPuskesmasAktif;
    }

} elseif (
    $roleAktif === "dinkes"
    && $filterPuskesmas > 0
) {

    $where[] = "b.id_puskesmas = ?";
    $tipe .= "i";
    $parameter[] = $filterPuskesmas;
}

if ($filterBulan > 0) {

    $bulanDuaDigit =
        str_pad(
            (string) $filterBulan,
            2,
            "0",
            STR_PAD_LEFT
        );

    $where[] =
        "SUBSTRING(CAST(k.tanggal AS CHAR), 6, 2) = ?";

    $tipe .= "s";
    $parameter[] = $bulanDuaDigit;
}

if ($filterTahun > 0) {

    $where[] =
        "LEFT(CAST(k.tanggal AS CHAR), 4) = ?";

    $tipe .= "s";
    $parameter[] = (string) $filterTahun;
}

if ($filterStatusStunting !== "") {

    $where[] =
        "LOWER(TRIM(COALESCE(hd.status_stunting, ''))) = LOWER(?)";

    $tipe .= "s";
    $parameter[] = $filterStatusStunting;
}

if ($cari !== "") {

    $kataKunci = "%" . $cari . "%";

    $where[] = "
        (
            b.nama_balita LIKE ?
            OR b.nik_balita LIKE ?
            OR ps.nama_puskesmas LIKE ?
            OR p.nama LIKE ?
            OR hd.status_gizi LIKE ?
            OR hd.status_stunting LIKE ?
            OR k.keluhan LIKE ?
            OR k.hasil_konsultasi LIKE ?
            OR k.tindak_lanjut LIKE ?
        )
    ";

    $tipe .= "sssssssss";

    for ($i = 0; $i < 9; $i++) {
        $parameter[] = $kataKunci;
    }
}

$arah =
    $filterUrutan === "terlama"
        ? "ASC"
        : "DESC";

$sql =
    $sqlDasar
    . " WHERE "
    . implode(" AND ", $where)
    . " ORDER BY
        COALESCE(k.tanggal_selesai, k.tanggal) {$arah},
        k.id_konsultasi {$arah}";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die(
        "Gagal menyiapkan riwayat konsultasi: "
        . mysqli_error($conn)
    );
}

bindRiwayatKonsultasi(
    $stmt,
    $tipe,
    $parameter
);

if (!mysqli_stmt_execute($stmt)) {
    die(
        "Gagal menjalankan riwayat konsultasi: "
        . mysqli_stmt_error($stmt)
    );
}

$query =
    mysqli_stmt_get_result($stmt);

$totalData =
    $query
        ? mysqli_num_rows($query)
        : 0;

$pesan =
    trim(
        (string) (
            $_GET["pesan"]
            ?? ""
        )
    );

$jenisAlert = "";
$isiPesan = "";

if ($pesan === "selesai") {
    $jenisAlert = "success";
    $isiPesan =
        "Konsultasi telah selesai dan tersimpan sebagai riwayat.";
} elseif ($pesan === "terkunci") {
    $jenisAlert = "info";
    $isiPesan =
        "Konsultasi yang sudah selesai dikunci sebagai riwayat dan tidak dapat diedit lagi.";
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
                        Riwayat Konsultasi
                    </h4>

                    <small class="text-muted">
                        Konsultasi yang sudah selesai disimpan sebagai riwayat
                        dan tidak dapat diubah kembali.
                    </small>
                </div>

                <div class="d-flex flex-wrap gap-2">

                    <?php if (
                        in_array(
                            $roleAktif,
                            [
                                "petugas_gizi",
                                "petugas_kia",
                                "kader",
                                "kepala_puskesmas"
                            ],
                            true
                        )
                        && !$puskesmasBelumTerhubung
                    ): ?>

                        <span class="badge badge-info d-inline-flex align-items-center px-3">
                            <i class="bi bi-hospital me-1"></i>
                            <?= amanRiwayatKonsultasi(
                                $namaPuskesmasAktif
                            ); ?>
                        </span>

                    <?php elseif (
                        $roleAktif === "dinkes"
                    ): ?>

                        <span class="badge badge-info d-inline-flex align-items-center px-3">
                            <i class="bi bi-buildings me-1"></i>
                            Monitoring Riwayat
                        </span>

                    <?php endif; ?>

                    <a
                        href="data_konsultasi.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Konsultasi Aktif
                    </a>

                </div>

            </div>

            <div class="card-body">

                <?php if ($isiPesan !== ""): ?>

                    <div
                        class="alert alert-<?= htmlspecialchars(
                            $jenisAlert,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?> alert-dismissible fade show"
                        role="alert"
                    >
                        <i class="bi bi-info-circle me-1"></i>

                        <?= htmlspecialchars(
                            $isiPesan,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>
                    </div>

                <?php endif; ?>

                <?php if ($puskesmasBelumTerhubung): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Akun belum terhubung dengan Puskesmas.
                    </div>
                <?php endif; ?>

                <form
                    method="GET"
                    class="row g-2 mb-4 align-items-end"
                >

                    <div class="col-12 col-xl-4">
                        <label class="form-label small text-muted mb-1">
                            Cari
                        </label>

                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="text"
                                name="cari"
                                class="form-control"
                                placeholder="Cari balita, NIK, petugas, hasil, tindak lanjut..."
                                value="<?= htmlspecialchars(
                                    $cari,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                            >
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label small text-muted mb-1">
                            Bulan
                        </label>

                        <select name="bulan" class="form-select">
                            <option value="0">
                                Semua Bulan
                            </option>

                            <?php
                            $namaBulan = [
                                1 => "Januari",
                                2 => "Februari",
                                3 => "Maret",
                                4 => "April",
                                5 => "Mei",
                                6 => "Juni",
                                7 => "Juli",
                                8 => "Agustus",
                                9 => "September",
                                10 => "Oktober",
                                11 => "November",
                                12 => "Desember"
                            ];
                            ?>

                            <?php foreach (
                                $namaBulan
                                as $nomor => $nama
                            ): ?>
                                <option
                                    value="<?= $nomor; ?>"
                                    <?= $filterBulan === $nomor
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= htmlspecialchars(
                                        $nama,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label small text-muted mb-1">
                            Tahun
                        </label>

                        <select name="tahun" class="form-select">
                            <option value="0">
                                Semua Tahun
                            </option>

                            <?php foreach (
                                $daftarTahunFilter
                                as $tahun
                            ): ?>
                                <option
                                    value="<?= (int) $tahun; ?>"
                                    <?= $filterTahun === (int) $tahun
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= (int) $tahun; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label small text-muted mb-1">
                            Status Stunting
                        </label>

                        <select
                            name="status_stunting"
                            class="form-select"
                        >
                            <option value="">
                                Semua Status
                            </option>

                            <?php foreach (
                                [
                                    "Normal",
                                    "Risiko Stunting",
                                    "Stunting",
                                    "Stunting Berat"
                                ]
                                as $status
                            ): ?>
                                <option
                                    value="<?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                    <?= $filterStatusStunting === $status
                                        ? "selected"
                                        : ""; ?>
                                >
                                    <?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label small text-muted mb-1">
                            Urutan
                        </label>

                        <select name="urutan" class="form-select">
                            <option
                                value="terbaru"
                                <?= $filterUrutan === "terbaru"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Terbaru
                            </option>

                            <option
                                value="terlama"
                                <?= $filterUrutan === "terlama"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Terlama
                            </option>
                        </select>
                    </div>

                    <?php if (
                        $roleAktif === "dinkes"
                    ): ?>

                        <div class="col-12 col-md-6 col-xl-4">
                            <label class="form-label small text-muted mb-1">
                                Puskesmas
                            </label>

                            <select
                                name="puskesmas"
                                class="form-select"
                            >
                                <option value="0">
                                    Semua Puskesmas
                                </option>

                                <?php foreach (
                                    $daftarPuskesmasFilter
                                    as $puskesmas
                                ): ?>
                                    <option
                                        value="<?= (int) $puskesmas["id_puskesmas"]; ?>"
                                        <?= $filterPuskesmas ===
                                            (int) $puskesmas["id_puskesmas"]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= amanRiwayatKonsultasi(
                                            $puskesmas["nama_puskesmas"]
                                            ?? ""
                                        ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    <?php endif; ?>

                    <div class="col-6 col-md-3 col-xl-2 d-grid">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-funnel me-1"></i>
                            Filter
                        </button>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2 d-grid">
                        <a
                            href="riwayat_konsultasi.php"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            Reset
                        </a>
                    </div>

                </form>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted small">
                        Total riwayat:
                        <strong><?= (int) $totalData; ?></strong>
                        konsultasi
                    </span>

                    <span class="badge bg-success">
                        <i class="bi bi-check-circle me-1"></i>
                        Selesai
                    </span>
                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th>Tanggal</th>
                                <th>Balita</th>
                                <th>Puskesmas</th>
                                <th>Status Stunting</th>
                                <th>Petugas Gizi</th>
                                <th>Status</th>
                                <th>Hasil Konsultasi</th>
                                <th>Tindak Lanjut</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (
                            $query
                            && $totalData > 0
                        ): ?>

                            <?php
                            $nomor = 1;

                            while (
                                $data =
                                    mysqli_fetch_assoc($query)
                            ):
                                $tanggalTampil = "-";

                                $tanggalRiwayat =
                                    $data["tanggal_selesai"]
                                    ?? $data["tanggal"]
                                    ?? null;

                                if (
                                    !empty($tanggalRiwayat)
                                    && substr((string) $tanggalRiwayat, 0, 10) !== "0000-00-00"
                                ) {
                                    $timestamp =
                                        strtotime($tanggalRiwayat);

                                    if ($timestamp !== false) {
                                        $tanggalTampil =
                                            date("d-m-Y", $timestamp);
                                    }
                                }

                                $statusStunting =
                                    trim(
                                        (string) (
                                            $data["status_stunting"]
                                            ?? ""
                                        )
                                    );

                                $statusStuntingLower =
                                    strtolower($statusStunting);

                                if (
                                    $statusStuntingLower === "normal"
                                ) {
                                    $kelasStatus = "bg-success";
                                } elseif (
                                    $statusStuntingLower === "risiko stunting"
                                ) {
                                    $kelasStatus =
                                        "bg-warning text-dark";
                                } else {
                                    $kelasStatus = "bg-danger";
                                }
                            ?>

                                <tr>
                                    <td class="text-center">
                                        <?= $nomor++; ?>
                                    </td>

                                    <td class="text-nowrap">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?= amanRiwayatKonsultasi(
                                            $tanggalTampil
                                        ); ?>
                                    </td>

                                    <td>
                                        <strong class="d-block">
                                            <?= amanRiwayatKonsultasi(
                                                $data["nama_balita"]
                                                ?? ""
                                            ); ?>
                                        </strong>

                                        <small class="text-muted">
                                            NIK:
                                            <?= amanRiwayatKonsultasi(
                                                $data["nik_balita"]
                                                ?? ""
                                            ); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= amanRiwayatKonsultasi(
                                            $data["nama_puskesmas"]
                                            ?? ""
                                        ); ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $kelasStatus; ?>">
                                            <?= amanRiwayatKonsultasi(
                                                $statusStunting
                                            ); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= amanRiwayatKonsultasi(
                                            $data["nama_petugas"]
                                            ?? "Belum ditentukan"
                                        ); ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Selesai
                                        </span>
                                    </td>

                                    <td style="min-width: 220px; white-space: normal;">
                                        <?= nl2br(
                                            amanRiwayatKonsultasi(
                                                $data["hasil_konsultasi"]
                                                ?? ""
                                            )
                                        ); ?>
                                    </td>

                                    <td style="min-width: 220px; white-space: normal;">
                                        <?= nl2br(
                                            amanRiwayatKonsultasi(
                                                $data["tindak_lanjut"]
                                                ?? ""
                                            )
                                        ); ?>
                                    </td>

                                    <td class="text-center">
                                        <a
                                            href="detail_konsultasi.php?id=<?= (int) $data["id_konsultasi"]; ?>&kembali=riwayat"
                                            class="btn btn-info btn-sm"
                                        >
                                            <i class="bi bi-eye"></i>
                                            Detail
                                        </a>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td
                                    colspan="10"
                                    class="text-center py-5 text-muted"
                                >
                                    <i class="bi bi-clock-history fs-3 d-block mb-2"></i>
                                    Belum ada riwayat konsultasi yang sesuai.
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
if (
    isset($stmt)
    && $stmt instanceof mysqli_stmt
) {
    mysqli_stmt_close($stmt);
}

require_once "../includes/footer.php";
?>
