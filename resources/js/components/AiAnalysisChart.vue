<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Title, Tooltip, Legend, TooltipItem, ChartType } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Title, Tooltip, Legend);

const props = withDefaults(defineProps<{
    chartType: 'bar' | 'line';
    labels: string[];
    values: (string | number)[];
    title?: string;
    label?: string;
}>(), {
    chartType: 'bar',
    title: '',
    label: 'Jumlah Kejadian',
});

const chartRef = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

const initChart = () => {
    if (chartRef.value) {
        const ctx = chartRef.value.getContext('2d');
        if (ctx) {
            chartInstance = new Chart(ctx as unknown as CanvasRenderingContext2D, {
                type: props.chartType as ChartType,
                data: {
                    labels: props.labels,
                    datasets: [{
                        label: props.label,
                        data: props.values,
                        backgroundColor: props.chartType === 'bar'
                            ? 'rgba(14, 165, 233, 0.6)'
                            : 'rgba(244, 63, 94, 0.6)',
                        borderColor: props.chartType === 'bar'
                            ? 'rgba(14, 165, 233, 1)'
                            : 'rgba(244, 63, 94, 1)',
                        borderWidth: 1,
                        fill: false,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: !!props.title,
                            text: props.title,
                            color: '#94a3b8',
                            font: { size: 13 },
                        },
                        legend: {
                            labels: { color: '#94a3b8' },
                        },
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8', font: { size: 11 } },
                            grid: { color: 'rgba(148, 163, 189, 0.1)' },
                        },
                        y: {
                            ticks: { color: '#94a3b8', font: { size: 11 } },
                            grid: { color: 'rgba(148, 163, 189, 0.1)' },
                        },
                    },
                },
            });
        }
    }
};

const updateChart = () => {
    if (chartInstance) {
        chartInstance.data.labels = props.labels;
        chartInstance.data.datasets[0].data = props.values;
        chartInstance.update();
    }
};

onMounted(() => {
    initChart();
});

onBeforeUnmount(() => {
    chartInstance?.destroy();
    chartInstance = null;
});

watch([() => props.labels, () => props.values, () => props.chartType], () => {
    updateChart();
});
</script>

<template>
    <canvas ref="chartRef" style="max-height: 240px;"></canvas>
</template>
