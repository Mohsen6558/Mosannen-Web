<script setup>
defineProps({
    title: { type: String, default: 'موردی یافت نشد' },
    message: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div class="mb-3 flex size-12 items-center justify-center rounded-full bg-surface-100 text-ink-300 dark:bg-surface-800">
            <slot name="icon">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V7a2 2 0 00-2-2h-3l-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2h7M16 19h6m-3-3v6" />
                </svg>
            </slot>
        </div>
        <p class="text-sm font-medium text-ink-700 dark:text-ink-100">{{ title }}</p>
        <p v-if="message" class="mt-1 max-w-sm text-xs leading-relaxed text-ink-500">{{ message }}</p>
        <div v-if="$slots.action" class="mt-4"><slot name="action" /></div>
    </div>
</template>
