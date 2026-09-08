<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Odontogram from '@/Components/Odontogram.vue';
import UiButton from '@/Components/UiButton.vue';
import UiDatePicker from '@/Components/UiDatePicker.vue';
import UiField from '@/Components/UiField.vue';
import UiModal from '@/Components/UiModal.vue';
import UiMoneyInput from '@/Components/UiMoneyInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { todayIso } from '@/Support/jalali';

const props = defineProps({
    show: { type: Boolean, default: false },
    patient: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const services = ref([]);
const loadingServices = ref(false);

const form = useForm({
    patient_id: props.patient.id,
    treatment_service_id: null,
    performed_on: todayIso(),
    amount: null,
    description: '',
    teeth: [],
    send_sms: false,
});

// The catalogue is only needed once the modal opens, so it is fetched then
// rather than shipped with every patient page.
watch(() => props.show, async (open) => {
    if (!open || services.value.length) return;

    loadingServices.value = true;
    try {
        const res = await fetch(route('lookups.services'), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        services.value = res.ok ? await res.json() : [];
    } finally {
        loadingServices.value = false;
    }
});

const serviceOptions = computed(() =>
    services.value.map((s) => ({ value: s.id, label: s.name })),
);

// Selecting a service pre-fills its tariff; the operator can still override.
watch(() => form.treatment_service_id, (id) => {
    const service = services.value.find((s) => String(s.id) === String(id));
    if (service) form.amount = Number(service.price);
});

const canSms = computed(() => !!props.patient.mobile);

function submit() {
    form.post(route('treatments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('treatment_service_id', 'amount', 'description', 'teeth', 'send_sms');
            emit('close');
        },
    });
}
</script>

<template>
    <UiModal :show="show" size="lg" title="ثبت درمان" :subtitle="patient.full_name" @close="emit('close')">
        <form id="treatment-form" class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <UiField label="نوع درمان" :error="form.errors.treatment_service_id" required>
                    <template #default="{ id, invalid }">
                        <UiSelect
                            :id="id"
                            v-model="form.treatment_service_id"
                            :options="serviceOptions"
                            :invalid="invalid"
                            :placeholder="loadingServices ? 'در حال بارگذاری…' : '— انتخاب کنید —'"
                        />
                    </template>
                </UiField>

                <UiField label="تاریخ درمان" :error="form.errors.performed_on" required>
                    <template #default="{ invalid }">
                        <UiDatePicker v-model="form.performed_on" :invalid="invalid" :max="todayIso()" />
                    </template>
                </UiField>
            </div>

            <UiField label="مبلغ" :error="form.errors.amount" hint="با انتخاب نوع درمان، تعرفه به‌صورت خودکار وارد می‌شود" required>
                <template #default="{ invalid }">
                    <UiMoneyInput v-model="form.amount" :invalid="invalid" />
                </template>
            </UiField>

            <UiField label="دندان‌های درمان‌شده" :error="form.errors.teeth">
                <Odontogram v-model="form.teeth" compact />
            </UiField>

            <UiField label="توضیحات" :error="form.errors.description">
                <template #default="{ id }">
                    <UiTextarea :id="id" v-model="form.description" :rows="2" />
                </template>
            </UiField>

            <label
                class="flex items-center gap-2 text-sm"
                :class="canSms ? 'cursor-pointer text-ink-700 dark:text-ink-100' : 'cursor-not-allowed text-ink-300'"
            >
                <input
                    v-model="form.send_sms"
                    type="checkbox"
                    :disabled="!canSms"
                    class="size-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30 disabled:opacity-50"
                >
                ارسال پیامک اطلاع‌رسانی به بیمار
                <span v-if="!canSms" class="text-[11px]">(شماره موبایل ثبت نشده)</span>
            </label>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="emit('close')">انصراف</UiButton>
            <UiButton type="submit" form="treatment-form" :loading="form.processing">ثبت درمان</UiButton>
        </template>
    </UiModal>
</template>
