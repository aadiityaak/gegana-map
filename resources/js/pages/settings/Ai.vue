    <script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'AI settings',
                href: '/settings/ai',
            },
        ],
    },
});

const page = usePage();
const ai = computed(() => (page.props as any)?.ai ?? {});

const form = useForm({
    endpoint: ai.value?.endpoint ?? '',
    api_key: ai.value?.api_key ?? '',
    model: ai.value?.model ?? '',
});

const save = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post('/settings/ai', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="AI settings" />

    <h1 class="sr-only">AI settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="AI"
            description="Konfigurasi AI untuk analisa, prediksi, dan antisipasi ancaman"
        />

        <div class="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4">
            <div class="grid gap-2">
                <Label for="ai-endpoint">Endpoint</Label>
                <Input
                    id="ai-endpoint"
                    v-model="form.endpoint"
                    placeholder="https://api.openai.com/v1/chat/completions"
                    autocomplete="off"
                />
                <div v-if="form.errors.endpoint" class="text-sm text-destructive">
                    {{ form.errors.endpoint }}
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="ai-api-key">API Key</Label>
                <Input
                    id="ai-api-key"
                    v-model="form.api_key"
                    type="password"
                    placeholder="sk-..."
                    autocomplete="off"
                />
                <div v-if="form.errors.api_key" class="text-sm text-destructive">
                    {{ form.errors.api_key }}
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="ai-model">Model</Label>
                <Input
                    id="ai-model"
                    v-model="form.model"
                    placeholder="gpt-4o"
                    autocomplete="off"
                />
                <div v-if="form.errors.model" class="text-sm text-destructive">
                    {{ form.errors.model }}
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" :disabled="form.processing" @click="save">
                    {{ form.processing ? 'Saving...' : 'Save' }}
                </Button>
            </div>
        </div>
    </div>
</template>
