<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiStat from '@/Components/UiStat.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatMoney, toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const props = defineProps({
    filters: { type: Object, required: true },
    canSeeMoney: { type: Boolean, default: false },
    financial: { type: Object, default: null },
    services: { type: Array, default: () => [] },
    radiographs: { type: Array, default: () => [] },
    patients: { type: Object, default: () => ({ new_count: 0, by_insurance: [] }) },
    debtors: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const report = ref(props.filters.report ?? 'financial');

const REPORTS = computed(() => [
    ...(props.canSeeMoney ? [{ key: 'financial', label: 'گزارش مالی' }] : []),
    { key: 'services', label: 'گزارش زیردرمان' },
    { key: 'radiographs', label: 'گزارش عکس‌برداری' },
    { key: 'patients', label: 'گزارش بیماران' },
    ...(props.canSeeMoney ? [{ key: 'debtors', label: 'بدهکاران' }] : []),
]);

watch([from, to], () => {
    router.get(route('reports.index'), {
        from: from.value || undefined,
        to: to.value || undefined,
        report: report.value,
    }, { preserveState: true, replace: true, preserveScroll: true });
});

// Max value in a series, for drawing proportional bars.
const maxDaily = computed(() => Math.max(...(props.financial?.daily ?? []).map((d) => d.total), 1));
const maxRadio = computed(() => Math.max(...props.radiographs.map((d) => d.count), 1));
</script>

