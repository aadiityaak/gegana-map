<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';

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
        params.set('per_page', '50');
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
            <div class="rounded-xl border border-green-500/15 bg-black/20 p-3">
                <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                    <span>> leaflet_map: incidents</span>
                    <span v-if="loading" class="flex items-center gap-2">
                        <Spinner />
                        loading_feed...
                    </span>
                </div>
                <div ref="mapContainer" class="relative z-0 h-[380px] w-full overflow-hidden rounded-lg border border-green-500/15" />
            </div>

            <div
                v-for="item in items"
                :key="item.id"
                class="rounded-xl border border-green-500/15 bg-black/30 p-4"
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

            <div v-if="!items.length" class="rounded border border-green-500/15 bg-black/20 p-4 text-green-300/60">
                > tidak ada data untuk filter ini.
            </div>
        </div>
    </div>
</template>
