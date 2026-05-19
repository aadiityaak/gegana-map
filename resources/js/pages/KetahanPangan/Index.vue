<template>
    <Head title="Ketahanan Pangan" />


        <div class="flex flex-col font-mono">
            <div class="border-b border-border bg-card/70">
                <div class="border-b border-border px-6 py-4">
                    <h3 class="text-lg font-semibold tracking-widest text-foreground">
                        > PETA KETAHANAN PANGAN INDONESIA
                    </h3>
                </div>
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label
                                class="mb-2 block text-xs tracking-widest text-muted-foreground"
                            >
                                PILIH KOMODITAS
                            </label>
                            <select
                                v-model="selectedKomoditas"
                                class="block w-full rounded-md border border-border bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none dark:border-green-500/20"
                            >
                                <option value="" disabled>Pilih Komoditas</option>
                                <option
                                    v-for="komoditas in komoditasList"
                                    :key="komoditas.value"
                                    :value="komoditas.value"
                                >
                                    {{ komoditas.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="mb-2 block text-xs tracking-widest text-muted-foreground"
                            >
                                LEVEL HARGA
                            </label>
                            <select
                                v-model="selectedLevelHarga"
                                class="block w-full rounded-md border border-border bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none dark:border-green-500/20"
                            >
                                <option value="1">Harga Produsen</option>
                                <option value="2">Harga Grosir</option>
                                <option value="3">Harga Eceran</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label
                                class="mb-2 block text-xs tracking-widest text-muted-foreground"
                            >
                                TANGGAL MULAI
                            </label>
                            <input
                                v-model="startDate"
                                type="date"
                                class="block w-full rounded-md border border-border bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none dark:border-green-500/20"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-2 block text-xs tracking-widest text-muted-foreground"
                            >
                                TANGGAL AKHIR
                            </label>
                            <input
                                v-model="endDate"
                                type="date"
                                class="block w-full rounded-md border border-border bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-ring focus:outline-none dark:border-green-500/20"
                            />
                        </div>

                        <div class="flex flex-col justify-end">
                            <button
                                @click="fetchPriceData"
                                :disabled="loading || !selectedKomoditas"
                                class="inline-flex w-full items-center justify-center rounded-md border border-border bg-primary px-4 py-2 text-sm font-semibold tracking-widest text-primary-foreground transition hover:bg-primary/90 focus:ring-2 focus:ring-ring focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <RefreshCw
                                    v-if="loading"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <Search v-else class="mr-2 h-4 w-4" />
                                {{ loading ? 'LOADING...' : '> EXECUTE' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative h-[75vh] overflow-hidden">
                <div
                    v-if="loading"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-background/70"
                >
                    <div class="flex flex-col items-center">
                        <RefreshCw class="mb-4 h-8 w-8 animate-spin text-primary" />
                        <p class="text-xs tracking-widest text-muted-foreground">LOADING_MAP_DATA...</p>
                    </div>
                </div>

                <div
                    v-else-if="error"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-background/70"
                >
                    <div class="text-center">
                        <AlertCircle class="mx-auto mb-4 h-8 w-8 text-destructive" />
                        <p class="text-sm text-destructive">{{ error }}</p>
                        <button
                            @click="fetchPriceData"
                            class="mt-3 text-xs tracking-widest text-primary underline-offset-4 hover:underline"
                        >
                            > RETRY
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    class="relative h-full bg-background"
                >
                    <svg
                        viewBox="0 0 792.54596 316.66394"
                        class="absolute inset-0 h-full w-full"
                        xmlns="http://www.w3.org/2000/svg"
                        preserveAspectRatio="xMinYMin"
                    >
                        <path
                            v-for="provinceCode in provincePathData"
                            :key="provinceCode.id"
                            :d="provinceCode.path"
                            :fill="getProvinceMapColor(provinceCode.name)"
                            stroke="rgba(34, 197, 94, 0.22)"
                            stroke-width="1"
                            stroke-linejoin="round"
                            class="cursor-pointer transition-all hover:stroke-[rgba(34,197,94,0.75)]"
                            @click="showProvinceByName(provinceCode.name)"
                        >
                            <title>{{ provinceCode.name }}</title>
                        </path>
                    </svg>

                    <div
                        v-if="lastUpdated && !selectedProvince"
                        class="rounded-lg border border-border bg-card/80 px-3 py-2 shadow-lg backdrop-blur"
                    >
                        <div class="text-[11px] tracking-widest text-muted-foreground">
                            > LAST_UPDATED: {{ formatDateTime(lastUpdated) }}
                        </div>
                    </div>

                    <div
                        v-if="selectedProvince"
                        class="absolute right-4 top-4 z-30 w-72 rounded-lg border border-border bg-card/90 p-4 shadow-lg backdrop-blur"
                    >
                        <h4 class="text-sm font-semibold tracking-widest text-foreground">
                            {{ selectedProvince.province_name }}
                        </h4>
                        <p class="mt-2 text-xs text-muted-foreground">
                            > HARGA:
                            <span class="font-semibold text-foreground">
                                {{ formatPrice(selectedProvince.price) }}
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            > STATUS:
                            <span
                                :class="getPriceStatusClass(selectedProvince.status)"
                                class="rounded px-2 py-1 text-xs"
                            >
                                {{ getPriceStatusText(selectedProvince.status) }}
                            </span>
                        </p>
                        <button
                            @click="selectedProvince = null"
                            class="mt-3 text-xs tracking-widest text-primary underline-offset-4 hover:underline"
                        >
                            > CLOSE
                        </button>
                    </div>

                    <div
                        class="absolute bottom-4 left-4 z-30 rounded-lg border border-border bg-card/80 p-4 shadow-lg backdrop-blur"
                        style="position: absolute"
                    >
                        <h4 class="mb-2 text-xs font-semibold tracking-widest text-foreground">
                            > LEGEND STATUS HARGA
                        </h4>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-green-500"></div>
                                <span class="text-muted-foreground">Aman</span>
                            </div>
                            <div class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-yellow-500"></div>
                                <span class="text-muted-foreground">Waspada</span>
                            </div>
                            <div class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-red-500"></div>
                                <span class="text-muted-foreground">Intervensi</span>
                            </div>
                            <div class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                                <span class="text-muted-foreground">N/A</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="priceData && priceData.length > 0"
                class="border-t border-border bg-card/70"
            >
                <div class="border-b border-border px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold tracking-widest text-foreground">
                            > DATA HARGA
                            {{
                                komoditasList.find((k) => k.value === selectedKomoditas)?.label ||
                                'Komoditas'
                            }}
                        </h3>
                        <div class="flex items-center space-x-4">
                            <div class="text-xs tracking-widest text-muted-foreground">
                                > {{ priceData.length }} PROVINSI
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="rounded-lg border border-border bg-background/40 p-4 dark:border-green-500/15">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs tracking-widest text-muted-foreground">
                                        RATA-RATA
                                    </p>
                                    <p class="mt-2 text-lg font-semibold tracking-wide text-foreground">
                                        {{ formatPrice(getAveragePrice()) }}
                                    </p>
                                </div>
                                <div class="rounded-full border border-border bg-background/40 p-2 dark:border-green-500/15">
                                    <svg
                                        class="h-6 w-6 text-primary"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-background/40 p-4 dark:border-green-500/15">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs tracking-widest text-muted-foreground">
                                        TERTINGGI
                                    </p>
                                    <p class="mt-2 text-lg font-semibold tracking-wide text-foreground">
                                        {{ formatPrice(getHighestPrice().price) }}
                                    </p>
                                    <p class="text-[11px] tracking-widest text-muted-foreground">
                                        {{ getHighestPrice().province }}
                                    </p>
                                </div>
                                <div class="rounded-full border border-border bg-background/40 p-2 dark:border-green-500/15">
                                    <svg
                                        class="h-6 w-6 text-primary"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M7 11l5-5m0 0l5 5m-5-5v12"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-background/40 p-4 dark:border-green-500/15">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs tracking-widest text-muted-foreground">
                                        TERENDAH
                                    </p>
                                    <p class="mt-2 text-lg font-semibold tracking-wide text-foreground">
                                        {{ formatPrice(getLowestPrice().price) }}
                                    </p>
                                    <p class="text-[11px] tracking-widest text-muted-foreground">
                                        {{ getLowestPrice().province }}
                                    </p>
                                </div>
                                <div class="rounded-full border border-border bg-background/40 p-2 dark:border-green-500/15">
                                    <svg
                                        class="h-6 w-6 text-primary"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 13l-5 5m0 0l-5-5m5 5V6"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-background/40 p-4 dark:border-green-500/15">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs tracking-widest text-muted-foreground">
                                        STATUS DOMINAN
                                    </p>
                                    <p class="mt-2 text-lg font-semibold tracking-wide text-foreground">
                                        {{ getDominantStatus().status }}
                                    </p>
                                    <p class="text-[11px] tracking-widest text-muted-foreground">
                                        {{ getDominantStatus().count }} PROVINSI
                                    </p>
                                </div>
                                <div class="rounded-full p-2" :class="getDominantStatus().bgClass">
                                    <div
                                        class="h-6 w-6 rounded-full"
                                        :class="getDominantStatus().colorClass"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-border">
                    <div class="overflow-y-auto">
                        <table class="min-w-full">
                            <thead class="sticky top-0 bg-background/70 backdrop-blur">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-semibold tracking-widest text-muted-foreground"
                                    >
                                        Provinsi
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-semibold tracking-widest text-muted-foreground"
                                    >
                                        Harga
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-semibold tracking-widest text-muted-foreground"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-semibold tracking-widest text-muted-foreground"
                                    >
                                        Trend
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-semibold tracking-widest text-muted-foreground"
                                    >
                                        vs HPP/HAP
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="item in priceData"
                                    :key="item.id"
                                    class="hover:bg-accent/30"
                                >
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center">
                                            <div
                                                class="mr-3 h-3 w-3 rounded-full"
                                                :style="{
                                                    backgroundColor: getMarkerColor(item.map_color, item.status),
                                                }"
                                            ></div>
                                            <span
                                                class="text-sm font-semibold text-foreground"
                                            >
                                                {{ item.province_name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            {{ formatPrice(item.price) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            :class="getPriceStatusClass(item.status)"
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                        >
                                            {{ getPriceStatusText(item.status) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center">
                                            <svg
                                                v-if="getTrendDirection(item) === 'up'"
                                                class="h-4 w-4 text-destructive"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M7 11l5-5m0 0l5 5m-5-5v12"
                                                />
                                            </svg>
                                            <svg
                                                v-else-if="getTrendDirection(item) === 'down'"
                                                class="h-4 w-4 text-primary"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M17 13l-5 5m0 0l-5-5m5 5V6"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                class="h-4 w-4 text-gray-400"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M20 12H4"
                                                />
                                            </svg>
                                            <span
                                                class="ml-1 text-xs"
                                                :class="{
                                                    'text-destructive': getTrendDirection(item) === 'up',
                                                    'text-primary': getTrendDirection(item) === 'down',
                                                    'text-muted-foreground': getTrendDirection(item) === 'stable',
                                                }"
                                            >
                                                {{ getTrendText(item) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center">
                                            <span
                                                class="text-xs font-medium"
                                                :class="{
                                                    'text-destructive': getHppHapPercentage(item) > 0,
                                                    'text-primary': getHppHapPercentage(item) < 0,
                                                    'text-muted-foreground': getHppHapPercentage(item) === 0,
                                                }"
                                            >
                                                {{
                                                    getHppHapPercentage(item) > 0 ? '+' : ''
                                                }}{{ getHppHapPercentage(item) }}%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertCircle, RefreshCw, Search } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Komoditas {
    value: string;
    label: string;
}

interface PriceDataItem {
    id: string;
    province_name: string;
    price: number;
    unit: string;
    status: string;
    map_color?: string;
    latlong?: string;
    hpp_hap?: string;
    hpp_hap_percentage?: number;
    hpp_hap_percentage_gap_change?: 'up' | 'down' | 'stable';
    hpp_hap_color_gap?: string;
}

type ProvincePathData = {
    id: string;
    name: string;
    path: string;
};

const props = defineProps<{
    komoditas: Komoditas[];
}>();

const selectedKomoditas = ref('35');
const selectedLevelHarga = ref('3');
const startDate = ref(new Date().toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);
const loading = ref(false);
const error = ref('');
const priceData = ref<PriceDataItem[]>([]);
const summaryData = ref<any>(null);
const lastUpdated = ref<Date | null>(null);
const selectedProvince = ref<PriceDataItem | null>(null);

const komoditasList = props.komoditas;
const provincePathData = ref<ProvincePathData[]>([]);

const parseProvincePathDataFromTs = (source: string): ProvincePathData[] => {
    const results: ProvincePathData[] = [];
    const re = /id:\s*'([^']+)'\s*,\s*name:\s*'([^']+)'\s*,\s*path:\s*'([^']*)'/g;
    for (const match of source.matchAll(re)) {
        const [, id, name, path] = match as unknown as [string, string, string, string];
        results.push({ id, name, path });
    }
    return results;
};

const loadProvincePaths = async () => {
    try {
        const res = await fetch('/api/ketahanan-pangan/indonesia-provinces-ts', {
            headers: { Accept: 'text/plain' },
        });
        const text = await res.text();
        if (!res.ok) {
            error.value = 'Gagal memuat data peta.';
            return;
        }
        const parsed = parseProvincePathDataFromTs(text);
        if (!parsed.length) {
            error.value = 'Gagal memuat data peta.';
            return;
        }
        provincePathData.value = parsed;
    } catch (e) {
        error.value = 'Gagal memuat data peta.';
    }
};

const formatDateForAPI = (dateString: string): string => {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
};

const fetchPriceData = async () => {
    if (!selectedKomoditas.value) return;

    loading.value = true;
    error.value = '';

    try {
        const periodDate = `${formatDateForAPI(startDate.value)} - ${formatDateForAPI(endDate.value)}`;
        const params = new URLSearchParams();
        params.set('level_harga_id', selectedLevelHarga.value);
        params.set('komoditas_id', selectedKomoditas.value);
        params.set('period_date', periodDate);
        params.set('multi_status_map[0]', '');
        params.set('multi_province_id[0]', '');

        const res = await fetch(`/api/ketahanan-pangan/harga-peta?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const json = (await res.json().catch(() => null)) as any;

        if (!res.ok) {
            error.value = 'Gagal mengambil data dari server. Silakan coba lagi.';
            priceData.value = [];
            summaryData.value = null;
            return;
        }

        if (json?.error) {
            error.value = String(json.error ?? '');
            priceData.value = [];
            summaryData.value = null;
            return;
        }

        priceData.value = transformPriceData(json);
        summaryData.value = json?.detail ?? null;
        lastUpdated.value = new Date();
    } catch (e) {
        error.value = 'Gagal mengambil data dari server. Silakan coba lagi.';
        priceData.value = [];
        summaryData.value = null;
    } finally {
        loading.value = false;
    }
};

const transformPriceData = (rawData: any): PriceDataItem[] => {
    if (rawData?.data && Array.isArray(rawData.data)) {
        return rawData.data.map((item: any) => ({
            id: item.province_id?.toString() || Math.random().toString(),
            province_name: item.province_name || 'Unknown',
            price: Number.parseFloat(item.rata_rata_geometrik || 0),
            unit: 'Rp/Kg',
            status: item.status_map || 'normal',
            map_color: item.map_color || 'gray',
            latlong: item.latlong || '',
            hpp_hap: item.hpp_hap || '',
            hpp_hap_percentage: Number.parseFloat(item.hpp_hap_percentage || 0),
            hpp_hap_percentage_gap_change: item.hpp_hap_percentage_gap_change || 'stable',
            hpp_hap_color_gap: item.hpp_hap_color_gap || 'gray',
        }));
    }
    return [];
};

const getMarkerColor = (mapColor?: string | null, status?: string | null): string => {
    switch (mapColor?.toString()?.toLowerCase()) {
        case 'green':
            return '#10b981';
        case 'yellow':
            return '#f59e0b';
        case 'red':
            return '#ef4444';
    }

    switch (status?.toString()?.toLowerCase()) {
        case 'aman':
            return '#10b981';
        case 'waspada':
            return '#f59e0b';
        case 'intervensi':
            return '#ef4444';
        default:
            return '#6b7280';
    }
};

const provinceNameMapping: Record<string, string[]> = {
    'DAERAH ISTIMEWA YOGYAKARTA': ['D.I. YOGYAKARTA', 'YOGYAKARTA', 'DI YOGYAKARTA', 'JOGJA', 'D.I YOGYAKARTA', 'DAERAH ISTIMEWA YOGYAKARTA'],
    'BANGKA BELITUNG': ['KEP. BANGKA BELITUNG', 'KEPULAUAN BANGKA BELITUNG'],
    'SUMATRA BARAT': ['SUMATERA BARAT', 'SUMBAR', 'SUMATRA BARAT'],
    'SUMATRA UTARA': ['SUMATERA UTARA', 'SUMUT', 'SUMATRA UTARA'],
    'SUMATRA SELATAN': ['SUMATERA SELATAN', 'SUMSEL', 'SUMATRA SELATAN'],
    'PAPUA SELATAN': ['PAPUA SELATAN'],
    'PAPUA PEGUNUNGAN': ['PAPUA PEGUNUNGAN'],
    'PAPUA BARAT': ['PAPUA BARAT'],
    'PAPUA BARAT DAYA': ['PAPUA BARAT DAYA'],
    'NUSA TENGGARA BARAT': ['NTB', 'NUSA TENGGARA BARAT'],
    'NUSA TENGGARA TIMUR': ['NTT', 'NUSA TENGGARA TIMUR'],
    'KALIMANTAN BARAT': ['KALBAR', 'KALIMANTAN BARAT'],
    'KALIMANTAN TENGAH': ['KALTENG', 'KALIMANTAN TENGAH'],
    'KALIMANTAN SELATAN': ['KALSEL', 'KALIMANTAN SELATAN'],
    'KALIMANTAN TIMUR': ['KALTIM', 'KALIMANTAN TIMUR'],
    'KALIMANTAN UTARA': ['KALUT', 'KALIMANTAN UTARA'],
    'SULAWESI UTARA': ['SULUT', 'SULAWESI UTARA'],
    'SULAWESI TENGAH': ['SULTENG', 'SULAWESI TENGAH'],
    'SULAWESI SELATAN': ['SULSEL', 'SULAWESI SELATAN'],
    'SULAWESI TENGGARA': ['SULTRA', 'SULAWESI TENGGARA'],
    'SULAWESI BARAT': ['SULBAR', 'SULAWESI BARAT'],
    'GORONTALO': ['GORONTALO'],
    'MALUKU': ['MALUKU'],
    'MALUKU UTARA': ['MALUT', 'MALUKU UTARA'],
    'JAWA BARAT': ['JABAR', 'JAWA BARAT'],
    'JAWA TENGAH': ['JATENG', 'JAWA TENGAH'],
    'JAWA TIMUR': ['JATIM', 'JAWA TIMUR'],
    'BANTEN': ['BANTEN'],
    'DKI JAKARTA': ['JAKARTA', 'DKI JAKARTA'],
    'BALI': ['BALI'],
    'ACEH': ['ACEH', 'NANGGROE ACEH DARUSSALAM'],
    'RIAU': ['RIAU'],
    'KEPULAUAN RIAU': ['KEP. RIAU', 'KEPULAUAN RIAU'],
    'JAMBI': ['JAMBI'],
    'BENGKULU': ['BENGKULU'],
    'LAMPUNG': ['LAMPUNG'],
};

const getProvinceMapColor = (provinceName: string): string => {
    let province = priceData.value.find((p) => p.province_name.toUpperCase() === provinceName.toUpperCase());

    if (!province) {
        const svgProvinceName = provinceName.toUpperCase();
        const apiProvinceAlias = Object.entries(provinceNameMapping).find(([_, aliases]) =>
            aliases.some((alias) => alias.toUpperCase() === svgProvinceName),
        );

        if (apiProvinceAlias) {
            const [mappedName] = apiProvinceAlias;
            province = priceData.value.find((p) =>
                provinceNameMapping[mappedName]?.some(
                    (alias) =>
                        p.province_name.toUpperCase().includes(alias.toUpperCase()) ||
                        alias.toUpperCase().includes(p.province_name.toUpperCase()),
                ),
            );
        }
    }

    if (!province) {
        province = priceData.value.find((p) => {
            const apiName = p.province_name.toUpperCase();
            const svgName = provinceName.toUpperCase();
            return (
                apiName.includes(svgName) ||
                svgName.includes(apiName) ||
                apiName.replace(/\s+/g, '').includes(svgName.replace(/\s+/g, '')) ||
                svgName.replace(/\s+/g, '').includes(apiName.replace(/\s+/g, ''))
            );
        });
    }

    if (!province) {
        return '#e5e7eb';
    }

    return getMarkerColor(province.map_color, province.status);
};

const showProvinceByName = (provinceName: string) => {
    let province = priceData.value.find((p) => p.province_name.toUpperCase() === provinceName.toUpperCase());

    if (!province) {
        const svgProvinceName = provinceName.toUpperCase();
        const apiProvinceAlias = Object.entries(provinceNameMapping).find(([_, aliases]) =>
            aliases.some((alias) => alias.toUpperCase() === svgProvinceName),
        );

        if (apiProvinceAlias) {
            const [mappedName] = apiProvinceAlias;
            province = priceData.value.find((p) =>
                provinceNameMapping[mappedName]?.some(
                    (alias) =>
                        p.province_name.toUpperCase().includes(alias.toUpperCase()) ||
                        alias.toUpperCase().includes(p.province_name.toUpperCase()),
                ),
            );
        }
    }

    if (!province) {
        province = priceData.value.find((p) => {
            const apiName = p.province_name.toUpperCase();
            const svgName = provinceName.toUpperCase();
            return (
                apiName.includes(svgName) ||
                svgName.includes(apiName) ||
                apiName.replace(/\s+/g, '').includes(svgName.replace(/\s+/g, '')) ||
                svgName.replace(/\s+/g, '').includes(apiName.replace(/\s+/g, ''))
            );
        });
    }

    if (province) {
        selectedProvince.value = province;
    }
};

const getAveragePrice = (): number => {
    if (summaryData.value && summaryData.value.hargaratarata) {
        return Number.parseFloat(summaryData.value.hargaratarata);
    }
    if (!priceData.value.length) return 0;
    const total = priceData.value.reduce((sum, item) => sum + item.price, 0);
    return total / priceData.value.length;
};

const getHighestPrice = (): { price: number; province: string } => {
    if (summaryData.value && summaryData.value.hargatertinggi) {
        return {
            price: Number.parseFloat(summaryData.value.hargatertinggi),
            province: summaryData.value.provinsitertinggi || '',
        };
    }
    if (!priceData.value.length) return { price: 0, province: '' };
    const highest = priceData.value.reduce((max, item) => (item.price > max.price ? item : max));
    return { price: highest.price, province: highest.province_name };
};

const getLowestPrice = (): { price: number; province: string } => {
    if (summaryData.value && summaryData.value.hargaterendah) {
        return {
            price: Number.parseFloat(summaryData.value.hargaterendah),
            province: summaryData.value.provinsiterendah || '',
        };
    }
    if (!priceData.value.length) return { price: 0, province: '' };
    const lowest = priceData.value.reduce((min, item) => (item.price < min.price ? item : min));
    return { price: lowest.price, province: lowest.province_name };
};

const getDominantStatus = (): { status: string; count: number; bgClass: string; colorClass: string } => {
    if (!priceData.value.length) {
        return { status: 'N/A', count: 0, bgClass: 'bg-gray-100', colorClass: 'bg-gray-300' };
    }

    const statusCount: Record<string, number> = {};
    for (const item of priceData.value) {
        const status = item.status || 'normal';
        statusCount[status] = (statusCount[status] || 0) + 1;
    }

    const dominantStatus = Object.keys(statusCount).reduce((a, b) => (statusCount[a] > statusCount[b] ? a : b));

    const statusMapping: Record<string, { label: string; bgClass: string; colorClass: string }> = {
        aman: { label: 'Aman', bgClass: 'bg-green-100 dark:bg-green-800', colorClass: 'bg-green-500' },
        waspada: { label: 'Waspada', bgClass: 'bg-yellow-100 dark:bg-yellow-800', colorClass: 'bg-yellow-500' },
        intervensi: { label: 'Intervensi', bgClass: 'bg-red-100 dark:bg-red-800', colorClass: 'bg-red-500' },
        normal: { label: 'Normal', bgClass: 'bg-gray-100 dark:bg-gray-800', colorClass: 'bg-gray-400' },
    };

    const mapping = statusMapping[dominantStatus.toLowerCase()] || statusMapping.normal;

    return {
        status: mapping.label,
        count: statusCount[dominantStatus] || 0,
        bgClass: mapping.bgClass,
        colorClass: mapping.colorClass,
    };
};

const getTrendDirection = (item: PriceDataItem): 'up' | 'down' | 'stable' => {
    const gapChange = (item as any).hpp_hap_percentage_gap_change;
    if (gapChange === 'up') return 'up';
    if (gapChange === 'down') return 'down';

    const avg = getAveragePrice();
    if (avg <= 0) return 'stable';
    if (item.price > avg * 1.05) return 'up';
    if (item.price < avg * 0.95) return 'down';
    return 'stable';
};

const getTrendText = (item: PriceDataItem): string => {
    const direction = getTrendDirection(item);
    const avg = getAveragePrice();
    if (avg <= 0) return '0.0%';
    const percentage = Math.abs(((item.price - avg) / avg) * 100);

    switch (direction) {
        case 'up':
            return `+${percentage.toFixed(1)}%`;
        case 'down':
            return `-${percentage.toFixed(1)}%`;
        default:
            return '0.0%';
    }
};

const getHppHapPercentage = (item: PriceDataItem): number => {
    const hppHapPercentage = (item as any).hpp_hap_percentage;
    if (typeof hppHapPercentage === 'number') {
        return Math.round(hppHapPercentage * 100) / 100;
    }
    return 0;
};

const formatPrice = (price: number): string => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);
};

const getPriceStatusClass = (status: string | null | undefined): string => {
    switch (status?.toString()?.toLowerCase()) {
        case 'aman':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'waspada':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'intervensi':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }
};

const getPriceStatusText = (status: string | null | undefined): string => {
    switch (status?.toString()?.toLowerCase()) {
        case 'aman':
            return 'Aman';
        case 'waspada':
            return 'Waspada';
        case 'intervensi':
            return 'Intervensi';
        default:
            return status || 'Normal';
    }
};

const formatDateTime = (date: Date): string => {
    return date.toLocaleString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(async () => {
    await loadProvincePaths();
    if (selectedKomoditas.value) {
        await fetchPriceData();
    }
});
</script>
