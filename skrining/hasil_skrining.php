<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/* Tanggal pemantauan mengikuti WIB / Jakarta. */
date_default_timezone_set("Asia/Jakarta");
@mysqli_query($conn, "SET time_zone = '+07:00'");

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
    "Data Skrining | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Role aktif
|--------------------------------------------------------------------------
*/

$roleAktif = $_SESSION["role"] ?? "";

/* Status hasil dan status verifikasi terlihat di semua role. */
$tampilkanVerifikasi = true;

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

function formatTanggalPemantauan($tanggal): string
{
    $tanggal = trim((string) ($tanggal ?? ""));

    if (
        $tanggal === ""
        || $tanggal === "0000-00-00"
        || $tanggal === "0000-00-00 00:00:00"
    ) {
        return "Belum tercatat";
    }

    try {
        $objekTanggal = new DateTime($tanggal, new DateTimeZone("Asia/Jakarta"));
    } catch (Exception $e) {
        return "Belum tercatat";
    }

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

    $bulan = (int) $objekTanggal->format("n");

    return $objekTanggal->format("j")
        . " " . ($namaBulan[$bulan] ?? "")
        . " " . $objekTanggal->format("Y");
}

function skriningDipakaiUlang($tanggalPengukuran, $tanggalSkrining): bool
{
    $tanggalPengukuran = trim((string) ($tanggalPengukuran ?? ""));
    $tanggalSkrining = trim((string) ($tanggalSkrining ?? ""));

    if ($tanggalPengukuran === "" || $tanggalSkrining === "") {
        return false;
    }

    if (
        strpos($tanggalPengukuran, "0000-00-00") === 0
        || strpos($tanggalSkrining, "0000-00-00") === 0
    ) {
        return false;
    }

    try {
        $pengukuran = new DateTime($tanggalPengukuran, new DateTimeZone("Asia/Jakarta"));
        $skrining = new DateTime($tanggalSkrining, new DateTimeZone("Asia/Jakarta"));
    } catch (Exception $e) {
        return false;
    }

    return $skrining->format("Y-m") < $pengukuran->format("Y-m");
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

    $statusAsli = trim((string) $status);
    $statusNormal = strtolower($statusAsli);

    /*
    |------------------------------------------------------------------
    | Normalisasi istilah lama dari database.
    | Data yang sebelumnya tersimpan sebagai "Risiko Gizi Lebih"
    | tetap ditampilkan dengan istilah yang sudah diperbaiki.
    |------------------------------------------------------------------
    */
    if ($statusNormal === "risiko gizi lebih") {
        return "Berisiko Gizi Lebih";
    }

    if (
        $statusNormal === "perlu pemeriksaan"
        || $statusNormal === "perlu pemeriksaan ulang"
        || $statusNormal === "data antropometri perlu diperiksa"
    ) {
        return "Data Antropometri Perlu Diperiksa";
    }

    return $statusAsli;
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
        strpos($statusNormal, "baik") !== false
        || strpos($statusNormal, "normal") !== false
    ) {
        return "text-bg-success";
    }

    if (
        strpos($statusNormal, "obesitas") !== false
        || strpos($statusNormal, "buruk") !== false
    ) {
        return "text-bg-danger";
    }

    if (
        strpos($statusNormal, "berisiko") !== false
        || strpos($statusNormal, "risiko") !== false
        || strpos($statusNormal, "kurang") !== false
        || strpos($statusNormal, "gizi lebih") !== false
    ) {
        return "text-bg-warning";
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

$bolehPerbaikiAntropometri =
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
| Scope Puskesmas berdasarkan akun
|--------------------------------------------------------------------------
|
| Kader, Petugas Gizi, Petugas KIA, dan Kepala Puskesmas hanya boleh
| melihat data dari Puskesmas akun masing-masing.
|
| Dinkes dapat melakukan monitoring seluruh Puskesmas.
|
*/

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$idPuskesmasAktif = 0;
$namaPuskesmasAktif = "";
$puskesmasBelumTerhubung = false;

$roleBerbasisPuskesmas =
    in_array(
        $roleAktif,
        [
            "kader",
            "petugas_gizi",
            "petugas_kia",
            "kepala_puskesmas"
        ],
        true
    );

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
| Filter daftar skrining
|--------------------------------------------------------------------------
|
| Filter menggunakan GET agar pilihan tetap tersimpan di URL dan mudah
| di-reset. Bulan/tahun mengikuti antropometri terbaru agar status bulanan
| tetap terbaca walaupun skrining menggunakan data periode sebelumnya.
| Pencarian mencakup nama balita, Puskesmas, pendidikan, pekerjaan,
| status gizi, status stunting, dan status verifikasi.
|
*/

$cariSkrining = trim((string) ($_GET["cari"] ?? ""));

$bulanFilter = (int) ($_GET["bulan"] ?? 0);
if ($bulanFilter < 1 || $bulanFilter > 12) {
    $bulanFilter = 0;
}

$tahunFilter = (int) ($_GET["tahun"] ?? 0);
if ($tahunFilter < 2000 || $tahunFilter > 2100) {
    $tahunFilter = 0;
}

$urutanFilter = strtolower(
    trim((string) ($_GET["urut"] ?? "terbaru"))
);

if (!in_array($urutanFilter, ["terbaru", "terlama"], true)) {
    $urutanFilter = "terbaru";
}

$filterAktif =
    $cariSkrining !== ""
    || $bulanFilter > 0
    || $tahunFilter > 0
    || $urutanFilter === "terlama";

/*
|--------------------------------------------------------------------------
| Tentukan kolom tanggal skrining bila tersedia
|--------------------------------------------------------------------------
|
| Beberapa versi database menggunakan nama kolom tanggal yang berbeda.
| Sistem mencoba memakai tanggal skrining terlebih dahulu. Bila tidak ada,
| filter bulan/tahun memakai tanggal hasil deteksi terbaru sebagai fallback.
|
*/

$kolomTanggalSkrining = "";

/*
| Nilai kosong berarti tabel skrining_awal tidak memiliki kolom tanggal
| yang dikenali. Jangan membentuk nama kolom SQL dari string kosong.
*/
$kandidatKolomTanggal = [
    "tanggal_skrining",
    "tanggal_input",
    "created_at",
    "dibuat_pada"
];

$hasilKolomSkrining = mysqli_query(
    $conn,
    "SHOW COLUMNS FROM skrining_awal"
);

if ($hasilKolomSkrining) {
    while ($kolom = mysqli_fetch_assoc($hasilKolomSkrining)) {
        $namaKolom = (string) ($kolom["Field"] ?? "");

        if (in_array($namaKolom, $kandidatKolomTanggal, true)) {
            $kolomTanggalSkrining = $namaKolom;
            break;
        }
    }
}

/*
| Filter bulan/tahun mengikuti KUNJUNGAN ANTROPOMETRI terbaru,
| bukan tanggal skrining. Skrining boleh memakai data bulan sebelumnya.
*/
$ekspresiTanggalFilter = "det.tanggal_pengukuran";

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

/*
|--------------------------------------------------------------------------
| Kompatibilitas struktur database
|--------------------------------------------------------------------------
|
| Versi database lama belum tentu memiliki:
| - hasil_deteksi.id_skrining
| - skrining_awal.tanggal_skrining
|
| Halaman ini mendeteksi struktur tabel lebih dulu sehingga tetap dapat
| digunakan tanpa menghasilkan "Unknown column".
|
*/

$punyaIdSkriningPadaDeteksi = false;

$cekIdSkriningDeteksi = mysqli_query(
    $conn,
    "SHOW COLUMNS FROM hasil_deteksi LIKE 'id_skrining'"
);

if (
    $cekIdSkriningDeteksi
    && mysqli_num_rows($cekIdSkriningDeteksi) > 0
) {
    $punyaIdSkriningPadaDeteksi = true;
}

/*
| Ekspresi tanggal skrining pada SELECT utama.
| Jika kolom tanggal belum tersedia, hasilnya NULL dan tampilan tetap aman.
*/
$selectTanggalSkriningUtama = "NULL";

if ($kolomTanggalSkrining !== "") {
    $kolomTanggalSkriningAman = str_replace(
        "`",
        "``",
        $kolomTanggalSkrining
    );

    $selectTanggalSkriningUtama =
        "s.`{$kolomTanggalSkriningAman}`";
}

/*
| Bagian relasi hasil_deteksi -> skrining_awal hanya dipakai bila
| hasil_deteksi memang memiliki kolom id_skrining.
*/
if ($punyaIdSkriningPadaDeteksi) {

    $selectIdSkriningDeteksi =
        "hd.id_skrining";

    if ($kolomTanggalSkrining !== "") {

        $kolomTanggalSkriningAman = str_replace(
            "`",
            "``",
            $kolomTanggalSkrining
        );

        $selectTanggalSkriningDipakai =
            "skr_used.`{$kolomTanggalSkriningAman}`";

    } else {

        $selectTanggalSkriningDipakai =
            "NULL";
    }

    $joinSkriningDipakai = "
        LEFT JOIN skrining_awal AS skr_used
            ON skr_used.id_skrining = hd.id_skrining
    ";

} else {

    /*
    | Database lama:
    | tidak ada relasi langsung dari hasil_deteksi ke skrining_awal.
    | Status tetap diambil dari hasil deteksi terbaru balita.
    */
    $selectIdSkriningDeteksi =
        "NULL";

    $selectTanggalSkriningDipakai =
        "NULL";

    $joinSkriningDipakai = "";
}

$sqlDasar = "
    SELECT
        s.id_skrining,
        s.id_balita,
        s.tinggi_badan_ibu,
        s.pendidikan_ibu,
        s.pekerjaan_ibu,
        {$selectTanggalSkriningUtama}
            AS tanggal_skrining_terakhir,

        b.nama_balita,
        b.id_puskesmas,

        ps.nama_puskesmas,

        det.id_deteksi,
        det.id_pengukuran,
        det.id_skrining AS id_skrining_dipakai,
        det.tanggal_pengukuran,
        det.tanggal_skrining_dipakai,
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
            hd.id_pengukuran,
            {$selectIdSkriningDeteksi}
                AS id_skrining,
            pa.id_balita,
            pa.tanggal_pengukuran,
            {$selectTanggalSkriningDipakai}
                AS tanggal_skrining_dipakai,
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

        {$joinSkriningDipakai}

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
| Susun kondisi filter
|--------------------------------------------------------------------------
*/

$kondisiSkrining = [
    /* Menu utama menampilkan snapshot skrining TERBARU per balita. */
    "s.id_skrining = (
        SELECT MAX(s2.id_skrining)
        FROM skrining_awal AS s2
        WHERE s2.id_balita = s.id_balita
    )"
];

