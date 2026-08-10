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
| Flash data skrining baru
|--------------------------------------------------------------------------
|
| Data ini dikirim oleh form_skrining.php setelah INSERT berhasil.
| Modal hanya tampil satu kali, lalu session langsung dihapus agar
| tidak muncul lagi ketika halaman direfresh.
|
*/

$skriningBaru = $_SESSION["skrining_baru"] ?? null;

if ($skriningBaru !== null) {
    unset($_SESSION["skrining_baru"]);
}

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
| Pengaturan hak akses berdasarkan role
|--------------------------------------------------------------------------
|
| Hanya Petugas Gizi yang dapat menambah, menganalisis,
| mengedit, dan menghapus data skrining.
| Role lain hanya melihat data dalam mode read-only.
|
*/

$roleAktif = $_SESSION["role"] ?? "";
$bolehKelolaSkrining = $roleAktif === "petugas_gizi";

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

                <?php if ($bolehKelolaSkrining): ?>

                    <a
                        href="form_skrining.php"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-plus-circle"></i>

                        Tambah Skrining

                    </a>

                <?php endif; ?>

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

                                <?php if ($bolehKelolaSkrining): ?>

                                    <th class="text-center">
                                        Aksi
                                    </th>

                                <?php endif; ?>

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

                                    <?php if ($bolehKelolaSkrining): ?>

                                        <td>

                                           <div
    class="table-actions
    justify-content-center
    d-flex
    gap-1"
>


    <!-- DETAIL SKRINING -->

    <a
        href="detail_skrining.php?id=<?= $idSkrining; ?>"
        class="btn btn-info btn-sm"
    >

        <i class="bi bi-eye"></i>

        Detail

    </a>



    <!-- ANALISIS DETEKSI -->

    <a
        href="../deteksi/analisis_deteksi.php?id_balita=<?= $idBalita; ?>"
        class="btn btn-primary btn-sm"
    >

        <i class="bi bi-search-heart"></i>

        Analisis

    </a>



    <!-- EDIT SKRINING -->

    <a
        href="edit_skrining.php?id=<?= $idSkrining; ?>"
        class="btn btn-warning btn-sm"
    >

        <i class="bi bi-pencil-square"></i>

        Edit

    </a>



    <!-- HAPUS SKRINING -->

    <a
        href="hapus_skrining.php?id=<?= $idSkrining; ?>"
        class="btn btn-danger btn-sm"
        onclick="return confirm(
            'Yakin ingin menghapus data skrining ini?'
        );"
    >

        <i class="bi bi-trash3"></i>

        Hapus

    </a>


</div>

                                        </td>

                                    <?php endif; ?>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="<?= $bolehKelolaSkrining
                                        ? "13"
                                        : "12"; ?>"
                                >

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

                                            <?= $bolehKelolaSkrining
                                                ? "Tambahkan skrining awal untuk mulai mencatat faktor risiko balita."
                                                : "Data skrining awal balita belum tersedia."; ?>

                                        </p>

                                        <?php if ($bolehKelolaSkrining): ?>

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

<?php if (is_array($skriningBaru)): ?>

    <?php
    $popupIdBalita =
        (int) ($skriningBaru["id_balita"] ?? 0);

    $popupNamaBalita =
        amanSkrining(
            $skriningBaru["nama_balita"]
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
        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">

                    <div>
                        <h5
                            class="modal-title"
                            id="modalSkriningBerhasilLabel"
                        >
                            <i class="bi bi-clipboard2-check me-2"></i>
                            Skrining Berhasil Disimpan
                        </h5>

                        <small class="text-muted">
                            Ringkasan data skrining awal yang baru diisi.
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

                    <div class="text-center mb-4">

                        <div
                            class="empty-state-icon mx-auto mb-2"
                            style="width: 58px; height: 58px;"
                        >
                            <i class="bi bi-person-heart"></i>
                        </div>

                        <h4 class="mb-1">
                            <?= $popupNamaBalita; ?>
                        </h4>

                        <p class="text-muted mb-0">
                            Data skrining awal berhasil direkam.
                        </p>

                    </div>

                    <div class="table-responsive">

                        <table
                            class="table table-bordered
                            align-middle mb-0"
                        >
                            <tbody>

                                <tr>
                                    <th width="48%">
                                        Tinggi Badan Ibu
                                    </th>
                                    <td>
                                        <?= amanSkrining(
                                            $skriningBaru[
                                                "tinggi_badan_ibu"
                                            ] ?? null
                                        ); ?>
                                        cm
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        ASI Eksklusif
                                    </th>
                                    <td>
                                        <?= amanSkrining(
                                            $skriningBaru[
                                                "lama_asi_eksklusif"
                                            ] ?? null
                                        ); ?>
                                        bulan
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        MPASI
                                    </th>
                                    <td>
                                        <?= amanSkrining(
                                            $skriningBaru["mpasi"]
                                            ?? null
                                        ); ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        Frekuensi Makan
                                    </th>
                                    <td>
                                        <?= amanSkrining(
                                            $skriningBaru[
                                                "frekuensi_makan"
                                            ] ?? null
                                        ); ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        Protein Hewani
                                    </th>
                                    <td>
                                        <?= amanSkrining(
                                            $skriningBaru[
                                                "protein_hewani"
                                            ] ?? null
                                        ); ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        Status Ekonomi
                                    </th>
                                    <td>
                                        <?= amanSkrining(
                                            $skriningBaru[
                                                "status_ekonomi"
                                            ] ?? null
                                        ); ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        Sanitasi
                                    </th>
                                    <td>
                                        <?= amanSkrining(
                                            $skriningBaru["sanitasi"]
                                            ?? null
                                        ); ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th>
                                        Air Bersih
                                    </th>
                                    <td>
                                        <?= amanSkrining(
                                            $skriningBaru[
                                                "air_bersih"
                                            ] ?? null
                                        ); ?>
                                    </td>
                                </tr>

                            </tbody>
                        </table>

                    </div>

                    <div class="alert alert-info mt-3 mb-0">

                        <i class="bi bi-info-circle me-1"></i>

                        Skrining awal mencatat faktor risiko.
                        Untuk menghitung status pertumbuhan
                        berdasarkan pengukuran antropometri,
                        lanjutkan ke proses analisis.

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >
                        <i class="bi bi-table"></i>
                        Lihat Daftar
                    </button>

                    <?php if (
                        $bolehKelolaSkrining
                        && $popupIdBalita > 0
                    ): ?>

                        <a
                            href="../deteksi/analisis_deteksi.php?id_balita=<?= $popupIdBalita; ?>"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-search-heart"></i>
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
                    && typeof bootstrap !== "undefined"
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

<?php require_once "../includes/footer.php"; ?>