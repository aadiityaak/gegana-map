<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';

type MonitoringItem = {
    id: number;
    title: string;
    description: string | null;
    incident_date: string | null;
    severity_level: 'low' | 'medium' | 'high' | 'critical' | string;
    status: 'active' | 'monitoring' | 'resolved' | string;
    provinsi?: { id: number; nama: string } | null;
    kabupaten_kota?: { id: number; nama: string } | null;
    kecamatan?: { id: number; nama: string } | null;
    category?: { id: number; name: string; slug: string } | null;
    sub_category?: { id: number; name: string; slug: string } | null;
    latitude?: number | string | null;
    longitude?: number | string | null;
    jumlah_terdampak?: number | null;
    source?: string | null;
    gallery?: Array<{ path: string; url: string }>;
    video_url?: string | null;
    sumber_berita?: string | null;
    data_source?: string | null;
};

type MonitoringResponse = {
    data: MonitoringItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        start_date: string;
        end_date: string;
    };
};

const props = defineProps<{
    category?: string;
    subcategory?: string;
}>();

const items = ref<MonitoringItem[]>([]);
const meta = ref<MonitoringResponse['meta'] | null>(null);
const loading = ref(false);
const errorMessage = ref<string | null>(null);

const mapContainer = ref<HTMLDivElement | null>(null);
let map: any | null = null;
let markerLayer: any | null = null;
let mapReady = false;

const detailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref<string | null>(null);
const detailItem = ref<MonitoringItem | null>(null);

const title = computed(() => {
    const parts = ['IPOLEKSOSBUDKAM'];
    if (props.category) parts.push(props.category);
    if (props.subcategory) parts.push(props.subcategory);
    return parts.join(' / ');
});

const categoryLabel = computed(() => {
    const labels: Record<string, string> = {
        ideologi: 'Ideologi',
        politik: 'Politik',
        ekonomi: 'Ekonomi',
        'sosial-budaya': 'Sosial Budaya',
        keamanan: 'Keamanan',
    };
    return props.category ? (labels[props.category] ?? props.category) : 'Semua Kategori';
});

const subcategoryLabel = computed(() => {
    const labels: Record<string, string> = {
        'ideologi-ideologi-kanan': 'Ideologi Kanan',
        'ideologi-ideologi-kiri': 'Ideologi Kiri',
        'ideologi-isu-menonjol': 'Isu Menonjol',
        'politik-dalam-negeri': 'Dalam Negeri',
        'politik-luar-negeri': 'Luar Negeri',
        'politik-isu-menonjol': 'Isu Menonjol',
        'ekonomi-export-import': 'Export Import',
        'ekonomi-harga-sembako': 'Harga Sembako',
        'ekonomi-index-pendapatan-masyarakat': 'Index Pendapatan masyarakat',
        'ekonomi-kesenjangan-sosial': 'Kesenjangan Sosial',
        'ekonomi-ekonomi-asing': 'Ekonomi Asing',
        'ekonomi-pro-kontra-proyek-strategis-nasional': 'Pro Kontra Proyek Strategis',
        'ekonomi-korupsi': 'Korupsi',
        'ekonomi-isu-menonjol': 'Isu Menonjol',
        'sosial-budaya-ormas': 'Ormas',
        'sosial-budaya-bencana-alam': 'Bencana Alam',
        'sosial-budaya-unjuk-rasa': 'Unjuk rasa',
        'sosial-budaya-konflik-sosial': 'Konflik sosial',
        'sosial-budaya-phk': 'PHK',
        'sosial-budaya-sara': 'SARA',
        'sosial-budaya-isu-menonjol': 'Isu Menonjol',
        'keamanan-teror': 'Teror',
        'keamanan-keamanan-negara': 'Keamanan Negara',
        'keamanan-isu-menonjol': 'Isu Menonjol',
    };
    return props.subcategory ? (labels[props.subcategory] ?? props.subcategory) : null;
});

const severityLabel = (value: string) =>
    ({
        low: 'Rendah',
        medium: 'Sedang',
        high: 'Tinggi',
        critical: 'Kritis',
    })[value] ?? value;

const statusLabel = (value: string) =>
    ({
        active: 'Aktif',
        monitoring: 'Monitoring',
        resolved: 'Selesai',
    })[value] ?? value;

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

