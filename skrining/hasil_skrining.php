<?php
session_start();
require_once "../config/koneksi.php";

// Ambil data skrining beserta nama balita
$sql = "
    SELECT 
        s.*,
        b.nama_balita
    FROM skrining_awal AS s
    INNER JOIN balita AS b
        ON s.id_balita = b.id_balita
    ORDER BY s.id_skrining DESC
";

$query = mysqli_query($conn, $sql);

// Cek jika query gagal
if (!$query) {
    die("Query gagal: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hasil Skrining</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f5f6fa;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .1);
        }

        .table th {
            background: #198754;
            color: white;
            text-align: center;
            white-space: nowrap;
        }

        .table td {
            text-align: center;
            vertical-align: middle;
        }

        .aksi {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>

<div class="container-fluid mt-4 px-4">

    <div class="card">

        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">

            <h4 class="mb-0">
                Data Skrining Awal
            </h4>

            <a
                href="form_skrining.php"
                class="btn btn-light"
            >
                + Tambah Skrining
            </a>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Balita</th>
                            <th>Tinggi Ibu</th>
                            <th>Pendidikan</th>
                            <th>Pekerjaan</th>
                            <th>ASI</th>
                            <th>MPASI</th>
                            <th>Frekuensi Makan</th>
                            <th>Protein Hewani</th>
                            <th>Status Ekonomi</th>
                            <th>Sanitasi</th>
                            <th>Air Bersih</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (mysqli_num_rows($query) > 0): ?>

                        <?php
                        $no = 1;

                        while ($data = mysqli_fetch_assoc($query)):
                            $idSkrining = (int) $data['id_skrining'];
                            $idBalita   = (int) $data['id_balita'];
                        ?>

                            <tr>

                                <td>
                                    <?= $no++; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['nama_balita'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['tinggi_badan_ibu'] ?? '-'); ?> cm
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['pendidikan_ibu'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['pekerjaan_ibu'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['lama_asi_eksklusif'] ?? '-'); ?> Bulan
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['mpasi'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['frekuensi_makan'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['protein_hewani'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['status_ekonomi'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['sanitasi'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['air_bersih'] ?? '-'); ?>
                                </td>

                                <td>
                                    <div class="aksi">

                                        <a
    href="/Search_Project/deteksi/analisis_deteksi.php?id_balita=<?= $idBalita; ?>"
    class="btn btn-success btn-sm"
>
    Pilih
</a>

                                        <a
                                            href="edit_skrining.php?id=<?= $idSkrining; ?>"
                                            class="btn btn-warning btn-sm"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="hapus_skrining.php?id=<?= $idSkrining; ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Yakin ingin menghapus data skrining ini?')"
                                        >
                                            Hapus
                                        </a>

                                    </div>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="13" class="text-center py-4">
                                Belum ada data skrining.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>