<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
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

type ProvinceCount = {
    id: string;
    name: string;
    count: number;
};

type ProvinceRef = {
    id: string;
    name: string;
};

const mapSvg = ref<string>('');
const mapError = ref<string | null>(null);
const mapRoot = ref<HTMLDivElement | null>(null);
const counts = ref<ProvinceCount[]>([]);
const hovered = ref<{ name: string; count: number } | null>(null);
const provinces = ref<ProvinceRef[]>([]);
const provinceIdByName = computed(() => {
    const map: Record<string, string> = {};
    for (const row of provinces.value) {
        map[provinceKey(row.name)] = row.id;
    }
    return map;
});
const provinceNameById = computed(() => {
    const map: Record<string, string> = {};
    for (const row of provinces.value) {
        map[row.id] = row.name;
    }
    return map;
});

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

const countsByProvinceName = computed(() => {
    const map: Record<string, number> = {};
    for (const row of counts.value) {
        map[provinceKey(row.name)] = Number(row.count) || 0;
    }
    return map;
});

const colorForCount = (count: number, max: number) => {
    if (count <= 0) return '#052e16';
    if (max <= 0) return '#14532d';
    const ratio = count / max;
    if (ratio <= 0.25) return '#14532d';
    if (ratio <= 0.5) return '#166534';
    if (ratio <= 0.75) return '#16a34a';
    return '#22c55e';
};

