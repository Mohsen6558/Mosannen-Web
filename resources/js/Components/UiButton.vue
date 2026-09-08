<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary|secondary|ghost|danger|subtle
    size: { type: String, default: 'md' },         // sm|md|lg|icon
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const VARIANTS = {
    primary:
        'bg-brand-600 text-white shadow-[var(--shadow-card)] hover:bg-brand-700 active:bg-brand-800 ' +
        'disabled:bg-brand-600/50',
    secondary:
        'bg-white text-ink-900 border border-surface-300 hover:bg-surface-100 active:bg-surface-200 ' +
        'dark:bg-surface-900 dark:text-ink-50 dark:border-surface-700 dark:hover:bg-surface-800',
    ghost:
        'text-ink-700 hover:bg-surface-200/70 active:bg-surface-200 ' +
        'dark:text-ink-100 dark:hover:bg-surface-800',
    subtle:
        'bg-brand-50 text-brand-700 hover:bg-brand-100 active:bg-brand-200 ' +
        'dark:bg-brand-950 dark:text-brand-200 dark:hover:bg-brand-900',
    danger:
        'bg-danger-500 text-white hover:bg-danger-600 active:bg-danger-600 disabled:bg-danger-500/50',
};

const SIZES = {
    sm: 'h-8 px-3 text-xs gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-12 px-6 text-base gap-2.5',
    icon: 'h-10 w-10 justify-center',
};

const classes = computed(() => [
    'inline-flex items-center rounded-lg font-medium transition-all duration-150',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500',
    'disabled:cursor-not-allowed disabled:opacity-60',
    VARIANTS[props.variant] ?? VARIANTS.primary,
    SIZES[props.size] ?? SIZES.md,
    props.block ? 'w-full justify-center' : '',
]);

const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <Link v-if="href && !isDisabled" :href="href" :class="classes">
        <slot name="icon" />
        <slot />
    </Link>

    <button v-else :type="type" :disabled="isDisabled" :class="classes">
        <svg v-if="loading" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
        </svg>
        <slot v-else name="icon" />
        <slot />
    </button>
</template>
