<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { CircleMarker, Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AiPanel from '@/components/AiPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type WanTerorItem = {
    id: number;
    incident_type: string;
    finding_type: string | null;
    description?: string | null;
    photos?: unknown;
    news_source?: string | null;
    province_id: string;
    regency_id: string;
    district_id: string;
    village_id: string;
    province_name?: string | null;
    regency_name?: string | null;
    district_name?: string | null;
    village_name?: string | null;
    created_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    meta?: {
        current_page: number;
        last_page: number;
        total: number;
    };
};

const props = defineProps<{
    items: Paginated<WanTerorItem>;
    filters: { type: string | null; province_id: string | null };
}>();

const typeLabel = (value: string) =>
    ({
        napiter: 'Data Napiter',
        'ex-napiter': 'Data EX Napiter',
        'jaringan-terorisme': 'Jaringan Terorisme',
        'bullying-perundungan': 'Bullying/Perundungan',
        'aksi-teror': 'Aksi Teror',
    })[value] ?? value;

const newsSourceLabel = (value: string | null | undefined) => {
    if (value === 'online') return 'online';
    if (value === 'ai_agent') return 'ai agent';
    return 'offline';
};

const currentType = computed(() => props.filters.type);
const currentProvinceId = computed(() => props.filters.province_id);

const createHref = computed(() => {
    if (!currentType.value) return '/wan-teror/create';
    return `/wan-teror/create?type=${encodeURIComponent(currentType.value)}`;
});

const listHref = (type: string | null) => {
    const qs = new URLSearchParams();
    if (type) qs.set('type', type);
    if (currentProvinceId.value) qs.set('province_id', currentProvinceId.value);
    const s = qs.toString();
    return s ? `/wan-teror?${s}` : '/wan-teror';
};

const deleteItem = (id: number) => {
    router.delete(`/wan-teror/${id}`);
};

const stripHtml = (value: string) =>
    value
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

const descriptionPreview = (value: string | null | undefined) => {
    if (!value) return '';
    const text = stripHtml(String(value));
    if (text.length <= 90) return text;
    return `${text.slice(0, 90)}...`;
};

const photosCount = (value: unknown) => {
    if (Array.isArray(value)) return value.length;
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value) as unknown;
            if (Array.isArray(parsed)) return parsed.length;
        } catch {
            return 0;
        }
    }
    return 0;
};

type ProvinceRef = {
    id: string;
    name: string;
};

const mapContainer = ref<HTMLDivElement | null>(null);
let map: LeafletMap | null = null;
const markers: CircleMarker[] = [];
const counts = ref<{ id: string; name: string; count: number }[]>([]);

const provinceCentroids: Record<string, [number, number]> = {
    '11': [4.6951, 96.7494],
    '12': [2.1154, 99.5451],
    '13': [-0.7399, 100.8000],
    '14': [0.2933, 101.7068],
    '15': [-1.6100, 103.6130],
    '16': [-2.9761, 104.7754],
    '17': [-3.7931, 102.2717],
    '18': [-5.3971, 105.2668],
    '19': [-2.1650, 106.1397],
    '21': [0.9544, 104.4548],
    '31': [-6.2088, 106.8456],
    '32': [-6.9034, 107.6186],
    '33': [-7.0051, 110.4381],
    '34': [-7.7956, 110.3695],
    '35': [-7.5361, 112.2384],
    '36': [-6.2661, 106.2052],
    '51': [-8.6705, 115.2126],
    '52': [-8.5890, 116.5691],
    '53': [-8.6574, 121.0796],
    '61': [-0.0263, 109.3425],
    '62': [-2.1940, 113.9232],
    '63': [-3.4412, 114.8762],
    '64': [-0.5022, 117.1536],
    '65': [3.3052, 117.6351],
    '71': [1.4930, 124.8413],
    '72': [-0.8795, 119.8510],
    '73': [-5.1354, 119.4237],
    '74': [-3.9791, 122.5184],
    '75': [0.5332, 123.0601],
    '76': [-2.6780, 118.8935],
    '81': [-3.6539, 128.1750],
    '82': [0.7342, 127.5559],
    '91': [-4.2699, 138.0804],
    '92': [0.5063, 134.0627],
    '93': [-0.8680, 134.0750],
    '94': [-4.4720, 137.1000],
    '95': [-2.6000, 140.6667],
    '96': [-6.4333, 140.3167],
};

const provinces = ref<ProvinceRef[]>([]);
const provinceNameById = computed(() => {
    const mapR: Record<string, string> = {};
    for (const row of provinces.value) {
        mapR[row.id] = row.name;
    }
    return mapR;
});

