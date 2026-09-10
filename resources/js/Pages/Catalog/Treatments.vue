<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CatalogTable from '@/Components/CatalogTable.vue';
import CatalogTabs from '@/Components/CatalogTabs.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiField from '@/Components/UiField.vue';
import UiMoneyInput from '@/Components/UiMoneyInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import { formatMoney, toPersianDigits } from '@/Support/format';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
});

const activeCategory = ref(null);

const categoryOptions = computed(() =>
    props.categories.map((c) => ({ value: c.id, label: c.name })),
);

const visibleServices = computed(() =>
    activeCategory.value
        ? props.services.filter((s) => String(s.treatment_category_id) === String(activeCategory.value))
        : props.services,
);
</script>

<template>
    <Head title="خدمات و تعرفه" />
    <PageHeader title="اطلاعات پایه" subtitle="داده‌هایی که یک بار تنظیم می‌شوند و همه‌جای سامانه از آن‌ها استفاده می‌کند" />
    <CatalogTabs />

    <div class="grid gap-5 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <CatalogTable
                title="گروه‌های درمان"
                :rows="categories"
                store-route="catalog.categories.store"
                update-route="catalog.categories.update"
                count-key="services_count"
                count-label="خدمت"
            />
        </div>

        <div class="lg:col-span-3">
            <div class="mb-3">
                <UiSelect v-model="activeCategory" :options="categoryOptions" placeholder="همه گروه‌ها" />
            </div>

            <CatalogTable
                title="خدمات"
                subtitle="تعرفه‌ها به ریال هستند و در زمان ثبت درمان کپی می‌شوند"
                :rows="visibleServices"
                store-route="catalog.services.store"
                update-route="catalog.services.update"
                :defaults="{ treatment_category_id: null, price: 0 }"
            >
                <template #row="{ row }">
                    <span class="nums-tabular shrink-0 text-xs font-medium text-ink-500">
                        {{ formatMoney(row.price, { suffix: false }) }}
                    </span>
                </template>

                <template #before-name="{ form }">
                    <UiField label="گروه درمان" :error="form.errors.treatment_category_id" required>
                        <template #default="{ id, invalid }">
                            <UiSelect :id="id" v-model="form.treatment_category_id" :options="categoryOptions" :invalid="invalid" />
                        </template>
                    </UiField>
                </template>

                <template #fields="{ form }">
                    <UiField label="تعرفه" :error="form.errors.price" required>
                        <template #default="{ invalid }">
                            <UiMoneyInput v-model="form.price" :invalid="invalid" />
                        </template>
                    </UiField>
                </template>
            </CatalogTable>
        </div>
    </div>
</template>
