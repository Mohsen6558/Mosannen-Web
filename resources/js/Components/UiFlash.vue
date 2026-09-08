<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const items = ref([]);
let seq = 0;

const TONES = {
    success: {
        cls: 'border-success-500/30 bg-success-50 text-success-600 dark:bg-success-600/10',
        path: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    error: {
        cls: 'border-danger-500/30 bg-danger-50 text-danger-600 dark:bg-danger-600/10',
        path: 'M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    warning: {
        cls: 'border-warning-500/30 bg-warning-50 text-warning-600 dark:bg-warning-600/10',
        path: 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
    },
};

function push(type, message) {
    const id = ++seq;
    items.value.push({ id, type, message });
    setTimeout(() => dismiss(id), 5000);
}

function dismiss(id) {
    items.value = items.value.filter((i) => i.id !== id);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        ['success', 'error', 'warning'].forEach((k) => flash[k] && push(k, flash[k]));
    },
    { immediate: true, deep: true },
);

const tone = (t) => TONES[t] ?? TONES.warning;
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4">
        <TransitionGroup name="pop">
            <div
                v-for="item in items"
                :key="item.id"
                class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-lg border px-4 py-3 shadow-[var(--shadow-overlay)] backdrop-blur"
                :class="tone(item.type).cls"
                role="status"
            >
                <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="tone(item.type).path" />
                </svg>
                <p class="flex-1 text-sm leading-relaxed">{{ item.message }}</p>
                <button type="button" class="shrink-0 opacity-60 hover:opacity-100" aria-label="بستن" @click="dismiss(item.id)">
                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
