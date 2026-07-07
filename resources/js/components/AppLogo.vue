<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const branding = computed(() => (page.props as any)?.branding ?? {});
const brandingLogo = computed(() =>
    branding.value?.logo_url?.trim() ? branding.value.logo_url : '/branding/pusdata.png',
);
</script>

<template>
    <div class="overflow-hidden rounded-md bg-black p-1 max-w-[300px] max-h-[100px]">
        <img
            :src="brandingLogo"
            alt="Logo"
            class="h-full w-full object-contain"
        />
    </div>
</template>
