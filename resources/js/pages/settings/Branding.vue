<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Branding settings',
                href: '/settings/branding',
            },
        ],
    },
});

interface ItemGambar {
    path: string;
    nama: string;
    sumber: string;
    url: string;
    ukuran: number;
    ukuran_human: string;
    diubah: string;
    aktif: boolean;
    bisa_dihapus: boolean;
}

interface LibraryBranding {
    logo: ItemGambar[];
    favicon: ItemGambar[];
}

const props = withDefaults(
    defineProps<{
        library?: LibraryBranding;
        aktif?: { logo: string; favicon: string };
    }>(),
    {
        library: () => ({ logo: [], favicon: [] }),
        aktif: () => ({ logo: '', favicon: '' }),
    },
);

const page = usePage();
const branding = computed(() => (page.props as any)?.branding ?? {});
const defaultName = computed(() => branding.value?.name ?? (page.props as any)?.name ?? 'APP');
const defaultLogoUrl = computed(() => branding.value?.logo_url ?? '/branding/pusdata.png');
const defaultFaviconUrl = computed(
    () => branding.value?.favicon_url ?? '/branding/gegana-fav.png',
);

const form = useForm({
    name: '',
    logo: null as File | null,
    favicon: null as File | null,
    logo_path: '',
    favicon_path: '',
});

const logoPreviewUrl = ref('');
const faviconPreviewUrl = ref('');
const logoPilihUrl = ref('');
const faviconPilihUrl = ref('');

const resolvedLogoSrc = computed(() =>
    logoPreviewUrl.value?.trim()
        ? logoPreviewUrl.value
        : logoPilihUrl.value?.trim()
          ? logoPilihUrl.value
          : defaultLogoUrl.value,
);
const resolvedFaviconSrc = computed(() =>
    faviconPreviewUrl.value?.trim()
        ? faviconPreviewUrl.value
        : faviconPilihUrl.value?.trim()
          ? faviconPilihUrl.value
          : defaultFaviconUrl.value,
);

const logoStatus = computed(() => {
    if (form.logo) return 'Logo baru siap diupload';
    if (form.logo_path) return 'Dipilih dari riwayat — klik Simpan untuk memakai';
    return 'Logo aktif dari server';
});
const faviconStatus = computed(() => {
    if (form.favicon) return 'Favicon baru siap diupload';
    if (form.favicon_path) return 'Dipilih dari riwayat — klik Simpan untuk memakai';
    return 'Favicon aktif dari server';
});

watch(
    defaultName,
    (value) => {
        form.name = value;
    },
    { immediate: true },
);

const updatePreviewUrl = (
    currentPreview: typeof logoPreviewUrl,
    file: File | null,
) => {
    if (currentPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(currentPreview.value);
    }

    currentPreview.value = file ? URL.createObjectURL(file) : '';
};

const onLogoChange = (event: Event) => {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];
    form.logo = file ?? null;
    if (file) {
        form.logo_path = '';
        logoPilihUrl.value = '';
    }
    updatePreviewUrl(logoPreviewUrl, file ?? null);
};

const onFaviconChange = (event: Event) => {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];
    form.favicon = file ?? null;
    if (file) {
        form.favicon_path = '';
        faviconPilihUrl.value = '';
    }
    updatePreviewUrl(faviconPreviewUrl, file ?? null);
};

/* ── Modal pilih gambar dari riwayat ─────────────────────────────────────── */
const jenisModal = ref<'logo' | 'favicon' | null>(null);
const modalTerbuka = computed({
    get: () => jenisModal.value !== null,
    set: (value: boolean) => {
        if (!value) jenisModal.value = null;
    },
});
const daftarModal = computed<ItemGambar[]>(() =>
    jenisModal.value ? (props.library?.[jenisModal.value] ?? []) : [],
);
const jumlahRiwayat = computed(() => ({
    logo: props.library?.logo?.length ?? 0,
    favicon: props.library?.favicon?.length ?? 0,
}));
const namaAktif = computed(() =>
    jenisModal.value === 'favicon' ? props.aktif?.favicon : props.aktif?.logo,
);

