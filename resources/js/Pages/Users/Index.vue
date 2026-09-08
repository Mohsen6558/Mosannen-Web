<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiConfirm from '@/Components/UiConfirm.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiModal from '@/Components/UiModal.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { initials } from '@/Support/format';
import { jalaliDateTime } from '@/Support/jalali';

const props = defineProps({
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    catalogue: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const open = ref(false);
const editing = ref(null);
const deleting = ref(null);

const ROLE_LABELS = {
    admin: 'مدیر سیستم',
    doctor: 'پزشک',
    reception: 'پذیرش',
    accountant: 'حسابدار',
    assistant: 'دستیار',
};

const form = useForm({
    username: '',
    full_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
    permissions: [],
    is_active: true,
});

// An admin holds every ability through a gate, so the per-permission
// checkboxes would be misleading.
const isAdmin = computed(() => form.roles.includes('admin'));

function edit(user = null) {
    editing.value = user;
    form.defaults({
        username: user?.username ?? '',
        full_name: user?.full_name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        roles: user ? [...user.roles] : [],
        permissions: user ? [...user.permissions] : [],
        is_active: user?.is_active ?? true,
    });
    form.reset();
    form.clearErrors();
    open.value = true;
}

function toggleRole(role) {
    form.roles = form.roles.includes(role)
        ? form.roles.filter((r) => r !== role)
        : [...form.roles, role];
}

function togglePermission(permission) {
    form.permissions = form.permissions.includes(permission)
        ? form.permissions.filter((p) => p !== permission)
        : [...form.permissions, permission];
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    editing.value
        ? form.put(route('users.update', editing.value.id), options)
        : form.post(route('users.store'), options);
}

function destroy() {
    router.delete(route('users.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="کاربران" />

    <PageHeader title="کاربران" subtitle="حساب‌های کاربری و سطوح دسترسی">
        <template #actions>
            <UiButton v-if="can('audit.view')" variant="secondary" :href="route('users.activity')">گزارش فعالیت‌ها</UiButton>
            <UiButton @click="edit()">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                </template>
                کاربر جدید
            </UiButton>
        </template>
    </PageHeader>

    <UiCard :padded="false">
        <ul class="divide-y divide-surface-100 dark:divide-surface-800">
            <li v-for="u in users" :key="u.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                    {{ initials(u.full_name || u.username) }}
                </span>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ u.full_name || u.username }}
                        <span class="font-mono text-[11px] text-ink-300" dir="ltr">@{{ u.username }}</span>
                    </p>
                    <p class="nums-tabular truncate text-[11px] text-ink-500">
                        <span v-if="u.last_login_at">آخرین ورود: {{ jalaliDateTime(u.last_login_at) }}</span>
                        <span v-else>تاکنون وارد نشده</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <UiBadge v-for="r in u.roles" :key="r" tone="brand">{{ ROLE_LABELS[r] ?? r }}</UiBadge>
                    <UiBadge v-if="!u.roles.length && u.permissions.length" tone="neutral">
                        دسترسی سفارشی
                    </UiBadge>
                    <UiBadge v-if="!u.is_active" tone="danger">غیرفعال</UiBadge>
                    <UiBadge v-if="u.must_change_password" tone="warning">نیازمند تغییر رمز</UiBadge>
                </div>

                <div class="flex shrink-0 gap-1">
                    <UiButton size="sm" variant="secondary" @click="edit(u)">ویرایش</UiButton>
                    <UiButton size="sm" variant="ghost" @click="deleting = u">
                        <svg class="size-4 text-danger-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M4 7h16M10 4h4a1 1 0 011 1v2H9V5a1 1 0 011-1z" /></svg>
                    </UiButton>
                </div>
            </li>
        </ul>
    </UiCard>

    <UiModal :show="open" size="lg" :title="editing ? `ویرایش ${editing.full_name || editing.username}` : 'کاربر جدید'" @close="open = false">
        <form id="user-form" class="space-y-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <UiField label="نام کاربری" :error="form.errors.username" hint="فقط حروف انگلیسی، عدد، خط تیره" required>
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.username" :invalid="invalid" dir="ltr" class="text-start" />
                    </template>
                </UiField>

                <UiField label="نام و نام خانوادگی" :error="form.errors.full_name" required>
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.full_name" :invalid="invalid" />
                    </template>
                </UiField>

                <UiField
                    label="رمز عبور"
                    :error="form.errors.password"
                    :hint="editing ? 'خالی بگذارید تا تغییر نکند' : 'کاربر در اولین ورود باید آن را تغییر دهد'"
                    :required="!editing"
                >
                    <template #default="{ id, invalid }">
                        <UiInput :id="id" v-model="form.password" :invalid="invalid" type="password" dir="ltr" class="text-start" autocomplete="new-password" />
                    </template>
                </UiField>

                <UiField label="تکرار رمز عبور">
                    <template #default="{ id }">
                        <UiInput :id="id" v-model="form.password_confirmation" type="password" dir="ltr" class="text-start" autocomplete="new-password" />
                    </template>
                </UiField>
            </div>

            <div>
                <p class="label-base">نقش</p>
                <p v-if="form.errors.roles" class="mb-1.5 text-xs text-danger-500">{{ form.errors.roles }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="r in roles"
                        :key="r"
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="form.roles.includes(r)
                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-200'
                            : 'border-surface-300 text-ink-500 hover:bg-surface-100 dark:border-surface-700 dark:hover:bg-surface-800'"
                        @click="toggleRole(r)"
                    >
                        {{ ROLE_LABELS[r] ?? r }}
                    </button>
                </div>
            </div>

            <div v-if="isAdmin" class="rounded-lg border border-info-500/30 bg-info-50 px-3 py-2.5 text-xs text-info-600 dark:bg-info-600/10">
                نقش «مدیر سیستم» به همه بخش‌ها دسترسی کامل دارد؛ انتخاب دسترسی‌های جداگانه لازم نیست.
            </div>

            <div v-else>
                <p class="label-base">دسترسی‌های اختصاصی</p>
                <div class="space-y-3">
                    <div v-for="(group, key) in catalogue" :key="key" class="rounded-lg border border-surface-200 p-3 dark:border-surface-800">
                        <p class="mb-2 text-xs font-semibold text-ink-500">{{ group.label }}</p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                            <label
                                v-for="(label, permission) in group.abilities"
                                :key="permission"
                                class="flex cursor-pointer items-center gap-1.5 text-xs"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.permissions.includes(permission)"
                                    class="size-3.5 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30"
                                    @change="togglePermission(permission)"
                                >
                                {{ label }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="size-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30">
                حساب فعال باشد
            </label>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="open = false">انصراف</UiButton>
            <UiButton type="submit" form="user-form" :loading="form.processing">ذخیره</UiButton>
        </template>
    </UiModal>

    <UiConfirm
        :show="!!deleting"
        title="حذف کاربر"
        :message="deleting ? `حساب کاربری ${deleting.full_name || deleting.username} حذف شود؟` : ''"
        confirm-label="حذف"
        @confirm="destroy"
        @cancel="deleting = null"
    />
</template>
