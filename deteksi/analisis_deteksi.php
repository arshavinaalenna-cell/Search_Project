<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
| Analisis deteksi dilakukan oleh Petugas Gizi.
*/

cekRole([
    "petugas_gizi"
]);

$judulHalaman =
    "Analisis Risiko Stunting | Sistem Deteksi Stunting";

/*
|--------------------------------------------------------------------------
| Fungsi mengamankan output
|--------------------------------------------------------------------------
*/

function amanAnalisis($nilai): string
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

$tabelTbULakiLaki = [
    0 => ["M" => 49.8842, "S" => 0.03795],
    1 => ["M" => 54.7244, "S" => 0.03557],
    2 => ["M" => 58.4249, "S" => 0.03424],
    3 => ["M" => 61.4292, "S" => 0.03328],
    4 => ["M" => 63.886, "S" => 0.03257],
    5 => ["M" => 65.9026, "S" => 0.03204],
    6 => ["M" => 67.6236, "S" => 0.03165],
    7 => ["M" => 69.1645, "S" => 0.03139],
    8 => ["M" => 70.5994, "S" => 0.03124],
    9 => ["M" => 71.9687, "S" => 0.03117],
    10 => ["M" => 73.2812, "S" => 0.03118],
    11 => ["M" => 74.5388, "S" => 0.03125],
    12 => ["M" => 75.7488, "S" => 0.03137],
    13 => ["M" => 76.9186, "S" => 0.03154],
    14 => ["M" => 78.0497, "S" => 0.03174],
    15 => ["M" => 79.1458, "S" => 0.03197],
    16 => ["M" => 80.2113, "S" => 0.03222],
    17 => ["M" => 81.2487, "S" => 0.0325],
    18 => ["M" => 82.2587, "S" => 0.03279],
    19 => ["M" => 83.2418, "S" => 0.0331],
    20 => ["M" => 84.1996, "S" => 0.03342],
    21 => ["M" => 85.1348, "S" => 0.03376],
    22 => ["M" => 86.0477, "S" => 0.0341],
    23 => ["M" => 86.941, "S" => 0.03445],
    24 => ["M" => 87.1161, "S" => 0.03507],
    25 => ["M" => 87.972, "S" => 0.03542],
    26 => ["M" => 88.8065, "S" => 0.03576],
    27 => ["M" => 89.6197, "S" => 0.0361],
    28 => ["M" => 90.412, "S" => 0.03642],
    29 => ["M" => 91.1828, "S" => 0.03674],
    30 => ["M" => 91.9327, "S" => 0.03704],
    31 => ["M" => 92.6631, "S" => 0.03733],
    32 => ["M" => 93.3753, "S" => 0.03761],
    33 => ["M" => 94.0711, "S" => 0.03787],
    34 => ["M" => 94.7532, "S" => 0.03812],
    35 => ["M" => 95.4236, "S" => 0.03836],
    36 => ["M" => 96.0835, "S" => 0.03858],
    37 => ["M" => 96.7337, "S" => 0.03879],
    38 => ["M" => 97.3749, "S" => 0.039],
    39 => ["M" => 98.0073, "S" => 0.03919],
    40 => ["M" => 98.631, "S" => 0.03937],
    41 => ["M" => 99.2459, "S" => 0.03954],
    42 => ["M" => 99.8515, "S" => 0.03971],
    43 => ["M" => 100.4485, "S" => 0.03986],
    44 => ["M" => 101.0374, "S" => 0.04002],
    45 => ["M" => 101.6186, "S" => 0.04016],
    46 => ["M" => 102.1933, "S" => 0.04031],
    47 => ["M" => 102.7625, "S" => 0.04045],
    48 => ["M" => 103.3273, "S" => 0.04059],
    49 => ["M" => 103.8886, "S" => 0.04073],
    50 => ["M" => 104.4473, "S" => 0.04086],
    51 => ["M" => 105.0041, "S" => 0.041],
    52 => ["M" => 105.5596, "S" => 0.04113],
    53 => ["M" => 106.1138, "S" => 0.04126],
    54 => ["M" => 106.6668, "S" => 0.04139],
    55 => ["M" => 107.2188, "S" => 0.04152],
    56 => ["M" => 107.7697, "S" => 0.04165],
    57 => ["M" => 108.3198, "S" => 0.04177],
    58 => ["M" => 108.8689, "S" => 0.0419],
    59 => ["M" => 109.417, "S" => 0.04202],
    60 => ["M" => 109.9638, "S" => 0.04214],
];

