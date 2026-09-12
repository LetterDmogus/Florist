<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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

const exportUrl = computed(() => route('reports.sales.export', {
    month: selectedMonth.value,
    year: selectedYear.value,
}));

const monthLabel = computed(() => {
    const found = props.monthOptions.find((item) => Number(item.value) === Number(selectedMonth.value));
    return found?.label || selectedMonth.value;
});

watch([selectedMonth, selectedYear], () => {
    router.get(route('reports.sales.index'), {
        month: selectedMonth.value,
        year: selectedYear.value,
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
                        v-model="selectedMonth"
                        class="rounded-xl border-secondary focus:border-primary focus:ring-primary/40"
                    >
                        <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                            {{ month.label }}
                        </option>
                    </select>
                    <select
                        v-model="selectedYear"
                        class="rounded-xl border-secondary focus:border-primary focus:ring-primary/40"
                    >
                        <option v-for="year in yearOptions" :key="year" :value="year">
                            {{ year }}
                        </option>
                    </select>
                    <a
                        :href="exportUrl"
                        class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 transition"
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
                        <h3 class="font-semibold text-pink-900">Pencatatan - {{ monthLabel }} {{ selectedYear }}</h3>
                        <div class="flex items-center gap-2 text-xs text-rose-700 font-medium">
                            <span class="inline-block w-3 h-3 rounded bg-rose-50 border border-rose-300"></span>
                            <span>Highlight Merah: Belum Lunas / DP</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
                        <div class="rounded-xl bg-pink-50 p-3">
                            <p class="text-xs text-muted-foreground">Money</p>
                            <p class="font-semibold text-pink-900">{{ formatCurrency(salesSummary.money) }}</p>
                        </div>
                        <div class="rounded-xl bg-pink-50 p-3">
                            <p class="text-xs text-muted-foreground">Fee</p>
                            <p class="font-semibold text-pink-900">{{ formatCurrency(salesSummary.fee) }}</p>
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
                            <p class="text-xs font-semibold text-rose-700">Total Piutang (Belum Lunas)</p>
                            <p class="font-bold text-rose-900">{{ formatCurrency(salesSummary.unpaid_total) }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-pink-100">
                        <table class="min-w-full text-sm">
                            <thead class="bg-pink-50 text-pink-900">
                                <tr>
                                    <th class="px-3 py-2 text-left">No</th>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Model</th>
                                    <th class="px-3 py-2 text-center">Status</th>
                                    <th class="px-3 py-2 text-right">DP Diterima</th>
                                    <th class="px-3 py-2 text-right">Belum Dibayar</th>
                                    <th class="px-3 py-2 text-right">Money</th>
                                    <th class="px-3 py-2 text-right">Fee</th>
                                    <th class="px-3 py-2 text-right">Gosend</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in salesRows"
                                    :key="`sales-${row.no}`"
                                    class="border-t transition-colors"
                                    :class="row.is_unpaid
                                        ? 'bg-rose-50/80 hover:bg-rose-100/70 border-rose-200 text-rose-950 font-medium'
                                        : 'hover:bg-pink-50/40 border-pink-100 text-foreground'"
                                >
                                    <td class="px-3 py-2 font-mono text-xs">{{ row.no }}</td>
                                    <td class="px-3 py-2">{{ row.date || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.model }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span
                                            v-if="row.payment_status === 'dp'"
                                            class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300"
                                        >
                                            DP
                                        </span>
                                        <span
                                            v-else-if="row.payment_status === 'unpaid'"
                                            class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
                                        >
                                            Belum Lunas
                                        </span>
                                        <span
                                            v-else
                                            class="inline-block px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"
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
                                        <span v-if="row.unpaid_amount > 0" class="font-bold text-rose-600">
                                            {{ formatCurrency(row.unpaid_amount) }}
                                        </span>
                                        <span v-else class="text-muted-foreground text-xs">
                                            -
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.money) }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.fee) }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.gosend) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(row.total) }}</td>
                                </tr>
                                <tr v-if="salesRows.length === 0">
                                    <td colspan="10" class="px-3 py-5 text-center text-muted-foreground">Belum ada data penjualan di periode ini.</td>
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
                                    <td class="px-3 py-2.5 text-right">{{ formatCurrency(salesSummary.gosend) }}</td>
                                    <td class="px-3 py-2.5 text-right font-black">{{ formatCurrency(salesSummary.total) }}</td>
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
    </AppLayout>
</template>

