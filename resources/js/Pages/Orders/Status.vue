<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Printer, ArrowUpDown, ChevronUp, ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    orderStatusSummary: {
        type: Array,
        default: () => [],
    },
    canManageOrderStatus: {
        type: Boolean,
        default: false,
    },
});

const updatingOrderId = ref(null);
const updatingPaymentOrderId = ref(null);
const search = ref(props.filters?.search ?? '');
const detailOrderId = ref(null);
const sortBy = ref(props.filters?.sort_by ?? 'created_at');
const sortDir = ref(props.filters?.sort_dir ?? 'desc');

const handleSort = (key) => {
    if (sortBy.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = key;
        sortDir.value = 'asc';
    }
    
    router.get(route('orders.status.index'), {
        ...props.filters,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

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

const activeOrderStatus = computed(() => props.filters?.order_status ?? '');

const orderStatusSummaryWithAll = computed(() => {
    const total = props.orderStatusSummary.reduce((sum, item) => sum + Number(item.count || 0), 0);

    return [
        { value: '', label: 'All Status', count: total },
        ...props.orderStatusSummary,
    ];
});

const statusLabelMap = computed(() => {
    return Object.fromEntries(
        props.orderStatusSummary.map((status) => [status.value, status.label]),
    );
});

const statusValues = computed(() => props.orderStatusSummary.map((status) => status.value));

const formatOrderStatus = (status) => {
    return statusLabelMap.value[status] ?? status;
};

const formatPaymentStatus = (status) => {
    const labels = {
        unpaid: 'Belum Bayar',
        dp: 'DP',
        paid: 'Lunas',
    };

    return labels[status] ?? status;
};

const filterOrdersByStatus = (status) => {
    router.get(route('orders.status.index'), {
        order_status: status || '',
        search: search.value || '',
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['orders', 'filters', 'orderStatusSummary', 'canManageOrderStatus'],
    });
};

const applySearch = () => {
    router.get(route('orders.status.index'), {
        order_status: activeOrderStatus.value || '',
        search: search.value || '',
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['orders', 'filters', 'orderStatusSummary', 'canManageOrderStatus'],
    });
};

const getStatusIndex = (status) => {
    return statusValues.value.indexOf(status);
};

const getNextStatus = (status, shippingType = 'pickup') => {
    if (status === 'ready' && shippingType === 'pickup') {
        return 'completed';
    }

    const index = getStatusIndex(status);
    if (index < 0 || index >= statusValues.value.length - 1) {
        return null;
    }

    return statusValues.value[index + 1] ?? null;
};

const isBlockedByPayment = (order) => {
    const nextStatus = getNextStatus(order.order_status, order.shipping_type);

    return nextStatus === 'completed' && order.payment_status !== 'paid';
};

const getFinalStatus = () => {
    return statusValues.value[statusValues.value.length - 1] ?? null;
};

const isFinalStatus = (status) => {
    return status === getFinalStatus();
};

const stepOrderStatus = (order) => {
    const status = getNextStatus(order.order_status, order.shipping_type);

    if (!status) {
        return;
    }

    updatingOrderId.value = order.id;

    router.patch(route('orders.status.update', order.id), {
        order_status: status,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['orders', 'filters', 'orderStatusSummary', 'canManageOrderStatus'],
        onFinish: () => {
            updatingOrderId.value = null;
        },
    });
};

const markOrderAsPaid = (order) => {
    if (order.payment_status === 'paid') {
        return;
    }

    updatingPaymentOrderId.value = order.id;

    router.patch(route('orders.payment-status.update', order.id), {
        payment_status: 'paid',
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['orders', 'filters', 'orderStatusSummary', 'canManageOrderStatus'],
        onFinish: () => {
            updatingPaymentOrderId.value = null;
        },
    });
};

const getNextStatusLabel = (status, shippingType = 'pickup') => {
    const next = getNextStatus(status, shippingType);

    return next ? formatOrderStatus(next) : '';
};

const getActionLabel = (order) => {
    if (isBlockedByPayment(order)) {
        return 'Lunasi Dulu';
    }

    return getNextStatusLabel(order.order_status, order.shipping_type);
};

const selectedOrder = computed(() => {
    const id = detailOrderId.value;
    if (!id) {
        return null;
    }

    return props.orders?.data?.find((order) => Number(order.id) === Number(id)) ?? null;
});

const detailRows = computed(() => {
    return selectedOrder.value?.order_details ?? [];
});

const showDetailModal = computed(() => selectedOrder.value !== null);

const openDetailModal = (order) => {
    detailOrderId.value = order.id;
};

const closeDetailModal = () => {
    detailOrderId.value = null;
};

const resolveDetailName = (detail) => {
    if (detail.item_type === 'bouquet') {
        return detail.bouquet_unit?.name ?? '-';
    }

    return detail.inventory_item?.name ?? '-';
};

watch(
    () => props.filters?.search,
    (value) => {
        search.value = value ?? '';
    },
);

watch(
    () => props.filters?.sort_by,
    (value) => {
        sortBy.value = value ?? 'created_at';
    },
);

watch(
    () => props.filters?.sort_dir,
    (value) => {
        sortDir.value = value ?? 'desc';
    },
);

watch(
    () => props.orders?.data,
    (orders) => {
        if (!detailOrderId.value) {
            return;
        }

        const found = (orders ?? []).some((order) => Number(order.id) === Number(detailOrderId.value));
        if (!found) {
            closeDetailModal();
        }
    },
);
</script>

<template>
    <AppLayout title="Order Status">
        <Head title="Order Status" />

        <div class="space-y-6">
            <section class="rounded-3xl border border-pink-200 bg-gradient-to-r from-pink-100/70 via-white to-pink-50 p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-pink-600">Order Tracking</p>
                <h1 class="mt-2 text-2xl font-bold text-pink-950">Status Order</h1>
                <p class="mt-1 text-sm text-pink-700">Lihat order berdasarkan status dan atur progres order.</p>
            </section>

            <section class="rounded-3xl border border-pink-200/80 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-pink-950">Daftar Order</h2>
                    <span class="text-xs text-pink-700">{{ orders.total ?? 0 }} total order</span>
                </div>

                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari order ID / customer / no hp..."
                        class="w-full max-w-md rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                        @keyup.enter="applySearch"
                    >
                    <button
                        type="button"
                        class="rounded-xl bg-pink-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-pink-700"
                        @click="applySearch"
                    >
                        Cari
                    </button>
                </div>

                <div class="mb-4 flex flex-wrap gap-2">
                    <button
                        v-for="status in orderStatusSummaryWithAll"
                        :key="`status-${status.value || 'all'}`"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition"
                        :class="activeOrderStatus === status.value
                            ? 'border-pink-600 bg-pink-600 text-white'
                            : 'border-pink-200 bg-pink-50 text-pink-700 hover:border-pink-300 hover:bg-pink-100'"
                        @click="filterOrdersByStatus(status.value)"
                    >
                        <span>{{ status.label }}</span>
                        <span
                            class="rounded-md px-1.5 py-0.5 text-[10px]"
                            :class="activeOrderStatus === status.value ? 'bg-white/20 text-white' : 'bg-pink-100 text-pink-700'"
                        >
                            {{ status.count }}
                        </span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-pink-100">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-pink-600">
                                <th class="px-3 py-2 cursor-pointer select-none hover:text-pink-800" @click="handleSort('customer_id')">
                                    <div class="flex items-center gap-1">
                                        Customer
                                        <ChevronUp v-if="sortBy === 'customer_id' && sortDir === 'asc'" class="w-3 h-3" />
                                        <ChevronDown v-else-if="sortBy === 'customer_id' && sortDir === 'desc'" class="w-3 h-3" />
                                        <ArrowUpDown v-else class="w-3 h-3 opacity-20" />
                                    </div>
                                </th>
                                <th class="px-3 py-2 cursor-pointer select-none hover:text-pink-800" @click="handleSort('shipping_date')">
                                    <div class="flex items-center gap-1">
                                        Tanggal
                                        <ChevronUp v-if="sortBy === 'shipping_date' && sortDir === 'asc'" class="w-3 h-3" />
                                        <ChevronDown v-else-if="sortBy === 'shipping_date' && sortDir === 'desc'" class="w-3 h-3" />
                                        <ArrowUpDown v-else class="w-3 h-3 opacity-20" />
                                    </div>
                                </th>
                                <th class="px-3 py-2">Shipping</th>
                                <th class="px-3 py-2 cursor-pointer select-none hover:text-pink-800" @click="handleSort('total')">
                                    <div class="flex items-center gap-1">
                                        Total
                                        <ChevronUp v-if="sortBy === 'total' && sortDir === 'asc'" class="w-3 h-3" />
                                        <ChevronDown v-else-if="sortBy === 'total' && sortDir === 'desc'" class="w-3 h-3" />
                                        <ArrowUpDown v-else class="w-3 h-3 opacity-20" />
                                    </div>
                                </th>
                                <th class="px-3 py-2">Ongkir</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Pembayaran</th>
                                <th class="px-3 py-2">Detail</th>
                                <th v-if="canManageOrderStatus" class="px-3 py-2">Atur Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-100 text-sm">
                            <tr v-for="order in orders.data" :key="order.id">
                                <td class="px-3 py-2 font-medium text-pink-950">{{ order.customer?.name ?? '-' }}</td>
                                <td class="px-3 py-2 text-pink-800">{{ formatShippingDate(order.shipping_date) }} {{ formatShippingTime(order.shipping_time) }}</td>
                                <td class="px-3 py-2 capitalize text-pink-800">{{ order.shipping_type }}</td>
                                <td class="px-3 py-2 font-semibold text-pink-900">{{ formatCurrency(order.total) }}</td>
                                <td class="px-3 py-2 text-pink-800">{{ formatCurrency(order.shipping_fee ?? 0) }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-lg bg-pink-100 px-2 py-1 text-xs font-semibold text-pink-700">
                                        {{ formatOrderStatus(order.order_status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded-lg px-2 py-1 text-xs font-semibold"
                                        :class="order.payment_status === 'paid'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : order.payment_status === 'dp'
                                                ? 'bg-amber-100 text-amber-700'
                                                : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ formatPaymentStatus(order.payment_status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-1">
                                        <BaseButton
                                            as="Link"
                                            :href="route('orders.print', order.id)"
                                            variant="info"
                                            size="icon"
                                            class="h-8 w-8"
                                            title="Cetak Struk"
                                        >
                                            <Printer class="h-4 w-4" />
                                        </BaseButton>
                                        <button
                                            type="button"
                                            class="inline-flex rounded-lg border border-pink-200 bg-white px-3 py-1.5 text-xs font-semibold text-pink-700 transition hover:bg-pink-50"
                                            @click="openDetailModal(order)"
                                        >
                                            Lihat Detail
                                        </button>
                                    </div>
                                </td>
                                <td v-if="canManageOrderStatus" class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="order.payment_status !== 'paid'"
                                            type="button"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="updatingPaymentOrderId === order.id"
                                            @click="markOrderAsPaid(order)"
                                        >
                                            {{ updatingPaymentOrderId === order.id ? 'Saving...' : 'Tandai Lunas' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg bg-pink-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-pink-700 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="updatingOrderId === order.id || !getNextStatus(order.order_status, order.shipping_type) || isBlockedByPayment(order)"
                                            @click="stepOrderStatus(order)"
                                        >
                                            {{ updatingOrderId === order.id ? 'Saving...' : getActionLabel(order) }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="orders.data.length === 0">
                                <td :colspan="canManageOrderStatus ? 9 : 8" class="px-3 py-6 text-center text-sm text-pink-700">Belum ada order.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Modal :show="showDetailModal" max-width="2xl" @close="closeDetailModal">
            <div v-if="selectedOrder" class="space-y-4 p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-pink-950">Detail Order #{{ selectedOrder.id }}</h3>
                        <p class="mt-1 text-xs text-pink-700">
                            {{ selectedOrder.customer?.name ?? '-' }} •
                            {{ formatShippingDate(selectedOrder.shipping_date) }} {{ formatShippingTime(selectedOrder.shipping_time) }} •
                            {{ selectedOrder.shipping_type }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <BaseButton
                            as="Link"
                            :href="route('orders.print', selectedOrder.id)"
                            variant="info"
                            size="sm"
                        >
                            <Printer class="w-4 h-4" />
                            Cetak Struk
                        </BaseButton>
                        <button
                            type="button"
                            class="rounded-lg border border-pink-200 px-3 py-1 text-xs font-semibold text-pink-700 hover:bg-pink-50"
                            @click="closeDetailModal"
                        >
                            Tutup
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-pink-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-pink-600">Total</p>
                        <p class="mt-1 text-sm font-bold text-pink-950">{{ formatCurrency(selectedOrder.total) }}</p>
                    </div>
                    <div class="rounded-xl bg-pink-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-pink-600">Ongkir</p>
                        <p class="mt-1 text-sm font-bold text-pink-950">{{ formatCurrency(selectedOrder.shipping_fee ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-pink-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-pink-600">Status</p>
                        <p class="mt-1 text-sm font-bold text-pink-950">{{ formatOrderStatus(selectedOrder.order_status) }}</p>
                    </div>
                    <div class="rounded-xl bg-pink-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-pink-600">Payment</p>
                        <p class="mt-1 text-sm font-bold text-pink-950">{{ formatPaymentStatus(selectedOrder.payment_status) }}</p>
                    </div>
                </div>

                <div
                    v-if="selectedOrder.shipping_type === 'delivery' && selectedOrder.delivery"
                    class="rounded-xl border border-pink-200 bg-pink-50/60 p-3 text-xs text-pink-800"
                >
                    <p class="font-semibold text-pink-900">Informasi Delivery</p>
                    <p class="mt-1">{{ selectedOrder.delivery.recipient_name }} ({{ selectedOrder.delivery.recipient_phone }})</p>
                    <p>{{ selectedOrder.delivery.full_address }}</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-pink-100">
                    <table class="min-w-full divide-y divide-pink-100">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wide text-pink-600">
                                <th class="px-3 py-2">Item</th>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Qty</th>
                                <th class="px-3 py-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-100 text-xs">
                            <tr v-for="detail in detailRows" :key="detail.id">
                                <td class="px-3 py-2 font-medium text-pink-950">{{ resolveDetailName(detail) }}</td>
                                <td class="px-3 py-2 capitalize text-pink-800">{{ detail.item_type }}</td>
                                <td class="px-3 py-2 text-pink-800">{{ detail.quantity }}</td>
                                <td class="px-3 py-2 font-semibold text-pink-900">{{ formatCurrency(detail.subtotal) }}</td>
                            </tr>
                            <tr v-if="detailRows.length === 0">
                                <td colspan="4" class="px-3 py-4 text-center text-xs text-pink-700">Belum ada item.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
