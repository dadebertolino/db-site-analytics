/**
 * DB Site Analytics — Grafici della dashboard admin.
 * Chart.js è incluso nel plugin (assets/js/vendor), nessuna CDN esterna.
 * I dati arrivano dal template in window.dbsaChart.
 */
(function () {
    'use strict';

    var data = window.dbsaChart;
    if (!data || typeof Chart === 'undefined') return;

    // Grafico linee visite
    var ctxLine = document.getElementById('dbsa-chart-views');
    if (ctxLine) {
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: data.i18n.pageviews,
                        data: data.pageviews,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34,113,177,0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    },
                    {
                        label: data.i18n.visitors,
                        data: data.visitors,
                        borderColor: '#1d6e3f',
                        backgroundColor: 'rgba(29,110,63,0.06)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { ticks: { maxTicksLimit: 15 } }
                }
            }
        });
    }

    // Donut device
    var ctxDev = document.getElementById('dbsa-chart-devices');
    if (ctxDev && data.devices.data.length) {
        new Chart(ctxDev, {
            type: 'doughnut',
            data: {
                labels: data.devices.labels,
                datasets: [{
                    data: data.devices.data,
                    backgroundColor: ['#2271b1', '#1d6e3f', '#dba617'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
})();
