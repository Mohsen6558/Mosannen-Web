<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiDatePicker from '@/Components/UiDatePicker.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiModal from '@/Components/UiModal.vue';
import UiPagination from '@/Components/UiPagination.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTable from '@/Components/UiTable.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { toPersianDigits } from '@/Support/format';
import { jalali, todayIso } from '@/Support/jalali';

const props = defineProps({
    items: { type: Array, default: () => [] },
    movements: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const from = ref(props.filters.from ?? null);
const to = ref(props.filters.to ?? null);
const itemModal = ref(false);
const moveModal = ref(false);
const editing = ref(null);

const lowStock = computed(() =>
    props.items.filter((i) => i.reorder_level > 0 && i.on_hand <= i.reorder_level),
);

const itemForm = useForm({ name: '', unit: '', reorder_level: 0, item_order: 0, is_active: true });
const moveForm = useForm({ stock_item_id: null, direction: 'out', quantity: 1, moved_on: todayIso(), description: '' });

function openItem(item = null) {
    editing.value = item;
    itemForm.defaults({
        name: item?.name ?? '',
        unit: item?.unit ?? '',
        reorder_level: item?.reorder_level ?? 0,
        item_order: item?.item_order ?? 0,
        is_active: item?.is_active ?? true,
    });
    itemForm.reset();
    itemModal.value = true;
}

function submitItem() {
    const options = { preserveScroll: true, onSuccess: () => (itemModal.value = false) };

    editing.value
        ? itemForm.put(route('stock.items.update', editing.value.id), options)
        : itemForm.post(route('stock.items.store'), options);
}

function submitMovement() {
    moveForm.post(route('stock.movements.store'), {
        preserveScroll: true,
        onSuccess: () => {
            moveForm.reset('quantity', 'description');
            moveModal.value = false;
        },
    });
}

function reload() {
    router.get(route('stock.index'), {
        from: from.value || undefined,
        to: to.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
}

const columns = [
    { key: 'moved_on', label: 'تاریخ', width: '110px' },
    { key: 'item', label: 'کالا' },
    { key: 'direction', label: 'نوع', width: '90px' },
    { key: 'quantity', label: 'تعداد', align: 'end', width: '90px' },
    { key: 'user', label: 'ثبت‌کننده', width: '140px' },
    { key: 'description', label: 'توضیحات' },
];
</script>

<template>
    <Head title="انبار" />

    <PageHeader title="انبار" :subtitle="`${toPersianDigits(items.length)} قلم کالا`">
        <template #actions>
            <UiButton v-if="can('stock.manage')" variant="secondary" @click="openItem()">کالای جدید</UiButton>
            <UiButton v-if="can('stock.manage')" @click="moveModal = true">ثبت ورود/خروج</UiButton>
        </template>
    </PageHeader>

    <div class="space-y-4">
        <div
            v-if="lowStock.length"
            class="rounded-lg border border-warning-500/30 bg-warning-50 px-4 py-3 dark:bg-warning-600/10"
        >
            <p class="mb-2 text-sm font-medium text-warning-600">
                {{ toPersianDigits(lowStock.length) }} قلم کالا به حد سفارش رسیده است
            </p>
            <div class="flex flex-wrap gap-2">
                <UiBadge v-for="i in lowStock" :key="i.id" tone="warning" class="nums-tabular">
                    {{ i.name }}: {{ toPersianDigits(i.on_hand) }} {{ i.unit || '' }}
                </UiBadge>
            </div>
        </div>

        <UiCard title="موجودی کالاها">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <button
                    v-for="i in items"
                    :key="i.id"
                    type="button"
                    :disabled="!can('stock.manage')"
                    class="rounded-lg border border-surface-200 px-3 py-2.5 text-start transition-colors enabled:hover:border-brand-400 disabled:cursor-default dark:border-surface-800"
                    @click="openItem(i)"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="truncate text-sm font-medium">{{ i.name }}</p>
                        <span
                            class="nums-tabular shrink-0 text-sm font-bold"
                            :class="i.reorder_level > 0 && i.on_hand <= i.reorder_level ? 'text-warning-600' : 'text-ink-900 dark:text-ink-50'"
                        >
                            {{ toPersianDigits(i.on_hand) }}
                        </span>
                    </div>
                    <p class="nums-tabular mt-0.5 text-[11px] text-ink-500">
                        {{ i.unit || 'عدد' }}
                        <span v-if="i.reorder_level > 0"> — حد سفارش: {{ toPersianDigits(i.reorder_level) }}</span>
                    </p>
                </button>
            </div>

            <UiEmpty v-if="!items.length" title="کالایی ثبت نشده" />
        </UiCard>

        <UiCard title="گردش انبار" :padded="false">
            <template #actions>
                <DateRangeFilter v-model:from="from" v-model:to="to" @update:from="reload" @update:to="reload" />
            </template>

            <UiTable :columns="columns" :rows="movements.data">
                <template #cell:moved_on="{ value }">
                    <span class="nums-tabular text-ink-500">{{ jalali(value) }}</span>
                </template>

                <template #cell:item="{ row }">
                    <span class="font-medium">{{ row.item }}</span>
                </template>

                <template #cell:direction="{ value }">
                    <UiBadge :tone="value === 'in' ? 'success' : 'danger'">
                        {{ value === 'in' ? 'ورود' : 'خروج' }}
                    </UiBadge>
                </template>

                <template #cell:quantity="{ row }">
                    <span class="nums-tabular font-medium">
                        {{ row.direction === 'in' ? '+' : '−' }}{{ toPersianDigits(row.quantity) }}
                    </span>
                    <span class="text-[11px] text-ink-300"> {{ row.unit || '' }}</span>
                </template>

                <template #cell:user="{ value }">
                    <span class="text-xs text-ink-500">{{ value || '—' }}</span>
                </template>

                <template #cell:description="{ value }">
                    <span class="text-xs text-ink-500">{{ value || '—' }}</span>
                </template>

                <template #empty>
                    <UiEmpty title="گردشی ثبت نشده" />
                </template>
            </UiTable>

            <template #footer>
                <UiPagination :meta="movements" />
            </template>
        </UiCard>
    </div>

    <!-- Item -->
    <UiModal :show="itemModal" size="sm" :title="editing ? 'ویرایش کالا' : 'کالای جدید'" @close="itemModal = false">
        <form id="item-form" class="space-y-4" @submit.prevent="submitItem">
            <UiField label="نام کالا" :error="itemForm.errors.name" required>
                <template #default="{ id, invalid }">
                    <UiInput :id="id" v-model="itemForm.name" :invalid="invalid" autofocus />
                </template>
            </UiField>

            <div class="grid gap-4 sm:grid-cols-2">
                <UiField label="واحد" :error="itemForm.errors.unit">
                    <template #default="{ id }">
                        <UiInput :id="id" v-model="itemForm.unit" placeholder="عدد، بسته، جعبه…" />
                    </template>
                </UiField>

                <UiField label="حد سفارش" :error="itemForm.errors.reorder_level" hint="۰ یعنی هشدار ندهد">
                    <template #default="{ id }">
                        <UiInput :id="id" v-model.number="itemForm.reorder_level" type="number" min="0" dir="ltr" class="text-start nums-tabular" />
                    </template>
                </UiField>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input v-model="itemForm.is_active" type="checkbox" class="size-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30">
                فعال
            </label>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="itemModal = false">انصراف</UiButton>
            <UiButton type="submit" form="item-form" :loading="itemForm.processing">ذخیره</UiButton>
        </template>
    </UiModal>

    <!-- Movement -->
    <UiModal :show="moveModal" size="sm" title="ثبت ورود/خروج کالا" @close="moveModal = false">
        <form id="move-form" class="space-y-4" @submit.prevent="submitMovement">
            <UiField label="کالا" :error="moveForm.errors.stock_item_id" required>
                <template #default="{ id, invalid }">
                    <UiSelect
                        :id="id"
                        v-model="moveForm.stock_item_id"
                        :invalid="invalid"
                        :options="items.map(i => ({ value: i.id, label: `${i.name} (موجودی: ${toPersianDigits(i.on_hand)})` }))"
                    />
                </template>
            </UiField>

            <UiField label="نوع" required>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="d in [{ v: 'in', l: 'ورود به انبار' }, { v: 'out', l: 'خروج از انبار' }]"
                        :key="d.v"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                        :class="moveForm.direction === d.v
                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-200'
                            : 'border-surface-300 text-ink-500 hover:bg-surface-100 dark:border-surface-700 dark:hover:bg-surface-800'"
                        @click="moveForm.direction = d.v"
                    >
                        {{ d.l }}
                    </button>
                </div>
            </UiField>

            <div class="grid gap-4 sm:grid-cols-2">
                <UiField label="تعداد" :error="moveForm.errors.quantity" required>
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model.number="moveForm.quantity" :invalid="invalid" type="number" min="1" dir="ltr" class="text-start nums-tabular" />
                    </template>
                </UiField>

                <UiField label="تاریخ" :error="moveForm.errors.moved_on" required>
                    <template #default="{ invalid }">
                        <UiDatePicker v-model="moveForm.moved_on" :invalid="invalid" :max="todayIso()" :clearable="false" />
                    </template>
                </UiField>
            </div>

            <UiField label="توضیحات" :error="moveForm.errors.description">
                <template #default="{ id }">
                    <UiTextarea :id="id" v-model="moveForm.description" :rows="2" />
                </template>
            </UiField>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="moveModal = false">انصراف</UiButton>
            <UiButton type="submit" form="move-form" :loading="moveForm.processing">ثبت</UiButton>
        </template>
    </UiModal>
</template>
