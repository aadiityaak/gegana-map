<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { CircleMarker, Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TiptapLink from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
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
type Mode = 'create' | 'edit' | 'view';

const props = defineProps<{
    mode: Mode;
    item: {
        id: number;
        incident_type: string;
        finding_type: string | null;
        description?: string | null;
        photos?: string[] | null;
        latitude?: number | null;
        longitude?: number | null;
        news_source?: string | null;
        news_url?: string | null;
        province_id: string;
        regency_id: string;
        district_id: string;
        village_id: string;
        created_at?: string | null;
        province_name?: string | null;
        regency_name?: string | null;
        district_name?: string | null;
        village_name?: string | null;
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

const isView = computed(() => props.mode === 'view');
const viewTypeLabel = computed(() => {
    const value = String(props.item?.incident_type ?? form.incident_type ?? '');
    return incidentTypes.find((t) => t.value === value)?.label ?? value;
});

const viewFindingLabel = computed(() => {
    const value = props.item?.finding_type;
    if (!value) return '';
    return findingTypes.find((t) => t.value === value)?.label ?? String(value);
});

const viewLocationLabel = computed(() => {
    const parts = [
        props.item?.village_name,
        props.item?.district_name,
        props.item?.regency_name,
        props.item?.province_name,
    ]
        .filter(Boolean)
        .map((v) => String(v));
    if (parts.length > 0) return parts.join(', ');
    const ids = [
        props.item?.village_id,
        props.item?.district_id,
        props.item?.regency_id,
        props.item?.province_id,
    ]
        .filter(Boolean)
        .map((v) => String(v));
    return ids.join(', ');
});

const formatDateTime = (value: string | null | undefined) => {
    if (!value) return '';
    const d = new Date(value);
    if (!Number.isFinite(d.getTime())) return String(value);
    return d.toLocaleString('id-ID', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const viewCoords = computed(() => {
    const lat = parseCoordNumber(form.latitude);
    const lng = parseCoordNumber(form.longitude);
    if (lat == null || lng == null) return null;
    return { lat, lng };
});

const form = useForm({
    incident_type: props.item?.incident_type ?? initialType.value,
    finding_type: props.item?.finding_type ?? '',
    description: props.item?.description ?? '',
    existing_photos: props.item?.photos ?? [],
    photos: [] as File[],
    latitude: props.item?.latitude ?? null,
    longitude: props.item?.longitude ?? null,
    news_source: props.item?.news_source ?? 'offline',
    news_url: props.item?.news_url ?? '',
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
const isOnlineSource = computed(() => form.news_source === 'online');

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        TiptapLink.configure({
            openOnClick: false,
            autolink: true,
            linkOnPaste: true,
        }),
    ],
    content: String(form.description ?? ''),
    editorProps: {
        attributes: {
            class: 'min-h-[140px] px-3 py-2 text-sm text-green-200/85 outline-none',
        },
    },
    editable: props.mode !== 'view',
    onUpdate: ({ editor }) => {
        form.description = editor.getHTML();
    },
});

const getEditor = () => ((editor as any)?.value ?? editor) as any;

const setLink = () => {
    const url = window.prompt('URL');
    if (!url) return;
    const e = getEditor();
    e?.chain?.().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const photoInputEl = ref<HTMLInputElement | null>(null);
const isDraggingPhotos = ref(false);
const newPhotoPreviews = ref<Array<{ key: string; file: File; url: string }>>([]);

const fileKey = (f: File) => `${f.name}|${f.size}|${f.lastModified}`;

const syncPhotosToForm = () => {
    form.photos = newPhotoPreviews.value.map((p) => p.file);
};

const addPhotoFiles = (files: File[]) => {
    const incoming = files.filter((f) => f && f.type.startsWith('image/'));
    if (incoming.length === 0) return;

    const existingKeys = new Set(newPhotoPreviews.value.map((p) => p.key));
    for (const f of incoming) {
        const key = fileKey(f);
        if (existingKeys.has(key)) continue;
        newPhotoPreviews.value.push({ key, file: f, url: URL.createObjectURL(f) });
        existingKeys.add(key);
    }
    syncPhotosToForm();
};

const onPhotosChange = (e: Event) => {
    if (isView.value) return;
    const input = e.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];
    addPhotoFiles(files);
    input.value = '';
};

const openPhotoPicker = () => {
    if (isView.value) return;
    photoInputEl.value?.click();
};

const onPhotosDrop = (e: DragEvent) => {
    if (isView.value) return;
    e.preventDefault();
    isDraggingPhotos.value = false;
    const files = e.dataTransfer?.files ? Array.from(e.dataTransfer.files) : [];
    addPhotoFiles(files);
};

const removeNewPhoto = (key: string) => {
    if (isView.value) return;
    const idx = newPhotoPreviews.value.findIndex((p) => p.key === key);
    if (idx < 0) return;
    const [removed] = newPhotoPreviews.value.splice(idx, 1);
    if (removed?.url) URL.revokeObjectURL(removed.url);
    syncPhotosToForm();
};

const removeExistingPhoto = (path: string) => {
    if (isView.value) return;
    form.existing_photos = (form.existing_photos ?? []).filter((p: string) => p !== path);
};

const photoUrl = (path: string) => `/storage/${path}`;

const photosError = computed(() => form.errors.photos || form.errors['photos.0'] || '');

const getLeaflet = async () => {
    const mod = await import('leaflet');
    return mod.default;
};

const mapContainer = ref<HTMLDivElement | null>(null);
let map: LeafletMap | null = null;
let coordMarker: CircleMarker | null = null;

const parseCoordNumber = (value: unknown): number | null => {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed === '') return null;
        const n = Number.parseFloat(trimmed);
        return Number.isFinite(n) ? n : null;
    }
    return null;
};

const updateCoordMarker = async () => {
    if (!map) return;
    const lat = parseCoordNumber(form.latitude);
    const lng = parseCoordNumber(form.longitude);
    if (lat == null || lng == null) {
        if (coordMarker) {
            coordMarker.remove();
            coordMarker = null;
        }
        return;
    }

    const L = await getLeaflet();
    if (!coordMarker) {
        coordMarker = L.circleMarker([lat, lng], {
            radius: 8,
            color: '#22c55e',
            fillColor: '#22c55e',
            fillOpacity: 0.55,
            weight: 2,
        }).addTo(map);
    } else {
        coordMarker.setLatLng([lat, lng]);
    }
};

const setCoords = async (lat: number, lng: number) => {
    form.latitude = Number(lat.toFixed(6));
    form.longitude = Number(lng.toFixed(6));
    await updateCoordMarker();
};

const ensureMap = async () => {
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
    const lat = parseCoordNumber(form.latitude);
    const lng = parseCoordNumber(form.longitude);
    const initialCenter: [number, number] =
        lat != null && lng != null ? [lat, lng] : [-2.5489, 118.0149];
    const initialZoom = lat != null && lng != null ? 12 : 5;

    map = L.map(mapContainer.value, { zoomControl: true }).setView(
        initialCenter,
        initialZoom,
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
    }).addTo(map);

    map.on('click', async (e: any) => {
        if (isView.value) return;
        const clickedLat = Number(e?.latlng?.lat);
        const clickedLng = Number(e?.latlng?.lng);
        if (!Number.isFinite(clickedLat) || !Number.isFinite(clickedLng)) return;
        await setCoords(clickedLat, clickedLng);
    });

    await updateCoordMarker();

    setTimeout(() => {
        map?.invalidateSize?.();
    }, 0);
};

onBeforeUnmount(() => {
    for (const p of newPhotoPreviews.value) {
        URL.revokeObjectURL(p.url);
    }
    getEditor()?.destroy?.();
    map?.remove();
    map = null;
    coordMarker = null;
});

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
    () => form.news_source,
    (value) => {
        if (value !== 'online') {
            form.news_url = '';
        }
    },
);

