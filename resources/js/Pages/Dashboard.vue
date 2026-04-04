<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardWelcome from '@/Components/DashboardWelcome.vue';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    chartData: {
        type: Object,
        required: true,
    },
    recentOrders: {
        type: Array,
        required: true,
    }
});
</script>

<template>
    <AppLayout title="Dashboard Overview">
        <DashboardWelcome :stats="stats" :chart-data="chartData" :recent-orders="recentOrders" />
    </AppLayout>
</template>