<template>
    <Head title="گزارش‌ها" />

    <PageHeader title="گزارش‌ها" :subtitle="`${jalali(filters.from, { full: true })} تا ${jalali(filters.to, { full: true })}`">
        <template #actions>
            <DateRangeFilter v-model:from="from" v-model:to="to" />
        </template>
    </PageHeader>

    <div class="space-y-4">
        <div class="flex flex-wrap gap-1 border-b border-surface-200 dark:border-surface-800">
            <button
                v-for="r in REPORTS"
                :key="r.key"
                type="button"
                class="relative px-4 py-2.5 text-sm font-medium transition-colors"
                :class="report === r.key ? 'text-brand-700 dark:text-brand-300' : 'text-ink-500 hover:text-ink-900 dark:hover:text-ink-50'"
                @click="report = r.key"
            >
                {{ r.label }}
                <span v-if="report === r.key" class="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-brand-600" />
            </button>
        </div>

        <!-- Financial -->
        <template v-if="report === 'financial' && financial">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <UiStat label="مجموع دریافتی" :value="formatMoney(financial.summary.total, { suffix: false })" hint="ریال" tone="success" />
                <UiStat label="مجموع تخفیف" :value="formatMoney(financial.summary.discount, { suffix: false })" hint="ریال" tone="warning" />
                <UiStat label="مجموع درمان" :value="formatMoney(financial.summary.billed, { suffix: false })" hint="ریال" tone="brand" />
                <UiStat
                    label="اختلاف"
                    :value="formatMoney(Math.abs(financial.summary.billed - financial.summary.total - financial.summary.discount), { suffix: false })"
                    hint="ریال"
                />
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <UiCard class="lg:col-span-2" title="درآمد روزانه" :padded="false">
                    <div v-if="financial.daily.length" class="max-h-96 overflow-y-auto">
                        <table class="w-full text-sm">
                            <tbody>
                                <tr v-for="d in financial.daily" :key="d.date" class="border-b border-surface-100 last:border-0 dark:border-surface-800/60">
                                    <td class="nums-tabular px-4 py-2.5 whitespace-nowrap text-ink-500">{{ jalali(d.date) }}</td>
                                    <td class="w-full px-2 py-2.5">
                                        <div class="h-2 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                                            <div class="h-full rounded-full bg-brand-500" :style="{ width: `${(d.total / maxDaily) * 100}%` }" />
                                        </div>
                                    </td>
                                    <td class="nums-tabular px-4 py-2.5 text-end whitespace-nowrap">{{ formatMoney(d.total, { suffix: false }) }}</td>
                                    <td class="nums-tabular px-2 py-2.5 text-end text-[11px] whitespace-nowrap text-ink-300">
                                        {{ toPersianDigits(d.count) }} تراکنش
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <UiEmpty v-else title="داده‌ای در این بازه نیست" />
                </UiCard>

                <UiCard title="تفکیک نحوه پرداخت">
                    <ul v-if="financial.by_type.length" class="space-y-3">
                        <li v-for="t in financial.by_type" :key="t.type">
                            <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                                <span class="truncate">{{ t.type }}</span>
                                <span class="nums-tabular shrink-0 font-medium">{{ formatMoney(t.total, { suffix: false }) }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                                <div class="h-full rounded-full bg-brand-500"
                                     :style="{ width: `${(t.total / Math.max(...financial.by_type.map(x => x.total), 1)) * 100}%` }" />
                            </div>
                        </li>
                    </ul>
                    <UiEmpty v-else title="داده‌ای نیست" />
                </UiCard>
            </div>
        </template>

        <!-- Services -->
        <UiCard v-else-if="report === 'services'" title="خدمات انجام‌شده" :padded="false">
            <table v-if="services.length" class="w-full text-sm">
                <thead class="border-b border-surface-200 dark:border-surface-800">
                    <tr class="text-xs text-ink-500">
                        <th class="px-4 py-3 text-start font-semibold">خدمت</th>
                        <th class="px-4 py-3 text-start font-semibold">گروه</th>
                        <th class="px-4 py-3 text-end font-semibold">تعداد</th>
                        <th v-if="canSeeMoney" class="px-4 py-3 text-end font-semibold">مجموع مبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(s, i) in services" :key="i" class="border-b border-surface-100 last:border-0 dark:border-surface-800/60">
                        <td class="px-4 py-2.5 font-medium">{{ s.name }}</td>
                        <td class="px-4 py-2.5 text-xs text-ink-500">{{ s.category || '—' }}</td>
                        <td class="nums-tabular px-4 py-2.5 text-end">{{ toPersianDigits(s.count) }}</td>
                        <td v-if="canSeeMoney" class="nums-tabular px-4 py-2.5 text-end">{{ formatMoney(s.total, { suffix: false }) }}</td>
                    </tr>
                </tbody>
            </table>
            <UiEmpty v-else title="درمانی در این بازه ثبت نشده" />
        </UiCard>

        <!-- Radiographs -->
        <UiCard v-else-if="report === 'radiographs'" title="عکس‌برداری روزانه" :padded="false">
            <table v-if="radiographs.length" class="w-full text-sm">
                <tbody>
                    <tr v-for="d in radiographs" :key="d.date" class="border-b border-surface-100 last:border-0 dark:border-surface-800/60">
                        <td class="nums-tabular px-4 py-2.5 whitespace-nowrap text-ink-500">{{ jalali(d.date) }}</td>
                        <td class="w-full px-2 py-2.5">
                            <div class="h-2 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                                <div class="h-full rounded-full bg-brand-500" :style="{ width: `${(d.count / maxRadio) * 100}%` }" />
                            </div>
                        </td>
                        <td class="nums-tabular px-4 py-2.5 text-end whitespace-nowrap">{{ toPersianDigits(d.count) }} تصویر</td>
                    </tr>
                </tbody>
            </table>
            <UiEmpty v-else title="تصویری در این بازه ثبت نشده" />
        </UiCard>

        <!-- Patients -->
        <template v-else-if="report === 'patients'">
            <div class="grid gap-4 lg:grid-cols-3">
                <UiStat label="پرونده‌های جدید" :value="toPersianDigits(patients.new_count)" tone="brand" />
            </div>

            <UiCard title="تفکیک بر اساس بیمه">
                <ul v-if="patients.by_insurance.length" class="space-y-3">
                    <li v-for="(row, i) in patients.by_insurance" :key="i">
                        <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                            <span>{{ row.insurance }}</span>
                            <span class="nums-tabular font-medium">{{ toPersianDigits(row.count) }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                            <div class="h-full rounded-full bg-brand-500"
                                 :style="{ width: `${(row.count / Math.max(...patients.by_insurance.map(x => x.count), 1)) * 100}%` }" />
                        </div>
                    </li>
                </ul>
                <UiEmpty v-else title="پرونده جدیدی در این بازه ثبت نشده" />
            </UiCard>
        </template>

        <!-- Debtors -->
        <UiCard v-else-if="report === 'debtors'" title="بدهکاران" subtitle="بیمارانی که مانده حساب بدهکار دارند" :padded="false">
            <template #actions>
                <UiButton v-if="can('reports.export')" variant="secondary" size="sm" :href="route('reports.debtors.export')">
                    خروجی CSV
                </UiButton>
            </template>

            <table v-if="debtors.length" class="w-full text-sm">
                <thead class="border-b border-surface-200 dark:border-surface-800">
                    <tr class="text-xs text-ink-500">
                        <th class="px-4 py-3 text-start font-semibold">کد</th>
                        <th class="px-4 py-3 text-start font-semibold">بیمار</th>
                        <th class="px-4 py-3 text-start font-semibold">موبایل</th>
                        <th class="px-4 py-3 text-end font-semibold">درمان</th>
                        <th class="px-4 py-3 text-end font-semibold">پرداختی</th>
                        <th class="px-4 py-3 text-end font-semibold">مانده</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in debtors" :key="d.id" class="border-b border-surface-100 last:border-0 dark:border-surface-800/60">
                        <td class="nums-tabular px-4 py-2.5 text-ink-500">{{ toPersianDigits(d.code) }}</td>
                        <td class="px-4 py-2.5">
                            <Link :href="route('patients.show', d.id)" class="font-medium hover:text-brand-600">{{ d.name }}</Link>
                        </td>
                        <td class="nums-tabular px-4 py-2.5 text-xs" dir="ltr">{{ d.mobile ? toPersianDigits(d.mobile) : '—' }}</td>
                        <td class="nums-tabular px-4 py-2.5 text-end">{{ formatMoney(d.billed, { suffix: false }) }}</td>
                        <td class="nums-tabular px-4 py-2.5 text-end text-success-600">{{ formatMoney(d.paid, { suffix: false }) }}</td>
                        <td class="nums-tabular px-4 py-2.5 text-end font-semibold text-danger-500">{{ formatMoney(d.balance, { suffix: false }) }}</td>
                    </tr>
                </tbody>
            </table>
            <UiEmpty v-else title="بدهکاری وجود ندارد" message="همه بیماران تسویه کرده‌اند." />
        </UiCard>
    </div>
</template>