const applyCountsToSvg = async () => {
    await nextTick();
    const root = mapRoot.value;
    if (!root) return;
    const svg = root.querySelector('svg') as SVGSVGElement | null;
    if (!svg) return;

    const max = Math.max(
        0,
        ...Object.values(countsByProvinceName.value).map((v) =>
            Number.isFinite(v) ? v : 0,
        ),
    );

    const oldLabels = svg.querySelector('#wan-teror-count-labels');
    oldLabels?.remove();

    const labelsGroup = document.createElementNS(
        'http://www.w3.org/2000/svg',
        'g',
    );
    labelsGroup.setAttribute('id', 'wan-teror-count-labels');
    svg.appendChild(labelsGroup);

    const paths = svg.querySelectorAll<SVGPathElement>('path[data-name], path[title]');
    for (const el of Array.from(paths)) {
        const rawName = (el.getAttribute('data-name') ?? el.getAttribute('title') ?? '').trim();
        if (!rawName) continue;

        const originalTitle = (el.getAttribute('data-title-original') ?? '').trim() || rawName;
        if (!el.getAttribute('data-title-original') && originalTitle) {
            el.setAttribute('data-title-original', originalTitle);
        }
        const rawTitle = originalTitle;
        if (!rawTitle) continue;
        const mappedTitle = provinceKey(rawTitle);
        const count = countsByProvinceName.value[mappedTitle] ?? 0;
        const provinceId = provinceIdByName.value[mappedTitle] ?? null;

        el.style.fill = colorForCount(count, max);
        el.style.stroke =
            currentProvinceId.value && provinceId === currentProvinceId.value
                ? 'rgba(34,197,94,0.8)'
                : 'rgba(34,197,94,0.25)';
        el.style.strokeWidth =
            currentProvinceId.value && provinceId === currentProvinceId.value
                ? '1.4'
                : '0.7';
        el.style.cursor = provinceId ? 'pointer' : 'default';
        el.setAttribute('data-count', String(count));
        el.setAttribute('title', `${rawTitle} (${count})`);

        el.onmouseenter = () => {
            hovered.value = { name: rawTitle, count };
        };
        el.onmouseleave = () => {
            hovered.value = null;
        };
        el.onclick = () => {
            if (!provinceId) return;
            const nextProvinceId =
                currentProvinceId.value === provinceId ? null : provinceId;
            router.get(
                '/wan-teror',
                {
                    type: currentType.value ?? undefined,
                    province_id: nextProvinceId ?? undefined,
                },
                { preserveScroll: true, preserveState: true, replace: true },
            );
        };

        if (count > 0) {
            try {
                const bbox = el.getBBox();
                const text = document.createElementNS(
                    'http://www.w3.org/2000/svg',
                    'text',
                );
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

const loadCounts = async () => {
    const type = currentType.value;
    const qs = type ? `?type=${encodeURIComponent(type)}` : '';
    const res = await fetch(`/api/wan-teror/counts-by-province${qs}`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
        return;
    }
    const json = (await res.json()) as { data?: ProvinceCount[] };
    counts.value = Array.isArray(json.data) ? json.data : [];
};

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

watch(
    () => props.filters.type,
    async (value, oldValue) => {
        if (value === oldValue) return;
        await loadCounts();
        await applyCountsToSvg();
    },
);

watch(
    () => props.filters.province_id,
    async (value, oldValue) => {
        if (value === oldValue) return;
        await applyCountsToSvg();
    },
);

onMounted(async () => {
    try {
        const res = await fetch('/api/wan-teror/indonesia-map-svg', {
            headers: { Accept: 'image/svg+xml' },
        });
        if (!res.ok) {
            mapError.value = `Gagal memuat map (${res.status})`;
            return;
        }
        await loadProvinces();
        await loadCounts();
        mapSvg.value = await res.text();
        await applyCountsToSvg();
    } catch {
        mapError.value = 'Gagal memuat map';
    }
});
</script>

<template>
    <Head title="WAN TEROR" />

    <div class="p-4 font-mono sm:p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > WAN TEROR
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
                :class="currentType === 'napiter' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link :href="listHref('napiter')">Napiter</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'ex-napiter' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link :href="listHref('ex-napiter')">EX Napiter</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'jaringan-terorisme' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link :href="listHref('jaringan-terorisme')">Jaringan</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'bullying-perundungan' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link :href="listHref('bullying-perundungan')">Bullying</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'aksi-teror' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link :href="listHref('aksi-teror')">Aksi Teror</Link>
            </Button>
        </div>

        <div class="mb-4 rounded-xl border border-green-500/15 bg-black/20 p-3">
            <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                <span>> MAP INDONESIA</span>
                <span class="text-[11px]">> indonesiaLow.svg</span>
            </div>
            <div
                v-if="mapError"
                class="rounded-lg border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-200"
            >
                > {{ mapError }}
            </div>
            <div
                v-else-if="mapSvg"
                class="relative w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30 p-2"
            >
                <div
                    v-if="hovered"
                    class="pointer-events-none absolute left-2 right-2 top-2 z-10 flex items-center justify-between rounded-lg border border-green-500/15 bg-black/50 px-3 py-2 text-xs text-green-200/90 backdrop-blur"
                >
                    <span>> {{ hovered.name }}</span>
                    <span class="text-[11px]">> kejadian: {{ hovered.count }}</span>
                </div>
                <div
                    ref="mapRoot"
                    class="mx-auto max-w-[1210px] overflow-hidden rounded-lg [&_svg]:h-auto [&_svg]:w-full [&_svg]:rounded-lg"
                    v-html="mapSvg"
                />
            </div>
            <div
                v-else
                class="rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-300/60"
            >
                loading_map...
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-green-500/15 bg-black/20">
            <div class="min-w-[860px]">
                <div class="grid grid-cols-12 gap-2 border-b border-green-500/15 p-3 text-xs text-green-300/70">
                    <div class="col-span-1">ID</div>
                    <div class="col-span-4">Kategori</div>
                    <div class="col-span-1">Sumber</div>
                    <div class="col-span-5">Wilayah</div>
                    <div class="col-span-1 text-right">Aksi</div>
                </div>
                <div
                    v-if="props.items.data.length === 0"
                    class="p-4 text-xs text-green-300/60"
                >
                    > belum ada data.
                </div>
                <div
                    v-for="row in props.items.data"
                    :key="row.id"
                    class="grid grid-cols-12 gap-2 border-b border-green-500/10 p-3 text-xs text-green-200/85 last:border-b-0"
                >
                    <div class="col-span-1">{{ row.id }}</div>
                    <div class="col-span-4">
                        {{ typeLabel(row.incident_type) }}
                        <div
                            v-if="row.finding_type"
                            class="mt-1 text-[11px] text-green-300/60"
                        >
                            > {{ row.finding_type }}
                        </div>
                    </div>
                    <div class="col-span-1">
                        <span class="rounded border border-green-500/15 bg-black/20 px-2 py-0.5 text-[11px] text-green-200/85">
                            {{ newsSourceLabel(row.news_source) }}
                        </span>
                    </div>
                    <div class="col-span-5">
                        {{
                            [row.village_name, row.district_name, row.regency_name, row.province_name]
                                .filter(Boolean)
                                .join(', ') ||
                                `${row.village_id}, ${row.district_id}, ${row.regency_id}, ${row.province_id}`
                        }}
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-green-300/60">
                            <span
                                v-if="photosCount(row.photos) > 0"
                                class="rounded border border-green-500/15 bg-black/20 px-2 py-0.5"
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
                    <div class="col-span-1 flex justify-end gap-2">
                        <Button size="sm" variant="secondary" as-child>
                            <Link :href="`/wan-teror/${row.id}`">View</Link>
                        </Button>
                        <Button size="sm" variant="secondary" as-child>
                            <Link :href="`/wan-teror/${row.id}/edit`">Edit</Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            @click="deleteItem(row.id)"
                        >
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
