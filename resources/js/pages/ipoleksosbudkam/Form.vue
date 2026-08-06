<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, nextTick, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
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
        gallery?: Array<{ path: string; url: string }> | null;
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
    provinceList?: { id: number; name: string }[];
    regencyList?: { id: number; name: string; province_id: number }[];
    districtList?: { id: number; name: string; regency_id: number }[];
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

const sourceOptions = [
    { value: 'Offline', label: 'Offline' },
    { value: 'Online', label: 'Online' },
    { value: 'Ai Agent', label: 'Ai Agent' },
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

// Gallery
const galleryFiles = ref<File[]>([]);
const existingGallery = ref<Array<{ path: string; url: string }>>(
    (props.item?.gallery && Array.isArray(props.item.gallery))
        ? [...props.item.gallery]
        : [],
);
const keepGalleryPaths = ref<string[]>(existingGallery.value.map((g) => g.path));

function onGalleryFilesChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (!input.files) return;
    for (let i = 0; i < input.files.length; i++) {
        galleryFiles.value.push(input.files[i]);
    }
    input.value = ''; // reset so same file can be re-selected
}

function removeNewFile(index: number) {
    galleryFiles.value.splice(index, 1);
}

function removeExisting(index: number) {
    existingGallery.value.splice(index, 1);
    keepGalleryPaths.value = existingGallery.value.map((g) => g.path);
}

function previewUrl(file: File): string {
    return URL.createObjectURL(file);
}

// Local refs for category/sub_category to avoid useForm reactivity issues with shadcn Select
const localCategory = ref(props.item?.category ?? '');
const localSubCategory = ref(props.item?.sub_category ?? '');

// Leaflet map for coordinate picking
const mapContainer = ref<HTMLDivElement | null>(null);
let pickerMap: any = null;
let pickerMarker: any = null;

const initPickerMap = async () => {
    if (typeof window === 'undefined') return;
    if (!mapContainer.value) return;
    if (pickerMap) return;

    const L = await import('leaflet');

    pickerMap = L.map(mapContainer.value, { zoomControl: true }).setView([-2.5489, 118.0149], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
    }).addTo(pickerMap);

    const initLat = props.item?.latitude != null && props.item?.latitude !== ''
        ? Number(props.item.latitude)
        : null;
    const initLng = props.item?.longitude != null && props.item?.longitude !== ''
        ? Number(props.item.longitude)
        : null;

    if (initLat != null && initLng != null && Number.isFinite(initLat) && Number.isFinite(initLng)) {
        pickerMarker = L.marker([initLat, initLng], { draggable: true }).addTo(pickerMap);
        pickerMarker.on('dragend', () => {
            const pos = pickerMarker.getLatLng();
            form.latitude = String(pos.lat.toFixed(6));
            form.longitude = String(pos.lng.toFixed(6));
        });
        pickerMap.setView([initLat, initLng], 12);
    }

    pickerMap.on('click', (e: any) => {
        const { lat, lng } = e.latlng;
        form.latitude = String(lat.toFixed(6));
        form.longitude = String(lng.toFixed(6));
        if (pickerMarker) {
            pickerMarker.setLatLng([lat, lng]);
        } else {
            pickerMarker = L.marker([lat, lng], { draggable: true }).addTo(pickerMap);
            pickerMarker.on('dragend', () => {
                const pos = pickerMarker.getLatLng();
                form.latitude = String(pos.lat.toFixed(6));
                form.longitude = String(pos.lng.toFixed(6));
            });
        }
    });

    setTimeout(() => pickerMap?.invalidateSize(), 300);
};

// Wilayah cascade — preloaded via Inertia props, client-side filtering (crime-map pattern)
const provinceId = ref('');
const regencyId = ref('');
const districtId = ref('');

const filteredRegencies = computed(() => {
    if (!provinceId.value) return [];
    return (props.regencyList ?? []).filter((r) => String(r.province_id) === String(provinceId.value));
});

