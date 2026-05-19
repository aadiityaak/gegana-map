<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'About settings',
                href: '/settings/about',
            },
        ],
    },
});

const aboutText = ref('');

const load = () => {
    if (typeof window === 'undefined') return;
    aboutText.value = localStorage.getItem('about.text') ?? '';
};

const save = () => {
    if (typeof window === 'undefined') return;
    localStorage.setItem('about.text', aboutText.value);
    window.dispatchEvent(new CustomEvent('about:update'));
};

const reset = () => {
    if (typeof window === 'undefined') return;
    localStorage.removeItem('about.text');
    aboutText.value = '';
    window.dispatchEvent(new CustomEvent('about:update'));
};

onMounted(load);
</script>

<template>
    <Head title="About settings" />

    <h1 class="sr-only">About settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="About"
            description="Konten tentang aplikasi yang ditampilkan di menu About"
        />

        <div class="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4">
            <div class="grid gap-2">
                <Label for="about-text">Teks About</Label>
                <textarea
                    id="about-text"
                    v-model="aboutText"
                    rows="8"
                    class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    placeholder="Tulis deskripsi singkat aplikasi..."
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
