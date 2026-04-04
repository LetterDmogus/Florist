<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { Printer, ArrowLeft } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    order: Object,
    settings: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

const itemSubtotal = () => {
    const details = Array.isArray(props.order?.order_details) ? props.order.order_details : [];

    return details.reduce((sum, item) => sum + (Number(item?.subtotal) || 0), 0);
};

const printReceipt = () => {
    window.print();
};

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 p-4 md:p-8 flex flex-col items-center">
        <Head title="Struk Pesanan" />

        <!-- UI Controls (Hidden on Print) -->
        <div class="w-full max-w-[80mm] mb-6 flex justify-between print:hidden">
            <BaseButton variant="secondary" size="sm" @click="goBack">
                <ArrowLeft class="w-4 h-4" />
                Kembali
            </BaseButton>
            <BaseButton variant="primary" size="sm" @click="printReceipt">
                <Printer class="w-4 h-4" />
                Cetak Struk
            </BaseButton>
        </div>

        <!-- Receipt Paper -->
        <div class="bg-white shadow-lg p-6 w-full max-w-[80mm] font-mono text-[12px] text-black receipt-content">
            <!-- Header -->
            <div class="text-center space-y-1 mb-6">
                <h1 class="text-lg font-bold uppercase">{{ settings.store_name }}</h1>
                <p class="whitespace-pre-line">{{ settings.address }}</p>
                <p>Telp: {{ settings.phone }}</p>
            </div>

            <div class="border-t border-dashed border-gray-300 my-4"></div>

            <!-- Order Info -->
            <div class="space-y-1 mb-4">
                <div class="flex justify-between">
                    <span>No. Order:</span>
                    <span class="font-bold">#{{ order.id }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Tanggal:</span>
                    <span>{{ new Date(order.created_at).toLocaleDateString('id-ID') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Kasir:</span>
                    <span>{{ order.user?.name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Pelanggan:</span>
                    <span>{{ order.customer?.name }}</span>
                </div>
            </div>

            <div class="border-t border-dashed border-gray-300 my-4"></div>

            <!-- Items -->
            <table class="w-full mb-4">
                <thead>
                    <tr class="text-left">
                        <th class="py-1">Item</th>
                        <th class="py-1 text-right">Qty</th>
                        <th class="py-1 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in order.order_details" :key="item.id">
                        <td class="py-1">
                            {{ item.item_type === 'bouquet' ? (item.bouquet_unit?.name || 'Custom Bouquet') : (item.inventory_item?.name || 'Item') }}
                        </td>
                        <td class="py-1 text-right">{{ item.quantity }}</td>
                        <td class="py-1 text-right">{{ formatCurrency(item.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="border-t border-dashed border-gray-300 my-4"></div>

            <!-- Totals -->
            <div class="space-y-1">
                <div class="flex justify-between">
                    <span>Subtotal Item:</span>
                    <span>{{ formatCurrency(itemSubtotal()) }}</span>
                </div>
                <div v-if="Number(order.shipping_fee || 0) > 0" class="flex justify-between">
                    <span>Ongkir:</span>
                    <span>{{ formatCurrency(order.shipping_fee) }}</span>
                </div>
                <div class="flex justify-between text-base font-bold">
                    <span>TOTAL</span>
                    <span>{{ formatCurrency(order.total) }}</span>
                </div>
                <div v-if="order.down_payment > 0" class="flex justify-between">
                    <span>DP / Bayar:</span>
                    <span>{{ formatCurrency(order.down_payment) }}</span>
                </div>
                <div v-if="order.down_payment > 0" class="flex justify-between font-bold">
                    <span>SISA:</span>
                    <span>{{ formatCurrency(order.total - order.down_payment) }}</span>
                </div>
            </div>

            <div class="border-t border-dashed border-gray-300 my-6"></div>

            <!-- Footer -->
            <div class="text-center space-y-2">
                <p class="italic text-[10px]">{{ settings.receipt_note }}</p>
                <p class="text-[10px] font-bold">--- Terimakasih ---</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    body {
        background: white !important;
        margin: 0;
        padding: 0;
    }
    .min-h-screen {
        min-height: auto !important;
        background: transparent !important;
        padding: 0 !important;
    }
    .shadow-lg {
        shadow: none !important;
    }
    .receipt-content {
        max-width: 100% !important;
        padding: 0 !important;
    }
}

/* Base styles for receipt-like font */
.receipt-content {
    line-height: 1.4;
}
</style>
