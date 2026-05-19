<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
        monthly: {
            months: string[];
            series: {
                jibom: number[];
                kwrn: number[];
                wanTeror: number[];
            };
        };
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

const months = computed(() => props.dashboard.monthly?.months ?? []);
const monthLabels = computed(() =>
    months.value.map((key) => {
        try {
            const d = new Date(`${key}-01T00:00:00`);
            return d.toLocaleString('id-ID', { month: 'short' });
        } catch {
            return key;
        }
    }),
);

const series = computed(() => props.dashboard.monthly?.series ?? { jibom: [], kwrn: [], wanTeror: [] });
const chartWidth = 720;
const chartHeight = 240;
const padLeft = 44;
const padRight = 16;
const padTop = 16;
const padBottom = 34;

const plotW = computed(() => chartWidth - padLeft - padRight);
const plotH = computed(() => chartHeight - padTop - padBottom);

const allValues = computed(() => [
    ...(series.value.jibom ?? []),
    ...(series.value.kwrn ?? []),
    ...(series.value.wanTeror ?? []),
]);
const maxValue = computed(() => Math.max(1, ...allValues.value.map((n) => (Number.isFinite(n) ? n : 0))));
const stepX = computed(() => {
    const n = months.value.length;
    if (n <= 1) return plotW.value;
    return plotW.value / (n - 1);
});

const pointXY = (idx: number, value: number) => {
    const x = padLeft + idx * stepX.value;
    const v = Number.isFinite(value) ? value : 0;
    const y = padTop + plotH.value * (1 - v / maxValue.value);
    return { x, y };
};

