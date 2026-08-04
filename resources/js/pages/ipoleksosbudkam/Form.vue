<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import DatePicker from '@/components/ui/date-picker/DatePicker.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/InputError.vue';

type Mode = 'create' | 'edit' | 'view' | 'index';

const props = defineProps<{
    mode: Mode;
    item: {
        id: number;
        title: string;
        description: string | null;
        incident_date: string | null;
        severity_level: string;
        status: string;
        category: string | null;
        sub_category: string | null;
        latitude: number | string | null;
        longitude: number | string | null;
        provinsi: string | null;
        kabupaten_kota: string | null;
        kecamatan: string | null;
        jumlah_terdampak: number | null;
        source: string | null;
        sumber_berita: string | null;
    } | null;
    items?: any;
    filters?: { category?: string | null };
}>();

const page = usePage();

const severityOptions = [
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
    { value: 'critical', label: 'Kritis' },
];

const statusOptions = [
    { value: 'active', label: 'Aktif' },
    { value: 'monitoring', label: 'Monitoring' },
    { value: 'resolved', label: 'Selesai' },
];

const categoryOptions = [
    { value: 'ideologi', label: 'Ideologi' },
    { value: 'politik', label: 'Politik' },
    { value: 'ekonomi', label: 'Ekonomi' },
    { value: 'sosial-budaya', label: 'Sosial Budaya' },
    { value: 'keamanan', label: 'Keamanan' },
];

const subCategoryMap: Record<string, { value: string; label: string }[]> = {
    ideologi: [
        { value: 'ideologi-ideologi-kanan', label: 'Ideologi Kanan' },
        { value: 'ideologi-ideologi-kiri', label: 'Ideologi Kiri' },
        { value: 'ideologi-isu-menonjol', label: 'Isu Menonjol' },
    ],
    politik: [
        { value: 'politik-dalam-negeri', label: 'Dalam Negeri' },
        { value: 'politik-luar-negeri', label: 'Luar Negeri' },
        { value: 'politik-isu-menonjol', label: 'Isu Menonjol' },
    ],
    ekonomi: [
        { value: 'ekonomi-export-import', label: 'Export Import' },
        { value: 'ekonomi-harga-sembako', label: 'Harga Sembako' },
        { value: 'ekonomi-kurs-mata-uang', label: 'Kurs Mata Uang' },
        { value: 'ekonomi-pasar-saham', label: 'Pasar Saham' },
        { value: 'ekonomi-index-pendapatan-masyarakat', label: 'Index Pendapatan Masyarakat' },
        { value: 'ekonomi-kesenjangan-sosial', label: 'Kesenjangan Sosial' },
        { value: 'ekonomi-ekonomi-asing', label: 'Ekonomi Asing' },
        { value: 'ekonomi-pro-kontra-proyek-strategis-nasional', label: 'Pro Kontra Proyek Strategis' },
        { value: 'ekonomi-korupsi', label: 'Korupsi' },
        { value: 'ekonomi-isu-menonjol', label: 'Isu Menonjol' },
    ],
    'sosial-budaya': [
        { value: 'sosial-budaya-ormas', label: 'Ormas' },
        { value: 'sosial-budaya-bencana-alam', label: 'Bencana Alam' },
        { value: 'sosial-budaya-unjuk-rasa', label: 'Unjuk Rasa' },
        { value: 'sosial-budaya-konflik-sosial', label: 'Konflik Sosial' },
        { value: 'sosial-budaya-phk', label: 'PHK' },
        { value: 'sosial-budaya-sara', label: 'SARA' },
        { value: 'sosial-budaya-isu-menonjol', label: 'Isu Menonjol' },
    ],
    keamanan: [
        { value: 'keamanan-teror', label: 'Teror' },
        { value: 'keamanan-keamanan-negara', label: 'Keamanan Negara' },
        { value: 'keamanan-isu-menonjol', label: 'Isu Menonjol' },
    ],
};

const isView = computed(() => props.mode === 'view');
const isEdit = computed(() => props.mode === 'edit');
const isCreate = computed(() => props.mode === 'create');