if ($roleBerbasisPuskesmas) {
    if ($puskesmasBelumTerhubung) {
        $kondisiSkrining[] = "1 = 0";
    } else {
        $kondisiSkrining[] =
            "b.id_puskesmas = " . (int) $idPuskesmasAktif;
    }
}

if ($cariSkrining !== "") {
    $cariSql = mysqli_real_escape_string(
        $conn,
        $cariSkrining
    );

    $kondisiSkrining[] = "(
        b.nama_balita LIKE '%{$cariSql}%'
        OR ps.nama_puskesmas LIKE '%{$cariSql}%'
        OR s.pendidikan_ibu LIKE '%{$cariSql}%'
        OR s.pekerjaan_ibu LIKE '%{$cariSql}%'
        OR det.status_gizi LIKE '%{$cariSql}%'
        OR det.status_stunting LIKE '%{$cariSql}%'
        OR det.status_verifikasi LIKE '%{$cariSql}%'
    )";
}

if ($bulanFilter > 0) {
    $kondisiSkrining[] =
        "SUBSTRING(CAST({$ekspresiTanggalFilter} AS CHAR), 6, 2) = '" .
        str_pad((string) ((int) $bulanFilter), 2, "0", STR_PAD_LEFT) .
        "'";
}

if ($tahunFilter > 0) {
    $kondisiSkrining[] =
        "SUBSTRING(CAST({$ekspresiTanggalFilter} AS CHAR), 1, 4) = '" .
        (int) $tahunFilter .
        "'";
}

