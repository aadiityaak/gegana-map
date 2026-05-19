<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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

type ProvinceCount = {
    id: string;
    name: string;
    count: number;
};

const normalizeName = (value: string) =>
    value
        .toUpperCase()
        .replace(/\s+/g, ' ')
        .trim();

const provinceAliases: Record<string, string> = {
    'JAKARTA RAYA': 'DKI JAKARTA',
    JAKARTA: 'DKI JAKARTA',
    YOGYAKARTA: 'DI YOGYAKARTA',
    'DAERAH ISTIMEWA YOGYAKARTA': 'DI YOGYAKARTA',
};

const provinceKey = (value: string) => {
    const k = normalizeName(value);
    return provinceAliases[k] ?? k;
};

const colorForCount = (count: number, max: number) => {
    if (count <= 0) return '#052e16';
    if (max <= 0) return '#14532d';
    const ratio = count / max;
    if (ratio <= 0.25) return '#14532d';
    if (ratio <= 0.5) return '#166534';
    if (ratio <= 0.75) return '#16a34a';
    return '#22c55e';
};

const jibomMapSvg = ref<string>('');
const jibomMapError = ref<string | null>(null);
const jibomMapRoot = ref<HTMLDivElement | null>(null);
const jibomCounts = ref<ProvinceCount[]>([]);
const jibomHovered = ref<{ name: string; count: number } | null>(null);

const kwrnMapSvg = ref<string>('');
const kwrnMapError = ref<string | null>(null);
const kwrnMapRoot = ref<HTMLDivElement | null>(null);
const kwrnCounts = ref<ProvinceCount[]>([]);
const kwrnHovered = ref<{ name: string; count: number } | null>(null);

const wanTerorMapSvg = ref<string>('');
const wanTerorMapError = ref<string | null>(null);
const wanTerorMapRoot = ref<HTMLDivElement | null>(null);
const wanTerorCounts = ref<ProvinceCount[]>([]);
const wanTerorHovered = ref<{ name: string; count: number } | null>(null);

const applyCountsToSvg = async (options: {
    root: HTMLDivElement | null;
    counts: ProvinceCount[];
    hovered: typeof jibomHovered;
    labelsId: string;
    listBaseHref: string;
}) => {
    await nextTick();

    const root = options.root;
    if (!root) return;
    const svg = root.querySelector('svg') as SVGSVGElement | null;
    if (!svg) return;

    const countsByProvinceName: Record<string, number> = {};
    const provinceIdByName: Record<string, string> = {};
    for (const row of options.counts) {
        const mapped = provinceKey(row.name);
        countsByProvinceName[mapped] = Number(row.count) || 0;
        provinceIdByName[mapped] = row.id;
    }

    const max = Math.max(
        0,
        ...Object.values(countsByProvinceName).map((v) => (Number.isFinite(v) ? v : 0)),
    );

    const labelsGroupId = `dashboard-count-labels-${options.labelsId}`;
    const oldLabels = svg.querySelector(`#${labelsGroupId}`);
    oldLabels?.remove();

    const labelsGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    labelsGroup.setAttribute('id', labelsGroupId);
    svg.appendChild(labelsGroup);

    const paths = svg.querySelectorAll<SVGPathElement>('path[data-name], path[title]');
    for (const el of Array.from(paths)) {
        const rawName = (el.getAttribute('data-name') ?? el.getAttribute('title') ?? '').trim();
        if (!rawName) continue;

        const originalName = (el.getAttribute('data-title-original') ?? '').trim() || rawName;
        if (!el.getAttribute('data-title-original') && originalName) {
            el.setAttribute('data-title-original', originalName);
        }

        const rawTitle = originalName;
        if (!rawTitle) continue;
        const mappedTitle = provinceKey(rawTitle);
        const count = countsByProvinceName[mappedTitle] ?? 0;
        const provinceId = provinceIdByName[mappedTitle] ?? null;

        el.style.fill = colorForCount(count, max);
        el.style.stroke = 'rgba(34,197,94,0.25)';
        el.style.strokeWidth = '0.7';
        el.style.cursor = provinceId ? 'pointer' : 'default';
        el.setAttribute('data-count', String(count));
        el.setAttribute('title', `${rawTitle} (${count})`);

        el.onmouseenter = () => {
            options.hovered.value = { name: rawTitle, count };
        };
        el.onmouseleave = () => {
            options.hovered.value = null;
        };
        el.onclick = () => {
            if (!provinceId) return;
            router.visit(`${options.listBaseHref}?province_id=${encodeURIComponent(provinceId)}`);
        };

        if (count > 0) {
            try {
                const bbox = el.getBBox();
                const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.textContent = String(count);
                text.setAttribute('x', String(bbox.x + bbox.width / 2));
                text.setAttribute('y', String(bbox.y + bbox.height / 2));
                text.setAttribute('text-anchor', 'middle');
                text.setAttribute('dominant-baseline', 'middle');
                text.setAttribute(
                    'style',
                    'font-size: 10px; font-weight: 700; fill: rgba(236, 253, 245, 0.95); paint-order: stroke; stroke: rgba(0,0,0,0.65); stroke-width: 2px; pointer-events: none;',
                );
                labelsGroup.appendChild(text);
            } catch {
                //
            }
        }
    }
};