$tabelTbUPerempuan = [
    0 => ["M" => 49.1477, "S" => 0.0379],
    1 => ["M" => 53.6872, "S" => 0.0364],
    2 => ["M" => 57.0673, "S" => 0.03568],
    3 => ["M" => 59.8029, "S" => 0.0352],
    4 => ["M" => 62.0899, "S" => 0.03486],
    5 => ["M" => 64.0301, "S" => 0.03463],
    6 => ["M" => 65.7311, "S" => 0.03448],
    7 => ["M" => 67.2873, "S" => 0.03441],
    8 => ["M" => 68.7498, "S" => 0.0344],
    9 => ["M" => 70.1435, "S" => 0.03444],
    10 => ["M" => 71.4818, "S" => 0.03452],
    11 => ["M" => 72.771, "S" => 0.03464],
    12 => ["M" => 74.015, "S" => 0.03479],
    13 => ["M" => 75.2176, "S" => 0.03496],
    14 => ["M" => 76.3817, "S" => 0.03514],
    15 => ["M" => 77.5099, "S" => 0.03534],
    16 => ["M" => 78.6055, "S" => 0.03555],
    17 => ["M" => 79.671, "S" => 0.03576],
    18 => ["M" => 80.7079, "S" => 0.03598],
    19 => ["M" => 81.7182, "S" => 0.0362],
    20 => ["M" => 82.7036, "S" => 0.03643],
    21 => ["M" => 83.6654, "S" => 0.03666],
    22 => ["M" => 84.604, "S" => 0.03688],
    23 => ["M" => 85.5202, "S" => 0.03711],
    24 => ["M" => 85.7153, "S" => 0.03764],
    25 => ["M" => 86.5904, "S" => 0.03786],
    26 => ["M" => 87.4462, "S" => 0.03808],
    27 => ["M" => 88.283, "S" => 0.0383],
    28 => ["M" => 89.1004, "S" => 0.03851],
    29 => ["M" => 89.8991, "S" => 0.03872],
    30 => ["M" => 90.6797, "S" => 0.03893],
    31 => ["M" => 91.443, "S" => 0.03913],
    32 => ["M" => 92.1906, "S" => 0.03933],
    33 => ["M" => 92.9239, "S" => 0.03952],
    34 => ["M" => 93.6444, "S" => 0.03971],
    35 => ["M" => 94.3533, "S" => 0.03989],
    36 => ["M" => 95.0515, "S" => 0.04006],
    37 => ["M" => 95.7399, "S" => 0.04024],
    38 => ["M" => 96.4187, "S" => 0.04041],
    39 => ["M" => 97.0885, "S" => 0.04057],
    40 => ["M" => 97.7493, "S" => 0.04073],
    41 => ["M" => 98.4015, "S" => 0.04089],
    42 => ["M" => 99.0448, "S" => 0.04105],
    43 => ["M" => 99.6795, "S" => 0.0412],
    44 => ["M" => 100.3058, "S" => 0.04135],
    45 => ["M" => 100.9238, "S" => 0.0415],
    46 => ["M" => 101.5337, "S" => 0.04164],
    47 => ["M" => 102.136, "S" => 0.04179],
    48 => ["M" => 102.7312, "S" => 0.04193],
    49 => ["M" => 103.3197, "S" => 0.04206],
    50 => ["M" => 103.9021, "S" => 0.0422],
    51 => ["M" => 104.4786, "S" => 0.04233],
    52 => ["M" => 105.0494, "S" => 0.04246],
    53 => ["M" => 105.6148, "S" => 0.04259],
    54 => ["M" => 106.1748, "S" => 0.04272],
    55 => ["M" => 106.7295, "S" => 0.04285],
    56 => ["M" => 107.2788, "S" => 0.04298],
    57 => ["M" => 107.8227, "S" => 0.0431],
    58 => ["M" => 108.3613, "S" => 0.04322],
    59 => ["M" => 108.8948, "S" => 0.04334],
    60 => ["M" => 109.4233, "S" => 0.04347],
];

