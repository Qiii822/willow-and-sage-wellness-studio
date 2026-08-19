document.addEventListener('DOMContentLoaded', function () {

    if (typeof Chart === 'undefined') {
        return;
    }

    Chart.defaults.font.family =
        '-apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif';

    Chart.defaults.color = '#7c7773';


    /* =========================================================
       Customer age chart
       ========================================================= */

    const ageCanvas =
        document.getElementById('ageChart');

    if (ageCanvas) {

        const totalCustomers =
            Number(ageCanvas.dataset.totalCustomers || 0);

        const centreTextPlugin = {
            id: 'centreText',

            afterDraw(chart) {

                const {
                    ctx,
                    chartArea: {
                        left,
                        right,
                        top,
                        bottom
                    }
                } = chart;

                const centreX =
                    (left + right) / 2;

                const centreY =
                    (top + bottom) / 2 - 12;

                ctx.save();

                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                ctx.fillStyle = '#2f2d2b';

                ctx.font =
                    '700 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Arial';

                ctx.fillText(
                    totalCustomers.toLocaleString(),
                    centreX,
                    centreY
                );

                ctx.fillStyle = '#8a847f';

                ctx.font =
                    '500 12px -apple-system, BlinkMacSystemFont, "Segoe UI", Arial';

                ctx.fillText(
                    'Customers',
                    centreX,
                    centreY + 25
                );

                ctx.restore();
            }
        };

        new Chart(ageCanvas, {
            type: 'doughnut',

            data: {
                labels: [
                    '18–24',
                    '25–34',
                    '35–44',
                    '45–54',
                    '55+'
                ],

                datasets: [{
                    data: [
                        18,
                        34,
                        27,
                        14,
                        7
                    ],

                    backgroundColor: [
                        '#6f879b',
                        '#8fa69a',
                        '#b79a77',
                        '#aca3b4',
                        '#d4cec6'
                    ],

                    borderColor: '#fbfaf8',
                    borderWidth: 4,

                    hoverOffset: 4
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                cutout: '72%',

                plugins: {
                    legend: {
                        position: 'bottom',

                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',

                            boxWidth: 8,
                            boxHeight: 8,

                            padding: 18,

                            color: '#6f6a66',

                            font: {
                                size: 12,
                                weight: 500
                            }
                        }
                    },

                    tooltip: {
                        backgroundColor: '#2f2d2b',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',

                        padding: 12,
                        cornerRadius: 10,

                        displayColors: true
                    }
                }
            },

            plugins: [
                centreTextPlugin
            ]
        });
    }


    /* =========================================================
       Booking trend chart
       ========================================================= */

    const bookingCanvas =
        document.getElementById('bookingChart');

    if (bookingCanvas) {

        const ctx =
            bookingCanvas.getContext('2d');

        const gradient =
            ctx.createLinearGradient(
                0,
                0,
                0,
                260
            );

        gradient.addColorStop(
            0,
            'rgba(88, 114, 138, 0.18)'
        );

        gradient.addColorStop(
            1,
            'rgba(88, 114, 138, 0.01)'
        );

        new Chart(bookingCanvas, {
            type: 'line',

            data: {
                labels: [
                    'Mon',
                    'Tue',
                    'Wed',
                    'Thu',
                    'Fri',
                    'Sat',
                    'Sun'
                ],

                datasets: [{
                    label: 'Bookings',

                    data: [
                        12,
                        18,
                        15,
                        24,
                        28,
                        35,
                        31
                    ],

                    borderColor: '#58728a',
                    backgroundColor: gradient,

                    fill: true,

                    borderWidth: 2.5,
                    tension: 0.4,

                    pointRadius: 0,
                    pointHoverRadius: 5,

                    pointBackgroundColor: '#58728a',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: 'index'
                },

                plugins: {
                    legend: {
                        display: false
                    },

                    tooltip: {
                        backgroundColor: '#2f2d2b',

                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',

                        padding: 12,
                        cornerRadius: 10,

                        displayColors: false,

                        callbacks: {
                            label: function (context) {
                                return context.parsed.y
                                    + ' bookings';
                            }
                        }
                    }
                },

                scales: {
                    x: {
                        border: {
                            display: false
                        },

                        grid: {
                            display: false
                        },

                        ticks: {
                            color: '#8a847f',

                            font: {
                                size: 11
                            },

                            padding: 8
                        }
                    },

                    y: {
                        beginAtZero: true,

                        border: {
                            display: false
                        },

                        grid: {
                            color:
                                'rgba(120, 115, 110, 0.08)',

                            drawTicks: false
                        },

                        ticks: {
                            color: '#9a948f',

                            stepSize: 10,

                            padding: 10,

                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }

});