$sql = $sqlDasar;

if (!empty($kondisiSkrining)) {
    $sql .= "\nWHERE " . implode("\nAND ", $kondisiSkrining);
}

/*
|--------------------------------------------------------------------------
| Urutan data
|--------------------------------------------------------------------------
|
| id_skrining dipakai sebagai urutan input sehingga data terbaru/terlama
| tetap dapat diurutkan meskipun suatu balita belum mempunyai hasil deteksi.
|
*/

$arahUrutan =
    $urutanFilter === "terlama"
        ? "ASC"
        : "DESC";

$sql .= "\nORDER BY
    CASE WHEN det.tanggal_pengukuran IS NULL THEN 1 ELSE 0 END,
    det.tanggal_pengukuran {$arahUrutan},
    s.id_skrining {$arahUrutan}";

$query = mysqli_query(
    $conn,
    $sql
);

if (!$query) {
    die(
        "Gagal mengambil data skrining: "
        . mysqli_error($conn)
    );
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

    case "pengukuran_dianalisis":

        $jenisAlert = "success";
        $isiPesan =
            "Pengukuran bulan ini berhasil disimpan dan status terbaru sudah dihitung menggunakan skrining terakhir. Jika ada faktor risiko yang berubah, gunakan tombol Perbarui Skrining.";
        break;

    case "skrining_diperbarui":

        $jenisAlert = "success";
        $isiPesan =
            "Skrining berhasil diperbarui. Sistem sudah menghitung ulang status menggunakan pengukuran antropometri terbaru.";
        break;

    case "verifikasi_berhasil":

        $jenisAlert = "success";
        $isiPesan = "Hasil skrining berhasil diverifikasi oleh Ahli Gizi.";
        break;

    case "pemeriksaan_ulang_diperlukan":

        $jenisAlert = "warning";
        $isiPesan =
            "Data antropometri perlu diperiksa ulang. Ahli Gizi tetap dapat melakukan verifikasi dengan memberikan catatan pada hasil verifikasi.";
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

    case "belum_ada_skrining":

        $jenisAlert = "warning";

        $isiPesan =
            "Balita belum memiliki data skrining. "
            . "Skrining perlu dilengkapi oleh Kader sebelum Petugas Gizi melakukan analisis.";

        break;
}