const bukaModal = (jenis: 'logo' | 'favicon') => {
    jenisModal.value = jenis;
};

const pilihGambar = (item: ItemGambar) => {
    if (jenisModal.value === 'favicon') {
        form.favicon = null;
        form.favicon_path = item.path;
        updatePreviewUrl(faviconPreviewUrl, null);
        faviconPilihUrl.value = item.url;
    } else {
        form.logo = null;
        form.logo_path = item.path;
        updatePreviewUrl(logoPreviewUrl, null);
        logoPilihUrl.value = item.url;
    }

    jenisModal.value = null;
};

const hapusGambar = (item: ItemGambar) => {
    if (!item.bisa_dihapus) return;
    if (!window.confirm(`Hapus "${item.nama}" dari riwayat gambar?`)) return;

    router.delete(`/settings/branding/media?path=${encodeURIComponent(item.path)}`, {
        preserveScroll: true,
    });
};

const save = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post('/settings/branding', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('logo', 'favicon');
            updatePreviewUrl(logoPreviewUrl, null);
            updatePreviewUrl(faviconPreviewUrl, null);
        },
    });
};

const reset = () => {
    form.reset('logo', 'favicon', 'logo_path', 'favicon_path');
    form.name = defaultName.value;
    updatePreviewUrl(logoPreviewUrl, null);
    updatePreviewUrl(faviconPreviewUrl, null);
    logoPilihUrl.value = '';
    faviconPilihUrl.value = '';
    const inputLogo = document.getElementById('branding-logo') as HTMLInputElement | null;
    const inputFavicon = document.getElementById('branding-favicon') as HTMLInputElement | null;
    if (inputLogo) inputLogo.value = '';
    if (inputFavicon) inputFavicon.value = '';
};

onBeforeUnmount(() => {
    updatePreviewUrl(logoPreviewUrl, null);
    updatePreviewUrl(faviconPreviewUrl, null);
});
</script>

