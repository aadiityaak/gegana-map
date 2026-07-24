    <script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

defineProps<{
    module: string;
}>();

type Action = 'analisa' | 'prediksi' | 'antisipasi';
type Period = '1month' | '6months' | '1year';

const activeAction = ref<Action>('analisa');
const activePeriod = ref<Period>('1month');
const loading = ref(false);
const result = ref<string | null>(null);
const error = ref<string | null>(null);
const totalData = ref<number | null>(null);

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
        }
    } catch (e: any) {
        error.value = e.message ?? 'Network error.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="rounded-xl border border-sky-500/15 bg-black/20 p-3">
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
                :class="activeAction === key ? 'border-sky-500/25 bg-sky-500/10 text-sky-200' : ''"
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
                :class="activePeriod === key ? 'border-sky-500/25 bg-sky-500/10 text-sky-200' : ''"
                @click="activePeriod = key"
            >
                {{ label }}
            </Button>
        </div>

        <div class="mb-3">
            <Button
                size="sm"
                :disabled="loading"
                @click="run"
            >
                {{ loading ? 'Memproses...' : 'Jalankan' }}
            </Button>
        </div>

        <div
            v-if="totalData !== null"
            class="mb-2 text-[11px] text-sky-300"
        >
            &gt; Data tersedia: {{ totalData }} kejadian
        </div>

        <div
            v-if="loading"
            class="rounded border border-sky-500/15 bg-black/30 p-3 text-xs text-sky-300"
        >
            &gt; Menghubungi AI...
        </div>

        <div
            v-if="error"
            class="rounded border border-red-500/30 bg-red-500/10 p-3 text-xs text-red-300"
        >
            &gt; Error: {{ error }}
        </div>

        <div
            v-if="result && !loading"
            class="rounded border border-sky-500/15 bg-black/30 p-3 text-xs text-sky-200/90 leading-relaxed whitespace-pre-wrap"
        >
            {{ result }}
        </div>
    </div>
</template>