if (
    $roleBerbasisPuskesmas
    && $puskesmasBelumTerhubung
    && $isiPesan === ""
) {
    $jenisAlert = "warning";

    $isiPesan =
        "Akun ini belum terhubung dengan Puskesmas. "
        . "Data skrining wilayah tidak dapat ditampilkan.";
}

/*
|--------------------------------------------------------------------------
| Template aplikasi
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";

?>

<style>
    /* =========================================================
       UI KHUSUS HALAMAN HASIL SKRINING
       Tidak mengubah logic, query, role, atau relasi data.
    ========================================================== */

    .skrining-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 18px;
        padding-bottom: 16px;
        border-bottom: 1px solid rgba(0, 0, 0, .06);
    }

    .skrining-toolbar-left {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .skrining-filter-box {
        margin-bottom: 20px;
        padding: 16px;
        border: 1px solid rgba(0, 0, 0, .07);
        border-radius: 14px;
        background: rgba(255, 255, 255, .55);
    }

    .skrining-filter-box .form-label {
        font-weight: 600;
    }

    .skrining-table th {
        white-space: nowrap;
    }

    .skrining-table td {
        vertical-align: middle;
    }

    .skrining-table .nama-balita {
        min-width: 150px;
    }

    .skrining-table .aksi-skrining {
        min-width: 250px;
    }

    .skrining-modal-summary {
        padding: 18px;
        border-radius: 14px;
        background: rgba(0, 0, 0, .025);
    }

    .skrining-data-filter {
        margin-bottom: 20px;
        padding: 18px;
        border: 1px solid rgba(0, 0, 0, .07);
        border-radius: 14px;
        background: rgba(255, 255, 255, .72);
    }

    .skrining-data-filter .filter-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 14px;
        font-weight: 700;
    }

    .skrining-data-filter .form-label {
        margin-bottom: 6px;
        font-size: .86rem;
        font-weight: 600;
        color: #5c667a;
    }

    .skrining-data-filter .input-group-text {
        background: #fff;
    }

    .skrining-filter-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }


    @media (max-width: 767.98px) {
        .skrining-toolbar {
            align-items: flex-start;
        }

        .skrining-filter-box {
            padding: 14px;
        }
    }