const openDetail = async (id: number) => {
    detailOpen.value = true;
    detailLoading.value = true;
    detailError.value = null;
    detailItem.value = null;

    try {
        const res = await fetch(`/api/ipoleksosbudkam/monitoring-data/${id}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const json = (await res.json().catch(() => null)) as { data?: MonitoringItem; message?: string } | null;

        if (!res.ok) {
            detailError.value = json?.message ?? 'Gagal memuat detail.';
            return;
        }

        detailItem.value = json?.data ?? null;
        if (!detailItem.value) {
            detailError.value = 'Detail tidak ditemukan.';
        }
    } catch (e) {
        detailError.value = 'Gagal memuat detail.';
    } finally {
        detailLoading.value = false;
    }
};

const updateMapMarkers = async () => {
    if (!map || !markerLayer) return;

    markerLayer.clearLayers();

    const L = await getLeaflet();
    const points: Array<[number, number]> = [];

    for (const item of items.value) {
        const lat =
            typeof item.latitude === 'number'
                ? item.latitude
                : typeof item.latitude === 'string'
                  ? Number.parseFloat(item.latitude)
                  : null;
        const lng =
            typeof item.longitude === 'number'
                ? item.longitude
                : typeof item.longitude === 'string'
                  ? Number.parseFloat(item.longitude)
                  : null;
        const hasValidCoords =
            typeof lat === 'number' &&
            typeof lng === 'number' &&
            Number.isFinite(lat) &&
            Number.isFinite(lng);
        if (lat === null || lng === null) continue;
        if (!hasValidCoords) continue;
        points.push([lat, lng]);


        const color = markerColor(item.severity_level);
        const marker = L.circleMarker([lat, lng], {
            radius: 7,
            color: color,
            weight: 1,
            fillColor: color,
            fillOpacity: 0.65,
        });

        marker.bindPopup(
            `
                <div style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; color: rgba(226,255,232,0.92);">
                    <div style="font-weight: 700; margin-bottom: 6px;">${String(item.title).replaceAll('<', '&lt;')}</div>
                    <div style="font-size: 12px; opacity: 0.75; margin-bottom: 8px;">
                        ${formatDateTime(item.incident_date)} · ${locationLabel(item)}
                    </div>
                    <div style="font-size: 11px; opacity: 0.65; margin-bottom: 8px;">klik marker untuk detail</div>
                    <div style="display:flex; gap:8px; font-size: 12px;">
                        <span style="border: 1px solid rgba(34,197,94,0.25); padding: 2px 8px; border-radius: 999px; background: rgba(0,0,0,0.35);">${severityLabel(item.severity_level)}</span>
                        <span style="border: 1px solid rgba(34,197,94,0.25); padding: 2px 8px; border-radius: 999px; background: rgba(34,197,94,0.10);">${statusLabel(item.status)}</span>
                    </div>
                </div>
            `,
            {
                closeButton: false,
            },
        );
        marker.on('click', () => {
            void openDetail(item.id);
        });

        markerLayer.addLayer(marker);
    }

    if (points.length) {
        const bounds = L.latLngBounds(points);
        map.fitBounds(bounds, { padding: [24, 24], maxZoom: 10 });
    }
};

const load = async () => {
    loading.value = true;
    errorMessage.value = null;

    try {
        const params = new URLSearchParams();
        params.set('per_page', '200');
        params.set('sort_by', 'incident_date');
        params.set('sort_dir', 'desc');
        if (props.category) params.set('category', props.category);
        if (props.subcategory) params.set('subcategory', props.subcategory);

        const res = await fetch(`/api/ipoleksosbudkam/monitoring-data?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const json = (await res.json().catch(() => null)) as MonitoringResponse | { message?: string } | null;

        if (!res.ok) {
            errorMessage.value = (json as any)?.message ?? 'Gagal memuat data.';
            items.value = [];
            meta.value = null;
            return;
        }

        items.value = (json as MonitoringResponse).data ?? [];
        meta.value = (json as MonitoringResponse).meta ?? null;
    } catch (e) {
        errorMessage.value = 'Gagal memuat data.';
        items.value = [];
        meta.value = null;
    } finally {
        loading.value = false;
    }
};

watchEffect(() => {
    void load();
});

const totalData = computed(() => meta.value?.total ?? items.value.length);

const totalCategory = computed(() => {
    const set = new Set<number | string>();
    for (const item of items.value) {
        if (item.category?.id != null) {
            set.add(item.category.id);
        } else if (item.category?.name) {
            set.add(item.category.name);
        }
    }
    return set.size;
});

const totalTerdampak = computed(() => {
    return items.value.reduce((sum, item) => {
        const v = typeof item.jumlah_terdampak === 'number' ? item.jumlah_terdampak : 0;
        return sum + v;
    }, 0);
});

type RankedItem = { label: string; count: number; terdampak: number };

const topWilayahTerdampak = computed<RankedItem[]>(() => {
    const map = new Map<string, RankedItem>();

    for (const item of items.value) {
        const label = item.provinsi?.nama ?? null;
        if (!label) continue;

        const current = map.get(label) ?? { label, count: 0, terdampak: 0 };
        current.count += 1;
        current.terdampak += typeof item.jumlah_terdampak === 'number' ? item.jumlah_terdampak : 0;
        map.set(label, current);
    }

    return Array.from(map.values())
        .sort((a, b) => b.terdampak - a.terdampak || b.count - a.count || a.label.localeCompare(b.label))
        .slice(0, 7);
});

const topIsuMenonjol = computed<RankedItem[]>(() => {
    const map = new Map<string, RankedItem>();

    for (const item of items.value) {
        const isIsuMenonjol =
            (item.sub_category?.slug?.includes('isu-menonjol') ?? false) ||
            (item.sub_category?.name?.toLowerCase().includes('isu menonjol') ?? false);

        if (!isIsuMenonjol) continue;

        const label = item.title;
        const current = map.get(label) ?? { label, count: 0, terdampak: 0 };
        current.count += 1;
        current.terdampak += typeof item.jumlah_terdampak === 'number' ? item.jumlah_terdampak : 0;
        map.set(label, current);
    }

    return Array.from(map.values())
        .sort((a, b) => b.count - a.count || b.terdampak - a.terdampak || a.label.localeCompare(b.label))
        .slice(0, 7);
});

type RiskRow = { key: string; label: string; count: number; percent: number };

const riskSummary = computed<RiskRow[]>(() => {
    const counts: Record<string, number> = { low: 0, medium: 0, high: 0, critical: 0 };
    for (const item of items.value) {
        if (typeof item.severity_level === 'string' && item.severity_level in counts) {
            counts[item.severity_level] += 1;
        }
    }

    const total = Object.values(counts).reduce((a, b) => a + b, 0) || 1;

    return [
        { key: 'critical', label: 'Kritis', count: counts.critical, percent: (counts.critical / total) * 100 },
        { key: 'high', label: 'Tinggi', count: counts.high, percent: (counts.high / total) * 100 },
        { key: 'medium', label: 'Sedang', count: counts.medium, percent: (counts.medium / total) * 100 },
        { key: 'low', label: 'Rendah', count: counts.low, percent: (counts.low / total) * 100 },
    ];
});

const formatNumber = (value: number) => new Intl.NumberFormat('id-ID').format(value);

onMounted(async () => {
    if (typeof window === 'undefined') return;

    await nextTick();
    if (!mapContainer.value || map) return;

    const L = await getLeaflet();

    map = L.map(mapContainer.value, {
        zoomControl: true,
    }).setView([-2.5489, 118.0149], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
    }).addTo(map);

    markerLayer = L.featureGroup().addTo(map);
    mapReady = true;

    void updateMapMarkers();

    setTimeout(() => {
        map?.invalidateSize?.();
    }, 0);
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
    () => items.value,
    () => {
        if (!mapReady) return;
        void updateMapMarkers();
    },
    { deep: true },
);
</script>

<template>
    <Head :title="title" />

    <div class="p-6 font-mono">
        <div class="mb-6 flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > IPOLEKSOSBUDKAM
                </h1>
                <Badge class="border border-green-500/25 bg-green-500/10 text-green-200">
                    {{ categoryLabel }}
                </Badge>
                <Badge
                    v-if="subcategoryLabel"
                    class="border border-green-500/25 bg-black/30 text-green-200"
                >
                    {{ subcategoryLabel }}
                </Badge>
            </div>
            <div class="text-xs tracking-wide text-green-300/60">
                > data_source: crime-map / endpoint_proxy
                <span v-if="meta">
                    · total: {{ meta.total }}
                    · range: {{ meta.start_date }} → {{ meta.end_date }}
                </span>
            </div>
        </div>

        <div v-if="errorMessage" class="rounded border border-red-500/25 bg-red-500/10 p-4 text-red-200">
            > {{ errorMessage }}
        </div>

        <div v-else class="space-y-3">
            <div class="grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-green-500/15 bg-black/30 p-4">
                    <div class="text-xs tracking-widest text-green-300/60">TOTAL DATA</div>
                    <div class="mt-2 text-2xl font-semibold tracking-wide text-green-100">
                        {{ formatNumber(totalData) }}
                    </div>
                    <div class="mt-1 text-xs text-green-300/60">> record_count</div>
                </div>
                <div class="rounded-xl border border-green-500/15 bg-black/30 p-4">
                    <div class="text-xs tracking-widest text-green-300/60">TOTAL KATEGORI</div>
                    <div class="mt-2 text-2xl font-semibold tracking-wide text-green-100">
                        {{ formatNumber(totalCategory) }}
                    </div>
                    <div class="mt-1 text-xs text-green-300/60">> distinct_category</div>
                </div>
                <div class="rounded-xl border border-green-500/15 bg-black/30 p-4">
                    <div class="text-xs tracking-widest text-green-300/60">TOTAL TERDAMPAK</div>
                    <div class="mt-2 text-2xl font-semibold tracking-wide text-green-100">
                        {{ formatNumber(totalTerdampak) }}
                    </div>
                    <div class="mt-1 text-xs text-green-300/60">> sum_affected (loaded)</div>
                </div>
            </div>

            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-3">
                    <div class="rounded-xl border border-green-500/15 bg-black/20 p-3">
                        <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                            <span>> leaflet_map: incidents</span>
                            <span v-if="loading" class="flex items-center gap-2">
                                <Spinner />
                                loading_feed...
                            </span>
                        </div>
                        <div
                            ref="mapContainer"
                            class="relative z-0 h-[420px] w-full overflow-hidden rounded-lg border border-green-500/15"
                        />
                    </div>

                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="cursor-pointer rounded-xl border border-green-500/15 bg-black/30 p-4 transition hover:border-green-400/25 hover:bg-black/35"
                        @click="openDetail(item.id)"
                    >
                        <div class="flex flex-col gap-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-green-100">
                                        > {{ item.title }}
                                    </div>
                                    <div class="mt-1 text-xs text-green-300/60">
                                        {{ formatDateTime(item.incident_date) }} · {{ locationLabel(item) }}
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <Badge class="border border-green-500/25 bg-black/35 text-green-200">
                                        {{ severityLabel(item.severity_level) }}
                                    </Badge>
                                    <Badge class="border border-green-500/25 bg-green-500/10 text-green-200">
                                        {{ statusLabel(item.status) }}
                                    </Badge>
                                </div>
                            </div>

                            <div v-if="item.description" class="text-sm text-green-200/80">
                                {{ item.description }}
                            </div>

                            <div class="flex flex-wrap gap-3 text-xs text-green-300/60">
                                <div v-if="item.category?.name">
                                    > kategori: {{ item.category.name }}
                                </div>
                                <div v-if="item.sub_category?.name">
                                    > sub: {{ item.sub_category.name }}
                                </div>
                                <div v-if="item.source">
                                    > source: {{ item.source }}
                                </div>
                                <div v-if="typeof item.jumlah_terdampak === 'number'">
                                    > terdampak: {{ item.jumlah_terdampak }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="!items.length"
                        class="rounded border border-green-500/15 bg-black/20 p-4 text-green-300/60"
                    >
                        > tidak ada data untuk filter ini.
                    </div>
                </div>

                <aside class="space-y-3">
                    <div class="rounded-xl border border-green-500/15 bg-black/25 p-4">
                        <div class="mb-3 text-xs tracking-widest text-green-300/60">
                            TOP WILAYAH TERDAMPAK
                        </div>
                        <div v-if="topWilayahTerdampak.length" class="space-y-2">
                            <div
                                v-for="row in topWilayahTerdampak"
                                :key="row.label"
                                class="flex items-center justify-between gap-3 rounded-md border border-green-500/10 bg-black/20 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-medium text-green-200">
                                        {{ row.label }}
                                    </div>
                                    <div class="mt-1 text-[11px] text-green-300/60">
                                        > data: {{ row.count }}
                                    </div>
                                </div>
                                <Badge class="border border-green-500/25 bg-green-500/10 text-green-200">
                                    {{ formatNumber(row.terdampak) }}
                                </Badge>
                            </div>
                        </div>
                        <div v-else class="text-xs text-green-300/60">> no_rank_data</div>
                    </div>

                    <div class="rounded-xl border border-green-500/15 bg-black/25 p-4">
                        <div class="mb-3 text-xs tracking-widest text-green-300/60">
                            TOP ISU MENONJOL
                        </div>
                        <div v-if="topIsuMenonjol.length" class="space-y-2">
                            <div
                                v-for="row in topIsuMenonjol"
                                :key="row.label"
                                class="rounded-md border border-green-500/10 bg-black/20 px-3 py-2"
                            >
                                <div class="truncate text-xs font-medium text-green-200">
                                    > {{ row.label }}
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-green-300/60">
                                    <span>> freq: {{ row.count }}</span>
                                    <span>> terdampak: {{ formatNumber(row.terdampak) }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-xs text-green-300/60">> no_issues_detected</div>
                    </div>

                    <div class="rounded-xl border border-green-500/15 bg-black/25 p-4">
                        <div class="mb-3 text-xs tracking-widest text-green-300/60">
                            TINGKAT RESIKO
                        </div>
                        <div class="space-y-2">
                            <div v-for="row in riskSummary" :key="row.key" class="space-y-1">
                                <div class="flex items-center justify-between text-xs text-green-200/80">
                                    <span>> {{ row.label }}</span>
                                    <span>{{ row.count }}</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded bg-black/40">
                                    <div
                                        class="h-full rounded bg-green-500/40"
                                        :style="{ width: `${Math.max(0, Math.min(100, row.percent))}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <Sheet :open="detailOpen" @update:open="detailOpen = $event">
            <SheetContent
                side="right"
                class="border border-green-500/15 bg-[#05070A] text-green-200 sm:max-w-md"
            >
                <SheetHeader class="border-b border-green-500/15">
                    <SheetTitle class="font-mono text-sm tracking-widest text-green-200">
                        > DETAIL INSIDEN
                    </SheetTitle>
                    <div v-if="detailItem" class="mt-2 space-y-1 font-mono">
                        <div class="text-sm font-semibold text-green-100">
                            > {{ detailItem.title }}
                        </div>
                        <div class="text-xs text-green-300/60">
                            {{ formatDateTime(detailItem.incident_date) }} · {{ locationLabel(detailItem) }}
                        </div>
                    </div>
                </SheetHeader>

                <div class="flex-1 space-y-4 overflow-auto p-4 font-mono">
                    <div v-if="detailLoading" class="flex items-center gap-2 text-xs text-green-300/60">
                        <Spinner />
                        loading_detail...
                    </div>

                    <div v-else-if="detailError" class="rounded border border-red-500/25 bg-red-500/10 p-3 text-red-200">
                        > {{ detailError }}
                    </div>

                    <template v-else-if="detailItem">
                        <div class="flex flex-wrap gap-2">
                            <Badge class="border border-green-500/25 bg-black/35 text-green-200">
                                {{ severityLabel(detailItem.severity_level) }}
                            </Badge>
                            <Badge class="border border-green-500/25 bg-green-500/10 text-green-200">
                                {{ statusLabel(detailItem.status) }}
                            </Badge>
                            <Badge v-if="detailItem.category?.name" class="border border-green-500/25 bg-black/30 text-green-200">
                                {{ detailItem.category.name }}
                            </Badge>
                            <Badge v-if="detailItem.sub_category?.name" class="border border-green-500/25 bg-black/30 text-green-200">
                                {{ detailItem.sub_category.name }}
                            </Badge>
                        </div>

                        <div v-if="detailItem.description" class="rounded-lg border border-green-500/15 bg-black/20 p-3 text-sm text-green-200/85">
                            {{ detailItem.description }}
                        </div>

                        <div class="grid gap-2 rounded-lg border border-green-500/15 bg-black/20 p-3 text-xs text-green-300/70">
                            <div v-if="detailItem.source">> source: {{ detailItem.source }}</div>
                            <div v-if="detailItem.data_source">> data_source: {{ detailItem.data_source }}</div>
                            <div v-if="detailItem.sumber_berita">> sumber_berita: {{ detailItem.sumber_berita }}</div>
                            <div v-if="typeof detailItem.jumlah_terdampak === 'number'">> terdampak: {{ formatNumber(detailItem.jumlah_terdampak) }}</div>
                            <div v-if="detailItem.latitude != null && detailItem.longitude != null">
                                > koordinat: {{ detailItem.latitude }}, {{ detailItem.longitude }}
                            </div>
                        </div>

                        <div v-if="detailItem.gallery?.length" class="space-y-2">
                            <div class="text-xs tracking-widest text-green-300/60">GALLERY</div>
                            <div class="grid grid-cols-2 gap-2">
                                <a
                                    v-for="img in detailItem.gallery"
                                    :key="img.path"
                                    class="group block overflow-hidden rounded-md border border-green-500/15 bg-black/20"
                                    :href="img.url"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <img
                                        :src="img.url"
                                        :alt="img.path"
                                        class="h-28 w-full object-cover opacity-90 transition group-hover:opacity-100"
                                        loading="lazy"
                                    />
                                </a>
                            </div>
                        </div>

                        <div v-if="detailItem.video_url" class="space-y-2">
                            <div class="text-xs tracking-widest text-green-300/60">VIDEO</div>
                            <video :src="detailItem.video_url" controls class="w-full rounded-md border border-green-500/15" />
                        </div>
                    </template>

                    <div v-else class="text-xs text-green-300/60">> pilih data untuk melihat detail.</div>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
