<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';

defineProps({ mustChange: { type: Boolean, default: false } });

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

function submit() {
    form.put(route('profile.update'), {
        onSuccess: () => form.reset(),
        onError: () => form.reset('current_password'),
    });
}
</script>

<template>
    <Head title="تغییر رمز عبور" />

    <div class="mx-auto max-w-lg">
        <PageHeader title="تغییر رمز عبور" />

        <div
            v-if="mustChange"
            class="mb-4 rounded-lg border border-warning-500/30 bg-warning-50 px-4 py-3 text-sm text-warning-600 dark:bg-warning-600/10"
        >
            حساب کاربری شما از سیستم قدیمی منتقل شده است. برای ادامه باید یک رمز عبور جدید انتخاب کنید.
        </div>

        <UiCard title="رمز عبور جدید">
            <form class="space-y-4" @submit.prevent="submit">
                <UiField label="رمز عبور فعلی" :error="form.errors.current_password" required>
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.current_password" :invalid="invalid" type="password" dir="ltr" class="text-start" autocomplete="current-password" />
                    </template>
                </UiField>

                <UiField label="رمز عبور جدید" :error="form.errors.password" hint="حداقل ۱۰ کاراکتر، شامل حرف و عدد" required>
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.password" :invalid="invalid" type="password" dir="ltr" class="text-start" autocomplete="new-password" />
                    </template>
                </UiField>

                <UiField label="تکرار رمز عبور جدید" required>
                    <template #default="{ id }">
                        <UiInput :id="id" v-model="form.password_confirmation" type="password" dir="ltr" class="text-start" autocomplete="new-password" />
                    </template>
                </UiField>

                <UiButton type="submit" :loading="form.processing">ذخیره</UiButton>
            </form>
        </UiCard>
    </div>
</template>
