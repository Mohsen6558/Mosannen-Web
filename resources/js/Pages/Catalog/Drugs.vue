<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CatalogTable from '@/Components/CatalogTable.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';

const props = defineProps({
    drugs: { type: Array, default: () => [] },
    variants: { type: Array, default: () => [] },
});

const activeDrug = ref(null);

const drugOptions = computed(() => props.drugs.map((d) => ({ value: d.id, label: d.name })));

const visibleVariants = computed(() =>
    activeDrug.value
        ? props.variants.filter((v) => String(v.drug_id) === String(activeDrug.value))
        : props.variants,
);
</script>

<template>
    <Head title="داروها" />
    <PageHeader title="داروها" subtitle="داروها و اشکال دارویی قابل تجویز" />

    <div class="grid gap-5 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <CatalogTable
                title="داروها"
                :rows="drugs"
                store-route="catalog.drugs.store"
                update-route="catalog.drugs.update"
                count-key="variants_count"
                count-label="شکل"
            />
        </div>

        <div class="lg:col-span-3">
            <div class="mb-3">
                <UiSelect v-model="activeDrug" :options="drugOptions" placeholder="همه داروها" />
            </div>

            <CatalogTable
                title="اشکال دارویی"
                subtitle="دوز و دستور پیش‌فرض، هنگام تجویز به‌صورت خودکار پر می‌شود"
                :rows="visibleVariants"
                store-route="catalog.variants.store"
                update-route="catalog.variants.update"
                :defaults="{ drug_id: null, default_dosage: '', default_instructions: '' }"
            >
                <template #before-name="{ form }">
                    <UiField label="دارو" :error="form.errors.drug_id" required>
                        <template #default="{ id, invalid }">
                            <UiSelect :id="id" v-model="form.drug_id" :options="drugOptions" :invalid="invalid" />
                        </template>
                    </UiField>
                </template>

                <template #fields="{ form }">
                    <UiField label="دوز پیش‌فرض" :error="form.errors.default_dosage">
                        <template #default="{ id }">
                            <UiInput :id="id" v-model="form.default_dosage" placeholder="هر ۸ ساعت یک عدد" />
                        </template>
                    </UiField>

                    <UiField label="دستور پیش‌فرض" :error="form.errors.default_instructions">
                        <template #default="{ id }">
                            <UiInput :id="id" v-model="form.default_instructions" placeholder="بعد از غذا" />
                        </template>
                    </UiField>
                </template>
            </CatalogTable>
        </div>
    </div>
</template>
