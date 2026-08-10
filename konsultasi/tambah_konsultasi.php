<?php

require_once "../auth/session.php";
require_once "../includes/cek_role.php";
require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hak akses
|--------------------------------------------------------------------------
|
| Konsultasi baru hanya diajukan oleh Orang Tua.
| Petugas Gizi menanggapi melalui edit_konsultasi.php.
|
*/

cekRole([
    "orang_tua"
]);

$judulHalaman =
    "Ajukan Konsultasi | Sistem Deteksi Stunting";

$idUserAktif =
    (int) ($_SESSION["id_user"] ?? 0);

$pesanError = "";

$idBalita = "";
$idPetugas = "";
$tanggal = date("Y-m-d");
$keluhan = "";

/*
|--------------------------------------------------------------------------
| Mengambil anak milik Orang Tua
|--------------------------------------------------------------------------
*/

$stmtBalita = mysqli_prepare(
    $conn,
    "SELECT
        b.id_balita,
        b.nama_balita,
        b.nik_balita,
        b.id_puskesmas,
        p.nama_puskesmas
     FROM balita AS b
     LEFT JOIN puskesmas AS p
        ON b.id_puskesmas = p.id_puskesmas
     WHERE b.id_user = ?
     ORDER BY b.nama_balita ASC"
);

