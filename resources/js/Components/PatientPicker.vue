<script setup>
import { ref, watch } from 'vue';
import { refDebounced, onClickOutside } from '@vueuse/core';
import { toPersianDigits } from '@/Support/format';

/** Type-ahead patient lookup, backed by the trigram search on the server. */
const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    label: { type: String, default: null },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select']);

const root = ref(null);
const query = ref('');
const debounced = refDebounced(query, 250);
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const chosen = ref(null);

onClickOutside(root, () => (open.value = false));

watch(debounced, async (q) => {
    if (!q || q.length < 2) {
        results.value = [];

        return;
    }

    loading.value = true;
    try {
        const res = await fetch(`${route('patients.search')}?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        results.value = res.ok ? await res.json() : [];
        open.value = true;
    } finally {
        loading.value = false;
    }
});

function pick(patient) {
    chosen.value = patient;
    query.value = '';
    open.value = false;
    emit('update:modelValue', patient.id);
    emit('select', patient);
}

function clear() {
    chosen.value = null;
    emit('update:modelValue', null);
}
</script>

<template>
    <div ref="root" class="relative">
        <div
            v-if="chosen"
            class="flex items-center justify-between gap-2 rounded-lg border border-brand-500/40 bg-brand-50 px-3 py-2 dark:bg-brand-950"
        >
            <span class="min-w-0 truncate text-sm font-medium text-brand-800 dark:text-brand-200">
                {{ chosen.label }}
                <span class="nums-tabular text-xs font-normal opacity-70">({{ toPersianDigits(chosen.code) }})</span>
            </span>
            <button type="button" class="shrink-0 text-brand-600 hover:text-danger-500" aria-label="تغییر بیمار" @click="clear">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
            </button>
        </div>

        <input
            v-else
            v-model="query"
            type="text"
            class="input-base"
            :class="invalid ? 'border-danger-500' : ''"
            placeholder="نام، کد پرونده یا شماره موبایل بیمار…"
            @focus="open = true"
        >

        <Transition name="pop">
            <ul
                v-if="open && !chosen && (results.length || loading)"
                class="absolute z-40 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-surface-200 bg-white py-1 shadow-[var(--shadow-overlay)] dark:border-surface-700 dark:bg-surface-900"
            >
                <li v-if="loading" class="px-3 py-2 text-xs text-ink-500">در حال جستجو…</li>

                <li v-for="p in results" :key="p.id">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-3 py-2 text-start text-sm hover:bg-surface-100 dark:hover:bg-surface-800"
                        @click="pick(p)"
                    >
                        <span class="truncate">{{ p.label }}</span>
                        <span class="nums-tabular shrink-0 text-[11px] text-ink-500">{{ toPersianDigits(p.code) }}</span>
                    </button>
                </li>

                <li v-if="!loading && !results.length" class="px-3 py-2 text-xs text-ink-500">بیماری یافت نشد</li>
            </ul>
        </Transition>
    </div>
</template>
