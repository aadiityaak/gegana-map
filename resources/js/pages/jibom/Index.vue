<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { CircleMarker, Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type JibomItem = {
    id: number;
    incident_type: string;
    finding_type: string | null;
    description?: string | null;
    photos?: unknown;
    news_source?: string | null;
    latitude?: number | null;
    longitude?: number | null;
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
    items: Paginated<JibomItem>;
    filters: { type: string | null; province_id: string | null };
}>();

const typeLabel = (value: string) =>
    ({
        ancaman: 'Ancaman Pengeboman',
        temuan: 'Temuan Bom',
        ledakan: 'Ledakan Bom',
    })[value] ?? value;

const findingLabel = (value: string | null) =>
    ({
        'bom-militer': 'Bom Militer',
        'bom-rakitan': 'Bom Rakitan',
        'bom-ikan': 'Bom Ikan',
        petasan: 'Petasan & Lainnya',
        lainnya: 'Lainnya',
    })[value ?? ''] ?? value ?? '-';

const newsSourceLabel = (value: string | null | undefined) => {
    if (value === 'online') return 'online';
    if (value === 'ai_agent') return 'ai agent';
    return 'offline';
};

const currentType = computed(() => props.filters.type);
const currentProvinceId = computed(() => props.filters.province_id);

const createHref = computed(() => {
    if (!currentType.value) return '/jibom/create';
    return `/jibom/create?type=${encodeURIComponent(currentType.value)}`;
});

const listHref = (type: string | null) => {
    const qs = new URLSearchParams();
    if (type) qs.set('type', type);
    if (currentProvinceId.value) qs.set('province_id', currentProvinceId.value);
    const s = qs.toString();
    return s ? `/jibom?${s}` : '/jibom';
};

const deleteItem = (id: number) => {
    router.delete(`/jibom/${id}`);
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
const parseCoord = (v: unknown): number | null => {
    if (v == null) return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
};

const itemsWithCoords = computed(() =>
    props.items.data.filter((it) => parseCoord(it.latitude) !== null && parseCoord(it.longitude) !== null),
);

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
    if (!res.ok) {
        return;
    }
    const json = (await res.json()) as ProvinceRef[];
    provinces.value = Array.isArray(json) ? json : [];
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
    for (const item of itemsWithCoords.value) {
        const lat = parseCoord(item.latitude)!;
        const lng = parseCoord(item.longitude)!;
        const color = currentProvinceId.value && item.province_id === currentProvinceId.value
            ? '#22c55e'
            : '#a3e635';
        const marker = L.circleMarker([lat, lng], {
            radius: 7,
            color,
            fillColor: color,
            fillOpacity: 0.6,
            weight: 2,
        }).addTo(map!);

        const province = provinceNameById.value[item.province_id] ?? item.province_id;
        const tooltip = `${province}: ${typeLabel(item.incident_type)}`;
        marker.bindTooltip(tooltip, {
            direction: 'top',
            offset: [0, -8],
            className: 'leaflet-tooltip-dark',
        });

        marker.on('click', () => {
            const nextProvinceId = currentProvinceId.value === item.province_id ? null : item.province_id;
            router.get(
                '/jibom',
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
        attribution: '© CartoDB',
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
    () => [props.filters.type, props.filters.province_id, props.items.data] as const,
    async () => {
        await nextTick();
        await renderMarkers();
    },
);

onMounted(async () => {
    await loadProvinces();
    await ensureMap();
});
</script>

<template>
    <Head title="JIBOM" />

    <div class="p-4 font-mono sm:p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > JIBOM
                </h1>
                <Badge class="border border-green-500/25 bg-black/30 text-green-200">
                    {{ currentType ? typeLabel(currentType) : 'Semua' }}
                </Badge>
                <Badge
                    v-if="currentProvinceId"
                    class="border border-green-500/25 bg-black/30 text-green-200"
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
                :class="currentType ? '' : 'border-green-500/25 bg-green-500/10 text-green-200'"
                as-child
            >
                <Link :href="listHref(null)">Semua</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'ancaman' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link :href="listHref('ancaman')">Ancaman</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'temuan' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link :href="listHref('temuan')">Temuan</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'ledakan' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link :href="listHref('ledakan')">Ledakan</Link>
            </Button>
        </div>

        <div class="mb-4 rounded-xl border border-green-500/15 bg-black/20 p-3">
            <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                <span>> MAP INDONESIA</span>
                <span class="text-[11px]">> leaflet · cartodb dark</span>
            </div>
            <div
                ref="mapContainer"
                class="h-[500px] w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border border-green-500/15 bg-black/20">
            <div class="min-w-[860px]">
                <div class="grid grid-cols-12 gap-2 border-b border-green-500/15 p-3 text-xs text-green-300/70">
                    <div class="col-span-1">ID</div>
                    <div class="col-span-2">Jenis</div>
                    <div class="col-span-2">Temuan</div>
                    <div class="col-span-1">Sumber</div>
                    <div class="col-span-4">Wilayah</div>
                    <div class="col-span-2 text-right">Aksi</div>
                </div>
                <div v-if="props.items.data.length === 0" class="p-4 text-xs text-green-300/60">
                    > belum ada data.
                </div>
                <div
                    v-for="row in props.items.data"
                    :key="row.id"
                    class="grid grid-cols-12 gap-2 border-b border-green-500/10 p-3 text-xs text-green-200/85 last:border-b-0"
                >
                    <div class="col-span-1">{{ row.id }}</div>
                    <div class="col-span-2">
                        {{ typeLabel(row.incident_type) }}
                    </div>
                    <div class="col-span-2">
                        {{ findingLabel(row.finding_type) }}
                    </div>
                    <div class="col-span-1">
                        <span class="rounded border border-green-500/15 bg-black/20 px-2 py-0.5 text-[11px] text-green-200/85">
                            {{ newsSourceLabel(row.news_source) }}
                        </span>
                    </div>
                    <div class="col-span-4">
                        {{
                            [row.village_name, row.district_name, row.regency_name, row.province_name]
                                .filter(Boolean)
                                .join(', ') || '-'
                        }}
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-green-300/60">
                            <span v-if="photosCount(row.photos) > 0" class="rounded border border-green-500/15 bg-black/20 px-2 py-0.5">
                                > foto: {{ photosCount(row.photos) }}
                            </span>
                            <span v-if="descriptionPreview(row.description)" class="max-w-full truncate">
                                > {{ descriptionPreview(row.description) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-span-2 flex justify-end gap-2">
                        <Button size="sm" variant="secondary" as-child>
                            <Link :href="`/jibom/${row.id}`">View</Link>
                        </Button>
                        <Button size="sm" variant="secondary" as-child>
                            <Link :href="`/jibom/${row.id}/edit`">Edit</Link>
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
                :class="link.active ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link v-if="link.url" :href="link.url" v-html="link.label" />
                <span v-else v-html="link.label" />
            </Button>
        </div>
    </div>
</template>

<style>
.leaflet-tooltip-dark {
    background: rgba(0, 0, 0, 0.8) !important;
    border: 1px solid rgba(34, 197, 94, 0.3) !important;
    color: #bbf7d0 !important;
    font-family: monospace !important;
    font-size: 11px !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    box-shadow: none !important;
}
.leaflet-tooltip-dark::before {
    border-top-color: rgba(34, 197, 94, 0.3) !important;
}
.leaflet-container {
    background: #0a0a0a !important;
    font-family: monospace !important;
}
.leaflet-control-zoom a {
    background: rgba(0, 0, 0, 0.7) !important;
    color: #bbf7d0 !important;
    border: 1px solid rgba(34, 197, 94, 0.2) !important;
}
.leaflet-control-zoom a:hover {
    background: rgba(34, 197, 94, 0.15) !important;
}
.leaflet-control-attribution {
    background: rgba(0, 0, 0, 0.6) !important;
    color: rgba(34, 197, 94, 0.5) !important;
    font-size: 9px !important;
}
.leaflet-control-attribution a {
    color: rgba(34, 197, 94, 0.5) !important;
}
</style>
