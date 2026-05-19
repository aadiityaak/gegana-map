<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
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