const loadSvgMap = async (url: string): Promise<string> => {
    const res = await fetch(url, { headers: { Accept: 'image/svg+xml' } });
    if (!res.ok) {
        throw new Error(`Gagal memuat map (${res.status})`);
    }
    return await res.text();
};

const loadCountsByProvince = async (url: string): Promise<ProvinceCount[]> => {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const json = (await res.json().catch(() => null)) as { data?: Array<{ id: string; name: string; count: number }> } | { message?: string } | null;
    if (!res.ok) {
        throw new Error((json as any)?.message ?? `Gagal memuat counts (${res.status})`);
    }
    return (json as any)?.data ?? [];
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
        low: '#34d399',
        medium: '#fbbf24',
        high: '#fb7185',
        critical: '#f87171',
    };
    return map[severity] ?? '#22c55e';
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

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
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
            "font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; color: rgba(226,255,232,0.92);";

        const titleEl = document.createElement('div');
        titleEl.textContent = item.title;
        titleEl.style.cssText = 'font-weight: 700; margin-bottom: 6px;';
        popupEl.appendChild(titleEl);

        const metaEl = document.createElement('div');
        metaEl.textContent = `${formatDateTime(item.incident_date)} · ${locationLabel(item)}`;
        metaEl.style.cssText = 'font-size: 12px; opacity: 0.75; margin-bottom: 10px;';
        popupEl.appendChild(metaEl);

        const buttonEl = document.createElement('button');
        buttonEl.type = 'button';
        buttonEl.textContent = '> OPEN DETAIL';
        buttonEl.style.cssText =
            'display:inline-flex; align-items:center; justify-content:center; border: 1px solid rgba(34,197,94,0.25); padding: 4px 10px; border-radius: 999px; background: rgba(34,197,94,0.10); color: rgba(226,255,232,0.92); font-size: 12px; cursor: pointer; margin-bottom: 10px;';
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

    try {
        const [svgJibom, svgKwrn, svgWan, countsJibom, countsKwrn, countsWan] = await Promise.all([
            loadSvgMap('/api/jibom/indonesia-map-svg'),
            loadSvgMap('/api/kwrn/indonesia-map-svg'),
            loadSvgMap('/api/wan-teror/indonesia-map-svg'),
            loadCountsByProvince('/api/jibom/counts-by-province'),
            loadCountsByProvince('/api/kwrn/counts-by-province'),
            loadCountsByProvince('/api/wan-teror/counts-by-province'),
        ]);

        jibomMapSvg.value = svgJibom;
        kwrnMapSvg.value = svgKwrn;
        wanTerorMapSvg.value = svgWan;

        jibomCounts.value = countsJibom;
        kwrnCounts.value = countsKwrn;
        wanTerorCounts.value = countsWan;
    } catch (e) {
        const msg = e instanceof Error ? e.message : 'Gagal memuat peta provinsi.';
        jibomMapError.value = msg;
        kwrnMapError.value = msg;
        wanTerorMapError.value = msg;
    }
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
        markerLayer = null;
        mapReady = false;
    }
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
    [jibomMapSvg, jibomCounts, jibomMapRoot],
    () => {
        void applyCountsToSvg({
            root: jibomMapRoot.value,
            counts: jibomCounts.value,
            hovered: jibomHovered,
            labelsId: 'jibom',
            listBaseHref: '/jibom',
        });
    },
    { deep: true, flush: 'post', immediate: true },
);

watch(
    [kwrnMapSvg, kwrnCounts, kwrnMapRoot],
    () => {
        void applyCountsToSvg({
            root: kwrnMapRoot.value,
            counts: kwrnCounts.value,
            hovered: kwrnHovered,
            labelsId: 'kwrn',
            listBaseHref: '/kwrn',
        });
    },
    { deep: true, flush: 'post', immediate: true },
);

