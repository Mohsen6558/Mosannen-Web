<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { refDebounced } from '@vueuse/core';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiCard from '@/Components/UiCard.vue';
import UiConfirm from '@/Components/UiConfirm.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiInput from '@/Components/UiInput.vue';
import UiPagination from '@/Components/UiPagination.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiStat from '@/Components/UiStat.vue';
import UiTable from '@/Components/UiTable.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatMoney, toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const props = defineProps({
    payments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    paymentTypes: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
});

const { can } = usePermissions();

const q = ref(props.filters.q ?? '');
const debounced = refDebounced(q, 300);
const from = ref(props.filters.from ?? null);
const to = ref(props.filters.to ?? null);
const type = ref(props.filters.type ?? null);
const deleting = ref(null);

watch([debounced, from, to, type], () => {
    router.get(route('payments.index'), {
        q: q.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        type: type.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
});

const columns = [
    { key: 'paid_on', label: 'تاریخ', width: '130px' },
    { key: 'patient', label: 'بیمار' },
    { key: 'type', label: 'نحوه پرداخت', width: '140px' },
    { key: 'user', label: 'ثبت‌کننده', width: '140px' },
    { key: 'discount', label: 'تخفیف', align: 'end', width: '120px' },
    { key: 'amount', label: 'مبلغ', align: 'end', width: '140px' },
    { key: 'actions', label: '', width: '48px' },
];

function destroy() {
    router.delete(route('payments.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="پرداخت‌ها" />

    <PageHeader title="پرداخت‌ها" :subtitle="`${toPersianDigits(totals.count)} تراکنش در بازه انتخابی`" />

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UiStat label="مجموع دریافتی" :value="formatMoney(totals.amount, { suffix: false })" hint="ریال" tone="success" />
            <UiStat label="مجموع تخفیف" :value="formatMoney(totals.discount, { suffix: false })" hint="ریال" tone="warning" />
            <UiStat label="تعداد تراکنش" :value="toPersianDigits(totals.count)" />
        </div>

        <UiCard v-if="totals.by_type?.length" title="تفکیک بر اساس نحوه پرداخت">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="t in totals.by_type"
                    :key="t.type"
                    class="rounded-lg border border-surface-200 px-3 py-2.5 dark:border-surface-800"
                >
                    <p class="text-xs text-ink-500">{{ t.type }}</p>
                    <p class="nums-tabular mt-1 font-semibold">{{ formatMoney(t.total, { suffix: false }) }}</p>
                    <p class="nums-tabular text-[11px] text-ink-300">{{ toPersianDigits(t.count) }} تراکنش</p>
                </div>
            </div>
        </UiCard>

        <UiCard>
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <UiInput v-model="q" placeholder="جستجوی بیمار…" />
                </div>
                <div class="w-44">
                    <UiSelect v-model="type" :options="paymentTypes.map(t => ({ value: t.id, label: t.name }))" placeholder="همه روش‌ها" />
                </div>
                <DateRangeFilter v-model:from="from" v-model:to="to" />
            </div>
        </UiCard>

        <UiCard :padded="false">
            <UiTable :columns="columns" :rows="payments.data">
                <template #cell:paid_on="{ row }">
                    <span class="nums-tabular text-ink-500">{{ jalali(row.paid_on) }}</span>
                    <span v-if="row.paid_at" class="nums-tabular ms-1 text-[11px] text-ink-300">
                        {{ toPersianDigits(String(row.paid_at).slice(0, 5)) }}
                    </span>
                </template>

                <template #cell:patient="{ row }">
                    <Link :href="route('patients.show', row.patient.id)" class="font-medium hover:text-brand-600">
                        {{ row.patient.name }}
                    </Link>
                    <span class="nums-tabular ms-1 text-[11px] text-ink-300">{{ toPersianDigits(row.patient.code) }}</span>
                </template>

                <template #cell:type="{ value }">
                    <span :class="value ? '' : 'text-ink-300'">{{ value || '—' }}</span>
                </template>

                <template #cell:user="{ value }">
                    <span class="text-xs text-ink-500">{{ value || '—' }}</span>
                </template>

                <template #cell:discount="{ value }">
                    <span class="nums-tabular" :class="value ? 'text-warning-600' : 'text-ink-300'">
                        {{ value ? formatMoney(value, { suffix: false }) : '—' }}
                    </span>
                </template>

                <template #cell:amount="{ value }">
                    <span class="nums-tabular font-medium text-success-600">{{ formatMoney(value, { suffix: false }) }}</span>
                </template>

                <template #cell:actions="{ row }">
                    <button
                        v-if="can('payments.delete')"
                        type="button"
                        class="rounded p-1 text-ink-300 transition-colors hover:text-danger-500"
                        aria-label="حذف پرداخت"
                        @click.stop="deleting = row"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M4 7h16M10 4h4a1 1 0 011 1v2H9V5a1 1 0 011-1z" /></svg>
                    </button>
                </template>

                <template #empty>
                    <UiEmpty title="پرداختی یافت نشد" message="بازه تاریخ یا فیلترها را تغییر دهید." />
                </template>
            </UiTable>

            <template #footer>
                <UiPagination :meta="payments" />
            </template>
        </UiCard>
    </div>

    <UiConfirm
        :show="!!deleting"
        title="حذف پرداخت"
        :message="deleting ? `پرداخت ${formatMoney(deleting.amount)} از ${deleting.patient.name} حذف شود؟` : ''"
        confirm-label="حذف"
        @confirm="destroy"
        @cancel="deleting = null"
    />
</template>
