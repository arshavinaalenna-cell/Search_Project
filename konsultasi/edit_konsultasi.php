<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

cekRole(["petugas_gizi"]);

$judulHalaman = "Edit Konsultasi | Sistem Deteksi Stunting";

$idUserAktif = (int) ($_SESSION["id_user"] ?? 0);
$pesanError = "";

$idKonsultasi = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$idKonsultasi) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Mengambil data konsultasi
|--------------------------------------------------------------------------
|
| Petugas Gizi hanya dapat membuka konsultasi yang ditugaskan kepadanya.
|
*/

$stmtKonsultasi = mysqli_prepare(
    $conn,
    "SELECT
        k.id_konsultasi,
        k.id_balita,
        k.id_petugas,
        k.tanggal,
        k.keluhan,
        k.hasil_konsultasi,
        k.tindak_lanjut,
        b.nama_balita,
        b.nik_balita,
        b.nama_ibu
     FROM konsultasi k
     INNER JOIN balita b
        ON k.id_balita = b.id_balita
     WHERE k.id_konsultasi = ?
     AND k.id_petugas = ?
     LIMIT 1"
);

if (!$stmtKonsultasi) {
    die(
        "Gagal menyiapkan data konsultasi: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtKonsultasi,
    "ii",
    $idKonsultasi,
    $idUserAktif
);

mysqli_stmt_execute($stmtKonsultasi);

$hasilKonsultasi = mysqli_stmt_get_result(
    $stmtKonsultasi
);

$dataKonsultasi = mysqli_fetch_assoc(
    $hasilKonsultasi
);

mysqli_stmt_close($stmtKonsultasi);

if (!$dataKonsultasi) {
    header(
        "Location: data_konsultasi.php?pesan=tidak_ditemukan"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai awal form
|--------------------------------------------------------------------------
*/

$hasilKonsultasiForm =
    $dataKonsultasi["hasil_konsultasi"] ?? "";

$tindakLanjut =
    $dataKonsultasi["tindak_lanjut"] ?? "";

/*
|--------------------------------------------------------------------------
| Memproses perubahan konsultasi
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hasilKonsultasiForm = trim(
        $_POST["hasil_konsultasi"] ?? ""
    );

    $tindakLanjut = trim(
        $_POST["tindak_lanjut"] ?? ""
    );

    if ($hasilKonsultasiForm === "") {
        $pesanError =
            "Hasil konsultasi wajib diisi.";
    } elseif ($tindakLanjut === "") {
        $pesanError =
            "Tindak lanjut wajib diisi.";
    }

    if ($pesanError === "") {
        $stmtUpdate = mysqli_prepare(
            $conn,
            "UPDATE konsultasi
             SET
                hasil_konsultasi = ?,
                tindak_lanjut = ?
             WHERE id_konsultasi = ?
             AND id_petugas = ?"
        );

        if (!$stmtUpdate) {
            $pesanError =
                "Gagal menyiapkan perubahan konsultasi: "
                . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmtUpdate,
                "ssii",
                $hasilKonsultasiForm,
                $tindakLanjut,
                $idKonsultasi,
                $idUserAktif
            );

            if (mysqli_stmt_execute($stmtUpdate)) {
                mysqli_stmt_close($stmtUpdate);

                header(
                    "Location: data_konsultasi.php?pesan=edit_berhasil"
                );
                exit;
            }

            $pesanError =
                "Data konsultasi gagal diperbarui: "
                . mysqli_stmt_error($stmtUpdate);

            mysqli_stmt_close($stmtUpdate);
        }
    }
}

$tanggalTampil = "-";

if (!empty($dataKonsultasi["tanggal"])) {
    $tanggalTampil = date(
        "d-m-Y",
        strtotime(
            $dataKonsultasi["tanggal"]
        )
    );
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
?>

<div class="layout-wrapper">

    <?php require_once "../includes/sidebar.php"; ?>

    <main class="main-content">

        <div
            class="d-flex flex-column flex-md-row
            justify-content-between align-items-md-center
            gap-3 mb-4"
        >
            <div>
                <h2 class="mb-1">
                    Isi Hasil Konsultasi
                </h2>

                <p class="text-muted mb-0">
                    Tinjau keluhan orang tua lalu isi hasil
                    konsultasi dan tindak lanjut.
                </p>
            </div>

            <a
                href="data_konsultasi.php"
                class="btn btn-outline-secondary"
            >
                Kembali
            </a>
        </div>

        <?php if ($pesanError !== ""): ?>

            <div
                class="alert alert-danger
                alert-dismissible fade show"
                role="alert"
            >
                <?= htmlspecialchars(
                    $pesanError,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>

        <?php endif; ?>

        <div class="card content-card mb-4">

            <div class="card-body p-4">

                <h5 class="mb-3">
                    Informasi Konsultasi
                </h5>

                <div class="table-responsive">

                    <table
                        class="table table-bordered align-middle mb-0"
                    >

                        <tr>
                            <th width="30%">
                                Tanggal Konsultasi
                            </th>

                            <td>
                                <?= htmlspecialchars(
                                    $tanggalTampil,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Nama Balita</th>

                            <td>
                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nama_balita"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>NIK Balita</th>

                            <td>
                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nik_balita"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Nama Ibu</th>

                            <td>
                                <?= htmlspecialchars(
                                    $dataKonsultasi[
                                        "nama_ibu"
                                    ] ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Keluhan</th>

                            <td>
                                <?= nl2br(
                                    htmlspecialchars(
                                        $dataKonsultasi[
                                            "keluhan"
                                        ] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ) ?>
                            </td>
                        </tr>

                    </table>

                </div>

            </div>

        </div>

        <div class="card content-card">

            <div class="card-body p-4">

                <form method="POST">

                    <div class="mb-3">

                        <label
                            for="hasil_konsultasi"
                            class="form-label"
                        >
                            Hasil Konsultasi
                        </label>

                        <textarea
                            id="hasil_konsultasi"
                            name="hasil_konsultasi"
                            class="form-control"
                            rows="5"
                            placeholder="Tuliskan hasil pemeriksaan dan saran gizi"
                            required
                        ><?= htmlspecialchars(
                            $hasilKonsultasiForm,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>

                    </div>

                    <div class="mb-3">

                        <label
                            for="tindak_lanjut"
                            class="form-label"
                        >
                            Tindak Lanjut
                        </label>

                        <textarea
                            id="tindak_lanjut"
                            name="tindak_lanjut"
                            class="form-control"
                            rows="5"
                            placeholder="Tuliskan rencana pemantauan atau tindakan berikutnya"
                            required
                        ><?= htmlspecialchars(
                            $tindakLanjut,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>

                    </div>

                    <div class="d-flex flex-wrap gap-2">

                        <button
                            type="submit"
                            class="btn btn-warning"
                        >
                            Simpan Hasil Konsultasi
                        </button>

                        <a
                            href="detail_konsultasi.php?id=<?= (int) $idKonsultasi ?>"
                            class="btn btn-outline-secondary"
                        >
                            Batal
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>