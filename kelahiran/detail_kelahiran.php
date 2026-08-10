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
    "petugas_kia"
]);

$judulHalaman =
    "Detail Riwayat Kelahiran | Sistem Deteksi Stunting";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";

/*
|--------------------------------------------------------------------------
| Mengambil Puskesmas Petugas KIA aktif
|--------------------------------------------------------------------------
|
| Detail hanya boleh dibuka jika riwayat balita berasal dari
| Puskesmas yang sama dengan akun Petugas KIA.
|
*/

$stmtPuskesmasAkun = mysqli_prepare(
    $conn,
    "SELECT
        u.id_puskesmas,
        p.nama_puskesmas
     FROM pengguna AS u
     LEFT JOIN puskesmas AS p
        ON u.id_puskesmas = p.id_puskesmas
     WHERE u.id_user = ?
     AND u.role = 'petugas_kia'
     LIMIT 1"
);

if (!$stmtPuskesmasAkun) {
    die(
        "Gagal memeriksa Puskesmas Petugas KIA: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPuskesmasAkun,
    "i",
    $idUserAktif
);

mysqli_stmt_execute(
    $stmtPuskesmasAkun
);

$hasilPuskesmasAkun =
    mysqli_stmt_get_result(
        $stmtPuskesmasAkun
    );

$dataPuskesmasAkun =
    mysqli_fetch_assoc(
        $hasilPuskesmasAkun
    );

mysqli_stmt_close(
    $stmtPuskesmasAkun
);

if (
    !$dataPuskesmasAkun
    || empty(
        $dataPuskesmasAkun["id_puskesmas"]
    )
) {
    header(
        "Location: riwayat_kelahiran.php?pesan=puskesmas_belum_terhubung"
    );
    exit;
}

$idPuskesmasAktif =
    (int) $dataPuskesmasAkun[
        "id_puskesmas"
    ];

$namaPuskesmasAktif =
    trim(
        (string) (
            $dataPuskesmasAkun[
                "nama_puskesmas"
            ]
            ?? ""
        )
    );

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
    AND b.id_puskesmas = ?
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
    "ii",
    $idRiwayat,
    $idPuskesmasAktif
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

        <div class="card content-card">

            <div
                class="card-header
                d-flex
                flex-wrap
                justify-content-between
                align-items-center
                gap-3"
            >

                <div>

                    <h4 class="mb-1">
                        <i class="bi bi-balloon-heart me-2"></i>
                        Detail Riwayat Kelahiran
                    </h4>

                    <small class="text-muted">
                        <?= amanDetailKelahiran(
                            $data["nama_balita"]
                        ); ?>
                        · NIK
                        <?= amanDetailKelahiran(
                            $data["nik_balita"]
                        ); ?>
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <span
                        class="badge badge-info
                        d-inline-flex
                        align-items-center
                        px-3"
                    >
                        <i class="bi bi-hospital me-1"></i>
                        <?= amanDetailKelahiran(
                            $data["nama_puskesmas"]
                        ); ?>
                    </span>

                    <a
                        href="edit_kelahiran.php?id=<?= $idRiwayat; ?>"
                        class="btn btn-warning btn-sm"
                    >
                        <i class="bi bi-pencil-square"></i>
                        Edit
                    </a>

                    <a
                        href="riwayat_kelahiran.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

            </div>

            <div class="card-body">

                <div class="row g-4">

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <div class="mb-3">

                                <span class="badge badge-primary">
                                    <i class="bi bi-person-vcard"></i>
                                    Identitas Balita
                                </span>

                            </div>

                            <div class="detail-grid">

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Nama Balita
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["nama_balita"]
                                        ); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        NIK
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["nik_balita"]
                                        ); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Jenis Kelamin
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["jenis_kelamin"]
                                        ); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Tanggal Lahir
                                    </span>
                                    <span class="detail-value">
                                        <?= formatTanggalKelahiran(
                                            $data["tanggal_lahir"]
                                        ); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Nama Ibu
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["nama_ibu"]
                                        ); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Posyandu
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["nama_posyandu"]
                                        ); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Puskesmas
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["nama_puskesmas"]
                                        ); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Alamat
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["alamat"]
                                        ); ?>
                                    </span>
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <div class="mb-3">

                                <span class="badge badge-info">
                                    <i class="bi bi-heart-pulse"></i>
                                    Data Kelahiran
                                </span>

                            </div>

                            <div class="detail-grid">

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Berat Lahir
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["berat_lahir"]
                                            ?? null
                                        ); ?>
                                        <?= isset(
                                            $data["berat_lahir"]
                                        )
                                            && $data["berat_lahir"] !== null
                                            && $data["berat_lahir"] !== ""
                                            ? " kg"
                                            : ""; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Panjang Lahir
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["panjang_lahir"]
                                            ?? null
                                        ); ?>
                                        <?= isset(
                                            $data["panjang_lahir"]
                                        )
                                            && $data["panjang_lahir"] !== null
                                            && $data["panjang_lahir"] !== ""
                                            ? " cm"
                                            : ""; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Usia Kehamilan
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["usia_kehamilan"]
                                            ?? null
                                        ); ?>
                                        <?= isset(
                                            $data["usia_kehamilan"]
                                        )
                                            && $data["usia_kehamilan"] !== null
                                            && $data["usia_kehamilan"] !== ""
                                            ? " minggu"
                                            : ""; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Jenis Persalinan
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["jenis_persalinan"]
                                            ?? null
                                        ); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Usia Ibu Saat Melahirkan
                                    </span>
                                    <span class="detail-value">
                                        <?= amanDetailKelahiran(
                                            $data["usia_ibu_melahirkan"]
                                            ?? null
                                        ); ?>
                                        <?= isset(
                                            $data["usia_ibu_melahirkan"]
                                        )
                                            && $data["usia_ibu_melahirkan"] !== null
                                            && $data["usia_ibu_melahirkan"] !== ""
                                            ? " tahun"
                                            : ""; ?>
                                    </span>
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Riwayat Kehamilan
                            </span>

                            <div class="detail-value mt-2">
                                <?= nl2br(
                                    amanDetailKelahiran(
                                        $data["riwayat_kehamilan"]
                                        ?? null
                                    )
                                ); ?>
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-6">

                        <div class="detail-item h-100">

                            <span class="detail-label">
                                Komplikasi Kehamilan
                            </span>

                            <div class="detail-value mt-2">
                                <?= nl2br(
                                    amanDetailKelahiran(
                                        $data["komplikasi_kehamilan"]
                                        ?? null
                                    )
                                ); ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>