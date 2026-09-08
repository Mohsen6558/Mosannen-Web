<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] }, // [{value,label}] or [string]
    placeholder: { type: String, default: '— انتخاب کنید —' },
    invalid: { type: Boolean, default: false },
    nullable: { type: Boolean, default: true },
});

defineEmits(['update:modelValue']);

const normalized = computed(() =>
    props.options.map((o) =>
        typeof o === 'object' && o !== null ? o : { value: o, label: String(o) },
    ),
);
</script>

<template>
    <select
        :value="modelValue"
        class="input-base appearance-none bg-[length:1.1em] bg-[position:left_0.6rem_center] bg-no-repeat pe-3 ps-9"
        :class="invalid ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20' : ''"
        style="background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238b8378' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E&quot;)"
        v-bind="$attrs"
        @change="$emit('update:modelValue', $event.target.value === '' ? null : $event.target.value)"
    >
        <option v-if="nullable" value="">{{ placeholder }}</option>
        <option v-for="o in normalized" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>
</template>
