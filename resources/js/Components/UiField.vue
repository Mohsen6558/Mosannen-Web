<script setup>
import { useId } from 'vue';

defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
});

const id = useId();
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="label-base">
            {{ label }}
            <span v-if="required" class="text-danger-500" aria-hidden="true">*</span>
        </label>

        <slot :id="id" :invalid="!!error" />

        <p v-if="error" class="mt-1.5 text-xs text-danger-500">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-ink-500">{{ hint }}</p>
    </div>
</template>
