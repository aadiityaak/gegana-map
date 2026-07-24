<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { CircleMarker, Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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

type MonitoringItem = {
    id: number | string;
    title: string;
    incident_date: string | null;
    severity_level: 'low' | 'medium' | 'high' | 'critical' | string;
    status: 'active' | 'monitoring' | 'resolved' | string;
    provinsi?: { id: number; nama: string } | null;
    kabupaten_kota?: { id: number; nama: string } | null;
    kecamatan?: { id: number; nama: string } | null;
    latitude?: number | string | null;
    longitude?: number | string | null;
};

type MonitoringResponse = {
    data: MonitoringItem[];
};

const props = defineProps<{
    dashboard: {
        totals: { jibom: number; kbrn: number; wanTeror: number; all: number };
        modules: ModuleSummary[];
        topProvincesAll: TopProvince[];
        monthly: {
            months: string[];
            series: {
                jibom: number[];
                kbrn: number[];
                wanTeror: number[];
            };
        };
        generatedAt: string;
    };
}>();

const fmt = (n: number) => new Intl.NumberFormat('id-ID').format(n ?? 0);
const moduleColor = (key: string) => (key === 'jibom' ? 'blue' : key === 'kbrn' ? 'violet' : 'amber');
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

const series = computed(() => props.dashboard.monthly?.series ?? { jibom: [], kbrn: [], wanTeror: [] });
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
    ...(series.value.kbrn ?? []),
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
const pathKBRN = computed(() => pathFor(series.value.kbrn ?? []));
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
        kbrn: series.value.kbrn?.[i] ?? 0,
        wanTeror: series.value.wanTeror?.[i] ?? 0,
    };
});

const hoverX = computed(() => {
    if (hoverIndex.value === null) return null;
    return padLeft + hoverIndex.value * stepX.value;
});

type ProvinceCount = {
    id: string;
    name: string;
    count: number;
};

// --- Province Leaflet Map ---
const provinceTab = ref<'jibom' | 'kbrn' | 'wan-teror'>('jibom');
const provinceMapContainer = ref<HTMLDivElement | null>(null);
let provinceMap: LeafletMap | null = null;
const provinceMarkers: CircleMarker[] = [];
const provinceCounts = ref<ProvinceCount[]>([]);

const provinceCentroids: Record<string, [number, number]> = {
    '11': [4.6951, 96.7494],   '12': [2.1154, 99.5451],
    '13': [-0.7399, 100.8000], '14': [0.2933, 101.7068],
    '15': [-1.6100, 103.6130], '16': [-2.9761, 104.7754],
    '17': [-3.7931, 102.2717], '18': [-5.3971, 105.2668],
    '19': [-2.1650, 106.1397], '21': [0.9544, 104.4548],
    '31': [-6.2088, 106.8456], '32': [-6.9034, 107.6186],
    '33': [-7.0051, 110.4381], '34': [-7.7956, 110.3695],
    '35': [-7.5361, 112.2384], '36': [-6.2661, 106.2052],
    '51': [-8.6705, 115.2126], '52': [-8.5890, 116.5691],
    '53': [-8.6574, 121.0796], '61': [-0.0263, 109.3425],
    '62': [-2.1940, 113.9232], '63': [-3.4412, 114.8762],
    '64': [-0.5022, 117.1536], '65': [3.3052, 117.6351],
    '71': [1.4930, 124.8413],  '72': [-0.8795, 119.8510],
    '73': [-5.1354, 119.4237], '74': [-3.9791, 122.5184],
    '75': [0.5332, 123.0601],  '76': [-2.6780, 118.8935],
    '81': [-3.6539, 128.1750], '82': [0.7342, 127.5559],
    '91': [-4.2699, 138.0804], '92': [0.5063, 134.0627],
    '93': [-0.8680, 134.0750], '94': [-4.4720, 137.1000],
    '95': [-2.6000, 140.6667], '96': [-6.4333, 140.3167],
};