/*
|--------------------------------------------------------------------------
| Tabel rujukan WHO Child Growth Standards
| Indikator: Berat Badan menurut Umur (BB/U), 0-60 bulan
|--------------------------------------------------------------------------
| Berbeda dengan TB/U, nilai L (Box-Cox power) untuk BB/U TIDAK selalu 1,
| sehingga rumus LMS lengkap tetap dipakai:
|
|   Z = [(y/M)^L - 1] / (L * S)
|
| Sumber: WHO Child Growth Standards - Weight-for-age,
| Birth to 5 years (https://www.who.int/tools/child-growth-standards).
*/

$tabelBbULakiLaki = [
    0 => ["L" => 0.3487, "M" => 3.3464, "S" => 0.14602],
    1 => ["L" => 0.2297, "M" => 4.4709, "S" => 0.13395],
    2 => ["L" => 0.197, "M" => 5.5675, "S" => 0.12385],
    3 => ["L" => 0.1738, "M" => 6.3762, "S" => 0.11727],
    4 => ["L" => 0.1553, "M" => 7.0023, "S" => 0.11316],
    5 => ["L" => 0.1395, "M" => 7.5105, "S" => 0.1108],
    6 => ["L" => 0.1257, "M" => 7.934, "S" => 0.10958],
    7 => ["L" => 0.1134, "M" => 8.297, "S" => 0.10902],
    8 => ["L" => 0.1021, "M" => 8.6151, "S" => 0.10882],
    9 => ["L" => 0.0917, "M" => 8.9014, "S" => 0.10881],
    10 => ["L" => 0.082, "M" => 9.1649, "S" => 0.10891],
    11 => ["L" => 0.073, "M" => 9.4122, "S" => 0.10906],
    12 => ["L" => 0.0644, "M" => 9.6479, "S" => 0.10925],
    13 => ["L" => 0.0563, "M" => 9.8749, "S" => 0.10949],
    14 => ["L" => 0.0487, "M" => 10.0953, "S" => 0.10976],
    15 => ["L" => 0.0413, "M" => 10.3108, "S" => 0.11007],
    16 => ["L" => 0.0343, "M" => 10.5228, "S" => 0.11041],
    17 => ["L" => 0.0275, "M" => 10.7319, "S" => 0.11079],
    18 => ["L" => 0.0211, "M" => 10.9385, "S" => 0.11119],
    19 => ["L" => 0.0148, "M" => 11.143, "S" => 0.11164],
    20 => ["L" => 0.0087, "M" => 11.3462, "S" => 0.11211],
    21 => ["L" => 0.0029, "M" => 11.5486, "S" => 0.11261],
    22 => ["L" => -0.0028, "M" => 11.7504, "S" => 0.11314],
    23 => ["L" => -0.0083, "M" => 11.9514, "S" => 0.11369],
    24 => ["L" => -0.0137, "M" => 12.1515, "S" => 0.11426],
    25 => ["L" => -0.0189, "M" => 12.3502, "S" => 0.11485],
    26 => ["L" => -0.024, "M" => 12.5466, "S" => 0.11544],
    27 => ["L" => -0.0289, "M" => 12.7401, "S" => 0.11604],
    28 => ["L" => -0.0337, "M" => 12.9303, "S" => 0.11664],
    29 => ["L" => -0.0385, "M" => 13.1169, "S" => 0.11723],
    30 => ["L" => -0.0431, "M" => 13.3, "S" => 0.11781],
    31 => ["L" => -0.0476, "M" => 13.4798, "S" => 0.11839],
    32 => ["L" => -0.052, "M" => 13.6567, "S" => 0.11896],
    33 => ["L" => -0.0564, "M" => 13.8309, "S" => 0.11953],
    34 => ["L" => -0.0606, "M" => 14.0031, "S" => 0.12008],
    35 => ["L" => -0.0648, "M" => 14.1736, "S" => 0.12062],
    36 => ["L" => -0.0689, "M" => 14.3429, "S" => 0.12116],
    37 => ["L" => -0.0729, "M" => 14.5113, "S" => 0.12168],
    38 => ["L" => -0.0769, "M" => 14.6791, "S" => 0.1222],
    39 => ["L" => -0.0808, "M" => 14.8466, "S" => 0.12271],
    40 => ["L" => -0.0846, "M" => 15.014, "S" => 0.12322],
    41 => ["L" => -0.0883, "M" => 15.1813, "S" => 0.12373],
    42 => ["L" => -0.092, "M" => 15.3486, "S" => 0.12425],
    43 => ["L" => -0.0957, "M" => 15.5158, "S" => 0.12478],
    44 => ["L" => -0.0993, "M" => 15.6828, "S" => 0.12531],
    45 => ["L" => -0.1028, "M" => 15.8497, "S" => 0.12586],
    46 => ["L" => -0.1063, "M" => 16.0163, "S" => 0.12643],
    47 => ["L" => -0.1097, "M" => 16.1827, "S" => 0.127],
    48 => ["L" => -0.1131, "M" => 16.3489, "S" => 0.12759],
    49 => ["L" => -0.1165, "M" => 16.515, "S" => 0.12819],
    50 => ["L" => -0.1198, "M" => 16.6811, "S" => 0.1288],
    51 => ["L" => -0.123, "M" => 16.8471, "S" => 0.12943],
    52 => ["L" => -0.1262, "M" => 17.0132, "S" => 0.13005],
    53 => ["L" => -0.1294, "M" => 17.1792, "S" => 0.13069],
    54 => ["L" => -0.1325, "M" => 17.3452, "S" => 0.13133],
    55 => ["L" => -0.1356, "M" => 17.5111, "S" => 0.13197],
    56 => ["L" => -0.1387, "M" => 17.6768, "S" => 0.13261],
    57 => ["L" => -0.1417, "M" => 17.8422, "S" => 0.13325],
    58 => ["L" => -0.1447, "M" => 18.0073, "S" => 0.13389],
    59 => ["L" => -0.1477, "M" => 18.1722, "S" => 0.13453],
    60 => ["L" => -0.1506, "M" => 18.3366, "S" => 0.13517],
];

