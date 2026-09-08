<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Page title block.
 *
 * Pages render this as the first element of their content rather than filling
 * a layout slot: the layout is applied automatically by the Inertia resolver,
 * and a page's root template has no component to attach a named slot to.
 */
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    // [{ label, href }] — the last entry is the current page.
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <nav v-if="breadcrumbs.length" class="mb-1 flex items-center gap-1.5 text-[11px] text-ink-500">
                <template v-for="(crumb, i) in breadcrumbs" :key="i">
                    <Link v-if="crumb.href" :href="crumb.href" class="hover:text-brand-600">{{ crumb.label }}</Link>
                    <span v-else class="text-ink-300">{{ crumb.label }}</span>
                    <svg v-if="i < breadcrumbs.length - 1" class="size-3 shrink-0 rotate-180 text-ink-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M9 6l6 6-6 6" />
                    </svg>
                </template>
            </nav>

            <h1 class="truncate text-lg font-bold text-ink-900 dark:text-ink-50">{{ title }}</h1>
            <p v-if="subtitle" class="mt-0.5 truncate text-xs text-ink-500">{{ subtitle }}</p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
