"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const semuaFormHapus = document.querySelectorAll(".form-hapus");

    semuaFormHapus.forEach(function (form) {
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
});