$tabelBbUPerempuan = [
    0 => ["L" => 0.3809, "M" => 3.2322, "S" => 0.14171],
    1 => ["L" => 0.1714, "M" => 4.1873, "S" => 0.13724],
    2 => ["L" => 0.0962, "M" => 5.1282, "S" => 0.13],
    3 => ["L" => 0.0402, "M" => 5.8458, "S" => 0.12619],
    4 => ["L" => -0.005, "M" => 6.4237, "S" => 0.12402],
    5 => ["L" => -0.043, "M" => 6.8985, "S" => 0.12274],
    6 => ["L" => -0.0756, "M" => 7.297, "S" => 0.12204],
    7 => ["L" => -0.1039, "M" => 7.6422, "S" => 0.12178],
    8 => ["L" => -0.1288, "M" => 7.9487, "S" => 0.12181],
    9 => ["L" => -0.1507, "M" => 8.2254, "S" => 0.12199],
    10 => ["L" => -0.17, "M" => 8.48, "S" => 0.12223],
    11 => ["L" => -0.1872, "M" => 8.7192, "S" => 0.12247],
    12 => ["L" => -0.2024, "M" => 8.9481, "S" => 0.12268],
    13 => ["L" => -0.2158, "M" => 9.1699, "S" => 0.12283],
    14 => ["L" => -0.2278, "M" => 9.387, "S" => 0.12294],
    15 => ["L" => -0.2384, "M" => 9.6008, "S" => 0.12299],
    16 => ["L" => -0.2478, "M" => 9.8124, "S" => 0.12303],
    17 => ["L" => -0.2562, "M" => 10.0226, "S" => 0.12306],
    18 => ["L" => -0.2637, "M" => 10.2315, "S" => 0.12309],
    19 => ["L" => -0.2703, "M" => 10.4393, "S" => 0.12315],
    20 => ["L" => -0.2762, "M" => 10.6464, "S" => 0.12323],
    21 => ["L" => -0.2815, "M" => 10.8534, "S" => 0.12335],
    22 => ["L" => -0.2862, "M" => 11.0608, "S" => 0.1235],
    23 => ["L" => -0.2903, "M" => 11.2688, "S" => 0.12369],
    24 => ["L" => -0.2941, "M" => 11.4775, "S" => 0.1239],
    25 => ["L" => -0.2975, "M" => 11.6864, "S" => 0.12414],
    26 => ["L" => -0.3005, "M" => 11.8947, "S" => 0.12441],
    27 => ["L" => -0.3032, "M" => 12.1015, "S" => 0.12472],
    28 => ["L" => -0.3057, "M" => 12.3059, "S" => 0.12506],
    29 => ["L" => -0.308, "M" => 12.5073, "S" => 0.12545],
    30 => ["L" => -0.3101, "M" => 12.7055, "S" => 0.12587],
    31 => ["L" => -0.312, "M" => 12.9006, "S" => 0.12633],
    32 => ["L" => -0.3138, "M" => 13.093, "S" => 0.12683],
    33 => ["L" => -0.3155, "M" => 13.2837, "S" => 0.12737],
    34 => ["L" => -0.3171, "M" => 13.4731, "S" => 0.12794],
    35 => ["L" => -0.3186, "M" => 13.6618, "S" => 0.12855],
    36 => ["L" => -0.3201, "M" => 13.8503, "S" => 0.12919],
    37 => ["L" => -0.3216, "M" => 14.0385, "S" => 0.12988],
    38 => ["L" => -0.323, "M" => 14.2265, "S" => 0.13059],
    39 => ["L" => -0.3243, "M" => 14.414, "S" => 0.13135],
    40 => ["L" => -0.3257, "M" => 14.601, "S" => 0.13213],
    41 => ["L" => -0.327, "M" => 14.7873, "S" => 0.13293],
    42 => ["L" => -0.3283, "M" => 14.9727, "S" => 0.13376],
    43 => ["L" => -0.3296, "M" => 15.1573, "S" => 0.1346],
    44 => ["L" => -0.3309, "M" => 15.341, "S" => 0.13545],
    45 => ["L" => -0.3322, "M" => 15.524, "S" => 0.1363],
    46 => ["L" => -0.3335, "M" => 15.7064, "S" => 0.13716],
    47 => ["L" => -0.3348, "M" => 15.8882, "S" => 0.138],
    48 => ["L" => -0.3361, "M" => 16.0697, "S" => 0.13884],
    49 => ["L" => -0.3374, "M" => 16.2511, "S" => 0.13968],
    50 => ["L" => -0.3387, "M" => 16.4322, "S" => 0.14051],
    51 => ["L" => -0.34, "M" => 16.6133, "S" => 0.14132],
    52 => ["L" => -0.3414, "M" => 16.7942, "S" => 0.14213],
    53 => ["L" => -0.3427, "M" => 16.9748, "S" => 0.14293],
    54 => ["L" => -0.344, "M" => 17.1551, "S" => 0.14371],
    55 => ["L" => -0.3453, "M" => 17.3347, "S" => 0.14448],
    56 => ["L" => -0.3466, "M" => 17.5136, "S" => 0.14525],
    57 => ["L" => -0.3479, "M" => 17.6916, "S" => 0.146],
    58 => ["L" => -0.3492, "M" => 17.8686, "S" => 0.14675],
    59 => ["L" => -0.3505, "M" => 18.0445, "S" => 0.14748],
    60 => ["L" => -0.3518, "M" => 18.2193, "S" => 0.14821],
];

