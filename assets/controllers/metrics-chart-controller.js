import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for Chart.js metrics charts (FR70).
 *
 * Usage:
 * <canvas data-controller="metrics-chart"
 *         data-metrics-chart-type-value="line"
 *         data-metrics-chart-data-value='[{"month":"2026-01","count":10}]'>
 * </canvas>
 */
export default class extends Controller {
    static values = {
        type: { type: String, default: 'line' },
        data: Array,
        distribution: Object,
        color: { type: String, default: 'blue' }
    };

    chart = null;
    waitTimeout = null;
    waitAttempts = 0;
    maxWaitAttempts = 50; // 5 seconds max wait

    connect() {
        this.waitForChartJs();
    }

    disconnect() {
        // Clear any pending timeout
        if (this.waitTimeout) {
            clearTimeout(this.waitTimeout);
            this.waitTimeout = null;
        }
        // Destroy chart instance to free memory
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }

    waitForChartJs() {
        // Wait for Chart.js to be loaded from CDN
        if (typeof Chart === 'undefined') {
            this.waitAttempts++;
            if (this.waitAttempts >= this.maxWaitAttempts) {
                console.error('Chart.js failed to load after 5 seconds');
                return;
            }
            this.waitTimeout = setTimeout(() => this.waitForChartJs(), 100);
            return;
        }

        this.initChart();
    }

    initChart() {
        const ctx = this.element.getContext('2d');

        // Get theme-aware colors for chart text and grid
        this.themeColors = this.getThemeColors();

        if (this.hasDistributionValue) {
            this.createDistributionChart(ctx);
        } else if (this.hasDataValue) {
            this.createTimeSeriesChart(ctx);
        }
    }

    getThemeColors() {
        const computedStyle = getComputedStyle(document.documentElement);
        return {
            text: computedStyle.getPropertyValue('--text-secondary').trim() || '#6b7280',
            grid: computedStyle.getPropertyValue('--border-default').trim() || '#e5e7eb',
        };
    }

    createTimeSeriesChart(ctx) {
        const data = this.dataValue;
        const labels = data.map(item => this.formatMonthLabel(item.month));
        const values = data.map(item => item.count);

        const colorConfig = this.getColorConfig();

        this.chart = new Chart(ctx, {
            type: this.typeValue,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Nombre',
                    data: values,
                    borderColor: colorConfig.border,
                    backgroundColor: colorConfig.background,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: this.themeColors.text
                        },
                        grid: {
                            color: this.themeColors.grid
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: this.themeColors.text
                        },
                        grid: {
                            color: this.themeColors.grid
                        }
                    }
                }
            }
        });
    }

    createDistributionChart(ctx) {
        const distribution = this.distributionValue;
        const labels = Object.keys(distribution);
        const values = Object.values(distribution);

        const colors = [
            'rgba(59, 130, 246, 0.8)',   // blue
            'rgba(34, 197, 94, 0.8)',    // green
            'rgba(234, 179, 8, 0.8)',    // yellow
            'rgba(168, 85, 247, 0.8)',   // purple
            'rgba(239, 68, 68, 0.8)',    // red
        ];

        this.chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels.map(l => l + ' joueurs'),
                datasets: [{
                    label: 'Tournois',
                    data: values,
                    backgroundColor: colors,
                    borderColor: colors.map(c => c.replace('0.8', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: this.themeColors.text
                        },
                        grid: {
                            color: this.themeColors.grid
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: this.themeColors.text
                        },
                        grid: {
                            color: this.themeColors.grid
                        }
                    }
                }
            }
        });
    }

    getColorConfig() {
        const configs = {
            blue: {
                border: 'rgb(59, 130, 246)',
                background: 'rgba(59, 130, 246, 0.1)'
            },
            green: {
                border: 'rgb(34, 197, 94)',
                background: 'rgba(34, 197, 94, 0.1)'
            },
            purple: {
                border: 'rgb(168, 85, 247)',
                background: 'rgba(168, 85, 247, 0.1)'
            },
            yellow: {
                border: 'rgb(234, 179, 8)',
                background: 'rgba(234, 179, 8, 0.1)'
            }
        };

        return configs[this.colorValue] || configs.blue;
    }

    formatMonthLabel(monthStr) {
        if (!monthStr) return '';

        const [year, month] = monthStr.split('-');
        const monthNames = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin',
                           'Juil', 'Aout', 'Sep', 'Oct', 'Nov', 'Dec'];

        return monthNames[parseInt(month, 10) - 1] + ' ' + year.slice(2);
    }
}
