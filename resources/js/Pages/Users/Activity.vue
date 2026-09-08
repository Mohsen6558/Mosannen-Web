<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiCard from '@/Components/UiCard.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiPagination from '@/Components/UiPagination.vue';
import UiSelect from '@/Components/UiSelect.vue';
import { jalaliDateTime } from '@/Support/jalali';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
});

const user = ref(props.filters.user ?? null);
const event = ref(props.filters.event ?? null);

const EVENTS = {
    created: { label: 'ایجاد', tone: 'success' },
    updated: { label: 'ویرایش', tone: 'info' },
    deleted: { label: 'حذف', tone: 'danger' },
    login: { label: 'ورود', tone: 'neutral' },
    password_changed: { label: 'تغییر رمز', tone: 'warning' },
};

watch([user, event], () => {
    router.get(route('users.activity'), {
        user: user.value || undefined,
        event: event.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
});
</script>

<template>
    <Head title="گزارش فعالیت‌ها" />

    <PageHeader
        title="گزارش فعالیت‌ها"
        subtitle="ردیابی تغییرات روی پرونده‌ها و اطلاعات مالی"
        :breadcrumbs="[{ label: 'کاربران', href: route('users.index') }, { label: 'گزارش فعالیت‌ها' }]"
    />

    <div class="space-y-4">
        <UiCard>
            <div class="flex flex-wrap gap-3">
                <div class="w-52">
                    <UiSelect v-model="user" :options="users.map(u => ({ value: u.id, label: u.full_name || u.username }))" placeholder="همه کاربران" />
                </div>
                <div class="w-44">
                    <UiSelect v-model="event" :options="Object.entries(EVENTS).map(([v, e]) => ({ value: v, label: e.label }))" placeholder="همه رویدادها" />
                </div>
            </div>
        </UiCard>

        <UiCard :padded="false">
            <ul v-if="logs.data.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                <li v-for="log in logs.data" :key="log.id" class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                    <UiBadge :tone="EVENTS[log.event]?.tone ?? 'neutral'" class="shrink-0">
                        {{ EVENTS[log.event]?.label ?? log.event }}
                    </UiBadge>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ log.description || '—' }}</p>
                        <p class="nums-tabular truncate text-[11px] text-ink-500">
                            <span v-if="log.subject_type">{{ log.subject_type }}#{{ log.subject_id }}</span>
                            <span v-if="log.ip_address"> · {{ log.ip_address }}</span>
                        </p>
                    </div>

                    <span class="shrink-0 text-xs text-ink-500">{{ log.user || 'سیستم' }}</span>
                    <span class="nums-tabular shrink-0 text-[11px] text-ink-300">{{ jalaliDateTime(log.created_at) }}</span>
                </li>
            </ul>

            <UiEmpty v-else title="فعالیتی ثبت نشده" />

            <template #footer>
                <UiPagination :meta="logs" />
            </template>
        </UiCard>
    </div>
</template>
