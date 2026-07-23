<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';

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

const props = defineProps<{
    app: {
        version: string;
        environment: string;
    };
    backend: {
        php: string;
        laravel: string;
        composer: {
            require: Record<string, string>;
            require_dev: Record<string, string>;
        };
    };
    frontend: {
        node: string | null;
        package: {
            dependencies: Record<string, string>;
            dev_dependencies: Record<string, string>;
        };
    };
}>();

const backendRequireLines = computed(() =>
    Object.entries(props.backend.composer.require ?? {})
        .map(([name, ver]) => `${name}: ${ver}`)
        .sort(),
);
const backendRequireDevLines = computed(() =>
    Object.entries(props.backend.composer.require_dev ?? {})
        .map(([name, ver]) => `${name}: ${ver}`)
        .sort(),
);
const frontendDepsLines = computed(() =>
    Object.entries(props.frontend.package.dependencies ?? {})
        .map(([name, ver]) => `${name}: ${ver}`)
        .sort(),
);
const frontendDevDepsLines = computed(() =>
    Object.entries(props.frontend.package.dev_dependencies ?? {})
        .map(([name, ver]) => `${name}: ${ver}`)
        .sort(),
);
</script>

<template>
    <Head title="About" />

    <h1 class="sr-only">About</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="About"
            description="Versi aplikasi dan stack teknologi yang digunakan"
        />

        <div class="rounded-xl border border-sky-500/15 bg-black/20 p-4 font-mono">
            <div class="text-xs font-semibold tracking-widest text-sky-300">
                > APP
            </div>
            <div class="mt-3 grid gap-2 text-sm text-sky-200/85">
                <div>> versi: v{{ props.app.version }}</div>
                <div>> env: {{ props.app.environment }}</div>

            </div>
        </div>

        <div class="rounded-xl border border-sky-500/15 bg-black/20 p-4 font-mono">
            <div class="text-xs font-semibold tracking-widest text-sky-300">
                > BACKEND
            </div>
            <div class="mt-3 grid gap-2 text-sm text-sky-200/85">
                <div class="flex items-baseline"><span class="shrink-0">> php: {{ props.backend.php }} </span><span class="flex-1 border-b border-dotted border-sky-200/30"></span><span class="shrink-0"> installed</span></div>
                <div class="flex items-baseline"><span class="shrink-0">> laravel: {{ props.backend.laravel }} </span><span class="flex-1 border-b border-dotted border-sky-200/30"></span><span class="shrink-0"> installed</span></div>
            </div>

            <div class="mt-4 grid gap-3">
                <div class="text-xs text-sky-300">> composer require</div>
                <div class="grid gap-1 max-h-[260px] overflow-auto rounded-lg border border-sky-500/15 bg-black/30 p-3 text-xs text-sky-200/85">
                    <div v-for="line in backendRequireLines" :key="line" class="flex items-baseline"><span class="shrink-0">{{ line }} </span><span class="flex-1 border-b border-dotted border-sky-200/30"></span><span class="shrink-0"> installed</span></div>
                </div>
                <div class="text-xs text-sky-300">> composer require-dev</div>
                <div class="grid gap-1 max-h-[220px] overflow-auto rounded-lg border border-sky-500/15 bg-black/30 p-3 text-xs text-sky-200/85">
                    <div v-for="line in backendRequireDevLines" :key="line" class="flex items-baseline"><span class="shrink-0">{{ line }} </span><span class="flex-1 border-b border-dotted border-sky-200/30"></span><span class="shrink-0"> installed</span></div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-sky-500/15 bg-black/20 p-4 font-mono">
            <div class="text-xs font-semibold tracking-widest text-sky-300">
                > FRONTEND
            </div>
            <div class="mt-4 grid gap-3">
                <div class="text-xs text-sky-300">> npm dependencies</div>
                <div class="grid gap-1 max-h-[260px] overflow-auto rounded-lg border border-sky-500/15 bg-black/30 p-3 text-xs text-sky-200/85">
                    <div v-for="line in frontendDepsLines" :key="line" class="flex items-baseline"><span class="shrink-0">{{ line }} </span><span class="flex-1 border-b border-dotted border-sky-200/30"></span><span class="shrink-0"> installed</span></div>
                </div>
                <div class="text-xs text-sky-300">> npm devDependencies</div>
                <div class="grid gap-1 max-h-[220px] overflow-auto rounded-lg border border-sky-500/15 bg-black/30 p-3 text-xs text-sky-200/85">
                    <div v-for="line in frontendDevDepsLines" :key="line" class="flex items-baseline"><span class="shrink-0">{{ line }} </span><span class="flex-1 border-b border-dotted border-sky-200/30"></span><span class="shrink-0"> installed</span></div>
                </div>
            </div>
        </div>
    </div>
</template>
