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

const load = () => {
    if (typeof window === 'undefined') return;
    name.value = localStorage.getItem('branding.name') ?? defaultName.value;
};

const save = () => {
    if (typeof window === 'undefined') return;
    const trimmed = name.value.trim();
    localStorage.setItem('branding.name', trimmed || defaultName.value);
    window.dispatchEvent(new CustomEvent('branding:update'));
};

const reset = () => {
    if (typeof window === 'undefined') return;
    localStorage.removeItem('branding.name');
    name.value = defaultName.value;
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

            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" @click="save">Save</Button>
                <Button type="button" variant="secondary" @click="reset">
                    Reset
                </Button>
            </div>
        </div>
    </div>
</template>
