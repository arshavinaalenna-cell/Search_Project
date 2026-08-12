<?php

/*
|--------------------------------------------------------------------------
| Statistik Dashboard
|--------------------------------------------------------------------------
|
| Prinsip:
| 1. Aktivitas/riwayat tetap dihitung sebagai jumlah record.
| 2. Kondisi kesehatan dihitung berdasarkan HASIL DETEKSI TERBARU
|    setiap balita, sehingga satu balita tidak dihitung berulang.
| 3. Kader, Petugas KIA, Petugas Gizi, dan Kepala Puskesmas hanya
|    menghitung data pada Puskesmas yang terhubung dengan akun.
| 4. Dinkes melihat seluruh wilayah.
| 5. Orang Tua hanya melihat data anak milik akunnya.
|
*/

function statistikKolomAda(
    mysqli $conn,
    string $tabel,
    string $kolom
): bool {
    $tabelAman = mysqli_real_escape_string($conn, $tabel);
    $kolomAman = mysqli_real_escape_string($conn, $kolom);

    $hasil = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '{$tabelAman}'
           AND COLUMN_NAME = '{$kolomAman}'"
    );

    if (!$hasil) {
        return false;
    }

    $row = mysqli_fetch_assoc($hasil);

    return (int) ($row["total"] ?? 0) > 0;
}

function statistikHitungPrepared(
    mysqli $conn,
    string $sql,
    string $tipe = "",
    array $parameter = []
): int {
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return 0;
    }

    if ($tipe !== "" && !empty($parameter)) {
        mysqli_stmt_bind_param(
            $stmt,
            $tipe,
            ...$parameter
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }

    $hasil = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($hasil);

    mysqli_stmt_close($stmt);

    return (int) ($row["total"] ?? 0);
}

function statistikPuskesmasAkun(
    mysqli $conn,
    int $idUser
): int {
    if ($idUser <= 0) {
        return 0;
    }

    return statistikHitungPrepared(
        $conn,
        "SELECT COALESCE(id_puskesmas, 0) AS total
         FROM pengguna
         WHERE id_user = ?
         LIMIT 1",
        "i",
        [$idUser]
    );
}

function statistikHitungOrangTua(
    mysqli $conn,
    string $jenisData,
    int $idUser
): int {
    if ($idUser <= 0) {
        return 0;
    }

    $sql = "";

    switch ($jenisData) {
        case "balita":
            $sql = "
                SELECT COUNT(*) AS total
                FROM balita AS b
                WHERE b.id_user = ?
            ";
            break;

        case "pengukuran":
            $sql = "
                SELECT COUNT(*) AS total
                FROM pengukuran_antropometri AS pa
                INNER JOIN balita AS b
                    ON pa.id_balita = b.id_balita
                WHERE b.id_user = ?
            ";
            break;

        case "skrining":
            $sql = "
                SELECT COUNT(*) AS total
                FROM skrining_awal AS sa
                INNER JOIN balita AS b
                    ON sa.id_balita = b.id_balita
                WHERE b.id_user = ?
            ";
            break;

        case "hasil_deteksi":
            $sql = "
                SELECT COUNT(*) AS total
                FROM hasil_deteksi AS hd
                INNER JOIN pengukuran_antropometri AS pa
                    ON hd.id_pengukuran = pa.id_pengukuran
                INNER JOIN balita AS b
                    ON pa.id_balita = b.id_balita
                WHERE b.id_user = ?
            ";
            break;

        case "konsultasi":
            $sql = "
                SELECT COUNT(*) AS total
                FROM konsultasi AS k
                INNER JOIN balita AS b
                    ON k.id_balita = b.id_balita
                WHERE b.id_user = ?
            ";
            break;

        default:
            return 0;
    }

    return statistikHitungPrepared(
        $conn,
        $sql,
        "i",
        [$idUser]
    );
}