const pathFor = (values: number[]) => {
    if (!values || values.length === 0) return '';
    const pts = values.map((v, i) => pointXY(i, v));
    return pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(2)} ${p.y.toFixed(2)}`).join(' ');
};

const pathJibom = computed(() => pathFor(series.value.jibom ?? []));
const pathKwrn = computed(() => pathFor(series.value.kwrn ?? []));
const pathWanTeror = computed(() => pathFor(series.value.wanTeror ?? []));

const hoverIndex = ref<number | null>(null);
const chartEl = ref<SVGSVGElement | null>(null);

const onChartMove = (e: MouseEvent) => {
    const el = chartEl.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const n = months.value.length;
    if (n <= 0) return;
    const raw = (x - padLeft) / stepX.value;
    const idx = Math.min(n - 1, Math.max(0, Math.round(raw)));
    hoverIndex.value = idx;
};
const onChartLeave = () => {
    hoverIndex.value = null;
};

const hoverLabel = computed(() => {
    if (hoverIndex.value === null) return null;
    return months.value[hoverIndex.value] ?? null;
});
const hoverMonthShort = computed(() => {
    if (hoverIndex.value === null) return null;
    return monthLabels.value[hoverIndex.value] ?? null;
});
const hoverValues = computed(() => {
    if (hoverIndex.value === null) return null;
    const i = hoverIndex.value;
    return {
        jibom: series.value.jibom?.[i] ?? 0,
        kwrn: series.value.kwrn?.[i] ?? 0,
        wanTeror: series.value.wanTeror?.[i] ?? 0,
    };
});

const hoverX = computed(() => {
    if (hoverIndex.value === null) return null;
    return padLeft + hoverIndex.value * stepX.value;
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="relative p-6 font-mono dashboard-fx">
        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
            <div class="fx-grid absolute inset-0" />
            <div class="fx-scan absolute inset-0" />
            <div class="fx-noise absolute inset-0" />
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > <span class="glitch" data-text="DASHBOARD">DASHBOARD</span
                    ><span class="cursor">_</span>
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
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
                <div class="text-xs text-green-300/60">> TOTAL</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-green-200">
                    {{ fmt(props.dashboard.totals.all) }}
                </div>
                <div class="mt-3 text-xs text-green-300/60">
                    > semua modul (kejadian)
                </div>
            </div>
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
                <div class="text-xs text-green-300/60">> JIBOM</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-green-200">
                    {{ fmt(props.dashboard.totals.jibom) }}
                </div>
                <div class="mt-3 text-xs text-green-300/60">> ancaman / temuan / ledakan</div>
            </div>
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
                <div class="text-xs text-green-300/60">> KWRN</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-green-200">
                    {{ fmt(props.dashboard.totals.kwrn) }}
                </div>
                <div class="mt-3 text-xs text-green-300/60">> ancaman / temuan / ledakan</div>
            </div>
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
                <div class="text-xs text-green-300/60">> WAN TEROR</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-green-200">
                    {{ fmt(props.dashboard.totals.wanTeror) }}
                </div>
                <div class="mt-3 text-xs text-green-300/60">> 5 kategori</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 lg:col-span-2 dash-card">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-xs font-semibold tracking-widest text-green-300/60">
                        > GRAFIK 12 BULAN (JIBOM / KWRN / WAN TEROR)
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-green-200/80">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-green-400"></span>
                            <span>> JIBOM</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                            <span>> KWRN</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            <span>> WAN TEROR</span>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-lg border border-green-500/15 bg-black/30 p-3 dash-chart">
                    <div v-if="months.length === 0" class="text-xs text-green-300/60">
                        > belum ada data grafik.
                    </div>
                    <svg
                        v-else
                        ref="chartEl"
                        :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                        class="h-[260px] w-full"
                        @mousemove="onChartMove"
                        @mouseleave="onChartLeave"
                    >
                        <g>
                            <line
                                v-for="i in 4"
                                :key="i"
                                :x1="padLeft"
                                :x2="chartWidth - padRight"
                                :y1="padTop + (plotH * i) / 4"
                                :y2="padTop + (plotH * i) / 4"
                                stroke="rgba(34,197,94,0.12)"
                                stroke-width="1"
                            />
                            <line
                                :x1="padLeft"
                                :x2="chartWidth - padRight"
                                :y1="padTop + plotH"
                                :y2="padTop + plotH"
                                stroke="rgba(34,197,94,0.18)"
                                stroke-width="1"
                            />
                            <line
                                :x1="padLeft"
                                :x2="padLeft"
                                :y1="padTop"
                                :y2="padTop + plotH"
                                stroke="rgba(34,197,94,0.18)"
                                stroke-width="1"
                            />
                        </g>

                        <path
                            v-if="pathJibom"
                            :d="pathJibom"
                            fill="none"
                            stroke="#22c55e"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="dash-draw dash-draw-1"
                        />
                        <path
                            v-if="pathKwrn"
                            :d="pathKwrn"
                            fill="none"
                            stroke="#38bdf8"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="dash-draw dash-draw-2"
                        />
                        <path
                            v-if="pathWanTeror"
                            :d="pathWanTeror"
                            fill="none"
                            stroke="#fbbf24"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="dash-draw dash-draw-3"
                        />

                        <g v-if="hoverIndex !== null && hoverX !== null">
                            <line
                                :x1="hoverX"
                                :x2="hoverX"
                                :y1="padTop"
                                :y2="padTop + plotH"
                                stroke="rgba(34,197,94,0.25)"
                                stroke-width="1"
                            />
                            <circle
                                v-if="series.jibom?.length"
                                :cx="hoverX"
                                :cy="pointXY(hoverIndex, series.jibom[hoverIndex] ?? 0).y"
                                r="3.2"
                                fill="#22c55e"
                                stroke="rgba(0,0,0,0.7)"
                                stroke-width="1"
                            />
                            <circle
                                v-if="series.kwrn?.length"
                                :cx="hoverX"
                                :cy="pointXY(hoverIndex, series.kwrn[hoverIndex] ?? 0).y"
                                r="3.2"
                                fill="#38bdf8"
                                stroke="rgba(0,0,0,0.7)"
                                stroke-width="1"
                            />
                            <circle
                                v-if="series.wanTeror?.length"
                                :cx="hoverX"
                                :cy="pointXY(hoverIndex, series.wanTeror[hoverIndex] ?? 0).y"
                                r="3.2"
                                fill="#fbbf24"
                                stroke="rgba(0,0,0,0.7)"
                                stroke-width="1"
                            />
                        </g>

                        <g>
                            <text
                                v-for="(lbl, i) in monthLabels"
                                :key="`${lbl}-${i}`"
                                :x="padLeft + i * stepX"
                                :y="chartHeight - 12"
                                text-anchor="middle"
                                font-size="10"
                                fill="rgba(134,239,172,0.65)"
                            >
                                {{ lbl }}
                            </text>
                        </g>

                        <g>
                            <text
                                :x="padLeft - 10"
                                :y="padTop + 10"
                                text-anchor="end"
                                font-size="10"
                                fill="rgba(134,239,172,0.65)"
                            >
                                {{ fmt(maxValue) }}
                            </text>
                            <text
                                :x="padLeft - 10"
                                :y="padTop + plotH"
                                text-anchor="end"
                                font-size="10"
                                fill="rgba(134,239,172,0.65)"
                            >
                                0
                            </text>
                        </g>
                    </svg>

                    <div
                        v-if="hoverLabel && hoverValues"
                        class="pointer-events-none absolute right-3 top-3 rounded-lg border border-green-500/15 bg-black/60 px-3 py-2 text-[11px] text-green-200/90 backdrop-blur"
                    >
                        <div>> {{ hoverLabel }} ({{ hoverMonthShort }})</div>
                        <div class="mt-1 grid gap-0.5 text-green-200/85">
                            <div>> JIBOM: {{ fmt(hoverValues.jibom) }}</div>
                            <div>> KWRN: {{ fmt(hoverValues.kwrn) }}</div>
                            <div>> WAN TEROR: {{ fmt(hoverValues.wanTeror) }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        variant="secondary"
                        as-child
                        class="border border-green-500/15 bg-black/30 text-green-200 hover:bg-green-500/10"
                    >
                        <Link href="/jibom">> JIBOM</Link>
                    </Button>
                    <Button
                        variant="secondary"
                        as-child
                        class="border border-green-500/15 bg-black/30 text-green-200 hover:bg-green-500/10"
                    >
                        <Link href="/kwrn">> KWRN</Link>
                    </Button>
                    <Button
                        variant="secondary"
                        as-child
                        class="border border-green-500/15 bg-black/30 text-green-200 hover:bg-green-500/10"
                    >
                        <Link href="/wan-teror">> WAN TEROR</Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
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
                class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card"
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

<style scoped>
.dashboard-fx {
    isolation: isolate;
}

.fx-grid {
    opacity: 0.18;
    background-image: linear-gradient(
            to bottom,
            rgba(34, 197, 94, 0.08) 1px,
            transparent 1px
        ),
        linear-gradient(
            to right,
            rgba(34, 197, 94, 0.05) 1px,
            transparent 1px
        );
    background-size: 100% 3px, 40px 100%;
    filter: saturate(1.1);
}

.fx-scan {
    opacity: 0.22;
    background: linear-gradient(
        to bottom,
        transparent 0%,
        rgba(34, 197, 94, 0.08) 45%,
        rgba(34, 197, 94, 0.12) 50%,
        rgba(34, 197, 94, 0.08) 55%,
        transparent 100%
    );
    transform: translateY(-120%);
    animation: scan 6.5s linear infinite;
    mix-blend-mode: screen;
}

.fx-noise {
    opacity: 0.12;
    background-image: radial-gradient(
            rgba(34, 197, 94, 0.12) 1px,
            transparent 1px
        ),
        radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
    background-size: 3px 3px, 5px 5px;
    background-position: 0 0, 1px 2px;
    animation: noise 1.6s steps(2, end) infinite;
    mix-blend-mode: overlay;
}

.dash-card {
    position: relative;
    box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.06),
        0 0 26px rgba(34, 197, 94, 0.05);
    animation: glow 3.8s ease-in-out infinite;
}

.dash-chart {
    box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.05),
        0 0 34px rgba(34, 197, 94, 0.06);
}

.dash-draw {
    stroke-dasharray: 1800;
    stroke-dashoffset: 1800;
    animation: draw 1.25s ease forwards;
}

.dash-draw-2 {
    animation-delay: 0.08s;
}

.dash-draw-3 {
    animation-delay: 0.16s;
}

.glitch {
    position: relative;
    display: inline-block;
}

.glitch::before,
.glitch::after {
    content: attr(data-text);
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    overflow: hidden;
    opacity: 0.55;
    pointer-events: none;
}

.glitch::before {
    transform: translateX(-0.8px);
    text-shadow: -1px 0 rgba(34, 197, 94, 0.35);
    clip-path: inset(0 0 55% 0);
    animation: glitch 2.7s infinite linear alternate-reverse;
}

.glitch::after {
    transform: translateX(0.8px);
    text-shadow: 1px 0 rgba(56, 189, 248, 0.28);
    clip-path: inset(55% 0 0 0);
    animation: glitch 2.3s infinite linear alternate;
}

.cursor {
    display: inline-block;
    margin-left: 2px;
    opacity: 0.9;
    animation: blink 1s steps(1, end) infinite;
}

@keyframes scan {
    0% {
        transform: translateY(-120%);
    }
    100% {
        transform: translateY(120%);
    }
}

@keyframes noise {
    0% {
        transform: translate3d(0, 0, 0);
    }
    25% {
        transform: translate3d(-1px, 1px, 0);
    }
    50% {
        transform: translate3d(1px, -1px, 0);
    }
    75% {
        transform: translate3d(-1px, -1px, 0);
    }
    100% {
        transform: translate3d(0, 0, 0);
    }
}

@keyframes glow {
    0%,
    100% {
        box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.06),
            0 0 26px rgba(34, 197, 94, 0.05);
    }
    50% {
        box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.1),
            0 0 40px rgba(34, 197, 94, 0.08);
    }
}

@keyframes draw {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes blink {
    0%,
    49% {
        opacity: 0.9;
    }
    50%,
    100% {
        opacity: 0.1;
    }
}

@keyframes glitch {
    0% {
        clip-path: inset(0 0 55% 0);
        transform: translateX(-0.8px);
    }
    10% {
        clip-path: inset(8% 0 42% 0);
        transform: translateX(0.6px);
    }
    20% {
        clip-path: inset(18% 0 60% 0);
        transform: translateX(-0.4px);
    }
    30% {
        clip-path: inset(42% 0 22% 0);
        transform: translateX(0.8px);
    }
    40% {
        clip-path: inset(58% 0 10% 0);
        transform: translateX(-0.6px);
    }
    50% {
        clip-path: inset(22% 0 55% 0);
        transform: translateX(0.4px);
    }
    60% {
        clip-path: inset(0 0 65% 0);
        transform: translateX(-0.8px);
    }
    70% {
        clip-path: inset(65% 0 0 0);
        transform: translateX(0.6px);
    }
    80% {
        clip-path: inset(35% 0 40% 0);
        transform: translateX(-0.4px);
    }
    90% {
        clip-path: inset(50% 0 25% 0);
        transform: translateX(0.8px);
    }
    100% {
        clip-path: inset(0 0 55% 0);
        transform: translateX(-0.8px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fx-scan,
    .fx-noise,
    .dash-card,
    .dash-draw,
    .glitch::before,
    .glitch::after,
    .cursor {
        animation: none !important;
    }
}
</style>
