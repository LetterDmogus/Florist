<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

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
    purchaseSummary: {
        type: Object,
        required: true,
    },
    purchaseRows: {
        type: Array,
        default: () => [],
    },
    supplyPurchaseRows: {
        type: Array,
        default: () => [],
    },
    storeExpenseRows: {
        type: Array,
        default: () => [],
    },
    rawMaterialRows: {
        type: Array,
        default: () => [],
    },
    shippingRows: {
        type: Array,
        default: () => [],
    },
    refundRows: {
        type: Array,
        default: () => [],
    },
    reportEntries: {
        type: Array,
        default: () => [],
    },
    reportCategoryOptions: {
        type: Array,
        default: () => [],
    },
    canManageReportEntries: {
        type: Boolean,
        default: false,
    },
});

const selectedMonth = ref(props.filters.month);
const selectedYear = ref(props.filters.year);
const showEntryModal = ref(false);
const editingEntry = ref(null);

const exportUrl = computed(() => route('reports.purchases.export', {
    month: selectedMonth.value,
    year: selectedYear.value,
}));

const form = useForm({
    occurred_on: '',
    category: 'store_expense',
    description: '',
    amount_idr: '',
    amount_rmb: '',
    exchange_rate: '',
    freight_idr: '',
    tracking_number: '',
    code: '',
    estimated_arrived_on: '',
    notes: '',
});

const isPurchaseSupply = computed(() => form.category === 'purchase_supply');
const isRefund = computed(() => form.category === 'refund');

watch([selectedMonth, selectedYear], () => {
    router.get(route('reports.purchases.index'), {
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

const formatNumber = (value) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}).format(Number(value || 0));

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.occurred_on = `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-01`;
    form.category = 'store_expense';
};

const openCreateEntry = () => {
    editingEntry.value = null;
    resetForm();
    showEntryModal.value = true;
};

const openEditEntry = (entry) => {
    editingEntry.value = entry;
    form.occurred_on = entry.occurred_on || '';
    form.category = entry.category || 'store_expense';
    form.description = entry.description || '';
    form.amount_idr = entry.amount_idr ?? '';
    form.amount_rmb = entry.amount_rmb ?? '';
    form.exchange_rate = entry.exchange_rate ?? '';
    form.freight_idr = entry.freight_idr ?? '';
    form.tracking_number = entry.tracking_number || '';
    form.code = entry.code || '';
    form.estimated_arrived_on = entry.estimated_arrived_on || '';
    form.notes = entry.notes || '';
    form.clearErrors();
    showEntryModal.value = true;
};

const closeEntryModal = () => {
    showEntryModal.value = false;
    editingEntry.value = null;
    resetForm();
};

const submitEntry = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeEntryModal(),
    };

    if (editingEntry.value) {
        form.put(route('reports.entries.update', editingEntry.value.id), options);
        return;
    }

    form.post(route('reports.entries.store'), options);
};

