"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const semuaFormHapusUser =
        document.querySelectorAll(".form-hapus");

    semuaFormHapusUser.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            const nama = form.dataset.nama || "pengguna";

            const setuju = window.confirm(
                "Yakin ingin menghapus pengguna " + nama + "?"
            );

            if (!setuju) {
                event.preventDefault();
            }
        });
    });

    const semuaFormHapusBalita =
        document.querySelectorAll(".form-hapus-balita");

    semuaFormHapusBalita.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            const nama = form.dataset.nama || "balita";

            const setuju = window.confirm(
                "Yakin ingin menghapus data balita " + nama + "?"
            );

            if (!setuju) {
                event.preventDefault();
            }
        });
    });
});