<script setup>
import UiButton from '@/Components/UiButton.vue';
import UiModal from '@/Components/UiModal.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'تایید عملیات' },
    message: { type: String, default: 'آیا مطمئن هستید؟' },
    confirmLabel: { type: String, default: 'تایید' },
    cancelLabel: { type: String, default: 'انصراف' },
    tone: { type: String, default: 'danger' },
    processing: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <UiModal :show="show" size="sm" :title="title" @close="$emit('cancel')">
        <div class="flex gap-3">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-full"
                :class="tone === 'danger' ? 'bg-danger-50 text-danger-500 dark:bg-danger-600/15' : 'bg-warning-50 text-warning-600 dark:bg-warning-600/15'"
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
            </div>
            <p class="pt-2 text-sm leading-relaxed text-ink-700 dark:text-ink-100">{{ message }}</p>
        </div>

        <template #footer>
            <UiButton variant="secondary" :disabled="processing" @click="$emit('cancel')">{{ cancelLabel }}</UiButton>
            <UiButton :variant="tone" :loading="processing" @click="$emit('confirm')">{{ confirmLabel }}</UiButton>
        </template>
    </UiModal>
</template>