const activeTabLabel = computed(() => {
    if (provinceTab.value === 'jibom') return 'JIBOM';
    if (provinceTab.value === 'kbrn') return 'KBRN';
    return 'WAN TEROR';
});

const loadProvinceCounts = async (type: string) => {
    const module = type === 'wan-teror' ? 'wan-teror' : type;
    const res = await fetch(`/api/${module}/counts-by-province`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) return;
    const json = (await res.json()) as { data?: ProvinceCount[] };
    provinceCounts.value = Array.isArray(json.data) ? json.data : [];
};

const clearProvinceMarkers = () => {
    for (const m of provinceMarkers) m.remove();
    provinceMarkers.length = 0;
};

const renderProvinceMarkers = async () => {
    if (!provinceMap) return;
    clearProvinceMarkers();
    const L = await getLeaflet();

    for (const row of provinceCounts.value) {
        const centroid = provinceCentroids[row.id];
        if (!centroid) continue;
        const count = Number(row.count) || 0;
        const fg = count > 0 ? '#ABD5E5' : '#52525b';
        const glow = count > 0 ? '0 0 4px rgba(171, 213, 229, 0.45)' : 'none';
        const size = count > 0 ? 28 + Math.min(count, 40) * 0.6 : 20;

        const svgPin = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" fill="${fg}" stroke="${fg}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="filter:drop-shadow(${glow})"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>`;
        const labelSvg = count > 0
            ? `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" style="position:absolute;top:0;left:0;pointer-events:none"><text x="12" y="16" text-anchor="middle" fill="#0a0a0a" font-size="9" font-weight="700" font-family="monospace">${count}</text></svg>`
            : '';

        const icon = L.divIcon({
            className: 'dashboard-province-pin',
            html: `<div style="position:relative;width:${size}px;height:${size}px">${svgPin}${labelSvg}</div>`,
            iconSize: [size, size],
            iconAnchor: [size / 2, size],
            popupAnchor: [0, -(size + 4)],
        });

        const marker = L.marker(centroid, { icon }).addTo(provinceMap!);
        marker.bindTooltip(`${row.name}: ${count}`, {
            direction: 'top',
            offset: [0, -(size + 4)],
            className: 'leaflet-tooltip-dark',
        });

        const href = provinceTab.value === 'jibom' ? '/jibom' : provinceTab.value === 'kbrn' ? '/kbrn' : '/wan-teror';
        marker.on('click', () => {
            router.visit(`${href}?province_id=${encodeURIComponent(row.id)}`);
        });

        provinceMarkers.push(marker);
    }
};

const ensureProvinceMap = async () => {
    if (typeof window === 'undefined') return;
    await nextTick();
    if (!provinceMapContainer.value) return;

    if (provinceMap) {
        provinceMap.invalidateSize?.();
        await renderProvinceMarkers();
        return;
    }

    const L = await getLeaflet();
    provinceMap = L.map(provinceMapContainer.value, {
        zoomControl: true,
        attributionControl: false,
    }).setView([-2.5489, 118.0149], 5);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '� CartoDB',
        maxZoom: 19,
    }).addTo(provinceMap);

    L.control.attribution({ prefix: false }).addTo(provinceMap);
    await renderProvinceMarkers();
};

const mapContainer = ref<HTMLDivElement | null>(null);
let map: any | null = null;
let markerLayer: any | null = null;
let mapReady = false;

const mapLoading = ref(false);
const mapError = ref<string | null>(null);
const mapItems = ref<MonitoringItem[]>([]);

const formatDateTime = (value: string | null) => {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString('id-ID');
};

const locationLabel = (item: MonitoringItem) => {
    const parts = [
        item.kecamatan?.nama,
        item.kabupaten_kota?.nama,
        item.provinsi?.nama,
    ].filter(Boolean) as string[];
    return parts.length ? parts.join(', ') : '-';
};

const getLeaflet = async () => {
    const mod = await import('leaflet');
    return (mod as any).default ?? mod;
};

