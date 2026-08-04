<script setup lang="ts">
import { PopoverRoot, PopoverContent, PopoverTrigger } from 'reka-ui';
import Calendar from '@/components/ui/calendar/Calendar.vue';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

const props = defineProps<{
    modelValue: string; // YYYY-MM-DD or empty
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const displayText = computed(() => {
    if (!props.modelValue) return 'Pilih tanggal...';
    try {
        const [y, m, d] = props.modelValue.split('-');
        return new Date(Number(y), Number(m) - 1, Number(d)).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    } catch {
        return props.modelValue;
    }
});
</script>

<template>
    <PopoverRoot>
        <PopoverTrigger>
            <Button
                variant="outline"
                class="w-full justify-between border-sky-500/25 bg-black/40 text-sm text-sky-100 hover:bg-sky-500/10 hover:text-sky-100"
            >
                <span class="truncate">{{ displayText }}</span>
                <svg class="ml-2 h-4 w-4 shrink-0 text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <path d="M16 2v4M8 2v4M3 10h18" />
                </svg>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="z-[9999] w-auto rounded-lg border border-sky-500/25 bg-[#0a0f1a] p-0 shadow-2xl" :side-offset="4">
            <Calendar
                :model-value="modelValue"
                @update:model-value="(v) => emit('update:modelValue', v)"
            />
        </PopoverContent>
    </PopoverRoot>
</template>
