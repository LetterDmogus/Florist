<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardWelcome from '@/Components/DashboardWelcome.vue';

defineProps({
    chartData: {
        type: Object,
        required: true,
    }
});
</script>

<template>
    <AppLayout title="Dashboard Overview">
        <DashboardWelcome :chart-data="chartData" />
    </AppLayout>
</template>
