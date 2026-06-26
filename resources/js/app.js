import Chart from "chart.js/auto";
import flatpickr from "flatpickr";
import TomSelect from "tom-select";
import "./bootstrap";
import "./../../vendor/power-components/livewire-powergrid/dist/powergrid";

// @ts-ignore
window.Chart = Chart;
window.TomSelect = TomSelect;
window.flatpickr = flatpickr;
window.reportChart = (config) => ({
    chart: null,
    config,
    init() {
        if (!this.$refs.canvas || !window.Chart) {
            return;
        }

        this.chart = new window.Chart(this.$refs.canvas, {
            type: this.config.type,
            data: {
                labels: this.config.labels,
                datasets: this.config.datasets,
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                interaction: {
                    intersect: false,
                    mode: "index",
                },
                plugins: {
                    legend: {
                        display: this.config.datasets.length > 1,
                        position: "bottom",
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = context.parsed?.y ?? context.parsed ?? 0;

                                if (this.config.valueFormat === "currency" && window.formatMoney) {
                                    return `${context.dataset.label}: ${window.formatMoney(value)}`;
                                }

                                return `${context.dataset.label}: ${Number(value).toLocaleString()}`;
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => {
                                if (this.config.valueFormat === "currency" && window.formatMoney) {
                                    return window.formatMoney(value);
                                }

                                return Number(value).toLocaleString();
                            },
                        },
                    },
                },
            },
        });
    },
});

// import Alpine from 'alpinejs';
// window.Alpine = Alpine;
// Alpine.start();
