<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { Eye, X } from 'lucide-vue-next';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    monthOptions: {
        type: Array,
        default: () => [],
    },
    yearOptions: {
        type: Array,
        default: () => [],
    },
    salesSummary: {
        type: Object,
        required: true,
    },
    salesRows: {
        type: Array,
        default: () => [],
    },
    profitSummary: {
        type: Object,
        required: true,
    },
    activeTab: {
        type: String,
        default: 'sales',
    },
});

const selectedMonth = ref(props.filters.month);
const selectedYear = ref(props.filters.year);
const selectedType = ref(props.filters.type || 'all');

const selectedOrder = ref(null);
const showDetailModal = ref(false);

const openOrderDetail = (row) => {
    selectedOrder.value = row;
    showDetailModal.value = true;
};

const closeOrderDetail = () => {
    showDetailModal.value = false;
    selectedOrder.value = null;
};

const exportUrl = computed(() => route('reports.sales.export', {
    month: selectedMonth.value,
    year: selectedYear.value,
    type: selectedType.value,
}));

const monthLabel = computed(() => {
    const found = props.monthOptions.find((item) => Number(item.value) === Number(selectedMonth.value));
    return found?.label || selectedMonth.value;
});

watch([selectedMonth, selectedYear, selectedType], () => {
    router.get(route('reports.sales.index'), {
        month: selectedMonth.value,
        year: selectedYear.value,
        type: selectedType.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
});

const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
}).format(Number(value || 0));
</script>

