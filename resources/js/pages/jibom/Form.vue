<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type WilayahItem = { id: string; name: string };
type Mode = 'create' | 'edit';

const props = defineProps<{
    mode: Mode;
    item: {
        id: number;
        incident_type: string;
        finding_type: string | null;
        province_id: string;
        regency_id: string;
        district_id: string;
        village_id: string;
    } | null;
    filters?: { type?: string | null };
}>();

const incidentTypes = [
    { value: 'ancaman', label: 'Ancaman Pengeboman' },
    { value: 'temuan', label: 'Temuan Bom' },
    { value: 'ledakan', label: 'Ledakan Bom' },
];

const findingTypes = [
    { value: 'bom-militer', label: 'Bom Militer' },
    { value: 'bom-rakitan', label: 'Bom Rakitan' },
    { value: 'bom-ikan', label: 'Bom Ikan' },
    { value: 'petasan', label: 'Petasan & Lainnya' },
    { value: 'lainnya', label: 'Lainnya' },
];

const initialType = computed(() => {
    if (props.item) return props.item.incident_type;
    const t = props.filters?.type;
    if (t === 'ancaman' || t === 'temuan' || t === 'ledakan') return t;
    return 'ancaman';
});

const form = useForm({
    incident_type: props.item?.incident_type ?? initialType.value,
    finding_type: props.item?.finding_type ?? '',
    province_id: props.item?.province_id ?? '',
    regency_id: props.item?.regency_id ?? '',
    district_id: props.item?.district_id ?? '',
    village_id: props.item?.village_id ?? '',
});

const provinces = ref<WilayahItem[]>([]);
const regencies = ref<WilayahItem[]>([]);
const districts = ref<WilayahItem[]>([]);
const villages = ref<WilayahItem[]>([]);

const loadingRegencies = ref(false);
const loadingDistricts = ref(false);
const loadingVillages = ref(false);

const isTemuan = computed(() => form.incident_type === 'temuan');

const fetchJson = async (url: string) => {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) return [];
    return (await res.json()) as WilayahItem[];
};

const loadProvinces = async () => {
    provinces.value = await fetchJson('/api/wilayah/provinces');
};

const loadRegencies = async (provinceId: string) => {
    loadingRegencies.value = true;
    regencies.value = await fetchJson(
        `/api/wilayah/regencies?province_id=${encodeURIComponent(provinceId)}`,
    );
    loadingRegencies.value = false;
};

const loadDistricts = async (regencyId: string) => {
    loadingDistricts.value = true;
    districts.value = await fetchJson(
        `/api/wilayah/districts?regency_id=${encodeURIComponent(regencyId)}`,
    );
    loadingDistricts.value = false;
};

const loadVillages = async (districtId: string) => {
    loadingVillages.value = true;
    villages.value = await fetchJson(
        `/api/wilayah/villages?district_id=${encodeURIComponent(districtId)}`,
    );
    loadingVillages.value = false;
};

watch(
    () => form.incident_type,
    () => {
        if (!isTemuan.value) {
            form.finding_type = '';
        }
    },
);

watch(
    () => form.province_id,
    async (value) => {
        regencies.value = [];
        districts.value = [];
        villages.value = [];
        form.regency_id = '';
        form.district_id = '';
        form.village_id = '';
        if (!value) return;
        await loadRegencies(value);
    },
);

watch(
    () => form.regency_id,
    async (value) => {
        districts.value = [];
        villages.value = [];
        form.district_id = '';
        form.village_id = '';
        if (!value) return;
        await loadDistricts(value);
    },
);

watch(
    () => form.district_id,
    async (value) => {
        villages.value = [];
        form.village_id = '';
        if (!value) return;
        await loadVillages(value);
    },
);

onMounted(async () => {
    await loadProvinces();
    if (form.province_id) await loadRegencies(form.province_id);
    if (form.regency_id) await loadDistricts(form.regency_id);
    if (form.district_id) await loadVillages(form.district_id);
});

const submit = () => {
    if (props.mode === 'edit' && props.item) {
        form.put(`/jibom/${props.item.id}`);
        return;
    }
    form.post('/jibom');
};

const title = computed(() => (props.mode === 'edit' ? 'Edit JIBOM' : 'Tambah JIBOM'));
</script>

<template>
    <Head :title="title" />

    <div class="p-6 font-mono">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > {{ props.mode === 'edit' ? 'EDIT JIBOM' : 'TAMBAH JIBOM' }}
                </h1>
                <Badge class="border border-green-500/25 bg-black/30 text-green-200">
                    {{ form.incident_type }}
                </Badge>
            </div>
            <Button variant="secondary" as-child>
                <Link href="/jibom">Back</Link>
            </Button>
        </div>

        <div class="space-y-6 rounded-xl border border-green-500/15 bg-black/20 p-4">
            <div class="grid gap-2">
                <Label>Jenis</Label>
                <Select v-model="form.incident_type">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Pilih jenis" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="t in incidentTypes"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.incident_type" />
            </div>

            <div v-if="isTemuan" class="grid gap-2">
                <Label>Temuan Bom</Label>
                <Select v-model="form.finding_type">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Pilih jenis temuan" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="t in findingTypes"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.finding_type" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Provinsi</Label>
                    <Select v-model="form.province_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Pilih provinsi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="p in provinces"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.province_id" />
                </div>

                <div class="grid gap-2">
                    <Label>Kab/Kota</Label>
                    <Select v-model="form.regency_id" :disabled="!form.province_id || loadingRegencies">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Pilih kab/kota" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="r in regencies"
                                :key="r.id"
                                :value="r.id"
                            >
                                {{ r.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.regency_id" />
                </div>

                <div class="grid gap-2">
                    <Label>Kecamatan</Label>
                    <Select v-model="form.district_id" :disabled="!form.regency_id || loadingDistricts">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Pilih kecamatan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="d in districts"
                                :key="d.id"
                                :value="d.id"
                            >
                                {{ d.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.district_id" />
                </div>

                <div class="grid gap-2">
                    <Label>Kelurahan/Desa</Label>
                    <Select v-model="form.village_id" :disabled="!form.district_id || loadingVillages">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Pilih kel/desa" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="v in villages"
                                :key="v.id"
                                :value="v.id"
                            >
                                {{ v.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.village_id" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" :disabled="form.processing" @click="submit">
                    Save
                </Button>
                <Button type="button" variant="secondary" :disabled="form.processing" as-child>
                    <Link href="/jibom">Cancel</Link>
                </Button>
            </div>
        </div>
    </div>
</template>

