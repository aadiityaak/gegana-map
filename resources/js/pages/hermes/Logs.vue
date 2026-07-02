<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, Search, FileText, AlertCircle, Info, PlusCircle, InfoIcon, ArrowUpRight, RefreshCw } from 'lucide-vue-next';
import { ref, onBeforeUnmount, onMounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';

type LogEntry = {
    id: number;
    type: string;
    title: string;
    message: string | null;
    metadata: string | null;
    created_at: string;
};

const logs = ref<LogEntry[]>([]);
const paused = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;

const fetchLogs = async (since?: number | null) => {
    try {
        const params = new URLSearchParams();
        if (since) params.set('since', String(since));
        const res = await fetch(`/api/hermes/logs?${params.toString()}`);
        const data = await res.json();
        if (data.logs && data.logs.length > 0) {
            const newLogs = data.logs as LogEntry[];
            const existingIds = new Set(logs.value.map((l) => l.id));
            const fresh = newLogs.filter((l) => !existingIds.has(l.id));
            if (fresh.length) {
                logs.value = [...fresh, ...logs.value].slice(0, 200);
            }
        }
    } catch {
        // swallow
    }
};

const startPolling = () => {
    if (timer) clearInterval(timer);
    timer = setInterval(() => {
        const maxId = logs.value[0]?.id ?? 0;
        fetchLogs(maxId);
    }, 3000);
};

const stopPolling = () => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
};

const togglePause = () => {
    paused.value = !paused.value;
    if (paused.value) {
        stopPolling();
    } else {
        startPolling();
    }
};

onMounted(() => {
    fetchLogs();
    startPolling();
});

onBeforeUnmount(() => stopPolling());

const typeIcon = (t: string) => {
    if (t.startsWith('search')) return Search;
    if (t.startsWith('summary') || t === 'summarizing') return FileText;
    if (t === 'insert') return PlusCircle;
    if (t === 'update' || t === 'skip') return ArrowUpRight;
    if (t === 'error') return AlertCircle;
    if (t === 'scan_start' || t === 'scan_done') return Activity;
    return Info;
};

const typeBadge = (t: string) => {
    const map: Record<string, string> = {
        scan_start: 'scan',
        scan_done: 'scan',
        search_start: 'search',
        search_done: 'search',
        summarizing: 'summary',
        summary_done: 'summary',
        insert: 'insert',
        update: 'update',
        skip: 'skip',
        error: 'error',
        info: 'info',
    };
    return map[t] ?? t;
};

const typeBadgeClass = (t: string) => {
    if (t === 'error') return 'border-red-500/30 bg-red-500/10 text-red-300';
    if (t === 'insert' || t === 'update') return 'border-green-500/30 bg-green-500/10 text-green-300';
    if (t === 'skip') return 'border-yellow-500/30 bg-yellow-500/10 text-yellow-300';
    if (t.startsWith('search') || t.startsWith('scan')) return 'border-blue-500/30 bg-blue-500/10 text-blue-300';
    if (t.startsWith('summary')) return 'border-purple-500/30 bg-purple-500/10 text-purple-300';
    return 'border-green-500/20 bg-black/30 text-green-300/70';
};

const formatTime = (ts: string) => {
    return new Date(ts).toLocaleTimeString('id-ID', {
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
};

const formatDate = (ts: string) => {
    return new Date(ts).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
};

const parseMeta = (meta: string | null): Record<string, any> => {
    if (!meta) return {};
    try { return JSON.parse(meta); } catch { return {}; }
};
</script>

<template>
    <Head title="Hermes Agent Logs" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <Heading title="Hermes Agent Logs" description="Realtime log aktivitas Hermes AI Agent" />
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="togglePause" class="text-xs">
                    <span v-if="paused">▶ Lanjutkan</span>
                    <span v-else class="flex items-center gap-1">
                        <RefreshCw class="size-3 animate-spin" />
                        Live
                    </span>
                </Button>
                <span class="text-[11px] text-green-300/40">
                    {{ logs.length }} log
                </span>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-green-500/15 bg-black/30">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-green-500/10 px-4 py-2">
                <div class="flex items-center gap-2 text-xs text-green-300/50">
                    <Activity class="size-3.5" />
                    <span>HERMES_AGENT_LOG / {{ logs.length }} entries</span>
                </div>
                <span class="text-[10px] text-green-300/30 font-mono">polling 3s</span>
            </div>

            <!-- Log rows -->
            <div class="max-h-[70vh] overflow-y-auto">
                <div v-if="logs.length === 0" class="flex flex-col items-center gap-2 px-4 py-12 text-green-300/40">
                    <InfoIcon class="size-8" />
                    <span class="text-sm">Belum ada log. Menunggu Hermes Agent...</span>
                </div>

                <div
                    v-for="log in logs"
                    :key="log.id"
                    class="flex gap-3 border-b border-green-500/5 px-4 py-2.5 transition hover:bg-green-500/3"
                >
                    <component
                        :is="typeIcon(log.type)"
                        class="mt-0.5 size-4 shrink-0 text-green-300/50"
                    />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-green-200/90">{{ log.title }}</span>
                            <Badge :class="typeBadgeClass(log.type)" class="px-1.5 py-0 text-[10px]">
                                {{ typeBadge(log.type) }}
                            </Badge>
                        </div>

                        <p
                            v-if="log.message"
                            class="mt-0.5 text-xs leading-relaxed text-green-300/70 whitespace-pre-wrap break-words"
                        >
                            {{ log.message }}
                        </p>

                        <div v-if="parseMeta(log.metadata) && Object.keys(parseMeta(log.metadata)).length" class="mt-1 flex flex-wrap gap-1">
                            <span
                                v-for="(v, k) in parseMeta(log.metadata)"
                                :key="k"
                                class="inline-flex items-center gap-1 rounded border border-green-500/10 bg-black/20 px-1.5 py-0 text-[10px] font-mono text-green-300/50"
                            >
                                {{ k }}: <span class="text-green-300/80">{{ v }}</span>
                            </span>
                        </div>

                        <div class="mt-1 flex items-center gap-2 text-[10px] text-green-300/30 font-mono">
                            <span>{{ formatDate(String(log.created_at)) }}</span>
                            <span>{{ formatTime(String(log.created_at)) }}</span>
                            <span>#{{ log.id }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