watch(
    [wanTerorMapSvg, wanTerorCounts, wanTerorMapRoot],
    () => {
        void applyCountsToSvg({
            root: wanTerorMapRoot.value,
            counts: wanTerorCounts.value,
            hovered: wanTerorHovered,
            labelsId: 'wan-teror',
            listBaseHref: '/wan-teror',
        });
    },
    { deep: true, flush: 'post', immediate: true },
);
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

        <div class="mt-6 rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3 text-xs text-green-300/60">
                <div>> MAP: IPOLEKSOSBUDKAM (LAST 200)</div>
                <div class="flex items-center gap-3">
                    <span v-if="mapLoading">> loading_map...</span>
                    <span v-else>> loaded: {{ mapItems.length }}</span>
                </div>
            </div>

            <div v-if="mapError" class="rounded border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-200">
                > {{ mapError }}
            </div>

            <div
                v-else
                ref="mapContainer"
                class="relative z-0 h-[420px] w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30"
            />
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
                <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                    <span>> MAP JIBOM</span>
                    <span class="text-[11px]">> by_province</span>
                </div>
                <div v-if="jibomMapError" class="rounded-lg border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-200">
                    > {{ jibomMapError }}
                </div>
                <div
                    v-else-if="jibomMapSvg"
                    class="relative w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30 p-2"
                >
                    <div
                        v-if="jibomHovered"
                        class="pointer-events-none absolute left-2 right-2 top-2 z-10 flex items-center justify-between rounded-lg border border-green-500/15 bg-black/50 px-3 py-2 text-xs text-green-200/90 backdrop-blur"
                    >
                        <span>> {{ jibomHovered.name }}</span>
                        <span class="text-[11px]">> kejadian: {{ fmt(jibomHovered.count) }}</span>
                    </div>
                    <div
                        ref="jibomMapRoot"
                        class="mx-auto max-w-[1210px] overflow-hidden rounded-lg [&_svg]:h-auto [&_svg]:w-full [&_svg]:rounded-lg"
                        v-html="jibomMapSvg"
                    />
                </div>
                <div v-else class="rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-300/60">
                    loading_map...
                </div>
            </div>

            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
                <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                    <span>> MAP KWRN</span>
                    <span class="text-[11px]">> by_province</span>
                </div>
                <div v-if="kwrnMapError" class="rounded-lg border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-200">
                    > {{ kwrnMapError }}
                </div>
                <div
                    v-else-if="kwrnMapSvg"
                    class="relative w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30 p-2"
                >
                    <div
                        v-if="kwrnHovered"
                        class="pointer-events-none absolute left-2 right-2 top-2 z-10 flex items-center justify-between rounded-lg border border-green-500/15 bg-black/50 px-3 py-2 text-xs text-green-200/90 backdrop-blur"
                    >
                        <span>> {{ kwrnHovered.name }}</span>
                        <span class="text-[11px]">> kejadian: {{ fmt(kwrnHovered.count) }}</span>
                    </div>
                    <div
                        ref="kwrnMapRoot"
                        class="mx-auto max-w-[1210px] overflow-hidden rounded-lg [&_svg]:h-auto [&_svg]:w-full [&_svg]:rounded-lg"
                        v-html="kwrnMapSvg"
                    />
                </div>
                <div v-else class="rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-300/60">
                    loading_map...
                </div>
            </div>

            <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 dash-card">
                <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                    <span>> MAP WAN TEROR</span>
                    <span class="text-[11px]">> by_province</span>
                </div>
                <div v-if="wanTerorMapError" class="rounded-lg border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-200">
                    > {{ wanTerorMapError }}
                </div>
                <div
                    v-else-if="wanTerorMapSvg"
                    class="relative w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30 p-2"
                >
                    <div
                        v-if="wanTerorHovered"
                        class="pointer-events-none absolute left-2 right-2 top-2 z-10 flex items-center justify-between rounded-lg border border-green-500/15 bg-black/50 px-3 py-2 text-xs text-green-200/90 backdrop-blur"
                    >
                        <span>> {{ wanTerorHovered.name }}</span>
                        <span class="text-[11px]">> kejadian: {{ fmt(wanTerorHovered.count) }}</span>
                    </div>
                    <div
                        ref="wanTerorMapRoot"
                        class="mx-auto max-w-[1210px] overflow-hidden rounded-lg [&_svg]:h-auto [&_svg]:w-full [&_svg]:rounded-lg"
                        v-html="wanTerorMapSvg"
                    />
                </div>
                <div v-else class="rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-300/60">
                    loading_map...
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
