<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
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
                    Laporan Penjualan
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
                <section class="rounded-2xl border border-pink-100 bg-white p-5">
                    <h3 class="font-semibold text-pink-900 mb-3">Pencatatan - {{ monthLabel }} {{ selectedYear }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4">
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
                            <p class="text-xs text-muted-foreground">Total</p>
                            <p class="font-semibold text-pink-900">{{ formatCurrency(salesSummary.total) }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-pink-100">
                        <table class="min-w-full text-sm">
                            <thead class="bg-pink-50 text-pink-900">
                                <tr>
                                    <th class="px-3 py-2 text-left">No</th>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Model</th>
                                    <th class="px-3 py-2 text-right">Money</th>
                                    <th class="px-3 py-2 text-right">Fee</th>
                                    <th class="px-3 py-2 text-right">Gosend</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in salesRows" :key="`sales-${row.no}`" class="border-t border-pink-100">
                                    <td class="px-3 py-2">{{ row.no }}</td>
                                    <td class="px-3 py-2">{{ row.date || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.model }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.money) }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.fee) }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.gosend) }}</td>
                                    <td class="px-3 py-2 text-right font-medium">{{ formatCurrency(row.total) }}</td>
                                </tr>
                                <tr v-if="salesRows.length === 0">
                                    <td colspan="7" class="px-3 py-5 text-center text-muted-foreground">Belum ada data penjualan di periode ini.</td>
                                </tr>
                            </tbody>
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