const form = useForm({
    title: props.item?.title ?? '',
    description: props.item?.description ?? '',
    incident_date: props.item?.incident_date ?? '',
    severity_level: props.item?.severity_level ?? 'low',
    status: props.item?.status ?? 'active',
    category: props.item?.category ?? '',
    sub_category: props.item?.sub_category ?? '',
    latitude: props.item?.latitude != null ? String(props.item.latitude) : '',
    longitude: props.item?.longitude != null ? String(props.item.longitude) : '',
    provinsi: props.item?.provinsi ?? '',
    kabupaten_kota: props.item?.kabupaten_kota ?? '',
    kecamatan: props.item?.kecamatan ?? '',
    jumlah_terdampak: props.item?.jumlah_terdampak != null ? String(props.item.jumlah_terdampak) : '',
    source: props.item?.source ?? '',
    sumber_berita: props.item?.sumber_berita ?? '',
});

const filteredSubCategories = computed(() => {
    if (!form.category) return [];
    return subCategoryMap[form.category] ?? [];
});

watch(() => form.category, () => {
    form.sub_category = '';
});

const title = computed(() => {
    if (isCreate.value) return 'IPOLEKSOSBUDKAM / create';
    if (isEdit.value) return 'IPOLEKSOSBUDKAM / edit';
    if (isView.value) return 'IPOLEKSOSBUDKAM / detail';
    return 'IPOLEKSOSBUDKAM / local';
});

const submit = () => {
    const data: Record<string, any> = {
        title: form.title,
        description: form.description || null,
        incident_date: form.incident_date || null,
        severity_level: form.severity_level,
        status: form.status,
        category: form.category || null,
        sub_category: form.sub_category || null,
        latitude: form.latitude ? Number(form.latitude) : null,
        longitude: form.longitude ? Number(form.longitude) : null,
        provinsi: form.provinsi || null,
        kabupaten_kota: form.kabupaten_kota || null,
        kecamatan: form.kecamatan || null,
        jumlah_terdampak: form.jumlah_terdampak ? Number(form.jumlah_terdampak) : null,
        source: form.source || null,
        sumber_berita: form.sumber_berita || null,
    };

    if (isEdit.value && props.item) {
        form.put(`/ipoleksosbudkam-local/${props.item.id}`, {
            preserveScroll: true,
            onSuccess: () => {},
        });
    } else {
        form.post('/ipoleksosbudkam-local', {
            preserveScroll: true,
            onSuccess: () => {},
        });
    }
};
</script>

