    <script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogClose,
} from '@/components/ui/dialog';

const props = defineProps<{
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

// riwayat
const history = ref<any[]>([]);
const loadingHistory = ref(false);
const showHistory = ref(false);

// modal
const modalOpen = ref(false);
const modalItem = ref<any>(null);

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

const actionLabelMap: Record<string, string> = {
    analisa: 'Analisa',
    prediksi: 'Prediksi',
    antisipasi: 'Antisipasi',
};

const periodLabelMap: Record<string, string> = {
    '1month': '1 Bulan',
    '6months': '6 Bulan',
    '1year': '1 Tahun',
};

const fetchHistory = async () => {
    loadingHistory.value = true;
    try {
        const res = await fetch(`/api/ai/history/${props.module}`, {
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        if (res.ok) {
            history.value = json.data ?? [];
        }
    } catch {
        // silent
    } finally {
        loadingHistory.value = false;
    }
};

onMounted(fetchHistory);

const viewHistory = (item: any) => {
    modalItem.value = item;
    modalOpen.value = true;
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
            await fetchHistory();
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

            <Button
                size="sm"
                variant="secondary"
                :class="showHistory ? 'border-sky-500/50 bg-sky-500/25 text-sky-100' : ''"
                @click="showHistory = !showHistory"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw-clock-icon lucide-rotate-ccw-clock me-1"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                Riwayat ({{ history.length }})
            </Button>
        </div>

        <div
            v-if="totalData !== null"
            class="mb-3 text-[11px] text-sky-300/80"
        >
            &gt; Data tersedia: {{ totalData }} kejadian
        </div>

        <!-- daftar riwayat -->
        <div
            v-if="showHistory"
            class="mb-3 max-h-48 overflow-y-auto rounded border border-sky-500/15 bg-black/30 p-2 space-y-1"
        >
            <div
                v-for="item in history"
                :key="item.id"
                class="cursor-pointer rounded px-2 py-1.5 text-xs transition-colors bg-sky-500/5"
                :class="modalItem?.id === item.id && modalOpen ? 'bg-sky-500/15 text-sky-200' : 'hover:bg-sky-500/10 hover:text-sky-300'"
                @click="viewHistory(item)"
            >
                <div class="flex items-center justify-between gap-2">
                    <span>
                        [{{ actionLabelMap[item.action] ?? item.action }}]
                        {{ periodLabelMap[item.period] ?? item.period }}
                    </span>
                    <span class="shrink-0 text-[10px] opacity-60">{{ item.total_data }} data</span>
                </div>
                <div class="text-[10px] opacity-50">
                    {{ new Date(item.created_at).toLocaleString('id-ID') }}
                </div>
            </div>
            <div
                v-if="!loadingHistory && history.length === 0"
                class="py-2 text-center text-[11px] text-sky-300/50"
            >
                Belum ada riwayat
            </div>
            <div
                v-if="loadingHistory"
                class="py-2 text-center text-[11px] text-sky-300/50"
            >
                Memuat...
            </div>
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
    </div>

    <!-- modal riwayat -->
    <Dialog :open="modalOpen" @update:open="modalOpen = $event">
        <DialogContent class="sm:max-w-2xl max-h-[85vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    <template v-if="modalItem">
                        [{{ actionLabelMap[modalItem.action] ?? modalItem.action }}]
                        {{ periodLabelMap[modalItem.period] ?? modalItem.period }}
                        — {{ modalItem.total_data }} data
                    </template>
                </DialogTitle>
            </DialogHeader>
            <div
                v-if="modalItem"
                class="text-base leading-relaxed whitespace-pre-wrap text-foreground/90"
            >
                {{ modalItem.result }}
            </div>
        </DialogContent>
    </Dialog>
</template>
