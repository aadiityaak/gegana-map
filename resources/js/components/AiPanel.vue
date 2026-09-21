<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import AiAnalysisChart from './AiAnalysisChart.vue';

const props = defineProps<{
    module: string;
}>();

const emit = defineEmits<{
    (e: 'analyzed'): void;
}>();

type Action = 'analisa' | 'prediksi' | 'antisipasi';
type Period = '1month' | '6months' | '1year';

const activeAction = ref<Action>('analisa');
const activePeriod = ref<Period>('1month');
const loading = ref(false);
const result = ref<string | null>(null);
const error = ref<string | null>(null);
const totalData = ref<number | null>(null);
const stats = ref<any>(null);

const actionLabels: Record<Action, string> = {
    analisa: 'Analisa',
    prediksi: 'Prediksi',
    antisipasi: 'Antisipasi',
};

const periodLabels: Record<Period, string> = {
    '1month': '1 Bulan',
    '6months': '6 Bulan',
    '1year': '1 Tahun',
};

const judulGrafik = computed(() => {
    const peta: Record<Action, { tren: string; area: string; kategori: string }> = {
        analisa: {
            tren: 'Tren Kejadian per Bulan',
            area: 'Area Rawan (Provinsi Terbanyak)',
            kategori: 'Distribusi Kategori Kejadian',
        },
        prediksi: {
            tren: 'Tren Historis (dasar prediksi)',
            area: 'Area Prioritas Prediksi',
            kategori: 'Kategori Risiko Historis',
        },
        antisipasi: {
            tren: 'Tren Historis (dasar antisipasi)',
            area: 'Area Prioritas Antisipasi',
            kategori: 'Kategori Sasaran Mitigasi',
        },
    };

    return peta[activeAction.value];
});

const labelBulanPeriode = computed(() => periodLabels[activePeriod.value]);

const adaData = computed(() => (stats.value?.kpi?.total ?? 0) > 0);
const tren = computed(() => stats.value?.tren ?? []);
const provinsi = computed(() => stats.value?.provinsi ?? []);
const tipe = computed(() => stats.value?.tipe ?? []);

const labelTren = computed(() => tren.value.map((b: any) => b.label));
const nilaiTren = computed(() => tren.value.map((b: any) => b.value));
const labelProvinsi = computed(() => provinsi.value.map((b: any) => b.label));
const nilaiProvinsi = computed(() => provinsi.value.map((b: any) => b.value));
const labelTipe = computed(() => tipe.value.map((b: any) => b.label));
const nilaiTipe = computed(() => tipe.value.map((b: any) => b.value));

const delta = computed(() => Number(stats.value?.kpi?.delta_persen ?? 0));
const deltaNaik = computed(() => delta.value > 0);
const deltaNetral = computed(() => delta.value === 0);

