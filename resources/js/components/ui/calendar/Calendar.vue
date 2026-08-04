<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    modelValue?: string; // YYYY-MM-DD or empty
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const DAYS_OF_WEEK = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const today = new Date();
const currentYear = today.getFullYear();
const currentMonth = today.getMonth(); // 0-based

const viewYear = ref(currentYear);
const viewMonth = ref(currentMonth);

const selectedDate = computed(() => {
    if (!props.modelValue) return null;
    const parts = props.modelValue.split('-');
    if (parts.length !== 3) return null;
    return { year: Number(parts[0]), month: Number(parts[1]) - 1, day: Number(parts[2]) };
});

const daysInMonth = computed(() => {
    return new Date(viewYear.value, viewMonth.value + 1, 0).getDate();
});

const firstDayOfMonth = computed(() => {
    return new Date(viewYear.value, viewMonth.value, 1).getDay(); // 0=Sun
});

const days = computed(() => {
    const result: (number | null)[] = [];
    for (let i = 0; i < firstDayOfMonth.value; i++) {
        result.push(null);
    }
    for (let d = 1; d <= daysInMonth.value; d++) {
        result.push(d);
    }
    return result;
});

const monthLabel = computed(() => {
    return new Date(viewYear.value, viewMonth.value).toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    });
});

function isSelected(day: number) {
    if (!selectedDate.value) return false;
    return (
        selectedDate.value.year === viewYear.value &&
        selectedDate.value.month === viewMonth.value &&
        selectedDate.value.day === day
    );
}

function isToday(day: number) {
    return (
        today.getFullYear() === viewYear.value &&
        today.getMonth() === viewMonth.value &&
        today.getDate() === day
    );
}

function selectDay(day: number) {
    const y = String(viewYear.value);
    const m = String(viewMonth.value + 1).padStart(2, '0');
    const d = String(day).padStart(2, '0');
    emit('update:modelValue', `${y}-${m}-${d}`);
}

function prevMonth() {
    if (viewMonth.value === 0) {
        viewYear.value--;
        viewMonth.value = 11;
    } else {
        viewMonth.value--;
    }
}

function nextMonth() {
    if (viewMonth.value === 11) {
        viewYear.value++;
        viewMonth.value = 0;
    } else {
        viewMonth.value++;
    }
}
</script>

<template>
    <div class="w-[260px] select-none p-3">
        <!-- Header -->
        <div class="mb-2 flex items-center justify-between">
            <button
                type="button"
                class="rounded-md p-1 text-sky-300 hover:bg-sky-500/20 hover:text-sky-100"
                @click="prevMonth"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>
            <span class="text-sm font-medium text-sky-200">{{ monthLabel }}</span>
            <button
                type="button"
                class="rounded-md p-1 text-sky-300 hover:bg-sky-500/20 hover:text-sky-100"
                @click="nextMonth"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 18l6-6-6-6" />
                </svg>
            </button>
        </div>

        <!-- Day headers -->
        <div class="mb-1 grid grid-cols-7">
            <div
                v-for="d in DAYS_OF_WEEK"
                :key="d"
                class="py-1 text-center text-xs text-sky-400"
            >
                {{ d }}
            </div>
        </div>

        <!-- Day grid -->
        <div class="grid grid-cols-7 gap-y-1">
            <div
                v-for="(day, idx) in days"
                :key="idx"
                class="flex items-center justify-center"
            >
                <button
                    v-if="day !== null"
                    type="button"
                    class="h-8 w-8 rounded-md text-xs transition"
                    :class="[
                        isSelected(day)
                            ? 'bg-sky-500/30 text-sky-100 font-semibold'
                            : isToday(day)
                                ? 'border border-sky-500/40 text-sky-200'
                                : 'text-sky-300 hover:bg-sky-500/15 hover:text-sky-100',
                    ]"
                    @click="selectDay(day)"
                >
                    {{ day }}
                </button>
                <span v-else class="h-8 w-8" />
            </div>
        </div>
    </div>
</template>
