<?php

require_once "../auth/session.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Judul halaman
|--------------------------------------------------------------------------
*/

$judulHalaman =
    "Data Skrining Awal | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Mengambil data skrining beserta nama balita
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.id_skrining,
        s.id_balita,
        s.tinggi_badan_ibu,
        s.pendidikan_ibu,
        s.pekerjaan_ibu,
        s.lama_asi_eksklusif,
        s.mpasi,
        s.frekuensi_makan,
        s.protein_hewani,
        s.status_ekonomi,
        s.sanitasi,
        s.air_bersih,
        b.nama_balita
    FROM skrining_awal AS s
    INNER JOIN balita AS b
        ON s.id_balita = b.id_balita
    ORDER BY s.id_skrining DESC
";

$query = mysqli_query($conn, $sql);

if (!$query) {
    die(
        "Gagal mengambil data skrining: "
        . mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
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
| Memanggil template aplikasi
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <!-- Header halaman -->
        <div class="page-header">

            <div>

                <h1 class="page-title">

                    <i class="bi bi-clipboard2-heart me-2"></i>

                    Data Skrining Awal

                </h1>

                <p class="page-subtitle">

                    Kelola data faktor risiko dan riwayat awal
                    balita sebagai bagian dari proses deteksi stunting.

                </p>

            </div>

            <div class="d-flex flex-wrap gap-2">

                <a
                    href="../dashboard/dashboard.php"
                    class="btn btn-secondary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Kembali ke Dashboard

                </a>

                <a
                    href="form_skrining.php"
                    class="btn btn-primary"
                >

                    <i class="bi bi-plus-circle"></i>

                    Tambah Skrining

                </a>

            </div>

        </div>

        <!-- Pesan -->
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

        <!-- Card skrining -->
        <div class="card content-card">

            <div class="card-header">

                <div>

                    <h4 class="mb-1">
                        Daftar Skrining Balita
                    </h4>

                    <small class="text-muted">

                        Total data:

                        <?= mysqli_num_rows($query); ?>

                        skrining

                    </small>

                </div>

                <span class="badge badge-info">

                    <i class="bi bi-person-hearts"></i>

                    Skrining Awal

                </span>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th class="text-center">
                                    No
                                </th>

                                <th>
                                    Nama Balita
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
                                    ASI
                                </th>

                                <th class="text-center">
                                    MPASI
                                </th>

                                <th class="text-center">
                                    Frekuensi Makan
                                </th>

                                <th class="text-center">
                                    Protein Hewani
                                </th>

                                <th class="text-center">
                                    Status Ekonomi
                                </th>

                                <th class="text-center">
                                    Sanitasi
                                </th>

                                <th class="text-center">
                                    Air Bersih
                                </th>

                                <th class="text-center">
                                    Aksi
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (mysqli_num_rows($query) > 0): ?>

                            <?php

                            $nomor = 1;

                            while (
                                $data = mysqli_fetch_assoc($query)
                            ):

                                $idSkrining =
                                    (int) $data["id_skrining"];

                                $idBalita =
                                    (int) $data["id_balita"];

                            ?>

                                <tr>

                                    <td class="text-center">

                                        <?= $nomor++; ?>

                                    </td>

                                    <td>

                                        <div
                                            class="d-flex
                                            align-items-center
                                            gap-2"
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

                                                <?= amanSkrining(
                                                    $data["nama_balita"]
                                                ); ?>

                                            </strong>

                                        </div>

                                    </td>

                                    <td class="text-center">

                                        <?= amanSkrining(
                                            $data["tinggi_badan_ibu"]
                                        ); ?>

                                        cm

                                    </td>

                                    <td>

                                        <?= amanSkrining(
                                            $data["pendidikan_ibu"]
                                        ); ?>

                                    </td>

                                    <td>

                                        <?= amanSkrining(
                                            $data["pekerjaan_ibu"]
                                        ); ?>

                                    </td>

                                    <td class="text-center">

                                        <?= amanSkrining(
                                            $data["lama_asi_eksklusif"]
                                        ); ?>

                                        bulan

                                    </td>

                                    <td class="text-center">

                                        <?= amanSkrining(
                                            $data["mpasi"]
                                        ); ?>

                                    </td>

                                    <td class="text-center">

                                        <?= amanSkrining(
                                            $data["frekuensi_makan"]
                                        ); ?>

                                    </td>

                                    <td class="text-center">

                                        <?= amanSkrining(
                                            $data["protein_hewani"]
                                        ); ?>

                                    </td>

                                    <td class="text-center">

                                        <?= amanSkrining(
                                            $data["status_ekonomi"]
                                        ); ?>

                                    </td>

                                    <td class="text-center">

                                        <?= amanSkrining(
                                            $data["sanitasi"]
                                        ); ?>

                                    </td>

                                    <td class="text-center">

                                        <?= amanSkrining(
                                            $data["air_bersih"]
                                        ); ?>

                                    </td>

                                    <td>

                                        <div
                                            class="table-actions
                                            justify-content-center"
                                        >

                                            <a
                                                href="../deteksi/analisis_deteksi.php?id_balita=<?= $idBalita; ?>"
                                                class="btn btn-info btn-sm"
                                            >

                                                <i
                                                    class="bi
                                                    bi-search-heart"
                                                ></i>

                                                Pilih

                                            </a>

                                            <a
                                                href="edit_skrining.php?id=<?= $idSkrining; ?>"
                                                class="btn btn-warning btn-sm"
                                            >

                                                <i
                                                    class="bi
                                                    bi-pencil-square"
                                                ></i>

                                                Edit

                                            </a>

                                            <a
                                                href="hapus_skrining.php?id=<?= $idSkrining; ?>"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm(
                                                    'Yakin ingin menghapus data skrining ini?'
                                                );"
                                            >

                                                <i
                                                    class="bi
                                                    bi-trash3"
                                                ></i>

                                                Hapus

                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="13">

                                    <div class="empty-state">

                                        <div
                                            class="empty-state-icon"
                                        >

                                            <i
                                                class="bi
                                                bi-clipboard2-heart"
                                            ></i>

                                        </div>

                                        <h3>
                                            Belum ada data skrining
                                        </h3>

                                        <p>

                                            Tambahkan skrining awal
                                            untuk mulai mencatat faktor
                                            risiko balita.

                                        </p>

                                        <a
                                            href="form_skrining.php"
                                            class="btn btn-primary mt-3"
                                        >

                                            <i
                                                class="bi
                                                bi-plus-circle"
                                            ></i>

                                            Tambah Skrining

                                        </a>

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