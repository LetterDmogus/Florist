<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Truck } from 'lucide-vue-next';

defineProps({
    deliveries: Object,
});
</script>

<template>
    <AppLayout title="Deliveries">
        <template #header>
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                Deliveries
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white p-12 rounded-3xl border border-secondary shadow-sm text-center">
                    <div class="w-20 h-20 bg-primary/20 rounded-2xl flex items-center justify-center mx-auto mb-6 text-primary">
                        <Truck class="w-10 h-10" />
                    </div>
                    <h3 class="text-2xl font-bold text-foreground mb-2">Deliveries Page Coming Soon</h3>
                    <p class="text-muted-foreground">We are working on the delivery management system. Please check back later.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