<template>
    <Head title="Branding settings" />

    <h1 class="sr-only">Branding settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Branding"
            description="Atur identitas tampilan aplikasi"
        />

        <div class="rounded-xl border border-sidebar-border/70 bg-card p-4">
            <div class="mb-3 text-sm font-semibold tracking-widest text-muted-foreground">
                PREVIEW
            </div>
            <div class="flex items-center gap-2">
                <AppLogo />
            </div>
        </div>

        <div class="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4">
            <div class="grid gap-2">
                <Label for="branding-name">Nama Brand</Label>
                <Input
                    id="branding-name"
                    v-model="form.name"
                    placeholder="Nama aplikasi"
                    autocomplete="off"
                />
            </div>

            <div class="grid gap-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Label for="branding-logo">Logo</Label>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="bukaModal('logo')"
                    >
                        Pilih dari gambar tersimpan ({{ jumlahRiwayat.logo }})
                    </Button>
                </div>
                <Input
                    id="branding-logo"
                    type="file"
                    accept="image/png"
                    @change="onLogoChange"
                />
                <div
                    class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 bg-background p-3"
                >
                    <img
                        :src="resolvedLogoSrc"
                        alt="Logo preview"
                        class="max-h-[300px] max-w-[300px] rounded-md object-contain"
                    />
                    <div class="text-sm text-muted-foreground">
                        {{ logoStatus }}
                    </div>
                </div>
                <div v-if="form.errors.logo" class="text-sm text-destructive">
                    {{ form.errors.logo }}
                </div>
                <div v-if="form.errors.logo_path" class="text-sm text-destructive">
                    {{ form.errors.logo_path }}
                </div>
                <div class="text-sm text-muted-foreground">
                    PNG maks 4 MB. Upload baru disimpan sebagai berkas terpisah, jadi logo
                    lama tetap bisa dipakai lagi lewat tombol
                    <span class="font-medium">Pilih dari gambar tersimpan</span>.
                </div>
            </div>

            <div class="grid gap-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Label for="branding-favicon">Favicon</Label>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="bukaModal('favicon')"
                    >
                        Pilih dari gambar tersimpan ({{ jumlahRiwayat.favicon }})
                    </Button>
                </div>
                <Input
                    id="branding-favicon"
                    type="file"
                    accept="image/png"
                    @change="onFaviconChange"
                />
                <div
                    class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 bg-background p-3"
                >
                    <img
                        :src="resolvedFaviconSrc"
                        alt="Favicon preview"
                        class="max-h-[300px] max-w-[300px] rounded object-contain"
                    />
                    <div class="text-sm text-muted-foreground">
                        {{ faviconStatus }}
                    </div>
                </div>
                <div v-if="form.errors.favicon" class="text-sm text-destructive">
                    {{ form.errors.favicon }}
                </div>
                <div v-if="form.errors.favicon_path" class="text-sm text-destructive">
                    {{ form.errors.favicon_path }}
                </div>
                <div class="text-sm text-muted-foreground">
                    PNG maks 2 MB. Idealnya 32×32 atau 64×64 px agar tab browser ringan.
                </div>
            </div>

            <div v-if="form.errors.name" class="text-sm text-destructive">
                {{ form.errors.name }}
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" :disabled="form.processing" @click="save">
                    {{ form.processing ? 'Saving...' : 'Save' }}
                </Button>
                <Button type="button" variant="secondary" :disabled="form.processing" @click="reset">
                    Reset form
                </Button>
            </div>
        </div>
    </div>

    <!-- Modal pilih gambar dari riwayat -->
    <Dialog v-model:open="modalTerbuka">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>
                    Pilih {{ jenisModal === 'favicon' ? 'favicon' : 'logo' }} dari gambar
                    tersimpan
                </DialogTitle>
                <DialogDescription>
                    Klik satu gambar untuk dipakai. Gambar yang sedang aktif ditandai
                    "Aktif" dan tidak bisa dihapus.
                </DialogDescription>
            </DialogHeader>

            <div v-if="daftarModal.length === 0" class="text-sm text-muted-foreground">
                Belum ada gambar tersimpan. Upload lewat kolom file di atas.
            </div>

            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <button
                    v-for="item in daftarModal"
                    :key="item.path"
                    type="button"
                    class="group relative flex flex-col gap-2 rounded-lg border p-3 text-left transition hover:border-primary"
                    :class="item.aktif ? 'border-primary/70 bg-primary/5' : 'border-sidebar-border/70 bg-background'"
                    @click="pilihGambar(item)"
                >
                    <div class="flex h-28 items-center justify-center rounded-md bg-muted/40 p-2">
                        <img
                            :src="item.url"
                            :alt="item.nama"
                            class="max-h-24 max-w-full object-contain"
                            loading="lazy"
                        />
                    </div>
                    <div class="truncate text-xs font-medium" :title="item.nama">
                        {{ item.nama }}
                    </div>
                    <div class="text-[11px] text-muted-foreground">
                        {{ item.diubah }} · {{ item.ukuran_human }}
                    </div>
                    <div class="flex items-center gap-1">
                        <span
                            v-if="item.aktif"
                            class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary"
                        >
                            Aktif
                        </span>
                        <span
                            v-else
                            class="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground"
                        >
                            {{ item.sumber }}
                        </span>
                    </div>
                    <span
                        v-if="item.bisa_dihapus"
                        class="absolute right-2 top-2 hidden rounded bg-background/90 px-1.5 py-0.5 text-[10px] text-destructive underline group-hover:inline-block"
                        @click.stop="hapusGambar(item)"
                    >
                        Hapus
                    </span>
                </button>
            </div>

            <div class="text-xs text-muted-foreground">
                Aktif sekarang: <span class="font-mono">{{ namaAktif }}</span>
            </div>
        </DialogContent>
    </Dialog>
</template>