function hitungZScoreTbu(
    float $tinggiBadan,
    int $umurBulan,
    string $jenisKelamin,
    array $tabelLakiLaki,
    array $tabelPerempuan
): ?float {

    $umurTerbatas = max(0, min(60, $umurBulan));

    $tabel = (
        strtolower(trim($jenisKelamin)) === "laki-laki"
    ) ? $tabelLakiLaki : $tabelPerempuan;

    if (!isset($tabel[$umurTerbatas])) {
        return null;
    }

    $median = $tabel[$umurTerbatas]["M"];
    $koefisienVariasi = $tabel[$umurTerbatas]["S"];

    if ($median <= 0 || $koefisienVariasi <= 0) {
        return null;
    }

    $zScore =
        ($tinggiBadan - $median) / ($median * $koefisienVariasi);

    return round($zScore, 2);
}

function klasifikasiStuntingWho(?float $zScore): array
{
    if ($zScore === null) {
        return [
            "label" => "Belum Dapat Dianalisis",
            "badge" => "badge-secondary",
            "stat" => "stat-info",
            "ikon" => "bi-question-circle",
            "keterangan" => "Data umur atau jenis kelamin balita berada di luar jangkauan tabel rujukan WHO (0-60 bulan), atau data tinggi badan belum lengkap.",
        ];
    }

    if ($zScore < -3) {
        return [
            "label" => "Stunting Berat",
            "badge" => "badge-danger",
            "stat" => "stat-danger",
            "ikon" => "bi-exclamation-octagon-fill",
            "keterangan" => "Tinggi badan balita sangat jauh di bawah standar WHO untuk usianya (Z-Score < -3 SD). Segera rujuk ke fasilitas kesehatan untuk penanganan lebih lanjut.",
        ];
    }

    if ($zScore < -2) {
        return [
            "label" => "Stunting",
            "badge" => "badge-danger",
            "stat" => "stat-warning",
            "ikon" => "bi-exclamation-octagon",
            "keterangan" => "Tinggi badan balita di bawah standar WHO untuk usianya (-3 SD <= Z-Score < -2 SD). Diperlukan intervensi gizi dan pemantauan rutin.",
        ];
    }

    if ($zScore < -1) {
        return [
            "label" => "Risiko Stunting",
            "badge" => "badge-warning",
            "stat" => "stat-warning",
            "ikon" => "bi-exclamation-triangle",
            "keterangan" => "Tinggi badan balita mendekati ambang batas stunting (-2 SD <= Z-Score < -1 SD). Perlu diwaspadai dan dipantau lebih ketat agar tidak turun ke kategori stunting.",
        ];
    }

    return [
        "label" => "Normal",
        "badge" => "badge-success",
        "stat" => "stat-success",
        "ikon" => "bi-shield-check",
        "keterangan" => "Tinggi badan balita sesuai dengan standar pertumbuhan WHO untuk usianya (Z-Score >= -1 SD).",
    ];
}

