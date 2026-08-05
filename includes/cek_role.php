<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Membatasi akses halaman berdasarkan role.
 *
 * Contoh:
 * cekRole(["dinkes"]);
 * cekRole(["dinkes", "ahli_gizi"]);
 */
function cekRole(array $roleDiizinkan): void
{
    if (!isset($_SESSION["id_user"])) {
        header("Location: ../auth/login.php?pesan=belum_login");
        exit;
    }

    $rolePengguna = $_SESSION["role"] ?? "";

    if (!in_array($rolePengguna, $roleDiizinkan, true)) {
        http_response_code(403);

        echo "
            <h2>Akses Ditolak</h2>
            <p>Kamu tidak memiliki hak akses ke halaman ini.</p>
            <a href='../dashboard/dashboard.php'>
                Kembali ke Dashboard
            </a>
        ";

        exit;
    }
}