watch(
    () => [form.latitude, form.longitude] as const,
    async () => {
        await updateCoordMarker();
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
    if (!isView.value) {
        await loadProvinces();
        if (form.province_id) await loadRegencies(form.province_id);
        if (form.regency_id) await loadDistricts(form.regency_id);
        if (form.district_id) await loadVillages(form.district_id);
        getEditor()?.commands?.setContent(String(form.description ?? ''), false);
        await ensureMap();
        return;
    }

    getEditor()?.commands?.setContent(String(form.description ?? ''), false);
    if (viewCoords.value) {
        await ensureMap();
    }
});

const submit = () => {
    if (props.mode === 'view') return;
    if (props.mode === 'edit' && props.item) {
        form.put(`/jibom/${props.item.id}`, { forceFormData: true });
        return;
    }
    form.post('/jibom', { forceFormData: true });
};

watch(isView, (v) => {
    getEditor()?.setEditable?.(!v);
});

const title = computed(() => {
    if (props.mode === 'view') return 'View JIBOM';
    return props.mode === 'edit' ? 'Edit JIBOM' : 'Tambah JIBOM';
});
</script>

<template>
    <Head :title="title" />

    <div class="p-4 font-mono sm:p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > {{
                        props.mode === 'view'
                            ? 'VIEW JIBOM'
                            : props.mode === 'edit'
                              ? 'EDIT JIBOM'
                              : 'TAMBAH JIBOM'
                    }}
                </h1>
                <Badge class="border border-green-500/25 bg-black/30 text-green-200">
                    {{ form.incident_type }}
                </Badge>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="secondary" as-child>
                    <Link href="/jibom">Back</Link>
                </Button>
                <Button v-if="props.mode === 'view' && props.item" variant="secondary" as-child>
                    <Link :href="`/jibom/${props.item.id}/edit`">Edit</Link>
                </Button>
            </div>
        </div>

        <div v-if="isView" class="space-y-4">
            <div class="rounded-xl border border-green-500/15 bg-black/30 p-4">
                <div class="text-sm font-semibold text-green-100">
                    > {{ viewTypeLabel }}
                </div>
                <div class="mt-2 text-xs text-green-300/60">
                    {{ formatDateTime(String(props.item?.created_at ?? '')) }} · {{ viewLocationLabel }}
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Badge class="border border-green-500/25 bg-black/35 text-green-200">
                        {{ form.incident_type }}
                    </Badge>
                    <Badge v-if="viewFindingLabel" class="border border-green-500/25 bg-black/30 text-green-200">
                        {{ viewFindingLabel }}
                    </Badge>
                    <Badge class="border border-green-500/25 bg-green-500/10 text-green-200">
                        {{ String(form.news_source || 'offline') }}
                    </Badge>
                </div>
            </div>

            <div class="rounded-xl border border-green-500/15 bg-black/20 p-3">
                <div class="mb-2 flex items-center justify-between text-xs text-green-300/60">
                    <span>> MAP LOCATION</span>
                    <span v-if="viewCoords" class="text-[11px]">
                        > {{ viewCoords.lat.toFixed(5) }}, {{ viewCoords.lng.toFixed(5) }}
                    </span>
                </div>
                <div
                    v-if="viewCoords"
                    ref="mapContainer"
                    class="relative z-0 h-[360px] w-full overflow-hidden rounded-lg border border-green-500/15"
                />
                <div v-else class="rounded-lg border border-green-500/15 bg-black/30 p-4 text-xs text-green-300/60">
                    > koordinat tidak tersedia.
                </div>
            </div>

            <div
                v-if="props.item?.description"
                class="rounded-xl border border-green-500/15 bg-black/20 p-4 text-sm text-green-200/85"
                v-html="String(props.item?.description ?? '')"
            />

            <div class="grid gap-2 rounded-xl border border-green-500/15 bg-black/20 p-4 text-xs text-green-300/70">
                <div>> wilayah: {{ viewLocationLabel }}</div>
                <div v-if="form.news_source">> sumber_berita: {{ form.news_source }}</div>
                <div v-if="form.news_url">
                    > url:
                    <a
                        :href="String(form.news_url)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ml-1 text-green-200 underline decoration-green-400/40 underline-offset-4 hover:text-green-100"
                    >
                        {{ String(form.news_url) }}
                    </a>
                </div>
            </div>

            <div v-if="(props.item?.photos ?? []).length" class="space-y-2">
                <div class="text-xs tracking-widest text-green-300/60">GALLERY</div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="p in (props.item?.photos ?? [])"
                        :key="p"
                        class="overflow-hidden rounded-md border border-green-500/15 bg-black/20"
                    >
                        <div class="relative w-full overflow-hidden bg-black/35 [aspect-ratio:4/3]">
                            <img
                                :src="photoUrl(p)"
                                :alt="p"
                                class="absolute inset-0 h-full w-full object-contain p-2 opacity-95"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="space-y-6 rounded-xl border border-green-500/15 bg-black/20 p-4">
            <div class="grid gap-2">
                <Label>Jenis</Label>
                <Select v-model="form.incident_type">
                    <SelectTrigger class="w-full" :disabled="isView">
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
                    <SelectTrigger class="w-full" :disabled="isView">
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

            <div class="grid gap-2">
                <Label>Deskripsi</Label>
                <div class="rounded-lg border border-green-500/15 bg-black/30">
                    <div v-if="!isView" class="flex flex-wrap gap-2 border-b border-green-500/15 p-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="getEditor()?.chain?.().focus().toggleBold().run()"
                        >
                            Bold
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="getEditor()?.chain?.().focus().toggleItalic().run()"
                        >
                            Italic
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="getEditor()?.chain?.().focus().toggleUnderline().run()"
                        >
                            Underline
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="getEditor()?.chain?.().focus().toggleBulletList().run()"
                        >
                            UL
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="getEditor()?.chain?.().focus().toggleOrderedList().run()"
                        >
                            OL
                        </Button>
                        <Button type="button" size="sm" variant="secondary" @click="setLink">
                            Link
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="getEditor()?.chain?.().focus().unsetAllMarks().clearNodes().run()"
                        >
                            Clear
                        </Button>
                    </div>
                    <EditorContent :editor="editor" />
                </div>
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-2">
                <Label>Gallery Foto</Label>
                <div v-if="isView">
                    <div v-if="(form.existing_photos ?? []).length > 0" class="grid gap-2">
                        <div class="text-xs text-green-300/60">> foto tersimpan</div>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                            <div
                                v-for="p in (form.existing_photos ?? [])"
                                :key="p"
                                class="overflow-hidden rounded-lg border border-green-500/15 bg-black/30"
                            >
                                <img :src="photoUrl(p)" class="h-24 w-full object-cover" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-green-300/60">> tidak ada foto.</div>
                </div>
                <input
                    ref="photoInputEl"
                    type="file"
                    multiple
                    accept="image/*"
                    class="hidden"
                    @change="onPhotosChange"
                />
                <button
                    v-if="!isView"
                    type="button"
                    class="w-full rounded-lg border border-dashed border-green-500/25 bg-black/30 px-3 py-6 text-left text-xs text-green-200/85"
                    :class="isDraggingPhotos ? 'bg-green-500/10' : ''"
                    @click="openPhotoPicker"
                    @dragenter.prevent="isDraggingPhotos = true"
                    @dragover.prevent="isDraggingPhotos = true"
                    @dragleave.prevent="isDraggingPhotos = false"
                    @drop="onPhotosDrop"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="text-green-200/90">> drag & drop foto di sini</div>
                            <div class="mt-1 text-green-300/60">> atau klik untuk pilih file</div>
                        </div>
                        <div v-if="newPhotoPreviews.length > 0" class="rounded border border-green-500/15 bg-black/20 px-2 py-1 text-[11px] text-green-200/85">
                            > baru: {{ newPhotoPreviews.length }}
                        </div>
                    </div>
                </button>
                <InputError :message="photosError" />

                <div v-if="!isView && (form.existing_photos ?? []).length > 0" class="grid gap-2">
                    <div class="text-xs text-green-300/60">> foto tersimpan</div>
                    <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                        <button
                            v-for="p in (form.existing_photos ?? [])"
                            :key="p"
                            type="button"
                            class="group relative overflow-hidden rounded-lg border border-green-500/15 bg-black/30"
                            @click="removeExistingPhoto(p)"
                        >
                            <img :src="photoUrl(p)" class="h-24 w-full object-cover" />
                            <div class="absolute inset-x-0 bottom-0 bg-black/60 px-2 py-1 text-[11px] text-green-200/85">
                                > remove
                            </div>
                        </button>
                    </div>
                </div>

                <div v-if="!isView && newPhotoPreviews.length > 0" class="grid gap-2">
                    <div class="text-xs text-green-300/60">> preview foto baru</div>
                    <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                        <button
                            v-for="p in newPhotoPreviews"
                            :key="p.key"
                            type="button"
                            class="group relative overflow-hidden rounded-lg border border-green-500/15 bg-black/30"
                            @click="removeNewPhoto(p.key)"
                        >
                            <img :src="p.url" class="h-24 w-full object-cover" />
                            <div class="absolute inset-x-0 bottom-0 bg-black/60 px-2 py-1 text-[11px] text-green-200/85">
                                > remove
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Provinsi</Label>
                    <Select v-model="form.province_id">
                        <SelectTrigger class="w-full" :disabled="isView">
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
                    <Select v-model="form.regency_id" :disabled="isView || !form.province_id || loadingRegencies">
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
                    <Select v-model="form.district_id" :disabled="isView || !form.regency_id || loadingDistricts">
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
                    <Select v-model="form.village_id" :disabled="isView || !form.district_id || loadingVillages">
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

            <div class="grid gap-2">
                <Label>Koordinat</Label>
                <div ref="mapContainer" class="h-[260px] w-full overflow-hidden rounded-lg border border-green-500/15 bg-black/30" />
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Latitude</Label>
                        <input
                            v-model.number="form.latitude"
                            type="number"
                            step="0.000001"
                            class="h-10 w-full rounded-md border border-green-500/15 bg-black/30 px-3 text-sm text-green-200/85 outline-none"
                            placeholder="-6.200000"
                            :disabled="isView"
                        />
                        <InputError :message="form.errors.latitude" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Longitude</Label>
                        <input
                            v-model.number="form.longitude"
                            type="number"
                            step="0.000001"
                            class="h-10 w-full rounded-md border border-green-500/15 bg-black/30 px-3 text-sm text-green-200/85 outline-none"
                            placeholder="106.816666"
                            :disabled="isView"
                        />
                        <InputError :message="form.errors.longitude" />
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Sumber Berita</Label>
                    <Select v-model="form.news_source">
                        <SelectTrigger class="w-full" :disabled="isView">
                            <SelectValue placeholder="Pilih sumber" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="offline">Offline</SelectItem>
                            <SelectItem value="online">Online</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.news_source" />
                </div>

                <div v-if="isOnlineSource" class="grid gap-2">
                    <Label>URL Sumber</Label>
                    <input
                        v-model="form.news_url"
                        type="url"
                        class="h-10 w-full rounded-md border border-green-500/15 bg-black/30 px-3 text-sm text-green-200/85 outline-none"
                        placeholder="https://..."
                        :disabled="isView"
                    />
                    <InputError :message="form.errors.news_url" />
                </div>
            </div>

            <div v-if="!isView" class="flex flex-wrap items-center gap-2">
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
