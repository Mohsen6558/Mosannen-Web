<script setup>
import { computed, ref, watch } from 'vue';
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
import { summarize } from '@/Support/teeth';

const props = defineProps({
    treatments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ amount: 0 }) },
});

const { can } = usePermissions();

const q = ref(props.filters.q ?? '');
const debounced = refDebounced(q, 300);
const from = ref(props.filters.from ?? null);
const to = ref(props.filters.to ?? null);
const category = ref(props.filters.category ?? null);
const service = ref(props.filters.service ?? null);
const deleting = ref(null);

// Narrow the service list to the chosen category.
const serviceOptions = computed(() =>
    props.services
        .filter((s) => !category.value || String(s.treatment_category_id) === String(category.value))
        .map((s) => ({ value: s.id, label: s.name })),
);

watch(category, () => (service.value = null));
watch([debounced, from, to, category, service], () => reload());

function reload() {
    router.get(route('treatments.index'), {
        q: q.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        category: category.value || undefined,
        service: service.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
}

const columns = [
    { key: 'performed_on', label: 'تاریخ', width: '110px' },
    { key: 'patient', label: 'بیمار' },
    { key: 'service', label: 'درمان' },
    { key: 'teeth', label: 'دندان', width: '140px' },
    { key: 'user', label: 'پزشک', width: '150px' },
    { key: 'amount', label: 'مبلغ', align: 'end', width: '140px' },
    { key: 'actions', label: '', width: '48px' },
];

function destroy() {
    router.delete(route('treatments.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="درمان‌ها" />

    <PageHeader title="درمان‌ها" :subtitle="`${toPersianDigits(treatments.total)} رکورد`" />

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <UiStat label="تعداد درمان" :value="toPersianDigits(treatments.total)" />
            <UiStat label="مجموع مبلغ" :value="formatMoney(totals.amount, { suffix: false })" hint="ریال" tone="brand" />
        </div>

        <UiCard>
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <UiInput v-model="q" placeholder="جستجوی بیمار…" />
                </div>
                <div class="w-44">
                    <UiSelect v-model="category" :options="categories.map(c => ({ value: c.id, label: c.name }))" placeholder="همه گروه‌ها" />
                </div>
                <div class="w-52">
                    <UiSelect v-model="service" :options="serviceOptions" placeholder="همه خدمات" />
                </div>
                <DateRangeFilter v-model:from="from" v-model:to="to" />
            </div>
        </UiCard>

        <UiCard :padded="false">
            <UiTable :columns="columns" :rows="treatments.data">
                <template #cell:performed_on="{ value }">
                    <span class="nums-tabular text-ink-500">{{ jalali(value) }}</span>
                </template>

                <template #cell:patient="{ row }">
                    <Link :href="route('patients.show', row.patient.id)" class="font-medium hover:text-brand-600">
                        {{ row.patient.name }}
                    </Link>
                    <span class="nums-tabular ms-1 text-[11px] text-ink-300">{{ toPersianDigits(row.patient.code) }}</span>
                </template>

                <template #cell:service="{ row }">
                    <p>{{ row.service }}</p>
                    <p v-if="row.category" class="text-[11px] text-ink-500">{{ row.category }}</p>
                </template>

                <template #cell:teeth="{ value }">
                    <span v-if="value?.length" class="nums-tabular text-xs">{{ summarize(value, toPersianDigits) }}</span>
                    <span v-else class="text-ink-300">—</span>
                </template>

                <template #cell:user="{ value }">
                    <span class="text-xs text-ink-500">{{ value || '—' }}</span>
                </template>

                <template #cell:amount="{ value }">
                    <span class="nums-tabular font-medium">{{ formatMoney(value, { suffix: false }) }}</span>
                </template>

                <template #cell:actions="{ row }">
                    <button
                        v-if="can('treatments.delete')"
                        type="button"
                        class="rounded p-1 text-ink-300 transition-colors hover:text-danger-500"
                        aria-label="حذف درمان"
                        @click.stop="deleting = row"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M4 7h16M10 4h4a1 1 0 011 1v2H9V5a1 1 0 011-1z" /></svg>
                    </button>
                </template>

                <template #empty>
                    <UiEmpty title="درمانی یافت نشد" message="فیلترها را تغییر دهید یا از پرونده بیمار درمان جدید ثبت کنید." />
                </template>
            </UiTable>

            <template #footer>
                <UiPagination :meta="treatments" />
            </template>
        </UiCard>
    </div>

    <UiConfirm
        :show="!!deleting"
        title="حذف درمان"
        :message="deleting ? `درمان «${deleting.service}» برای ${deleting.patient.name} حذف شود؟` : ''"
        confirm-label="حذف"
        @confirm="destroy"
        @cancel="deleting = null"
    />
</template>
