<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
    tone: { type: String, default: 'neutral' },
    trend: { type: Number, default: null }, // percent change
});

const TONES = {
    neutral: 'text-ink-900 dark:text-ink-50',
    brand: 'text-brand-700 dark:text-brand-300',
    success: 'text-success-600 dark:text-success-500',
    danger: 'text-danger-600 dark:text-danger-500',
    warning: 'text-warning-600 dark:text-warning-500',
};

const valueCls = computed(() => TONES[props.tone] ?? TONES.neutral);
</script>

<template>
    <div class="card p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-xs font-medium text-ink-500">{{ label }}</p>
                <p class="nums-tabular mt-1.5 truncate text-xl font-bold" :class="valueCls">{{ value }}</p>
                <p v-if="hint" class="mt-1 truncate text-[11px] text-ink-500">{{ hint }}</p>
            </div>

            <div
                v-if="$slots.icon"
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-100 text-ink-500 dark:bg-surface-800"
            >
                <slot name="icon" />
            </div>
        </div>

        <p
            v-if="trend !== null"
            class="mt-2 inline-flex items-center gap-1 text-[11px] font-medium"
            :class="trend >= 0 ? 'text-success-600' : 'text-danger-500'"
        >
            <svg class="size-3" viewBox="0 0 12 12" fill="currentColor">
                <path v-if="trend >= 0" d="M6 2l4 5H2z" />
                <path v-else d="M6 10L2 5h8z" />
            </svg>
            {{ Math.abs(trend) }}٪
        </p>
    </div>
</template>
