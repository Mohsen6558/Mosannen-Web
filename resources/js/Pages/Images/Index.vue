<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { refDebounced } from '@vueuse/core';
import DateRangeFilter from '@/Components/DateRangeFilter.vue';
import Odontogram from '@/Components/Odontogram.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PatientPicker from '@/Components/PatientPicker.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiDatePicker from '@/Components/UiDatePicker.vue';
import UiEmpty from '@/Components/UiEmpty.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiModal from '@/Components/UiModal.vue';
import UiPagination from '@/Components/UiPagination.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { toPersianDigits } from '@/Support/format';
import { jalali, todayIso } from '@/Support/jalali';

const props = defineProps({
    radiographs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const q = ref(props.filters.q ?? '');
const debounced = refDebounced(q, 300);
const from = ref(props.filters.from ?? null);
const to = ref(props.filters.to ?? null);
const uploadOpen = ref(false);
const lightbox = ref(null);

watch([debounced, from, to], () => {
    router.get(route('images.index'), {
        q: q.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
});

const form = useForm({
    patient_id: null,
    taken_on: todayIso(),
    subject: '',
    teeth: [],
    files: [],
});

function onFiles(event) {
    form.files = Array.from(event.target.files ?? []);
}

function submit() {
    form.post(route('images.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('subject', 'teeth', 'files');
            uploadOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="عکس‌برداری" />

    <PageHeader title="عکس‌برداری" :subtitle="`${toPersianDigits(radiographs.total)} تصویر`">
        <template #actions>
            <UiButton v-if="can('images.upload')" @click="uploadOpen = true">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4" /></svg>
                </template>
                بارگذاری تصویر
            </UiButton>
        </template>
    </PageHeader>

    <div class="space-y-4">
        <UiCard>
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <UiInput v-model="q" placeholder="جستجوی بیمار…" />
                </div>
                <DateRangeFilter v-model:from="from" v-model:to="to" />
            </div>
        </UiCard>

        <UiCard v-if="radiographs.data.length">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <button
                    v-for="r in radiographs.data"
                    :key="r.id"
                    type="button"
                    class="group overflow-hidden rounded-lg border border-surface-200 text-start transition-shadow hover:shadow-[var(--shadow-raised)] dark:border-surface-800"
                    @click="lightbox = r"
                >
                    <div class="aspect-4/3 bg-surface-900">
                        <img v-if="r.thumb" :src="r.thumb" :alt="r.subject || 'رادیوگرافی'" class="size-full object-cover transition-transform group-hover:scale-[1.03]" loading="lazy">
                        <div v-else class="flex size-full items-center justify-center text-xs text-ink-500">بدون فایل</div>
                    </div>
                    <div class="p-2.5">
                        <p class="truncate text-xs font-medium">{{ r.patient.name }}</p>
                        <p class="nums-tabular truncate text-[11px] text-ink-500">{{ jalali(r.taken_on) }}</p>
                        <p v-if="r.subject" class="truncate text-[11px] text-ink-300">{{ r.subject }}</p>
                    </div>
                </button>
            </div>

            <template #footer>
                <UiPagination :meta="radiographs" />
            </template>
        </UiCard>

        <UiCard v-else>
            <UiEmpty title="تصویری یافت نشد" message="اولین تصویر رادیوگرافی را بارگذاری کنید." />
        </UiCard>
    </div>

    <!-- Upload -->
    <UiModal :show="uploadOpen" size="lg" title="بارگذاری تصویر رادیوگرافی" @close="uploadOpen = false">
        <form id="upload-form" class="space-y-4" @submit.prevent="submit">
            <UiField label="بیمار" :error="form.errors.patient_id" required>
                <PatientPicker v-model="form.patient_id" :invalid="!!form.errors.patient_id" />
            </UiField>

            <div class="grid gap-4 sm:grid-cols-2">
                <UiField label="تاریخ عکس‌برداری" :error="form.errors.taken_on" required>
                    <template #default="{ invalid }">
                        <UiDatePicker v-model="form.taken_on" :invalid="invalid" :max="todayIso()" />
                    </template>
                </UiField>

                <UiField label="موضوع" :error="form.errors.subject">
                    <template #default="{ id }">
                        <UiInput :id="id" v-model="form.subject" placeholder="پری‌اپیکال، پانورامیک، بایت‌وینگ…" />
                    </template>
                </UiField>
            </div>

            <UiField label="دندان‌های مرتبط" :error="form.errors.teeth">
                <Odontogram v-model="form.teeth" compact />
            </UiField>

            <UiField label="فایل‌ها" :error="form.errors.files" hint="حداکثر ۲۰ فایل، هرکدام تا ۲۵ مگابایت — JPG، PNG، WebP، TIFF" required>
                <template #default="{ id }">
                    <input
                        :id="id"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp,image/bmp,image/tiff"
                        class="input-base file:me-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-brand-700 dark:file:bg-brand-950 dark:file:text-brand-200"
                        @change="onFiles"
                    >
                </template>
            </UiField>

            <p v-if="form.files.length" class="nums-tabular text-xs text-ink-500">
                {{ toPersianDigits(form.files.length) }} فایل انتخاب شد
            </p>

            <div v-if="form.progress" class="h-1.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                <div class="h-full bg-brand-500 transition-[width]" :style="{ width: `${form.progress.percentage}%` }" />
            </div>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="uploadOpen = false">انصراف</UiButton>
            <UiButton type="submit" form="upload-form" :loading="form.processing">بارگذاری</UiButton>
        </template>
    </UiModal>

    <!-- Lightbox -->
    <Teleport to="body">
        <div v-if="lightbox" class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-surface-950/90 p-6" @click.self="lightbox = null">
            <img :src="lightbox.url" :alt="lightbox.subject || 'رادیوگرافی'" class="max-h-[80vh] max-w-full rounded-lg object-contain">
            <div class="text-center text-sm text-white/80">
                <Link :href="route('patients.show', lightbox.patient.id)" class="font-medium hover:underline">{{ lightbox.patient.name }}</Link>
                <span class="nums-tabular mx-2 text-white/50">{{ jalali(lightbox.taken_on) }}</span>
                <span v-if="lightbox.subject">{{ lightbox.subject }}</span>
            </div>
            <button type="button" class="absolute end-5 top-5 rounded-lg bg-white/10 p-2 text-white hover:bg-white/20" aria-label="بستن" @click="lightbox = null">
                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
            </button>
        </div>
    </Teleport>
</template>