/*
|--------------------------------------------------------------------------
| Fungsi menghitung Z-Score BB/U (rumus WHO terbaru)
|--------------------------------------------------------------------------
| Rumus LMS WHO lengkap (L tidak selalu 1 untuk indikator BB/U):
| Z = [(y/M)^L - 1] / (L * S)
*/

function hitungZScoreBbu(
    float $beratBadan,
    int $umurBulan,
    string $jenisKelamin,
    array $tabelLakiLaki,
    array $tabelPerempuan
): ?float {

    // Tabel WHO untuk indikator BB/U hanya tersedia 0-60 bulan
    $umurTerbatas = max(0, min(60, $umurBulan));

    $tabel = (
        strtolower(trim($jenisKelamin)) === "laki-laki"
    ) ? $tabelLakiLaki : $tabelPerempuan;

    if (!isset($tabel[$umurTerbatas])) {
        return null;
    }

    $l = $tabel[$umurTerbatas]["L"];
    $median = $tabel[$umurTerbatas]["M"];
    $koefisienVariasi = $tabel[$umurTerbatas]["S"];

    if (
        $beratBadan <= 0
        || $median <= 0
        || $koefisienVariasi <= 0
    ) {
        return null;
    }

    if (abs($l) < 0.0001) {
        // L mendekati 0 -> bentuk logaritmik
        $zScore = log($beratBadan / $median) / $koefisienVariasi;
    } else {
        $zScore =
            (pow($beratBadan / $median, $l) - 1)
            / ($l * $koefisienVariasi);
    }

    return round($zScore, 2);
}

/*
|--------------------------------------------------------------------------
| Fungsi klasifikasi status gizi (rumus WHO terbaru berdasarkan BB/U)
|--------------------------------------------------------------------------
| Ambang batas Z-Score BB/U (Kepmenkes RI No. HK.01.07/MENKES/1928/2022):
| Z < -3 SD             : Berat Badan Sangat Kurang (Gizi Buruk)
| -3 SD <= Z < -2 SD    : Berat Badan Kurang (Gizi Kurang)
| -2 SD <= Z <= +1 SD   : Berat Badan Normal (Gizi Baik)
| Z > +1 SD             : Risiko Berat Badan Lebih
*/

function klasifikasiGiziWho(?float $zScore): array
{
    if ($zScore === null) {
        return [
            "label" => "Perlu Pemeriksaan",
            "badge" => "badge-secondary",
            "keterangan" => "Data umur, jenis kelamin, atau berat badan balita belum lengkap / di luar jangkauan tabel rujukan WHO (0-60 bulan).",
        ];
    }

    if ($zScore < -3) {
        return [
            "label" => "Gizi Buruk",
            "badge" => "badge-danger",
            "keterangan" => "Berat badan balita sangat jauh di bawah standar WHO untuk usianya (Z-Score BB/U < -3 SD). Segera rujuk ke fasilitas kesehatan.",
        ];
    }

    if ($zScore < -2) {
        return [
            "label" => "Gizi Kurang",
            "badge" => "badge-warning",
            "keterangan" => "Berat badan balita di bawah standar WHO untuk usianya (-3 SD <= Z-Score BB/U < -2 SD). Perlu intervensi gizi.",
        ];
    }

    if ($zScore <= 1) {
        return [
            "label" => "Gizi Baik",
            "badge" => "badge-success",
            "keterangan" => "Berat badan balita sesuai standar WHO untuk usianya (-2 SD <= Z-Score BB/U <= +1 SD).",
        ];
    }

    return [
        "label" => "Risiko Gizi Lebih",
        "badge" => "badge-warning",
        "keterangan" => "Berat badan balita di atas standar WHO untuk usianya (Z-Score BB/U > +1 SD). Perlu pemantauan pola makan.",
    ];
}

