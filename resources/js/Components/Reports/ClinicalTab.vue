<script setup>
import { computed } from 'vue';
import Odontogram from '@/Components/Odontogram.vue';
import UiCard from '@/Components/UiCard.vue';
import UiChart from '@/Components/UiChart.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiStat from '@/Components/UiStat.vue';
import { SERIES } from '@/Support/chartTheme';
import { formatMoney, toPersianDigits } from '@/Support/format';

const props = defineProps({
    data: { type: Object, required: true },
    canSeeMoney: { type: Boolean, default: false },
});

const services = computed(() => props.data.services ?? []);
const categories = computed(() => props.data.categories ?? []);
const teeth = computed(() => props.data.teeth ?? {});

const treatedTeeth = computed(() => Object.keys(teeth.value).length);
const busiestTooth = computed(() => {
    const entries = Object.entries(teeth.value);
    if (!entries.length) return null;

    return entries.reduce((a, b) => (b[1] > a[1] ? b : a));
});

/** Horizontal bars: category names are long, and the ranking is the point. */
const categoryOption = computed(() => {
    const rows = [...categories.value].reverse();

    return {
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const p = params[0];
                const row = categories.value.find((c) => c.name === p.axisValue);

                return `<div style="font-weight:600;margin-bottom:2px">${p.axisValue}</div>
                    <div>${toPersianDigits(p.value)} درمان</div>
                    ${row?.total != null ? `<div style="opacity:.65">${formatMoney(row.total)}</div>` : ''}`;
            },
        },
        xAxis: { type: 'value', axisLabel: { formatter: (v) => toPersianDigits(v) } },
        yAxis: { type: 'category', data: rows.map((r) => r.name) },
        series: [{
            name: 'تعداد درمان',
            type: 'bar',
            data: rows.map((r) => r.count),
            itemStyle: { color: SERIES[0], borderRadius: [0, 4, 4, 0] },
            barMaxWidth: 20,
        }],
    };
});
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UiStat label="کل درمان‌ها" :value="toPersianDigits(data.total ?? 0)" hint="در بازه انتخابی" tone="brand" />
            <UiStat label="نوع خدمت انجام‌شده" :value="toPersianDigits(services.length)" />
            <UiStat label="دندان‌های درمان‌شده" :value="toPersianDigits(treatedTeeth)" hint="از ۵۲ دندان" />
            <UiStat
                v-if="busiestTooth"
                label="پرکارترین دندان"
                :value="toPersianDigits(busiestTooth[0])"
                :hint="`${toPersianDigits(busiestTooth[1])} درمان`"
            />
        </div>

        <UiCard
            title="پراکندگی درمان روی دندان‌ها"
            subtitle="هرچه رنگ پررنگ‌تر، تعداد درمان ثبت‌شده روی آن دندان بیشتر"
        >
            <Odontogram
                v-if="treatedTeeth"
                :model-value="[]"
                :heat="teeth"
                readonly
                compact
            />
            <UiEmpty v-else title="در این بازه درمانی روی دندان ثبت نشده" />
        </UiCard>

        <div class="grid gap-5 lg:grid-cols-2">
            <UiCard title="درمان بر اساس گروه">
                <UiChart v-if="categories.length" :option="categoryOption" :height="`${Math.max(10, categories.length * 2.6)}rem`" />
                <UiEmpty v-else title="داده‌ای نیست" />
            </UiCard>

            <UiCard title="خدمات انجام‌شده" :padded="false">
                <div v-if="services.length" class="max-h-[26rem] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-white dark:bg-surface-900">
                            <tr class="border-b border-surface-100 text-xs text-ink-300 dark:border-surface-800">
                                <th class="px-4 pb-2.5 text-start font-medium">خدمت</th>
                                <th class="px-4 pb-2.5 text-end font-medium">تعداد</th>
                                <th v-if="canSeeMoney" class="px-4 pb-2.5 text-end font-medium">مبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(s, i) in services" :key="i" class="border-b border-surface-100/80 last:border-0 dark:border-surface-800/50">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium">{{ s.name }}</p>
                                    <p v-if="s.category" class="text-[11px] text-ink-300">{{ s.category }}</p>
                                </td>
                                <td class="nums-tabular px-4 py-2.5 text-end">{{ toPersianDigits(s.count) }}</td>
                                <td v-if="canSeeMoney" class="nums-tabular px-4 py-2.5 text-end text-ink-500">
                                    {{ formatMoney(s.total, { suffix: false }) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <UiEmpty v-else title="درمانی در این بازه ثبت نشده" />
            </UiCard>
        </div>
    </div>
</template>
