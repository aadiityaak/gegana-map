<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AiStatsCharts from './AiStatsCharts.vue';

const props = defineProps<{
    module: string;
}>();

const history = ref<any[]>([]);
const loadingHistory = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const totalHistory = ref(0);

const modalOpen = ref(false);
const modalItem = ref<any>(null);

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

const fetchHistory = async (page = 1) => {
    loadingHistory.value = true;
    try {
        const params = new URLSearchParams({ per_page: '50', page: String(page) });
        const res = await fetch(`/api/ai/history/${props.module}?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        if (res.ok) {
            history.value = json.data ?? [];
            currentPage.value = json.meta?.current_page ?? 1;
            lastPage.value = json.meta?.last_page ?? 1;
            totalHistory.value = json.meta?.total ?? 0;
        }
    } catch {
        // silent
    } finally {
        loadingHistory.value = false;
    }
};

const viewHistory = (item: any) => {
    modalItem.value = item;
    modalOpen.value = true;
};

const deleteHistory = async (item: any) => {
    if (!confirm('Hapus riwayat ini?')) return;

    try {
        const res = await fetch(`/api/ai/history/${item.id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
        });
        const json = await res.json();
        if (res.ok) {
            if (modalItem.value?.id === item.id) {
                modalOpen.value = false;
            }
            await fetchHistory(currentPage.value);
            if (history.value.length === 0 && currentPage.value > 1) {
                await fetchHistory(currentPage.value - 1);
            }
        } else {
            alert(json.message ?? 'Gagal menghapus.');
        }
    } catch (e: any) {
        alert(e.message ?? 'Network error.');
    }
};

onMounted(fetchHistory);

defineExpose({ refresh: fetchHistory });
</script>

<template>
    <!-- Daftar Riwayat -->
    <div class="rounded-xl border border-sky-500/15 bg-sky-500/[0.03] p-3">
        <div class="mb-2 flex items-center justify-between">
            <span class="text-sm font-semibold text-sky-300">Riwayat Analisa</span>
            <span class="text-xs text-sky-300/50">{{ totalHistory }} item</span>
        </div>

        <div
            v-if="history.length > 0"
            class="space-y-1"
        >
            <div
                v-for="item in history"
                :key="item.id"
                class="flex cursor-pointer items-center justify-between rounded px-2 py-2 text-sm transition-colors bg-sky-500/5 hover:bg-sky-500/10 hover:text-sky-300"
                :class="modalItem?.id === item.id && modalOpen ? 'bg-sky-500/15 text-sky-200' : ''"
                @click="viewHistory(item)"
            >
                <div class="flex items-center gap-2">
                    <span class="rounded bg-sky-500/20 px-1.5 py-0.5 text-xs">
                        {{ actionLabelMap[item.action] ?? item.action }}
                    </span>
                    <span class="text-sm text-sky-300/70">
                        {{ periodLabelMap[item.period] ?? item.period }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs opacity-50">{{ item.total_data }} data</span>
                    <span class="text-xs opacity-40">{{ new Date(item.created_at).toLocaleDateString('id-ID') }}</span>
                    <button
                        @click.stop="deleteHistory(item)"
                        class="rounded p-0.5 text-red-400/70 hover:bg-red-500/10 hover:text-red-300"
                        title="Hapus"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="mt-2 flex items-center justify-between">
            <span class="text-xs text-sky-300/50">
                Halaman {{ currentPage }} / {{ lastPage }}
            </span>
            <div class="flex gap-1">
                <button
                    :disabled="currentPage <= 1"
                    @click="fetchHistory(currentPage - 1)"
                    class="rounded px-2 py-0.5 text-xs text-sky-300 border border-sky-500/20 hover:bg-sky-500/10 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                >
                    Sebelumnya
                </button>
                <button
                    :disabled="currentPage >= lastPage"
                    @click="fetchHistory(currentPage + 1)"
                    class="rounded px-2 py-0.5 text-xs text-sky-300 border border-sky-500/20 hover:bg-sky-500/10 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                >
                    Selanjutnya
                </button>
            </div>
        </div>

        <div
            v-if="!loadingHistory && history.length === 0"
            class="py-3 text-center text-sm text-sky-300/50"
        >
            Belum ada riwayat
        </div>
        <div
            v-if="loadingHistory"
            class="py-3 text-center text-sm text-sky-300/50"
        >
            Memuat...
        </div>
    </div>

    <!-- Modal Detail -->
    <Dialog :open="modalOpen" @update:open="modalOpen = $event">
        <DialogContent class="sm:max-w-2xl max-h-[85vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    <template v-if="modalItem">
                        [{{ actionLabelMap[modalItem.action] ?? modalItem.action }}]
                        {{ periodLabelMap[modalItem.period] ?? modalItem.period }}
                        &mdash; {{ modalItem.total_data }} data
                    </template>
                </DialogTitle>
            </DialogHeader>
            <div v-if="modalItem" class="space-y-4">
                <div class="text-sm leading-relaxed whitespace-pre-wrap text-foreground/90">
                    {{ modalItem.result }}
                </div>
                <AiStatsCharts :stats="modalItem.stats" :result="modalItem.result" />
            </div>
        </DialogContent>
    </Dialog>
</template>
