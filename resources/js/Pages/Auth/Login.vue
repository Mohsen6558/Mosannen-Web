<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import UiButton from '@/Components/UiButton.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';

defineOptions({ layout: null });

const form = useForm({ username: '', password: '', remember: false });

function submit() {
    form.post(route('login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="ورود" />

    <div class="flex min-h-dvh items-center justify-center bg-surface-100 p-4 dark:bg-surface-950">
        <div class="w-full max-w-sm">
            <div class="mb-7 text-center">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-[var(--shadow-raised)]">
                    <svg class="size-7" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 3c-2.2 0-3 1-4.5 1S5 3.4 4.2 4.6C3.2 6.1 3.6 8.6 4.3 11c.6 2 .7 3.4.9 5.2.2 1.7.5 3.8 1.9 3.8 1.3 0 1.5-1.6 1.8-3.4.3-1.8.6-3.1 1.6-3.1s1.3 1.3 1.6 3.1c.3 1.8.5 3.4 1.8 3.4 1.4 0 1.7-2.1 1.9-3.8.2-1.8.3-3.2.9-5.2.7-2.4 1.1-4.9.1-6.4C17.5 3.4 16 4 14.5 4S14.2 3 12 3z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">
                    {{ $page.props.clinic?.name || 'سامانه مدیریت کلینیک' }}
                </h1>
                <p class="mt-1 text-sm text-ink-500">برای ادامه وارد شوید</p>
            </div>

            <form class="card space-y-4 p-6" @submit.prevent="submit">
                <UiField label="نام کاربری" :error="form.errors.username" required>
                    <template #default="{ id, invalid }">
                        <UiInput
                            :id="id"
                            v-model="form.username"
                            :invalid="invalid"
                            autocomplete="username"
                            autofocus
                            dir="ltr"
                            class="text-start"
                        />
                    </template>
                </UiField>

                <UiField label="رمز عبور" :error="form.errors.password" required>
                    <template #default="{ id, invalid }">
                        <UiInput
                            :id="id"
                            v-model="form.password"
                            :invalid="invalid"
                            type="password"
                            autocomplete="current-password"
                            dir="ltr"
                            class="text-start"
                        />
                    </template>
                </UiField>

                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-700 dark:text-ink-100">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="size-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30 dark:border-surface-600 dark:bg-surface-800"
                    >
                    مرا به خاطر بسپار
                </label>

                <UiButton type="submit" block size="lg" :loading="form.processing">ورود</UiButton>
            </form>

            <p class="mt-6 text-center text-[11px] text-ink-300">
                سامانه مدیریت کلینیک دندانپزشکی
            </p>
        </div>
    </div>
</template>