</style>

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

                        Data Skrining

                    </h4>

                    <small class="text-muted">

                        <?php
                        if (
                            $roleAktif
                            === "kader"
                        ):
                        ?>

                            Kelola skrining serta lihat
                            status gizi dan status stunting balita.

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

                            <?= amanSkrining(
                                $namaPuskesmasAktif
                            ); ?>
                        </span>

                    <?php elseif (
                        $roleAktif === "dinkes"
                    ): ?>

                        <span
                            class="badge badge-info
                            d-inline-flex
                            align-items-center
                            px-3"
                        >
                            <i class="bi bi-buildings me-1"></i>
                            Semua Puskesmas
                        </span>

                    <?php endif; ?>

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
                        && !$puskesmasBelumTerhubung
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

                <div class="skrining-toolbar">

                    <div class="skrining-toolbar-left">

                        <span class="badge badge-primary">

                            <i class="bi bi-clipboard2-data"></i>

                            <?= $totalData; ?> data

                        </span>

                        <?php
                        if (
                            $roleAktif
                            === "kader"
                        ):
                        ?>

                            <span class="badge badge-info">

                                <i class="bi bi-pencil-square"></i>

                                Input Kader

                            </span>

                        <?php
                        elseif (
                            $roleAktif
                            === "petugas_gizi"
                        ):
                        ?>

                            <span class="badge badge-info">

                                <i class="bi bi-search-heart"></i>

                                Analisis Gizi

                            </span>

                        <?php
                        else:
                        ?>

                            <span class="badge badge-info">

                                <i class="bi bi-eye"></i>

                                Monitoring

                            </span>

                        <?php endif; ?>

                    </div>

                    <small class="text-muted">
                        Daftar skrining dan status terbaru balita.
                    </small>

                </div>

                <!-- =================================================
                     SCOPE WILAYAH
                ================================================== -->

                <?php if (
                    $roleBerbasisPuskesmas
                    && !$puskesmasBelumTerhubung
                ): ?>

                    <div class="skrining-filter-box">

                        <div
                            class="d-flex
                            flex-wrap
                            justify-content-between
                            align-items-center
                            gap-2"
                        >

                            <div>

                                <small
                                    class="text-muted
                                    d-block"
                                >
                                    Wilayah data
                                </small>

                                <strong>
                                    <?= amanSkrining(
                                        $namaPuskesmasAktif
                                    ); ?>
                                </strong>

                            </div>

                            <span class="badge badge-info">
                                <i class="bi bi-lock"></i>
                                Mengikuti akun
                            </span>

                        </div>

                    </div>

                <?php endif; ?>

                <!-- =================================================
                     FILTER & PENCARIAN DATA
                ================================================== -->

                <form
                    method="get"
                    action="hasil_skrining.php"
                    class="skrining-data-filter"
                >

                    <div class="filter-title">
                        <i class="bi bi-funnel"></i>
                        Filter & Cari Data
                    </div>

                    <div class="row g-3 align-items-end">

                        <!-- CARI -->
                        <div class="col-xl-4 col-lg-4 col-md-12">
                            <label
                                for="cari"
                                class="form-label"
                            >
                                Cari
                            </label>

                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>

                                <input
                                    type="search"
                                    class="form-control"
                                    id="cari"
                                    name="cari"
                                    value="<?= htmlspecialchars(
                                        $cariSkrining,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>"
                                    placeholder="Cari nama balita, status, pekerjaan..."
                                    autocomplete="off"
                                >
                            </div>
                        </div>

                        <!-- BULAN -->
                        <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6">
                            <label
                                for="bulan"
                                class="form-label"
                            >
                                Bulan
                            </label>

                            <select
                                class="form-select"
                                id="bulan"
                                name="bulan"
                            >
                                <option value="0">Semua Bulan</option>

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

                                foreach ($namaBulan as $angkaBulan => $teksBulan):
                                ?>
                                    <option
                                        value="<?= $angkaBulan; ?>"
                                        <?= $bulanFilter === $angkaBulan
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= $teksBulan; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- TAHUN -->
                        <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6">
                            <label
                                for="tahun"
                                class="form-label"
                            >
                                Tahun
                            </label>

                            <select
                                class="form-select"
                                id="tahun"
                                name="tahun"
                            >
                                <option value="0">Semua Tahun</option>

                                <?php
                                $tahunSekarang = (int) date("Y");
                                $tahunAwal = max(2015, $tahunSekarang - 10);

                                if (
                                    $tahunFilter > 0
                                    && $tahunFilter < $tahunAwal
                                ) {
                                    $tahunAwal = $tahunFilter;
                                }

                                for (
                                    $tahun = $tahunSekarang;
                                    $tahun >= $tahunAwal;
                                    $tahun--
                                ):
                                ?>
                                    <option
                                        value="<?= $tahun; ?>"
                                        <?= $tahunFilter === $tahun
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= $tahun; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- URUTAN -->
                        <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6">
                            <label
                                for="urut"
                                class="form-label"
                            >
                                Urutkan
                            </label>

                            <select
                                class="form-select"
                                id="urut"
                                name="urut"
                            >
                                <option
                                    value="terbaru"
                                    <?= $urutanFilter === "terbaru"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Terbaru
                                </option>

                                <option
                                    value="terlama"
                                    <?= $urutanFilter === "terlama"
                                        ? "selected"
                                        : ""; ?>
                                >
                                    Terlama
                                </option>
                            </select>
                        </div>

                        <!-- AKSI FILTER -->
                        <div class="col-xl-2 col-lg-2 col-md-12">
                            <div class="skrining-filter-actions">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    <i class="bi bi-funnel-fill"></i>
                                    Terapkan
                                </button>

                                <a
                                    href="hasil_skrining.php"
                                    class="btn btn-outline-secondary"
                                    title="Reset filter"
                                >
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                    Reset
                                </a>
                            </div>
                        </div>

                    </div>

                    <?php if ($filterAktif): ?>
                        <div class="mt-3 small text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Menampilkan
                            <strong><?= $totalData; ?></strong>
                            data sesuai filter yang dipilih.
                        </div>
                    <?php endif; ?>

                </form>

                <div class="alert alert-light border mb-3">
                    <div class="d-flex flex-wrap gap-3 align-items-center small">
                        <strong>
                            <i class="bi bi-info-circle me-1"></i>
                            Keterangan:
                        </strong>

                        <span>
                            <span class="badge text-bg-success">
                                Diperbarui bulan ini
                            </span>
                            skrining diperbarui pada periode kunjungan terbaru.
                        </span>

                        <span>
                            <span class="badge text-bg-info">
                                Menggunakan skrining sebelumnya
                            </span>
                            faktor risiko belum berubah sehingga memakai data terakhir.
                        </span>

                        <span>
                            <span class="badge text-bg-secondary">
                                Skrining tersimpan
                            </span>
                            data tersedia tetapi tanggal belum tercatat pada database lama.
                        </span>
                    </div>
                </div>

                <!-- =================================================
                     TABEL SKRINING
                ================================================== -->

                <div class="table-responsive">

                    <table
                        class="table
                        table-hover
                        align-middle
                        skrining-table"
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
                                    Antropometri Terakhir
                                </th>

                                <th class="text-center">
                                    Skrining Digunakan
                                </th>

                                <th class="text-center">
                                    <div>Status Gizi</div>
                                    <small class="text-muted fw-normal">
                                        BB/PB atau BB/TB
                                    </small>
                                </th>

                                <th class="text-center">
                                    <div>Status Stunting</div>
                                    <small class="text-muted fw-normal">
                                        PB/U atau TB/U
                                    </small>
                                </th>

                                <?php if ($tampilkanVerifikasi): ?>
                                    <th class="text-center">
                                        Verifikasi
                                    </th>
                                <?php endif; ?>

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

                                $idPengukuranTerakhir =
                                    (int) (
                                        $data[
                                            "id_pengukuran"
                                        ]
                                        ?? 0
                                    );

                                $statusGiziAsliNormal =
                                    strtolower(
                                        trim(
                                            (string) (
                                                $data["status_gizi"]
                                                ?? ""
                                            )
                                        )
                                    );

                                $perluPemeriksaanAntropometri =
                                    in_array(
                                        $statusGiziAsliNormal,
                                        [
                                            "perlu pemeriksaan",
                                            "perlu pemeriksaan ulang",
                                            "data antropometri perlu diperiksa"
                                        ],
                                        true
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
                                    $idDeteksi > 0
                                        ? teksStatusVerifikasi(
                                            $data[
                                                "status_verifikasi"
                                            ]
                                            ?? null
                                        )
                                        : "Belum ada hasil";

                                $sudahDiverifikasi =
                                    $idDeteksi > 0
                                    && strtolower(trim((string) ($data["status_verifikasi"] ?? "")))
                                        === "sudah diverifikasi";

                                $tanggalPengukuranTerakhir =
                                    $data["tanggal_pengukuran"] ?? null;

                                $tanggalSkriningDipakai =
                                    $data["tanggal_skrining_dipakai"]
                                    ?? $data["tanggal_skrining_terakhir"]
                                    ?? null;

                                $pakaiSkriningSebelumnya =
                                    skriningDipakaiUlang(
                                        $tanggalPengukuranTerakhir,
                                        $tanggalSkriningDipakai
                                    );

                            ?>

                                <tr>

                                    <td
                                        class="text-center"
                                    >
                                        <?= $nomor++; ?>
                                    </td>

                                    <!-- BALITA -->
                                    <td class="nama-balita">

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

                                    <!-- ANTROPOMETRI TERAKHIR -->
                                    <td class="text-center">
                                        <div class="fw-semibold">
                                            <?= amanSkrining(
                                                formatTanggalPemantauan(
                                                    $tanggalPengukuranTerakhir
                                                )
                                            ); ?>
                                        </div>
                                        <?php if ($idDeteksi > 0): ?>
                                            <small class="text-muted">
                                                Status dihitung dari pengukuran ini
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">
                                                Belum ada hasil deteksi
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- SKRINING YANG DIGUNAKAN -->
                                    <td class="text-center">

                                        <?php if ($tanggalSkriningDipakai): ?>

                                            <div class="fw-semibold">
                                                <?= amanSkrining(
                                                    formatTanggalPemantauan(
                                                        $tanggalSkriningDipakai
                                                    )
                                                ); ?>
                                            </div>

                                            <?php if ($pakaiSkriningSebelumnya): ?>

                                                <span
                                                    class="badge rounded-pill text-bg-info mt-1"
                                                    title="Belum ada pembaruan skrining pada kunjungan antropometri terbaru."
                                                >
                                                    Menggunakan skrining sebelumnya
                                                </span>

                                                <small class="d-block text-muted mt-1">
                                                    Faktor risiko memakai data skrining terakhir.
                                                </small>

                                            <?php else: ?>

                                                <span
                                                    class="badge rounded-pill text-bg-success mt-1"
                                                >
                                                    Diperbarui bulan ini
                                                </span>

                                            <?php endif; ?>

                                        <?php elseif ($idSkrining > 0): ?>

                                            <div class="fw-semibold text-muted">
                                                Tanggal belum tercatat
                                            </div>

                                            <span
                                                class="badge rounded-pill text-bg-secondary mt-1"
                                                title="Data skrining tersedia, tetapi versi database lama belum mencatat tanggal skrining."
                                            >
                                                Skrining tersimpan
                                            </span>

                                            <small class="d-block text-muted mt-1">
                                                Data skrining tersedia, tanggal belum tercatat.
                                            </small>

                                        <?php else: ?>

                                            <div class="fw-semibold text-warning">
                                                Belum pernah skrining
                                            </div>

                                            <span class="badge rounded-pill text-bg-warning mt-1">
                                                Perlu skrining pertama
                                            </span>

                                        <?php endif; ?>

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

                                    <?php if ($tampilkanVerifikasi): ?>
                                        <!-- STATUS VERIFIKASI -->
                                        <td
                                            class="text-center"
                                        >

                                            <span
                                                class="badge
                                                rounded-pill
                                                <?= $idDeteksi > 0
                                                    ? kelasStatusVerifikasi(
                                                        $data[
                                                            "status_verifikasi"
                                                        ]
                                                        ?? null
                                                    )
                                                    : "text-bg-secondary"; ?>"
                                            >

                                                <?= amanSkrining(
                                                    $statusVerifikasi
                                                ); ?>

                                            </span>

                                        </td>
                                    <?php endif; ?>

                                    <!-- AKSI -->
                                    <?php
                                    if (
                                        $punyaAksiSkrining
                                    ):
                                    ?>

                                        <td class="aksi-skrining">

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

                                                    <?php if (
                                                        $idDeteksi > 0
                                                    ): ?>

                                                        <?php if ($perluPemeriksaanAntropometri): ?>

                                                            <span
                                                                class="badge bg-warning text-dark"
                                                                title="Data antropometri perlu diperiksa ulang oleh Kader"
                                                            >
                                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                                Perlu Pemeriksaan Ulang
                                                            </span>

                                                        <?php endif; ?>

                                                        <a
                                                            href="../deteksi/analisis_deteksi.php?id_balita=<?= $idBalita; ?>&kembali=skrining"
                                                            class="btn
                                                            btn-primary
                                                            btn-sm"
                                                            title="Hitung ulang hasil deteksi"
                                                            onclick="return confirm(
                                                                'Analisis ulang akan menghitung kembali hasil deteksi dan mengubah status verifikasi menjadi Belum diverifikasi. Lanjutkan?'
                                                            );"
                                                        >

                                                            <i
                                                                class="bi
                                                                bi-arrow-repeat"
                                                            ></i>

                                                            Analisis Ulang

                                                        </a>

                                                        <?php if (!$sudahDiverifikasi): ?>
                                                            <a
                                                                href="../deteksi/verifikasi_deteksi.php?id=<?= $idDeteksi; ?>&kembali=skrining"
                                                                class="btn
                                                                btn-success
                                                                btn-sm"
                                                                title="Verifikasi hasil deteksi"
                                                            >
                                                                <i class="bi bi-check2-circle"></i>
                                                                Verifikasi Hasil
                                                            </a>
                                                        <?php endif; ?>

                                                    <?php else: ?>

                                                        <a
                                                            href="../deteksi/analisis_deteksi.php?id_balita=<?= $idBalita; ?>&kembali=skrining"
                                                            class="btn
                                                            btn-primary
                                                            btn-sm"
                                                            title="Lakukan analisis deteksi"
                                                        >

                                                            <i
                                                                class="bi
                                                                bi-search-heart"
                                                            ></i>

                                                            Analisis Deteksi

                                                        </a>

                                                    <?php endif; ?>

                                                <?php endif; ?>

                                                <?php if (
                                                    $bolehPerbaikiAntropometri
                                                    && $perluPemeriksaanAntropometri
                                                ): ?>

                                                    <a
                                                        href="../Pengukuran/tambah_pengukuran.php?id_balita=<?= $idBalita; ?>&pemeriksaan_ulang=1"
                                                        class="btn btn-danger btn-sm"
                                                        title="Input pengukuran ulang tanpa menghapus riwayat sebelumnya"
                                                    >
                                                        <i class="bi bi-rulers"></i>
                                                        Perbaiki Antropometri
                                                    </a>

                                                <?php endif; ?>

                                                <?php
                                                if (
                                                    $bolehEditSkrining
                                                ):
                                                ?>

                                                    <a
                                                        href="form_skrining.php?id_balita=<?= $idBalita; ?>&mode=perbarui"
                                                        class="btn
                                                        btn-warning
                                                        btn-sm"
                                                        title="Perbarui skrining dengan memakai data terakhir sebagai nilai awal"
                                                    >

                                                        <i
                                                            class="bi
                                                            bi-arrow-repeat"
                                                        ></i>

                                                        Perbarui Skrining

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
                                    colspan="<?= 10
                                        + ($tampilkanVerifikasi ? 1 : 0)
                                        + ($punyaAksiSkrining ? 1 : 0); ?>"
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
                                            <?= $filterAktif
                                                ? "Data tidak ditemukan"
                                                : "Belum ada data skrining"; ?>
                                        </h3>

                                        <p>

                                            <?php if ($filterAktif): ?>
                                                Tidak ada data yang cocok dengan pencarian atau filter yang dipilih.
                                            <?php else: ?>
                                                <?= $bolehTambahSkrining
                                                    ? "Tambahkan skrining untuk mulai mencatat faktor risiko balita."
                                                    : "Data skrining balita belum tersedia."; ?>
                                            <?php endif; ?>

                                        </p>

                                        <?php if ($filterAktif): ?>

                                            <a
                                                href="hasil_skrining.php"
                                                class="btn
                                                btn-outline-secondary
                                                mt-3"
                                            >
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                Reset Filter
                                            </a>

                                        <?php
                                        elseif (
                                            $bolehTambahSkrining
                                            && !$puskesmasBelumTerhubung
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
                            Skrining awal berhasil disimpan.
                            Hasil deteksi tetap dianalisis
                            oleh Petugas Gizi.
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
                        mb-4
                        skrining-modal-summary"
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

                            Data skrining
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

                                    <?php if ($tampilkanVerifikasi): ?>
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
                                    <?php endif; ?>

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
                            sudah tercatat untuk balita ini,
                            bukan hasil otomatis dari
                            skrining yang baru disimpan.

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
                            href="../deteksi/analisis_deteksi.php?id_balita=<?= $popupIdBalita; ?>&kembali=skrining"
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

if (
    isset($stmtSkrining)
    && $stmtSkrining instanceof mysqli_stmt
) {
    mysqli_stmt_close(
        $stmtSkrining
    );
}

require_once "../includes/footer.php";

?>