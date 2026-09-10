<script setup>
import { computed } from 'vue';

/**
 * A single figure. Deliberately plain: the number is the only thing with
 * weight, so a row of these reads as data rather than as decoration.
 */
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
    tone: { type: String, default: 'neutral' },
    trend: { type: Number, default: null },
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
    <div class="card px-5 py-4">
        <p class="truncate text-xs text-ink-500">{{ label }}</p>

        <p class="nums-tabular mt-1.5 truncate text-2xl leading-tight font-bold" :class="valueCls">
            {{ value }}
        </p>

        <div class="mt-1 flex items-center gap-2">
            <p v-if="hint" class="truncate text-[11px] text-ink-300">{{ hint }}</p>
            <p
                v-if="trend !== null"
                class="nums-tabular shrink-0 text-[11px] font-medium"
                :class="trend >= 0 ? 'text-success-600' : 'text-danger-500'"
            >
                {{ trend >= 0 ? '+' : '−' }}{{ Math.abs(trend) }}٪
            </p>
        </div>
    </div>
</template>
