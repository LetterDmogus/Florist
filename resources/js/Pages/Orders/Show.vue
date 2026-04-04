<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    orderStatusLabels: {
        type: Object,
        default: () => ({}),
    },
});

const formatCurrency = (value) => {
    const amount = Number(value) || 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

const formatShippingDate = (value) => {
    if (!value) {
        return '-';
    }

    const raw = String(value);
    const parsed = new Date(raw.includes('T') ? raw : `${raw}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return raw.split('T')[0] ?? raw;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(parsed);
};

const formatShippingTime = (value) => {
    if (!value) {
        return '-';
    }

    const [hour = '00', minute = '00'] = String(value).split(':');

    return `${hour}:${minute}`;
};

const detailRows = computed(() => {
    return (props.order.order_details ?? []).map((detail) => ({
        ...detail,
        item_name: detail.item_type === 'bouquet'
            ? detail.bouquet_unit?.name
            : detail.inventory_item?.name,
    }));
});

const orderStatusLabel = computed(() => {
    return props.orderStatusLabels?.[props.order.order_status] ?? props.order.order_status;
});

const remainingPayment = computed(() => {
    const total = Number(props.order.total ?? 0);
    const downPayment = Number(props.order.down_payment ?? 0);

    if (props.order.payment_status === 'paid' && downPayment <= 0) {
        return 0;
    }

    return Math.max(0, total - downPayment);
});
</script>

<template>
    <AppLayout :title="`Order #${order.id}`">
        <Head :title="`Order #${order.id}`" />

        <div class="space-y-6">
            <section class="rounded-3xl border border-pink-200 bg-white p-5 shadow-sm">
                <h1 class="text-xl font-bold text-pink-950">Order #{{ order.id }}</h1>
                <p class="mt-1 text-sm text-pink-700">
                    {{ order.customer?.name }} • {{ formatShippingDate(order.shipping_date) }} {{ formatShippingTime(order.shipping_time) }} • {{ order.shipping_type }}
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-pink-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-pink-600">Total</p>
                        <p class="mt-1 text-lg font-bold text-pink-950">{{ formatCurrency(order.total) }}</p>
                    </div>
                    <div class="rounded-2xl bg-pink-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-pink-600">Payment</p>
                        <p class="mt-1 text-lg font-bold capitalize text-pink-950">{{ order.payment_status }}</p>
                    </div>
                    <div class="rounded-2xl bg-pink-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-pink-600">Order Status</p>
                        <p class="mt-1 text-lg font-bold text-pink-950">{{ orderStatusLabel }}</p>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-pink-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-pink-600">Ongkir</p>
                        <p class="mt-1 text-lg font-bold text-pink-950">{{ formatCurrency(order.shipping_fee ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl bg-pink-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-pink-600">Down Payment</p>
                        <p class="mt-1 text-lg font-bold text-pink-950">{{ formatCurrency(order.down_payment ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl bg-pink-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-pink-600">Sisa Bayar</p>
                        <p class="mt-1 text-lg font-bold text-pink-950">{{ formatCurrency(remainingPayment) }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-pink-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-pink-950">Order Items</h2>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-pink-100">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-pink-600">
                                <th class="px-3 py-2">Item</th>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Qty</th>
                                <th class="px-3 py-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-100 text-sm">
                            <tr v-for="detail in detailRows" :key="detail.id">
                                <td class="px-3 py-2 font-medium text-pink-950">{{ detail.item_name ?? '-' }}</td>
                                <td class="px-3 py-2 capitalize text-pink-800">{{ detail.item_type }}</td>
                                <td class="px-3 py-2 text-pink-800">{{ detail.quantity }}</td>
                                <td class="px-3 py-2 font-semibold text-pink-900">{{ formatCurrency(detail.subtotal) }}</td>
                            </tr>
                            <tr v-if="detailRows.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center text-sm text-pink-700">Belum ada item pada order ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
