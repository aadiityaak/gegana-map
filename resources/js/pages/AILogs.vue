<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';

type Tab = 'history' | 'hermes';

const activeTab = ref<Tab>('history');

// Riwayat analisa
type HistoryEntry = {
    id: number;
    module: string;
    action: string;
    period: string;
    total_data: number;
    result: string;
    created_at: string;
};

const history = ref<HistoryEntry[]>([]);
const loadingHistory = ref(false);

const fetchHistory = async () => {
    loadingHistory.value = true;
    try {
        const res = await fetch('/api/ai/all-history', {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (res.ok) {
            history.value = data.data ?? [];
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
            history.value = history.value.filter(h => h.id !== id);
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

// Import Hermes logs component
import HermesLogs from './hermes/Logs.vue';

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
        <div class="flex gap-2 border-b border-sky-500/20">
            <button
                v-for="tab in [
                    { key: 'history', label: 'Riwayat Analisa' },
                    { key: 'hermes', label: 'Log Hermes' },
                ]"
                :key="tab.key"
                @click="activeTab = tab.key as Tab"
                :class="activeTab === tab.key
                    ? 'border-rose-500/25 bg-rose-500/10 text-rose-200'
                    : 'border-transparent text-sky-300 hover:bg-sky-500/5'"
                class="border-b-2 px-4 py-2 text-sm transition-all"
            >
                {{ tab.label }}
            </button>
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
                    class="rounded-xl border border-sky-500/15 bg-sky-500/[0.03] p-4"
                >
                    <div class="flex items-center justify-between">
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
                            <span class="text-[10px] text-sky-300/50">
                                {{ new Date(item.created_at).toLocaleString('id-ID') }}
                            </span>
                            <button
                                @click="deleteHistoryItem(item.id)"
                                class="rounded p-1 text-red-400/70 hover:bg-red-500/10 hover:text-red-300"
                                title="Hapus"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="14"
                                    height="14"
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

                    <div class="mt-2 max-h-48 overflow-y-auto text-xs text-sky-300/80">
                        {{ item.result }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Log Hermes -->
        <div v-else>
            <HermesLogs />
        </div>
    </div>
</template>
