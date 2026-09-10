<script setup>
import { Head } from '@inertiajs/vue3';
import CatalogTable from '@/Components/CatalogTable.vue';
import CatalogTabs from '@/Components/CatalogTabs.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({ insurances: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="بیمه‌ها" />
    <PageHeader title="اطلاعات پایه" subtitle="داده‌هایی که یک بار تنظیم می‌شوند و همه‌جای سامانه از آن‌ها استفاده می‌کند" />
    <CatalogTabs />

    <div class="mx-auto max-w-2xl">
        <CatalogTable
            title="بیمه‌ها"
            :rows="insurances"
            store-route="catalog.insurances.store"
            update-route="catalog.insurances.update"
            count-key="patients_count"
            count-label="بیمار"
        />
    </div>
</template>
