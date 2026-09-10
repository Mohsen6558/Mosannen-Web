<script setup>
import { computed, ref } from 'vue';
import { PERMANENT, PRIMARY, isPrimary, toothName } from '@/Support/teeth';
import { toPersianDigits } from '@/Support/format';

/**
 * Interactive dental chart.
 *
 * Replaces the legacy frmTooth, which was 52 WinForms checkboxes whose
 * control names were concatenated into a comma-separated string. Here the
 * value is an array of FDI codes and the layout is anatomical, so the
 * operator clicks the tooth rather than hunting for a checkbox.
 *
 * Orientation follows clinical convention: the chart is drawn as the
 * clinician faces the patient, so the patient's right side appears on the
 * viewer's left. In an RTL document that means the upper-right quadrant
 * renders first in visual order from the left.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    // Teeth with existing work, shown tinted but still selectable.
    treated: { type: Array, default: () => [] },
    planned: { type: Array, default: () => [] },
    missing: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
    showPrimary: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const hovered = ref(null);

const selected = computed(() => new Set(props.modelValue.map(String)));
const treatedSet = computed(() => new Set(props.treated.map(String)));
const plannedSet = computed(() => new Set(props.planned.map(String)));
const missingSet = computed(() => new Set(props.missing.map(String)));

function toggle(code) {
    if (props.readonly) return;

    const next = new Set(selected.value);
    next.has(code) ? next.delete(code) : next.add(code);
    emit('update:modelValue', [...next].sort((a, b) => Number(a) - Number(b)));
}

function toggleRow(codes) {
    if (props.readonly) return;

    const next = new Set(selected.value);
    const allOn = codes.every((c) => next.has(c));
    codes.forEach((c) => (allOn ? next.delete(c) : next.add(c)));
    emit('update:modelValue', [...next].sort((a, b) => Number(a) - Number(b)));
}

function clearAll() {
    emit('update:modelValue', []);
}

function state(code) {
    if (selected.value.has(code)) return 'selected';
    if (missingSet.value.has(code)) return 'missing';
    if (plannedSet.value.has(code)) return 'planned';
    if (treatedSet.value.has(code)) return 'treated';
    return 'healthy';
}

const FILL = {
    selected: 'fill-[var(--color-tooth-selected)]',
    treated: 'fill-[var(--color-tooth-treated)]',
    planned: 'fill-[var(--color-tooth-planned)]',
    missing: 'fill-[var(--color-tooth-missing)]',
    healthy: 'fill-white dark:fill-surface-800',
};

const TEXT = {
    selected: 'fill-white',
    treated: 'fill-white',
    planned: 'fill-white',
    missing: 'fill-white',
    healthy: 'fill-ink-700 dark:fill-ink-100',
};

// Visual order left→right for each row, clinician's view.
const rows = computed(() => {
    const out = [
        { key: 'permanent-upper', label: 'فک بالا — دائمی', codes: [...[...PERMANENT.UR].reverse(), ...PERMANENT.UL] },
    ];

    if (props.showPrimary) {
        out.push({ key: 'primary-upper', label: 'فک بالا — شیری', codes: [...[...PRIMARY.UR].reverse(), ...PRIMARY.UL] });
        out.push({ key: 'primary-lower', label: 'فک پایین — شیری', codes: [...[...PRIMARY.LR].reverse(), ...PRIMARY.LL] });
    }

    out.push({ key: 'permanent-lower', label: 'فک پایین — دائمی', codes: [...[...PERMANENT.LR].reverse(), ...PERMANENT.LL] });

    return out;
});

const size = computed(() => (props.compact ? 26 : 34));
const gap = computed(() => (props.compact ? 3 : 4));

function xFor(index, count) {
    // Centre each row, leaving a wider gap at the midline between quadrants.
    const midlineExtra = index >= count / 2 ? gap.value * 3 : 0;
    return index * (size.value + gap.value) + midlineExtra;
}

function rowWidth(count) {
    return count * (size.value + gap.value) + gap.value * 3;
}
</script>

<template>
    <div class="select-none">
        <div v-if="!readonly" class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-ink-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-[var(--color-tooth-selected)]" /> انتخاب‌شده
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-[var(--color-tooth-treated)]" /> درمان‌شده
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-[var(--color-tooth-planned)]" /> برنامه‌ریزی‌شده
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-[var(--color-tooth-missing)]" /> کشیده‌شده
                </span>
            </div>

            <button
                v-if="modelValue.length"
                type="button"
                class="text-xs font-medium text-danger-500 hover:underline"
                @click="clearAll"
            >
                پاک کردن انتخاب‌ها ({{ toPersianDigits(modelValue.length) }})
            </button>
        </div>

        <div
            class="overflow-x-auto"
            :class="readonly ? 'py-1' : 'rounded-lg bg-surface-100/60 p-4 dark:bg-surface-950'"
        >
            <div class="mx-auto w-fit space-y-2">
                <div v-for="row in rows" :key="row.key" class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="readonly"
                        class="w-28 shrink-0 text-end text-[10px] text-ink-500 transition-colors enabled:hover:text-brand-600 disabled:cursor-default"
                        :title="readonly ? null : 'انتخاب یا لغو کل ردیف'"
                        @click="toggleRow(row.codes)"
                    >
                        {{ row.label }}
                    </button>

                    <svg
                        :width="rowWidth(row.codes.length)"
                        :height="size + 16"
                        :viewBox="`0 0 ${rowWidth(row.codes.length)} ${size + 16}`"
                        class="overflow-visible"
                        role="group"
                        :aria-label="row.label"
                    >
                        <g v-for="(code, i) in row.codes" :key="code">
                            <!-- Tooth body: crown outline, slightly narrowed at the neck. -->
                            <rect
                                :x="xFor(i, row.codes.length)"
                                y="8"
                                :width="size"
                                :height="size"
                                :rx="isPrimary(code) ? size * 0.3 : size * 0.22"
                                class="cursor-pointer stroke-surface-300 transition-all duration-100 dark:stroke-surface-600"
                                :class="[
                                    FILL[state(code)],
                                    readonly ? 'cursor-default' : 'hover:stroke-brand-500 hover:stroke-2',
                                    hovered === code ? 'stroke-brand-500 stroke-2' : '',
                                ]"
                                stroke-width="1.2"
                                role="checkbox"
                                :aria-checked="selected.has(code)"
                                :aria-label="`دندان ${code} — ${toothName(code)}`"
                                tabindex="0"
                                @click="toggle(code)"
                                @keydown.enter.prevent="toggle(code)"
                                @keydown.space.prevent="toggle(code)"
                                @mouseenter="hovered = code"
                                @mouseleave="hovered = null"
                            />

                            <text
                                :x="xFor(i, row.codes.length) + size / 2"
                                :y="8 + size / 2 + 4"
                                text-anchor="middle"
                                class="pointer-events-none text-[11px] font-medium"
                                :class="TEXT[state(code)]"
                            >
                                {{ toPersianDigits(code) }}
                            </text>
                        </g>
                    </svg>
                </div>
            </div>
        </div>

        <p
            v-if="hovered"
            class="mt-2 text-center text-xs text-ink-500"
            aria-live="polite"
        >
            دندان <span class="font-medium text-ink-900 dark:text-ink-50">{{ toPersianDigits(hovered) }}</span>
            — {{ toothName(hovered) }}
        </p>
    </div>
</template>
