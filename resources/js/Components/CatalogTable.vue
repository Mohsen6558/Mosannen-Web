<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiModal from '@/Components/UiModal.vue';
import { toPersianDigits } from '@/Support/format';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    rows: { type: Array, default: () => [] },
    storeRoute: { type: String, required: true },
    updateRoute: { type: String, required: true },
    // Extra fields beyond name/order/active: [{ key, label, type, hint }]
    fields: { type: Array, default: () => [] },
    defaults: { type: Object, default: () => ({}) },
    countKey: { type: String, default: null },
    countLabel: { type: String, default: '' },
});

const open = ref(false);
const editing = ref(null);

const form = useForm({ name: '', item_order: 0, is_active: true, ...props.defaults });

function edit(row = null) {
    editing.value = row;

    const values = { name: '', item_order: 0, is_active: true, ...props.defaults };

    if (row) {
        Object.keys(values).forEach((k) => {
            if (row[k] !== undefined) values[k] = row[k];
        });
    }

    form.defaults(values);
    form.reset();
    form.clearErrors();
    open.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    editing.value
        ? form.put(route(props.updateRoute, editing.value.id), options)
        : form.post(route(props.storeRoute), options);
}

defineExpose({ edit });
</script>

<template>
    <UiCard :title="title" :subtitle="subtitle" :padded="false">
        <template #actions>
            <UiButton size="sm" @click="edit()">افزودن</UiButton>
        </template>

        <ul v-if="rows.length" class="divide-y divide-surface-100 dark:divide-surface-800">
            <li v-for="row in rows" :key="row.id">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-start transition-colors hover:bg-surface-50 dark:hover:bg-surface-800/40"
                    @click="edit(row)"
                >
                    <span class="nums-tabular w-8 shrink-0 text-xs text-ink-300">{{ toPersianDigits(row.item_order) }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm" :class="row.is_active ? '' : 'text-ink-300 line-through'">
                        {{ row.name }}
                    </span>
                    <slot name="row" :row="row" />
                    <UiBadge v-if="countKey && row[countKey] !== undefined" class="nums-tabular">
                        {{ toPersianDigits(row[countKey]) }} {{ countLabel }}
                    </UiBadge>
                    <UiBadge v-if="!row.is_active" tone="neutral">غیرفعال</UiBadge>
                </button>
            </li>
        </ul>

        <UiEmpty v-else :title="`${title} ثبت نشده`" />
    </UiCard>

    <UiModal :show="open" size="sm" :title="editing ? `ویرایش ${title}` : `افزودن به ${title}`" @close="open = false">
        <form id="catalog-form" class="space-y-4" @submit.prevent="submit">
            <slot name="before-name" :form="form" />

            <UiField label="عنوان" :error="form.errors.name" required>
                <template #default="{ id, invalid }">
                    <UiInput :id="id" v-model="form.name" :invalid="invalid" autofocus />
                </template>
            </UiField>

            <slot name="fields" :form="form" />

            <UiField label="ترتیب نمایش" :error="form.errors.item_order">
                <template #default="{ id }">
                    <UiInput :id="id" v-model.number="form.item_order" type="number" min="0" dir="ltr" class="text-start nums-tabular" />
                </template>
            </UiField>

            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="size-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30">
                فعال
            </label>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="open = false">انصراف</UiButton>
            <UiButton type="submit" form="catalog-form" :loading="form.processing">ذخیره</UiButton>
        </template>
    </UiModal>
</template>
