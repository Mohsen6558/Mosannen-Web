<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Odontogram from '@/Components/Odontogram.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiConfirm from '@/Components/UiConfirm.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiStat from '@/Components/UiStat.vue';
import TreatmentFormModal from '@/Components/TreatmentFormModal.vue';
import PaymentFormModal from '@/Components/PaymentFormModal.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatMoney, toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';
import { summarize } from '@/Support/teeth';

const props = defineProps({
    patient: { type: Object, required: true },
    summary: { type: Object, required: true },
    treatments: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    radiographs: { type: Array, default: () => [] },
    appointments: { type: Array, default: () => [] },
    prescriptions: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const tab = ref('treatments');
const showTreatment = ref(false);
const showPayment = ref(false);
const confirmDelete = ref(false);
const lightbox = ref(null);

const deleteForm = useForm({});

// Every tooth this patient has had work on, for the chart overview.
const treatedTeeth = computed(() =>
    [...new Set(props.treatments.flatMap((t) => t.teeth ?? []))],
);

const TABS = computed(() => [
    { key: 'treatments', label: 'درمان‌ها', count: props.treatments.length },
    { key: 'payments', label: 'پرداخت‌ها', count: props.payments.length },
    { key: 'images', label: 'تصاویر', count: props.radiographs.length },
    { key: 'prescriptions', label: 'نسخه‌ها', count: props.prescriptions.length },
    { key: 'info', label: 'مشخصات', count: null },
]);

const GENDER = { m: 'مرد', f: 'زن' };

function destroy() {
    deleteForm.delete(route('patients.destroy', props.patient.id), {
        onFinish: () => (confirmDelete.value = false),
    });
}
</script>

<template>
    <Head :title="patient.full_name" />

    <PageHeader
        :title="patient.full_name"
        :subtitle="`کد پرونده ${toPersianDigits(patient.code)}${patient.insurance ? ' — ' + patient.insurance.name : ''}`"
        :breadcrumbs="[
            { label: 'پرونده بیماران', href: route('patients.index') },
            { label: patient.full_name },
        ]"
    >
        <template #actions>
            <UiButton v-if="can('treatments.create')" variant="primary" @click="showTreatment = true">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                </template>
                ثبت درمان
            </UiButton>
            <UiButton v-if="can('payments.create')" variant="subtle" @click="showPayment = true">دریافت وجه</UiButton>
            <UiButton v-if="can('patients.update')" variant="secondary" :href="route('patients.edit', patient.id)">ویرایش</UiButton>
            <UiButton v-if="can('patients.delete')" variant="ghost" size="icon" title="حذف پرونده" @click="confirmDelete = true">
                <svg class="size-4 text-danger-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M4 7h16M10 4h4a1 1 0 011 1v2H9V5a1 1 0 011-1z" /></svg>
            </UiButton>
        </template>
    </PageHeader>

    <div class="space-y-5">
        <!-- Financial summary -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UiStat label="مجموع درمان" :value="formatMoney(summary.billed, { suffix: false })" hint="ریال" />
            <UiStat label="پرداخت‌شده" :value="formatMoney(summary.paid, { suffix: false })" hint="ریال" tone="success" />
            <UiStat label="تخفیف" :value="formatMoney(summary.discount, { suffix: false })" hint="ریال" />
            <UiStat
                label="مانده حساب"
                :value="formatMoney(Math.abs(summary.balance), { suffix: false })"
                :hint="summary.balance > 0 ? 'بدهکار (ریال)' : summary.balance < 0 ? 'بستانکار (ریال)' : 'تسویه'"
                :tone="summary.balance > 0 ? 'danger' : summary.balance < 0 ? 'success' : 'neutral'"
            />
        </div>

        <!-- Dental chart -->
        <UiCard title="نمودار دندان" subtitle="دندان‌هایی که روی آن‌ها درمان ثبت شده">
            <Odontogram :model-value="[]" :treated="treatedTeeth" readonly compact />
        </UiCard>

        <!-- Tabs -->
        <div>
            <div class="mb-4 flex gap-1 overflow-x-auto border-b border-surface-200 dark:border-surface-800">
                <button
                    v-for="t in TABS"
                    :key="t.key"
                    type="button"
                    class="relative shrink-0 px-4 py-2.5 text-sm font-medium transition-colors"
                    :class="tab === t.key
                        ? 'text-brand-700 dark:text-brand-300'
                        : 'text-ink-500 hover:text-ink-900 dark:hover:text-ink-50'"
                    @click="tab = t.key"
                >
                    {{ t.label }}
                    <span v-if="t.count !== null" class="nums-tabular ms-1 text-xs text-ink-300">
                        {{ toPersianDigits(t.count) }}
                    </span>
                    <span v-if="tab === t.key" class="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-brand-600" />
                </button>
            </div>

            <!-- Treatments -->
            <UiCard v-if="tab === 'treatments'" :padded="false">
                <div v-if="treatments.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-surface-200 dark:border-surface-800">
                            <tr class="text-xs text-ink-500">
                                <th class="px-4 py-3 text-start font-semibold">تاریخ</th>
                                <th class="px-4 py-3 text-start font-semibold">درمان</th>
                                <th class="px-4 py-3 text-start font-semibold">دندان</th>
                                <th class="px-4 py-3 text-start font-semibold">پزشک</th>
                                <th class="px-4 py-3 text-end font-semibold">مبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in treatments" :key="t.id" class="border-b border-surface-100 last:border-0 dark:border-surface-800/60">
                                <td class="nums-tabular px-4 py-3 whitespace-nowrap text-ink-500">{{ jalali(t.performed_on) }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ t.service }}</p>
                                    <p v-if="t.category" class="text-[11px] text-ink-500">{{ t.category }}</p>
                                    <p v-if="t.description" class="mt-0.5 text-[11px] text-ink-500">{{ t.description }}</p>
                                </td>
                                <td class="nums-tabular px-4 py-3 text-xs">
                                    <span v-if="t.teeth?.length">{{ summarize(t.teeth, toPersianDigits) }}</span>
                                    <span v-else class="text-ink-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-ink-500">{{ t.user || '—' }}</td>
                                <td class="nums-tabular px-4 py-3 text-end font-medium whitespace-nowrap">
                                    {{ formatMoney(t.amount, { suffix: false }) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <UiEmpty v-else title="درمانی ثبت نشده" message="اولین درمان این بیمار را ثبت کنید.">
                    <template v-if="can('treatments.create')" #action>
                        <UiButton @click="showTreatment = true">ثبت درمان</UiButton>
                    </template>
                </UiEmpty>
            </UiCard>

            <!-- Payments -->
            <UiCard v-else-if="tab === 'payments'" :padded="false">
                <div v-if="payments.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-surface-200 dark:border-surface-800">
                            <tr class="text-xs text-ink-500">
                                <th class="px-4 py-3 text-start font-semibold">تاریخ</th>
                                <th class="px-4 py-3 text-start font-semibold">نحوه پرداخت</th>
                                <th class="px-4 py-3 text-start font-semibold">ثبت‌کننده</th>
                                <th class="px-4 py-3 text-end font-semibold">تخفیف</th>
                                <th class="px-4 py-3 text-end font-semibold">مبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in payments" :key="p.id" class="border-b border-surface-100 last:border-0 dark:border-surface-800/60">
                                <td class="nums-tabular px-4 py-3 whitespace-nowrap text-ink-500">
                                    {{ jalali(p.paid_on) }}
                                    <span v-if="p.paid_at" class="text-[11px]">{{ toPersianDigits(String(p.paid_at).slice(0, 5)) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ p.type || '—' }}
                                    <p v-if="p.description" class="text-[11px] text-ink-500">{{ p.description }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-ink-500">{{ p.user || '—' }}</td>
                                <td class="nums-tabular px-4 py-3 text-end text-ink-500">
                                    {{ p.discount ? formatMoney(p.discount, { suffix: false }) : '—' }}
                                </td>
                                <td class="nums-tabular px-4 py-3 text-end font-medium text-success-600 whitespace-nowrap">
                                    {{ formatMoney(p.amount, { suffix: false }) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <UiEmpty v-else title="پرداختی ثبت نشده" />
            </UiCard>

            <!-- Radiographs -->
            <UiCard v-else-if="tab === 'images'">
                <div v-if="radiographs.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <button
                        v-for="r in radiographs"
                        :key="r.id"
                        type="button"
                        class="group overflow-hidden rounded-lg border border-surface-200 text-start transition-shadow hover:shadow-[var(--shadow-raised)] dark:border-surface-800"
                        @click="lightbox = r"
                    >
                        <div class="aspect-4/3 bg-surface-900">
                            <img v-if="r.thumb" :src="r.thumb" :alt="r.subject || 'رادیوگرافی'" class="size-full object-cover" loading="lazy">
                            <div v-else class="flex size-full items-center justify-center text-xs text-ink-500">بدون فایل</div>
                        </div>
                        <div class="p-2">
                            <p class="nums-tabular truncate text-[11px] text-ink-500">{{ jalali(r.taken_on) }}</p>
                            <p v-if="r.subject" class="truncate text-xs">{{ r.subject }}</p>
                        </div>
                    </button>
                </div>
                <UiEmpty v-else title="تصویری ثبت نشده" />
            </UiCard>

            <!-- Prescriptions -->
            <UiCard v-else-if="tab === 'prescriptions'" :padded="false">
                <ul v-if="prescriptions.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                    <li v-for="p in prescriptions" :key="p.id">
                        <Link :href="route('prescriptions.show', p.id)" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-surface-50 dark:hover:bg-surface-800/40">
                            <div class="min-w-0">
                                <p class="nums-tabular text-sm font-medium">{{ jalali(p.prescribed_on, { full: true }) }}</p>
                                <p v-if="p.notes" class="truncate text-[11px] text-ink-500">{{ p.notes }}</p>
                            </div>
                            <UiBadge class="nums-tabular">{{ toPersianDigits(p.items_count) }} قلم</UiBadge>
                        </Link>
                    </li>
                </ul>
                <UiEmpty v-else title="نسخه‌ای ثبت نشده" />
            </UiCard>

            <!-- Demographics -->
            <UiCard v-else-if="tab === 'info'">
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="row in [
                        ['نام پدر', patient.father_name],
                        ['جنسیت', GENDER[patient.gender]],
                        ['تاریخ تولد', patient.birth_date ? jalali(patient.birth_date, { full: true }) : null],
                        ['کد ملی', patient.national_code ? toPersianDigits(patient.national_code) : null],
                        ['موبایل', patient.mobile ? toPersianDigits(patient.mobile) : null],
                        ['تلفن منزل', patient.home_phone ? toPersianDigits(patient.home_phone) : null],
                        ['تلفن محل کار', patient.work_phone ? toPersianDigits(patient.work_phone) : null],
                        ['شغل', patient.job],
                        ['معرف', patient.referrer_name],
                        ['کد زونکن', patient.binder_code],
                        ['تاریخ ثبت', jalali(patient.registered_on, { full: true })],
                        ['ثبت‌کننده', patient.created_by],
                        ['آدرس منزل', patient.home_address],
                        ['آدرس محل کار', patient.work_address],
                    ]" :key="row[0]">
                        <dt class="text-[11px] text-ink-500">{{ row[0] }}</dt>
                        <dd class="mt-0.5 text-sm" :class="row[1] ? '' : 'text-ink-300'">{{ row[1] || '—' }}</dd>
                    </div>
                </dl>

                <div v-if="patient.medical_summary || patient.description || patient.notes" class="mt-6 space-y-4 border-t border-surface-200 pt-5 dark:border-surface-800">
                    <div v-for="row in [
                        ['خلاصه وضعیت', patient.medical_summary],
                        ['توضیحات', patient.description],
                        ['ملاحظات', patient.notes],
                    ].filter(r => r[1])" :key="row[0]">
                        <p class="mb-1 text-[11px] font-medium text-ink-500">{{ row[0] }}</p>
                        <p class="text-sm leading-relaxed whitespace-pre-line">{{ row[1] }}</p>
                    </div>
                </div>
            </UiCard>
        </div>
    </div>

    <TreatmentFormModal :show="showTreatment" :patient="patient" @close="showTreatment = false" />
    <PaymentFormModal :show="showPayment" :patient="patient" :balance="summary.balance" @close="showPayment = false" />

    <UiConfirm
        :show="confirmDelete"
        title="حذف پرونده بیمار"
        :message="`پرونده ${patient.full_name} حذف شود؟ سوابق درمان و مالی حفظ می‌شوند و پرونده قابل بازیابی است.`"
        confirm-label="حذف پرونده"
        :processing="deleteForm.processing"
        @confirm="destroy"
        @cancel="confirmDelete = false"
    />

    <!-- Radiograph lightbox -->
    <Teleport to="body">
        <div v-if="lightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-surface-950/90 p-6" @click="lightbox = null">
            <img :src="lightbox.url" :alt="lightbox.subject || 'رادیوگرافی'" class="max-h-full max-w-full rounded-lg object-contain">
            <button type="button" class="absolute end-5 top-5 rounded-lg bg-white/10 p-2 text-white hover:bg-white/20" aria-label="بستن">
                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
            </button>
        </div>
    </Teleport>
</template>
