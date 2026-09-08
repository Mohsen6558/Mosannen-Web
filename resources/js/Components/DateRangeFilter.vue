<script setup>
import UiDatePicker from '@/Components/UiDatePicker.vue';
import { todayIso } from '@/Support/jalali';

defineProps({
    from: { type: String, default: null },
    to: { type: String, default: null },
});

const emit = defineEmits(['update:from', 'update:to']);

/** Quick ranges staff reach for constantly. */
const PRESETS = [
    { label: 'امروز', days: 0 },
    { label: '۷ روز', days: 6 },
    { label: '۳۰ روز', days: 29 },
    { label: '۹۰ روز', days: 89 },
];

function applyPreset(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - days);
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    emit('update:from', iso(from));
    emit('update:to', iso(to));
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div class="w-40">
            <UiDatePicker :model-value="from" placeholder="از تاریخ" @update:model-value="emit('update:from', $event)" />
        </div>
        <span class="text-xs text-ink-300">تا</span>
        <div class="w-40">
            <UiDatePicker :model-value="to" placeholder="تا تاریخ" :max="todayIso()" @update:model-value="emit('update:to', $event)" />
        </div>

        <div class="flex gap-1">
            <button
                v-for="p in PRESETS"
                :key="p.label"
                type="button"
                class="rounded-md px-2.5 py-1.5 text-xs font-medium text-ink-500 transition-colors hover:bg-surface-200 hover:text-ink-900 dark:hover:bg-surface-800 dark:hover:text-ink-50"
                @click="applyPreset(p.days)"
            >
                {{ p.label }}
            </button>
        </div>
    </div>
</template>
