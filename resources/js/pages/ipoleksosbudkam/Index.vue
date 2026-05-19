<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, watchEffect } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';

type MonitoringItem = {
    id: number | string;
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
    detailId?: number;
}>();

const items = ref<MonitoringItem[]>([]);
const meta = ref<MonitoringResponse['meta'] | null>(null);
const loading = ref(false);
const errorMessage = ref<string | null>(null);

const parsePositiveInt = (value: string | null): number | null => {
    if (!value) return null;
    const n = Number.parseInt(value, 10);
    if (!Number.isFinite(n) || n <= 0) return null;
    return n;
};

const initialQuery = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const currentPage = ref<number>(parsePositiveInt(initialQuery?.get('page') ?? null) ?? 1);
const perPage = ref<number>(parsePositiveInt(initialQuery?.get('per_page') ?? null) ?? 25);

const mapContainer = ref<HTMLDivElement | null>(null);
let map: any | null = null;
let markerLayer: any | null = null;
let mapReady = false;

const detailMapContainer = ref<HTMLDivElement | null>(null);
let detailMap: any | null = null;
let detailMarker: any | null = null;

const detailLoading = ref(false);
const detailError = ref<string | null>(null);
const detailItem = ref<MonitoringItem | null>(null);

const isDetailPage = computed(() => typeof props.detailId === 'number' && Number.isFinite(props.detailId));
const isWidgetPage = computed(() => {
    if (props.category !== 'ekonomi') return false;
    return props.subcategory === 'ekonomi-kurs-mata-uang' || props.subcategory === 'ekonomi-pasar-saham';
});

const widgetRoot = ref<HTMLDivElement | null>(null);
const widgetError = ref<string | null>(null);

