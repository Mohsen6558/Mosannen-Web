<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 3 },
    invalid: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <textarea
        :value="modelValue"
        :rows="rows"
        class="input-base resize-y leading-relaxed"
        :class="invalid ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20' : ''"
        v-bind="$attrs"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>
