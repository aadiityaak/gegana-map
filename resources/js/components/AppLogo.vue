<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const defaultName = computed(() => (page.props as any)?.name ?? 'APP');
const defaultLogoUrl = '/branding/lgo.png';
const customName = ref<string | null>(null);
const customLogoDataUrl = ref<string | null>(null);

const load = () => {
    if (typeof window === 'undefined') return;
    customName.value = localStorage.getItem('branding.name');
    customLogoDataUrl.value = localStorage.getItem('branding.logoDataUrl');
};

const brandingName = computed(() =>
    customName.value?.trim() ? customName.value : defaultName.value,
);
const brandingLogo = computed(() =>
    customLogoDataUrl.value?.trim() ? customLogoDataUrl.value : defaultLogoUrl,
);

onMounted(() => {
    load();
    window.addEventListener('branding:update', load as EventListener);
});

onUnmounted(() => {
    window.removeEventListener('branding:update', load as EventListener);
});
</script>

<template>
    <div class="overflow-hidden rounded-md bg-black p-1">
        <img
            :src="brandingLogo"
            alt="Logo"
            class="h-full w-full object-contain"
        />
    </div>
</template>
