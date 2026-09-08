<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiDatePicker from '@/Components/UiDatePicker.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { toPersianDigits } from '@/Support/format';
import { todayIso } from '@/Support/jalali';

const props = defineProps({
    patient: { type: Object, default: null },
    nextCode: { type: [Number, null], default: null },
    insurances: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.patient);

const form = useForm({
    first_name: props.patient?.first_name ?? '',
    last_name: props.patient?.last_name ?? '',
    father_name: props.patient?.father_name ?? '',
    birth_date: props.patient?.birth_date ?? null,
    registered_on: props.patient?.registered_on ?? todayIso(),
    gender: props.patient?.gender ?? null,
    national_code: props.patient?.national_code ?? '',
    mobile: props.patient?.mobile ?? '',
    home_phone: props.patient?.home_phone ?? '',
    work_phone: props.patient?.work_phone ?? '',
    home_address: props.patient?.home_address ?? '',
    work_address: props.patient?.work_address ?? '',
    job: props.patient?.job ?? '',
    referrer_name: props.patient?.referrer_name ?? '',
    binder_code: props.patient?.binder_code ?? '',
    medical_summary: props.patient?.medical_summary ?? '',
    description: props.patient?.description ?? '',
    notes: props.patient?.notes ?? '',
    insurance_id: props.patient?.insurance_id ?? null,
});

const insuranceOptions = computed(() =>
    props.insurances.map((i) => ({ value: i.id, label: i.name })),
);

function submit() {
    isEdit.value
        ? form.put(route('patients.update', props.patient.id))
        : form.post(route('patients.store'));
}
</script>

<template>
    <Head :title="isEdit ? `ویرایش ${patient.first_name} ${patient.last_name}` : 'بیمار جدید'" />

    <PageHeader
        :title="isEdit ? 'ویرایش مشخصات بیمار' : 'ثبت بیمار جدید'"
        :subtitle="isEdit
            ? `کد پرونده ${toPersianDigits(patient.code)}`
            : nextCode ? `کد پرونده جدید: ${toPersianDigits(nextCode)}` : null"
        :breadcrumbs="[
            { label: 'پرونده بیماران', href: route('patients.index') },
            { label: isEdit ? 'ویرایش' : 'بیمار جدید' },
        ]"
    />

    <form class="mx-auto max-w-4xl space-y-5" @submit.prevent="submit">
        <UiCard title="اطلاعات هویتی">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <UiField label="نام" :error="form.errors.first_name" required>
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.first_name" :invalid="invalid" autofocus />
                    </template>
                </UiField>

                <UiField label="نام خانوادگی" :error="form.errors.last_name" required>
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.last_name" :invalid="invalid" />
                    </template>
                </UiField>

                <UiField label="نام پدر" :error="form.errors.father_name">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.father_name" :invalid="invalid" />
                    </template>
                </UiField>

                <UiField label="جنسیت" :error="form.errors.gender">
                    <template #default="{ id }">
                        <UiSelect :id="id" v-model="form.gender"
                                  :options="[{ value: 'm', label: 'مرد' }, { value: 'f', label: 'زن' }]" />
                    </template>
                </UiField>

                <UiField label="تاریخ تولد" :error="form.errors.birth_date">
                    <template #default="{ invalid }">
                        <UiDatePicker v-model="form.birth_date" :invalid="invalid" :max="todayIso()" />
                    </template>
                </UiField>

                <UiField label="کد ملی" :error="form.errors.national_code">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.national_code" :invalid="invalid" numeric
                                 inputmode="numeric" maxlength="10" dir="ltr" class="text-start nums-tabular" />
                    </template>
                </UiField>
            </div>
        </UiCard>

        <UiCard title="اطلاعات تماس">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <UiField label="موبایل" :error="form.errors.mobile" hint="برای ارسال پیامک و یادآوری نوبت">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.mobile" :invalid="invalid" numeric
                                 inputmode="tel" placeholder="09xxxxxxxxx" dir="ltr" class="text-start nums-tabular" />
                    </template>
                </UiField>

                <UiField label="تلفن منزل" :error="form.errors.home_phone">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.home_phone" :invalid="invalid" numeric dir="ltr" class="text-start nums-tabular" />
                    </template>
                </UiField>

                <UiField label="تلفن محل کار" :error="form.errors.work_phone">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.work_phone" :invalid="invalid" numeric dir="ltr" class="text-start nums-tabular" />
                    </template>
                </UiField>

                <UiField label="آدرس منزل" :error="form.errors.home_address" class="sm:col-span-2 lg:col-span-3">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.home_address" :invalid="invalid" />
                    </template>
                </UiField>

                <UiField label="آدرس محل کار" :error="form.errors.work_address" class="sm:col-span-2 lg:col-span-3">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.work_address" :invalid="invalid" />
                    </template>
                </UiField>
            </div>
        </UiCard>

        <UiCard title="پرونده">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <UiField label="بیمه" :error="form.errors.insurance_id">
                    <template #default="{ id }">
                        <UiSelect :id="id" v-model="form.insurance_id" :options="insuranceOptions" placeholder="آزاد" />
                    </template>
                </UiField>

                <UiField label="تاریخ ثبت" :error="form.errors.registered_on" required>
                    <template #default="{ invalid }">
                        <UiDatePicker v-model="form.registered_on" :invalid="invalid" />
                    </template>
                </UiField>

                <UiField label="کد زونکن" :error="form.errors.binder_code" hint="محل نگهداری پرونده کاغذی">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.binder_code" :invalid="invalid" />
                    </template>
                </UiField>

                <UiField label="شغل" :error="form.errors.job">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.job" :invalid="invalid" />
                    </template>
                </UiField>

                <UiField label="معرف" :error="form.errors.referrer_name">
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.referrer_name" :invalid="invalid" />
                    </template>
                </UiField>
            </div>
        </UiCard>

        <UiCard title="یادداشت‌های پزشکی">
            <div class="space-y-4">
                <UiField label="خلاصه وضعیت" :error="form.errors.medical_summary"
                         hint="سوابق پزشکی، حساسیت‌ها، داروهای مصرفی">
                    <template #default="{ id, invalid }">
                        <UiTextarea :id="id" v-model="form.medical_summary" :invalid="invalid" :rows="3" />
                    </template>
                </UiField>

                <UiField label="توضیحات" :error="form.errors.description">
                    <template #default="{ id, invalid }">
                        <UiTextarea :id="id" v-model="form.description" :invalid="invalid" :rows="2" />
                    </template>
                </UiField>

                <UiField label="ملاحظات" :error="form.errors.notes">
                    <template #default="{ id, invalid }">
                        <UiTextarea :id="id" v-model="form.notes" :invalid="invalid" :rows="2" />
                    </template>
                </UiField>
            </div>
        </UiCard>

        <div class="flex items-center justify-end gap-2 pb-4">
            <UiButton variant="secondary" :href="isEdit ? route('patients.show', patient.id) : route('patients.index')">
                انصراف
            </UiButton>
            <UiButton type="submit" size="lg" :loading="form.processing">
                {{ isEdit ? 'ذخیره تغییرات' : 'ثبت بیمار' }}
            </UiButton>
        </div>
    </form>
</template>
