<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
        ],
    }),
});

type TopProvince = {
    id: string;
    name: string;
    count: number;
};

type ModuleType = {
    value: string;
    label: string;
};

type ModuleSummary = {
    key: string;
    title: string;
    href: string;
    total: number;
    types: ModuleType[];
    countsByType: Record<string, number>;
    topProvinces: TopProvince[];
    lastCreatedAt: string | null;
};

const props = defineProps<{
    dashboard: {
        totals: { jibom: number; kwrn: number; wanTeror: number; all: number };
        modules: ModuleSummary[];
        topProvincesAll: TopProvince[];
        generatedAt: string;
    };
}>();

const fmt = (n: number) => new Intl.NumberFormat('id-ID').format(n ?? 0);
const generatedLabel = computed(() => {
    try {
        const d = new Date(props.dashboard.generatedAt);
        return d.toLocaleString('id-ID');
    } catch {
        return props.dashboard.generatedAt;
    }
});

const quickLinks = computed(() => [
    { label: 'JIBOM', href: '/jibom' },
    { label: 'KWRN', href: '/kwrn' },
    { label: 'WAN TEROR', href: '/wan-teror' },
    { label: 'BRANDING', href: '/settings/branding' },
    { label: 'ABOUT', href: '/settings/about' },
]);
</script>

<template>
    <Head title="Dashboard" />

    <div class="p-6 font-mono">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > DASHBOARD
                </h1>
                <Badge class="border border-green-500/25 bg-black/30 text-green-200">
                    total: {{ fmt(props.dashboard.totals.all) }}
                </Badge>
                <Badge class="border border-green-500/25 bg-black/30 text-green-300/80">
                    sync: {{ generatedLabel }}
                </Badge>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4">
                <div class="text-xs text-green-300/60">> TOTAL</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-green-200">
                    {{ fmt(props.dashboard.totals.all) }}
                </div>
                <div class="mt-3 text-xs text-green-300/60">
                    > semua modul (kejadian)
                </div>
            </div>
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4">
                <div class="text-xs text-green-300/60">> JIBOM</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-green-200">
                    {{ fmt(props.dashboard.totals.jibom) }}
                </div>
                <div class="mt-3 text-xs text-green-300/60">> ancaman / temuan / ledakan</div>
            </div>
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4">
                <div class="text-xs text-green-300/60">> KWRN</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-green-200">
                    {{ fmt(props.dashboard.totals.kwrn) }}
                </div>
                <div class="mt-3 text-xs text-green-300/60">> ancaman / temuan / ledakan</div>
            </div>
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4">
                <div class="text-xs text-green-300/60">> WAN TEROR</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-green-200">
                    {{ fmt(props.dashboard.totals.wanTeror) }}
                </div>
                <div class="mt-3 text-xs text-green-300/60">> 5 kategori</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-xs font-semibold tracking-widest text-green-300/60">
                        > QUICK ACCESS
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="item in quickLinks"
                        :key="item.href"
                        variant="secondary"
                        as-child
                        class="border border-green-500/15 bg-black/30 text-green-200 hover:bg-green-500/10"
                    >
                        <Link :href="item.href">> {{ item.label }}</Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4">
                <div class="text-xs font-semibold tracking-widest text-green-300/60">
                    > TOP PROVINSI (ALL)
                </div>
                <div class="mt-3 grid gap-2">
                    <div
                        v-for="(row, idx) in props.dashboard.topProvincesAll"
                        :key="row.id"
                        class="flex items-center justify-between rounded-lg border border-green-500/10 bg-black/30 px-3 py-2 text-xs text-green-200/85"
                    >
                        <div class="truncate">
                            > {{ idx + 1 }}. {{ row.name || row.id }}
                        </div>
                        <div class="ml-3 shrink-0 text-green-300/70">
                            {{ fmt(row.count) }}
                        </div>
                    </div>
                    <div v-if="props.dashboard.topProvincesAll.length === 0" class="text-xs text-green-300/60">
                        > belum ada data.
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 xl:grid-cols-3">
            <div
                v-for="mod in props.dashboard.modules"
                :key="mod.key"
                class="rounded-xl border border-green-500/15 bg-black/20 p-4"
            >
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div class="text-xs font-semibold tracking-widest text-green-300/60">
                        > {{ mod.title }}
                    </div>
                    <Button as-child size="sm" class="border border-green-500/25 bg-green-500/10 text-green-200 hover:bg-green-500/15">
                        <Link :href="mod.href">Open</Link>
                    </Button>
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between rounded-lg border border-green-500/10 bg-black/30 px-3 py-2 text-xs text-green-200/85">
                        <div>> total</div>
                        <div class="text-green-300/70">{{ fmt(mod.total) }}</div>
                    </div>
                    <div
                        v-for="t in mod.types"
                        :key="t.value"
                        class="flex items-center justify-between rounded-lg border border-green-500/10 bg-black/30 px-3 py-2 text-xs text-green-200/85"
                    >
                        <div class="truncate">> {{ t.label }}</div>
                        <div class="ml-3 shrink-0 text-green-300/70">
                            {{ fmt(mod.countsByType[t.value] ?? 0) }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-xs font-semibold tracking-widest text-green-300/60">
                    > TOP PROVINSI
                </div>
                <div class="mt-2 grid gap-2">
                    <div
                        v-for="(row, idx) in mod.topProvinces"
                        :key="row.id"
                        class="flex items-center justify-between rounded-lg border border-green-500/10 bg-black/30 px-3 py-2 text-xs text-green-200/85"
                    >
                        <div class="truncate">
                            > {{ idx + 1 }}. {{ row.name || row.id }}
                        </div>
                        <div class="ml-3 shrink-0 text-green-300/70">
                            {{ fmt(row.count) }}
                        </div>
                    </div>
                    <div v-if="mod.topProvinces.length === 0" class="text-xs text-green-300/60">
                        > belum ada data.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
