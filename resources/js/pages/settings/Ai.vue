    <script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

const testing = ref(false);
const testResult = ref<{ status: 'ok' | 'failed'; model?: string; response?: string; message?: string } | null>(null);

const testConnection = async () => {
    // Save dulu untuk pastikan konfigurasi terbaru tersimpan
    await form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post('/settings/ai', { preserveScroll: true });

    testing.value = true;
    testResult.value = null;
    try {
        const res = await fetch('/api/ai/test', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });
        const data = await res.json();
        testResult.value = {
            status: data.status ?? (res.ok ? 'ok' : 'failed'),
            model: data.model,
            response: data.response ?? data.result,
            message: data.message,
        };
    } catch (e: any) {
        testResult.value = {
            status: 'failed',
            message: e?.message ?? 'Gagal menghubungi server',
        };
    } finally {
        testing.value = false;
    }
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
                <Button type="button" variant="outline" :disabled="testing || form.processing" @click="testConnection">
                    {{ testing ? 'Testing...' : 'Test AI' }}
                </Button>
            </div>

            <div v-if="testResult" class="rounded-md border border-sidebar-border/70 bg-muted p-3 text-sm">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <span class="font-medium" :class="testResult.status === 'ok' ? 'text-green-600' : 'text-red-600'">
                        {{ testResult.status === 'ok' ? '✅ Terhubung' : '❌ Gagal' }}
                    </span>
                    <span v-if="testResult.model" class="text-muted-foreground">
                        Model: {{ testResult.model }}
                    </span>
                </div>
                <div v-if="testResult.response" class="whitespace-pre-wrap text-foreground">
                    {{ testResult.response }}
                </div>
                <div v-else-if="testResult.message" class="text-muted-foreground">
                    {{ testResult.message }}
                </div>
            </div>
        </div>
    </div>
</template>