function statistikHitungWilayah(
    mysqli $conn,
    string $jenisData,
    int $idPuskesmas = 0
): int {
    $filter = "";
    $tipe = "";
    $parameter = [];

    if ($idPuskesmas > 0) {
        $filter = " WHERE b.id_puskesmas = ? ";
        $tipe = "i";
        $parameter = [$idPuskesmas];
    }

    switch ($jenisData) {
        case "balita":
            $sql = "
                SELECT COUNT(*) AS total
                FROM balita AS b
                {$filter}
            ";
            break;

        case "pengukuran":
            $sql = "
                SELECT COUNT(*) AS total
                FROM pengukuran_antropometri AS pa
                INNER JOIN balita AS b
                    ON pa.id_balita = b.id_balita
                {$filter}
            ";
            break;

        case "skrining":
            $sql = "
                SELECT COUNT(*) AS total
                FROM skrining_awal AS sa
                INNER JOIN balita AS b
                    ON sa.id_balita = b.id_balita
                {$filter}
            ";
            break;

        case "hasil_deteksi":
            $sql = "
                SELECT COUNT(*) AS total
                FROM hasil_deteksi AS hd
                INNER JOIN pengukuran_antropometri AS pa
                    ON hd.id_pengukuran = pa.id_pengukuran
                INNER JOIN balita AS b
                    ON pa.id_balita = b.id_balita
                {$filter}
            ";
            break;

        case "konsultasi":
            $sql = "
                SELECT COUNT(*) AS total
                FROM konsultasi AS k
                INNER JOIN balita AS b
                    ON k.id_balita = b.id_balita
                {$filter}
            ";
            break;

        case "pengguna":
            if ($idPuskesmas > 0) {
                $sql = "
                    SELECT COUNT(*) AS total
                    FROM pengguna AS b
                    WHERE b.id_puskesmas = ?
                ";
            } else {
                $sql = "
                    SELECT COUNT(*) AS total
                    FROM pengguna AS b
                ";
            }
            break;

        default:
            return 0;
    }

    return statistikHitungPrepared(
        $conn,
        $sql,
        $tipe,
        $parameter
    );
}

/*
|--------------------------------------------------------------------------
| Statistik kondisi terbaru per balita
|--------------------------------------------------------------------------
*/

function statistikStatusTerbaru(
    mysqli $conn,
    int $idPuskesmas,
    array $statusDicari
): int {
    if (empty($statusDicari)) {
        return 0;
    }

    $statusNormal = array_map(
        static fn($status) => strtolower(trim((string) $status)),
        $statusDicari
    );

    $placeholderStatus =
        implode(
            ",",
            array_fill(0, count($statusNormal), "?")
        );

    $filterPuskesmas = "";
    $tipe = "";
    $parameter = [];

    if ($idPuskesmas > 0) {
        $filterPuskesmas =
            " AND b.id_puskesmas = ? ";
        $tipe .= "i";
        $parameter[] = $idPuskesmas;
    }

    $tipe .= str_repeat("s", count($statusNormal));
    foreach ($statusNormal as $status) {
        $parameter[] = $status;
    }

    $sql = "
        SELECT COUNT(*) AS total
        FROM balita AS b
        INNER JOIN (
            SELECT
                pa2.id_balita,
                MAX(hd2.id_deteksi) AS id_deteksi_terbaru
            FROM hasil_deteksi AS hd2
            INNER JOIN pengukuran_antropometri AS pa2
                ON hd2.id_pengukuran = pa2.id_pengukuran
            GROUP BY pa2.id_balita
        ) AS terbaru
            ON terbaru.id_balita = b.id_balita
        INNER JOIN hasil_deteksi AS hd
            ON hd.id_deteksi = terbaru.id_deteksi_terbaru
        WHERE 1 = 1
        {$filterPuskesmas}
          AND LOWER(TRIM(COALESCE(hd.status_stunting, '')))
                IN ({$placeholderStatus})
    ";

    return statistikHitungPrepared(
        $conn,
        $sql,
        $tipe,
        $parameter
    );
}

function statistikBelumDiverifikasiTerbaru(
    mysqli $conn,
    int $idPuskesmas
): int {
    $filterPuskesmas = "";
    $tipe = "";
    $parameter = [];

    if ($idPuskesmas > 0) {
        $filterPuskesmas =
            " AND b.id_puskesmas = ? ";
        $tipe = "i";
        $parameter = [$idPuskesmas];
    }

    return statistikHitungPrepared(
        $conn,
        "
        SELECT COUNT(*) AS total
        FROM balita AS b
        INNER JOIN (
            SELECT
                pa2.id_balita,
                MAX(hd2.id_deteksi) AS id_deteksi_terbaru
            FROM hasil_deteksi AS hd2
            INNER JOIN pengukuran_antropometri AS pa2
                ON hd2.id_pengukuran = pa2.id_pengukuran
            GROUP BY pa2.id_balita
        ) AS terbaru
            ON terbaru.id_balita = b.id_balita
        INNER JOIN hasil_deteksi AS hd
            ON hd.id_deteksi = terbaru.id_deteksi_terbaru
        WHERE 1 = 1
        {$filterPuskesmas}
          AND LOWER(
                TRIM(
                    COALESCE(
                        hd.status_verifikasi,
                        'Belum diverifikasi'
                    )
                )
              ) <> 'sudah diverifikasi'
        ",
        $tipe,
        $parameter
    );
}

