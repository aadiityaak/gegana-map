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
        name: string;
        version: string;
        environment: string;
        url: string;
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

        <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 font-mono">
            <div class="text-xs font-semibold tracking-widest text-green-300/60">
                > APP
            </div>
            <div class="mt-3 grid gap-2 text-sm text-green-200/85">
                <div>> nama: {{ props.app.name }}</div>
                <div>> versi: v{{ props.app.version }}</div>
                <div>> env: {{ props.app.environment }}</div>
                <div>> url: {{ props.app.url }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 font-mono">
            <div class="text-xs font-semibold tracking-widest text-green-300/60">
                > BACKEND
            </div>
            <div class="mt-3 grid gap-2 text-sm text-green-200/85">
                <div>> php: {{ props.backend.php }}</div>
                <div>> laravel: {{ props.backend.laravel }}</div>
            </div>

            <div class="mt-4 grid gap-3">
                <div class="text-xs text-green-300/60">> composer require</div>
                <pre class="max-h-[260px] overflow-auto rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-200/85">{{ backendRequireLines.join('\n') }}</pre>
                <div class="text-xs text-green-300/60">> composer require-dev</div>
                <pre class="max-h-[220px] overflow-auto rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-200/85">{{ backendRequireDevLines.join('\n') }}</pre>
            </div>
        </div>

        <div class="rounded-xl border border-green-500/15 bg-black/20 p-4 font-mono">
            <div class="text-xs font-semibold tracking-widest text-green-300/60">
                > FRONTEND
            </div>
            <div class="mt-4 grid gap-3">
                <div class="text-xs text-green-300/60">> npm dependencies</div>
                <pre class="max-h-[260px] overflow-auto rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-200/85">{{ frontendDepsLines.join('\n') }}</pre>
                <div class="text-xs text-green-300/60">> npm devDependencies</div>
                <pre class="max-h-[220px] overflow-auto rounded-lg border border-green-500/15 bg-black/30 p-3 text-xs text-green-200/85">{{ frontendDevDepsLines.join('\n') }}</pre>
            </div>
        </div>
    </div>
</template>
