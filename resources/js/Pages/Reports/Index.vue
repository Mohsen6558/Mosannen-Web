<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppointmentsTab from '@/Components/Reports/AppointmentsTab.vue';
import ClinicalTab from '@/Components/Reports/ClinicalTab.vue';
import FinancialTab from '@/Components/Reports/FinancialTab.vue';
import PatientsTab from '@/Components/Reports/PatientsTab.vue';
import PractitionersTab from '@/Components/Reports/PractitionersTab.vue';
import ReceivablesTab from '@/Components/Reports/ReceivablesTab.vue';
import { jalali } from '@/Support/jalali';

const props = defineProps({
    filters: { type: Object, required: true },
    canSeeMoney: { type: Boolean, default: false },
    tabs: { type: Array, default: () => [] },
    data: { type: Object, default: () => ({}) },
});

const TAB_COMPONENTS = {
    financial: FinancialTab,
    receivables: ReceivablesTab,
    clinical: ClinicalTab,
    practitioners: PractitionersTab,
    patients: PatientsTab,
    appointments: AppointmentsTab,
};

const from = ref(props.filters.from);
const to = ref(props.filters.to);

// Each tab is computed server-side on demand, so switching is a visit.
function go(report = props.filters.report) {
    router.get(route('reports.index'), {
        from: from.value || undefined,
        to: to.value || undefined,
        report,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

watch([from, to], () => go());
</script>

<template>
    <Head title="گزارش‌ها" />

    <PageHeader
        title="گزارش‌ها"
        :subtitle="`${jalali(filters.from, { full: true })} تا ${jalali(filters.to, { full: true })}`"
    >
        <template #actions>
            <DateRangeFilter v-model:from="from" v-model:to="to" />
        </template>
    </PageHeader>

    <nav class="mb-5 flex flex-wrap gap-1">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="rounded-lg px-3.5 py-2 text-sm font-medium transition-colors"
            :class="filters.report === tab.key
                ? 'bg-brand-600 text-white'
                : 'text-ink-500 hover:bg-surface-200/70 hover:text-ink-900 dark:hover:bg-surface-800 dark:hover:text-ink-50'"
            @click="go(tab.key)"
        >
            {{ tab.label }}
        </button>
    </nav>

    <component
        :is="TAB_COMPONENTS[filters.report]"
        :data="data"
        :filters="filters"
        :can-see-money="canSeeMoney"
    />
</template>
