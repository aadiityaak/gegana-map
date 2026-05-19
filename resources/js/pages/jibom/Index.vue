<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type JibomItem = {
    id: number;
    incident_type: string;
    finding_type: string | null;
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
    filters: { type: string | null };
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

const currentType = computed(() => props.filters.type);

const createHref = computed(() => {
    if (!currentType.value) return '/jibom/create';
    return `/jibom/create?type=${encodeURIComponent(currentType.value)}`;
});

const deleteItem = (id: number) => {
    router.delete(`/jibom/${id}`);
};

type ProvinceCount = {
    id: string;
    name: string;
    count: number;
};

const mapSvg = ref<string>('');
const mapError = ref<string | null>(null);
const mapRoot = ref<HTMLDivElement | null>(null);
const counts = ref<ProvinceCount[]>([]);
const hovered = ref<{ name: string; count: number } | null>(null);

const normalizeName = (value: string) =>
    value
        .toUpperCase()
        .replace(/\s+/g, ' ')
        .trim();

const titleAliases: Record<string, string> = {
    'JAKARTA RAYA': 'DKI JAKARTA',
    YOGYAKARTA: 'DAERAH ISTIMEWA YOGYAKARTA',
};

const countsByProvinceName = computed(() => {
    const map: Record<string, number> = {};
    for (const row of counts.value) {
        map[normalizeName(row.name)] = Number(row.count) || 0;
    }
    return map;
});

const colorForCount = (count: number, max: number) => {
    if (count <= 0) return '#0b0f0b';
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

    const polygons = svg.querySelector('#polygons') as SVGGElement | null;
    if (!polygons) return;

    const oldLabels = svg.querySelector('#jibom-count-labels');
    oldLabels?.remove();

    const labelsGroup = document.createElementNS(
        'http://www.w3.org/2000/svg',
        'g',
    );
    labelsGroup.setAttribute('id', 'jibom-count-labels');
    polygons.appendChild(labelsGroup);

    const paths = svg.querySelectorAll<SVGPathElement>('#polygons .land');
    for (const el of Array.from(paths)) {
        const rawTitle = (el.getAttribute('title') ?? '').trim();
        if (!rawTitle) continue;
        const normalizedTitle = normalizeName(rawTitle);
        const mappedTitle = titleAliases[normalizedTitle] ?? normalizedTitle;
        const count = countsByProvinceName.value[mappedTitle] ?? 0;

        el.style.fill = colorForCount(count, max);
        el.style.stroke = 'rgba(34,197,94,0.25)';
        el.style.strokeWidth = '0.7';
        el.style.cursor = 'pointer';
        el.setAttribute('data-count', String(count));
        el.setAttribute('title', `${rawTitle} (${count})`);

        el.onmouseenter = () => {
            hovered.value = { name: rawTitle, count };
        };
        el.onmouseleave = () => {
            hovered.value = null;
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
    const res = await fetch(`/api/jibom/counts-by-province${qs}`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
        return;
    }
    const json = (await res.json()) as { data?: ProvinceCount[] };
    counts.value = Array.isArray(json.data) ? json.data : [];
};


onMounted(async () => {
    try {
        const res = await fetch('/api/jibom/indonesia-map-svg', {
            headers: { Accept: 'image/svg+xml' },
        });
        if (!res.ok) {
            mapError.value = `Gagal memuat map (${res.status})`;
            return;
        }
        await loadCounts();
        mapSvg.value = await res.text();
        await applyCountsToSvg();
    } catch {
        mapError.value = 'Gagal memuat map';
    }
});
</script>

<template>
    <Head title="JIBOM" />

    <div class="p-6 font-mono">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > JIBOM
                </h1>
                <Badge class="border border-green-500/25 bg-black/30 text-green-200">
                    {{ currentType ? typeLabel(currentType) : 'Semua' }}
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
                <Link href="/jibom">Semua</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'ancaman' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link href="/jibom?type=ancaman">Ancaman</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'temuan' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link href="/jibom?type=temuan">Temuan</Link>
            </Button>
            <Button
                variant="secondary"
                :class="currentType === 'ledakan' ? 'border-green-500/25 bg-green-500/10 text-green-200' : ''"
                as-child
            >
                <Link href="/jibom?type=ledakan">Ledakan</Link>
            </Button>
        </div>

        <div class="mb-4 rounded-xl border border-green-500/15 bg-black/20 p-3">
            <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                <span>> MAP INDONESIA</span>
                <span class="text-[11px]">> indonesiaLow.svg</span>
            </div>
            <div v-if="hovered" class="mb-2 flex items-center justify-between rounded-lg border border-green-500/15 bg-black/30 px-3 py-2 text-xs text-green-200/85">
                <span>> {{ hovered.name }}</span>
                <span class="text-[11px]">> kejadian: {{ hovered.count }}</span>
            </div>
            <div v-if="mapError" class="rounded-lg border border-red-500/25 bg-red-500/10 p-3 text-xs text-red-200">
                > {{ mapError }}
            </div>
            <div
                v-else-if="mapSvg"
                class="w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30 p-2"
            >
                <div ref="mapRoot" class="mx-auto max-w-[980px] [&_svg]:h-auto [&_svg]:w-full" v-html="mapSvg" />
            </div>
            <div v-else class="rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-300/60">
                loading_map...
            </div>
        </div>

        <div class="rounded-xl border border-green-500/15 bg-black/20">
            <div class="grid grid-cols-12 gap-2 border-b border-green-500/15 p-3 text-xs text-green-300/70">
                <div class="col-span-1">ID</div>
                <div class="col-span-3">Jenis</div>
                <div class="col-span-2">Temuan</div>
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
                <div class="col-span-3">
                    {{ typeLabel(row.incident_type) }}
                </div>
                <div class="col-span-2">
                    {{ findingLabel(row.finding_type) }}
                </div>
                <div class="col-span-4">
                    {{
                        [row.village_name, row.district_name, row.regency_name, row.province_name]
                            .filter(Boolean)
                            .join(', ') || `${row.village_id}, ${row.district_id}, ${row.regency_id}, ${row.province_id}`
                    }}
                </div>
                <div class="col-span-2 flex justify-end gap-2">
                    <Button size="sm" variant="secondary" as-child>
                        <Link :href="`/jibom/${row.id}/edit`">Edit</Link>
                    </Button>
                    <Button size="sm" variant="destructive" @click="deleteItem(row.id)">
                        Hapus
                    </Button>
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

