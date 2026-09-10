<script setup>
import { computed } from 'vue';
import UiCard from '@/Components/UiCard.vue';
import UiChart from '@/Components/UiChart.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiStat from '@/Components/UiStat.vue';
import { SERIES, STATUS } from '@/Support/chartTheme';
import { toPersianDigits } from '@/Support/format';

const props = defineProps({ data: { type: Object, required: true } });

const statuses = computed(() => props.data.statuses ?? []);
const total = computed(() => props.data.total ?? 0);

const countOf = (key) => statuses.value.find((s) => s.key === key)?.count ?? 0;
const rate = (key) => (total.value ? Math.round((countOf(key) / total.value) * 100) : 0);

/**
 * Status is state, not series: each slice keeps its reserved colour and is
 * labelled, so meaning never rests on hue alone.
 */
const STATUS_COLOR = {
    scheduled: SERIES[2],
    confirmed: SERIES[0],
    done: STATUS.good,
    cancelled: '#8b8378',
    no_show: STATUS.critical,
};

const statusOption = computed(() => {
    const rows = statuses.value.filter((s) => s.count > 0);

    return {
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const p = params[0];
                const pct = total.value ? Math.round((p.value / total.value) * 100) : 0;

                return `<div style="font-weight:600;margin-bottom:2px">${p.axisValue}</div>
                    <div>${toPersianDigits(p.value)} نوبت · ${toPersianDigits(pct)}٪</div>`;
            },
        },
        xAxis: { type: 'value', axisLabel: { formatter: (v) => toPersianDigits(v) }, minInterval: 1 },
        yAxis: { type: 'category', data: [...rows].reverse().map((r) => r.name) },
        series: [{
            name: 'نوبت',
            type: 'bar',
            data: [...rows].reverse().map((r) => ({
                value: r.count,
                itemStyle: { color: STATUS_COLOR[r.key], borderRadius: [0, 4, 4, 0] },
            })),
            barMaxWidth: 20,
        }],
    };
});

const hourOption = computed(() => {
    const rows = props.data.byHour ?? [];

    return {
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const p = params[0];

                return `<div style="font-weight:600;margin-bottom:2px">ساعت ${toPersianDigits(p.axisValue)}</div>
                    <div>${toPersianDigits(p.value)} نوبت</div>`;
            },
        },
        xAxis: {
            type: 'category',
            inverse: true,
            data: rows.map((r) => `${String(r.hour).padStart(2, '0')}:00`),
            axisLabel: { formatter: (v) => toPersianDigits(v.slice(0, 2)) },
        },
        yAxis: { type: 'value', axisLabel: { formatter: (v) => toPersianDigits(v) }, minInterval: 1 },
        series: [{
            name: 'نوبت',
            type: 'bar',
            data: rows.map((r) => r.count),
            itemStyle: { color: SERIES[0], borderRadius: [4, 4, 0, 0] },
            barMaxWidth: 26,
        }],
    };
});

const weekdayOption = computed(() => {
    const rows = props.data.byWeekday ?? [];

    return {
        tooltip: {
            trigger: 'axis',
            formatter: (params) => `<div style="font-weight:600">${params[0].axisValue}</div>
                <div>${toPersianDigits(params[0].value)} نوبت</div>`,
        },
        xAxis: { type: 'category', inverse: true, data: rows.map((r) => r.name) },
        yAxis: { type: 'value', axisLabel: { formatter: (v) => toPersianDigits(v) }, minInterval: 1 },
        series: [{
            name: 'نوبت',
            type: 'bar',
            data: rows.map((r) => r.count),
            itemStyle: { color: SERIES[0], borderRadius: [4, 4, 0, 0] },
            barMaxWidth: 30,
        }],
    };
});
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UiStat label="کل نوبت‌ها" :value="toPersianDigits(total)" hint="در بازه انتخابی" tone="brand" />
            <UiStat label="انجام‌شده" :value="`${toPersianDigits(rate('done'))}٪`" :hint="`${toPersianDigits(countOf('done'))} نوبت`" tone="success" />
            <UiStat label="غیبت بیمار" :value="`${toPersianDigits(rate('no_show'))}٪`" :hint="`${toPersianDigits(countOf('no_show'))} نوبت`" tone="danger" />
            <UiStat label="لغو‌شده" :value="`${toPersianDigits(rate('cancelled'))}٪`" :hint="`${toPersianDigits(countOf('cancelled'))} نوبت`" tone="warning" />
        </div>

        <UiCard title="وضعیت نوبت‌ها">
            <UiChart v-if="total" :option="statusOption" height="13rem" />
            <UiEmpty v-else title="در این بازه نوبتی ثبت نشده" />
        </UiCard>

        <div class="grid gap-5 lg:grid-cols-2">
            <UiCard title="شلوغ‌ترین ساعت‌ها">
                <UiChart v-if="total" :option="hourOption" height="15rem" />
                <UiEmpty v-else title="داده‌ای نیست" />
            </UiCard>

            <UiCard title="شلوغ‌ترین روزهای هفته">
                <UiChart v-if="total" :option="weekdayOption" height="15rem" />
                <UiEmpty v-else title="داده‌ای نیست" />
            </UiCard>
        </div>
    </div>
</template>
