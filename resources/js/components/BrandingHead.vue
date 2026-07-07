<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const faviconUrl = computed(
    () => (page.props as any)?.branding?.favicon_url ?? '/branding/gegana-fav.png',
);
</script>

<template>
    <Head>
        <link rel="icon" :href="faviconUrl" type="image/png" />
        <link rel="apple-touch-icon" :href="faviconUrl" />
    </Head>
</template>
