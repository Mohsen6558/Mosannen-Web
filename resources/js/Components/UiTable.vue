<script setup>
/**
 * Presentational table shell. Sorting and paging are server-driven — the
 * component only emits intent and renders what the controller returned.
 */
defineProps({
    columns: { type: Array, required: true }, // [{key,label,align,width,sortable,class}]
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    sort: { type: Object, default: () => ({ by: null, dir: 'asc' }) },
    loading: { type: Boolean, default: false },
    clickable: { type: Boolean, default: false },
});

const emit = defineEmits(['sort', 'rowClick']);

function toggleSort(col) {
    if (!col.sortable) return;
    emit('sort', col.key);
}
</script>

<template>
    <div class="relative overflow-x-auto">
        <div
            v-if="loading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 dark:bg-surface-900/60"
        >
            <svg class="size-5 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
            </svg>
        </div>

        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-surface-100 dark:border-surface-800">
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        scope="col"
                        class="px-4 pb-2.5 text-xs font-medium whitespace-nowrap text-ink-300"
                        :class="[
                            col.align === 'end' ? 'text-end' : col.align === 'center' ? 'text-center' : 'text-start',
                            col.sortable ? 'cursor-pointer select-none hover:text-ink-900 dark:hover:text-ink-50' : '',
                        ]"
                        :style="col.width ? { width: col.width } : null"
                        @click="toggleSort(col)"
                    >
                        <span class="inline-flex items-center gap-1">
                            {{ col.label }}
                            <svg
                                v-if="col.sortable"
                                class="size-3 transition-opacity"
                                :class="sort.by === col.key ? 'opacity-100 text-brand-600' : 'opacity-30'"
                                viewBox="0 0 12 12" fill="currentColor"
                            >
                                <path v-if="sort.by === col.key && sort.dir === 'desc'" d="M6 9L2 5h8z" />
                                <path v-else d="M6 3l4 4H2z" />
                            </svg>
                        </span>
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row[rowKey]"
                    class="border-b border-surface-100/80 transition-colors last:border-0 dark:border-surface-800/50"
                    :class="clickable ? 'cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800/40' : ''"
                    @click="clickable && emit('rowClick', row)"
                >
                    <td
                        v-for="col in columns"
                        :key="col.key"
                        class="px-4 py-3.5 align-middle"
                        :class="[
                            col.align === 'end' ? 'text-end' : col.align === 'center' ? 'text-center' : 'text-start',
                            col.class,
                        ]"
                    >
                        <slot :name="`cell:${col.key}`" :row="row" :value="row[col.key]">
                            {{ row[col.key] ?? '—' }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>

        <slot v-if="!rows.length && !loading" name="empty" />
    </div>
</template>
