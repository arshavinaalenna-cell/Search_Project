document.addEventListener(
    "DOMContentLoaded",
    function () {
        const sumberData =
            document.getElementById(
                "dataGrafikLaporan"
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
                "Data grafik laporan tidak valid.",
                error
            );

            return;
        }

        const dataRisiko =
            dataGrafik.risiko || {};

        const dataTren =
            dataGrafik.tren || {};

        const dataStatusGizi =
            dataGrafik.statusGizi || {};

        /*
        |--------------------------------------------------------------------------
        | Grafik komposisi tingkat risiko
        |--------------------------------------------------------------------------
        */

        const elemenRisiko =
            document.getElementById(
                "grafikRisiko"
            );

        if (elemenRisiko) {
            new Chart(
                elemenRisiko,
                {
                    type: "doughnut",

                    data: {
                        labels:
                            dataRisiko.labels || [],

                        datasets: [
                            {
                                data:
                                    dataRisiko.data || [],

                                backgroundColor: [
                                    "#8fd3b6",
                                    "#f6cf72",
                                    "#ea8c9d"
                                ],

                                borderWidth: 2,

                                borderColor:
                                    "#ffffff"
                            }
                        ]
                    },

                    options: {
                        responsive: true,

                        maintainAspectRatio:
                            false,

                        cutout: "62%",

                        plugins: {
                            legend: {
                                position: "bottom"
                            },

                            tooltip: {
                                callbacks: {
                                    label:
                                        function (
                                            context
                                        ) {
                                            const nilai =
                                                context.raw
                                                ?? 0;

                                            return (
                                                context.label
                                                + ": "
                                                + nilai
                                                + " balita"
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
        | Grafik tren jumlah deteksi per bulan
        |--------------------------------------------------------------------------
        */

        const elemenTren =
            document.getElementById(
                "grafikTren"
            );

        if (elemenTren) {
            new Chart(
                elemenTren,
                {
                    type: "line",

                    data: {
                        labels:
                            dataTren.labels || [],

                        datasets: [
                            {
                                label:
                                    "Jumlah Deteksi",

                                data:
                                    dataTren.data || [],

                                borderColor:
                                    "#df7894",

                                backgroundColor:
                                    "rgba(223, 120, 148, 0.15)",

                                fill: true,

                                tension: 0.35,

                                pointRadius: 4,

                                pointHoverRadius: 6
                            }
                        ]
                    },

                    options: {
                        responsive: true,

                        maintainAspectRatio:
                            false,

                        scales: {
                            y: {
                                beginAtZero: true,

                                ticks: {
                                    precision: 0
                                },

                                title: {
                                    display: true,

                                    text:
                                        "Jumlah Deteksi"
                                }
                            },

                            x: {
                                title: {
                                    display: true,

                                    text: "Bulan"
                                }
                            }
                        },

                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Grafik distribusi status gizi
        |--------------------------------------------------------------------------
        */

        const elemenStatusGizi =
            document.getElementById(
                "grafikStatusGizi"
            );

        if (elemenStatusGizi) {
            new Chart(
                elemenStatusGizi,
                {
                    type: "bar",

                    data: {
                        labels:
                            dataStatusGizi.labels || [],

                        datasets: [
                            {
                                label:
                                    "Jumlah Balita",

                                data:
                                    dataStatusGizi.data || [],

                                backgroundColor:
                                    "#9ecdf2",

                                borderColor:
                                    "#78add8",

                                borderWidth: 1,

                                borderRadius: 8,

                                maxBarThickness: 70
                            }
                        ]
                    },

                    options: {
                        responsive: true,

                        maintainAspectRatio:
                            false,

                        scales: {
                            y: {
                                beginAtZero: true,

                                ticks: {
                                    precision: 0
                                },

                                title: {
                                    display: true,

                                    text:
                                        "Jumlah Balita"
                                }
                            },

                            x: {
                                ticks: {
                                    maxRotation: 0,

                                    minRotation: 0
                                }
                            }
                        },

                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                }
            );
        }
    }
);
