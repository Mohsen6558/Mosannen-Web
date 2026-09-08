<script setup>
import { computed } from 'vue';
import { toEnglishDigits } from '@/Support/format';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    invalid: { type: Boolean, default: false },
    // Persian keyboards produce Persian digits; normalise them for numeric fields.
    numeric: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const classes = computed(() => [
    'input-base',
    props.invalid ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20' : '',
]);

function onInput(event) {
    const raw = event.target.value;
    emit('update:modelValue', props.numeric ? toEnglishDigits(raw) : raw);
}
</script>

<template>
    <div class="relative">
        <span
            v-if="$slots.prefix"
            class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-ink-300"
        >
            <slot name="prefix" />
        </span>

        <input
            :type="type"
            :value="modelValue"
            :class="[classes, $slots.prefix ? 'ps-10' : '', $slots.suffix ? 'pe-10' : '']"
            v-bind="$attrs"
            @input="onInput"
        >

        <span v-if="$slots.suffix" class="absolute inset-y-0 end-0 flex items-center pe-3 text-ink-300">
            <slot name="suffix" />
        </span>
    </div>
</template>
