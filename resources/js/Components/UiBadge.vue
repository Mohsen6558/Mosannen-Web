<script setup>
import { computed } from 'vue';

const props = defineProps({
    tone: { type: String, default: 'neutral' }, // neutral|brand|success|danger|warning|info
    dot: { type: Boolean, default: false },
});

const TONES = {
    neutral: 'bg-surface-200 text-ink-700 dark:bg-surface-800 dark:text-ink-100',
    brand: 'bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-200',
    success: 'bg-success-50 text-success-600 dark:bg-success-600/15 dark:text-success-500',
    danger: 'bg-danger-50 text-danger-600 dark:bg-danger-600/15 dark:text-danger-500',
    warning: 'bg-warning-50 text-warning-600 dark:bg-warning-600/15 dark:text-warning-500',
    info: 'bg-info-50 text-info-600 dark:bg-info-600/15 dark:text-info-500',
};

const DOTS = {
    neutral: 'bg-ink-500', brand: 'bg-brand-500', success: 'bg-success-500',
    danger: 'bg-danger-500', warning: 'bg-warning-500', info: 'bg-info-500',
};

const cls = computed(() => TONES[props.tone] ?? TONES.neutral);
const dotCls = computed(() => DOTS[props.tone] ?? DOTS.neutral);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="cls"
    >
        <span v-if="dot" class="size-1.5 rounded-full" :class="dotCls" />
        <slot />
    </span>
</template>
