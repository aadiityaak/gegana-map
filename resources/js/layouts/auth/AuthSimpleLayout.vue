<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const defaultLogoUrl = '/branding/lgo.png';
const customLogoDataUrl = ref<string | null>(null);

const loadBranding = () => {
    if (typeof window === 'undefined') return;
    customLogoDataUrl.value = localStorage.getItem('branding.logoDataUrl');
};

const brandingLogo = computed(() =>
    customLogoDataUrl.value?.trim() ? customLogoDataUrl.value : defaultLogoUrl,
);

onMounted(() => {
    loadBranding();
    window.addEventListener('branding:update', loadBranding as EventListener);
});

onUnmounted(() => {
    window.removeEventListener('branding:update', loadBranding as EventListener);
});
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden bg-[rgba(0,0,0,0.6)] p-6 text-green-200 md:p-10"
    >
        <div class="pointer-events-none absolute inset-0">
            <div
                class="absolute inset-0 bg-[radial-gradient(900px_circle_at_50%_-20%,rgba(34,197,94,0.18),transparent_60%)]"
            />
            <div
                class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(34,197,94,0.08)_1px,transparent_1px)] bg-[length:100%_3px] opacity-40"
            />
            <div
                class="absolute inset-0 bg-[linear-gradient(to_right,rgba(34,197,94,0.06)_1px,transparent_1px)] bg-[length:40px_100%] opacity-25"
            />
        </div>
        <div class="w-full max-w-sm">
            <div
                class="relative flex flex-col gap-8 rounded-2xl border border-green-500/20 bg-black/40 p-6 shadow-[0_0_0_1px_rgba(34,197,94,0.06),0_18px_70px_rgba(0,0,0,0.6)] backdrop-blur-sm"
            >
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-mono text-sm font-medium tracking-wider text-green-300"
                    >
                        <div class="mb-1 h-12 w-full max-w-[340px] overflow-hidden rounded-md bg-black p-1">
                            <img
                                :src="brandingLogo"
                                alt="Logo"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="font-mono text-lg font-semibold tracking-widest text-green-200">
                            {{ title }}
                        </h1>
                        <p class="text-center font-mono text-xs tracking-wide text-green-300/60">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