const markerColor = (severity: string) => {
    const map: Record<string, string> = {
        low: '#8EC8DD',
        medium: '#fbbf24',
        high: '#fb7185',
        critical: '#f87171',
    };
    return map[severity] ?? '#ABD5E5';
};

const parseCoordNumber = (value: number | string | null | undefined): number | null => {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }
    if (typeof value !== 'string') return null;
    const normalized = value.trim().replace(',', '.');
    const n = Number.parseFloat(normalized);
    return Number.isFinite(n) ? n : null;
};

const normalizeIndonesiaCoords = (lat: number, lng: number): [number, number] => {
    const indonesiaLatOk = lat >= -11.5 && lat <= 6.5;
    const indonesiaLngOk = lng >= 95 && lng <= 141.5;
    if (indonesiaLatOk && indonesiaLngOk) return [lat, lng];

    const swappedIndonesiaLatOk = lng >= -11.5 && lng <= 6.5;
    const swappedIndonesiaLngOk = lat >= 95 && lat <= 141.5;
    if (swappedIndonesiaLatOk && swappedIndonesiaLngOk) return [lng, lat];

    return [lat, lng];
};

const normalizeId = (value: number | string): number | null => {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }
    const n = Number.parseInt(value, 10);
    return Number.isFinite(n) ? n : null;
};

const goToDetail = (id: number | string) => {
    const normalizedId = normalizeId(id);
    if (normalizedId === null) return;
    router.visit(`/ipoleksosbudkam/detail/${normalizedId}`);
};

const ensureMap = async () => {
    if (typeof window === 'undefined') return;
    await nextTick();
    if (!mapContainer.value) return;
    if (map) {
        setTimeout(() => {
            map?.invalidateSize?.();
        }, 0);
        return;
    }

    const L = await getLeaflet();

    map = L.map(mapContainer.value, {
        zoomControl: true,
    }).setView([-2.5489, 118.0149], 5);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '� CartoDB',
        maxZoom: 19,
    }).addTo(map);

    markerLayer = L.featureGroup().addTo(map);
    mapReady = true;

    setTimeout(() => {
        map?.invalidateSize?.();
    }, 0);
};

const updateMapMarkers = async () => {
    if (!mapReady || !map || !markerLayer) return;
    const L = await getLeaflet();

    markerLayer.clearLayers();
    const points: any[] = [];

    for (const item of mapItems.value) {
        const latRaw = parseCoordNumber(item.latitude);
        const lngRaw = parseCoordNumber(item.longitude);
        if (latRaw == null || lngRaw == null) continue;
        const [lat, lng] = normalizeIndonesiaCoords(latRaw, lngRaw);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
        points.push([lat, lng]);

        const marker = L.circleMarker([lat, lng], {
            radius: 7,
            color: markerColor(item.severity_level),
            fillColor: markerColor(item.severity_level),
            fillOpacity: 0.65,
            weight: 2,
        });

        const popupEl = document.createElement('div');
        popupEl.style.cssText =
            "font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; color: rgba(222,238,248,0.92);";

        const titleEl = document.createElement('div');
        titleEl.textContent = item.title;
        titleEl.style.cssText = 'font-weight: 700; margin-bottom: 6px;';
        popupEl.appendChild(titleEl);

        const metaEl = document.createElement('div');
        metaEl.textContent = `${formatDateTime(item.incident_date)} � ${locationLabel(item)}`;
        metaEl.style.cssText = 'font-size: 12px; opacity: 0.75; margin-bottom: 10px;';
        popupEl.appendChild(metaEl);

        const buttonEl = document.createElement('button');
        buttonEl.type = 'button';
        buttonEl.textContent = '> OPEN DETAIL';
        buttonEl.style.cssText =
            'display:inline-flex; align-items:center; justify-content:center; border: 1px solid rgba(171, 213, 229, 0.25); padding: 4px 10px; border-radius: 999px; background: rgba(171, 213, 229, 0.10); color: rgba(222,238,248,0.92); font-size: 12px; cursor: pointer; margin-bottom: 10px;';
        buttonEl.addEventListener('click', (ev) => {
            L.DomEvent.stopPropagation(ev);
            goToDetail(item.id);
        });
        popupEl.appendChild(buttonEl);

        L.DomEvent.disableClickPropagation(popupEl);
        marker.bindPopup(popupEl, { closeButton: false });
        marker.on('click', () => {
            goToDetail(item.id);
        });

        markerLayer.addLayer(marker);
    }

    if (points.length) {
        const bounds = L.latLngBounds(points);
        map.fitBounds(bounds, { padding: [24, 24], maxZoom: 10 });
    }
};