const title = computed(() => {
    if (isDetailPage.value) return 'IPOLEKSOSBUDKAM / detail';
    if (isWidgetPage.value) return 'IPOLEKSOSBUDKAM / ekonomi / widget';
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
        'ekonomi-kurs-mata-uang': 'Kurs Mata Uang',
        'ekonomi-pasar-saham': 'Pasar Saham',
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

const sanitizeHtml = (input: string | null): string | null => {
    if (!input) return null;
    const html = String(input).trim();
    if (!html) return null;

    if (typeof window === 'undefined') {
        return html;
    }

    const doc = new DOMParser().parseFromString(html, 'text/html');

    for (const el of Array.from(doc.querySelectorAll('script,style,iframe,object,embed,link,meta'))) {
        el.remove();
    }

    for (const node of Array.from(doc.body.querySelectorAll('*'))) {
        for (const attr of Array.from(node.attributes)) {
            const name = attr.name.toLowerCase();
            if (name.startsWith('on')) {
                node.removeAttribute(attr.name);
                continue;
            }
            if (node.tagName.toLowerCase() === 'a' && name === 'href') {
                continue;
            }
            node.removeAttribute(attr.name);
        }
    }

    for (const a of Array.from(doc.body.querySelectorAll('a[href]'))) {
        const href = a.getAttribute('href') ?? '';
        const safe =
            href.startsWith('/') ||
            href.startsWith('#') ||
            /^https?:\/\//i.test(href) ||
            /^mailto:/i.test(href) ||
            /^tel:/i.test(href);
        if (!safe) {
            a.removeAttribute('href');
        }
    }

    return doc.body.innerHTML.trim() || null;
};

const detailDescriptionHtml = computed(() => sanitizeHtml(detailItem.value?.description ?? null));

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

const detailHref = (id: number): string => {
    return `/ipoleksosbudkam/detail/${id}`;
};

const goToDetail = (id: number | string) => {
    const normalizedId = normalizeId(id);
    if (normalizedId === null) return;
    router.visit(detailHref(normalizedId));
};

const widgetTitle = computed(() => {
    if (props.subcategory === 'ekonomi-kurs-mata-uang') return 'KURS MATA UANG';
    if (props.subcategory === 'ekonomi-pasar-saham') return 'PASAR SAHAM';
    return 'WIDGET';
});

const renderTradingViewWidget = async () => {
    widgetError.value = null;
    if (typeof window === 'undefined') return;
    if (!isWidgetPage.value) return;
    if (!widgetRoot.value) return;

    widgetRoot.value.innerHTML = '';

    const appendEmbed = (options: {
        title: string;
        scriptSrc: string;
        config: Record<string, unknown>;
        minHeight: number;
    }) => {
        if (!widgetRoot.value) return;

        const block = document.createElement('div');
        block.style.cssText =
            'width: 100%; border: 1px solid rgba(34,197,94,0.15); background: rgba(0,0,0,0.20); border-radius: 12px; overflow: hidden;';

        const header = document.createElement('div');
        header.textContent = `> ${options.title}`;
        header.style.cssText =
            'padding: 10px 12px; border-bottom: 1px solid rgba(34,197,94,0.15); font-size: 12px; letter-spacing: 0.12em; color: rgba(134,239,172,0.70); font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;';
        block.appendChild(header);

        const container = document.createElement('div');
        container.className = 'tradingview-widget-container';
        container.style.width = '100%';
        container.style.minHeight = `${options.minHeight}px`;

        const widgetInner = document.createElement('div');
        widgetInner.className = 'tradingview-widget-container__widget';
        container.appendChild(widgetInner);

        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.async = true;
        script.src = options.scriptSrc;
        script.text = JSON.stringify(options.config);
        script.onerror = () => {
            widgetError.value = 'Gagal memuat widget TradingView.';
        };

        container.appendChild(script);
        block.appendChild(container);
        widgetRoot.value.appendChild(block);

        const spacer = document.createElement('div');
        spacer.style.height = '12px';
        widgetRoot.value.appendChild(spacer);
    };

    if (props.subcategory === 'ekonomi-kurs-mata-uang') {
        appendEmbed({
            title: 'Ticker Tape',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js',
            minHeight: 72,
            config: {
                colorTheme: 'dark',
                isTransparent: false,
                displayMode: 'adaptive',
                locale: 'id',
                showSymbolLogo: true,
                symbols: [
                    { proName: 'FX_IDC:USDIDR', title: 'USD/IDR' },
                    { proName: 'FX:EURUSD', title: 'EUR/USD' },
                    { proName: 'FX:GBPUSD', title: 'GBP/USD' },
                    { proName: 'FX:USDJPY', title: 'USD/JPY' },
                    { proName: 'FX:AUDUSD', title: 'AUD/USD' },
                ],
            },
        });

        appendEmbed({
            title: 'Forex Cross Rates',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-forex-cross-rates.js',
            minHeight: 560,
            config: {
                width: '100%',
                height: 560,
                currencies: ['IDR', 'USD', 'EUR', 'JPY', 'GBP', 'AUD', 'CNY', 'SGD'],
                isTransparent: false,
                colorTheme: 'dark',
                locale: 'id',
            },
        });

        appendEmbed({
            title: 'Market Quotes (Forex)',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-market-quotes.js',
            minHeight: 520,
            config: {
                width: '100%',
                height: 520,
                locale: 'id',
                colorTheme: 'dark',
                isTransparent: false,
                showSymbolLogo: true,
                symbolsGroups: [
                    {
                        name: 'Major',
                        originalName: 'Forex',
                        symbols: [
                            { name: 'FX_IDC:USDIDR', displayName: 'USD/IDR' },
                            { name: 'FX:EURUSD', displayName: 'EUR/USD' },
                            { name: 'FX:GBPUSD', displayName: 'GBP/USD' },
                            { name: 'FX:USDJPY', displayName: 'USD/JPY' },
                            { name: 'FX:AUDUSD', displayName: 'AUD/USD' },
                        ],
                    },
                ],
            },
        });

        appendEmbed({
            title: 'Symbol Overview',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-symbol-overview.js',
            minHeight: 520,
            config: {
                symbols: [
                    ['FX_IDC:USDIDR|1D'],
                    ['FX:EURUSD|1D'],
                    ['FX:USDJPY|1D'],
                ],
                chartOnly: false,
                width: '100%',
                height: 520,
                locale: 'id',
                colorTheme: 'dark',
                isTransparent: false,
                autosize: false,
                showVolume: true,
                showMA: true,
                hideDateRanges: false,
                hideMarketStatus: false,
                hideSymbolLogo: false,
                scalePosition: 'right',
                scaleMode: 'Normal',
                fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace',
                fontSize: '10',
                noTimeScale: false,
                valuesTracking: '1',
                changeMode: 'price-and-percent',
            },
        });

        appendEmbed({
            title: 'Technical Analysis (USD/IDR)',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-technical-analysis.js',
            minHeight: 520,
            config: {
                interval: '1D',
                width: '100%',
                height: 520,
                isTransparent: false,
                symbol: 'FX_IDC:USDIDR',
                showIntervalTabs: true,
                locale: 'id',
                colorTheme: 'dark',
            },
        });

        return;
    }

    if (props.subcategory === 'ekonomi-pasar-saham') {
        appendEmbed({
            title: 'Ticker Tape',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js',
            minHeight: 72,
            config: {
                colorTheme: 'dark',
                isTransparent: false,
                displayMode: 'adaptive',
                locale: 'id',
                showSymbolLogo: true,
                symbols: [
                    { proName: 'IDX:COMPOSITE', title: 'IHSG' },
                    { proName: 'FX_IDC:USDIDR', title: 'USD/IDR' },
                    { proName: 'FX:EURUSD', title: 'EUR/USD' },
                    { proName: 'NASDAQ:NDX', title: 'Nasdaq 100' },
                    { proName: 'SP:SPX', title: 'S&P 500' },
                ],
            },
        });

        appendEmbed({
            title: 'Market Overview',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-market-overview.js',
            minHeight: 560,
            config: {
                colorTheme: 'dark',
                dateRange: '12M',
                showChart: true,
                locale: 'id',
                width: '100%',
                height: 560,
                isTransparent: false,
                tabs: [
                    {
                        title: 'Index',
                        symbols: [
                            { s: 'IDX:COMPOSITE', d: 'Jakarta Composite' },
                            { s: 'SP:SPX', d: 'S&P 500' },
                            { s: 'NASDAQ:NDX', d: 'Nasdaq 100' },
                            { s: 'FOREXCOM:DJI', d: 'Dow Jones' },
                        ],
                        originalTitle: 'Indices',
                    },
                    {
                        title: 'FX',
                        symbols: [
                            { s: 'FX_IDC:USDIDR', d: 'USD/IDR' },
                            { s: 'FX:EURUSD', d: 'EUR/USD' },
                            { s: 'FX:GBPUSD', d: 'GBP/USD' },
                            { s: 'FX:USDJPY', d: 'USD/JPY' },
                        ],
                        originalTitle: 'Forex',
                    },
                ],
            },
        });

        appendEmbed({
            title: 'Market Quotes',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-market-quotes.js',
            minHeight: 520,
            config: {
                width: '100%',
                height: 520,
                locale: 'id',
                colorTheme: 'dark',
                isTransparent: false,
                showSymbolLogo: true,
                symbolsGroups: [
                    {
                        name: 'Index',
                        originalName: 'Indices',
                        symbols: [
                            { name: 'IDX:COMPOSITE', displayName: 'IHSG' },
                            { name: 'SP:SPX', displayName: 'S&P 500' },
                            { name: 'NASDAQ:NDX', displayName: 'Nasdaq 100' },
                            { name: 'FOREXCOM:DJI', displayName: 'Dow Jones' },
                        ],
                    },
                    {
                        name: 'FX',
                        originalName: 'Forex',
                        symbols: [
                            { name: 'FX_IDC:USDIDR', displayName: 'USD/IDR' },
                            { name: 'FX:EURUSD', displayName: 'EUR/USD' },
                            { name: 'FX:GBPUSD', displayName: 'GBP/USD' },
                            { name: 'FX:USDJPY', displayName: 'USD/JPY' },
                        ],
                    },
                ],
            },
        });

        appendEmbed({
            title: 'Technical Analysis (IHSG)',
            scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-technical-analysis.js',
            minHeight: 520,
            config: {
                interval: '1D',
                width: '100%',
                height: 520,
                isTransparent: false,
                symbol: 'IDX:COMPOSITE',
                showIntervalTabs: true,
                locale: 'id',
                colorTheme: 'dark',
            },
        });

        return;
    }

    appendEmbed({
        title: 'Market Overview',
        scriptSrc: 'https://s3.tradingview.com/external-embedding/embed-widget-market-overview.js',
        minHeight: 700,
        config: {
            colorTheme: 'dark',
            dateRange: '12M',
            showChart: true,
            locale: 'id',
            width: '100%',
            height: 700,
            isTransparent: false,
            tabs: [
                {
                    title: 'Index',
                    symbols: [
                        { s: 'IDX:COMPOSITE', d: 'Jakarta Composite' },
                        { s: 'SP:SPX', d: 'S&P 500' },
                        { s: 'NASDAQ:NDX', d: 'Nasdaq 100' },
                        { s: 'FOREXCOM:DJI', d: 'Dow Jones' },
                    ],
                    originalTitle: 'Indices',
                },
                {
                    title: 'FX',
                    symbols: [
                        { s: 'FX_IDC:USDIDR', d: 'USD/IDR' },
                        { s: 'FX:EURUSD', d: 'EUR/USD' },
                        { s: 'FX:GBPUSD', d: 'GBP/USD' },
                        { s: 'FX:USDJPY', d: 'USD/JPY' },
                    ],
                    originalTitle: 'Forex',
                },
            ],
        },
    });
};

const detailCoords = computed(() => {
    if (!detailItem.value) return null;
    const lat = parseCoordNumber(detailItem.value.latitude);
    const lng = parseCoordNumber(detailItem.value.longitude);
    if (lat === null || lng === null) return null;
    const [fixedLat, fixedLng] = normalizeIndonesiaCoords(lat, lng);
    if (!Number.isFinite(fixedLat) || !Number.isFinite(fixedLng)) return null;
    return { lat: fixedLat, lng: fixedLng };
});

const loadDetail = async (id: number | string) => {
    const normalizedId = normalizeId(id);
    if (normalizedId === null) {
        detailLoading.value = false;
        detailError.value = 'Invalid id.';
        detailItem.value = null;
        return;
    }

    detailLoading.value = true;
    detailError.value = null;
    detailItem.value = null;

    try {
        const res = await fetch(`/api/ipoleksosbudkam/monitoring-data/${normalizedId}`, {
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

const ensureDetailMap = async () => {
    if (typeof window === 'undefined') return;
    if (!detailMapContainer.value) return;
    if (detailMap) return;

    const L = await getLeaflet();

    detailMap = L.map(detailMapContainer.value, { zoomControl: true }).setView([-2.5489, 118.0149], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
    }).addTo(detailMap);

    setTimeout(() => {
        detailMap?.invalidateSize?.();
    }, 0);
};

const updateDetailMap = async () => {
    if (!detailMap) return;

    const coords = detailCoords.value;
    if (!coords) {
        if (detailMarker) {
            detailMarker.remove();
            detailMarker = null;
        }
        detailMap.setView([-2.5489, 118.0149], 5);
        return;
    }

    const L = await getLeaflet();
    const color = markerColor(detailItem.value?.severity_level ?? 'low');

    if (detailMarker) {
        detailMarker.setLatLng([coords.lat, coords.lng]);
    } else {
        detailMarker = L.circleMarker([coords.lat, coords.lng], {
            radius: 9,
            color: color,
            weight: 1,
            fillColor: color,
            fillOpacity: 0.7,
        }).addTo(detailMap);
    }

    detailMap.setView([coords.lat, coords.lng], 8);
};

const updateMapMarkers = async () => {
    if (!map || !markerLayer) return;

    markerLayer.clearLayers();

    const L = await getLeaflet();
    const points: Array<[number, number]> = [];

    for (const item of items.value) {
        const lat = parseCoordNumber(item.latitude);
        const lng = parseCoordNumber(item.longitude);
        const hasValidCoords =
            typeof lat === 'number' &&
            typeof lng === 'number' &&
            Number.isFinite(lat) &&
            Number.isFinite(lng);
        if (lat === null || lng === null) continue;
        if (!hasValidCoords) continue;
        const [fixedLat, fixedLng] = normalizeIndonesiaCoords(lat, lng);
        points.push([fixedLat, fixedLng]);


        const color = markerColor(item.severity_level);
        const marker = L.circleMarker([fixedLat, fixedLng], {
            radius: 7,
            color: color,
            weight: 1,
            fillColor: color,
            fillOpacity: 0.65,
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

        const tagsEl = document.createElement('div');
        tagsEl.style.cssText = 'display:flex; gap:8px; font-size: 12px;';
        const sevEl = document.createElement('span');
        sevEl.textContent = severityLabel(item.severity_level);
        sevEl.style.cssText =
            'border: 1px solid rgba(34,197,94,0.25); padding: 2px 8px; border-radius: 999px; background: rgba(0,0,0,0.35);';
        tagsEl.appendChild(sevEl);
        const statEl = document.createElement('span');
        statEl.textContent = statusLabel(item.status);
        statEl.style.cssText =
            'border: 1px solid rgba(34,197,94,0.25); padding: 2px 8px; border-radius: 999px; background: rgba(34,197,94,0.10);';
        tagsEl.appendChild(statEl);
        popupEl.appendChild(tagsEl);

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

const load = async () => {
    loading.value = true;
    errorMessage.value = null;

    try {
        const params = new URLSearchParams();
        params.set('page', String(currentPage.value));
        params.set('per_page', String(perPage.value));
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

        if (meta.value) {
            currentPage.value = meta.value.current_page ?? currentPage.value;
            perPage.value = meta.value.per_page ?? perPage.value;
        }
    } catch (e) {
        errorMessage.value = 'Gagal memuat data.';
        items.value = [];
        meta.value = null;
    } finally {
        loading.value = false;
    }
};

const syncListQuery = () => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(currentPage.value));
    url.searchParams.set('per_page', String(perPage.value));
    window.history.replaceState({}, '', url.toString());
};

const clampPage = (value: number): number => {
    const last = meta.value?.last_page ?? value;
    return Math.min(Math.max(1, value), Math.max(1, last));
};

const setPage = (value: number) => {
    const clamped = clampPage(value);
    if (clamped === currentPage.value) return;
    currentPage.value = clamped;
};

const pageButtons = computed<number[]>(() => {
    const last = meta.value?.last_page ?? 1;
    const current = meta.value?.current_page ?? currentPage.value;
    if (last <= 1) return [1];

    const windowSize = 5;
    const half = Math.floor(windowSize / 2);

    let start = Math.max(1, current - half);
    let end = Math.min(last, start + windowSize - 1);
    start = Math.max(1, end - windowSize + 1);

    const pages: number[] = [];
    for (let p = start; p <= end; p += 1) pages.push(p);
    return pages;
});

watch(
    [() => props.category, () => props.subcategory, currentPage, perPage],
    ([newCategory, newSubcategory, newPage], oldValue) => {
        if (isDetailPage.value || isWidgetPage.value) return;

        const oldCategory = Array.isArray(oldValue) ? oldValue[0] : undefined;
        const oldSubcategory = Array.isArray(oldValue) ? oldValue[1] : undefined;

        if ((newCategory !== oldCategory || newSubcategory !== oldSubcategory) && newPage !== 1) {
            currentPage.value = 1;
            return;
        }

        syncListQuery();
        void load();
    },
    { immediate: true },
);

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

const ensureListMap = async () => {
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

    void updateMapMarkers();

    setTimeout(() => {
        map?.invalidateSize?.();
    }, 0);
};

onMounted(async () => {
    if (typeof window === 'undefined') return;

    if (isDetailPage.value && props.detailId != null) {
        await loadDetail(props.detailId);
        await nextTick();
        await ensureDetailMap();
        await updateDetailMap();
        return;
    }

    if (isWidgetPage.value) {
        await nextTick();
        await renderTradingViewWidget();
        return;
    }

    await ensureListMap();
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
        markerLayer = null;
        mapReady = false;
    }
    if (detailMap) {
        detailMap.remove();
        detailMap = null;
        detailMarker = null;
    }
});

watch(
    () => items.value,
    () => {
        if (isDetailPage.value) return;
        if (isWidgetPage.value) return;
        if (!mapReady) return;
        void updateMapMarkers();
    },
    { deep: true },
);

watch(
    () => detailItem.value,
    async () => {
        if (!isDetailPage.value) return;
        await nextTick();
        await ensureDetailMap();
        await updateDetailMap();
    },
    { deep: true },
);

watch(
    () => props.detailId,
    async () => {
        if (!isDetailPage.value || props.detailId == null) return;
        await loadDetail(props.detailId);
    },
);

watch(
    () => props.subcategory,
    async () => {
        if (!isWidgetPage.value) return;
        await nextTick();
        await renderTradingViewWidget();
    },
);

watchEffect(() => {
    if (isDetailPage.value || isWidgetPage.value) return;
    void ensureListMap();
});
</script>

<template>
    <Head :title="title" />

    <div class="p-6 font-mono">
        <div v-if="isDetailPage" class="space-y-4">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg font-semibold tracking-widest text-green-200">
                        > DETAIL INSIDEN
                    </h1>
                    <Badge class="border border-green-500/25 bg-black/30 text-green-200">
                        id: {{ props.detailId }}
                    </Badge>
                </div>
                <a
                    href="/ipoleksosbudkam"
                    class="inline-flex items-center justify-center rounded-md border border-green-500/15 bg-black/30 px-3 py-2 text-xs tracking-widest text-green-200 hover:border-green-400/25"
                >
                    > BACK
                </a>
            </div>

            <div v-if="detailLoading" class="flex items-center gap-2 text-xs text-green-300/60">
                <Spinner />
                loading_detail...
            </div>

            <div v-else-if="detailError" class="rounded border border-red-500/25 bg-red-500/10 p-4 text-red-200">
                > {{ detailError }}
            </div>

            <div v-else-if="detailItem" class="space-y-4">
                <div class="rounded-xl border border-green-500/15 bg-black/30 p-4">
                    <div class="text-sm font-semibold text-green-100">
                        > {{ detailItem.title }}
                    </div>
                    <div class="mt-2 text-xs text-green-300/60">
                        {{ formatDateTime(detailItem.incident_date) }} · {{ locationLabel(detailItem) }}
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
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
                </div>

                <div class="rounded-xl border border-green-500/15 bg-black/20 p-3">
                    <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                        <span>> MAP LOCATION</span>
                        <span v-if="detailCoords" class="text-[11px]">> {{ detailCoords.lat.toFixed(5) }}, {{ detailCoords.lng.toFixed(5) }}</span>
                    </div>
                    <div
                        v-if="detailCoords"
                        ref="detailMapContainer"
                        class="relative z-0 h-[360px] w-full overflow-hidden rounded-lg border border-green-500/15"
                    />
                    <div v-else class="rounded-lg border border-green-500/15 bg-black/30 p-4 text-xs text-green-300/60">
                        > koordinat tidak tersedia.
                    </div>
                </div>

                <div
                    v-if="detailDescriptionHtml"
                    class="rounded-xl border border-green-500/15 bg-black/20 p-4 text-sm text-green-200/85"
                    v-html="detailDescriptionHtml"
                />

                <div class="grid gap-2 rounded-xl border border-green-500/15 bg-black/20 p-4 text-xs text-green-300/70">
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
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <a
                            v-for="img in detailItem.gallery"
                            :key="img.path"
                            class="group block overflow-hidden rounded-md border border-green-500/15 bg-black/20 hover:border-green-400/25"
                            :href="img.url"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <div
                                class="relative w-full overflow-hidden bg-black/35 [aspect-ratio:4/3]"
                            >
                                <img
                                    :src="img.url"
                                    :alt="img.path"
                                    class="absolute inset-0 h-full w-full object-contain p-2 opacity-95 transition group-hover:opacity-100"
                                    loading="lazy"
                                />
                            </div>
                            <div class="truncate border-t border-green-500/10 px-3 py-2 text-[11px] text-green-300/70">
                                > {{ img.path }}
                            </div>
                        </a>
                    </div>
                </div>

                <div v-if="detailItem.video_url" class="space-y-2">
                    <div class="text-xs tracking-widest text-green-300/60">VIDEO</div>
                    <video :src="detailItem.video_url" controls class="w-full rounded-md border border-green-500/15" />
                </div>
            </div>

            <div v-else class="text-xs text-green-300/60">> detail tidak ditemukan.</div>
        </div>

        <div v-else-if="isWidgetPage" class="space-y-4">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg font-semibold tracking-widest text-green-200">
                        > {{ widgetTitle }}
                    </h1>
                    <Badge class="border border-green-500/25 bg-black/30 text-green-200">
                        ekonomi
                    </Badge>
                </div>
                <a
                    href="/ipoleksosbudkam/ekonomi"
                    class="inline-flex items-center justify-center rounded-md border border-green-500/15 bg-black/30 px-3 py-2 text-xs tracking-widest text-green-200 hover:border-green-400/25"
                >
                    > BACK
                </a>
            </div>

            <div v-if="widgetError" class="rounded border border-red-500/25 bg-red-500/10 p-4 text-red-200">
                > {{ widgetError }}
            </div>

            <div class="rounded-xl border border-green-500/15 bg-black/20 p-3">
                <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                    <span>> TRADINGVIEW_WIDGET</span>
                    <span class="text-[11px]">> external_embed</span>
                </div>
                <div
                    ref="widgetRoot"
                    class="w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30"
                />
            </div>
        </div>

        <template v-else>
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
                            @click="goToDetail(item.id)"
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

                                <div
                                    v-if="item.description"
                                    class="text-sm text-green-200/80 overflow-hidden [display:-webkit-box] [-webkit-line-clamp:4] [-webkit-box-orient:vertical]"
                                    v-html="sanitizeHtml(item.description)"
                                />

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

                        <div
                            v-if="meta && meta.last_page > 1"
                            class="rounded-xl border border-green-500/15 bg-black/20 p-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-green-300/60">
                                <div>
                                    > halaman {{ meta.current_page }} / {{ meta.last_page }} · tampil: {{ items.length }} · total: {{ meta.total }}
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-md border border-green-500/15 bg-black/30 px-3 py-2 text-[11px] tracking-widest text-green-200 hover:border-green-400/25 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="loading || meta.current_page <= 1"
                                        @click="setPage(meta.current_page - 1)"
                                    >
                                        > PREV
                                    </button>

                                    <button
                                        v-for="p in pageButtons"
                                        :key="p"
                                        type="button"
                                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-3 text-[11px] tracking-widest"
                                        :class="
                                            p === meta.current_page
                                                ? 'border-green-400/35 bg-green-500/15 text-green-100'
                                                : 'border-green-500/15 bg-black/30 text-green-200 hover:border-green-400/25'
                                        "
                                        :disabled="loading || p === meta.current_page"
                                        @click="setPage(p)"
                                    >
                                        {{ p }}
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-md border border-green-500/15 bg-black/30 px-3 py-2 text-[11px] tracking-widest text-green-200 hover:border-green-400/25 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="loading || meta.current_page >= meta.last_page"
                                        @click="setPage(meta.current_page + 1)"
                                    >
                                        > NEXT
                                    </button>
                                </div>
                            </div>
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
        </template>
    </div>
</template>
