<script setup>
import { computed } from 'vue';
import { formatNumber, parseMoney, toPersianDigits } from '@/Support/format';

/**
 * Money is an integer number of Rial on the wire. This input shows grouped
 * Persian digits while typing and emits a plain integer, so the API never
 * has to parse "۱٬۲۰۰٬۰۰۰".
 */
const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    invalid: { type: Boolean, default: false },
    display: { type: String, default: 'rial' },
});

const emit = defineEmits(['update:modelValue']);

const shown = computed(() =>
    props.modelValue === null || props.modelValue === '' ? '' : formatNumber(props.modelValue),
);

// A readable hint of the same amount in Toman, since staff quote both.
const toman = computed(() => {
    const n = Number(props.modelValue);
    if (!n || Number.isNaN(n)) return null;
    return `${toPersianDigits(Math.round(n / 10).toLocaleString('en-US'))} تومان`;
});

function onInput(event) {
    emit('update:modelValue', parseMoney(event.target.value));
}
</script>

<template>
    <div>
        <div class="relative">
            <input
                :value="shown"
                inputmode="numeric"
                dir="ltr"
                class="input-base nums-tabular text-end pe-14"
                :class="invalid ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20' : ''"
                v-bind="$attrs"
                @input="onInput"
            >
            <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-xs text-ink-500">
                ریال
            </span>
        </div>
        <p v-if="toman" class="mt-1 text-xs text-ink-500">{{ toman }}</p>
    </div>
</template>