$idBalita = filter_input(
    INPUT_GET,
    "id_balita",
    FILTER_VALIDATE_INT
);

if (!$idBalita || $idBalita < 1) {
    header(
        "Location: ../skrining/hasil_skrining.php?pesan=balita_belum_dipilih"
    );
    exit;
}

$stmtBalita = mysqli_prepare(
    $conn,
    "SELECT
        id_balita,
        nama_balita,
        nik_balita,
        jenis_kelamin,
        tanggal_lahir
     FROM balita
     WHERE id_balita = ?
     LIMIT 1"
);

if (!$stmtBalita) {
    die(
        "Gagal menyiapkan data balita: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtBalita,
    "i",
    $idBalita
);

mysqli_stmt_execute($stmtBalita);

$resultBalita =
    mysqli_stmt_get_result($stmtBalita);

$balita =
    mysqli_fetch_assoc($resultBalita);

mysqli_stmt_close($stmtBalita);

if (!$balita) {
    header(
        "Location: ../skrining/hasil_skrining.php?pesan=tidak_ditemukan"
    );
    exit;
}

$stmtPengukuran = mysqli_prepare(
    $conn,
    "SELECT
        id_pengukuran,
        id_balita,
        tanggal_pengukuran,
        umur_bulan,
        berat_badan,
        tinggi_panjang_badan,
        lingkar_kepala,
        lila
     FROM pengukuran_antropometri
     WHERE id_balita = ?
     ORDER BY
        tanggal_pengukuran DESC,
        id_pengukuran DESC
     LIMIT 1"
);

if (!$stmtPengukuran) {
    die(
        "Gagal menyiapkan data pengukuran: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtPengukuran,
    "i",
    $idBalita
);

mysqli_stmt_execute($stmtPengukuran);

$resultPengukuran =
    mysqli_stmt_get_result($stmtPengukuran);

$pengukuran =
    mysqli_fetch_assoc($resultPengukuran);

mysqli_stmt_close($stmtPengukuran);

if (!$pengukuran) {
    header(
        "Location: ../pengukuran/data_pengukuran.php?pesan=belum_ada_pengukuran"
    );
    exit;
}

$stmtSkrining = mysqli_prepare(
    $conn,
    "SELECT
        id_skrining,
        id_balita,
        protein_hewani,
        sanitasi,
        air_bersih
     FROM skrining_awal
     WHERE id_balita = ?
     ORDER BY id_skrining DESC
     LIMIT 1"
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
    $idBalita
);

mysqli_stmt_execute($stmtSkrining);

$resultSkrining =
    mysqli_stmt_get_result($stmtSkrining);

$skrining =
    mysqli_fetch_assoc($resultSkrining);

mysqli_stmt_close($stmtSkrining);

if (!$skrining) {
    header(
        "Location: ../skrining/form_skrining.php?pesan=belum_ada_skrining"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Menghitung status stunting (rumus WHO terbaru berdasarkan Z-Score TB/U)
|--------------------------------------------------------------------------
| Status stunting murni ditentukan dari indikator antropometri
| Tinggi/Panjang Badan menurut Umur (TB/U). Data skrining awal
| (protein hewani, sanitasi, air bersih) sengaja dipisahkan dan
| tidak memengaruhi hasil deteksi -- data itu tetap tersimpan dan
| bisa dilihat di halaman skrining sebagai data pemantauan
| faktor risiko lingkungan.
*/

$zScoreTbu = hitungZScoreTbu(
    (float) $pengukuran["tinggi_panjang_badan"],
    (int) $pengukuran["umur_bulan"],
    (string) $balita["jenis_kelamin"],
    $tabelTbULakiLaki,
    $tabelTbUPerempuan
);

$hasilKlasifikasiWho = klasifikasiStuntingWho($zScoreTbu);

$statusStunting = $hasilKlasifikasiWho["label"];
$kelasStatus = $hasilKlasifikasiWho["badge"];
$kelasStat = $hasilKlasifikasiWho["stat"];
$ikonStatus = $hasilKlasifikasiWho["ikon"];
$keteranganStunting = $hasilKlasifikasiWho["keterangan"];

/*
|--------------------------------------------------------------------------
| Menghitung status gizi (rumus WHO terbaru berdasarkan Z-Score BB/U)
|--------------------------------------------------------------------------
*/

$zScoreBbu = hitungZScoreBbu(
    (float) $pengukuran["berat_badan"],
    (int) $pengukuran["umur_bulan"],
    (string) $balita["jenis_kelamin"],
    $tabelBbULakiLaki,
    $tabelBbUPerempuan
);

$hasilKlasifikasiGizi = klasifikasiGiziWho($zScoreBbu);

$statusGizi = $hasilKlasifikasiGizi["label"];

$idPengukuran = (int) $pengukuran["id_pengukuran"];

if ($idPengukuran <= 0) {
    die("ID pengukuran tidak valid.");
}

if (empty($statusStunting)) {
    die("Status stunting kosong.");
}

if (empty($statusGizi)) {
    $statusGizi = "Perlu Pemeriksaan";
}

$sqlCek = "
    SELECT id_deteksi
    FROM hasil_deteksi
    WHERE id_pengukuran = ?
    LIMIT 1
";

$stmtCek = mysqli_prepare($conn, $sqlCek);

if (!$stmtCek) {
    die(
        "Gagal menyiapkan pengecekan hasil deteksi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtCek,
    "i",
    $idPengukuran
);

if (!mysqli_stmt_execute($stmtCek)) {
    die(
        "Gagal menjalankan pengecekan hasil deteksi: "
        . mysqli_stmt_error($stmtCek)
    );
}

mysqli_stmt_store_result($stmtCek);

$jumlahData = mysqli_stmt_num_rows($stmtCek);

mysqli_stmt_close($stmtCek);

if ($jumlahData > 0) {

    $sqlSimpan = "
        UPDATE hasil_deteksi
        SET
            status_gizi = ?,
            status_stunting = ?,
            tanggal_deteksi = CURDATE()
        WHERE id_pengukuran = ?
    ";

    $stmtSimpan = mysqli_prepare(
        $conn,
        $sqlSimpan
    );

    if (!$stmtSimpan) {
        die(
            "Gagal menyiapkan UPDATE: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtSimpan,
        "ssi",
        $statusGizi,
        $statusStunting,
        $idPengukuran
    );

} else {

    $sqlSimpan = "
        INSERT INTO hasil_deteksi
        (
            id_pengukuran,
            status_gizi,
            status_stunting,
            tanggal_deteksi
        )
        VALUES
        (
            ?,
            ?,
            ?,
            CURDATE()
        )
    ";

    $stmtSimpan = mysqli_prepare(
        $conn,
        $sqlSimpan
    );

    if (!$stmtSimpan) {
        die(
            "Gagal menyiapkan INSERT: "
            . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtSimpan,
        "iss",
        $idPengukuran,
        $statusGizi,
        $statusStunting
    );
}

if (!mysqli_stmt_execute($stmtSimpan)) {

    die(
        "<h3>HASIL DETEKSI GAGAL DISIMPAN</h3>"
        . "<p><b>Error SQL:</b> "
        . htmlspecialchars(
            mysqli_stmt_error($stmtSimpan),
            ENT_QUOTES,
            "UTF-8"
        )
        . "</p>"
        . "<p><b>ID Pengukuran:</b> "
        . $idPengukuran
        . "</p>"
        . "<p><b>Status Gizi:</b> "
        . htmlspecialchars(
            $statusGizi,
            ENT_QUOTES,
            "UTF-8"
        )
        . "</p>"
        . "<p><b>Status Stunting:</b> "
        . htmlspecialchars(
            $statusStunting,
            ENT_QUOTES,
            "UTF-8"
        )
        . "</p>"
    );
}

mysqli_stmt_close($stmtSimpan);

/*
|--------------------------------------------------------------------------
| Berhasil disimpan -> kembali ke halaman Hasil Deteksi
|--------------------------------------------------------------------------
*/

header(
    "Location: hasil_deteksi.php?pesan=analisis_berhasil"
);

exit;