const loadMapData = async () => {
    mapLoading.value = true;
    mapError.value = null;

    try {
        const params = new URLSearchParams();
        params.set('page', '1');
        params.set('per_page', '200');
        params.set('sort_by', 'incident_date');
        params.set('sort_dir', 'desc');

        const res = await fetch(`/api/ipoleksosbudkam/monitoring-data?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const json = (await res.json().catch(() => null)) as MonitoringResponse | { message?: string } | null;
        if (!res.ok) {
            mapError.value = (json as any)?.message ?? 'Gagal memuat data peta.';
            mapItems.value = [];
            return;
        }

        mapItems.value = (json as MonitoringResponse).data ?? [];
    } catch {
        mapError.value = 'Gagal memuat data peta.';
        mapItems.value = [];
    } finally {
        mapLoading.value = false;
    }
};

onMounted(async () => {
    if (typeof window === 'undefined') return;
    await loadMapData();
    await ensureMap();
    await updateMapMarkers();

    await loadProvinceCounts('jibom');
    await ensureProvinceMap();
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
        markerLayer = null;
        mapReady = false;
    }
    if (provinceMap) {
        provinceMap.remove();
        provinceMap = null;
    }
    clearProvinceMarkers();
});

watch(
    () => mapItems.value,
    () => {
        if (!mapReady) return;
        void updateMapMarkers();
    },
    { deep: true },
);

watch(
    () => provinceTab.value,
    async (tab) => {
        await loadProvinceCounts(tab);
        await renderProvinceMarkers();
    },
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="relative p-4 font-mono dashboard-fx sm:p-6">
        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
            <div class="fx-grid absolute inset-0" />
            <div class="fx-scan absolute inset-0" />
            <div class="fx-noise absolute inset-0" />
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-sky-200">
                    > <span class="glitch" data-text="DASHBOARD">DASHBOARD</span
                    ><span class="cursor">_</span>
                </h1>
                <Badge class="border border-sky-500/25 bg-black/30 text-sky-200">
                    total: {{ fmt(props.dashboard.totals.all) }}
                </Badge>
                <Badge class="border border-sky-500/25 bg-black/30 text-sky-300">
                    sync: {{ generatedLabel }}
                </Badge>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div data-color="sky" class="rounded-xl border border-sky-500/35 p-4 dash-card">
                <div class="text-sm text-sky-300">> TOTAL</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-sky-200">
                    {{ fmt(props.dashboard.totals.all) }}
                </div>
                <div class="mt-3 text-sm text-sky-300">
                    > semua modul (kejadian)
                </div>
            </div>
            <div data-color="blue" class="rounded-xl border border-sky-500/35 p-4 dash-card">
                <div class="text-sm text-blue-300">> JIBOM</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-blue-200">
                    {{ fmt(props.dashboard.totals.jibom) }}
                </div>
                <div class="mt-3 text-sm text-blue-300">> ancaman / temuan / ledakan</div>
            </div>
            <div data-color="violet" class="rounded-xl border border-sky-500/35 p-4 dash-card">
                <div class="text-sm text-violet-300">> KBRN</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-violet-200">
                    {{ fmt(props.dashboard.totals.kbrn) }}
                </div>
                <div class="mt-3 text-sm text-violet-300">> ancaman / temuan / ledakan</div>
            </div>
            <div data-color="amber" class="rounded-xl border border-sky-500/35 p-4 dash-card">
                <div class="text-sm text-amber-300">> WAN TEROR</div>
                <div class="mt-2 text-3xl font-semibold tracking-widest text-amber-200">
                    {{ fmt(props.dashboard.totals.wanTeror) }}
                </div>
                <div class="mt-3 text-sm text-amber-300">> 5 kategori</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-sky-500/35 bg-black/20 p-4 lg:col-span-2 dash-card">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm font-semibold tracking-widest text-sky-300">
                        > GRAFIK 12 BULAN (JIBOM / KBRN / WAN TEROR)
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-sky-200/80">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                            <span>> JIBOM</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                            <span>> KBRN</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            <span>> WAN TEROR</span>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-lg border border-sky-500/35 bg-black/30 p-3 dash-chart">
                    <div v-if="months.length === 0" class="text-sm text-sky-300">
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
                                stroke="rgba(171, 213, 229, 0.12)"
                                stroke-width="1"
                            />
                            <line
                                :x1="padLeft"
                                :x2="chartWidth - padRight"
                                :y1="padTop + plotH"
                                :y2="padTop + plotH"
                                stroke="rgba(171, 213, 229, 0.18)"
                                stroke-width="1"
                            />
                            <line
                                :x1="padLeft"
                                :x2="padLeft"
                                :y1="padTop"
                                :y2="padTop + plotH"
                                stroke="rgba(171, 213, 229, 0.18)"
                                stroke-width="1"
                            />
                        </g>

                        <path
                            v-if="pathJibom"
                            :d="pathJibom"
                            fill="none"
                            stroke="#ABD5E5"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="dash-draw dash-draw-1"
                        />
                        <path
                            v-if="pathKBRN"
                            :d="pathKBRN"
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
                                stroke="rgba(171, 213, 229, 0.25)"
                                stroke-width="1"
                            />
                            <circle
                                v-if="series.jibom?.length"
                                :cx="hoverX"
                                :cy="pointXY(hoverIndex, series.jibom[hoverIndex] ?? 0).y"
                                r="3.2"
                                fill="#ABD5E5"
                                stroke="rgba(0,0,0,0.7)"
                                stroke-width="1"
                            />
                            <circle
                                v-if="series.kbrn?.length"
                                :cx="hoverX"
                                :cy="pointXY(hoverIndex, series.kbrn[hoverIndex] ?? 0).y"
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
                                fill="rgba(171,213,229,0.65)"
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
                                fill="rgba(171,213,229,0.65)"
                            >
                                {{ fmt(maxValue) }}
                            </text>
                            <text
                                :x="padLeft - 10"
                                :y="padTop + plotH"
                                text-anchor="end"
                                font-size="10"
                                fill="rgba(171,213,229,0.65)"
                            >
                                0
                            </text>
                        </g>
                    </svg>

                    <div
                        v-if="hoverLabel && hoverValues"
                        class="pointer-events-none absolute right-3 top-3 rounded-lg border border-sky-500/35 bg-black/60 px-3 py-2 text-[11px] text-sky-200/90 backdrop-blur"
                    >
                        <div>> {{ hoverLabel }} ({{ hoverMonthShort }})</div>
                        <div class="mt-1 grid gap-0.5 text-sky-200/85">
                            <div>> JIBOM: {{ fmt(hoverValues.jibom) }}</div>
                            <div>> KBRN: {{ fmt(hoverValues.kbrn) }}</div>
                            <div>> WAN TEROR: {{ fmt(hoverValues.wanTeror) }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        variant="secondary"
                        as-child
                        class="border border-sky-500/35 bg-black/30 text-sky-200 hover:bg-sky-500/10"
                    >
                        <Link href="/jibom">> JIBOM</Link>
                    </Button>
                    <Button
                        variant="secondary"
                        as-child
                        class="border border-sky-500/35 bg-black/30 text-sky-200 hover:bg-sky-500/10"
                    >
                        <Link href="/kbrn">> KBRN</Link>
                    </Button>
                    <Button
                        variant="secondary"
                        as-child
                        class="border border-sky-500/35 bg-black/30 text-sky-200 hover:bg-sky-500/10"
                    >
                        <Link href="/wan-teror">> WAN TEROR</Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border border-sky-500/35 bg-black/20 p-4 dash-card">
                <div class="text-sm font-semibold tracking-widest text-sky-300">
                    > TOP PROVINSI (ALL)
                </div>
                <div class="mt-3 grid gap-2">
                    <div
                        v-for="(row, idx) in props.dashboard.topProvincesAll"
                        :key="row.id"
                        class="flex items-center justify-between rounded-lg border border-sky-500/10 bg-black/30 px-3 py-2 text-sm text-sky-200/85"
                    >
                        <div class="truncate">
                            > {{ idx + 1 }}. {{ row.name || row.id }}
                        </div>
                        <div class="ml-3 shrink-0 text-sky-300">
                            {{ fmt(row.count) }}
                        </div>
                    </div>
                    <div v-if="props.dashboard.topProvincesAll.length === 0" class="text-sm text-sky-300">
                        > belum ada data.
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-sky-500/35 bg-black/20 p-4 dash-card">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3 text-sm text-sky-300">
                <div>> MAP: IPOLEKSOSBUDKAM (LAST 200)</div>
                <div class="flex items-center gap-3">
                    <span v-if="mapLoading">> loading_map...</span>
                    <span v-else>> loaded: {{ mapItems.length }}</span>
                </div>
            </div>

            <div v-if="mapError" class="rounded border border-red-500/25 bg-red-500/10 p-3 text-sm text-red-200">
                > {{ mapError }}
            </div>

            <div
                v-else
                ref="mapContainer"
                class="relative z-0 h-[320px] w-full overflow-hidden rounded-lg border border-sky-500/35 bg-black/30 sm:h-[420px]"
            />
        </div>

        <div class="mt-6 rounded-xl border border-sky-500/35 bg-black/20 p-4 dash-card">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3 text-sm text-sky-300">
                <div>> MAP PROVINSI: {{ activeTabLabel }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        class="border border-sky-500/35 bg-black/30 text-sky-200 hover:bg-sky-500/10"
                        :class="provinceTab === 'jibom' ? 'border-sky-500/25 bg-sky-500/10' : ''"
                        @click="provinceTab = 'jibom'"
                    >
                        JIBOM
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        class="border border-sky-500/35 bg-black/30 text-sky-200 hover:bg-sky-500/10"
                        :class="provinceTab === 'kbrn' ? 'border-sky-500/25 bg-sky-500/10' : ''"
                        @click="provinceTab = 'kbrn'"
                    >
                        KBRN
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        class="border border-sky-500/35 bg-black/30 text-sky-200 hover:bg-sky-500/10"
                        :class="provinceTab === 'wan-teror' ? 'border-sky-500/25 bg-sky-500/10' : ''"
                        @click="provinceTab = 'wan-teror'"
                    >
                        WAN TEROR
                    </Button>
                </div>
            </div>

            <div
                ref="provinceMapContainer"
                class="relative z-0 h-[320px] w-full overflow-hidden rounded-lg border border-sky-500/35 bg-black/30 sm:h-[420px]"
            />
        </div>

        <div class="mt-6 grid gap-4 xl:grid-cols-3">
            <div
                v-for="mod in props.dashboard.modules"
                :key="mod.key"
                :data-color="moduleColor(mod.key)"
                class="rounded-xl border border-sky-500/35 p-4 dash-card"
            >
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div class="text-sm font-semibold tracking-widest text-sky-300">
                        > {{ mod.title }}
                    </div>
                    <Button as-child size="sm" class="border border-sky-500/25 bg-sky-500/10 text-sky-200 hover:bg-sky-500/15">
                        <Link :href="mod.href">Open</Link>
                    </Button>
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between rounded-lg border border-sky-500/10 bg-black/30 px-3 py-2 text-sm text-sky-200/85">
                        <div>> total</div>
                        <div class="text-sky-300">{{ fmt(mod.total) }}</div>
                    </div>
                    <div
                        v-for="t in mod.types"
                        :key="t.value"
                        class="flex items-center justify-between rounded-lg border border-sky-500/10 bg-black/30 px-3 py-2 text-sm text-sky-200/85"
                    >
                        <div class="truncate">> {{ t.label }}</div>
                        <div class="ml-3 shrink-0 text-sky-300">
                            {{ fmt(mod.countsByType[t.value] ?? 0) }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-sm font-semibold tracking-widest text-sky-300">
                    > TOP PROVINSI
                </div>
                <div class="mt-2 grid gap-2">
                    <div
                        v-for="(row, idx) in mod.topProvinces"
                        :key="row.id"
                        class="flex items-center justify-between rounded-lg border border-sky-500/10 bg-black/30 px-3 py-2 text-sm text-sky-200/85"
                    >
                        <div class="truncate">
                            > {{ idx + 1 }}. {{ row.name || row.id }}
                        </div>
                        <div class="ml-3 shrink-0 text-sky-300">
                            {{ fmt(row.count) }}
                        </div>
                    </div>
                    <div v-if="mod.topProvinces.length === 0" class="text-sm text-sky-300">
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
            rgba(171, 213, 229, 0.08) 1px,
            transparent 1px
        ),
        linear-gradient(
            to right,
            rgba(171, 213, 229, 0.05) 1px,
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
        rgba(171, 213, 229, 0.08) 45%,
        rgba(171, 213, 229, 0.12) 50%,
        rgba(171, 213, 229, 0.08) 55%,
        transparent 100%
    );
    transform: translateY(-120%);
    animation: scan 6.5s linear infinite;
    mix-blend-mode: screen;
}

.fx-noise {
    opacity: 0.12;
    background-image: radial-gradient(
            rgba(171, 213, 229, 0.12) 1px,
            transparent 1px
        ),
        radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
    background-size: 3px 3px, 5px 5px;
    background-position: 0 0, 1px 2px;
    animation: noise 1.6s steps(2, end) infinite;
    mix-blend-mode: overlay;
}

/* Color variants — cohesive sky/atmosphere palette */
.dash-card[data-color='sky']   { --card-rgb: 56, 189, 248; }
.dash-card[data-color='blue']  { --card-rgb: 96, 165, 250; }
.dash-card[data-color='violet'] { --card-rgb: 167, 139, 250; }
.dash-card[data-color='amber'] { --card-rgb: 251, 191, 36; }

.dash-card[data-color] {
    background-color: rgba(var(--card-rgb), 0.35);
}

.dash-card {
    --card-rgb: 171, 213, 229;
    position: relative;
    box-shadow: 0 0 0 1px rgba(var(--card-rgb), 0.06),
        0 0 26px rgba(var(--card-rgb), 0.05);
    animation: glow 3.8s ease-in-out infinite;
}

.dash-chart {
    box-shadow: 0 0 0 1px rgba(171, 213, 229, 0.05),
        0 0 34px rgba(171, 213, 229, 0.06);
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
    text-shadow: -1px 0 rgba(171, 213, 229, 0.35);
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
        box-shadow: 0 0 0 1px rgba(var(--card-rgb, 171, 213, 229), 0.06),
            0 0 26px rgba(var(--card-rgb, 171, 213, 229), 0.05);
    }
    50% {
        box-shadow: 0 0 0 1px rgba(var(--card-rgb, 171, 213, 229), 0.1),
            0 0 40px rgba(var(--card-rgb, 171, 213, 229), 0.08);
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