const filteredDistricts = computed(() => {
    if (!regencyId.value) return [];
    return (props.districtList ?? []).filter((d) => String(d.regency_id) === String(regencyId.value));
});

// Watchers: map selected ID → form name string, reset children
watch(provinceId, (val) => {
    form.provinsi = (props.provinceList ?? []).find((x) => String(x.id) === String(val))?.name ?? '';
    regencyId.value = '';
    districtId.value = '';
    form.kabupaten_kota = '';
    form.kecamatan = '';
});

watch(regencyId, (val) => {
    form.kabupaten_kota = filteredRegencies.value.find((x) => String(x.id) === String(val))?.name ?? '';
    districtId.value = '';
    form.kecamatan = '';
});

watch(districtId, (val) => {
    form.kecamatan = filteredDistricts.value.find((x) => String(x.id) === String(val))?.name ?? '';
});

onMounted(async () => {
    await nextTick();
    await initPickerMap();

    if (isEdit.value && props.item) {
        const prov = (props.provinceList ?? []).find((p) => p.name === props.item?.provinsi);
        if (prov) {
            provinceId.value = String(prov.id);
            const reg = (props.regencyList ?? []).find(
                (r) => r.name === props.item?.kabupaten_kota && String(r.province_id) === String(prov.id),
            );
            if (reg) {
                regencyId.value = String(reg.id);
                const dist = (props.districtList ?? []).find(
                    (d) => d.name === props.item?.kecamatan && String(d.regency_id) === String(reg.id),
                );
                if (dist) districtId.value = String(dist.id);
            }
        }
    }
});

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false }),
    ],
    content: String(form.description ?? ''),
    editorProps: {
        attributes: {
            class: 'min-h-[500px] px-3 py-2 text-sm text-sky-200/85 outline-none prose prose-invert prose-sm max-w-none',
        },
    },
    editable: !isView.value,
    onUpdate: ({ editor: ed }) => {
        form.description = ed.getHTML();
    },
});

// Toolbar helpers
function setLink() {
    if (!editor.value) return;
    const prev = editor.value.getAttributes('link').href;
    const url = window.prompt('URL:', prev ?? 'https://');
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
}

const filteredSubCategories = computed(() => {
    const cat = localCategory.value;
    if (!cat) return [];
    return subCategoryMap[cat] ?? [];
});

function onCategoryChange(v: string) {
    localCategory.value = v;
    localSubCategory.value = '';
}

const title = computed(() => {
    if (isCreate.value) return 'IPOLEKSOSBUDKAM / create';
    if (isEdit.value) return 'IPOLEKSOSBUDKAM / edit';
    if (isView.value) return 'IPOLEKSOSBUDKAM / detail';
    return 'IPOLEKSOSBUDKAM / local';
});

// Mencegah Enter di input teks memicu implicit form submit (submit tak sengaja)
function onFormKeydown(e: KeyboardEvent) {
    if (e.key !== 'Enter') return;
    const tag = (e.target as HTMLElement)?.tagName;
    if (tag === 'INPUT') e.preventDefault();
}

