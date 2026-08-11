<?php

session_start();

require_once "../config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Hanya menerima pengiriman form POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Pilihan yang diizinkan
|--------------------------------------------------------------------------
*/

$daftarPendidikan = [
    "Tidak Sekolah",
    "SD",
    "SMP",
    "SMA/SMK",
    "Diploma",
    "D4/S1",
    "S2",
    "S3"
];

$daftarPekerjaan = [
    "Ibu Rumah Tangga",
    "PNS/TNI/POLRI",
    "Pegawai Swasta",
    "Wiraswasta",
    "Buruh",
    "Petani",
    "Nelayan",
    "Tenaga Kesehatan",
    "Guru/Dosen",
    "Lainnya"
];

/*
|--------------------------------------------------------------------------
| Mengambil input akun
|--------------------------------------------------------------------------
*/

$nama =
    trim(
        $_POST["nama"] ?? ""
    );

$username =
    trim(
        $_POST["username"] ?? ""
    );

$password =
    $_POST["password"] ?? "";

$konfirmasiPassword =
    $_POST["konfirmasi_password"] ?? "";

$idPuskesmas =
    filter_input(
        INPUT_POST,
        "id_puskesmas",
        FILTER_VALIDATE_INT
    );

/*
|--------------------------------------------------------------------------
| Mengambil input Profil Ibu
|--------------------------------------------------------------------------
*/

$nikIbu =
    preg_replace(
        "/\D/",
        "",
        trim(
            $_POST["nik_ibu"] ?? ""
        )
    );

$namaIbu =
    trim(
        $_POST["nama_ibu"] ?? ""
    );

$noHp =
    trim(
        $_POST["no_hp"] ?? ""
    );

$alamat =
    trim(
        $_POST["alamat"] ?? ""
    );

$pendidikanIbu =
    trim(
        $_POST["pendidikan_ibu"] ?? ""
    );

$pekerjaanIbu =
    trim(
        $_POST["pekerjaan_ibu"] ?? ""
    );

/*
|--------------------------------------------------------------------------
| Menyimpan input lama
|--------------------------------------------------------------------------
|
| Password tidak pernah disimpan ke session.
|
*/

$_SESSION["register_old"] = [
    "nama" =>
        $nama,

    "username" =>
        $username,

    "nik_ibu" =>
        $nikIbu,

    "nama_ibu" =>
        $namaIbu,

    "no_hp" =>
        $noHp,

    "alamat" =>
        $alamat,

    "pendidikan_ibu" =>
        $pendidikanIbu,

    "pekerjaan_ibu" =>
        $pekerjaanIbu,

    "id_puskesmas" =>
        (int) $idPuskesmas
];

/*
|--------------------------------------------------------------------------
| Fungsi kembali ke registrasi jika terjadi kesalahan
|--------------------------------------------------------------------------
*/