const loadProvinces = async () => {
    const res = await fetch('/api/wilayah/provinces', {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) return;
    const json = (await res.json()) as ProvinceRef[];
    provinces.value = Array.isArray(json) ? json : [];
};

const loadCounts = async () => {
    const type = currentType.value;
    const qs = type ? `?type=${encodeURIComponent(type)}` : '';
    const res = await fetch(`/api/wan-teror/counts-by-province${qs}`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) return;
    const json = (await res.json()) as { data?: { id: string; name: string; count: number }[] };
    counts.value = Array.isArray(json.data) ? json.data : [];
};

const getLeaflet = async () => {
    const mod = await import('leaflet');
    return mod.default;
};

const clearMarkers = () => {
    for (const m of markers) {
        m.remove();
    }
    markers.length = 0;
};

const renderMarkers = async () => {
    if (!map) return;
    clearMarkers();

    const L = await getLeaflet();
    for (const row of counts.value) {
        const centroid = provinceCentroids[row.id];
        if (!centroid) continue;

        const count = Number(row.count) || 0;
        const isActive = currentProvinceId.value === row.id;
        const fg = isActive ? '#ABD5E5' : count > 0 ? '#ABD5E5' : '#52525b';
        const glow = isActive
            ? '0 0 8px rgba(171, 213, 229, 0.6)'
            : count > 0
                ? '0 0 4px rgba(163,230,53,0.4)'
                : 'none';
        const size = count > 0 ? 28 + Math.min(count, 40) * 0.6 : 20;

        const svgPin = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" fill="${fg}" stroke="${fg}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="filter:drop-shadow(${glow})"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>`;
        const labelSvg = count > 0
            ? `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" style="position:absolute;top:0;left:0;pointer-events:none"><text x="12" y="16" text-anchor="middle" fill="#0a0a0a" font-size="9" font-weight="700" font-family="monospace">${count}</text></svg>`
            : '';

        const icon = L.divIcon({
            className: 'wan-teror-map-pin',
            html: `<div style="position:relative;width:${size}px;height:${size}px">${svgPin}${labelSvg}</div>`,
            iconSize: [size, size],
            iconAnchor: [size / 2, size],
            popupAnchor: [0, -(size + 4)],
        });

        const marker = L.marker(centroid, { icon }).addTo(map!);

        marker.bindTooltip(`${row.name}: ${count}`, {
            direction: 'top',
            offset: [0, -(size + 4)],
            className: 'leaflet-tooltip-dark',
        });

        marker.on('click', () => {
            const nextProvinceId = currentProvinceId.value === row.id ? null : row.id;
            router.get(
                '/wan-teror',
                {
                    type: currentType.value ?? undefined,
                    province_id: nextProvinceId ?? undefined,
                },
                { preserveScroll: true, preserveState: true, replace: true },
            );
        });

        markers.push(marker);
    }
};

const ensureMap = async () => {
    if (typeof window === 'undefined') return;
    await nextTick();
    if (!mapContainer.value) return;

    if (map) {
        map.invalidateSize?.();
        await renderMarkers();
        return;
    }

    const L = await getLeaflet();
    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: false,
    }).setView([-2.5489, 118.0149], 5);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '� CartoDB',
        maxZoom: 19,
    }).addTo(map);

    L.control.attribution({ prefix: false }).addTo(map);

    await renderMarkers();
};

onBeforeUnmount(() => {
    clearMarkers();
    map?.remove();
    map = null;
});

watch(
    () => props.filters.type,
    async () => {
        await loadCounts();
        await renderMarkers();
    },
);

watch(
    () => props.filters.province_id,
    async () => {
        await renderMarkers();
    },
);

onMounted(async () => {
    await loadProvinces();
    await loadCounts();
    await ensureMap();
});
</script>

