<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import UiCard from '@/Components/UiCard.vue';
import UiStat from '@/Components/UiStat.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import { formatMoney, formatNumber, toPersianDigits } from '@/Support/format';
import { jalali, todayIso } from '@/Support/jalali';

const props = defineProps({
    stats: { type: Object, required: true },
    incomeSeries: { type: Array, default: () => [] },
    topServices: { type: Array, default: () => [] },
    todayAppointments: { type: Array, default: () => [] },
    recentPatients: { type: Array, default: () => [] },
    lowStock: { type: Array, default: () => [] },
});

const showMoney = computed(() => props.stats.income_today !== null);

const monthTrend = computed(() => {
    const now = props.stats.income_month;
    const prev = props.stats.income_prev_month;
    if (!showMoney.value || !prev) return null;
    return Math.round(((now - prev) / prev) * 100);
});

// Sparkline path over the last 30 days of income.
const spark = computed(() => {
    const data = props.incomeSeries;
    if (data.length < 2) return null;

    const max = Math.max(...data.map((d) => d.total), 1);
    const w = 100;
    const h = 32;
    const step = w / (data.length - 1);

    const points = data.map((d, i) => `${i * step},${h - (d.total / max) * h}`);

    return {
        line: `M ${points.join(' L ')}`,
        area: `M 0,${h} L ${points.join(' L ')} L ${w},${h} Z`,
        max,
    };
});

const STATUS_TONE = {
    scheduled: 'info', confirmed: 'brand', done: 'success',
    cancelled: 'neutral', no_show: 'danger',
};

const STATUS_LABEL = {
    scheduled: 'ثبت‌شده', confirmed: 'تایید‌شده', done: 'انجام‌شده',
    cancelled: 'لغو‌شده', no_show: 'غایب',
};
</script>

