<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AiAnalysisChart from './AiAnalysisChart.vue';

const props = withDefaults(defineProps<{
    stats?: any | null;
    result?: string | null;
}>(), {
    stats: null,
    result: null,
});

const adaStats = computed(() => (props.stats?.kpi?.total ?? 0) > 0);

const tren = computed(() => props.stats?.tren ?? []);
const provinsi = computed(() => props.stats?.provinsi ?? []);
const tipe = computed(() => props.stats?.tipe ?? []);

const labelTren = computed(() => tren.value.map((b: any) => b.label));
const nilaiTren = computed(() => tren.value.map((b: any) => b.value));
const labelProvinsi = computed(() => provinsi.value.map((b: any) => b.label));
const nilaiProvinsi = computed(() => provinsi.value.map((b: any) => b.value));
const labelTipe = computed(() => tipe.value.map((b: any) => b.label));
const nilaiTipe = computed(() => tipe.value.map((b: any) => b.value));

const tinggiProvinsi = computed(() => Math.max(140, labelProvinsi.value.length * 26 + 50));

const delta = computed(() => Number(props.stats?.kpi?.delta_persen ?? 0));
const deltaNaik = computed(() => delta.value > 0);
const deltaNetral = computed(() => delta.value === 0);

// Cadangan untuk riwayat lama (dibuat sebelum kolom stats ada):
// baca angka dari narasi AI supaya tetap ada grafik.
const angkaTeks = ref<{ labels: string[]; values: number[] }>({ labels: [], values: [] });

const bacaDariTeks = (teks: string) => {
    const labels: string[] = [];
    const values: number[] = [];

    const bagian = teks.match(/\*\*(?:Distribusi Geografis|Area Rawan|Provinsi Terbanyak)[^*]*\*\*\s*\n([\s\S]*?)(?=\n\*\*|\n\n|$)/i);
    const isi = bagian ? bagian[1] : teks;

    for (const baris of isi.split('\n')) {
        const cocok = baris.match(/-?\s*([^\n()]+?)\s*\(([0-9.,]+)\)/);
        if (cocok) {
            const nilai = parseInt(cocok[2].replace(/[^0-9]/g, ''), 10);
            if (!isNaN(nilai)) {
                labels.push(cocok[1].trim());
                values.push(nilai);
            }
        }
        if (labels.length >= 8) break;
    }

    return { labels, values };
};

watch(
    () => props.result,
    (teks) => {
        angkaTeks.value = !adaStats.value && teks ? bacaDariTeks(teks) : { labels: [], values: [] };
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-3">
        <!-- Grafik hasil hitungan server -->
        <template v-if="adaStats">
            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-lg border border-sky-500/20 bg-black/25 p-2.5">
                    <div class="text-[10px] uppercase tracking-wide text-sky-300/70">Total</div>
                    <div class="text-base font-semibold text-sky-100">{{ stats.kpi.total }}</div>
                </div>
                <div class="rounded-lg border border-sky-500/20 bg-black/25 p-2.5">
                    <div class="text-[10px] uppercase tracking-wide text-sky-300/70">Provinsi</div>
                    <div class="text-base font-semibold text-sky-100">{{ stats.kpi.provinsi }}</div>
                </div>
                <div class="rounded-lg border border-sky-500/20 bg-black/25 p-2.5">
                    <div class="text-[10px] uppercase tracking-wide text-sky-300/70">vs Sebelumnya</div>
                    <div
                        class="text-base font-semibold"
                        :class="deltaNetral ? 'text-sky-100' : (deltaNaik ? 'text-rose-300' : 'text-emerald-300')"
                    >
                        {{ deltaNaik ? '▲' : (deltaNetral ? '■' : '▼') }}
                        {{ deltaNetral ? '0' : (deltaNaik ? delta : -delta) }}%
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-sky-500/15 bg-black/25 p-3">
                <div class="mb-2 text-xs text-sky-300">Tren Kejadian per Bulan</div>
                <AiAnalysisChart
                    chart-type="line"
                    :labels="labelTren"
                    :values="nilaiTren"
                    label="Kejadian"
                    :tinggi="160"
                />
            </div>

            <div class="rounded-lg border border-sky-500/15 bg-black/25 p-3">
                <div class="mb-2 text-xs text-sky-300">Area Rawan (Provinsi)</div>
                <AiAnalysisChart
                    chart-type="bar"
                    horizontal
                    :labels="labelProvinsi"
                    :values="nilaiProvinsi"
                    label="Kejadian"
                    :tinggi="tinggiProvinsi"
                />
            </div>

            <div class="rounded-lg border border-sky-500/15 bg-black/25 p-3">
                <div class="mb-2 text-xs text-sky-300">Distribusi Kategori</div>
                <AiAnalysisChart
                    chart-type="doughnut"
                    :labels="labelTipe"
                    :values="nilaiTipe"
                    label="Kejadian"
                    :tinggi="190"
                />
            </div>
        </template>

        <!-- Cadangan: angka dari narasi AI (riwayat sebelum ada kolom stats) -->
        <div
            v-else-if="angkaTeks.labels.length > 0"
            class="rounded-lg border border-sky-500/15 bg-black/25 p-3"
        >
            <div class="mb-2 flex items-center justify-between text-xs text-sky-300">
                <span>Provinsi (dibaca dari narasi AI)</span>
                <span class="text-[10px] text-sky-300/50">riwayat lama, tanpa data statistik</span>
            </div>
            <AiAnalysisChart
                chart-type="bar"
                horizontal
                :labels="angkaTeks.labels"
                :values="angkaTeks.values"
                label="Kejadian"
                :tinggi="Math.max(140, angkaTeks.labels.length * 26 + 50)"
            />
        </div>
    </div>
</template>