<template>
    <Head title="WAN TEROR" />

    <div class="p-4 font-mono sm:p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-sky-200">
                    > WAN TEROR
                </h1>
                <Badge class="border border-sky-500/25 bg-black/30 text-sky-200">
                    {{ currentType ? typeLabel(currentType) : 'Semua' }}
                </Badge>
                <Badge
                    v-if="currentProvinceId"
                    class="border border-sky-500/25 bg-black/30 text-sky-200"
                >
                    {{ provinceNameById[currentProvinceId] ?? currentProvinceId }}
                </Badge>
            </div>
            <div class="flex items-center gap-2">
                <Button as-child>
                    <Link :href="createHref">Tambah</Link>
                </Button>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <Button
                variant="secondary"
                :class="currentType ? '' : 'border-sky-500/50 bg-sky-500/25 text-sky-100'"
                as-child
            >
                <Link :href="listHref(null)">Semua</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'napiter' ? 'border-sky-500/50 bg-sky-500/25 text-sky-100' : ''"
                as-child
            >
                <Link :href="listHref('napiter')">Napiter</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'ex-napiter' ? 'border-sky-500/50 bg-sky-500/25 text-sky-100' : ''"
                as-child
            >
                <Link :href="listHref('ex-napiter')">EX Napiter</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'jaringan-terorisme' ? 'border-sky-500/50 bg-sky-500/25 text-sky-100' : ''"
                as-child
            >
                <Link :href="listHref('jaringan-terorisme')">Jaringan</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'bullying-perundungan' ? 'border-sky-500/50 bg-sky-500/25 text-sky-100' : ''"
                as-child
            >
                <Link :href="listHref('bullying-perundungan')">Bullying</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'aksi-teror' ? 'border-sky-500/50 bg-sky-500/25 text-sky-100' : ''"
                as-child
            >
                <Link :href="listHref('aksi-teror')">Aksi Teror</Link>
            </Button>
        </div>

        <div class="mb-4">
            <AiPanel module="wan-teror" />
        </div>

        <div class="mb-4 rounded-xl border border-sky-500/15 bg-black/20 p-3">
            <div class="mb-2 flex items-center justify-between text-sm text-sky-300">
                <span>> MAP INDONESIA</span>
                <span class="text-[11px]">> leaflet cartodb dark</span>
            </div>
            <div
                ref="mapContainer"
                class="h-[500px] w-full overflow-hidden rounded-lg border border-sky-500/15 bg-black/30"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border border-sky-500/15 bg-black/20">
            <div class="min-w-[860px]">
                <div class="grid grid-cols-12 gap-2 border-b border-sky-500/15 p-3 text-sm text-sky-300">
                    <div class="col-span-1">ID</div>
                    <div class="col-span-4">Kategori</div>
                    <div class="col-span-1">Sumber</div>
                    <div class="col-span-4">Wilayah</div>
                    <div class="col-span-2 text-right">Aksi</div>
                </div>
                <div
                    v-if="props.items.data.length === 0"
                    class="p-4 text-sm text-sky-300"
                >
                    > belum ada data.
                </div>
                <div
                    v-for="row in props.items.data"
                    :key="row.id"
                    class="grid grid-cols-12 gap-2 border-b border-sky-500/10 p-3 text-sm text-sky-200/85 last:border-b-0"
                >
                    <div class="col-span-1">{{ row.id }}</div>
                    <div class="col-span-4">
                        {{ typeLabel(row.incident_type) }}
                        <div
                            v-if="row.finding_type"
                            class="mt-1 text-[11px] text-sky-300"
                        >
                            > {{ row.finding_type }}
                        </div>
                    </div>
                    <div class="col-span-1">
                        <span class="rounded border border-sky-500/15 bg-black/20 px-2 py-0.5 text-[11px] text-sky-200/85">
                            {{ newsSourceLabel(row.news_source) }}
                        </span>
                    </div>
                    <div class="col-span-4">
                        {{
                            [row.village_name, row.district_name, row.regency_name, row.province_name]
                                .filter(Boolean)
                                .join(', ') ||
                                '-'
                        }}
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-sky-300">
                            <span
                                v-if="photosCount(row.photos) > 0"
                                class="rounded border border-sky-500/15 bg-black/20 px-2 py-0.5"
                            >
                                > foto: {{ photosCount(row.photos) }}
                            </span>
                            <span
                                v-if="descriptionPreview(row.description)"
                                class="max-w-full truncate"
                            >
                                > {{ descriptionPreview(row.description) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-span-2 flex justify-end gap-2">
                        <Button size="sm" variant="secondary" as-child>
                            <Link :href="`/wan-teror/${row.id}`">View</Link>
                        </Button>
                        <Button size="sm" variant="secondary" as-child>
                            <Link :href="`/wan-teror/${row.id}/edit`">Edit</Link>
                        </Button>
                        <Button size="sm" variant="destructive" @click="deleteItem(row.id)">
                            Hapus
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <Button
                v-for="link in props.items.links"
                :key="link.label"
                size="sm"
                variant="secondary"
                :disabled="!link.url"
                :class="link.active ? 'border-sky-500/50 bg-sky-500/25 text-sky-100' : ''"
                as-child
            >
                <Link v-if="link.url" :href="link.url" v-html="link.label" />
                <span v-else v-html="link.label" />
            </Button>
        </div>
    </div>
</template>

<style>
.wan-teror-map-pin {
    background: transparent !important;
    border: none !important;
}
.leaflet-tooltip-dark {
    background: rgba(0, 0, 0, 0.8) !important;
    border: 1px solid rgba(171, 213, 229, 0.3) !important;
    color: #bbf7d0 !important;
    font-family: monospace !important;
    font-size: 11px !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    box-shadow: none !important;
}
.leaflet-tooltip-dark::before {
    border-top-color: rgba(171, 213, 229, 0.3) !important;
}
.leaflet-container {
    background: #0a0a0a !important;
    font-family: monospace !important;
}
.leaflet-control-zoom a {
    background: rgba(0, 0, 0, 0.7) !important;
    color: #bbf7d0 !important;
    border: 1px solid rgba(171, 213, 229, 0.2) !important;
}
.leaflet-control-zoom a:hover {
    background: rgba(171, 213, 229, 0.15) !important;
}
.leaflet-control-attribution {
    background: rgba(0, 0, 0, 0.6) !important;
    color: rgba(171, 213, 229, 0.5) !important;
    font-size: 9px !important;
}
.leaflet-control-attribution a {
    color: rgba(171, 213, 229, 0.5) !important;
}
</style>