<template>
    <Head title="داشبورد" />

    <PageHeader title="داشبورد" :subtitle="jalali(todayIso(), { full: true, withWeekday: true })" />

    <div class="space-y-5">
        <!-- Stat row -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UiStat label="کل بیماران" :value="formatNumber(stats.patients_total)"
                    :hint="`${formatNumber(stats.patients_new_this_month)} پرونده جدید این ماه`" tone="brand">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.3-.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.3.36-1.86m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </template>
            </UiStat>

            <UiStat label="درمان‌های امروز" :value="formatNumber(stats.treatments_today)"
                    :hint="`${formatNumber(stats.appointments_today)} نوبت امروز`">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3c-2.2 0-3 1-4.5 1S5 3.4 4.2 4.6C3.2 6.1 3.6 8.6 4.3 11c.6 2 .7 3.4.9 5.2.2 1.7.5 3.8 1.9 3.8 1.3 0 1.5-1.6 1.8-3.4.3-1.8.6-3.1 1.6-3.1s1.3 1.3 1.6 3.1c.3 1.8.5 3.4 1.8 3.4 1.4 0 1.7-2.1 1.9-3.8.2-1.8.3-3.2.9-5.2.7-2.4 1.1-4.9.1-6.4C17.5 3.4 16 4 14.5 4S14.2 3 12 3z" /></svg>
                </template>
            </UiStat>

            <UiStat v-if="showMoney" label="درآمد امروز" :value="formatMoney(stats.income_today, { suffix: false })"
                    hint="ریال" tone="success">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 10a2 2 0 012-2h14a2 2 0 012 2M3 10v8a2 2 0 002 2h14a2 2 0 002-2v-8" /></svg>
                </template>
            </UiStat>

            <UiStat v-if="showMoney" label="مانده بدهی بیماران"
                    :value="formatMoney(stats.outstanding, { suffix: false })"
                    hint="ریال" tone="warning" />

            <UiStat v-if="!showMoney" label="نوبت‌های امروز" :value="formatNumber(stats.appointments_today)" />
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Income chart -->
            <UiCard v-if="showMoney" class="lg:col-span-2" title="درآمد ۳۰ روز اخیر"
                    :subtitle="monthTrend !== null ? `${monthTrend >= 0 ? '+' : ''}${toPersianDigits(monthTrend)}٪ نسبت به ماه گذشته` : null">
                <div v-if="spark">
                    <svg viewBox="0 0 100 32" preserveAspectRatio="none" class="h-32 w-full">
                        <defs>
                            <linearGradient id="incomeFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="var(--color-brand-500)" stop-opacity="0.28" />
                                <stop offset="100%" stop-color="var(--color-brand-500)" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path :d="spark.area" fill="url(#incomeFill)" />
                        <path :d="spark.line" fill="none" stroke="var(--color-brand-500)" stroke-width="1"
                              stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                    </svg>
                    <div class="mt-2 flex justify-between text-[11px] text-ink-500">
                        <span>{{ jalali(incomeSeries[0]?.date, { full: true }) }}</span>
                        <span class="nums-tabular">بیشترین: {{ formatMoney(spark.max) }}</span>
                        <span>{{ jalali(incomeSeries[incomeSeries.length - 1]?.date, { full: true }) }}</span>
                    </div>
                </div>
                <UiEmpty v-else title="داده‌ای برای نمایش نیست" />
            </UiCard>

            <!-- Today's appointments -->
            <UiCard title="نوبت‌های امروز" :class="showMoney ? '' : 'lg:col-span-2'">
                <template #actions>
                    <Link :href="route('appointments.index')" class="text-xs font-medium text-brand-600 hover:underline">همه</Link>
                </template>

                <ul v-if="todayAppointments.length" class="-my-1 divide-y divide-surface-100 dark:divide-surface-800">
                    <li v-for="a in todayAppointments" :key="a.id" class="flex items-center gap-3 py-2.5">
                        <span class="nums-tabular w-12 shrink-0 text-sm font-semibold text-brand-700 dark:text-brand-300">
                            {{ toPersianDigits(a.starts_at) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <Link v-if="a.patient.id" :href="route('patients.show', a.patient.id)" class="block truncate text-sm font-medium hover:text-brand-600">
                                {{ a.patient.name }}
                            </Link>
                            <p v-if="a.notes" class="truncate text-[11px] text-ink-500">{{ a.notes }}</p>
                        </div>
                        <UiBadge :tone="STATUS_TONE[a.status]" dot>{{ STATUS_LABEL[a.status] }}</UiBadge>
                    </li>
                </ul>

                <UiEmpty v-else title="نوبتی برای امروز ثبت نشده" />
            </UiCard>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Top services -->
            <UiCard title="پرتکرارترین خدمات این ماه">
                <ul v-if="topServices.length" class="space-y-2.5">
                    <li v-for="(s, i) in topServices" :key="i">
                        <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                            <span class="truncate">{{ s.name }}</span>
                            <span class="nums-tabular shrink-0 text-xs text-ink-500">{{ formatNumber(s.count) }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                            <div class="h-full rounded-full bg-brand-500"
                                 :style="{ width: `${(s.count / topServices[0].count) * 100}%` }" />
                        </div>
                    </li>
                </ul>
                <UiEmpty v-else title="درمانی در این ماه ثبت نشده" />
            </UiCard>

            <!-- Recent patients -->
            <UiCard title="آخرین پرونده‌ها">
                <template #actions>
                    <Link :href="route('patients.index')" class="text-xs font-medium text-brand-600 hover:underline">همه</Link>
                </template>

                <ul v-if="recentPatients.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                    <li v-for="p in recentPatients" :key="p.id" class="py-2">
                        <Link :href="route('patients.show', p.id)" class="flex items-center justify-between gap-2 hover:text-brand-600">
                            <span class="truncate text-sm">{{ p.name }}</span>
                            <span class="nums-tabular shrink-0 text-[11px] text-ink-500">{{ toPersianDigits(p.code) }}</span>
                        </Link>
                    </li>
                </ul>
                <UiEmpty v-else title="پرونده‌ای ثبت نشده" />
            </UiCard>

            <!-- Low stock -->
            <UiCard title="کالاهای رو به اتمام">
                <template #actions>
                    <Link :href="route('stock.index')" class="text-xs font-medium text-brand-600 hover:underline">انبار</Link>
                </template>

                <ul v-if="lowStock.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                    <li v-for="i in lowStock" :key="i.id" class="flex items-center justify-between gap-2 py-2">
                        <span class="truncate text-sm">{{ i.name }}</span>
                        <UiBadge tone="warning" class="nums-tabular">
                            {{ toPersianDigits(i.on_hand) }} {{ i.unit || '' }}
                        </UiBadge>
                    </li>
                </ul>
                <UiEmpty v-else title="موجودی همه کالاها کافی است" />
            </UiCard>
        </div>
    </div>
</template>
