<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            {
                title: 'World Monitoring',
                href: '/world-monitoring',
            },
        ],
    }),
});

const src = 'https://world.pemetaankerawanan.my.id/';
</script>

<template>
    <Head title="World Monitoring" />

    <div class="p-4 font-mono sm:p-6">
        <div class="rounded-xl border border-green-500/15 bg-black/20 p-4">
            <div class="mb-3 flex items-center justify-between text-xs text-green-300/60">
                <div>> WORLD MONITORING</div>
                <div class="text-[11px]">></div>
            </div>

            <div class="overflow-hidden rounded-lg border border-green-500/15 bg-black/30">
                <iframe
                    :src="src"
                    class="h-[calc(100vh-220px)] w-full sm:h-[calc(100vh-260px)]"
                    referrerpolicy="no-referrer"
                    allowfullscreen
                />
            </div>
        </div>
    </div>
</template>
