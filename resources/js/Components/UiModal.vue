<script setup>
import { watch } from 'vue';
import { onKeyStroke } from '@vueuse/core';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    size: { type: String, default: 'md' }, // sm|md|lg|xl|full
    closeable: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const SIZES = {
    sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl', xl: 'max-w-5xl', full: 'max-w-[95vw]',
};

onKeyStroke('Escape', () => props.show && props.closeable && emit('close'));

// Prevent the page behind the overlay from scrolling.
watch(() => props.show, (v) => {
    document.body.style.overflow = v ? 'hidden' : '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-surface-950/50 backdrop-blur-[2px]" @click="closeable && emit('close')" />

                <div class="flex min-h-full items-center justify-center p-4">
                    <Transition name="pop" appear>
                        <div
                            v-if="show"
                            class="relative w-full rounded-[var(--radius-card)] border border-surface-200 bg-white shadow-[var(--shadow-overlay)] dark:border-surface-700 dark:bg-surface-900"
                            :class="SIZES[size] ?? SIZES.md"
                        >
                            <header
                                v-if="title || $slots.header"
                                class="flex items-start justify-between gap-4 border-b border-surface-200 px-5 py-4 dark:border-surface-800"
                            >
                                <div class="min-w-0">
                                    <slot name="header">
                                        <h2 class="text-base font-semibold text-ink-900 dark:text-ink-50">{{ title }}</h2>
                                        <p v-if="subtitle" class="mt-0.5 text-xs text-ink-500">{{ subtitle }}</p>
                                    </slot>
                                </div>
                                <button
                                    v-if="closeable"
                                    type="button"
                                    class="-me-1 shrink-0 rounded-md p-1.5 text-ink-500 transition-colors hover:bg-surface-100 hover:text-ink-900 dark:hover:bg-surface-800"
                                    aria-label="بستن"
                                    @click="emit('close')"
                                >
                                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                                </button>
                            </header>

                            <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                                <slot />
                            </div>

                            <footer
                                v-if="$slots.footer"
                                class="flex items-center justify-end gap-2 border-t border-surface-200 bg-surface-50 px-5 py-3 dark:border-surface-800 dark:bg-surface-950"
                            >
                                <slot name="footer" />
                            </footer>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
