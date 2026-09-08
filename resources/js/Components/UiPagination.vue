<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { toPersianDigits } from '@/Support/format';

const props = defineProps({
    // Laravel paginator payload
    meta: { type: Object, required: true },
});

const from = computed(() => props.meta.from ?? 0);
const to = computed(() => props.meta.to ?? 0);
const total = computed(() => props.meta.total ?? 0);

/** Compact page window: 1 … 4 5 [6] 7 8 … 20 */
const pages = computed(() => {
    const current = props.meta.current_page;
    const last = props.meta.last_page;
    const out = new Set([1, last, current]);
    for (let i = current - 2; i <= current + 2; i++) if (i > 1 && i < last) out.add(i);
    const sorted = [...out].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);

    const withGaps = [];
    sorted.forEach((p, i) => {
        if (i > 0 && p - sorted[i - 1] > 1) withGaps.push('…');
        withGaps.push(p);
    });
    return withGaps;
});

function urlFor(page) {
    const url = new URL(props.meta.path ?? window.location.href, window.location.origin);
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    url.search = params.toString();
    return url.pathname + url.search;
}
</script>

<template>
    <nav
        v-if="meta.last_page > 1 || total > 0"
        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
    >
        <p class="text-xs text-ink-500">
            نمایش
            <span class="nums-tabular font-medium text-ink-700 dark:text-ink-100">{{ toPersianDigits(from) }}</span>
            تا
            <span class="nums-tabular font-medium text-ink-700 dark:text-ink-100">{{ toPersianDigits(to) }}</span>
            از
            <span class="nums-tabular font-medium text-ink-700 dark:text-ink-100">{{ toPersianDigits(total) }}</span>
            مورد
        </p>

        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <template v-for="(p, i) in pages" :key="i">
                <span v-if="p === '…'" class="px-1.5 text-xs text-ink-300">…</span>
                <Link
                    v-else
                    :href="urlFor(p)"
                    preserve-scroll
                    preserve-state
                    class="nums-tabular inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-medium transition-colors"
                    :class="p === meta.current_page
                        ? 'bg-brand-600 text-white'
                        : 'text-ink-700 hover:bg-surface-100 dark:text-ink-100 dark:hover:bg-surface-800'"
                >
                    {{ toPersianDigits(p) }}
                </Link>
            </template>
        </div>
    </nav>
</template>