<template>
    <AppLayout title="Laporan Penjualan">
        <Head title="Laporan Penjualan" />

        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Laporan Keuangan
                </h2>
                <div class="flex items-center gap-2">
                    <select
                        v-model="selectedType"
                        class="rounded-xl border-secondary focus:border-primary focus:ring-primary/40 text-sm font-medium"
                    >
                        <option value="all">Semua Kategori</option>
                        <option value="bouquet">Bouquet Only</option>
                        <option value="supply">Supply Only</option>
                    </select>
                    <select
                        v-model="selectedMonth"
                        class="rounded-xl border-secondary focus:border-primary focus:ring-primary/40 text-sm"
                    >
                        <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                            {{ month.label }}
                        </option>
                    </select>
                    <select
                        v-model="selectedYear"
                        class="rounded-xl border-secondary focus:border-primary focus:ring-primary/40 text-sm"
                    >
                        <option v-for="year in yearOptions" :key="year" :value="year">
                            {{ year }}
                        </option>
                    </select>
                    <a
                        :href="exportUrl"
                        class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 transition shadow-xs"
                    >
                        Export Excel
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- Tab Navigation -->
                <div class="flex items-center gap-2 mb-6 bg-secondary/30 p-1 rounded-2xl w-fit">
                    <Link 
                        :href="route('reports.sales.index')"
                        :class="[
                            'px-6 py-2.5 rounded-xl text-sm font-medium transition-all',
                            activeTab === 'sales' ? 'bg-white shadow-sm text-pink-600' : 'text-muted-foreground hover:text-foreground'
                        ]"
                    >
                        Laporan Penjualan
                    </Link>
                    <Link 
                        :href="route('reports.purchases.index')"
                        :class="[
                            'px-6 py-2.5 rounded-xl text-sm font-medium transition-all',
                            activeTab === 'purchases' ? 'bg-white shadow-sm text-pink-600' : 'text-muted-foreground hover:text-foreground'
                        ]"
                    >
                        Laporan Pembelian
                    </Link>
                </div>

                <section class="rounded-2xl border border-pink-100 bg-white p-5">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h3 class="font-semibold text-pink-900">Pencatatan Per Order - {{ monthLabel }} {{ selectedYear }}</h3>
                        <div class="flex items-center gap-2 text-xs text-rose-700 font-medium">
                            <span class="inline-block w-3 h-3 rounded bg-rose-100 border border-rose-300"></span>
                            <span>Highlight Merah: Masih DP / Belum Lunas</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3 mb-4">
                        <div class="rounded-xl bg-pink-50 p-3">
                            <p class="text-xs text-muted-foreground">Money</p>
                            <p class="font-semibold text-pink-900">{{ formatCurrency(salesSummary.money) }}</p>
                        </div>
                        <div class="rounded-xl bg-pink-50 p-3">
                            <p class="text-xs text-muted-foreground">Fee</p>
                            <p class="font-semibold text-pink-900">{{ formatCurrency(salesSummary.fee) }}</p>
                        </div>
                        <div class="rounded-xl bg-pink-50 p-3">
                            <p class="text-xs text-muted-foreground">Diskon</p>
                            <p class="font-semibold text-rose-700">{{ formatCurrency(salesSummary.discount || 0) }}</p>
                        </div>
                        <div class="rounded-xl bg-pink-50 p-3">
                            <p class="text-xs text-muted-foreground">Gosend</p>
                            <p class="font-semibold text-pink-900">{{ formatCurrency(salesSummary.gosend) }}</p>
                        </div>
                        <div class="rounded-xl bg-pink-50 p-3">
                            <p class="text-xs text-muted-foreground">Total Penjualan</p>
                            <p class="font-semibold text-pink-900">{{ formatCurrency(salesSummary.total) }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3">
                            <p class="text-xs font-semibold text-amber-700">Total DP Diterima</p>
                            <p class="font-bold text-amber-900">{{ formatCurrency(salesSummary.dp) }}</p>
                        </div>
                        <div class="rounded-xl bg-rose-50 border border-rose-200 p-3">
                            <p class="text-xs font-semibold text-rose-700">Total Piutang (Sisa)</p>
                            <p class="font-bold text-rose-900">{{ formatCurrency(salesSummary.unpaid_total) }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-pink-100">
                        <table class="min-w-full text-sm">
                            <thead class="bg-pink-50 text-pink-900">
                                <tr>
                                    <th class="px-3 py-2 text-left">No</th>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Customer / Model Item</th>
                                    <th class="px-3 py-2 text-center">Status</th>
                                    <th class="px-3 py-2 text-right">DP Diterima</th>
                                    <th class="px-3 py-2 text-right">Belum Dibayar</th>
                                    <th class="px-3 py-2 text-right">Money</th>
                                    <th class="px-3 py-2 text-right">Fee</th>
                                    <th class="px-3 py-2 text-right">Diskon</th>
                                    <th class="px-3 py-2 text-right">Gosend</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-3 py-2 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in salesRows"
                                    :key="`sales-${row.order_id || row.no}`"
                                    class="border-t transition-colors cursor-pointer"
                                    :class="row.is_dp
                                        ? 'bg-rose-100/80 hover:bg-rose-200/80 border-rose-300 text-rose-950 font-medium'
                                        : (row.is_unpaid
                                            ? 'bg-rose-50/90 hover:bg-rose-100/70 border-rose-200 text-rose-950 font-medium'
                                            : 'hover:bg-pink-50/40 border-pink-100 text-foreground')"
                                    @click="openOrderDetail(row)"
                                >
                                    <td class="px-3 py-2 font-mono text-xs">{{ row.no }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div>{{ row.date || '-' }}</div>
                                        <div v-if="row.time" class="text-xs text-muted-foreground">{{ row.time }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1.5 mb-0.5">
                                            <span class="font-semibold text-pink-950">{{ row.customer_name }}</span>
                                            <span
                                                v-if="row.type_label"
                                                class="inline-block px-1.5 py-0.2 rounded text-[10px] font-medium"
                                                :class="row.type_label === 'Supply' 
                                                    ? 'bg-amber-100 text-amber-800 border border-amber-200' 
                                                    : (row.type_label.includes('&') 
                                                        ? 'bg-purple-100 text-purple-800 border border-purple-200' 
                                                        : 'bg-pink-100 text-pink-700 border border-pink-200')"
                                            >
                                                {{ row.type_label }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-muted-foreground line-clamp-1">
                                            <span v-if="row.item_codes && row.item_codes !== '-'" class="font-mono text-pink-700 font-medium mr-1">[{{ row.item_codes }}]</span>
                                            <span>{{ row.model }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span
                                            v-if="row.payment_status === 'dp'"
                                            class="inline-block px-2.5 py-0.5 rounded text-[11px] font-bold bg-rose-200 text-rose-900 border border-rose-300"
                                        >
                                            DP
                                        </span>
                                        <span
                                            v-else-if="row.payment_status === 'unpaid'"
                                            class="inline-block px-2.5 py-0.5 rounded text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
                                        >
                                            Belum Lunas
                                        </span>
                                        <span
                                            v-else
                                            class="inline-block px-2.5 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"
                                        >
                                            Lunas
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <span v-if="row.dp > 0" class="font-bold text-amber-700">
                                            {{ formatCurrency(row.dp) }}
                                        </span>
                                        <span v-else class="text-muted-foreground text-xs">
                                            -
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <span v-if="row.unpaid_amount > 0" class="font-bold text-rose-700">
                                            {{ formatCurrency(row.unpaid_amount) }}
                                        </span>
                                        <span v-else class="text-muted-foreground text-xs">
                                            -
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.money) }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.fee) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <span v-if="row.discount > 0" class="text-rose-700 font-semibold">
                                            -{{ formatCurrency(row.discount) }}
                                        </span>
                                        <span v-else class="text-muted-foreground text-xs">-</span>
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.gosend) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(row.total) }}</td>
                                    <td class="px-3 py-2 text-center" @click.stop>
                                        <button
                                            type="button"
                                            @click="openOrderDetail(row)"
                                            class="inline-flex items-center justify-center p-1.5 rounded-lg bg-pink-100 text-pink-700 hover:bg-pink-200 hover:text-pink-900 transition"
                                            title="Lihat Detail Order"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="salesRows.length === 0">
                                    <td colspan="12" class="px-3 py-5 text-center text-muted-foreground">Belum ada data penjualan di periode ini.</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="salesRows.length > 0" class="border-t-2 border-pink-200 bg-pink-50/60 font-bold text-pink-950">
                                <tr>
                                    <td colspan="4" class="px-3 py-2.5 text-right uppercase text-xs tracking-wider">
                                        Total:
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-extrabold text-amber-800 bg-amber-100/50">
                                        {{ formatCurrency(salesSummary.dp) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-extrabold text-rose-700 bg-rose-100/50">
                                        {{ formatCurrency(salesSummary.unpaid_total) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right">{{ formatCurrency(salesSummary.money) }}</td>
                                    <td class="px-3 py-2.5 text-right">{{ formatCurrency(salesSummary.fee) }}</td>
                                    <td class="px-3 py-2.5 text-right text-rose-700">{{ formatCurrency(salesSummary.discount || 0) }}</td>
                                    <td class="px-3 py-2.5 text-right">{{ formatCurrency(salesSummary.gosend) }}</td>
                                    <td class="px-3 py-2.5 text-right font-black">{{ formatCurrency(salesSummary.total) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section class="rounded-2xl border border-pink-100 bg-white p-5">
                    <h3 class="font-semibold text-pink-900 mb-3">Ringkasan Laba</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-xl border border-pink-100 p-4 space-y-2">
                            <div class="flex justify-between text-sm"><span>Pendapatan Florist</span><span>{{ formatCurrency(profitSummary.florist_income) }}</span></div>
                            <div class="flex justify-between text-sm"><span>Pendapatan Supply</span><span>{{ formatCurrency(profitSummary.supply_income) }}</span></div>
                            <div class="flex justify-between text-sm"><span>Pembelian</span><span>-{{ formatCurrency(profitSummary.purchase_total) }}</span></div>
                            <div class="flex justify-between text-sm"><span>Biaya Toko</span><span>-{{ formatCurrency(profitSummary.store_expense_total) }}</span></div>
                            <div class="flex justify-between text-sm"><span>Biaya Bahan Baku</span><span>-{{ formatCurrency(profitSummary.raw_material_expense_total) }}</span></div>
                            <div class="flex justify-between text-sm font-semibold border-t border-pink-100 pt-2">
                                <span>Laba Kotor</span>
                                <span>{{ formatCurrency(profitSummary.gross_profit) }}</span>
                            </div>
                        </div>
                        <div class="rounded-xl border border-pink-100 p-4 space-y-2">
                            <div class="flex justify-between text-sm"><span>Total Biaya Gosend/Ongkir</span><span>{{ formatCurrency(profitSummary.shipping_total) }}</span></div>
                            <div class="flex justify-between text-sm"><span>Total Penyesuaian</span><span>{{ formatCurrency(profitSummary.adjustment_total) }}</span></div>
                            <div class="flex justify-between text-sm text-rose-700 font-medium"><span>Sisa Piutang Penjualan (DP / Belum Lunas)</span><span class="font-bold">{{ formatCurrency(salesSummary.unpaid_total) }}</span></div>
                            <div class="flex justify-between text-sm font-semibold border-t border-pink-100 pt-2">
                                <span>Laba Bersih</span>
                                <span>{{ formatCurrency(profitSummary.net_profit) }}</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Detail Order Modal -->
        <Modal :show="showDetailModal" max-width="2xl" @close="closeOrderDetail">
            <div v-if="selectedOrder" class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-pink-100">
                    <div>
                        <h3 class="text-lg font-bold text-pink-950 flex items-center gap-2">
                            <span>Order #{{ selectedOrder.order_id }}</span>
                            <span
                                v-if="selectedOrder.payment_status === 'dp'"
                                class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-200 text-rose-900 border border-rose-300"
                            >
                                Status: DP
                            </span>
                            <span
                                v-else-if="selectedOrder.payment_status === 'unpaid'"
                                class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200"
                            >
                                Belum Lunas
                            </span>
                            <span
                                v-else
                                class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200"
                            >
                                Lunas
                            </span>
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Pengiriman: {{ selectedOrder.date || '-' }} {{ selectedOrder.time || '' }} ({{ selectedOrder.shipping_type || 'pickup' }})
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeOrderDetail"
                        class="text-gray-400 hover:text-gray-600 transition p-1 rounded-lg hover:bg-gray-100"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Info Customer & Pengiriman -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 py-4 text-xs border-b border-pink-100">
                    <div class="space-y-1">
                        <p class="font-semibold text-pink-900">Pemesan (Customer):</p>
                        <p class="text-gray-700 font-medium">{{ selectedOrder.customer_name }}</p>
                        <p class="text-muted-foreground">{{ selectedOrder.customer_phone }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="font-semibold text-pink-900">Penerima & Alamat:</p>
                        <p class="text-gray-700 font-medium">{{ selectedOrder.delivery_recipient || selectedOrder.customer_name }}</p>
                        <p class="text-muted-foreground">{{ selectedOrder.delivery_phone || selectedOrder.customer_phone }}</p>
                        <p v-if="selectedOrder.delivery_address" class="text-gray-600 italic mt-1">{{ selectedOrder.delivery_address }}</p>
                    </div>
                </div>

                <!-- Items Breakdown -->
                <div class="py-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-pink-900 mb-2">Daftar Item / Buket</h4>
                    <div class="overflow-x-auto rounded-xl border border-pink-100">
                        <table class="min-w-full text-xs divide-y divide-pink-100">
                            <thead class="bg-pink-50/70 text-pink-900">
                                <tr>
                                    <th class="px-3 py-2 text-left">Kode</th>
                                    <th class="px-3 py-2 text-left">Item / Keterangan</th>
                                    <th class="px-3 py-2 text-center">Qty</th>
                                    <th class="px-3 py-2 text-right">Harga Satuan</th>
                                    <th class="px-3 py-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-pink-50 text-gray-800">
                                <tr v-for="item in selectedOrder.items" :key="`item-${item.id}`">
                                    <td class="px-3 py-2 font-mono text-pink-700 font-medium whitespace-nowrap">
                                        {{ item.item_code && item.item_code !== '-' ? item.item_code : '-' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-pink-950">{{ item.item_name }}</div>
                                        <div v-if="item.sender_name" class="text-[11px] text-muted-foreground">
                                            Dari: <span class="font-medium text-gray-700">{{ item.sender_name }}</span>
                                        </div>
                                        <div v-if="item.greeting_card" class="text-[11px] text-muted-foreground italic">
                                            "{{ item.greeting_card }}"
                                        </div>
                                        <div v-if="item.money_bouquet > 0" class="text-[11px] text-amber-700 font-medium">
                                            Uang di Buket: {{ formatCurrency(item.money_bouquet) }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-center">{{ item.quantity }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(item.unit_price) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
                                </tr>
                                <tr v-if="!selectedOrder.items || selectedOrder.items.length === 0">
                                    <td colspan="5" class="px-3 py-4 text-center text-muted-foreground">Tidak ada detail item.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Financial Totals -->
                <div class="bg-pink-50/50 rounded-xl p-4 space-y-2 text-xs border border-pink-100">
                    <div class="flex justify-between text-gray-600">
                        <span>Biaya Pengiriman (Gosend / Ongkir):</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(selectedOrder.gosend) }}</span>
                    </div>
                    <div v-if="selectedOrder.money > 0" class="flex justify-between text-gray-600">
                        <span>Total Uang Buket (Money):</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(selectedOrder.money) }}</span>
                    </div>
                    <div v-if="selectedOrder.bouquet_fee > 0" class="flex justify-between text-gray-600">
                        <span>Jasa & Bunga (Fee Florist):</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(selectedOrder.bouquet_fee) }}</span>
                    </div>
                    <div v-if="selectedOrder.order_supply_income > 0" class="flex justify-between text-blue-700">
                        <span>Pendapatan Supply:</span>
                        <span class="font-medium text-blue-900">{{ formatCurrency(selectedOrder.order_supply_income) }}</span>
                    </div>
                    <div v-if="selectedOrder.discount > 0" class="flex justify-between text-rose-700 font-medium">
                        <span>Diskon Khusus:</span>
                        <span>-{{ formatCurrency(selectedOrder.discount) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-pink-950 border-t border-pink-200 pt-2">
                        <span>Total Order:</span>
                        <span>{{ formatCurrency(selectedOrder.total) }}</span>
                    </div>
                    <div class="flex justify-between text-amber-800 font-semibold">
                        <span>DP Diterima:</span>
                        <span>{{ formatCurrency(selectedOrder.dp) }}</span>
                    </div>
                    <div class="flex justify-between font-bold" :class="selectedOrder.unpaid_amount > 0 ? 'text-rose-700' : 'text-emerald-700'">
                        <span>Sisa Tagihan / Piutang:</span>
                        <span>{{ formatCurrency(selectedOrder.unpaid_amount) }}</span>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="mt-5 flex justify-end gap-2">
                    <BaseButton
                        as="Link"
                        :href="route('orders.show', selectedOrder.order_id)"
                        variant="primary"
                        size="sm"
                    >
                        Buka Halaman Order
                    </BaseButton>
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        @click="closeOrderDetail"
                    >
                        Tutup
                    </BaseButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

