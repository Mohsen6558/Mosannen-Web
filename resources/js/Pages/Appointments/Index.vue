<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import PatientPicker from '@/Components/PatientPicker.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiDatePicker from '@/Components/UiDatePicker.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiModal from '@/Components/UiModal.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { toPersianDigits } from '@/Support/format';
import { jalali } from '@/Support/jalali';

const props = defineProps({
    date: { type: String, required: true },
    appointments: { type: Array, default: () => [] },
    doctors: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const date = ref(props.date);
const modalOpen = ref(false);

const STATUS = {
    scheduled: { label: 'ثبت‌شده', tone: 'info' },
    confirmed: { label: 'تایید‌شده', tone: 'brand' },
    done: { label: 'انجام‌شده', tone: 'success' },
    cancelled: { label: 'لغو‌شده', tone: 'neutral' },
    no_show: { label: 'غایب', tone: 'danger' },
};

watch(date, (v) => {
    if (v && v !== props.date) {
        router.get(route('appointments.index'), { date: v }, { preserveState: true, replace: true });
    }
});

const form = useForm({
    patient_id: null,
    user_id: null,
    treatment_service_id: null,
    scheduled_on: props.date,
    starts_at: '09:00',
    duration_minutes: 30,
    status: 'scheduled',
    notes: '',
});

// The working day, so gaps between appointments are visible at a glance.
const timeline = computed(() => {
    const slots = [];
    for (let h = 8; h <= 21; h++) {
        slots.push({
            hour: h,
            label: `${String(h).padStart(2, '0')}:00`,
            items: props.appointments.filter((a) => Number(String(a.starts_at).slice(0, 2)) === h),
        });
    }

    return slots;
});

function openNew(hour = null) {
    if (!can('appointments.manage')) return;

    form.reset();
    form.scheduled_on = date.value;
    if (hour !== null) form.starts_at = `${String(hour).padStart(2, '0')}:00`;
    modalOpen.value = true;
}

function submit() {
    form.post(route('appointments.store'), {
        preserveScroll: true,
        onSuccess: () => (modalOpen.value = false),
    });
}

function setStatus(appointment, status) {
    router.put(route('appointments.update', appointment.id), {
        patient_id: appointment.patient.id,
        user_id: null,
        treatment_service_id: null,
        scheduled_on: props.date,
        starts_at: appointment.starts_at,
        duration_minutes: appointment.duration_minutes,
        status,
        notes: appointment.notes,
    }, { preserveScroll: true });
}
</script>

<template>
    <Head title="نوبت‌دهی" />

    <PageHeader title="نوبت‌دهی" :subtitle="jalali(date, { full: true, withWeekday: true })">
        <template #actions>
            <div class="w-40">
                <UiDatePicker v-model="date" :clearable="false" />
            </div>
            <UiButton v-if="can('appointments.manage')" @click="openNew()">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                </template>
                نوبت جدید
            </UiButton>
        </template>
    </PageHeader>

    <UiCard :padded="false">
        <div v-if="appointments.length" class="divide-y divide-surface-100 dark:divide-surface-800">
            <div v-for="slot in timeline" :key="slot.hour" class="flex gap-4 px-4 py-3">
                <span class="nums-tabular w-12 shrink-0 pt-0.5 text-xs font-medium text-ink-300">{{ toPersianDigits(slot.label) }}</span>

                <div class="min-w-0 flex-1">
                    <div v-if="slot.items.length" class="space-y-2">
                        <div
                            v-for="a in slot.items"
                            :key="a.id"
                            class="flex flex-wrap items-center gap-3 rounded-lg border border-surface-200 px-3 py-2.5 dark:border-surface-800"
                        >
                            <span class="nums-tabular text-sm font-semibold text-brand-700 dark:text-brand-300">
                                {{ toPersianDigits(a.starts_at) }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <Link :href="route('patients.show', a.patient.id)" class="block truncate text-sm font-medium hover:text-brand-600">
                                    {{ a.patient.name }}
                                </Link>
                                <p class="truncate text-[11px] text-ink-500">
                                    <span v-if="a.service">{{ a.service }}</span>
                                    <span v-if="a.doctor"> — {{ a.doctor }}</span>
                                    <span class="nums-tabular"> — {{ toPersianDigits(a.duration_minutes) }} دقیقه</span>
                                </p>
                                <p v-if="a.notes" class="truncate text-[11px] text-ink-300">{{ a.notes }}</p>
                            </div>

                            <span v-if="a.patient.mobile" class="nums-tabular hidden text-xs text-ink-500 sm:block" dir="ltr">
                                {{ toPersianDigits(a.patient.mobile) }}
                            </span>

                            <UiBadge :tone="STATUS[a.status]?.tone" dot>{{ STATUS[a.status]?.label }}</UiBadge>

                            <div v-if="can('appointments.manage')" class="flex gap-1">
                                <button
                                    v-for="s in ['confirmed', 'done', 'no_show']"
                                    :key="s"
                                    type="button"
                                    class="rounded-md px-2 py-1 text-[11px] font-medium transition-colors"
                                    :class="a.status === s
                                        ? 'bg-brand-600 text-white'
                                        : 'text-ink-500 hover:bg-surface-100 dark:hover:bg-surface-800'"
                                    @click="setStatus(a, s)"
                                >
                                    {{ STATUS[s].label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        v-else-if="can('appointments.manage')"
                        type="button"
                        class="w-full rounded-lg border border-dashed border-surface-200 py-2 text-[11px] text-ink-300 transition-colors hover:border-brand-400 hover:text-brand-600 dark:border-surface-800"
                        @click="openNew(slot.hour)"
                    >
                        + افزودن نوبت
                    </button>
                    <div v-else class="py-2" />
                </div>
            </div>
        </div>

        <UiEmpty v-else title="نوبتی برای این روز ثبت نشده" message="با دکمه «نوبت جدید» اولین نوبت را ثبت کنید.">
            <template v-if="can('appointments.manage')" #action>
                <UiButton @click="openNew()">نوبت جدید</UiButton>
            </template>
        </UiEmpty>
    </UiCard>

    <UiModal :show="modalOpen" title="نوبت جدید" @close="modalOpen = false">
        <form id="appointment-form" class="space-y-4" @submit.prevent="submit">
            <UiField label="بیمار" :error="form.errors.patient_id" required>
                <PatientPicker v-model="form.patient_id" :invalid="!!form.errors.patient_id" />
            </UiField>

            <div class="grid gap-4 sm:grid-cols-2">
                <UiField label="تاریخ" :error="form.errors.scheduled_on" required>
                    <template #default="{ invalid }">
                        <UiDatePicker v-model="form.scheduled_on" :invalid="invalid" :clearable="false" />
                    </template>
                </UiField>

                <UiField label="ساعت" :error="form.errors.starts_at" required>
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.starts_at" :invalid="invalid" type="time" dir="ltr" class="text-start nums-tabular" />
                    </template>
                </UiField>

                <UiField label="مدت (دقیقه)" :error="form.errors.duration_minutes" required>
                    <template #default="{ id }">
                        <UiSelect
                            :id="id"
                            v-model.number="form.duration_minutes"
                            :nullable="false"
                            :options="[15, 30, 45, 60, 90, 120].map(m => ({ value: m, label: `${toPersianDigits(m)} دقیقه` }))"
                        />
                    </template>
                </UiField>

                <UiField label="پزشک" :error="form.errors.user_id">
                    <template #default="{ id }">
                        <UiSelect :id="id" v-model="form.user_id" :options="doctors.map(d => ({ value: d.id, label: d.full_name || d.name }))" placeholder="—" />
                    </template>
                </UiField>

                <UiField label="نوع درمان" :error="form.errors.treatment_service_id" class="sm:col-span-2">
                    <template #default="{ id }">
                        <UiSelect :id="id" v-model="form.treatment_service_id" :options="services.map(s => ({ value: s.id, label: s.name }))" placeholder="—" />
                    </template>
                </UiField>
            </div>

            <UiField label="یادداشت" :error="form.errors.notes">
                <template #default="{ id }">
                    <UiTextarea :id="id" v-model="form.notes" :rows="2" />
                </template>
            </UiField>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="modalOpen = false">انصراف</UiButton>
            <UiButton type="submit" form="appointment-form" :loading="form.processing">ثبت نوبت</UiButton>
        </template>
    </UiModal>
</template>