function statistikPerluKonsultasiBalita(
    mysqli $conn,
    int $idPuskesmas
): int {
    $filterPuskesmas = "";
    $tipe = "";
    $parameter = [];

    if ($idPuskesmas > 0) {
        $filterPuskesmas =
            " AND b.id_puskesmas = ? ";
        $tipe = "i";
        $parameter = [$idPuskesmas];
    }

    return statistikHitungPrepared(
        $conn,
        "
        SELECT COUNT(*) AS total
        FROM balita AS b
        INNER JOIN (
            SELECT
                pa2.id_balita,
                MAX(hd2.id_deteksi) AS id_deteksi_terbaru
            FROM hasil_deteksi AS hd2
            INNER JOIN pengukuran_antropometri AS pa2
                ON hd2.id_pengukuran = pa2.id_pengukuran
            GROUP BY pa2.id_balita
        ) AS terbaru
            ON terbaru.id_balita = b.id_balita
        INNER JOIN hasil_deteksi AS hd
            ON hd.id_deteksi = terbaru.id_deteksi_terbaru
        WHERE 1 = 1
        {$filterPuskesmas}
          AND LOWER(
                TRIM(
                    COALESCE(
                        hd.keputusan_konsultasi,
                        ''
                    )
                )
              ) = 'perlu konsultasi'
        ",
        $tipe,
        $parameter
    );
}

function statistikKonsultasiAktif(
    mysqli $conn,
    int $idPuskesmas
): int {
    $punyaKolomStatus =
        statistikKolomAda(
            $conn,
            "konsultasi",
            "status_konsultasi"
        );

    $filterPuskesmas = "";
    $tipe = "";
    $parameter = [];

    if ($idPuskesmas > 0) {
        $filterPuskesmas =
            " AND b.id_puskesmas = ? ";
        $tipe = "i";
        $parameter = [$idPuskesmas];
    }

    if ($punyaKolomStatus) {
        $filterAktif = "
            AND LOWER(
                TRIM(
                    COALESCE(
                        k.status_konsultasi,
                        'aktif'
                    )
                )
            ) <> 'selesai'
        ";
    } else {
        /*
         * Kompatibilitas database lama sebelum status_konsultasi ditambahkan.
         * Konsultasi dianggap selesai hanya jika hasil DAN tindak lanjut
         * sudah sama-sama terisi.
         */
        $filterAktif = "
            AND NOT (
                TRIM(COALESCE(k.hasil_konsultasi, '')) <> ''
                AND TRIM(COALESCE(k.tindak_lanjut, '')) <> ''
            )
        ";
    }

    return statistikHitungPrepared(
        $conn,
        "
        SELECT COUNT(*) AS total
        FROM konsultasi AS k
        INNER JOIN balita AS b
            ON k.id_balita = b.id_balita
        WHERE 1 = 1
        {$filterPuskesmas}
        {$filterAktif}
        ",
        $tipe,
        $parameter
    );
}

/*
|--------------------------------------------------------------------------
| Menentukan cakupan akun
|--------------------------------------------------------------------------
*/

$roleAktif =
    $_SESSION["role"] ?? "";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$roleTerikatPuskesmasStatistik = [
    "kader",
    "petugas_kia",
    "petugas_gizi",
    "kepala_puskesmas"
];

$idPuskesmasStatistik = 0;

if (
    in_array(
        $roleAktif,
        $roleTerikatPuskesmasStatistik,
        true
    )
) {
    $idPuskesmasStatistik =
        statistikPuskesmasAkun(
            $conn,
            $idUserAktif
        );
}

/*
|--------------------------------------------------------------------------
| Nilai awal
|--------------------------------------------------------------------------
*/

