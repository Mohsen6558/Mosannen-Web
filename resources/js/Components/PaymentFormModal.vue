<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import UiButton from '@/Components/UiButton.vue';
import UiDatePicker from '@/Components/UiDatePicker.vue';
import UiField from '@/Components/UiField.vue';
import UiModal from '@/Components/UiModal.vue';
import UiMoneyInput from '@/Components/UiMoneyInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatMoney } from '@/Support/format';
import { todayIso } from '@/Support/jalali';

const props = defineProps({
    show: { type: Boolean, default: false },
    patient: { type: Object, required: true },
    balance: { type: Number, default: 0 },
});

const emit = defineEmits(['close']);

const { can } = usePermissions();
const paymentTypes = ref([]);

const form = useForm({
    patient_id: props.patient.id,
    payment_type_id: null,
    paid_on: todayIso(),
    amount: null,
    discount: 0,
    description: '',
    send_sms: false,
});

watch(() => props.show, async (open) => {
    if (!open || paymentTypes.value.length) return;

    const res = await fetch(route('lookups.payment-types'), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    paymentTypes.value = res.ok ? await res.json() : [];

    if (paymentTypes.value.length && !form.payment_type_id) {
        form.payment_type_id = paymentTypes.value[0].id;
    }
});

const typeOptions = computed(() => paymentTypes.value.map((t) => ({ value: t.id, label: t.name })));
const canSms = computed(() => !!props.patient.mobile);
const owes = computed(() => props.balance > 0);

function payFull() {
    form.amount = props.balance;
}

function submit() {
    form.post(route('payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('amount', 'discount', 'description', 'send_sms');
            emit('close');
        },
    });
}
</script>

<template>
    <UiModal :show="show" title="دریافت وجه" :subtitle="patient.full_name" @close="emit('close')">
        <form id="payment-form" class="space-y-4" @submit.prevent="submit">
            <div
                v-if="owes"
                class="flex items-center justify-between gap-3 rounded-lg border border-warning-500/30 bg-warning-50 px-3 py-2.5 text-sm dark:bg-warning-600/10"
            >
                <span class="text-warning-600">
                    مانده بدهی: <span class="nums-tabular font-semibold">{{ formatMoney(balance) }}</span>
                </span>
                <button type="button" class="shrink-0 text-xs font-medium text-brand-600 hover:underline" @click="payFull">
                    تسویه کامل
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <UiField label="نحوه پرداخت" :error="form.errors.payment_type_id">
                    <template #default="{ id, invalid }">
                        <UiSelect :id="id" v-model="form.payment_type_id" :options="typeOptions" :invalid="invalid" :nullable="false" />
                    </template>
                </UiField>

                <UiField label="تاریخ پرداخت" :error="form.errors.paid_on" required>
                    <template #default="{ invalid }">
                        <UiDatePicker v-model="form.paid_on" :invalid="invalid" :max="todayIso()" />
                    </template>
                </UiField>
            </div>

            <UiField label="مبلغ دریافتی" :error="form.errors.amount" required>
                <template #default="{ invalid }">
                    <UiMoneyInput v-model="form.amount" :invalid="invalid" />
                </template>
            </UiField>

            <UiField
                v-if="can('payments.discount')"
                label="تخفیف"
                :error="form.errors.discount"
                hint="مبلغی که از بدهی بیمار بخشیده می‌شود"
            >
                <template #default="{ invalid }">
                    <UiMoneyInput v-model="form.discount" :invalid="invalid" />
                </template>
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
                <input v-model="form.send_sms" type="checkbox" :disabled="!canSms"
                       class="size-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30 disabled:opacity-50">
                ارسال رسید پیامکی به بیمار
                <span v-if="!canSms" class="text-[11px]">(شماره موبایل ثبت نشده)</span>
            </label>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="emit('close')">انصراف</UiButton>
            <UiButton type="submit" form="payment-form" :loading="form.processing">ثبت پرداخت</UiButton>
        </template>
    </UiModal>
</template>
