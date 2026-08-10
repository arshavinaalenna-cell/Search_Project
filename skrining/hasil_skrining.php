<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole([
    "kader",
    "petugas_gizi",
    "petugas_kia",
    "kepala_puskesmas",
    "dinkes"
]);

/*
|--------------------------------------------------------------------------
| Judul halaman
|--------------------------------------------------------------------------
*/

$judulHalaman =
    "Data Skrining Awal | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Role aktif
|--------------------------------------------------------------------------
*/

$roleAktif = $_SESSION["role"] ?? "";

/*
|--------------------------------------------------------------------------
| Flash data skrining baru
|--------------------------------------------------------------------------
|
| Dikirim dari form_skrining.php setelah INSERT berhasil.
| Modal hanya tampil satu kali.
|
*/

$skriningBaru = $_SESSION["skrining_baru"] ?? null;

if ($skriningBaru !== null) {
    unset($_SESSION["skrining_baru"]);
}

/*
|--------------------------------------------------------------------------
| Fungsi bantuan
|--------------------------------------------------------------------------
*/

function amanSkrining($nilai): string
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

function teksStatusStunting($status): string
{
    if (
        $status === null
        || trim((string) $status) === ""
    ) {
        return "Belum dianalisis";
    }

    return (string) $status;
}

function kelasStatusStunting($status): string
{
    if (
        $status === null
        || trim((string) $status) === ""
    ) {
        return "text-bg-secondary";
    }

    $statusNormal = strtolower(
        trim((string) $status)
    );

    /*
    |----------------------------------------------------------------------
    | Urutan pengecekan penting.
    | "Tidak Stunting" dicek lebih dulu karena tetap mengandung
    | kata "stunting".
    |----------------------------------------------------------------------
    */

    if (
        strpos($statusNormal, "tidak stunting") !== false
        || strpos($statusNormal, "normal") !== false
    ) {
        return "text-bg-success";
    }

    if (
        strpos($statusNormal, "berisiko") !== false
        || strpos($statusNormal, "berisiko") !== false
        || strpos($statusNormal, "risiko") !== false
    ) {
        return "text-bg-warning";
    }

    if (
        strpos($statusNormal, "stunting") !== false
    ) {
        return "text-bg-danger";
    }

    return "text-bg-info";
}

function teksStatusGizi($status): string
{
    if (
        $status === null
        || trim((string) $status) === ""
    ) {
        return "Belum tersedia";
    }

    return (string) $status;
}

function kelasStatusGizi($status): string
{
    if (
        $status === null
        || trim((string) $status) === ""
    ) {
        return "text-bg-secondary";
    }

    $statusNormal = strtolower(
        trim((string) $status)
    );

    if (
        strpos($statusNormal, "normal") !== false
        || strpos($statusNormal, "baik") !== false
    ) {
        return "text-bg-success";
    }

    if (
        strpos($statusNormal, "risiko") !== false
        || strpos($statusNormal, "kurang") !== false
    ) {
        return "text-bg-warning";
    }

    if (
        strpos($statusNormal, "buruk") !== false
        || strpos($statusNormal, "sangat") !== false
    ) {
        return "text-bg-danger";
    }

    return "text-bg-info";
}

function teksStatusVerifikasi($status): string
{
    if (
        $status === null
        || trim((string) $status) === ""
    ) {
        return "Belum diverifikasi";
    }

    return (string) $status;
}

function kelasStatusVerifikasi($status): string
{
    if (
        $status === null
        || trim((string) $status) === ""
    ) {
        return "text-bg-secondary";
    }

    $statusNormal = strtolower(
        trim((string) $status)
    );

    if (
        strpos(
            $statusNormal,
            "sudah diverifikasi"
        ) !== false
    ) {
        return "text-bg-success";
    }

    if (
        strpos(
            $statusNormal,
            "pemeriksaan ulang"
        ) !== false
    ) {
        return "text-bg-warning";
    }

    return "text-bg-secondary";
}

/*
|--------------------------------------------------------------------------
| Pengaturan hak akses
|--------------------------------------------------------------------------
*/

$bolehTambahSkrining =
    $roleAktif === "kader";

$bolehEditSkrining =
    $roleAktif === "kader";

$bolehAnalisisSkrining =
    $roleAktif === "petugas_gizi";

