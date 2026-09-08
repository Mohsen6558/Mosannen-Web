<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { refDebounced } from '@vueuse/core';
import PageHeader from '@/Components/PageHeader.vue';
import PatientPicker from '@/Components/PatientPicker.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiModal from '@/Components/UiModal.vue';
import UiPagination from '@/Components/UiPagination.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { toPersianDigits } from '@/Support/format';
import { jalaliDateTime } from '@/Support/jalali';

const props = defineProps({
    messages: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    templates: { type: Array, default: () => [] },
    driver: { type: String, default: 'log' },
    counts: { type: Object, default: () => ({ queued: 0, failed: 0 }) },
});

const { can } = usePermissions();

const q = ref(props.filters.q ?? '');
const debounced = refDebounced(q, 300);
const status = ref(props.filters.status ?? null);
const composeOpen = ref(false);
const editingTemplate = ref(null);

const STATUS = {
    queued: { label: 'در صف', tone: 'info' },
    sent: { label: 'ارسال‌شده', tone: 'success' },
    failed: { label: 'ناموفق', tone: 'danger' },
    cancelled: { label: 'لغو‌شده', tone: 'neutral' },
};

watch([debounced, status], () => {
    router.get(route('sms.index'), {
        q: q.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
});

const compose = useForm({ patient_id: null, mobile: '', body: '' });
const templateForm = useForm({ name: '', body: '', is_active: true });

function send() {
    compose.post(route('sms.send'), {
        preserveScroll: true,
        onSuccess: () => {
            compose.reset();
            composeOpen.value = false;
        },
    });
}

function openTemplate(t) {
    editingTemplate.value = t;
    templateForm.defaults({ name: t.name, body: t.body, is_active: t.is_active });
    templateForm.reset();
}

function saveTemplate() {
    templateForm.put(route('sms.templates.update', editingTemplate.value.id), {
        preserveScroll: true,
        onSuccess: () => (editingTemplate.value = null),
    });
}
</script>

<template>
    <Head title="پیامک" />

    <PageHeader title="پیامک" :subtitle="`سرویس فعال: ${driver === 'log' ? 'ثبت در لاگ (آزمایشی)' : driver}`">
        <template #actions>
            <UiButton v-if="can('sms.send')" @click="composeOpen = true">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                </template>
                ارسال پیامک
            </UiButton>
        </template>
    </PageHeader>

    <div class="space-y-4">
        <div
            v-if="driver === 'log'"
            class="rounded-lg border border-info-500/30 bg-info-50 px-4 py-3 text-sm text-info-600 dark:bg-info-600/10"
        >
            سرویس پیامک روی حالت آزمایشی است؛ پیام‌ها فقط در لاگ ثبت می‌شوند و ارسال واقعی انجام نمی‌شود.
            برای فعال‌سازی، <code class="rounded bg-info-500/10 px-1 font-mono text-xs">SMS_DRIVER</code>
            را در فایل <code class="rounded bg-info-500/10 px-1 font-mono text-xs">.env</code> تنظیم کنید.
        </div>

        <UiCard v-if="templates.length" title="قالب‌های پیامک" subtitle="متن پیام‌های خودکار سیستم">
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="t in templates"
                    :key="t.id"
                    type="button"
                    class="rounded-lg border border-surface-200 p-3 text-start transition-colors hover:border-brand-400 dark:border-surface-800"
                    @click="openTemplate(t)"
                >
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <p class="text-sm font-medium">{{ t.name }}</p>
                        <UiBadge :tone="t.is_active ? 'success' : 'neutral'">{{ t.is_active ? 'فعال' : 'غیرفعال' }}</UiBadge>
                    </div>
                    <p class="line-clamp-2 text-[11px] leading-relaxed text-ink-500">{{ t.body }}</p>
                </button>
            </div>
        </UiCard>

        <UiCard>
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <UiInput v-model="q" placeholder="جستجو در شماره یا متن پیام…" />
                </div>
                <div class="w-40">
                    <UiSelect
                        v-model="status"
                        placeholder="همه وضعیت‌ها"
                        :options="Object.entries(STATUS).map(([v, s]) => ({ value: v, label: s.label }))"
                    />
                </div>
                <div class="flex gap-2 text-xs text-ink-500">
                    <span class="nums-tabular">در صف: {{ toPersianDigits(counts.queued) }}</span>
                    <span class="nums-tabular">ناموفق: {{ toPersianDigits(counts.failed) }}</span>
                </div>
            </div>
        </UiCard>

        <UiCard :padded="false">
            <ul v-if="messages.data.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                <li v-for="m in messages.data" :key="m.id" class="flex flex-wrap items-start gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <span class="nums-tabular text-sm font-medium" dir="ltr">{{ toPersianDigits(m.mobile) }}</span>
                            <Link v-if="m.patient" :href="route('patients.show', m.patient.id)" class="text-xs text-brand-600 hover:underline">
                                {{ m.patient.name }}
                            </Link>
                            <UiBadge :tone="STATUS[m.status]?.tone" dot>{{ STATUS[m.status]?.label }}</UiBadge>
                        </div>
                        <p class="text-sm leading-relaxed whitespace-pre-line">{{ m.body }}</p>
                        <p v-if="m.error" class="mt-1 text-[11px] text-danger-500">{{ m.error }}</p>
                        <p class="nums-tabular mt-1 text-[11px] text-ink-300">
                            {{ jalaliDateTime(m.sent_at || m.created_at) }}
                        </p>
                    </div>

                    <div v-if="can('sms.send')" class="flex shrink-0 gap-1">
                        <UiButton
                            v-if="m.status === 'failed'"
                            size="sm"
                            variant="secondary"
                            @click="router.post(route('sms.retry', m.id), {}, { preserveScroll: true })"
                        >
                            ارسال مجدد
                        </UiButton>
                        <UiButton
                            v-if="m.status === 'queued'"
                            size="sm"
                            variant="ghost"
                            @click="router.post(route('sms.cancel', m.id), {}, { preserveScroll: true })"
                        >
                            لغو
                        </UiButton>
                    </div>
                </li>
            </ul>

            <UiEmpty v-else title="پیامکی یافت نشد" />

            <template #footer>
                <UiPagination :meta="messages" />
            </template>
        </UiCard>
    </div>

    <!-- Compose -->
    <UiModal :show="composeOpen" title="ارسال پیامک" @close="composeOpen = false">
        <form id="sms-form" class="space-y-4" @submit.prevent="send">
            <UiField label="بیمار" hint="یا شماره را مستقیم وارد کنید">
                <PatientPicker v-model="compose.patient_id" />
            </UiField>

            <UiField label="شماره موبایل" :error="compose.errors.mobile">
                <template #default="{ id, invalid }">
                    <UiInput :id="id" v-model="compose.mobile" :invalid="invalid" numeric placeholder="09xxxxxxxxx" dir="ltr" class="text-start nums-tabular" />
                </template>
            </UiField>

            <UiField label="متن پیام" :error="compose.errors.body" :hint="`${toPersianDigits(compose.body.length)} کاراکتر`" required>
                <template #default="{ id, invalid }">
                    <UiTextarea :id="id" v-model="compose.body" :invalid="invalid" :rows="4" />
                </template>
            </UiField>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="composeOpen = false">انصراف</UiButton>
            <UiButton type="submit" form="sms-form" :loading="compose.processing">ارسال</UiButton>
        </template>
    </UiModal>

    <!-- Template editor -->
    <UiModal :show="!!editingTemplate" title="ویرایش قالب پیامک" :subtitle="editingTemplate?.key" @close="editingTemplate = null">
        <form v-if="editingTemplate" id="template-form" class="space-y-4" @submit.prevent="saveTemplate">
            <UiField label="عنوان" :error="templateForm.errors.name" required>
                <template #default="{ id, invalid }">
                    <UiInput :id="id" v-model="templateForm.name" :invalid="invalid" />
                </template>
            </UiField>

            <UiField
                label="متن قالب"
                :error="templateForm.errors.body"
                hint="متغیرها: {{patient}} {{amount}} {{date}} {{time}} {{service}} {{balance}} {{clinic}} {{phone}}"
                required
            >
                <template #default="{ id, invalid }">
                    <UiTextarea :id="id" v-model="templateForm.body" :invalid="invalid" :rows="5" />
                </template>
            </UiField>

            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input v-model="templateForm.is_active" type="checkbox" class="size-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500/30">
                این قالب فعال باشد
            </label>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="editingTemplate = null">انصراف</UiButton>
            <UiButton type="submit" form="template-form" :loading="templateForm.processing">ذخیره</UiButton>
        </template>
    </UiModal>
</template>
