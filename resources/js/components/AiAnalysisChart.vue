<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import {
    Chart,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    DoughnutController,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    DoughnutController,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
);

const WARNA = [
    'rgba(56, 189, 248, 0.8)',
    'rgba(244, 63, 94, 0.8)',
    'rgba(16, 185, 129, 0.8)',
    'rgba(245, 158, 11, 0.8)',
    'rgba(139, 92, 246, 0.8)',
    'rgba(236, 72, 153, 0.8)',
    'rgba(20, 184, 166, 0.8)',
    'rgba(148, 163, 184, 0.75)',
];

const props = withDefaults(defineProps<{
    chartType?: 'bar' | 'line' | 'doughnut';
    horizontal?: boolean;
    labels: string[];
    values: (string | number)[];
    title?: string;
    label?: string;
    tinggi?: number;
    satuan?: string;
}>(), {
    chartType: 'bar',
    horizontal: false,
    title: '',
    label: 'Jumlah Kejadian',
    tinggi: 220,
    satuan: 'kejadian',
});

const chartRef = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

const bangunDataset = () => {
    if (props.chartType === 'doughnut') {
        return [{
            label: props.label,
            data: props.values,
            backgroundColor: props.values.map((_, i) => WARNA[i % WARNA.length]),
            borderColor: 'rgba(2, 6, 23, 0.85)',
            borderWidth: 1,
        }];
    }

    if (props.chartType === 'line') {
        return [{
            label: props.label,
            data: props.values,
            borderColor: 'rgba(56, 189, 248, 1)',
            backgroundColor: 'rgba(56, 189, 248, 0.18)',
            borderWidth: 2,
            tension: 0.35,
            fill: true,
            pointRadius: 3,
            pointBackgroundColor: 'rgba(56, 189, 248, 1)',
        }];
    }

    const warnaBatang = props.horizontal
        ? props.values.map((_, i) => WARNA[i % WARNA.length])
        : 'rgba(56, 189, 248, 0.75)';

    return [{
        label: props.label,
        data: props.values,
        backgroundColor: warnaBatang,
        borderColor: props.horizontal ? 'rgba(2, 6, 23, 0.4)' : 'rgba(56, 189, 248, 1)',
        borderWidth: 1,
        borderRadius: 4,
    }];
};

const bangunOpsi = () => {
    const tooltip = props.chartType === 'doughnut'
        ? {
            callbacks: {
                label: (ctx: any) => {
                    const total = (ctx.dataset.data as number[]).reduce((a, b) => a + Number(b), 0);
                    const nilai = Number(ctx.parsed);
                    const persen = total > 0 ? Math.round((nilai / total) * 100) : 0;
                    return ` ${ctx.label}: ${nilai} ${props.satuan} (${persen}%)`;
                },
            },
        }
        : {
            callbacks: {
                label: (ctx: any) => {
                    const nilai = ctx.parsed.y ?? ctx.parsed.x ?? ctx.parsed;
                    return ` ${ctx.dataset.label}: ${nilai} ${props.satuan}`;
                },
            },
        };

    const dasar: any = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        plugins: {
            legend: {
                display: props.chartType === 'doughnut',
                position: 'bottom',
                labels: { color: '#94a3b8', font: { size: 10 }, boxWidth: 10, padding: 8 },
            },
            tooltip,
        },
    };

    if (props.chartType !== 'doughnut') {
        dasar.indexAxis = props.horizontal ? 'y' : 'x';
        dasar.scales = {
            x: {
                beginAtZero: true,
                ticks: { color: '#94a3b8', font: { size: 10 }, precision: 0 },
                grid: { color: 'rgba(148, 163, 184, 0.12)' },
            },
            y: {
                beginAtZero: true,
                ticks: { color: '#94a3b8', font: { size: 10 }, precision: 0 },
                grid: { color: 'rgba(148, 163, 184, 0.12)' },
            },
        };
    }

    return dasar;
};

const gambar = () => {
    if (!chartRef.value) return;

    chartInstance?.destroy();
    chartInstance = null;

    const ctx = chartRef.value.getContext('2d');
    if (!ctx) return;

    chartInstance = new Chart(ctx as unknown as CanvasRenderingContext2D, {
        type: props.chartType as any,
        data: {
            labels: props.labels,
            datasets: bangunDataset() as any,
        },
        options: bangunOpsi(),
    });
};

const perbarui = () => {
    if (!chartInstance) return;
    chartInstance.data.labels = props.labels;
    chartInstance.data.datasets = bangunDataset() as any;
    chartInstance.update();
};

onMounted(gambar);

onBeforeUnmount(() => {
    chartInstance?.destroy();
    chartInstance = null;
});

watch([() => props.labels, () => props.values], perbarui);
watch([() => props.chartType, () => props.horizontal], gambar);
</script>

<template>
    <div :style="{ height: tinggi + 'px' }" class="w-full">
        <canvas ref="chartRef"></canvas>
    </div>
</template>