const run = async () => {
    loading.value = true;
    result.value = null;
    error.value = null;

    try {
        const res = await fetch(
            `/api/ai/analyze/${props.module}?action=${activeAction.value}&period=${activePeriod.value}`,
            { headers: { Accept: 'application/json' } },
        );
        const json = await res.json();
        if (!res.ok) {
            error.value = json.message ?? 'Gagal memanggil AI.';
        } else {
            result.value = json.result;
            totalData.value = json.total_data;
            stats.value = json.stats ?? null;
            emit('analyzed');
        }
    } catch (e: any) {
        error.value = e.message ?? 'Network error.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="rounded-xl border border-sky-500/50 bg-sky-500/30 p-3">
        <div class="mb-3 flex items-center justify-between text-xs text-sky-300">
            <span>&gt; AI ANALYSIS</span>
            <span class="text-[11px]">&gt; {{ module.toUpperCase() }}</span>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <Button
                v-for="(label, key) in actionLabels"
                :key="key"
                size="sm"
                variant="secondary"
                :class="activeAction === key ? 'border-rose-500/25 bg-rose-500/40 text-rose-200' : ''"
                @click="activeAction = key"
            >
                {{ label }}
            </Button>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <span class="text-[11px] text-sky-300">Periode:</span>
            <Button
                v-for="(label, key) in periodLabels"
                :key="key"
                size="sm"
                variant="secondary"
                :class="activePeriod === key ? 'border-rose-500/25 bg-rose-500/40 text-rose-200' : ''"
                @click="activePeriod = key"
            >
                {{ label }}
            </Button>

            <span class="mx-1 ms-auto h-5 w-px bg-sky-500/20"></span>

            <Button
                size="sm"
                variant="default"
                class="font-semibold"
                :disabled="loading"
                @click="run"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play-icon lucide-play me-1"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg>
                {{ loading ? 'Memproses...' : 'Jalankan' }}
            </Button>
        </div>

        <div
            v-if="totalData !== null"
            class="mb-3 text-[11px] text-sky-300/80"
        >
            &gt; Data tersedia: {{ totalData }} kejadian
        </div>

        <!-- Loading -->
        <div
            v-if="loading"
            class="rounded border border-sky-500/15 bg-sky-500/5 p-3 text-xs text-sky-300"
        >
            <span class="inline-block animate-pulse">&gt; Menghubungi Sistem...</span>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="rounded border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-300"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block me-1 -mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            {{ error }}
        </div>

        <!-- Kartu ringkasan -->
        <div
            v-if="stats && !loading"
            class="mb-3 grid grid-cols-2 gap-2 lg:grid-cols-4"
        >
            <div class="rounded-lg border border-sky-500/20 bg-black/25 p-2.5">
                <div class="text-[10px] uppercase tracking-wide text-sky-300/70">Total Kejadian</div>
                <div class="text-lg font-semibold text-sky-100">{{ stats.kpi.total }}</div>
                <div class="text-[10px] text-sky-300/50">periode {{ labelBulanPeriode.toLowerCase() }}</div>
            </div>
            <div class="rounded-lg border border-sky-500/20 bg-black/25 p-2.5">
                <div class="text-[10px] uppercase tracking-wide text-sky-300/70">Provinsi Terdampak</div>
                <div class="text-lg font-semibold text-sky-100">{{ stats.kpi.provinsi }}</div>
                <div class="text-[10px] text-sky-300/50">wilayah berbeda</div>
            </div>
            <div class="rounded-lg border border-sky-500/20 bg-black/25 p-2.5">
                <div class="text-[10px] uppercase tracking-wide text-sky-300/70">Rata-rata / Bulan</div>
                <div class="text-lg font-semibold text-sky-100">{{ stats.kpi.rata_per_bulan }}</div>
                <div class="text-[10px] text-sky-300/50">puncak: {{ stats.kpi.puncak_label }} ({{ stats.kpi.puncak_value }})</div>
            </div>
            <div class="rounded-lg border border-sky-500/20 bg-black/25 p-2.5">
                <div class="text-[10px] uppercase tracking-wide text-sky-300/70">vs Periode Sebelumnya</div>
                <div
                    class="text-lg font-semibold"
                    :class="deltaNetral ? 'text-sky-100' : (deltaNaik ? 'text-rose-300' : 'text-emerald-300')"
                >
                    {{ deltaNaik ? '▲' : (deltaNetral ? '■' : '▼') }}
                    {{ deltaNetral ? '0' : (deltaNaik ? delta : -delta) }}%
                </div>
                <div class="text-[10px] text-sky-300/50">sebelumnya: {{ stats.kpi.sebelumnya }} kejadian</div>
            </div>
        </div>

        <!-- Visualisasi -->
        <div v-if="stats && !loading" class="mt-3 space-y-3">
            <div class="flex items-center justify-between text-[11px] text-sky-300">
                <span>&gt; VISUALISASI DATA</span>
                <span class="text-sky-300/50">angka dari database, bukan teks AI</span>
            </div>

            <div
                v-if="!adaData"
                class="rounded-lg border border-sky-500/15 bg-sky-500/[0.04] p-3 text-xs text-sky-300/80"
            >
                Tidak ada kejadian tercatat pada periode ini — belum ada data untuk digrafikkan.
            </div>

            <template v-else>
                <div class="grid gap-3 lg:grid-cols-2">
                    <div class="rounded-lg border border-sky-500/15 bg-black/25 p-3">
                        <div class="mb-2 text-xs text-sky-300">{{ judulGrafik.tren }}</div>
                        <AiAnalysisChart
                            chart-type="line"
                            :labels="labelTren"
                            :values="nilaiTren"
                            label="Kejadian"
                        />
                    </div>

                    <div class="rounded-lg border border-sky-500/15 bg-black/25 p-3">
                        <div class="mb-2 text-xs text-sky-300">{{ judulGrafik.kategori }}</div>
                        <AiAnalysisChart
                            chart-type="doughnut"
                            :labels="labelTipe"
                            :values="nilaiTipe"
                            label="Kejadian"
                            :tinggi="220"
                        />
                    </div>
                </div>

                <div class="rounded-lg border border-sky-500/15 bg-black/25 p-3">
                    <div class="mb-2 text-xs text-sky-300">{{ judulGrafik.area }}</div>
                    <AiAnalysisChart
                        chart-type="bar"
                        horizontal
                        :labels="labelProvinsi"
                        :values="nilaiProvinsi"
                        label="Kejadian"
                        :tinggi="Math.max(180, labelProvinsi.length * 28 + 60)"
                    />
                </div>
            </template>
        </div>

        <!-- Narasi AI (teks analisa) — di bawah grafik -->
        <div v-if="result && !loading" class="mt-3 space-y-2">
            <div class="flex items-center justify-between text-[11px] text-sky-300">
                <span>&gt; NARASI ANALISA AI</span>
                <span class="text-sky-300/50">teks analisa dari model</span>
            </div>
            <div
                class="rounded-lg border border-sky-500/15 bg-sky-500/[0.04] p-4 text-sm text-sky-100/90 leading-relaxed whitespace-pre-wrap"
            >
                {{ result }}
            </div>
        </div>
    </div>
</template>