const deleteEntry = (entry) => {
    if (!confirm(`Hapus entry "${entry.description}"?`)) {
        return;
    }

    router.delete(route('reports.entries.destroy', entry.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Laporan Pembelian">
        <Head title="Laporan Pembelian" />

        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Laporan Pembelian
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
                    <PrimaryButton v-if="canManageReportEntries" class="rounded-xl flex items-center gap-2" @click="openCreateEntry">
                        <Plus class="w-4 h-4" />
                        Entry Manual
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <section class="rounded-2xl border border-pink-100 bg-white p-5">
                    <h3 class="font-semibold text-pink-900 mb-3">Pembelian</h3>
                    <div class="overflow-x-auto rounded-xl border border-pink-100">
                        <table class="min-w-full text-sm">
                            <thead class="bg-pink-50 text-pink-900">
                                <tr>
                                    <th class="px-3 py-2 text-left">No</th>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Item</th>
                                    <th class="px-3 py-2 text-right">RMB</th>
                                    <th class="px-3 py-2 text-right">IDR</th>
                                    <th class="px-3 py-2 text-right">Freight</th>
                                    <th class="px-3 py-2 text-left">No Resi</th>
                                    <th class="px-3 py-2 text-left">Kode</th>
                                    <th class="px-3 py-2 text-left">Estimate Arrived</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in purchaseRows" :key="`purchase-${row.no}`" class="border-t border-pink-100">
                                    <td class="px-3 py-2">{{ row.no }}</td>
                                    <td class="px-3 py-2">{{ row.date || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.item || '-' }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.rmb ? formatNumber(row.rmb) : '-' }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.idr) }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.freight ? formatCurrency(row.freight) : '-' }}</td>
                                    <td class="px-3 py-2">{{ row.tracking_number || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.code || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.estimate_arrived || '-' }}</td>
                                    <td class="px-3 py-2 text-right font-medium">{{ formatCurrency(row.total) }}</td>
                                </tr>
                                <tr v-if="purchaseRows.length === 0">
                                    <td colspan="10" class="px-3 py-5 text-center text-muted-foreground">Belum ada data pembelian untuk periode ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-pink-100 bg-white p-5">
                        <h3 class="font-semibold text-pink-900 mb-3">Supply Purchase</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                            <div v-for="row in supplyPurchaseRows" :key="`supply-${row.no}`" class="rounded-xl border border-pink-100 p-3 text-sm">
                                <div class="flex justify-between gap-2">
                                    <p class="font-medium">{{ row.item }}</p>
                                    <p>{{ formatCurrency(row.total) }}</p>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ row.date || '-' }}</p>
                            </div>
                            <p v-if="supplyPurchaseRows.length === 0" class="text-sm text-muted-foreground">Belum ada data.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-pink-100 bg-white p-5">
                        <h3 class="font-semibold text-pink-900 mb-3">Biaya Toko</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                            <div v-for="row in storeExpenseRows" :key="`store-${row.no}`" class="rounded-xl border border-pink-100 p-3 text-sm">
                                <div class="flex justify-between gap-2">
                                    <p class="font-medium">{{ row.description }}</p>
                                    <p>{{ formatCurrency(row.amount) }}</p>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ row.date || '-' }}</p>
                            </div>
                            <p v-if="storeExpenseRows.length === 0" class="text-sm text-muted-foreground">Belum ada data.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-pink-100 bg-white p-5">
                        <h3 class="font-semibold text-pink-900 mb-3">Biaya Bahan Baku</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                            <div v-for="row in rawMaterialRows" :key="`raw-${row.no}`" class="rounded-xl border border-pink-100 p-3 text-sm">
                                <div class="flex justify-between gap-2">
                                    <p class="font-medium">{{ row.description }}</p>
                                    <p>{{ formatCurrency(row.amount) }}</p>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ row.date || '-' }}</p>
                            </div>
                            <p v-if="rawMaterialRows.length === 0" class="text-sm text-muted-foreground">Belum ada data.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-pink-100 bg-white p-5">
                        <h3 class="font-semibold text-pink-900 mb-3">Shipping & Refund</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                            <div v-for="row in shippingRows" :key="`shipping-${row.no}`" class="rounded-xl border border-pink-100 p-3 text-sm">
                                <div class="flex justify-between gap-2">
                                    <p class="font-medium">{{ row.description }}</p>
                                    <p>{{ formatCurrency(row.amount) }}</p>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ row.date || '-' }}</p>
                            </div>
                            <div v-for="row in refundRows" :key="`refund-${row.no}`" class="rounded-xl border border-pink-100 p-3 text-sm">
                                <div class="flex justify-between gap-2">
                                    <p class="font-medium">{{ row.description }}</p>
                                    <p>{{ formatCurrency(row.idr) }}</p>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ row.date || '-' }} • RMB {{ formatNumber(row.rmb) }}</p>
                            </div>
                            <p v-if="shippingRows.length === 0 && refundRows.length === 0" class="text-sm text-muted-foreground">Belum ada data.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-pink-100 bg-white p-5">
                    <h3 class="font-semibold text-pink-900 mb-3">Rekap Pembelian</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div class="flex justify-between rounded-xl bg-pink-50 p-3"><span>Total Pembelian</span><span>{{ formatCurrency(purchaseSummary.purchase_total) }}</span></div>
                        <div class="flex justify-between rounded-xl bg-pink-50 p-3"><span>Total Supply Purchase</span><span>{{ formatCurrency(purchaseSummary.supply_purchase_total) }}</span></div>
                        <div class="flex justify-between rounded-xl bg-pink-50 p-3"><span>Total Biaya Toko</span><span>{{ formatCurrency(purchaseSummary.store_expense_total) }}</span></div>
                        <div class="flex justify-between rounded-xl bg-pink-50 p-3"><span>Total Biaya Bahan Baku</span><span>{{ formatCurrency(purchaseSummary.raw_material_expense_total) }}</span></div>
                        <div class="flex justify-between rounded-xl bg-pink-50 p-3"><span>Total Biaya</span><span>{{ formatCurrency(purchaseSummary.total_expense) }}</span></div>
                        <div class="flex justify-between rounded-xl bg-pink-50 p-3"><span>Refund IDR</span><span>{{ formatCurrency(purchaseSummary.refund_idr_total) }}</span></div>
                        <div class="flex justify-between rounded-xl bg-pink-100 p-3 md:col-span-2 font-semibold">
                            <span>Grand Total (Total Biaya + Pembelian - Refund)</span>
                            <span>{{ formatCurrency(purchaseSummary.grand_total) }}</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-pink-100 bg-white p-5">
                    <h3 class="font-semibold text-pink-900 mb-3">Entry Manual Report</h3>
                    <div class="overflow-x-auto rounded-xl border border-pink-100">
                        <table class="min-w-full text-sm">
                            <thead class="bg-pink-50 text-pink-900">
                                <tr>
                                    <th class="px-3 py-2 text-left">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Kategori</th>
                                    <th class="px-3 py-2 text-left">Deskripsi</th>
                                    <th class="px-3 py-2 text-right">IDR</th>
                                    <th class="px-3 py-2 text-right">RMB</th>
                                    <th class="px-3 py-2 text-right">Rate</th>
                                    <th v-if="canManageReportEntries" class="px-3 py-2 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in reportEntries" :key="entry.id" class="border-t border-pink-100">
                                    <td class="px-3 py-2">{{ entry.occurred_on || '-' }}</td>
                                    <td class="px-3 py-2">{{ entry.category_label }}</td>
                                    <td class="px-3 py-2">{{ entry.description }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(entry.amount_idr) }}</td>
                                    <td class="px-3 py-2 text-right">{{ entry.amount_rmb ? formatNumber(entry.amount_rmb) : '-' }}</td>
                                    <td class="px-3 py-2 text-right">{{ entry.exchange_rate ? formatNumber(entry.exchange_rate) : '-' }}</td>
                                    <td v-if="canManageReportEntries" class="px-3 py-2">
                                        <div class="flex justify-end gap-1">
                                            <button class="rounded-lg p-1.5 bg-secondary/50 hover:bg-secondary text-foreground" @click="openEditEntry(entry)">
                                                <Pencil class="w-3.5 h-3.5" />
                                            </button>
                                            <button class="rounded-lg p-1.5 bg-red-50 hover:bg-red-100 text-red-700" @click="deleteEntry(entry)">
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="reportEntries.length === 0">
                                    <td :colspan="canManageReportEntries ? 7 : 6" class="px-3 py-5 text-center text-muted-foreground">Belum ada entry manual untuk periode ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <Modal :show="showEntryModal && canManageReportEntries" @close="closeEntryModal" max-width="2xl">
            <form class="p-6 space-y-4" @submit.prevent="submitEntry">
                <h3 class="text-lg font-semibold text-foreground">
                    {{ editingEntry ? 'Edit Entry Report' : 'Tambah Entry Report' }}
                </h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <InputLabel value="Tanggal" />
                        <TextInput v-model="form.occurred_on" type="date" class="w-full" />
                        <InputError :message="form.errors.occurred_on" />
                    </div>
                    <div class="space-y-1">
                        <InputLabel value="Kategori" />
                        <select
                            v-model="form.category"
                            class="w-full rounded-xl border-secondary focus:border-primary focus:ring-primary/40"
                        >
                            <option v-for="option in reportCategoryOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category" />
                    </div>
                </div>

                <div class="space-y-1">
                    <InputLabel value="Deskripsi" />
                    <TextInput v-model="form.description" class="w-full" />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <InputLabel value="Jumlah IDR" />
                        <TextInput v-model="form.amount_idr" type="number" class="w-full" />
                        <InputError :message="form.errors.amount_idr" />
                    </div>
                    <div class="space-y-1">
                        <InputLabel value="Jumlah RMB (opsional)" />
                        <TextInput v-model="form.amount_rmb" type="number" class="w-full" />
                        <InputError :message="form.errors.amount_rmb" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <InputLabel value="Kurs (opsional)" />
                        <TextInput v-model="form.exchange_rate" type="number" class="w-full" />
                        <InputError :message="form.errors.exchange_rate" />
                    </div>
                    <div class="space-y-1">
                        <InputLabel value="Freight IDR (opsional)" />
                        <TextInput v-model="form.freight_idr" type="number" class="w-full" />
                        <InputError :message="form.errors.freight_idr" />
                    </div>
                </div>

                <div v-if="isPurchaseSupply || isRefund" class="grid gap-3 sm:grid-cols-3">
                    <div class="space-y-1">
                        <InputLabel value="No Resi (opsional)" />
                        <TextInput v-model="form.tracking_number" class="w-full" />
                        <InputError :message="form.errors.tracking_number" />
                    </div>
                    <div class="space-y-1">
                        <InputLabel value="Kode (opsional)" />
                        <TextInput v-model="form.code" class="w-full" />
                        <InputError :message="form.errors.code" />
                    </div>
                    <div class="space-y-1">
                        <InputLabel value="Estimate Arrived (opsional)" />
                        <TextInput v-model="form.estimated_arrived_on" type="date" class="w-full" />
                        <InputError :message="form.errors.estimated_arrived_on" />
                    </div>
                </div>

                <div class="space-y-1">
                    <InputLabel value="Catatan (opsional)" />
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="w-full rounded-xl border-secondary focus:border-primary focus:ring-primary/40"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <SecondaryButton type="button" @click="closeEntryModal">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>

