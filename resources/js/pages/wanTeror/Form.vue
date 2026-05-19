<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { CircleMarker, Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
    } | null;
    filters?: { type?: string | null };
}>();

const incidentTypes = [
    { value: 'napiter', label: 'Data Napiter' },
    { value: 'ex-napiter', label: 'Data EX Napiter' },
    { value: 'jaringan-terorisme', label: 'Jaringan Terorisme' },
    { value: 'bullying-perundungan', label: 'Bullying/Perundungan' },
    { value: 'aksi-teror', label: 'Aksi Teror' },
];

const initialType = computed(() => {
    if (props.item) return props.item.incident_type;
    const t = props.filters?.type;
    if (
        t === 'napiter' ||
        t === 'ex-napiter' ||
        t === 'jaringan-terorisme' ||
        t === 'bullying-perundungan' ||
        t === 'aksi-teror'
    ) {
        return t;
    }
    return 'napiter';
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

const isOnlineSource = computed(() => form.news_source === 'online');

const editorEl = ref<HTMLDivElement | null>(null);

const syncDescriptionFromEditor = () => {
    form.description = editorEl.value?.innerHTML ?? '';
};

const exec = (command: string, value?: string) => {
    editorEl.value?.focus();
    document.execCommand(command, false, value);
    syncDescriptionFromEditor();
};

const addLink = () => {
    const url = window.prompt('URL');
    if (!url) return;
    exec('createLink', url);
};

const photoInputEl = ref<HTMLInputElement | null>(null);
const isDraggingPhotos = ref(false);
const newPhotoPreviews = ref<Array<{ key: string; file: File; url: string }>>(
    [],
);

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
        newPhotoPreviews.value.push({
            key,
            file: f,
            url: URL.createObjectURL(f),
        });
        existingKeys.add(key);
    }
    syncPhotosToForm();
};

const onPhotosChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];
    addPhotoFiles(files);
    input.value = '';
};

const openPhotoPicker = () => {
    photoInputEl.value?.click();
};

const onPhotosDrop = (e: DragEvent) => {
    e.preventDefault();
    isDraggingPhotos.value = false;
    const files = e.dataTransfer?.files ? Array.from(e.dataTransfer.files) : [];
    addPhotoFiles(files);
};

const removeNewPhoto = (key: string) => {
    const idx = newPhotoPreviews.value.findIndex((p) => p.key === key);
    if (idx < 0) return;
    const [removed] = newPhotoPreviews.value.splice(idx, 1);
    if (removed?.url) URL.revokeObjectURL(removed.url);
    syncPhotosToForm();
};

const removeExistingPhoto = (path: string) => {
    form.existing_photos = (form.existing_photos ?? []).filter(
        (p: string) => p !== path,
    );
};

const photoUrl = (path: string) => `/storage/${path}`;

const photosError = computed(
    () => form.errors.photos || form.errors['photos.0'] || '',
);

const getLeaflet = async () => {
    const mod = await import('leaflet');
    return mod.default;
};

const mapContainer = ref<HTMLDivElement | null>(null);
let map: LeafletMap | null = null;
let coordMarker: CircleMarker | null = null;

onBeforeUnmount(() => {
    for (const p of newPhotoPreviews.value) {
        URL.revokeObjectURL(p.url);
    }
    map?.remove();
    map = null;
    coordMarker = null;
});

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

onMounted(async () => {
    await loadProvinces();
    if (form.province_id) await loadRegencies(form.province_id);
    if (form.regency_id) await loadDistricts(form.regency_id);
    if (form.district_id) await loadVillages(form.district_id);
    if (editorEl.value) {
        editorEl.value.innerHTML = String(form.description ?? '');
    }
    await ensureMap();
});

const submit = () => {
    syncDescriptionFromEditor();
    if (props.mode === 'edit' && props.item) {
        form.put(`/wan-teror/${props.item.id}`, { forceFormData: true });
        return;
    }
    form.post('/wan-teror', { forceFormData: true });
};

const title = computed(() =>
    props.mode === 'edit' ? 'Edit WAN TEROR' : 'Tambah WAN TEROR',
);
</script>

<template>
    <Head :title="title" />

    <div class="p-4 font-mono sm:p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold tracking-widest text-green-200">
                    > {{ props.mode === 'edit' ? 'EDIT WAN TEROR' : 'TAMBAH WAN TEROR' }}
                </h1>
                <Badge class="border border-green-500/25 bg-black/30 text-green-200">
                    {{ form.incident_type }}
                </Badge>
            </div>
            <Button variant="secondary" as-child>
                <Link href="/wan-teror">Back</Link>
            </Button>
        </div>

        <div class="space-y-6 rounded-xl border border-green-500/15 bg-black/20 p-4">
            <div class="grid gap-2">
                <Label>Kategori</Label>
                <Select v-model="form.incident_type">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Pilih kategori" />
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

            <div class="grid gap-2">
                <Label>Catatan (opsional)</Label>
                <input
                    v-model="form.finding_type"
                    type="text"
                    class="h-10 w-full rounded-md border border-green-500/15 bg-black/30 px-3 text-sm text-green-200/85 outline-none"
                    placeholder="misal: kode kasus / keterangan singkat"
                />
                <InputError :message="form.errors.finding_type" />
            </div>

            <div class="grid gap-2">
                <Label>Deskripsi</Label>
                <div class="rounded-lg border border-green-500/15 bg-black/30">
                    <div class="flex flex-wrap gap-2 border-b border-green-500/15 p-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="exec('bold')"
                        >
                            Bold
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="exec('italic')"
                        >
                            Italic
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="exec('underline')"
                        >
                            Underline
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="exec('insertUnorderedList')"
                        >
                            UL
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="exec('insertOrderedList')"
                        >
                            OL
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="addLink"
                        >
                            Link
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            @click="exec('removeFormat')"
                        >
                            Clear
                        </Button>
                    </div>
                    <div
                        ref="editorEl"
                        class="min-h-[140px] px-3 py-2 text-sm text-green-200/85 outline-none"
                        contenteditable
                        @input="syncDescriptionFromEditor"
                        @blur="syncDescriptionFromEditor"
                    />
                </div>
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-2">
                <Label>Gallery Foto</Label>
                <input
                    ref="photoInputEl"
                    type="file"
                    multiple
                    accept="image/*"
                    class="hidden"
                    @change="onPhotosChange"
                />
                <button
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
                        <div
                            v-if="newPhotoPreviews.length > 0"
                            class="rounded border border-green-500/15 bg-black/20 px-2 py-1 text-[11px] text-green-200/85"
                        >
                            > baru: {{ newPhotoPreviews.length }}
                        </div>
                    </div>
                </button>
                <InputError :message="photosError" />

                <div v-if="(form.existing_photos ?? []).length > 0" class="grid gap-2">
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

                <div v-if="newPhotoPreviews.length > 0" class="grid gap-2">
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
                    <Select
                        v-model="form.regency_id"
                        :disabled="!form.province_id || loadingRegencies"
                    >
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
                    <Select
                        v-model="form.district_id"
                        :disabled="!form.regency_id || loadingDistricts"
                    >
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
                    <Select
                        v-model="form.village_id"
                        :disabled="!form.district_id || loadingVillages"
                    >
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
                        />
                        <InputError :message="form.errors.longitude" />
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Sumber Berita</Label>
                    <Select v-model="form.news_source">
                        <SelectTrigger class="w-full">
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
                    />
                    <InputError :message="form.errors.news_url" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" :disabled="form.processing" @click="submit">
                    Save
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="form.processing"
                    as-child
                >
                    <Link href="/wan-teror">Cancel</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