$totalBalita = 0;
$totalPengguna = 0;
$totalSkrining = 0;
$totalHasilDeteksi = 0;
$totalPengukuran = 0;
$totalKonsultasi = 0;

$totalNormalTerbaru = 0;
$totalRisikoStuntingTerbaru = 0;
$totalStuntingTerbaru = 0;
$totalStuntingBeratTerbaru = 0;
$totalRisikoAtauStuntingTerbaru = 0;

$totalBelumDiverifikasiTerbaru = 0;
$totalPerluKonsultasiBalita = 0;
$totalKonsultasiAktif = 0;

/*
|--------------------------------------------------------------------------
| Orang Tua = riwayat milik anak sendiri
|--------------------------------------------------------------------------
*/

if ($roleAktif === "orang_tua") {

    $totalBalita =
        statistikHitungOrangTua(
            $conn,
            "balita",
            $idUserAktif
        );

    $totalPengukuran =
        statistikHitungOrangTua(
            $conn,
            "pengukuran",
            $idUserAktif
        );

    $totalSkrining =
        statistikHitungOrangTua(
            $conn,
            "skrining",
            $idUserAktif
        );

    $totalHasilDeteksi =
        statistikHitungOrangTua(
            $conn,
            "hasil_deteksi",
            $idUserAktif
        );

    $totalKonsultasi =
        statistikHitungOrangTua(
            $conn,
            "konsultasi",
            $idUserAktif
        );

} else {

    /*
     * Jika role terikat Puskesmas tetapi akun belum punya id_puskesmas,
     * semua statistik wilayah dibuat 0. Tidak boleh jatuh ke statistik global.
     */
    $roleWajibPuskesmas =
        in_array(
            $roleAktif,
            $roleTerikatPuskesmasStatistik,
            true
        );

    $cakupanValid =
        !$roleWajibPuskesmas
        || $idPuskesmasStatistik > 0;

    if ($cakupanValid) {

        $totalBalita =
            statistikHitungWilayah(
                $conn,
                "balita",
                $idPuskesmasStatistik
            );

        $totalPengukuran =
            statistikHitungWilayah(
                $conn,
                "pengukuran",
                $idPuskesmasStatistik
            );

        $totalSkrining =
            statistikHitungWilayah(
                $conn,
                "skrining",
                $idPuskesmasStatistik
            );

        $totalHasilDeteksi =
            statistikHitungWilayah(
                $conn,
                "hasil_deteksi",
                $idPuskesmasStatistik
            );

        $totalKonsultasi =
            statistikHitungWilayah(
                $conn,
                "konsultasi",
                $idPuskesmasStatistik
            );

        $totalNormalTerbaru =
            statistikStatusTerbaru(
                $conn,
                $idPuskesmasStatistik,
                [
                    "Normal",
                    "Normal/Sehat",
                    "Tidak Stunting"
                ]
            );

        $totalRisikoStuntingTerbaru =
            statistikStatusTerbaru(
                $conn,
                $idPuskesmasStatistik,
                [
                    "Risiko Stunting"
                ]
            );

        $totalStuntingTerbaru =
            statistikStatusTerbaru(
                $conn,
                $idPuskesmasStatistik,
                [
                    "Stunting"
                ]
            );

        $totalStuntingBeratTerbaru =
            statistikStatusTerbaru(
                $conn,
                $idPuskesmasStatistik,
                [
                    "Stunting Berat"
                ]
            );

        $totalRisikoAtauStuntingTerbaru =
            statistikStatusTerbaru(
                $conn,
                $idPuskesmasStatistik,
                [
                    "Risiko Stunting",
                    "Stunting",
                    "Stunting Berat"
                ]
            );

        $totalBelumDiverifikasiTerbaru =
            statistikBelumDiverifikasiTerbaru(
                $conn,
                $idPuskesmasStatistik
            );

        $totalPerluKonsultasiBalita =
            statistikPerluKonsultasiBalita(
                $conn,
                $idPuskesmasStatistik
            );

        $totalKonsultasiAktif =
            statistikKonsultasiAktif(
                $conn,
                $idPuskesmasStatistik
            );
    }

    if ($roleAktif === "dinkes") {
        $totalPengguna =
            statistikHitungWilayah(
                $conn,
                "pengguna",
                0
            );
    }
}