<template>
    <Head :title="title" />

    <div class="p-4 font-mono sm:p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a
                    href="/ipoleksosbudkam"
                    class="inline-flex items-center justify-center rounded-md border border-sky-500/15 bg-black/30 px-3 py-2 text-sm tracking-widest text-sky-200 hover:border-sky-400/25"
                >
                    > BACK
                </a>
                <h1 class="text-lg font-semibold tracking-widest text-sky-200">{{ title }}</h1>
            </div>
        </div>

        <div class="rounded-xl border border-sky-500/15 bg-black/30 p-6">
            <form v-if="!isView" @submit.prevent="submit" class="space-y-4">
                <!-- Title -->
                <div>
                    <Label class="text-sky-200">Judul *</Label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                        placeholder="Judul data..."
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <!-- Description -->
                <div>
                    <Label class="text-sky-200">Deskripsi</Label>
                    <textarea
                        v-model="form.description"
                        rows="5"
                        class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                        placeholder="Deskripsi..."
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Row: date + terdampak -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label class="text-sky-200">Tanggal Kejadian</Label>
                        <div class="mt-1">
                            <DatePicker v-model="form.incident_date" />
                        </div>
                        <InputError :message="form.errors.incident_date" />
                    </div>
                    <div>
                        <Label class="text-sky-200">Jumlah Terdampak</Label>
                        <input
                            v-model="form.jumlah_terdampak"
                            type="number"
                            class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                            placeholder="0"
                        />
                        <InputError :message="form.errors.jumlah_terdampak" />
                    </div>
                </div>

                <!-- Row: severity + status -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label class="text-sky-200">Tingkat Keparahan *</Label>
                        <Select v-model="form.severity_level">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent class="border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem v-for="s in severityOptions" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.severity_level" />
                    </div>
                    <div>
                        <Label class="text-sky-200">Status *</Label>
                        <Select v-model="form.status">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent class="border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem v-for="s in statusOptions" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>

                <!-- Row: category + sub_category -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label class="text-sky-200">Kategori</Label>
                        <Select v-model="form.category">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100">
                                <SelectValue placeholder="Pilih kategori..." />
                            </SelectTrigger>
                            <SelectContent class="border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem value="">Semua</SelectItem>
                                <SelectItem v-for="c in categoryOptions" :key="c.value" :value="c.value">
                                    {{ c.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.category" />
                    </div>
                    <div>
                        <Label class="text-sky-200">Sub Kategori</Label>
                        <Select v-model="form.sub_category" :disabled="!form.category">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100">
                                <SelectValue placeholder="Pilih sub kategori..." />
                            </SelectTrigger>
                            <SelectContent class="border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem value="">Semua</SelectItem>
                                <SelectItem v-for="sc in filteredSubCategories" :key="sc.value" :value="sc.value">
                                    {{ sc.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.sub_category" />
                    </div>
                </div>

                <!-- Row: lat + lng -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label class="text-sky-200">Latitude</Label>
                        <input
                            v-model="form.latitude"
                            type="number"
                            step="any"
                            class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                            placeholder="-6.2088"
                        />
                        <InputError :message="form.errors.latitude" />
                    </div>
                    <div>
                        <Label class="text-sky-200">Longitude</Label>
                        <input
                            v-model="form.longitude"
                            type="number"
                            step="any"
                            class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                            placeholder="106.8456"
                        />
                        <InputError :message="form.errors.longitude" />
                    </div>
                </div>

                <!-- Row: provinsi + kab/kota + kec -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label class="text-sky-200">Provinsi</Label>
                        <input
                            v-model="form.provinsi"
                            type="text"
                            class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                            placeholder="Provinsi..."
                        />
                    </div>
                    <div>
                        <Label class="text-sky-200">Kabupaten / Kota</Label>
                        <input
                            v-model="form.kabupaten_kota"
                            type="text"
                            class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                            placeholder="Kab/Kota..."
                        />
                    </div>
                    <div>
                        <Label class="text-sky-200">Kecamatan</Label>
                        <input
                            v-model="form.kecamatan"
                            type="text"
                            class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                            placeholder="Kecamatan..."
                        />
                    </div>
                </div>

                <!-- Row: source + sumber_berita -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label class="text-sky-200">Source</Label>
                        <input
                            v-model="form.source"
                            type="text"
                            class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                            placeholder="Source..."
                        />
                    </div>
                    <div>
                        <Label class="text-sky-200">Sumber Berita</Label>
                        <input
                            v-model="form.sumber_berita"
                            type="text"
                            class="mt-1 w-full rounded-md border border-sky-500/25 bg-black/40 px-3 py-2 text-sm text-sky-100 placeholder:text-sky-500/50"
                            placeholder="URL atau nama sumber..."
                        />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <Button
                        type="submit"
                        class="border-sky-500/25 bg-sky-500/15 text-sky-200 hover:bg-sky-500/25"
                        :disabled="form.processing"
                    >
                        > {{ isEdit ? 'UPDATE' : 'SIMPAN' }}
                    </Button>
                    <a
                        href="/ipoleksosbudkam"
                        class="inline-flex items-center justify-center rounded-md border border-sky-500/15 bg-black/30 px-4 py-2 text-sm tracking-widest text-sky-300 hover:border-sky-400/25"
                    >
                        > CANCEL
                    </a>
                </div>
            </form>

            <!-- View Mode -->
            <div v-else-if="isView && item" class="space-y-3 text-sm text-sky-200">
                <div class="text-base font-semibold text-sky-100">> {{ item.title }}</div>
                <div v-if="item.description" class="text-sky-300/80 whitespace-pre-wrap">{{ item.description }}</div>

                <div class="grid gap-2 rounded-lg border border-sky-500/15 bg-black/20 p-3 text-sky-300 md:grid-cols-2">
                    <div v-if="item.incident_date">> tanggal: {{ item.incident_date }}</div>
                    <div>> severity: {{ severityOptions.find(s => s.value === item.severity_level)?.label ?? item.severity_level }}</div>
                    <div>> status: {{ statusOptions.find(s => s.value === item.status)?.label ?? item.status }}</div>
                    <div v-if="item.category">> kategori: {{ categoryOptions.find(c => c.value === item.category)?.label ?? item.category }}</div>
                    <div v-if="item.sub_category">> sub: {{ item.sub_category }}</div>
                    <div v-if="item.latitude != null && item.longitude != null">> koordinat: {{ item.latitude }}, {{ item.longitude }}</div>
                    <div v-if="item.provinsi">> provinsi: {{ [item.provinsi, item.kabupaten_kota, item.kecamatan].filter(Boolean).join(', ') }}</div>
                    <div v-if="item.jumlah_terdampak != null">> terdampak: {{ item.jumlah_terdampak }}</div>
                    <div v-if="item.source">> source: {{ item.source }}</div>
                    <div v-if="item.sumber_berita">> sumber: {{ item.sumber_berita }}</div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <a
                        :href="`/ipoleksosbudkam-local/${item.id}/edit`"
                        class="inline-flex items-center justify-center rounded-md border border-sky-500/25 bg-sky-500/10 px-4 py-2 text-sm tracking-widest text-sky-200 hover:bg-sky-500/25"
                    >
                        > EDIT
                    </a>
                    <a
                        href="/ipoleksosbudkam"
                        class="inline-flex items-center justify-center rounded-md border border-sky-500/15 bg-black/30 px-4 py-2 text-sm tracking-widest text-sky-300 hover:border-sky-400/25"
                    >
                        > BACK
                    </a>
                </div>
            </div>

            <!-- Index mode: admin list -->
            <div v-else-if="mode === 'index' && items" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-sky-300">> Total lokal: {{ items.meta?.total ?? items.data?.length ?? 0 }}</span>
                        <a
                            href="/ipoleksosbudkam-local/create"
                            class="inline-flex items-center justify-center rounded-md border border-green-500/35 bg-green-500/10 px-4 py-2 text-sm tracking-widest text-green-300 hover:bg-green-500/20"
                        >
                            > TAMBAH
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-sky-200">
                            <thead>
                                <tr class="border-b border-sky-500/15 text-left text-sky-300">
                                    <th class="px-3 py-2">Judul</th>
                                    <th class="px-3 py-2">Kategori</th>
                                    <th class="px-3 py-2">Tanggal</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in (items.data ?? [])" :key="row.id" class="border-b border-sky-500/10 hover:bg-sky-500/5">
                                    <td class="px-3 py-2 truncate max-w-[200px]">{{ row.title }}</td>
                                    <td class="px-3 py-2">{{ row.category ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ row.incident_date ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ row.status }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <a
                                                :href="`/ipoleksosbudkam-local/${row.id}`"
                                                class="text-sky-300 hover:text-sky-100"
                                            >view</a>
                                            <a
                                                :href="`/ipoleksosbudkam-local/${row.id}/edit`"
                                                class="text-amber-300 hover:text-amber-100"
                                            >edit</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!items.data?.length">
                                    <td colspan="5" class="px-3 py-4 text-center text-sky-300">Belum ada data lokal.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination for index -->
                    <div v-if="items.meta?.last_page > 1" class="flex items-center justify-between gap-3 text-sm text-sky-300">
                        <span>Halaman {{ items.meta.current_page }} / {{ items.meta.last_page }}</span>
                        <div class="flex gap-2">
                            <a
                                v-for="link in items.meta.links?.filter((l: any) => l.label && l.label !== '&laquo; Previous' && l.label !== 'Next &raquo;') ?? []"
                                :key="link.label"
                                :href="link.url ?? '#'"
                                class="rounded-md border px-3 py-1 text-xs"
                                :class="link.active ? 'border-sky-400/35 bg-sky-500/15 text-sky-100' : 'border-sky-500/15 bg-black/30 text-sky-200 hover:border-sky-400/25'"
                                v-html="link.label"
                            />
                        </div>
                </div>
            </div>
        </div>
    </div>
</template>
