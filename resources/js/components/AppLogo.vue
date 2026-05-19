<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const defaultName = computed(() => (page.props as any)?.name ?? 'APP');
const customName = ref<string | null>(null);

const load = () => {
    if (typeof window === 'undefined') return;
    customName.value = localStorage.getItem('branding.name');
};

const brandingName = computed(() => (customName.value?.trim() ? customName.value : defaultName.value));

onMounted(() => {
    load();
    window.addEventListener('branding:update', load as EventListener);
});

onUnmounted(() => {
    window.removeEventListener('branding:update', load as EventListener);
});
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold"
            >{{ brandingName }}</span
        >
    </div>
</template>
