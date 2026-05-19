<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
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
const defaultName = computed(() => (page.props as any)?.name ?? 'APP');
const name = ref('');
const logoDataUrl = ref<string>('');
const faviconDataUrl = ref<string>('');

const load = () => {
    if (typeof window === 'undefined') return;
    name.value = localStorage.getItem('branding.name') ?? defaultName.value;
    logoDataUrl.value = localStorage.getItem('branding.logoDataUrl') ?? '';
    faviconDataUrl.value =
        localStorage.getItem('branding.faviconDataUrl') ?? '';
};

const readFileAsDataUrl = (file: File) =>
    new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('failed_to_read_file'));
        reader.onload = () => resolve(String(reader.result ?? ''));
        reader.readAsDataURL(file);
    });

const onLogoChange = async (event: Event) => {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];
    if (!file) return;
    logoDataUrl.value = await readFileAsDataUrl(file);
};

const onFaviconChange = async (event: Event) => {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];
    if (!file) return;
    faviconDataUrl.value = await readFileAsDataUrl(file);
};

const save = () => {
    if (typeof window === 'undefined') return;
    const trimmed = name.value.trim();
    localStorage.setItem('branding.name', trimmed || defaultName.value);
    if (logoDataUrl.value) {
        localStorage.setItem('branding.logoDataUrl', logoDataUrl.value);
    } else {
        localStorage.removeItem('branding.logoDataUrl');
    }
    if (faviconDataUrl.value) {
        localStorage.setItem('branding.faviconDataUrl', faviconDataUrl.value);
    } else {
        localStorage.removeItem('branding.faviconDataUrl');
    }
    window.dispatchEvent(new CustomEvent('branding:update'));
};

const reset = () => {
    if (typeof window === 'undefined') return;
    localStorage.removeItem('branding.name');
    localStorage.removeItem('branding.logoDataUrl');
    localStorage.removeItem('branding.faviconDataUrl');
    name.value = defaultName.value;
    logoDataUrl.value = '';
    faviconDataUrl.value = '';
    window.dispatchEvent(new CustomEvent('branding:update'));
};

onMounted(load);
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
                    v-model="name"
                    placeholder="Nama aplikasi"
                    autocomplete="off"
                />
            </div>

            <div class="grid gap-2">
                <Label for="branding-logo">Logo</Label>
                <Input
                    id="branding-logo"
                    type="file"
                    accept="image/*"
                    @change="onLogoChange"
                />
                <div
                    v-if="logoDataUrl"
                    class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 bg-background p-3"
                >
                    <img
                        :src="logoDataUrl"
                        alt="Logo preview"
                        class="h-10 w-10 rounded-md object-contain"
                    />
                    <div class="text-xs text-muted-foreground">
                        Preview logo (tersimpan lokal)
                    </div>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="branding-favicon">Favicon</Label>
                <Input
                    id="branding-favicon"
                    type="file"
                    accept="image/*"
                    @change="onFaviconChange"
                />
                <div
                    v-if="faviconDataUrl"
                    class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 bg-background p-3"
                >
                    <img
                        :src="faviconDataUrl"
                        alt="Favicon preview"
                        class="h-8 w-8 rounded object-contain"
                    />
                    <div class="text-xs text-muted-foreground">
                        Preview favicon (tersimpan lokal)
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" @click="save">Save</Button>
                <Button type="button" variant="secondary" @click="reset">
                    Reset
                </Button>
            </div>
        </div>
    </div>
</template>
