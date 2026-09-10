<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiChart from '@/Components/UiChart.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiStat from '@/Components/UiStat.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { SEQUENTIAL, STATUS } from '@/Support/chartTheme';
import { formatMoney, toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const props = defineProps({ data: { type: Object, required: true } });

const { can } = usePermissions();
const money = (v) => formatMoney(v, { suffix: false });

const buckets = computed(() => props.data.buckets ?? []);
const rows = computed(() => props.data.rows ?? []);

const overNinety = computed(() => buckets.value.find((b) => b.key === '90+')?.total ?? 0);

const share = (total) => (props.data.total ? Math.round((total / props.data.total) * 100) : 0);

/**
 * Ordered severity, so one hue deepening with age. The oldest bucket is the
 * one that needs chasing, and carries a label rather than relying on colour.
 */
const agingOption = computed(() => ({
    tooltip: {
        trigger: 'axis',
        formatter: (params) => {
            const p = params[0];
            const b = buckets.value.find((x) => x.label === p.axisValue);

            return `<div style="font-weight:600;margin-bottom:2px">${p.axisValue}</div>
                <div>${formatMoney(p.value)}</div>
                <div style="opacity:.65">${toPersianDigits(b?.patients ?? 0)} بیمار · ${toPersianDigits(share(p.value))}٪ از کل</div>`;
        },
    },
    xAxis: {
        type: 'category',
        inverse: true,
        data: buckets.value.map((b) => b.label),
    },
    yAxis: {
        type: 'value',
        axisLabel: { formatter: (v) => toPersianDigits((v / 1_000_000).toLocaleString('en-US')) + 'م' },
    },
    series: [{
        name: 'مانده',
        type: 'bar',
        data: buckets.value.map((b, i) => ({
            value: b.total,
            itemStyle: {
                // Deeper with age; the oldest bucket takes the critical status
                // colour, and its label says why.
                color: b.key === '90+' ? STATUS.critical : SEQUENTIAL[2 + i],
                borderRadius: [4, 4, 0, 0],
            },
        })),
        barMaxWidth: 56,
    }],
}));

/** Which bucket holds most of a patient's debt — shown as a text badge. */
function worstBucket(row) {
    const entries = Object.entries(row.buckets ?? {});
    const worst = entries.filter(([, v]) => v > 0).pop();

    return worst?.[0] ?? null;
}

const BUCKET_LABEL = {
    '0-30': 'تا ۳۰ روز',
    '31-60': '۳۱ تا ۶۰ روز',
    '61-90': '۶۱ تا ۹۰ روز',
    '90+': 'بیش از ۹۰ روز',
};
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UiStat label="کل مطالبات" :value="money(data.total)" hint="ریال" tone="danger" />
            <UiStat
                label="معوق بیش از ۹۰ روز"
                :value="money(overNinety)"
                :hint="`${toPersianDigits(share(overNinety))}٪ از کل مطالبات`"
                tone="danger"
            />
            <UiStat label="بیماران بدهکار" :value="toPersianDigits(rows.length)" hint="پرونده" />
            <UiStat
                label="میانگین بدهی"
                :value="rows.length ? money(Math.round(data.total / rows.length)) : '—'"
                hint="ریال"
            />
        </div>

        <UiCard
            title="سنّ مطالبات"
            subtitle="پرداخت‌ها به قدیمی‌ترین درمان تسویه‌نشده تخصیص داده می‌شوند؛ باقی‌مانده بر اساس تاریخ همان درمان دسته‌بندی می‌شود"
        >
            <UiChart v-if="data.total" :option="agingOption" height="15rem" />
            <UiEmpty v-else title="مطالباتی وجود ندارد" message="همه بیماران تسویه کرده‌اند." />

            <div v-if="data.total" class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="b in buckets" :key="b.key" class="rounded-lg bg-surface-100/70 px-3 py-2 dark:bg-surface-950">
                    <p class="text-[11px] text-ink-500">{{ b.label }}</p>
                    <p class="nums-tabular mt-0.5 font-semibold">{{ money(b.total) }}</p>
                    <p class="nums-tabular text-[11px] text-ink-300">
                        {{ toPersianDigits(b.patients) }} بیمار · {{ toPersianDigits(share(b.total)) }}٪
                    </p>
                </div>
            </div>
        </UiCard>

        <UiCard title="بدهکاران" :subtitle="`${toPersianDigits(rows.length)} پرونده، از بیشترین مانده`" :padded="false">
            <template #actions>
                <UiButton v-if="can('reports.export')" variant="secondary" size="sm" :href="route('reports.debtors.export')">
                    خروجی CSV
                </UiButton>
            </template>

            <div v-if="rows.length" class="max-h-[32rem] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-white dark:bg-surface-900">
                        <tr class="border-b border-surface-100 text-xs text-ink-300 dark:border-surface-800">
                            <th class="px-4 pb-2.5 text-start font-medium">کد</th>
                            <th class="px-4 pb-2.5 text-start font-medium">بیمار</th>
                            <th class="px-4 pb-2.5 text-start font-medium">موبایل</th>
                            <th class="px-4 pb-2.5 text-start font-medium">قدیمی‌ترین تسویه‌نشده</th>
                            <th class="px-4 pb-2.5 text-end font-medium">مانده</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.id" class="border-b border-surface-100/80 last:border-0 dark:border-surface-800/50">
                            <td class="nums-tabular px-4 py-3 text-ink-500">{{ toPersianDigits(r.code) }}</td>
                            <td class="px-4 py-3">
                                <Link :href="route('patients.show', r.id)" class="font-medium hover:text-brand-600">{{ r.name }}</Link>
                                <span
                                    v-if="worstBucket(r) === '90+'"
                                    class="ms-2 rounded px-1.5 py-0.5 text-[10px] font-medium"
                                    :style="{ background: `${STATUS.critical}1a`, color: STATUS.critical }"
                                >
                                    {{ BUCKET_LABEL['90+'] }}
                                </span>
                            </td>
                            <td class="nums-tabular px-4 py-3 text-xs" dir="ltr">{{ r.mobile ? toPersianDigits(r.mobile) : '—' }}</td>
                            <td class="nums-tabular px-4 py-3 text-xs text-ink-500">{{ r.oldest_unpaid ? jalali(r.oldest_unpaid) : '—' }}</td>
                            <td class="nums-tabular px-4 py-3 text-end font-semibold text-danger-500">{{ money(r.outstanding) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <UiEmpty v-else title="بدهکاری وجود ندارد" />
        </UiCard>
    </div>
</template>
