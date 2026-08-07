<script setup lang="ts">
import { ref } from 'vue';
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

// chart data
const chartLabels = ref<string[]>([]);
const chartValues = ref<number[]>([]);

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

const parseStats = (text: string) => {
    const labels: string[] = [];
    const values: number[] = [];

    const sectionMatch = text.match(/\*\*(?:Distribusi Geografis|Area Rawan|Provinsi Terbanyak)[^*]*\*\*\s*\n([\s\S]*?)(?=\n\*\*|\n\n|$)/i);
    const sectionText = sectionMatch ? sectionMatch[1] : text;

    const lines = sectionText.split('\n');
    for (const line of lines) {
        const match = line.match(/-?\s*([^\n()]+?)\s*\(([0-9.,]+)\)/);
        if (match) {
            const name = match[1].trim();
            const value = parseInt(match[2].replace(/[^\d]/g, ''), 10);
            if (!isNaN(value)) {
                labels.push(name);
                values.push(value);
            }
        }
    }

    chartLabels.value = labels;
    chartValues.value = values;
};

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
            parseStats(json.result);
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

        <!-- loading -->
        <div
            v-if="loading"
            class="rounded border border-sky-500/15 bg-sky-500/5 p-3 text-xs text-sky-300"
        >
            <span class="inline-block animate-pulse">&gt; Menghubungi Sistem...</span>
        </div>

        <!-- error -->
        <div
            v-if="error"
            class="rounded border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-300"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block me-1 -mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            {{ error }}
        </div>

        <!-- result -->
        <div
            v-if="result && !loading"
            class="rounded-lg border border-sky-500/15 bg-sky-500/[0.04] p-4 text-sm text-sky-100/90 leading-relaxed whitespace-pre-wrap"
        >
            {{ result }}
        </div>

        <!-- chart -->
        <div
            v-if="chartLabels.length > 0 && !loading"
            class="mt-3 rounded-lg border border-sky-500/15 bg-sky-500/[0.04] p-4"
        >
            <div class="mb-2 text-xs text-sky-300">Grafik Area Rawan</div>
            <div style="max-height: 240px;">
                <AiAnalysisChart
                    chart-type="bar"
                    :labels="chartLabels"
                    :values="chartValues"
                    title="Area Rawan"
                />
            </div>
        </div>
    </div>
</template>
