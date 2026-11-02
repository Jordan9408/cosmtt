/**
 * stats.js - Initialisation des graphiques de statistiques avec Chart.js
 */
document.addEventListener('DOMContentLoaded', () => {
    const dataElement = document.getElementById('statsData');
    if (!dataElement) return;

    let stats;
    try {
        stats = JSON.parse(dataElement.textContent);
    } catch (e) {
        console.error('Erreur lecture données statistiques:', e);
        return;
    }

    // 1. Graphique Heures (Barres)
    const hourCanvas = document.getElementById('hourChart');
    if (hourCanvas) {
        new Chart(hourCanvas, {
            type: 'bar',
            data: {
                labels: Array.from({ length: 24 }, (_, i) => i + "h"),
                datasets: [{
                    label: 'Visites',
                    data: stats.hourlyData || [],
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            callback: function(value) {
                                if (Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Graphique Appareils (Camembert)
    const deviceCanvas = document.getElementById('deviceChart');
    if (deviceCanvas) {
        new Chart(deviceCanvas, {
            type: 'pie',
            data: {
                labels: stats.devices?.labels || [],
                datasets: [{
                    data: stats.devices?.values || [],
                    backgroundColor: ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56']
                }]
            }
        });
    }

    // 3. Graphique Navigateurs (Beignet)
    const browserCanvas = document.getElementById('browserChart');
    if (browserCanvas) {
        new Chart(browserCanvas, {
            type: 'doughnut',
            data: {
                labels: stats.browsers?.labels || [],
                datasets: [{
                    data: stats.browsers?.values || [],
                    backgroundColor: ['#4bc0c0', '#ff9f40', '#9966ff', '#4caf50']
                }]
            }
        });
    }

    // 4. Graphique Évolution Mensuelle (Courbe)
    const monthlyCanvas = document.getElementById('monthlyChart');
    if (monthlyCanvas) {
        new Chart(monthlyCanvas, {
            type: 'line',
            data: {
                labels: stats.monthly?.labels || [],
                datasets: [{
                    label: 'Visites par mois',
                    data: stats.monthly?.values || [],
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        min: 0,
                        suggestedMax: 5,
                        ticks: {
                            stepSize: 5
                        }
                    }
                }
            }
        });
    }
});