$bolehLihatDetail =
    in_array(
        $roleAktif,
        [
            "kader",
            "petugas_gizi",
            "petugas_kia",
            "kepala_puskesmas",
            "dinkes"
        ],
        true
    );

$punyaAksiSkrining =
    $bolehLihatDetail
    || $bolehEditSkrining
    || $bolehAnalisisSkrining;

/*
|--------------------------------------------------------------------------
| Filter Puskesmas khusus Kader
|--------------------------------------------------------------------------
*/

$filterPuskesmas =
    $roleAktif === "kader"
        ? max(
            0,
            (int) ($_GET["puskesmas"] ?? 0)
        )
        : 0;

$daftarPuskesmas = [];

if ($roleAktif === "kader") {

    $queryPuskesmas = mysqli_query(
        $conn,
        "
        SELECT
            id_puskesmas,
            nama_puskesmas
        FROM puskesmas
        ORDER BY nama_puskesmas ASC
        "
    );

    if ($queryPuskesmas) {

        while (
            $puskesmas =
                mysqli_fetch_assoc($queryPuskesmas)
        ) {
            $daftarPuskesmas[] = $puskesmas;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Query data skrining
|--------------------------------------------------------------------------
|
| Status gizi, status stunting dan status verifikasi TIDAK disimpan
| ulang di skrining_awal.
|
| Semuanya diambil dari hasil_deteksi terbaru milik balita.
|
| Relasi:
|
| balita
|   ↓
| pengukuran_antropometri
|   ↓
| hasil_deteksi
|
|--------------------------------------------------------------------------
*/

$sqlDasar = "
    SELECT
        s.id_skrining,
        s.id_balita,
        s.tinggi_badan_ibu,
        s.pendidikan_ibu,
        s.pekerjaan_ibu,

        b.nama_balita,
        b.id_puskesmas,

        ps.nama_puskesmas,

        det.id_deteksi,
        det.status_gizi,
        det.status_stunting,
        det.tanggal_deteksi,
        det.status_verifikasi,
        det.catatan_verifikasi,
        det.diverifikasi_oleh,
        det.tanggal_verifikasi

    FROM skrining_awal AS s

    INNER JOIN balita AS b
        ON b.id_balita = s.id_balita

    LEFT JOIN puskesmas AS ps
        ON ps.id_puskesmas = b.id_puskesmas

    LEFT JOIN (

        SELECT
            hd.id_deteksi,
            pa.id_balita,
            hd.status_gizi,
            hd.status_stunting,
            hd.tanggal_deteksi,
            hd.status_verifikasi,
            hd.catatan_verifikasi,
            hd.diverifikasi_oleh,
            hd.tanggal_verifikasi

        FROM hasil_deteksi AS hd

        INNER JOIN pengukuran_antropometri AS pa
            ON pa.id_pengukuran =
                hd.id_pengukuran

        INNER JOIN (

            SELECT
                pa2.id_balita,
                MAX(hd2.id_deteksi)
                    AS id_deteksi_terbaru

            FROM hasil_deteksi AS hd2

            INNER JOIN
                pengukuran_antropometri AS pa2

                ON pa2.id_pengukuran =
                    hd2.id_pengukuran

            GROUP BY
                pa2.id_balita

        ) AS terbaru

            ON terbaru.id_deteksi_terbaru =
                hd.id_deteksi

    ) AS det

        ON det.id_balita =
            b.id_balita
";

/*
|--------------------------------------------------------------------------
| Jalankan query
|--------------------------------------------------------------------------
*/

if (
    $roleAktif === "kader"
    && $filterPuskesmas > 0
) {

    $sql = $sqlDasar . "
        WHERE b.id_puskesmas = ?
        ORDER BY s.id_skrining DESC
    ";

    $stmtSkrining =
        mysqli_prepare(
            $conn,
            $sql
        );

    if (!$stmtSkrining) {

        die(
            "Gagal menyiapkan data skrining: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtSkrining,
        "i",
        $filterPuskesmas
    );

    if (
        !mysqli_stmt_execute(
            $stmtSkrining
        )
    ) {

        die(
            "Gagal mengambil data skrining: "
            . mysqli_stmt_error(
                $stmtSkrining
            )
        );
    }

    $query =
        mysqli_stmt_get_result(
            $stmtSkrining
        );

    if (!$query) {

        die(
            "Gagal membaca hasil skrining."
        );
    }

} else {

    $sql = $sqlDasar . "
        ORDER BY s.id_skrining DESC
    ";

    $query =
        mysqli_query(
            $conn,
            $sql
        );

    if (!$query) {

        die(
            "Gagal mengambil data skrining: "
            . mysqli_error($conn)
        );
    }
}

$totalData =
    mysqli_num_rows($query);

/*
|--------------------------------------------------------------------------
| Ambil hasil deteksi terbaru untuk modal skrining baru
|--------------------------------------------------------------------------
*/

$popupIdBalita = 0;
$deteksiPopup = null;

if (is_array($skriningBaru)) {

    $popupIdBalita =
        (int) (
            $skriningBaru["id_balita"]
            ?? 0
        );
}

if ($popupIdBalita > 0) {

    $stmtPopup =
        mysqli_prepare(
            $conn,
            "
            SELECT
                hd.id_deteksi,
                hd.status_gizi,
                hd.status_stunting,
                hd.status_verifikasi,
                hd.tanggal_deteksi

            FROM hasil_deteksi AS hd

            INNER JOIN
                pengukuran_antropometri AS pa

                ON pa.id_pengukuran =
                    hd.id_pengukuran

            WHERE pa.id_balita = ?

            ORDER BY
                hd.id_deteksi DESC

            LIMIT 1
            "
        );

    if ($stmtPopup) {

        mysqli_stmt_bind_param(
            $stmtPopup,
            "i",
            $popupIdBalita
        );

        mysqli_stmt_execute(
            $stmtPopup
        );

        $hasilPopup =
            mysqli_stmt_get_result(
                $stmtPopup
            );

        if (
            $hasilPopup
            && mysqli_num_rows(
                $hasilPopup
            ) > 0
        ) {

            $deteksiPopup =
                mysqli_fetch_assoc(
                    $hasilPopup
                );
        }

        mysqli_stmt_close(
            $stmtPopup
        );
    }
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
            "Data skrining berhasil ditambahkan.";

        break;

    case "edit_berhasil":

        $jenisAlert = "success";

        $isiPesan =
            "Data skrining berhasil diperbarui.";

        break;

    case "hapus_berhasil":

        $jenisAlert = "success";

        $isiPesan =
            "Data skrining berhasil dihapus.";

        break;

    case "gagal_hapus":

        $jenisAlert = "danger";

        $isiPesan =
            "Data skrining gagal dihapus.";

        break;

    case "tidak_ditemukan":

        $jenisAlert = "warning";

        $isiPesan =
            "Data skrining tidak ditemukan.";

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

    <?php
    require_once "../includes/sidebar.php";
    ?>

    <main class="main-content">

        <!-- =========================================================
             CARD UTAMA
        ========================================================== -->

        <div class="card content-card">

            <!-- Header card -->
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

                        <i
                            class="bi
                            bi-clipboard2-heart
                            me-2"
                        ></i>

                        Data Skrining Awal

                    </h4>

                    <small class="text-muted">

                        <?php
                        if (
                            $roleAktif
                            === "kader"
                        ):
                        ?>

                            Kelola skrining awal
                            dan faktor risiko balita.

                        <?php
                        elseif (
                            $roleAktif
                            === "petugas_gizi"
                        ):
                        ?>

                            Tinjau skrining dan
                            lanjutkan analisis
                            status stunting.

                        <?php
                        elseif (
                            $roleAktif
                            === "petugas_kia"
                        ):
                        ?>

                            Tinjau skrining,
                            status gizi dan
                            status stunting balita.

                        <?php
                        else:
                        ?>

                            Monitoring data
                            skrining dan hasil
                            deteksi balita.

                        <?php endif; ?>

                    </small>

                </div>

                <div
                    class="d-flex
                    flex-wrap
                    gap-2"
                >

                    <a
                        href="../dashboard/dashboard.php"
                        class="btn btn-secondary btn-sm"
                    >

                        <i
                            class="bi
                            bi-arrow-left"
                        ></i>

                        Kembali

                    </a>

                    <?php
                    if (
                        $bolehTambahSkrining
                    ):
                    ?>

                        <a
                            href="form_skrining.php"
                            class="btn btn-primary btn-sm"
                        >

                            <i
                                class="bi
                                bi-plus-circle"
                            ></i>

                            Tambah Skrining

                        </a>

                    <?php endif; ?>

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

                <!-- =================================================
                     INFORMASI DAFTAR
                ================================================== -->

                <div
                    class="d-flex
                    flex-wrap
                    justify-content-between
                    align-items-center
                    gap-2
                    mb-4"
                >

                    <div>

                        <h5 class="mb-1">
                            Daftar Skrining Balita
                        </h5>

                        <small class="text-muted">

                            Total
                            <strong>
                                <?= $totalData; ?>
                            </strong>
                            data skrining.

                        </small>

                    </div>

                    <div>

                        <?php
                        if (
                            $roleAktif
                            === "kader"
                        ):
                        ?>

                            <span
                                class="badge
                                badge-info"
                            >

                                <i
                                    class="bi
                                    bi-pencil-square"
                                ></i>

                                Input Kader

                            </span>

                        <?php
                        elseif (
                            $roleAktif
                            === "petugas_gizi"
                        ):
                        ?>

                            <span
                                class="badge
                                badge-info"
                            >

                                <i
                                    class="bi
                                    bi-search-heart"
                                ></i>

                                Analisis Gizi

                            </span>

                        <?php
                        else:
                        ?>

                            <span
                                class="badge
                                badge-info"
                            >

                                <i
                                    class="bi
                                    bi-eye"
                                ></i>

                                Monitoring

                            </span>

                        <?php endif; ?>

                    </div>

                </div>

                <!-- =================================================
                     FILTER PUSKESMAS
                ================================================== -->

                <?php
                if (
                    $roleAktif
                    === "kader"
                ):
                ?>

                    <form
                        method="GET"
                        class="row
                        g-2
                        mb-4
                        align-items-end"
                    >

                        <div
                            class="col-12
                            col-lg-8"
                        >

                            <label
                                for="filter_puskesmas"
                                class="form-label
                                small
                                text-muted
                                mb-1"
                            >
                                Filter berdasarkan
                                Puskesmas
                            </label>

                            <select
                                id="filter_puskesmas"
                                name="puskesmas"
                                class="form-select"
                            >

                                <option value="0">
                                    Semua Puskesmas
                                </option>

                                <?php
                                foreach (
                                    $daftarPuskesmas
                                    as $puskesmas
                                ):
                                ?>

                                    <option
                                        value="<?= (int) $puskesmas[
                                            "id_puskesmas"
                                        ]; ?>"
                                        <?= (
                                            $filterPuskesmas
                                            ===
                                            (int) $puskesmas[
                                                "id_puskesmas"
                                            ]
                                        )
                                            ? "selected"
                                            : ""; ?>
                                    >

                                        <?= htmlspecialchars(
                                            $puskesmas[
                                                "nama_puskesmas"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div
                            class="col-6
                            col-lg-2"
                        >

                            <button
                                type="submit"
                                class="btn
                                btn-primary
                                w-100"
                            >

                                <i
                                    class="bi
                                    bi-funnel"
                                ></i>

                                Filter

                            </button>

                        </div>

                        <div
                            class="col-6
                            col-lg-2"
                        >

                            <a
                                href="hasil_skrining.php"
                                class="btn
                                btn-light
                                w-100"
                            >

                                <i
                                    class="bi
                                    bi-x-circle"
                                ></i>

                                Reset

                            </a>

                        </div>

                    </form>

                <?php endif; ?>

                <!-- =================================================
                     TABEL SKRINING
                ================================================== -->

                <div class="table-responsive">

                    <table
                        class="table
                        table-hover
                        align-middle"
                    >

                        <thead>

                            <tr>

                                <th
                                    class="text-center"
                                    style="width: 60px;"
                                >
                                    No
                                </th>

                                <th>
                                    Balita
                                </th>

                                <th>
                                    Puskesmas
                                </th>

                                <th class="text-center">
                                    Tinggi Ibu
                                </th>

                                <th>
                                    Pendidikan
                                </th>

                                <th>
                                    Pekerjaan
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

                                <?php
                                if (
                                    $punyaAksiSkrining
                                ):
                                ?>

                                    <th class="text-center">
                                        Aksi
                                    </th>

                                <?php endif; ?>

                            </tr>

                        </thead>

                        <tbody>

                        <?php
                        if (
                            $totalData > 0
                        ):
                        ?>

                            <?php

                            $nomor = 1;

                            while (
                                $data =
                                    mysqli_fetch_assoc(
                                        $query
                                    )
                            ):

                                $idSkrining =
                                    (int) $data[
                                        "id_skrining"
                                    ];

                                $idBalita =
                                    (int) $data[
                                        "id_balita"
                                    ];

                                $idDeteksi =
                                    (int) (
                                        $data[
                                            "id_deteksi"
                                        ]
                                        ?? 0
                                    );

                                $statusGizi =
                                    teksStatusGizi(
                                        $data[
                                            "status_gizi"
                                        ]
                                        ?? null
                                    );

                                $statusStunting =
                                    teksStatusStunting(
                                        $data[
                                            "status_stunting"
                                        ]
                                        ?? null
                                    );

                                $statusVerifikasi =
                                    teksStatusVerifikasi(
                                        $data[
                                            "status_verifikasi"
                                        ]
                                        ?? null
                                    );

                            ?>

                                <tr>

                                    <td
                                        class="text-center"
                                    >
                                        <?= $nomor++; ?>
                                    </td>

                                    <!-- BALITA -->
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

                                                <strong>

                                                    <?= amanSkrining(
                                                        $data[
                                                            "nama_balita"
                                                        ]
                                                    ); ?>

                                                </strong>

                                            </div>

                                        </div>

                                    </td>

                                    <!-- PUSKESMAS -->
                                    <td>

                                        <?= amanSkrining(
                                            $data[
                                                "nama_puskesmas"
                                            ]
                                            ?? null
                                        ); ?>

                                    </td>

                                    <!-- TINGGI IBU -->
                                    <td
                                        class="text-center"
                                    >

                                        <?php
                                        if (
                                            $data[
                                                "tinggi_badan_ibu"
                                            ] !== null
                                            &&
                                            $data[
                                                "tinggi_badan_ibu"
                                            ] !== ""
                                        ):
                                        ?>

                                            <?= amanSkrining(
                                                $data[
                                                    "tinggi_badan_ibu"
                                                ]
                                            ); ?>
                                            cm

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>

                                    <!-- PENDIDIKAN -->
                                    <td>

                                        <?= amanSkrining(
                                            $data[
                                                "pendidikan_ibu"
                                            ]
                                        ); ?>

                                    </td>

                                    <!-- PEKERJAAN -->
                                    <td>

                                        <?= amanSkrining(
                                            $data[
                                                "pekerjaan_ibu"
                                            ]
                                        ); ?>

                                    </td>

                                    <!-- STATUS GIZI -->
                                    <td
                                        class="text-center"
                                    >

                                        <span
                                            class="badge
                                            rounded-pill
                                            <?= kelasStatusGizi(
                                                $data[
                                                    "status_gizi"
                                                ]
                                                ?? null
                                            ); ?>"
                                        >

                                            <?= amanSkrining(
                                                $statusGizi
                                            ); ?>

                                        </span>

                                    </td>

                                    <!-- STATUS STUNTING -->
                                    <td
                                        class="text-center"
                                    >

                                        <span
                                            class="badge
                                            rounded-pill
                                            <?= kelasStatusStunting(
                                                $data[
                                                    "status_stunting"
                                                ]
                                                ?? null
                                            ); ?>"
                                        >

                                            <?= amanSkrining(
                                                $statusStunting
                                            ); ?>

                                        </span>

                                    </td>

                                    <!-- STATUS VERIFIKASI -->
                                    <td
                                        class="text-center"
                                    >

                                        <span
                                            class="badge
                                            rounded-pill
                                            <?= kelasStatusVerifikasi(
                                                $data[
                                                    "status_verifikasi"
                                                ]
                                                ?? null
                                            ); ?>"
                                        >

                                            <?= amanSkrining(
                                                $statusVerifikasi
                                            ); ?>

                                        </span>

                                    </td>

                                    <!-- AKSI -->
                                    <?php
                                    if (
                                        $punyaAksiSkrining
                                    ):
                                    ?>

                                        <td>

                                            <div
                                                class="table-actions
                                                justify-content-center
                                                d-flex
                                                flex-wrap
                                                gap-1"
                                            >

                                                <?php
                                                if (
                                                    $bolehLihatDetail
                                                ):
                                                ?>

                                                    <a
                                                        href="detail_skrining.php?id=<?= $idSkrining; ?>"
                                                        class="btn
                                                        btn-info
                                                        btn-sm"
                                                        title="Lihat detail skrining"
                                                    >

                                                        <i
                                                            class="bi
                                                            bi-eye"
                                                        ></i>

                                                        Detail

                                                    </a>

                                                <?php endif; ?>

                                                <?php
                                                if (
                                                    $bolehAnalisisSkrining
                                                ):
                                                ?>

                                                    <a
                                                        href="../deteksi/analisis_deteksi.php?id_balita=<?= $idBalita; ?>"
                                                        class="btn
                                                        btn-primary
                                                        btn-sm"
                                                        title="Analisis status stunting"
                                                    >

                                                        <i
                                                            class="bi
                                                            bi-search-heart"
                                                        ></i>

                                                        <?= $idDeteksi > 0
                                                            ? "Analisis Ulang"
                                                            : "Analisis"; ?>

                                                    </a>

                                                <?php endif; ?>

                                                <?php
                                                if (
                                                    $bolehEditSkrining
                                                ):
                                                ?>

                                                    <a
                                                        href="edit_skrining.php?id=<?= $idSkrining; ?>"
                                                        class="btn
                                                        btn-warning
                                                        btn-sm"
                                                        title="Edit skrining"
                                                    >

                                                        <i
                                                            class="bi
                                                            bi-pencil-square"
                                                        ></i>

                                                        Edit

                                                    </a>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    <?php endif; ?>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="<?= $punyaAksiSkrining
                                        ? "10"
                                        : "9"; ?>"
                                >

                                    <div
                                        class="empty-state"
                                    >

                                        <div
                                            class="empty-state-icon"
                                        >

                                            <i
                                                class="bi
                                                bi-clipboard2-heart"
                                            ></i>

                                        </div>

                                        <h3>
                                            Belum ada data
                                            skrining
                                        </h3>

                                        <p>

                                            <?= $bolehTambahSkrining
                                                ? "Tambahkan skrining awal untuk mulai mencatat faktor risiko balita."
                                                : "Data skrining awal balita belum tersedia."; ?>

                                        </p>

                                        <?php
                                        if (
                                            $bolehTambahSkrining
                                        ):
                                        ?>

                                            <a
                                                href="form_skrining.php"
                                                class="btn
                                                btn-primary
                                                mt-3"
                                            >

                                                <i
                                                    class="bi
                                                    bi-plus-circle"
                                                ></i>

                                                Tambah Skrining

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

<!-- =============================================================
     MODAL SETELAH SKRINING BARU DISIMPAN
============================================================== -->

<?php if (is_array($skriningBaru)): ?>

    <?php

    $popupNamaBalita =
        amanSkrining(
            $skriningBaru[
                "nama_balita"
            ]
            ?? "Balita"
        );

    ?>

    <div
        class="modal fade"
        id="modalSkriningBerhasil"
        tabindex="-1"
        aria-labelledby="modalSkriningBerhasilLabel"
        aria-hidden="true"
        data-bs-backdrop="static"
    >

        <div
            class="modal-dialog
            modal-dialog-centered"
        >

            <div class="modal-content">

                <div class="modal-header">

                    <div>

                        <h5
                            class="modal-title"
                            id="modalSkriningBerhasilLabel"
                        >

                            <i
                                class="bi
                                bi-clipboard2-check
                                me-2"
                            ></i>

                            Skrining Berhasil
                            Disimpan

                        </h5>

                        <small class="text-muted">
                            Ringkasan status balita
                            setelah data skrining
                            disimpan.
                        </small>

                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup"
                    ></button>

                </div>

                <div class="modal-body">

                    <div
                        class="text-center
                        mb-4"
                    >

                        <div
                            class="empty-state-icon
                            mx-auto
                            mb-2"
                        >

                            <i
                                class="bi
                                bi-person-heart"
                            ></i>

                        </div>

                        <h4 class="mb-1">

                            <?= $popupNamaBalita; ?>

                        </h4>

                        <p
                            class="text-muted
                            mb-0"
                        >

                            Data skrining awal
                            berhasil direkam.

                        </p>

                    </div>

                    <?php
                    if (
                        is_array(
                            $deteksiPopup
                        )
                    ):
                    ?>

                        <div
                            class="table-responsive"
                        >

                            <table
                                class="table
                                table-bordered
                                align-middle
                                mb-0"
                            >

                                <tbody>

                                    <tr>

                                        <th width="46%">
                                            Status Gizi
                                        </th>

                                        <td>

                                            <span
                                                class="badge
                                                rounded-pill
                                                <?= kelasStatusGizi(
                                                    $deteksiPopup[
                                                        "status_gizi"
                                                    ]
                                                    ?? null
                                                ); ?>"
                                            >

                                                <?= amanSkrining(
                                                    teksStatusGizi(
                                                        $deteksiPopup[
                                                            "status_gizi"
                                                        ]
                                                        ?? null
                                                    )
                                                ); ?>

                                            </span>

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>
                                            Status Stunting
                                        </th>

                                        <td>

                                            <span
                                                class="badge
                                                rounded-pill
                                                <?= kelasStatusStunting(
                                                    $deteksiPopup[
                                                        "status_stunting"
                                                    ]
                                                    ?? null
                                                ); ?>"
                                            >

                                                <?= amanSkrining(
                                                    teksStatusStunting(
                                                        $deteksiPopup[
                                                            "status_stunting"
                                                        ]
                                                        ?? null
                                                    )
                                                ); ?>

                                            </span>

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>
                                            Status Verifikasi
                                        </th>

                                        <td>

                                            <span
                                                class="badge
                                                rounded-pill
                                                <?= kelasStatusVerifikasi(
                                                    $deteksiPopup[
                                                        "status_verifikasi"
                                                    ]
                                                    ?? null
                                                ); ?>"
                                            >

                                                <?= amanSkrining(
                                                    teksStatusVerifikasi(
                                                        $deteksiPopup[
                                                            "status_verifikasi"
                                                        ]
                                                        ?? null
                                                    )
                                                ); ?>

                                            </span>

                                        </td>

                                    </tr>

                                    <?php
                                    if (
                                        !empty(
                                            $deteksiPopup[
                                                "tanggal_deteksi"
                                            ]
                                        )
                                    ):
                                    ?>

                                        <tr>

                                            <th>
                                                Deteksi Terakhir
                                            </th>

                                            <td>

                                                <?= amanSkrining(
                                                    $deteksiPopup[
                                                        "tanggal_deteksi"
                                                    ]
                                                ); ?>

                                            </td>

                                        </tr>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                        <div
                            class="alert
                            alert-info
                            mt-3
                            mb-0"
                        >

                            <i
                                class="bi
                                bi-info-circle
                                me-1"
                            ></i>

                            Status di atas merupakan
                            hasil deteksi terbaru yang
                            tercatat untuk balita ini.

                        </div>

                    <?php else: ?>

                        <div
                            class="alert
                            alert-warning
                            mb-0"
                        >

                            <div
                                class="d-flex
                                gap-2"
                            >

                                <i
                                    class="bi
                                    bi-exclamation-circle"
                                ></i>

                                <div>

                                    <strong>
                                        Belum ada hasil
                                        deteksi.
                                    </strong>

                                    <div class="small mt-1">

                                        Data skrining sudah
                                        tersimpan, tetapi
                                        status stunting belum
                                        dianalisis oleh
                                        Petugas Gizi.

                                    </div>

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >

                        <i
                            class="bi
                            bi-table"
                        ></i>

                        Lihat Daftar

                    </button>

                    <?php
                    if (
                        $popupIdBalita > 0
                    ):
                    ?>

                        <a
                            href="detail_skrining.php?id=<?= (int) (
                                $skriningBaru[
                                    "id_skrining"
                                ]
                                ?? 0
                            ); ?>"
                            class="btn btn-info"
                        >

                            <i
                                class="bi
                                bi-eye"
                            ></i>

                            Detail Skrining

                        </a>

                    <?php endif; ?>

                    <?php
                    if (
                        $bolehAnalisisSkrining
                        && $popupIdBalita > 0
                    ):
                    ?>

                        <a
                            href="../deteksi/analisis_deteksi.php?id_balita=<?= $popupIdBalita; ?>"
                            class="btn btn-primary"
                        >

                            <i
                                class="bi
                                bi-search-heart"
                            ></i>

                            Lanjut Analisis

                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

    <script>
        window.addEventListener(
            "load",
            function () {

                const elemenModal =
                    document.getElementById(
                        "modalSkriningBerhasil"
                    );

                if (
                    elemenModal
                    && typeof bootstrap
                        !== "undefined"
                ) {

                    const modalSkrining =
                        new bootstrap.Modal(
                            elemenModal
                        );

                    modalSkrining.show();
                }
            }
        );
    </script>

<?php endif; ?>

<?php
require_once "../includes/footer.php";
?>