const submit = () => {
    form.category = localCategory.value || null;
    form.sub_category = localSubCategory.value || null;

    // Transform form data for Inertia - file uploads need FormData
    const payload: Record<string, any> = {
        ...form.data(),
    };

    if (galleryFiles.value.length > 0) {
        payload.gallery_files = galleryFiles.value;
    }
    if (isEdit.value) {
        payload.keep_gallery = keepGalleryPaths.value;
    }

    if (isEdit.value && props.item) {
        form.transform(() => payload).put(`/ipoleksosbudkam-local/${props.item.id}`, {
            preserveScroll: true,
        });
    } else {
        form.transform(() => payload).post('/ipoleksosbudkam-local', {
            preserveScroll: true,
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
            <form v-if="!isView" @submit.prevent="submit" @keydown="onFormKeydown" class="space-y-4">
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
                    <div v-if="editor" class="mt-1 rounded-md border border-sky-500/25 bg-black/40 overflow-hidden">
                        <!-- Toolbar -->
                        <div class="flex flex-wrap items-center gap-0.5 border-b border-sky-500/20 px-2 py-1.5 bg-black/30">
                            <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="editor.isActive('bold') ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs font-bold transition" title="Bold">B</button>
                            <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="editor.isActive('italic') ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs italic transition" title="Italic">I</button>
                            <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="editor.isActive('underline') ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs underline transition" title="Underline">U</button>
                            <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="editor.isActive('strike') ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs line-through transition" title="Strikethrough">S</button>
                            <span class="mx-1 text-sky-500/30">|</span>
                            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" :class="editor.isActive('heading', { level: 1 }) ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs font-bold transition" title="Heading 1">H1</button>
                            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="editor.isActive('heading', { level: 2 }) ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs font-bold transition" title="Heading 2">H2</button>
                            <span class="mx-1 text-sky-500/30">|</span>
                            <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="editor.isActive('bulletList') ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs transition" title="Bullet List">•</button>
                            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="editor.isActive('orderedList') ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs transition" title="Ordered List">1.</button>
                            <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="editor.isActive('blockquote') ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs transition" title="Blockquote">"</button>
                            <span class="mx-1 text-sky-500/30">|</span>
                            <button type="button" @click="setLink" :class="editor.isActive('link') ? 'bg-sky-500/30 text-sky-100' : 'text-sky-400 hover:text-sky-200 hover:bg-sky-500/15'" class="rounded px-1.5 py-0.5 text-xs transition" title="Link">🔗</button>
                            <button type="button" @click="editor.chain().focus().unsetLink().run()" :disabled="!editor.isActive('link')" class="rounded px-1.5 py-0.5 text-xs text-sky-500/40 transition disabled:opacity-30 hover:text-sky-300" title="Remove Link">✕</button>
                        </div>
                        <EditorContent :editor="editor" />
                    </div>
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Gallery -->
                <div>
                    <Label class="text-sky-200">Galeri</Label>
                    <div class="mt-1 space-y-2">
                        <!-- Existing images (edit mode) -->
                        <div v-if="existingGallery.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                            <div
                                v-for="(img, i) in existingGallery"
                                :key="img.path"
                                class="group relative overflow-hidden rounded-md border border-sky-500/20 bg-black/30"
                            >
                                <img
                                    :src="img.url"
                                    class="aspect-square w-full object-cover"
                                />
                                <button
                                    type="button"
                                    @click="removeExisting(i)"
                                    class="absolute right-1 top-1 rounded-full bg-red-600/80 p-0.5 text-white opacity-0 transition group-hover:opacity-100"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                        <!-- New file previews -->
                        <div v-if="galleryFiles.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                            <div
                                v-for="(file, i) in galleryFiles"
                                :key="i"
                                class="group relative overflow-hidden rounded-md border border-sky-500/20 bg-black/30"
                            >
                                <img
                                    :src="previewUrl(file)"
                                    class="aspect-square w-full object-cover"
                                />
                                <button
                                    type="button"
                                    @click="removeNewFile(i)"
                                    class="absolute right-1 top-1 rounded-full bg-red-600/80 p-0.5 text-white opacity-0 transition group-hover:opacity-100"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                        <!-- Upload button -->
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-dashed border-sky-500/30 px-3 py-2 text-sm text-sky-400 transition hover:border-sky-400/50 hover:text-sky-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Tambah Gambar
                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                class="hidden"
                                @change="onGalleryFilesChange"
                            />
                        </label>
                    </div>
                    <InputError :message="form.errors.gallery_files" />
                    <InputError :message="form.errors['gallery_files.0']" />
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
                        <Select :model-value="localCategory" @update:model-value="onCategoryChange($event as string)">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100">
                                <SelectValue placeholder="Pilih kategori..." />
                            </SelectTrigger>
                            <SelectContent class="border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem v-for="c in categoryOptions" :key="c.value" :value="c.value">
                                    {{ c.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.category" />
                    </div>
                    <div>
                        <Label class="text-sky-200">Sub Kategori</Label>
                        <Select :model-value="localSubCategory" :disabled="!localCategory" @update:model-value="(v: string) => localSubCategory = v">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100">
                                <SelectValue placeholder="Pilih sub kategori..." />
                            </SelectTrigger>
                            <SelectContent class="border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem v-for="sc in filteredSubCategories" :key="sc.value" :value="sc.value">
                                    {{ sc.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.sub_category" />
                    </div>
                </div>

                <!-- Leaflet Map for coordinates -->
                <div>
                    <Label class="text-sky-200">Lokasi (klik peta)</Label>
                    <div
                        ref="mapContainer"
                        class="mt-1 h-[280px] w-full rounded-lg border border-sky-500/25"
                    />
                    <div class="mt-2 flex gap-4 text-sm text-sky-300">
                        <span>Lat: {{ form.latitude || '-' }}</span>
                        <span>Lng: {{ form.longitude || '-' }}</span>
                    </div>
                    <InputError :message="form.errors.latitude" />
                    <InputError :message="form.errors.longitude" />
                </div>

                <!-- Row: provinsi + kab/kota + kec -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label class="text-sky-200">Provinsi</Label>
                        <Select v-model="provinceId" :disabled="isView">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100 text-sm">
                                <SelectValue placeholder="Pilih provinsi..." />
                            </SelectTrigger>
                            <SelectContent class="max-h-[200px] border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem v-for="p in (provinceList ?? [])" :key="p.id" :value="p.id">
                                    {{ p.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label class="text-sky-200">Kabupaten / Kota</Label>
                        <Select v-model="regencyId" :disabled="isView || !provinceId">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100 text-sm">
                                <SelectValue placeholder="Pilih kab/kota..." />
                            </SelectTrigger>
                            <SelectContent class="max-h-[200px] border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem v-for="r in filteredRegencies" :key="r.id" :value="r.id">
                                    {{ r.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label class="text-sky-200">Kecamatan</Label>
                        <Select v-model="districtId" :disabled="isView || !regencyId">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100 text-sm">
                                <SelectValue placeholder="Pilih kecamatan..." />
                            </SelectTrigger>
                            <SelectContent class="max-h-[200px] border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem v-for="d in filteredDistricts" :key="d.id" :value="d.id">
                                    {{ d.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Row: source + sumber_berita -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label class="text-sky-200">Source</Label>
                        <Select v-model="form.source">
                            <SelectTrigger class="mt-1 w-full border-sky-500/25 bg-black/40 text-sky-100">
                                <SelectValue placeholder="Pilih source..." />
                            </SelectTrigger>
                            <SelectContent class="border-sky-500/25 bg-black/90 text-sky-100">
                                <SelectItem v-for="s in sourceOptions" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
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
                <div v-if="item.description" class="text-sky-300/80 prose prose-invert prose-sm max-w-none" v-html="item.description" />

                <!-- Gallery view mode -->
                <div v-if="item.gallery?.length" class="space-y-2">
                    <div class="text-sm tracking-widest text-sky-300">GALLERY</div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                        <div
                            v-for="img in item.gallery"
                            :key="img.path"
                            class="overflow-hidden rounded-md border border-sky-500/15 bg-black/20"
                        >
                            <div class="relative w-full overflow-hidden bg-black/35 [aspect-ratio:4/3]">
                                <img
                                    :src="img.url"
                                    :alt="img.path"
                                    class="absolute inset-0 h-full w-full object-contain p-2 opacity-95"
                                />
                            </div>
                        </div>
                    </div>
                </div>

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
