<script setup>
import { computed, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import {
    MONTH_NAMES, WEEKDAY_SHORT, daysInJalaliMonth, firstWeekdayOfMonth,
    jalali, parseJalali, toGregorian, todayIso, todayJalali, toJalali,
} from '@/Support/jalali';
import { toPersianDigits } from '@/Support/format';

/**
 * Jalali date picker over an ISO Gregorian value.
 *
 * The component's contract is deliberately narrow: `modelValue` is always
 * "YYYY-MM-DD" Gregorian (or null). Everything Persian happens inside.
 */
const props = defineProps({
    modelValue: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    placeholder: { type: String, default: '۱۴۰۳/۰۱/۰۱' },
    clearable: { type: Boolean, default: true },
    min: { type: String, default: null },
    max: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const root = ref(null);
const text = ref('');

onClickOutside(root, () => (open.value = false));

const initial = toJalali(props.modelValue) ?? todayJalali();
const viewYear = ref(initial.jy);
const viewMonth = ref(initial.jm);

watch(
    () => props.modelValue,
    (v) => {
        text.value = v ? jalali(v) : '';
        const j = toJalali(v);
        if (j) { viewYear.value = j.jy; viewMonth.value = j.jm; }
    },
    { immediate: true },
);

const selected = computed(() => toJalali(props.modelValue));
const today = toJalali(todayIso());

const grid = computed(() => {
    const lead = firstWeekdayOfMonth(viewYear.value, viewMonth.value);
    const total = daysInJalaliMonth(viewYear.value, viewMonth.value);
    const cells = Array.from({ length: lead }, () => null);
    for (let d = 1; d <= total; d++) cells.push(d);
    return cells;
});

function isDisabled(day) {
    const iso = toGregorian(viewYear.value, viewMonth.value, day);
    if (props.min && iso < props.min) return true;
    if (props.max && iso > props.max) return true;
    return false;
}

function isSelected(day) {
    return selected.value
        && selected.value.jy === viewYear.value
        && selected.value.jm === viewMonth.value
        && selected.value.jd === day;
}

function isToday(day) {
    return today && today.jy === viewYear.value && today.jm === viewMonth.value && today.jd === day;
}

function pick(day) {
    if (isDisabled(day)) return;
    emit('update:modelValue', toGregorian(viewYear.value, viewMonth.value, day));
    open.value = false;
}

function shiftMonth(delta) {
    let m = viewMonth.value + delta;
    let y = viewYear.value;
    if (m < 1) { m = 12; y--; }
    if (m > 12) { m = 1; y++; }
    viewMonth.value = m;
    viewYear.value = y;
}

function commitTyped() {
    const iso = parseJalali(text.value);
    if (iso) emit('update:modelValue', iso);
    else text.value = props.modelValue ? jalali(props.modelValue) : '';
}

function selectToday() {
    emit('update:modelValue', todayIso());
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    text.value = '';
}
</script>

<template>
    <div ref="root" class="relative">
        <div class="relative">
            <input
                v-model="text"
                class="input-base nums-tabular pe-16"
                :class="invalid ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20' : ''"
                :placeholder="placeholder"
                v-bind="$attrs"
                @focus="open = true"
                @keydown.enter.prevent="commitTyped(); open = false"
                @keydown.esc="open = false"
                @blur="commitTyped"
            >
            <div class="absolute inset-y-0 end-0 flex items-center gap-0.5 pe-2">
                <button
                    v-if="clearable && modelValue"
                    type="button"
                    class="rounded p-1 text-ink-300 hover:text-danger-500"
                    aria-label="پاک کردن تاریخ"
                    @click="clear"
                >
                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                </button>
                <button
                    type="button"
                    class="rounded p-1 text-ink-500 hover:text-brand-600"
                    aria-label="باز کردن تقویم"
                    @click="open = !open"
                >
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="5" width="18" height="16" rx="2.5" />
                        <path stroke-linecap="round" d="M8 3v4M16 3v4M3 10h18" />
                    </svg>
                </button>
            </div>
        </div>

        <Transition name="pop">
            <div
                v-if="open"
                class="absolute z-40 mt-2 w-72 rounded-[var(--radius-card)] border border-surface-200 bg-white p-3 shadow-[var(--shadow-overlay)] dark:border-surface-700 dark:bg-surface-900"
            >
                <div class="mb-2 flex items-center justify-between">
                    <button type="button" class="rounded-md p-1.5 hover:bg-surface-100 dark:hover:bg-surface-800" aria-label="ماه بعد" @click="shiftMonth(1)">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 6l6 6-6 6"/></svg>
                    </button>

                    <div class="flex items-center gap-1.5 text-sm font-semibold">
                        <select v-model.number="viewMonth" class="rounded-md border-0 bg-transparent py-0.5 text-sm font-semibold focus:ring-0">
                            <option v-for="(m, i) in MONTH_NAMES" :key="m" :value="i + 1">{{ m }}</option>
                        </select>
                        <input
                            v-model.number="viewYear"
                            type="number"
                            class="w-16 rounded-md border-0 bg-transparent py-0.5 text-center text-sm font-semibold focus:ring-0"
                        >
                    </div>

                    <button type="button" class="rounded-md p-1.5 hover:bg-surface-100 dark:hover:bg-surface-800" aria-label="ماه قبل" @click="shiftMonth(-1)">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 6l-6 6 6 6"/></svg>
                    </button>
                </div>

                <div class="mb-1 grid grid-cols-7 gap-0.5 text-center text-[11px] font-medium text-ink-500">
                    <span v-for="d in WEEKDAY_SHORT" :key="d" class="py-1">{{ d }}</span>
                </div>

                <div class="grid grid-cols-7 gap-0.5">
                    <template v-for="(day, i) in grid" :key="i">
                        <span v-if="day === null" />
                        <button
                            v-else
                            type="button"
                            :disabled="isDisabled(day)"
                            class="nums-tabular aspect-square rounded-md text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-30"
                            :class="isSelected(day)
                                ? 'bg-brand-600 font-semibold text-white'
                                : isToday(day)
                                    ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-200'
                                    : 'hover:bg-surface-100 dark:hover:bg-surface-800'"
                            @click="pick(day)"
                        >
                            {{ toPersianDigits(day) }}
                        </button>
                    </template>
                </div>

                <div class="mt-2 border-t border-surface-200 pt-2 dark:border-surface-800">
                    <button type="button" class="w-full rounded-md py-1.5 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-950" @click="selectToday">
                        امروز
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
