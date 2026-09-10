<script setup>
import { Head } from '@inertiajs/vue3';
import CatalogTable from '@/Components/CatalogTable.vue';
import CatalogTabs from '@/Components/CatalogTabs.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({ paymentTypes: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="نحوه پرداخت" />
    <PageHeader title="اطلاعات پایه" subtitle="داده‌هایی که یک بار تنظیم می‌شوند و همه‌جای سامانه از آن‌ها استفاده می‌کند" />
    <CatalogTabs />

    <div class="mx-auto max-w-2xl">
        <CatalogTable
            title="نحوه پرداخت"
            :rows="paymentTypes"
            store-route="catalog.payment-types.store"
            update-route="catalog.payment-types.update"
            count-key="payments_count"
            count-label="تراکنش"
        />
    </div>
</template>
