<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const props = defineProps({ prescription: { type: Object, required: true } });

const { can } = usePermissions();

function print() {
    window.print();
}

function destroy() {
    if (confirm('این نسخه حذف شود؟')) {
        router.delete(route('prescriptions.destroy', props.prescription.id));
    }
}
</script>

<template>
    <Head title="نسخه" />

    <PageHeader
        title="نسخه"
        :subtitle="`${prescription.patient.name} — ${jalali(prescription.prescribed_on, { full: true })}`"
        :breadcrumbs="[{ label: 'نسخه‌ها', href: route('prescriptions.index') }, { label: 'مشاهده' }]"
    >
        <template #actions>
            <UiButton variant="secondary" class="no-print" @click="print">چاپ</UiButton>
            <UiButton v-if="can('prescriptions.manage')" variant="ghost" class="no-print" @click="destroy">حذف</UiButton>
        </template>
    </PageHeader>

    <UiCard class="mx-auto max-w-3xl">
        <header class="mb-5 border-b border-surface-200 pb-4 dark:border-surface-800">
            <h2 class="text-lg font-bold">{{ $page.props.clinic?.name }}</h2>
            <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <p>
                    <span class="text-ink-500">بیمار:</span>
                    <Link :href="route('patients.show', prescription.patient.id)" class="font-medium hover:text-brand-600">
                        {{ prescription.patient.name }}
                    </Link>
                    <span class="nums-tabular text-xs text-ink-300"> ({{ toPersianDigits(prescription.patient.code) }})</span>
                </p>
                <p class="nums-tabular"><span class="text-ink-500">تاریخ:</span> {{ jalali(prescription.prescribed_on, { full: true }) }}</p>
                <p v-if="prescription.user"><span class="text-ink-500">پزشک:</span> {{ prescription.user }}</p>
            </div>
        </header>

        <ol class="space-y-3">
            <li v-for="(item, i) in prescription.items" :key="item.id" class="flex gap-3">
                <span class="nums-tabular flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-200">
                    {{ toPersianDigits(i + 1) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ item.drug }} — {{ item.variant }}</p>
                    <p class="nums-tabular text-xs text-ink-500">
                        <span v-if="item.dosage">{{ item.dosage }}</span>
                        <span v-if="item.instructions"> · {{ item.instructions }}</span>
                        <span> · تعداد: {{ toPersianDigits(item.quantity) }}</span>
                    </p>
                </div>
            </li>
        </ol>

        <p v-if="prescription.notes" class="mt-5 border-t border-surface-200 pt-4 text-sm leading-relaxed whitespace-pre-line dark:border-surface-800">
            {{ prescription.notes }}
        </p>
    </UiCard>
</template>
