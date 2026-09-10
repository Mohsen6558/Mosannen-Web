<script setup>
import { computed } from 'vue';
import UiCard from '@/Components/UiCard.vue';
import UiChart from '@/Components/UiChart.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiStat from '@/Components/UiStat.vue';
import { SERIES } from '@/Support/chartTheme';
import { formatMoney, toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const props = defineProps({
    data: { type: Object, required: true },
});

const summary = computed(() => props.data.summary ?? {});

/** What share of the work billed in this window has actually been collected. */
const collectionRate = computed(() => {
    const billed = summary.value.billed ?? 0;
    if (!billed) return null;

    return Math.round(((summary.value.collected ?? 0) / billed) * 100);
});

const money = (v) => formatMoney(v, { suffix: false });

/** Billed and collected share a unit, so they share one axis. */
const monthlyOption = computed(() => {
    const rows = props.data.monthly ?? [];

    return {
        legend: { data: ['صورتحساب', 'دریافتی'] },
        color: [SERIES[1], SERIES[0]],
        tooltip: {
            formatter: (params) => {
                const head = jalali(params[0].axisValue, { full: true }).replace(/^\S+\s/, '');
                const lines = params.map(
                    (p) => `<div style="display:flex;gap:12px;justify-content:space-between">
                        <span>${p.marker} ${p.seriesName}</span>
                        <b>${formatMoney(p.value)}</b></div>`,
                ).join('');

                return `<div style="font-weight:600;margin-bottom:4px">${head}</div>${lines}`;
            },
        },
        xAxis: {
            type: 'category',
            // Time reads right to left.
            inverse: true,
            data: rows.map((r) => r.month),
            axisLabel: {
                formatter: (v) => jalali(v, { full: true }).replace(/^\S+\s/, '').replace(/\s\d+$/, ''),
            },
        },
        yAxis: {
            type: 'value',
            axisLabel: { formatter: (v) => toPersianDigits((v / 1_000_000).toLocaleString('en-US')) + 'م' },
        },
        series: [
            {
                name: 'صورتحساب',
                type: 'bar',
                data: rows.map((r) => r.billed),
                itemStyle: { borderRadius: [4, 4, 0, 0] },
                barMaxWidth: 18,
                barGap: '12%',
            },
            {
                name: 'دریافتی',
                type: 'bar',
                data: rows.map((r) => r.collected),
                itemStyle: { borderRadius: [4, 4, 0, 0] },
                barMaxWidth: 18,
            },
        ],
    };
});

const dailyOption = computed(() => {
    const rows = props.data.daily ?? [];

    return {
        color: [SERIES[0]],
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'line', lineStyle: { color: SERIES[0], width: 1, type: 'dashed' } },
            formatter: (params) => {
                const p = params[0];
                const row = rows.find((r) => r.date === p.axisValue);

                return `<div style="font-weight:600;margin-bottom:2px">${jalali(p.axisValue, { full: true })}</div>
                    <div>${formatMoney(p.value)}</div>
                    <div style="opacity:.65">${toPersianDigits(row?.count ?? 0)} تراکنش</div>`;
            },
        },
        xAxis: {
            type: 'category',
            inverse: true,
            data: rows.map((r) => r.date),
            axisLabel: { formatter: (v) => jalali(v) },
        },
        yAxis: {
            type: 'value',
            axisLabel: { formatter: (v) => toPersianDigits((v / 1_000_000).toLocaleString('en-US')) + 'م' },
        },
        series: [{
            name: 'دریافتی',
            type: 'line',
            smooth: 0.2,
            symbol: 'circle',
            symbolSize: 8,
            showSymbol: rows.length <= 40,
            lineStyle: { width: 2 },
            areaStyle: { opacity: 0.12 },
            data: rows.map((r) => r.total),
        }],
    };
});

const maxType = computed(() => Math.max(...(props.data.byType ?? []).map((t) => t.total), 1));
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UiStat label="دریافتی" :value="money(summary.collected)" hint="ریال" tone="success" />
            <UiStat label="صورتحساب صادرشده" :value="money(summary.billed)" hint="ریال" tone="brand" />
            <UiStat label="تخفیف" :value="money(summary.discount)" hint="ریال" tone="warning" />
            <UiStat
                label="نرخ وصول"
                :value="collectionRate === null ? '—' : `${toPersianDigits(collectionRate)}٪`"
                :hint="`${toPersianDigits(summary.transactions ?? 0)} تراکنش`"
            />
        </div>

        <UiCard title="صورتحساب و دریافتی، ماه به ماه" subtitle="ارقام به میلیون ریال">
            <UiChart v-if="data.monthly?.length" :option="monthlyOption" height="19rem" />
            <UiEmpty v-else title="داده‌ای نیست" />
        </UiCard>

        <div class="grid gap-5 lg:grid-cols-3">
            <UiCard class="lg:col-span-2" title="دریافتی روزانه" subtitle="ارقام به میلیون ریال">
                <UiChart v-if="data.daily?.length" :option="dailyOption" height="16rem" />
                <UiEmpty v-else title="در این بازه پرداختی ثبت نشده" />
            </UiCard>

            <UiCard title="تفکیک نحوه پرداخت">
                <ul v-if="data.byType?.length" class="space-y-3">
                    <li v-for="t in data.byType" :key="t.type">
                        <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                            <span class="truncate">{{ t.type }}</span>
                            <span class="nums-tabular shrink-0 font-medium">{{ money(t.total) }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                            <div class="h-full rounded-full" :style="{ width: `${(t.total / maxType) * 100}%`, background: SERIES[0] }" />
                        </div>
                        <p class="nums-tabular mt-0.5 text-[11px] text-ink-300">{{ toPersianDigits(t.count) }} تراکنش</p>
                    </li>
                </ul>
                <UiEmpty v-else title="داده‌ای نیست" />
            </UiCard>
        </div>
    </div>
</template>
