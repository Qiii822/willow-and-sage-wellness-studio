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

});