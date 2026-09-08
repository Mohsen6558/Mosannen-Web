<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { refDebounced } from '@vueuse/core';
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
import UiPagination from '@/Components/UiPagination.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { toPersianDigits } from '@/Support/format';
import { jalali, todayIso } from '@/Support/jalali';

const props = defineProps({
    prescriptions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    drugs: { type: Array, default: () => [] },
    variants: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const q = ref(props.filters.q ?? '');
const debounced = refDebounced(q, 300);
const modalOpen = ref(false);

watch(debounced, () => {
    router.get(route('prescriptions.index'), { q: q.value || undefined }, {
        preserveState: true, replace: true, preserveScroll: true,
    });
});

const variantOptions = computed(() =>
    props.variants.map((v) => ({
        value: v.id,
        label: `${props.drugs.find((d) => d.id === v.drug_id)?.name ?? ''} — ${v.name}`,
    })),
);

const form = useForm({
    patient_id: null,
    prescribed_on: todayIso(),
    notes: '',
    items: [{ drug_variant_id: null, dosage: '', instructions: '', quantity: 1 }],
});

function addItem() {
    form.items.push({ drug_variant_id: null, dosage: '', instructions: '', quantity: 1 });
}

function removeItem(index) {
    if (form.items.length > 1) form.items.splice(index, 1);
}

// Fill dosage and instructions from the drug's defaults when one is picked.
function onVariantChange(item) {
    const variant = props.variants.find((v) => String(v.id) === String(item.drug_variant_id));
    if (!variant) return;
    if (!item.dosage) item.dosage = variant.default_dosage ?? '';
    if (!item.instructions) item.instructions = variant.default_instructions ?? '';
}

function submit() {
    form.post(route('prescriptions.store'), { onSuccess: () => (modalOpen.value = false) });
}
</script>

<template>
    <Head title="نسخه‌ها" />

    <PageHeader title="نسخه‌ها" :subtitle="`${toPersianDigits(prescriptions.total)} نسخه`">
        <template #actions>
            <UiButton v-if="can('prescriptions.manage')" @click="modalOpen = true">
                <template #icon>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                </template>
                نسخه جدید
            </UiButton>
        </template>
    </PageHeader>

    <div class="space-y-4">
        <UiCard>
            <UiInput v-model="q" placeholder="جستجوی بیمار…" />
        </UiCard>

        <UiCard :padded="false">
            <ul v-if="prescriptions.data.length" class="divide-y divide-surface-100 dark:divide-surface-800">
                <li v-for="p in prescriptions.data" :key="p.id">
                    <Link :href="route('prescriptions.show', p.id)" class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-surface-50 dark:hover:bg-surface-800/40">
                        <span class="nums-tabular w-28 shrink-0 text-xs text-ink-500">{{ jalali(p.prescribed_on) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ p.patient.name }}</p>
                            <p v-if="p.notes" class="truncate text-[11px] text-ink-500">{{ p.notes }}</p>
                        </div>
                        <span class="text-xs text-ink-500">{{ p.user || '—' }}</span>
                        <UiBadge class="nums-tabular">{{ toPersianDigits(p.items_count) }} قلم</UiBadge>
                    </Link>
                </li>
            </ul>

            <UiEmpty v-else title="نسخه‌ای ثبت نشده" />

            <template #footer>
                <UiPagination :meta="prescriptions" />
            </template>
        </UiCard>
    </div>

    <UiModal :show="modalOpen" size="lg" title="نسخه جدید" @close="modalOpen = false">
        <form id="prescription-form" class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <UiField label="بیمار" :error="form.errors.patient_id" required>
                    <PatientPicker v-model="form.patient_id" :invalid="!!form.errors.patient_id" />
                </UiField>

                <UiField label="تاریخ نسخه" :error="form.errors.prescribed_on" required>
                    <template #default="{ invalid }">
                        <UiDatePicker v-model="form.prescribed_on" :invalid="invalid" :clearable="false" />
                    </template>
                </UiField>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <p class="label-base mb-0">اقلام نسخه</p>
                    <button type="button" class="text-xs font-medium text-brand-600 hover:underline" @click="addItem">+ افزودن قلم</button>
                </div>

                <p v-if="form.errors.items" class="mb-2 text-xs text-danger-500">{{ form.errors.items }}</p>

                <div class="space-y-3">
                    <div
                        v-for="(item, i) in form.items"
                        :key="i"
                        class="rounded-lg border border-surface-200 p-3 dark:border-surface-800"
                    >
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span class="nums-tabular text-xs font-medium text-ink-500">قلم {{ toPersianDigits(i + 1) }}</span>
                            <button
                                v-if="form.items.length > 1"
                                type="button"
                                class="text-xs text-danger-500 hover:underline"
                                @click="removeItem(i)"
                            >
                                حذف
                            </button>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-4">
                            <div class="sm:col-span-2">
                                <UiSelect
                                    v-model="item.drug_variant_id"
                                    :options="variantOptions"
                                    placeholder="— انتخاب دارو —"
                                    :invalid="!!form.errors[`items.${i}.drug_variant_id`]"
                                    @update:model-value="onVariantChange(item)"
                                />
                            </div>
                            <UiInput v-model="item.dosage" placeholder="دوز مصرف" />
                            <div class="flex gap-2">
                                <UiInput v-model="item.instructions" placeholder="دستور" />
                                <UiInput v-model.number="item.quantity" type="number" min="1" class="w-20 text-center nums-tabular" dir="ltr" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <UiField label="توضیحات" :error="form.errors.notes">
                <template #default="{ id }">
                    <UiTextarea :id="id" v-model="form.notes" :rows="2" />
                </template>
            </UiField>
        </form>

        <template #footer>
            <UiButton variant="secondary" @click="modalOpen = false">انصراف</UiButton>
            <UiButton type="submit" form="prescription-form" :loading="form.processing">ثبت نسخه</UiButton>
        </template>
    </UiModal>
</template>
