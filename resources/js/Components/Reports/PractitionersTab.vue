<script setup>
import { computed } from 'vue';
import UiCard from '@/Components/UiCard.vue';
import UiChart from '@/Components/UiChart.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import { SERIES } from '@/Support/chartTheme';
import { formatMoney, toPersianDigits } from '@/Support/format';

const props = defineProps({ data: { type: Object, required: true } });

const rows = computed(() => props.data.rows ?? []);
const money = (v) => formatMoney(v, { suffix: false });

/**
 * Revenue per practitioner. Count and patient totals live in the table
 * beside it — a second y-scale on the same chart would be unreadable.
 */
const option = computed(() => {
    const ordered = [...rows.value].reverse();

    return {
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const p = params[0];
                const row = rows.value.find((r) => r.name === p.axisValue);

                return `<div style="font-weight:600;margin-bottom:2px">${p.axisValue}</div>
                    <div>${formatMoney(p.value)}</div>
                    <div style="opacity:.65">${toPersianDigits(row?.count ?? 0)} درمان ·
                    ${toPersianDigits(row?.patients ?? 0)} بیمار</div>`;
            },
        },
        xAxis: {
            type: 'value',
            axisLabel: { formatter: (v) => toPersianDigits((v / 1_000_000).toLocaleString('en-US')) + 'م' },
        },
        yAxis: { type: 'category', data: ordered.map((r) => r.name) },
        series: [{
            name: 'درآمد',
            type: 'bar',
            data: ordered.map((r) => r.total),
            itemStyle: { color: SERIES[0], borderRadius: [0, 4, 4, 0] },
            barMaxWidth: 24,
        }],
    };
});
</script>

<template>
    <div class="space-y-5">
        <UiCard title="درآمد به تفکیک پزشک" subtitle="ارقام به میلیون ریال">
            <UiChart v-if="rows.length" :option="option" :height="`${Math.max(9, rows.length * 3)}rem`" />
            <UiEmpty v-else title="در این بازه درمانی ثبت نشده" />
        </UiCard>

        <UiCard title="کارکرد پزشکان" :padded="false">
            <table v-if="rows.length" class="w-full text-sm">
                <thead>
                    <tr class="border-b border-surface-100 text-xs text-ink-300 dark:border-surface-800">
                        <th class="px-4 pb-2.5 text-start font-medium">پزشک</th>
                        <th class="px-4 pb-2.5 text-end font-medium">تعداد درمان</th>
                        <th class="px-4 pb-2.5 text-end font-medium">بیماران</th>
                        <th class="px-4 pb-2.5 text-end font-medium">میانگین هر درمان</th>
                        <th class="px-4 pb-2.5 text-end font-medium">مجموع</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in rows" :key="i" class="border-b border-surface-100/80 last:border-0 dark:border-surface-800/50">
                        <td class="px-4 py-3 font-medium">{{ r.name }}</td>
                        <td class="nums-tabular px-4 py-3 text-end">{{ toPersianDigits(r.count) }}</td>
                        <td class="nums-tabular px-4 py-3 text-end">{{ toPersianDigits(r.patients) }}</td>
                        <td class="nums-tabular px-4 py-3 text-end text-ink-500">{{ money(r.average) }}</td>
                        <td class="nums-tabular px-4 py-3 text-end font-semibold">{{ money(r.total) }}</td>
                    </tr>
                </tbody>
            </table>
            <UiEmpty v-else title="داده‌ای نیست" />
        </UiCard>
    </div>
</template>
