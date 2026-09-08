<script setup>
defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="card overflow-hidden">
        <header
            v-if="title || $slots.header || $slots.actions"
            class="flex items-start justify-between gap-4 border-b border-surface-200 px-5 py-4 dark:border-surface-800"
        >
            <div class="min-w-0">
                <slot name="header">
                    <h2 class="truncate text-sm font-semibold text-ink-900 dark:text-ink-50">{{ title }}</h2>
                    <p v-if="subtitle" class="mt-0.5 truncate text-xs text-ink-500">{{ subtitle }}</p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <div :class="padded ? 'p-5' : ''">
            <slot />
        </div>

        <footer
            v-if="$slots.footer"
            class="border-t border-surface-200 bg-surface-50 px-5 py-3 dark:border-surface-800 dark:bg-surface-950"
        >
            <slot name="footer" />
        </footer>
    </section>
</template>
