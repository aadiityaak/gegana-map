<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AiStatsCharts from '@/components/AiStatsCharts.vue';
import HermesLogs from './hermes/Logs.vue';

type Tab = 'history' | 'hermes';

const activeTab = ref<Tab>('history');

// Modal
const modalOpen = ref(false);
const selectedItem = ref<HistoryEntry | null>(null);

// Riwayat analisa
type HistoryEntry = {
    id: number;
    module: string;
    action: string;
    period: string;
    total_data: number;
    result: string;
    stats?: any | null;
    created_at: string;
};

const history = ref<HistoryEntry[]>([]);
const loadingHistory = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const totalHistory = ref(0);

const fetchHistory = async (page = 1) => {
    loadingHistory.value = true;
    try {
        const params = new URLSearchParams({ view: 'ai', per_page: '50', page: String(page) });
        const res = await fetch(`/api/ai/all-history?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (res.ok) {
            history.value = data.data ?? [];
            currentPage.value = data.meta?.current_page ?? 1;
            lastPage.value = data.meta?.last_page ?? 1;
            totalHistory.value = data.meta?.total ?? 0;
        }
    } catch {
        // swallow
    } finally {
        loadingHistory.value = false;
    }
};

const deleteHistoryItem = async (id: number) => {
    if (!confirm('Hapus riwayat ini?')) return;

    try {
        const res = await fetch(`/api/ai/history/${id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
        });
        const data = await res.json();
        if (res.ok) {
            // re-fetch current page (may go back one page if last item on page)
            await fetchHistory(currentPage.value);
            // if page is now empty and not the first page, go back one
            if (history.value.length === 0 && currentPage.value > 1) {
                await fetchHistory(currentPage.value - 1);
            }
        } else {
            alert(data.message ?? 'Gagal menghapus.');
        }
    } catch (e: any) {
        alert(e.message ?? 'Network error.');
    }
};

const actionLabel = (action: string) => {
    const map: Record<string, string> = {
        analisa: 'Analisa',
        prediksi: 'Prediksi',
        antisipasi: 'Antisipasi',
    };
    return map[action] ?? action;
};

const periodLabel = (period: string) => {
    const map: Record<string, string> = {
        '1month': '1 Bulan',
        '6months': '6 Bulan',
        '1year': '1 Tahun',
    };
    return map[period] ?? period;
};

const moduleLabel = (module: string) => {
    const map: Record<string, string> = {
        jibom: 'JIBOM',
        kbrn: 'KBRN',
        'wan-teror': 'WAN TEROR',
    };
    return map[module] ?? module;
};

const viewItem = (item: HistoryEntry) => {
    selectedItem.value = item;
    modalOpen.value = true;
};

// Import Hermes logs component
// (imported at top)

onMounted(fetchHistory);
</script>

<template>
    <Head title="AI Logs" />

    <div class="flex flex-col gap-4 p-4">
        <Heading
            title="AI Logs"
            description="Riwayat analisa AI & log Hermes agent"
        />

        <!-- Tabs -->
        <div class="flex gap-2 border-b border-sky-500/20 pb-3">
            <Button
                variant="secondary"
                :class="activeTab === 'history' ? 'border-sky-500/50 bg-sky-500/25 text-sky-100' : ''"
                @click="activeTab = 'history'"
            >
                Riwayat Analisa
            </Button>
            <Button
                variant="secondary"
                :class="activeTab === 'hermes' ? 'border-rose-500/25 bg-rose-500/40 text-rose-200' : ''"
                @click="activeTab = 'hermes'"
            >
                Sistem Agent
            </Button>
        </div>

        <!-- Tab: Riwayat Analisa -->
        <div v-if="activeTab === 'history'">
            <div v-if="loadingHistory" class="py-8 text-center text-sky-300">
                Memuat riwayat...
            </div>

            <div v-else class="space-y-3">
                <div
                    v-if="history.length === 0"
                    class="py-8 text-center text-sky-300/50"
                >
                    Belum ada riwayat analisa.
                </div>

                <div
                    v-for="item in history"
                    :key="item.id"
                    class="flex cursor-pointer items-center justify-between rounded-xl border border-sky-500/15 bg-sky-500/[0.03] p-3 transition-all hover:border-sky-500/30 hover:bg-sky-500/[0.06]"
                    @click="viewItem(item)"
                >
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <Badge variant="outline" class="text-xs">
                            {{ actionLabel(item.action) }}
                        </Badge>
                        <Badge variant="outline" class="text-xs">
                            {{ moduleLabel(item.module) }}
                        </Badge>
                        <span class="text-xs text-sky-300/70">
                            {{ periodLabel(item.period) }}
                        </span>
                        <span class="text-xs text-sky-300/50">
                            {{ item.total_data }} data
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs text-sky-300/50">
                            {{ new Date(item.created_at).toLocaleString('id-ID') }}
                        </span>
                        <button
                            @click.stop="deleteHistoryItem(item.id)"
                            class="rounded p-1.5 text-red-400/70 hover:bg-red-500/10 hover:text-red-300"
                            title="Hapus"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M3 6h18" />
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                <path d="M10 11v6" />
                                <path d="M14 11v6" />
                                <path d="M9 6V4a3 3 0 0 1 6 0v2" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="lastPage > 1" class="flex items-center justify-between pt-2">
                    <span class="text-xs text-sky-300/50">
                        {{ totalHistory }} data &middot; Halaman {{ currentPage }} / {{ lastPage }}
                    </span>
                    <div class="flex gap-1">
                        <button
                            :disabled="currentPage <= 1"
                            @click="fetchHistory(currentPage - 1)"
                            class="rounded px-3 py-1 text-xs text-sky-300 border border-sky-500/20 hover:bg-sky-500/10 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                        >
                            Sebelumnya
                        </button>
                        <button
                            :disabled="currentPage >= lastPage"
                            @click="fetchHistory(currentPage + 1)"
                            class="rounded px-3 py-1 text-xs text-sky-300 border border-sky-500/20 hover:bg-sky-500/10 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                        >
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Log Hermes -->
        <div v-else>
            <HermesLogs />
        </div>
    </div>

    <!-- Modal Detail -->
    <Dialog :open="modalOpen" @update:open="modalOpen = $event">
        <DialogContent class="sm:max-w-4xl max-h-[88vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    <template v-if="selectedItem">
                        <span class="inline-flex items-center gap-2">
                            <Badge variant="outline" class="text-xs">
                                {{ actionLabel(selectedItem.action) }}
                            </Badge>
                            <Badge variant="outline" class="text-xs">
                                {{ moduleLabel(selectedItem.module) }}
                            </Badge>
                            <span class="text-xs text-muted-foreground">
                                {{ periodLabel(selectedItem.period) }}
                            </span>
                        </span>
                    </template>
                </DialogTitle>
            </DialogHeader>
            <div v-if="selectedItem" class="space-y-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span>{{ selectedItem.total_data }} data</span>
                    <span>-</span>
                    <span>{{ new Date(selectedItem.created_at).toLocaleString('id-ID') }}</span>
                </div>
                <AiStatsCharts :stats="selectedItem.stats" :result="selectedItem.result" />
                <div v-if="selectedItem.result" class="space-y-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-sky-300/70">
                        Narasi analisa
                    </div>
                    <div class="text-sm leading-relaxed whitespace-pre-wrap text-foreground/90">
                        {{ selectedItem.result }}
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
