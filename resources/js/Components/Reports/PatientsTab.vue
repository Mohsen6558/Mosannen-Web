<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiChart from '@/Components/UiChart.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiStat from '@/Components/UiStat.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { SERIES } from '@/Support/chartTheme';
import { toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const props = defineProps({ data: { type: Object, required: true } });

const { can } = usePermissions();

const mix = computed(() => props.data.mix ?? { insurance: [], gender: [], age: [] });
const recall = computed(() => props.data.recall ?? []);
const referrals = computed(() => props.data.referrals ?? []);

const monthlyOption = computed(() => {
    const rows = props.data.monthly ?? [];

    return {
        color: [SERIES[0]],
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const p = params[0];

                return `<div style="font-weight:600;margin-bottom:2px">${jalali(p.axisValue, { full: true }).replace(/^\S+\s/, '')}</div>
                    <div>${toPersianDigits(p.value)} پرونده جدید</div>`;
            },
        },
        xAxis: {
            type: 'category',
            inverse: true,
            data: rows.map((r) => r.month),
            axisLabel: {
                formatter: (v) => jalali(v, { full: true }).replace(/^\S+\s/, '').replace(/\s\d+$/, ''),
            },
        },
        yAxis: { type: 'value', axisLabel: { formatter: (v) => toPersianDigits(v) }, minInterval: 1 },
        series: [{
            name: 'پرونده جدید',
            type: 'bar',
            data: rows.map((r) => r.count),
            itemStyle: { color: SERIES[0], borderRadius: [4, 4, 0, 0] },
            barMaxWidth: 22,
        }],
    };
});

/** A small ranked list beats a pie for any of these breakdowns. */
function maxOf(list) {
    return Math.max(...list.map((r) => r.count), 1);
}
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UiStat label="کل بیماران" :value="toPersianDigits(data.total ?? 0)" tone="brand" />
            <UiStat label="پرونده جدید در بازه" :value="toPersianDigits(data.new ?? 0)" />
            <UiStat label="نیازمند پیگیری" :value="toPersianDigits(recall.length)" hint="بیش از ۶ ماه بدون مراجعه" tone="warning" />
            <UiStat label="منابع معرفی" :value="toPersianDigits(referrals.length)" />
        </div>

        <UiCard title="پرونده‌های جدید، ماه به ماه">
            <UiChart v-if="data.monthly?.length" :option="monthlyOption" height="16rem" />
            <UiEmpty v-else title="داده‌ای نیست" />
        </UiCard>

        <div class="grid gap-5 lg:grid-cols-3">
            <UiCard v-for="group in [
                { title: 'بیمه', rows: mix.insurance },
                { title: 'گروه سنی', rows: mix.age },
                { title: 'جنسیت', rows: mix.gender },
            ]" :key="group.title" :title="group.title">
                <ul v-if="group.rows.length" class="space-y-2.5">
                    <li v-for="(r, i) in group.rows" :key="i">
                        <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                            <span class="truncate">{{ r.name }}</span>
                            <span class="nums-tabular shrink-0 text-xs font-medium">{{ toPersianDigits(r.count) }}</span>
                        </div>
                        <div class="h-1 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                            <div class="h-full rounded-full" :style="{ width: `${(r.count / maxOf(group.rows)) * 100}%`, background: SERIES[0] }" />
                        </div>
                    </li>
                </ul>
                <UiEmpty v-else title="داده‌ای نیست" />
            </UiCard>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <UiCard title="بیماران از کجا می‌آیند" subtitle="بر اساس فیلد معرف در پرونده">
                <ul v-if="referrals.length" class="space-y-2.5">
                    <li v-for="(r, i) in referrals" :key="i">
                        <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                            <span class="truncate">{{ r.name }}</span>
                            <span class="nums-tabular shrink-0 text-xs font-medium">{{ toPersianDigits(r.count) }}</span>
                        </div>
                        <div class="h-1 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                            <div class="h-full rounded-full" :style="{ width: `${(r.count / maxOf(referrals)) * 100}%`, background: SERIES[0] }" />
                        </div>
                    </li>
                </ul>
                <UiEmpty v-else title="معرفی ثبت نشده" message="فیلد «معرف» در پرونده بیماران پر نشده است." />
            </UiCard>

            <UiCard
                class="lg:col-span-2"
                title="لیست پیگیری"
                subtitle="بیمارانی که بیش از ۶ ماه مراجعه نکرده‌اند و شماره موبایل دارند"
                :padded="false"
            >
                <template #actions>
                    <UiButton v-if="can('reports.export')" variant="secondary" size="sm" :href="route('reports.recall.export')">
                        خروجی CSV
                    </UiButton>
                </template>

                <div v-if="recall.length" class="max-h-[24rem] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-white dark:bg-surface-900">
                            <tr class="border-b border-surface-100 text-xs text-ink-300 dark:border-surface-800">
                                <th class="px-4 pb-2.5 text-start font-medium">بیمار</th>
                                <th class="px-4 pb-2.5 text-start font-medium">موبایل</th>
                                <th class="px-4 pb-2.5 text-start font-medium">آخرین مراجعه</th>
                                <th class="px-4 pb-2.5 text-end font-medium">فاصله</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in recall" :key="r.id" class="border-b border-surface-100/80 last:border-0 dark:border-surface-800/50">
                                <td class="px-4 py-2.5">
                                    <Link :href="route('patients.show', r.id)" class="font-medium hover:text-brand-600">{{ r.name }}</Link>
                                    <span class="nums-tabular ms-1 text-[11px] text-ink-300">{{ toPersianDigits(r.code) }}</span>
                                </td>
                                <td class="nums-tabular px-4 py-2.5 text-xs" dir="ltr">{{ toPersianDigits(r.mobile) }}</td>
                                <td class="nums-tabular px-4 py-2.5 text-xs text-ink-500">{{ jalali(r.last_visit) }}</td>
                                <td class="nums-tabular px-4 py-2.5 text-end text-xs">{{ toPersianDigits(r.months) }} ماه</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <UiEmpty v-else title="همه بیماران اخیراً مراجعه کرده‌اند" />
            </UiCard>
        </div>
    </div>
</template>
