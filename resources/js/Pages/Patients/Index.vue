<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { refDebounced } from '@vueuse/core';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiInput from '@/Components/UiInput.vue';
import UiPagination from '@/Components/UiPagination.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTable from '@/Components/UiTable.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatMoney, toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const props = defineProps({
    patients: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    insurances: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const search = ref(props.filters.q ?? '');
const debounced = refDebounced(search, 300);
const insurance = ref(props.filters.insurance ?? null);
const debtorsOnly = ref(props.filters.debtors === '1');

const sort = computed(() => ({
    by: props.filters.sort ?? 'created_at',
    dir: props.filters.dir ?? 'desc',
}));

watch([debounced, insurance, debtorsOnly], () => reload());

function reload(extra = {}) {
    router.get(route('patients.index'), {
        q: search.value || undefined,
        insurance: insurance.value || undefined,
        debtors: debtorsOnly.value ? '1' : undefined,
        sort: sort.value.by,
        dir: sort.value.dir,
        ...extra,
    }, { preserveState: true, replace: true, preserveScroll: true });
}

function toggleSort(key) {
    reload({
        sort: key,
        dir: sort.value.by === key && sort.value.dir === 'asc' ? 'desc' : 'asc',
    });
}

const columns = [
    { key: 'code', label: 'کد پرونده', sortable: true, width: '110px' },
    { key: 'full_name', label: 'نام بیمار', sortable: false },
    { key: 'mobile', label: 'موبایل', width: '130px' },
    { key: 'insurance', label: 'بیمه', width: '140px' },
    { key: 'registered_on', label: 'تاریخ ثبت', sortable: true, width: '120px' },
    { key: 'balance', label: 'مانده', align: 'end', width: '150px' },
];
</script>

<template>
    <Head title="پرونده بیماران" />

    <PageHeader title="پرونده بیماران" :subtitle="`${toPersianDigits(patients.total)} پرونده`">
        <template #actions>
            <UiButton v-if="can('patients.create')" :href="route('patients.create')">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                </template>
                بیمار جدید
            </UiButton>
        </template>
    </PageHeader>

    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="min-w-56 flex-1">
                <UiInput v-model="search" placeholder="جستجو بر اساس نام، کد پرونده، موبایل یا کد ملی…">
                    <template #prefix>
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                    </template>
                </UiInput>
            </div>

            <div class="w-44">
                <UiSelect v-model="insurance" :options="insurances.map(i => ({ value: i.id, label: i.name }))" placeholder="همه بیمه‌ها" />
            </div>

            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-surface-300 px-3 py-2 text-sm dark:border-surface-700">
                <input v-model="debtorsOnly" type="checkbox" class="size-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30">
                فقط بدهکاران
            </label>

        </div>

        <UiCard :padded="false">
            <UiTable
                :columns="columns"
                :rows="patients.data"
                :sort="sort"
                clickable
                @sort="toggleSort"
                @row-click="(row) => router.visit(route('patients.show', row.id))"
            >
                <template #cell:code="{ value }">
                    <span class="nums-tabular font-medium">{{ toPersianDigits(value) }}</span>
                </template>

                <template #cell:full_name="{ row }">
                    <Link :href="route('patients.show', row.id)" class="font-medium hover:text-brand-600" @click.stop>
                        {{ row.full_name }}
                    </Link>
                </template>

                <template #cell:mobile="{ value }">
                    <span v-if="value" class="nums-tabular" dir="ltr">{{ toPersianDigits(value) }}</span>
                    <span v-else class="text-ink-300">—</span>
                </template>

                <template #cell:insurance="{ value }">
                    <span :class="value ? '' : 'text-ink-300'">{{ value || 'آزاد' }}</span>
                </template>

                <template #cell:registered_on="{ value }">
                    <span class="nums-tabular text-ink-500">{{ jalali(value) }}</span>
                </template>

                <template #cell:balance="{ value }">
                    <span
                        class="nums-tabular font-medium"
                        :class="value > 0 ? 'text-danger-500' : value < 0 ? 'text-success-600' : 'text-ink-300'"
                    >
                        {{ value === 0 ? '—' : formatMoney(Math.abs(value), { suffix: false }) }}
                    </span>
                </template>

                <template #empty>
                    <UiEmpty
                        title="بیماری یافت نشد"
                        :message="search ? 'عبارت جستجو را تغییر دهید.' : 'هنوز پرونده‌ای ثبت نشده است.'"
                    >
                        <template v-if="can('patients.create') && !search" #action>
                            <UiButton :href="route('patients.create')">ثبت اولین بیمار</UiButton>
                        </template>
                    </UiEmpty>
                </template>
            </UiTable>

            <template #footer>
                <UiPagination :meta="patients" />
            </template>
        </UiCard>
    </div>
</template>
