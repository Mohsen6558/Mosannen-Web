<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * The four base-data screens share one sidebar row, so they navigate between
 * themselves here.
 */
const TABS = [
    { name: 'خدمات و تعرفه', route: 'catalog.treatments.index' },
    { name: 'داروها', route: 'catalog.drugs.index' },
    { name: 'بیمه‌ها', route: 'catalog.insurances.index' },
    { name: 'نحوه پرداخت', route: 'catalog.payment-types.index' },
];
</script>

<template>
    <nav class="mb-5 flex flex-wrap gap-1">
        <Link
            v-for="tab in TABS"
            :key="tab.route"
            :href="route(tab.route)"
            class="rounded-lg px-3.5 py-2 text-sm font-medium transition-colors"
            :class="route().current(tab.route)
                ? 'bg-brand-600 text-white'
                : 'text-ink-500 hover:bg-surface-200/70 hover:text-ink-900 dark:hover:bg-surface-800 dark:hover:text-ink-50'"
        >
            {{ tab.name }}
        </Link>
    </nav>
</template>