if (!$stmtBalita) {
    die(
        "Gagal mengambil data balita: "
        . mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $stmtBalita,
    "i",
    $idUserAktif
);

mysqli_stmt_execute(
    $stmtBalita
);

$queryBalita =
    mysqli_stmt_get_result(
        $stmtBalita
    );

if (!$queryBalita) {
    die(
        "Gagal membaca data balita."
    );
}

$totalBalita =
    mysqli_num_rows(
        $queryBalita
    );

/*
|--------------------------------------------------------------------------
| Mengambil Petugas Gizi
|--------------------------------------------------------------------------
|
| Tampilan browser memfilter Petugas Gizi berdasarkan Puskesmas balita.
| Server tetap memvalidasi ulang saat penyimpanan.
|
*/

$queryPetugas = mysqli_query(
    $conn,
    "SELECT
        u.id_user,
        u.nama,
        u.username,
        u.id_puskesmas,
        p.nama_puskesmas
     FROM pengguna AS u
     LEFT JOIN puskesmas AS p
        ON u.id_puskesmas = p.id_puskesmas
     WHERE u.role = 'petugas_gizi'
     AND u.id_puskesmas IS NOT NULL
     ORDER BY u.nama ASC"
);

if (!$queryPetugas) {
    die(
        "Gagal mengambil data Petugas Gizi: "
        . mysqli_error($conn)
    );
}

$totalPetugas =
    mysqli_num_rows(
        $queryPetugas
    );

/*
|--------------------------------------------------------------------------
| Memproses pengajuan konsultasi
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $idBalita = filter_input(
        INPUT_POST,
        "id_balita",
        FILTER_VALIDATE_INT
    );

    $idPetugas = filter_input(
        INPUT_POST,
        "id_petugas",
        FILTER_VALIDATE_INT
    );

    $tanggal = trim(
        $_POST["tanggal"] ?? ""
    );

    $keluhan = trim(
        $_POST["keluhan"] ?? ""
    );

    if (!$idBalita) {

        $pesanError =
            "Silakan pilih balita.";

    } elseif (!$idPetugas) {

        $pesanError =
            "Silakan pilih Petugas Gizi.";

    } elseif ($tanggal === "") {

        $pesanError =
            "Tanggal konsultasi wajib diisi.";

    } else {

        $objekTanggal =
            DateTime::createFromFormat(
                "Y-m-d",
                $tanggal
            );

        $tanggalValid = (
            $objekTanggal !== false
            && $objekTanggal->format(
                "Y-m-d"
            ) === $tanggal
        );

        if (!$tanggalValid) {

            $pesanError =
                "Tanggal konsultasi tidak valid.";

        } elseif (
            $tanggal > date("Y-m-d")
        ) {

            $pesanError =
                "Tanggal konsultasi tidak boleh melebihi hari ini.";
        }
    }

    if (
        $pesanError === ""
        && $keluhan === ""
    ) {
        $pesanError =
            "Keluhan wajib diisi.";
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi anak milik akun dan Puskesmas anak
    |--------------------------------------------------------------------------
    */

    $idPuskesmasBalita = null;

    if ($pesanError === "") {

        $cekBalita = mysqli_prepare(
            $conn,
            "SELECT
                id_balita,
                id_puskesmas
             FROM balita
             WHERE id_balita = ?
             AND id_user = ?
             LIMIT 1"
        );

        if (!$cekBalita) {

            $pesanError =
                "Gagal memeriksa data balita.";

        } else {

            mysqli_stmt_bind_param(
                $cekBalita,
                "ii",
                $idBalita,
                $idUserAktif
            );

            mysqli_stmt_execute(
                $cekBalita
            );

            $hasilCekBalita =
                mysqli_stmt_get_result(
                    $cekBalita
                );

            $dataCekBalita =
                mysqli_fetch_assoc(
                    $hasilCekBalita
                );

            mysqli_stmt_close(
                $cekBalita
            );

            if (!$dataCekBalita) {

                $pesanError =
                    "Data balita tidak ditemukan atau tidak dapat diakses.";

            } elseif (
                empty(
                    $dataCekBalita[
                        "id_puskesmas"
                    ]
                )
            ) {

                $pesanError =
                    "Balita belum terhubung dengan Puskesmas.";

            } else {

                $idPuskesmasBalita =
                    (int) $dataCekBalita[
                        "id_puskesmas"
                    ];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi Petugas Gizi satu Puskesmas dengan balita
    |--------------------------------------------------------------------------
    */

    if ($pesanError === "") {

        $cekPetugas = mysqli_prepare(
            $conn,
            "SELECT id_user
             FROM pengguna
             WHERE id_user = ?
             AND role = 'petugas_gizi'
             AND id_puskesmas = ?
             LIMIT 1"
        );

        if (!$cekPetugas) {

            $pesanError =
                "Gagal memeriksa Petugas Gizi.";

        } else {

            mysqli_stmt_bind_param(
                $cekPetugas,
                "ii",
                $idPetugas,
                $idPuskesmasBalita
            );

            mysqli_stmt_execute(
                $cekPetugas
            );

            $hasilCekPetugas =
                mysqli_stmt_get_result(
                    $cekPetugas
                );

            $dataPetugas =
                mysqli_fetch_assoc(
                    $hasilCekPetugas
                );

            mysqli_stmt_close(
                $cekPetugas
            );

            if (!$dataPetugas) {
                $pesanError =
                    "Petugas Gizi tidak ditemukan atau tidak berasal dari Puskesmas yang sama.";
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan pengajuan
    |--------------------------------------------------------------------------
    |
    | Keluhan berasal dari Orang Tua dan tidak memiliki form edit setelah
    | berhasil dikirim. Hasil konsultasi serta tindak lanjut masih NULL
    | sampai Petugas Gizi memberikan tanggapan.
    |
    */

    if ($pesanError === "") {

        $stmtSimpan = mysqli_prepare(
            $conn,
            "INSERT INTO konsultasi
            (
                id_balita,
                id_petugas,
                tanggal,
                keluhan,
                hasil_konsultasi,
                tindak_lanjut
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                NULL,
                NULL
            )"
        );

        if (!$stmtSimpan) {

            $pesanError =
                "Gagal menyiapkan penyimpanan konsultasi: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmtSimpan,
                "iiss",
                $idBalita,
                $idPetugas,
                $tanggal,
                $keluhan
            );

            if (
                mysqli_stmt_execute(
                    $stmtSimpan
                )
            ) {

                mysqli_stmt_close(
                    $stmtSimpan
                );

                header(
                    "Location: data_konsultasi.php?pesan=tambah_berhasil"
                );
                exit;
            }

            $pesanError =
                "Konsultasi gagal dikirim: "
                . mysqli_stmt_error(
                    $stmtSimpan
                );

            mysqli_stmt_close(
                $stmtSimpan
            );
        }
    }
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
                        Ajukan Konsultasi
                    </h4>

                    <small class="text-muted">
                        Sampaikan keluhan atau kondisi anak kepada
                        Petugas Gizi di Puskesmas yang sama.
                    </small>

                </div>

                <div class="d-flex flex-wrap gap-2">

                    <span class="badge badge-info">
                        <i class="bi bi-person-heart"></i>
                        Orang Tua
                    </span>

                    <a
                        href="data_konsultasi.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                </div>

            </div>

            <div class="card-body">

                <?php if ($pesanError !== ""): ?>

                    <div
                        class="alert alert-danger
                        alert-dismissible fade show"
                        role="alert"
                    >
                        <i class="bi bi-x-circle me-1"></i>

                        <?= htmlspecialchars(
                            $pesanError,
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

                <?php if ($totalBalita === 0): ?>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Belum ada data anak yang terhubung dengan akun Anda.
                    </div>

                <?php endif; ?>

                <?php if ($totalPetugas === 0): ?>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Belum ada akun Petugas Gizi yang terhubung dengan Puskesmas.
                    </div>

                <?php endif; ?>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Setelah dikirim, <strong>keluhan tidak dapat diedit</strong>.
                    Pastikan isi keluhan sudah benar sebelum menyimpan.
                    Petugas Gizi hanya akan mengisi hasil konsultasi dan tindak lanjut.
                </div>

                <form method="POST">

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">

                            <label
                                for="id_balita"
                                class="form-label"
                            >
                                Nama Balita
                            </label>

                            <select
                                id="id_balita"
                                name="id_balita"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    -- Pilih Balita --
                                </option>

                                <?php
                                mysqli_data_seek(
                                    $queryBalita,
                                    0
                                );
                                ?>

                                <?php while (
                                    $balita =
                                        mysqli_fetch_assoc(
                                            $queryBalita
                                        )
                                ): ?>

                                    <option
                                        value="<?= (int) $balita[
                                            "id_balita"
                                        ]; ?>"
                                        data-puskesmas="<?= (int) (
                                            $balita[
                                                "id_puskesmas"
                                            ] ?? 0
                                        ); ?>"
                                        <?= (int) $idBalita ===
                                            (int) $balita[
                                                "id_balita"
                                            ]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $balita[
                                                "nama_balita"
                                            ]
                                            . " - "
                                            . $balita[
                                                "nik_balita"
                                            ]
                                            . " | "
                                            . (
                                                $balita[
                                                    "nama_puskesmas"
                                                ]
                                                ?? "Belum ada Puskesmas"
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                            <div class="form-text">
                                Hanya anak yang terhubung dengan akun Anda.
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <label
                                for="id_petugas"
                                class="form-label"
                            >
                                Petugas Gizi
                            </label>

                            <select
                                id="id_petugas"
                                name="id_petugas"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    -- Pilih Petugas Gizi --
                                </option>

                                <?php
                                mysqli_data_seek(
                                    $queryPetugas,
                                    0
                                );
                                ?>

                                <?php while (
                                    $petugas =
                                        mysqli_fetch_assoc(
                                            $queryPetugas
                                        )
                                ): ?>

                                    <option
                                        value="<?= (int) $petugas[
                                            "id_user"
                                        ]; ?>"
                                        data-puskesmas="<?= (int) (
                                            $petugas[
                                                "id_puskesmas"
                                            ] ?? 0
                                        ); ?>"
                                        <?= (int) $idPetugas ===
                                            (int) $petugas[
                                                "id_user"
                                            ]
                                            ? "selected"
                                            : ""; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $petugas[
                                                "nama"
                                            ]
                                            . " ("
                                            . $petugas[
                                                "username"
                                            ]
                                            . ")",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>

                                <?php endwhile; ?>

                            </select>

                            <div class="form-text">
                                Hanya Petugas Gizi dari Puskesmas
                                yang sama dengan anak yang dapat dipilih.
                            </div>

                            <div
                                id="pesanPetugasKosong"
                                class="form-text text-warning d-none"
                            >
                                Belum ada Petugas Gizi pada Puskesmas anak ini.
                            </div>

                        </div>

                        <div class="col-12 col-lg-4">

                            <label
                                for="tanggal"
                                class="form-label"
                            >
                                Tanggal Konsultasi
                            </label>

                            <input
                                type="date"
                                id="tanggal"
                                name="tanggal"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $tanggal,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>"
                                max="<?= date("Y-m-d"); ?>"
                                required
                            >

                        </div>

                        <div class="col-12 col-lg-8">

                            <label
                                for="keluhan"
                                class="form-label"
                            >
                                Keluhan / Kondisi yang Dikonsultasikan
                            </label>

                            <textarea
                                id="keluhan"
                                name="keluhan"
                                class="form-control"
                                rows="4"
                                placeholder="Tuliskan keluhan atau kondisi yang ingin dikonsultasikan"
                                required
                            ><?= htmlspecialchars(
                                $keluhan,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?></textarea>

                            <div class="form-text">
                                Tuliskan kondisi secara singkat dan jelas.
                                Keluhan akan terkunci setelah dikirim.
                            </div>

                        </div>

                    </div>

                    <hr>

                    <div class="form-actions">

                        <button
                            type="submit"
                            id="tombolSimpan"
                            class="btn btn-primary"
                            <?= (
                                $totalBalita === 0
                                || $totalPetugas === 0
                            )
                                ? "disabled"
                                : ""; ?>
                        >
                            <i class="bi bi-send"></i>
                            Kirim Konsultasi
                        </button>

                        <a
                            href="data_konsultasi.php"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-x-circle"></i>
                            Batal
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        const balitaSelect =
            document.getElementById(
                "id_balita"
            );

        const petugasSelect =
            document.getElementById(
                "id_petugas"
            );

        const pesanPetugasKosong =
            document.getElementById(
                "pesanPetugasKosong"
            );

        const tombolSimpan =
            document.getElementById(
                "tombolSimpan"
            );

        if (
            !balitaSelect
            || !petugasSelect
        ) {
            return;
        }

        const semuaPetugas =
            Array.from(
                petugasSelect.options
            ).map(
                function (option) {
                    return {
                        value: option.value,
                        text: option.text,
                        puskesmas:
                            option.dataset.puskesmas
                            || ""
                    };
                }
            );

        function filterPetugas() {

            const optionBalita =
                balitaSelect.options[
                    balitaSelect.selectedIndex
                ];

            const idPuskesmas =
                optionBalita
                    ? optionBalita.dataset.puskesmas
                    : "";

            const nilaiSebelumnya =
                petugasSelect.value;

            petugasSelect.innerHTML = "";

            const defaultOption =
                document.createElement(
                    "option"
                );

            defaultOption.value = "";
            defaultOption.textContent =
                "-- Pilih Petugas Gizi --";

            petugasSelect.appendChild(
                defaultOption
            );

            let jumlahCocok = 0;

            semuaPetugas.forEach(
                function (petugas) {

                    if (
                        petugas.value === ""
                    ) {
                        return;
                    }

                    if (
                        idPuskesmas !== ""
                        && petugas.puskesmas ===
                            idPuskesmas
                    ) {

                        const option =
                            document.createElement(
                                "option"
                            );

                        option.value =
                            petugas.value;

                        option.textContent =
                            petugas.text;

                        option.dataset.puskesmas =
                            petugas.puskesmas;

                        if (
                            petugas.value ===
                            nilaiSebelumnya
                        ) {
                            option.selected =
                                true;
                        }

                        petugasSelect.appendChild(
                            option
                        );

                        jumlahCocok++;
                    }
                }
            );

            if (pesanPetugasKosong) {
                pesanPetugasKosong.classList.toggle(
                    "d-none",
                    idPuskesmas === ""
                    || jumlahCocok > 0
                );
            }

            if (tombolSimpan) {

                const dataDasarTersedia =
                    balitaSelect.options.length > 1
                    && semuaPetugas.length > 1;

                tombolSimpan.disabled =
                    !dataDasarTersedia
                    || (
                        idPuskesmas !== ""
                        && jumlahCocok === 0
                    );
            }
        }

        balitaSelect.addEventListener(
            "change",
            filterPetugas
        );

        filterPetugas();
    }
);
</script>

<?php

if (
    isset($stmtBalita)
    && $stmtBalita instanceof mysqli_stmt
) {
    mysqli_stmt_close(
        $stmtBalita
    );
}

require_once "../includes/footer.php";

?>