function kembaliRegister(
    string $pesan
): void
{
    $_SESSION["register_error"] =
        $pesan;

    header(
        "Location: register.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Validasi kolom kosong
|--------------------------------------------------------------------------
*/

if (
    $nama === ""
    || $username === ""
    || $password === ""
    || $konfirmasiPassword === ""
    || !$idPuskesmas
    || $nikIbu === ""
    || $namaIbu === ""
    || $noHp === ""
    || $alamat === ""
    || $pendidikanIbu === ""
    || $pekerjaanIbu === ""
) {
    kembaliRegister(
        "Semua data akun, Puskesmas, dan Profil Ibu wajib diisi."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi nama akun
|--------------------------------------------------------------------------
*/

if (
    mb_strlen($nama) < 3
) {
    kembaliRegister(
        "Nama akun minimal 3 karakter."
    );
}

if (
    mb_strlen($nama) > 100
) {
    kembaliRegister(
        "Nama akun maksimal 100 karakter."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi username
|--------------------------------------------------------------------------
*/

if (
    strlen($username) < 4
) {
    kembaliRegister(
        "Username minimal 4 karakter."
    );
}

if (
    strlen($username) > 50
) {
    kembaliRegister(
        "Username maksimal 50 karakter."
    );
}

if (
    !preg_match(
        "/^[A-Za-z0-9._-]+$/",
        $username
    )
) {
    kembaliRegister(
        "Username hanya boleh menggunakan huruf, angka, titik, garis bawah, atau tanda hubung."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi password
|--------------------------------------------------------------------------
*/

if (
    strlen($password) < 8
) {
    kembaliRegister(
        "Password minimal 8 karakter."
    );
}

if (
    $password !==
    $konfirmasiPassword
) {
    kembaliRegister(
        "Konfirmasi password tidak sama."
    );
}

/*
|--------------------------------------------------------------------------
| Validasi Profil Ibu
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        "/^[0-9]{16}$/",
        $nikIbu
    )
) {
    kembaliRegister(
        "NIK Ibu harus terdiri dari 16 digit angka."
    );
}

if (
    mb_strlen($namaIbu) < 3
    || mb_strlen($namaIbu) > 100
) {
    kembaliRegister(
        "Nama Ibu harus terdiri dari 3 sampai 100 karakter."
    );
}

if (
    !preg_match(
        "/^[0-9+\-\s]{8,20}$/",
        $noHp
    )
) {
    kembaliRegister(
        "Nomor HP tidak valid."
    );
}

if (
    !in_array(
        $pendidikanIbu,
        $daftarPendidikan,
        true
    )
) {
    kembaliRegister(
        "Pilihan pendidikan Ibu tidak valid."
    );
}

if (
    !in_array(
        $pekerjaanIbu,
        $daftarPekerjaan,
        true
    )
) {
    kembaliRegister(
        "Pilihan pekerjaan Ibu tidak valid."
    );
}

/*
|--------------------------------------------------------------------------
| Memvalidasi Puskesmas
|--------------------------------------------------------------------------
|
| Nilai dari browser tidak langsung dipercaya. Puskesmas harus benar-benar
| ada pada tabel master puskesmas.
|
*/

$stmtCekPuskesmas =
    mysqli_prepare(
        $conn,
        "SELECT id_puskesmas
         FROM puskesmas
         WHERE id_puskesmas = ?
         LIMIT 1"
    );

if (!$stmtCekPuskesmas) {
    kembaliRegister(
        "Sistem gagal memeriksa Puskesmas."
    );
}

mysqli_stmt_bind_param(
    $stmtCekPuskesmas,
    "i",
    $idPuskesmas
);

mysqli_stmt_execute(
    $stmtCekPuskesmas
);

$resultCekPuskesmas =
    mysqli_stmt_get_result(
        $stmtCekPuskesmas
    );

$dataPuskesmas =
    mysqli_fetch_assoc(
        $resultCekPuskesmas
    );

mysqli_stmt_close(
    $stmtCekPuskesmas
);

if (!$dataPuskesmas) {
    kembaliRegister(
        "Puskesmas yang dipilih tidak valid."
    );
}

/*
|--------------------------------------------------------------------------
| Memeriksa username
|--------------------------------------------------------------------------
*/

$stmtCekUsername =
    mysqli_prepare(
        $conn,
        "SELECT id_user
         FROM pengguna
         WHERE username = ?
         LIMIT 1"
    );

if (!$stmtCekUsername) {
    kembaliRegister(
        "Sistem gagal memeriksa username."
    );
}

mysqli_stmt_bind_param(
    $stmtCekUsername,
    "s",
    $username
);

mysqli_stmt_execute(
    $stmtCekUsername
);

$resultCekUsername =
    mysqli_stmt_get_result(
        $stmtCekUsername
    );

$dataUsername =
    mysqli_fetch_assoc(
        $resultCekUsername
    );

mysqli_stmt_close(
    $stmtCekUsername
);

if ($dataUsername) {
    kembaliRegister(
        "Username sudah digunakan. Gunakan username lain."
    );
}

/*
|--------------------------------------------------------------------------
| Memeriksa NIK Ibu
|--------------------------------------------------------------------------
|
| Satu NIK Ibu hanya boleh mempunyai satu Profil Ibu.
|
*/

$stmtCekNik =
    mysqli_prepare(
        $conn,
        "SELECT id_orang_tua
         FROM orang_tua
         WHERE nik_ibu = ?
         LIMIT 1"
    );

if (!$stmtCekNik) {
    kembaliRegister(
        "Sistem gagal memeriksa NIK Ibu."
    );
}

mysqli_stmt_bind_param(
    $stmtCekNik,
    "s",
    $nikIbu
);

mysqli_stmt_execute(
    $stmtCekNik
);

$resultCekNik =
    mysqli_stmt_get_result(
        $stmtCekNik
    );

$dataNikIbu =
    mysqli_fetch_assoc(
        $resultCekNik
    );

mysqli_stmt_close(
    $stmtCekNik
);

if ($dataNikIbu) {
    kembaliRegister(
        "NIK Ibu sudah terdaftar pada akun lain."
    );
}

/*
|--------------------------------------------------------------------------
| Membuat password hash
|--------------------------------------------------------------------------
*/

$passwordHash =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );

if (
    $passwordHash === false
) {
    kembaliRegister(
        "Password gagal diproses."
    );
}

/*
|--------------------------------------------------------------------------
| Role otomatis Orang Tua
|--------------------------------------------------------------------------
*/

$role =
    "orang_tua";

/*
|--------------------------------------------------------------------------
| Transaksi registrasi
|--------------------------------------------------------------------------
|
| 1. Buat akun pengguna + Puskesmas pembina
| 2. Ambil id_user baru
| 3. Buat Profil Ibu dengan id_user yang sama
|
| Jika salah satu gagal, seluruh proses dibatalkan.
|
*/

mysqli_begin_transaction(
    $conn
);

try {

    /*
    |--------------------------------------------------------------------------
    | Menyimpan akun pengguna
    |--------------------------------------------------------------------------
    */

    $stmtInsertPengguna =
        mysqli_prepare(
            $conn,
            "INSERT INTO pengguna
            (
                nama,
                username,
                password,
                role,
                id_puskesmas
            )
            VALUES (?, ?, ?, ?, ?)"
        );

    if (!$stmtInsertPengguna) {
        throw new Exception(
            "Sistem gagal menyiapkan data akun."
        );
    }

    mysqli_stmt_bind_param(
        $stmtInsertPengguna,
        "ssssi",
        $nama,
        $username,
        $passwordHash,
        $role,
        $idPuskesmas
    );

    if (
        !mysqli_stmt_execute(
            $stmtInsertPengguna
        )
    ) {
        $kodeErrorPengguna =
            mysqli_stmt_errno(
                $stmtInsertPengguna
            );

        mysqli_stmt_close(
            $stmtInsertPengguna
        );

        if (
            $kodeErrorPengguna === 1062
        ) {
            throw new Exception(
                "Username sudah digunakan."
            );
        }

        throw new Exception(
            "Akun gagal disimpan."
        );
    }

    $idUserBaru =
        (int) mysqli_insert_id(
            $conn
        );

    mysqli_stmt_close(
        $stmtInsertPengguna
    );

    if (
        $idUserBaru < 1
    ) {
        throw new Exception(
            "ID akun baru tidak dapat dibuat."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Menyimpan Profil Ibu
    |--------------------------------------------------------------------------
    */

    $stmtInsertOrangTua =
        mysqli_prepare(
            $conn,
            "INSERT INTO orang_tua
            (
                id_user,
                nik_ibu,
                nama_ibu,
                no_hp,
                alamat,
                pendidikan_ibu,
                pekerjaan_ibu
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

    if (!$stmtInsertOrangTua) {
        throw new Exception(
            "Sistem gagal menyiapkan Profil Ibu."
        );
    }

    mysqli_stmt_bind_param(
        $stmtInsertOrangTua,
        "issssss",
        $idUserBaru,
        $nikIbu,
        $namaIbu,
        $noHp,
        $alamat,
        $pendidikanIbu,
        $pekerjaanIbu
    );

    if (
        !mysqli_stmt_execute(
            $stmtInsertOrangTua
        )
    ) {
        $kodeErrorProfil =
            mysqli_stmt_errno(
                $stmtInsertOrangTua
            );

        $pesanSqlProfil =
            mysqli_stmt_error(
                $stmtInsertOrangTua
            );

        mysqli_stmt_close(
            $stmtInsertOrangTua
        );

        if (
            $kodeErrorProfil === 1062
        ) {
            throw new Exception(
                "Profil Ibu sudah terdaftar."
            );
        }

        throw new Exception(
            "Profil Ibu gagal disimpan: "
            . $pesanSqlProfil
        );
    }

    mysqli_stmt_close(
        $stmtInsertOrangTua
    );

    /*
    |--------------------------------------------------------------------------
    | Selesaikan transaksi
    |--------------------------------------------------------------------------
    */

    mysqli_commit(
        $conn
    );

} catch (Throwable $exception) {

    mysqli_rollback(
        $conn
    );

    kembaliRegister(
        $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Menghapus data sementara
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION["register_old"]
);

unset(
    $_SESSION["register_error"]
);

/*
|--------------------------------------------------------------------------
| Registrasi selesai
|--------------------------------------------------------------------------
|
| Karena akun sudah memiliki Puskesmas pembina dan Profil Ibu dibuat
| dalam transaksi yang sama, akun siap dihubungkan oleh Kader pada
| Puskesmas yang sama ke data balita.
|
*/

header(
    "Location: login.php"
    . "?pesan=registrasi_sukses"
);

exit;