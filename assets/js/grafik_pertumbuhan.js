document.addEventListener(
    "DOMContentLoaded",
    function () {
        const sumberData =
            document.getElementById(
                "dataGrafikPertumbuhan"
            );

        if (
            !sumberData
            || typeof Chart === "undefined"
        ) {
            return;
        }

        let dataGrafik = null;

        try {
            dataGrafik = JSON.parse(
                sumberData.textContent
            );
        } catch (error) {
            console.error(
                "Data grafik pertumbuhan tidak valid.",
                error
            );

            return;
        }

        const labels =
            dataGrafik.labels || [];

        const beratBadan =
            dataGrafik.beratBadan || [];

        const tinggiBadan =
            dataGrafik.tinggiBadan || [];

        /*
        |--------------------------------------------------------------------------
        | Grafik berat badan
        |--------------------------------------------------------------------------
        */

        const elemenBerat =
            document.getElementById(
                "grafikBeratBadan"
            );

        if (elemenBerat) {
            new Chart(
                elemenBerat,
                {
                    type: "line",

                    data: {
                        labels: labels,

                        datasets: [
                            {
                                label:
                                    "Berat Badan",

                                data:
                                    beratBadan,

                                borderColor:
                                    "#df7894",

                                backgroundColor:
                                    "rgba(223, 120, 148, 0.14)",

                                fill: true,

                                tension: 0.3,

                                pointRadius: 5,

                                pointHoverRadius: 7,

                                spanGaps: true
                            }
                        ]
                    },

                    options: {
                        responsive: true,

                        maintainAspectRatio:
                            false,

                        interaction: {
                            mode: "index",

                            intersect: false
                        },

                        scales: {
                            y: {
                                beginAtZero: false,

                                title: {
                                    display: true,

                                    text:
                                        "Berat Badan (kg)"
                                }
                            },

                            x: {
                                title: {
                                    display: true,

                                    text:
                                        "Umur & Tanggal Pengukuran"
                                }
                            }
                        },

                        plugins: {
                            legend: {
                                display: false
                            },

                            tooltip: {
                                callbacks: {
                                    label:
                                        function (
                                            context
                                        ) {
                                            return (
                                                "BB: "
                                                + context.raw
                                                + " kg"
                                            );
                                        }
                                }
                            }
                        }
                    }
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Grafik tinggi / panjang badan
        |--------------------------------------------------------------------------
        */

        const elemenTinggi =
            document.getElementById(
                "grafikTinggiBadan"
            );

        if (elemenTinggi) {
            new Chart(
                elemenTinggi,
                {
                    type: "line",

                    data: {
                        labels: labels,

                        datasets: [
                            {
                                label:
                                    "Tinggi / Panjang Badan",

                                data:
                                    tinggiBadan,

                                borderColor:
                                    "#78add8",

                                backgroundColor:
                                    "rgba(120, 173, 216, 0.14)",

                                fill: true,

                                tension: 0.3,

                                pointRadius: 5,

                                pointHoverRadius: 7,

                                spanGaps: true
                            }
                        ]
                    },

                    options: {
                        responsive: true,

                        maintainAspectRatio:
                            false,

                        interaction: {
                            mode: "index",

                            intersect: false
                        },

                        scales: {
                            y: {
                                beginAtZero: false,

                                title: {
                                    display: true,

                                    text:
                                        "TB / PB (cm)"
                                }
                            },

                            x: {
                                title: {
                                    display: true,

                                    text:
                                        "Umur & Tanggal Pengukuran"
                                }
                            }
                        },

                        plugins: {
                            legend: {
                                display: false
                            },

                            tooltip: {
                                callbacks: {
                                    label:
                                        function (
                                            context
                                        ) {
                                            return (
                                                "TB/PB: "
                                                + context.raw
                                                + " cm"
                                            );
                                        }
                                }
                            }
                        }
                    }
                }
            );
        }
    }
);
