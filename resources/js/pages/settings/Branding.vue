<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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

const page = usePage();
const branding = computed(() => (page.props as any)?.branding ?? {});
const defaultName = computed(() => branding.value?.name ?? (page.props as any)?.name ?? 'APP');
const defaultLogoUrl = computed(() => branding.value?.logo_url ?? '/branding/lgo.png');
const defaultFaviconUrl = computed(
    () => branding.value?.favicon_url ?? '/branding/gegana-fav.png',
);

const form = useForm({
    name: '',
    logo: null as File | null,
    favicon: null as File | null,
});

const logoPreviewUrl = ref('');
const faviconPreviewUrl = ref('');

const resolvedLogoSrc = computed(() =>
    logoPreviewUrl.value?.trim() ? logoPreviewUrl.value : defaultLogoUrl.value,
);
const resolvedFaviconSrc = computed(() =>
    faviconPreviewUrl.value?.trim() ? faviconPreviewUrl.value : defaultFaviconUrl.value,
);

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
    updatePreviewUrl(logoPreviewUrl, file ?? null);
};

const onFaviconChange = (event: Event) => {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];
    form.favicon = file ?? null;
    updatePreviewUrl(faviconPreviewUrl, file ?? null);
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
        onFinish: () => {
            form.transform((data) => data);
        },
    });
};

const reset = () => {
    form.reset('logo', 'favicon');
    form.name = defaultName.value;
    updatePreviewUrl(logoPreviewUrl, null);
    updatePreviewUrl(faviconPreviewUrl, null);
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
            <div class="mb-3 text-xs font-semibold tracking-widest text-muted-foreground">
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
                <Label for="branding-logo">Logo</Label>
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
                    <div class="text-xs text-muted-foreground">
                        {{ form.logo ? 'Logo baru siap diupload' : 'Logo aktif dari server' }}
                    </div>
                </div>
                <div v-if="form.errors.logo" class="text-sm text-destructive">
                    {{ form.errors.logo }}
                </div>
                <div class="text-xs text-muted-foreground">
                    Gunakan file PNG agar URL `branding/lgo.png` tetap konsisten.
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="branding-favicon">Favicon</Label>
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
                    <div class="text-xs text-muted-foreground">
                        {{ form.favicon ? 'Favicon baru siap diupload' : 'Favicon aktif dari server' }}
                    </div>
                </div>
                <div v-if="form.errors.favicon" class="text-sm text-destructive">
                    {{ form.errors.favicon }}
                </div>
                <div class="text-xs text-muted-foreground">
                    Gunakan file PNG agar URL `branding/gegana-fav.png` tetap konsisten.
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
</template>
