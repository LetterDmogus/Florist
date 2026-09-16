<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { 
    Printer, 
    Pencil, 
    Trash2, 
    XCircle, 
    Clock, 
    PackageCheck, 
    Truck, 
    CheckCircle2, 
    AlertCircle, 
    Eye, 
    EyeOff,
    Search, 
    RotateCcw, 
    LayoutGrid, 
    List,
    Calendar,
    Phone,
    MapPin,
    Sparkles,
    ArrowUpDown,
    ChevronUp,
    ChevronDown
} from 'lucide-vue-next';

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
    canDeleteOrder: {
        type: Boolean,
        default: false,
    },
});

const viewMode = ref('kanban');
const updatingOrderId = ref(null);
const updatingPaymentOrderId = ref(null);
const search = ref(props.filters?.search ?? '');
const detailOrderId = ref(null);
const draggingOrderId = ref(null);
const dragOverStatus = ref(null);
const selectedDate = ref(props.filters?.date_from || props.filters?.date || '');
const selectedDateTo = ref(props.filters?.date_to ?? '');
const showHidden = ref(Boolean(props.filters?.show_hidden));

// LocalStorage key for persisting minimized orders
const STORAGE_KEY_MINIMIZED_ORDERS = 'florist_minimized_order_ids';

const loadMinimizedOrdersFromStorage = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY_MINIMIZED_ORDERS);
        if (stored) {
            const parsed = JSON.parse(stored);
            if (Array.isArray(parsed)) {
                return new Set(parsed);
            }
        }
    } catch (e) {
        console.error('Failed to load minimized orders from storage', e);
    }
    return new Set();
};

const saveMinimizedOrdersToStorage = (set) => {
    try {
        localStorage.setItem(STORAGE_KEY_MINIMIZED_ORDERS, JSON.stringify(Array.from(set)));
    } catch (e) {
        console.error('Failed to save minimized orders to storage', e);
    }
};

// Set of minimized order IDs (persisted in localStorage)
const minimizedOrderIds = ref(loadMinimizedOrdersFromStorage());

const isOrderMinimized = (orderId) => {
    return minimizedOrderIds.value.has(orderId);
};

const toggleOrderMinimize = (orderId) => {
    if (minimizedOrderIds.value.has(orderId)) {
        minimizedOrderIds.value.delete(orderId);
    } else {
        minimizedOrderIds.value.add(orderId);
    }
    saveMinimizedOrdersToStorage(minimizedOrderIds.value);
};

const areAllMinimized = computed(() => {
    const allIds = ordersList.value.map(o => o.id);
    return allIds.length > 0 && allIds.every(id => minimizedOrderIds.value.has(id));
});

const toggleMinimizeAll = () => {
    const allIds = ordersList.value.map(o => o.id);
    if (areAllMinimized.value) {
        minimizedOrderIds.value.clear();
    } else {
        minimizedOrderIds.value = new Set(allIds);
    }
    saveMinimizedOrdersToStorage(minimizedOrderIds.value);
};

const toggleShowHidden = () => {
    showHidden.value = !showHidden.value;
    router.get(route('orders.status.index'), {
        ...props.filters,
        order_status: activeOrderStatus.value || '',
        search: search.value || '',
        date_from: selectedDate.value || '',
        date_to: selectedDateTo.value || '',
        date: selectedDate.value || '',
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
        show_hidden: showHidden.value ? 1 : 0,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const columns = [
    {
        key: 'pending',
        title: 'Belum Diproses',
        description: 'Pesanan baru masuk',
        icon: Clock,
        headerBg: 'bg-amber-50 text-amber-900 border-amber-200',
        badgeBg: 'bg-amber-100 text-amber-800',
        dropZoneBg: 'bg-amber-50/50 border-amber-300',
    },
    {
        key: 'ready',
        title: 'Siap di-Pickup',
        description: 'Rangkaian buket selesai',
        icon: PackageCheck,
        headerBg: 'bg-blue-50 text-blue-900 border-blue-200',
        badgeBg: 'bg-blue-100 text-blue-800',
        dropZoneBg: 'bg-blue-50/50 border-blue-300',
    },
    {
        key: 'on_delivery',
        title: 'Sedang Diantar',
        description: 'Dalam proses kurir',
        icon: Truck,
        headerBg: 'bg-purple-50 text-purple-900 border-purple-200',
        badgeBg: 'bg-purple-100 text-purple-800',
        dropZoneBg: 'bg-purple-50/50 border-purple-300',
    },
    {
        key: 'completed',
        title: 'Selesai (History)',
        description: 'Pesanan telah diterima',
        icon: CheckCircle2,
        headerBg: 'bg-emerald-50 text-emerald-900 border-emerald-200',
        badgeBg: 'bg-emerald-100 text-emerald-800',
        dropZoneBg: 'bg-emerald-50/50 border-emerald-300',
    },
    {
        key: 'canceled',
        title: 'Dibatalkan',
        description: 'Pesanan batal',
        icon: AlertCircle,
        headerBg: 'bg-rose-50 text-rose-900 border-rose-200',
        badgeBg: 'bg-rose-100 text-rose-800',
        dropZoneBg: 'bg-rose-50/50 border-rose-300',
    },
];

const formatCurrency = (value) => {
    const amount = Number(value) || 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

const formatShippingDate = (value) => {
    if (!value) return '-';
    const raw = String(value);
    const parsed = new Date(raw.includes('T') ? raw : `${raw}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return raw.split('T')[0] ?? raw;
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
};

const formatShippingTime = (value) => {
    if (!value) return '-';
    const [hour = '00', minute = '00'] = String(value).split(':');
    return `${hour}:${minute}`;
};

const ordersList = computed(() => {
    return props.orders?.data ?? [];
});

const visibleColumns = computed(() => columns);

const filteredOrdersList = computed(() => ordersList.value);

const ordersByStatus = computed(() => {
    const map = {
        pending: [],
        ready: [],
        on_delivery: [],
        completed: [],
        canceled: [],
    };

    ordersList.value.forEach((order) => {
        if (map[order.order_status]) {
            map[order.order_status].push(order);
        }
    });

    return map;
});

const statusLabelMap = computed(() => {
    return Object.fromEntries(
        props.orderStatusSummary.map((status) => [status.value, status.label]),
    );
});

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

const activeOrderStatus = computed(() => props.filters?.order_status ?? '');

const orderStatusSummaryWithAll = computed(() => {
    const total = props.orderStatusSummary.reduce((sum, item) => sum + Number(item.count || 0), 0);

    return [
        { value: '', label: 'Semua Status', count: total },
        ...props.orderStatusSummary,
    ];
});

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
        order_status: activeOrderStatus.value || '',
        search: search.value || '',
        date_from: selectedDate.value || '',
        date_to: useDateRange.value ? (selectedDateTo.value || '') : '',
        date: selectedDate.value || '',
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const filterOrdersByStatus = (status) => {
    router.get(route('orders.status.index'), {
        order_status: status || '',
        search: search.value || '',
        date_from: selectedDate.value || '',
        date_to: selectedDateTo.value || '',
        date: selectedDate.value || '',
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const applySearch = () => {
    router.get(route('orders.status.index'), {
        order_status: activeOrderStatus.value || '',
        search: search.value || '',
        date_from: selectedDate.value || '',
        date_to: selectedDateTo.value || '',
        date: selectedDate.value || '',
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const applyDateFilter = (from = null, to = null) => {
    if (from !== null) {
        selectedDate.value = from || '';
    }
    if (to !== null) {
        selectedDateTo.value = to || '';
    }
    router.get(route('orders.status.index'), {
        order_status: activeOrderStatus.value || '',
        search: search.value || '',
        date_from: selectedDate.value || '',
        date_to: selectedDateTo.value || '',
        date: selectedDate.value || '',
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const setDateToday = () => {
    const today = new Date().toISOString().split('T')[0];
    applyDateFilter(today, '');
};

const setDateTomorrow = () => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    const tomorrow = d.toISOString().split('T')[0];
    applyDateFilter(tomorrow, '');
};

const resetFilter = () => {
    search.value = '';
    selectedDate.value = '';
    selectedDateTo.value = '';
    router.get(route('orders.status.index'), {
        order_status: '',
        search: '',
        date_from: '',
        date_to: '',
        date: '',
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const goToPage = (url) => {
    if (url) {
        router.get(url, {
            order_status: activeOrderStatus.value || '',
            search: search.value || '',
            date_from: selectedDate.value || '',
            date_to: selectedDateTo.value || '',
            date: selectedDate.value || '',
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }
};

// Drag and Drop Logic
const onDragStart = (event, order) => {
    if (!props.canManageOrderStatus) return;
    draggingOrderId.value = order.id;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(order.id));
};

const onDragEnd = () => {
    draggingOrderId.value = null;
    dragOverStatus.value = null;
};

const onDragOver = (event, targetStatus) => {
    if (!props.canManageOrderStatus) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dragOverStatus.value = targetStatus;
};

const onDragLeave = (targetStatus) => {
    if (dragOverStatus.value === targetStatus) {
        dragOverStatus.value = null;
    }
};

const onDrop = (event, targetStatus) => {
    if (!props.canManageOrderStatus) return;
    event.preventDefault();
    dragOverStatus.value = null;

    const orderId = draggingOrderId.value || event.dataTransfer.getData('text/plain');
    if (!orderId) return;

    const order = ordersList.value.find((o) => Number(o.id) === Number(orderId));
    if (!order) return;

    if (order.order_status === targetStatus) return;

    if (targetStatus === 'completed' && order.payment_status !== 'paid') {
        alert('Order harus berstatus Lunas terlebih dahulu sebelum dipindahkan ke status Selesai!');
        return;
    }

    if (order.shipping_type === 'pickup' && targetStatus === 'on_delivery') {
        alert('Order tipe Pickup (Ambil Sendiri) tidak bisa dipindahkan ke Sedang Diantar.');
        return;
    }

    changeOrderStatus(order, targetStatus);
};

const changeOrderStatus = (order, targetStatus) => {
    updatingOrderId.value = order.id;

    router.patch(route('orders.status.update', order.id), {
        order_status: targetStatus,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            updatingOrderId.value = null;
        },
    });
};

const markOrderAsPaid = (order) => {
    if (order.payment_status === 'paid') return;
    updatingPaymentOrderId.value = order.id;

    router.patch(route('orders.payment-status.update', order.id), {
        payment_status: 'paid',
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            updatingPaymentOrderId.value = null;
        },
    });
};

const cancelOrder = (order) => {
    if (!confirm('Apakah Anda yakin ingin membatalkan order ini?')) return;
    changeOrderStatus(order, 'canceled');
};

const toggleHideOrder = (order) => {
    updatingOrderId.value = order.id;
    router.patch(route('orders.toggle-hide', order.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            updatingOrderId.value = null;
        },
    });
};

const discardOrder = (order) => {
    if (!confirm('Hapus order ini secara permanen?')) return;
    updatingOrderId.value = order.id;
    router.delete(route('orders.destroy', order.id), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            updatingOrderId.value = null;
        },
    });
};

const selectedOrder = computed(() => {
    const id = detailOrderId.value;
    if (!id) return null;
    return ordersList.value.find((order) => Number(order.id) === Number(id)) ?? null;
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
        return detail.bouquet_unit?.name ?? 'Bouquet Item';
    }
    return detail.inventory_item?.name ?? 'Material/Item';
};

watch(
    () => props.filters?.search,
    (value) => {
        search.value = value ?? '';
    },
);
</script>

<template>
    <AppLayout title="Order Kanban Board">
        <Head title="Order Status Board" />

        <div class="space-y-6">
            <!-- Header Section (Direct Title without Banner Container) -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-pink-950">Status & Tracking Pesanan</h1>
                    <p class="mt-0.5 text-xs text-pink-600">Geser kartu pesanan antar kolom status untuk memperbarui progress secara instan.</p>
                </div>

                <div class="flex items-center gap-2">
                    <div class="bg-white p-1 rounded-xl border border-pink-200 flex items-center shadow-xs">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="viewMode === 'kanban' ? 'bg-pink-600 text-white shadow-xs' : 'text-pink-700 hover:bg-pink-50'"
                            @click="viewMode = 'kanban'"
                        >
                            <LayoutGrid class="w-4 h-4" />
                            Kanban Board
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition"
                            :class="viewMode === 'table' ? 'bg-pink-600 text-white shadow-xs' : 'text-pink-700 hover:bg-pink-50'"
                            @click="viewMode = 'table'"
                        >
                            <List class="w-4 h-4" />
                            Tabel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter and Search Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 rounded-xl border border-pink-100 shadow-xs">
                <div class="flex flex-1 items-center gap-2 max-w-md">
                    <div class="relative w-full">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari ID, customer, atau no HP..."
                            class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-pink-200 focus:border-pink-500 focus:ring-1 focus:ring-pink-400"
                            @keyup.enter="applySearch"
                        >
                        <Search class="w-3.5 h-3.5 text-pink-400 absolute left-2.5 top-2.5" />
                    </div>
                    <button
                        type="button"
                        class="px-3 py-1.5 bg-pink-600 text-white rounded-xl text-xs font-bold hover:bg-pink-700 transition shadow-xs"
                        @click="applySearch"
                    >
                        Cari
                    </button>
                    <button
                        v-if="search"
                        type="button"
                        class="p-1.5 text-pink-500 hover:bg-pink-50 rounded-xl transition"
                        title="Reset pencarian"
                        @click="resetFilter"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                    </button>
                </div>

                    <!-- Date Filter (Dari Tanggal & Sampai Tanggal) & Quick Presets -->
                    <div class="flex flex-wrap items-center gap-2 bg-pink-50/60 p-1.5 rounded-xl border border-pink-200/80">
                        <!-- Dari Tanggal Input -->
                        <div class="flex items-center gap-1.5 px-2 py-1 bg-white rounded-lg border border-pink-200 shadow-2xs">
                            <Calendar class="w-3.5 h-3.5 text-pink-500 shrink-0" />
                            <span class="text-[10px] text-pink-600 font-bold whitespace-nowrap">Dari:</span>
                            <input
                                v-model="selectedDate"
                                type="date"
                                class="border-0 p-0 text-xs font-bold text-pink-950 focus:ring-0 cursor-pointer bg-transparent"
                                title="Tanggal awal"
                                @change="applyDateFilter(selectedDate, selectedDateTo)"
                            >
                            <button
                                v-if="selectedDate"
                                type="button"
                                class="text-pink-400 hover:text-pink-700 ml-0.5"
                                title="Kosongkan tanggal awal"
                                @click="applyDateFilter('', selectedDateTo)"
                            >
                                <XCircle class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <!-- Sampai Tanggal Input (Langsung tampil, opsional diisi) -->
                        <div class="flex items-center gap-1.5 px-2 py-1 bg-white rounded-lg border border-pink-200 shadow-2xs">
                            <span class="text-[10px] text-pink-600 font-bold whitespace-nowrap">Sampai:</span>
                            <input
                                v-model="selectedDateTo"
                                type="date"
                                class="border-0 p-0 text-xs font-bold text-pink-950 focus:ring-0 cursor-pointer bg-transparent"
                                title="Sampai tanggal (opsional)"
                                :min="selectedDate || undefined"
                                @change="applyDateFilter(selectedDate, selectedDateTo)"
                            >
                            <button
                                v-if="selectedDateTo"
                                type="button"
                                class="text-pink-400 hover:text-pink-700 ml-0.5"
                                title="Kosongkan tanggal akhir"
                                @click="applyDateFilter(selectedDate, '')"
                            >
                                <XCircle class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <!-- Quick Presets -->
                        <div class="flex items-center gap-1 border-l border-pink-200/80 pl-1.5">
                            <button
                                type="button"
                                class="px-2.5 py-1 text-xs font-semibold rounded-lg transition"
                                :class="!selectedDate && !selectedDateTo ? 'bg-pink-600 text-white shadow-2xs' : 'text-pink-700 hover:bg-pink-100/60'"
                                @click="resetFilter"
                            >
                                Semua
                            </button>
                            <button
                                type="button"
                                class="px-2.5 py-1 text-xs font-semibold rounded-lg transition"
                                :class="selectedDate === new Date().toISOString().split('T')[0] && !selectedDateTo ? 'bg-pink-600 text-white shadow-2xs' : 'text-pink-700 hover:bg-pink-100/60'"
                                @click="setDateToday"
                            >
                                Hari Ini
                            </button>
                            <button
                                type="button"
                                class="px-2.5 py-1 text-xs font-semibold rounded-lg transition"
                                :class="selectedDate === new Date(Date.now() + 86400000).toISOString().split('T')[0] && !selectedDateTo ? 'bg-pink-600 text-white shadow-2xs' : 'text-pink-700 hover:bg-pink-100/60'"
                                @click="setDateTomorrow"
                            >
                                Besok
                            </button>
                        </div>
                    </div>

                    <!-- Extra Actions (Minimize All & Show Hidden) -->
                    <div class="flex items-center gap-1.5 bg-pink-50/60 p-1 rounded-xl border border-pink-200/80">
                        <button
                            type="button"
                            class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold transition"
                            :class="areAllMinimized ? 'bg-pink-600 text-white shadow-2xs' : 'text-pink-800 hover:bg-pink-100/60'"
                            :title="areAllMinimized ? 'Buka semua kartu pesanan (Maximize)' : 'Kecilkan semua kartu pesanan (Minimize)'"
                            @click="toggleMinimizeAll"
                        >
                            <component :is="areAllMinimized ? ChevronDown : ChevronUp" class="w-3.5 h-3.5" />
                            <span>{{ areAllMinimized ? 'Buka' : 'Kecilkan' }}</span>
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold transition"
                            :class="showHidden ? 'bg-amber-600 text-white shadow-2xs' : 'text-amber-800 hover:bg-amber-100/60'"
                            :title="showHidden ? 'Sembunyikan kartu berstatus hide' : 'Tampilkan kartu yang di-hide'"
                            @click="toggleShowHidden"
                        >
                            <component :is="showHidden ? Eye : EyeOff" class="w-3.5 h-3.5" />
                            <span>{{ showHidden ? 'Hide: Tampil' : 'Lihat Hide' }}</span>
                        </button>
                    </div>

                    <div class="text-xs text-pink-800 font-medium whitespace-nowrap">
                        Total: <span class="font-bold text-pink-950">{{ filteredOrdersList.length }}</span> order
                    </div>
            </div>

            <!-- KANBAN BOARD VIEW -->
            <div v-if="viewMode === 'kanban'" class="overflow-x-auto pb-6">
                <!-- Container kolom disatukan tanpa gap, dipisahkan garis divide-x border -->
                <div
                    class="grid min-w-[900px] bg-white rounded-xl border border-pink-200 divide-y md:divide-y-0 md:divide-x divide-pink-200/90 shadow-2xs overflow-hidden"
                    :class="visibleColumns.length === 5 ? 'grid-cols-1 md:grid-cols-5' : visibleColumns.length === 4 ? 'grid-cols-1 md:grid-cols-4' : 'grid-cols-1 md:grid-cols-3'"
                >
                    <div
                        v-for="col in visibleColumns"
                        :key="col.key"
                        class="flex flex-col bg-pink-50/15 p-2 min-h-[580px] transition-colors"
                        :class="dragOverStatus === col.key ? col.dropZoneBg + ' ring-2 ring-inset ring-pink-400' : ''"
                        @dragover="onDragOver($event, col.key)"
                        @dragleave="onDragLeave(col.key)"
                        @drop="onDrop($event, col.key)"
                    >
                        <!-- Column Header -->
                        <div class="p-2 rounded-lg border mb-2 flex items-center justify-between" :class="col.headerBg">
                            <div class="flex items-center gap-1.5">
                                <component :is="col.icon" class="w-3.5 h-3.5" />
                                <h3 class="text-xs font-black uppercase tracking-wider">{{ col.title }}</h3>
                            </div>
                            <span class="px-1.5 py-0.2 rounded text-[10px] font-black" :class="col.badgeBg">
                                {{ ordersByStatus[col.key]?.length || 0 }}
                            </span>
                        </div>

                        <!-- Drop Zone / Cards List -->
                        <div class="flex-1 space-y-2 overflow-y-auto max-h-[720px] pr-0.5">
                            <div
                                v-for="order in ordersByStatus[col.key]"
                                :key="order.id"
                                :draggable="canManageOrderStatus && updatingOrderId !== order.id"
                                class="rounded-lg border shadow-2xs hover:shadow-xs transition-all select-none group overflow-hidden"
                                :class="[
                                    order.payment_status === 'dp'
                                        ? 'bg-amber-50/60 border-amber-300 hover:border-amber-400'
                                        : 'bg-white border-pink-200/90 hover:border-pink-400',
                                    canManageOrderStatus ? 'cursor-grab active:cursor-grabbing' : '',
                                    draggingOrderId === order.id ? 'opacity-40 scale-95' : '',
                                    updatingOrderId === order.id ? 'opacity-60 pointer-events-none' : '',
                                    isOrderMinimized(order.id) ? (order.payment_status === 'dp' ? 'bg-amber-50/30' : 'bg-pink-50/20') : ''
                                ]"
                                @dragstart="onDragStart($event, order)"
                                @dragend="onDragEnd"
                            >
                                <!-- Order Header (ID & Teks Pickup/Delivery di sampingnya, serta Payment Status + Lunas & Minimize Button) -->
                                <div
                                    class="px-2.5 py-1.5 border-b flex items-start justify-between gap-1.5"
                                    :class="order.payment_status === 'dp' ? 'bg-amber-100/50 border-amber-200/80' : 'bg-pink-50/40 border-pink-100'"
                                >
                                    <div class="flex flex-col gap-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="text-[11px] font-black font-mono"
                                                :class="order.payment_status === 'dp' ? 'text-amber-800' : 'text-pink-700'"
                                            >
                                                #{{ order.id }}
                                            </span>
                                            <!-- Teks Pickup / Delivery di samping kode pesanan -->
                                            <span
                                                class="px-1.5 py-0.2 rounded text-[9px] font-bold capitalize shrink-0"
                                                :class="order.shipping_type === 'delivery' ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-amber-50 text-amber-700 border border-amber-100'"
                                            >
                                                {{ order.shipping_type }}
                                            </span>
                                            <span
                                                v-if="order.is_hidden"
                                                class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-200 shrink-0"
                                                title="Order ini disembunyikan"
                                            >
                                                Hidden
                                            </span>
                                        </div>

                                        <!-- Badge Bouquet / Supply diletakkan di bawah kode pesanan -->
                                        <div>
                                            <span
                                                class="inline-block px-1.5 py-0.2 rounded text-[9px] font-bold"
                                                :class="order.order_type === 'inventory' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-pink-100 text-pink-800 border border-pink-200'"
                                                :title="order.order_type === 'inventory' ? 'Order Supply' : 'Order Bouquet'"
                                            >
                                                {{ order.order_type === 'inventory' ? 'Supply' : 'Bouquet' }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-start gap-1 shrink-0">
                                        <!-- Payment Status Badge dan Tombol Lunas tepat di bawahnya -->
                                        <div class="flex flex-col items-end gap-1">
                                            <span
                                                class="text-[9px] font-bold px-1.5 py-0.2 rounded uppercase tracking-wider"
                                                :class="order.payment_status === 'paid'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : order.payment_status === 'dp'
                                                        ? 'bg-amber-200/80 text-amber-900 font-extrabold border border-amber-300'
                                                        : 'bg-slate-100 text-slate-700'"
                                            >
                                                {{ formatPaymentStatus(order.payment_status) }}
                                            </span>

                                            <!-- Tombol Lunas tepat di bawah badge DP / Status Pembayaran -->
                                            <button
                                                v-if="canManageOrderStatus && order.payment_status !== 'paid' && order.order_status !== 'canceled'"
                                                type="button"
                                                class="px-1.5 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[9px] font-bold transition disabled:opacity-50 inline-flex items-center gap-0.5 shadow-2xs"
                                                :disabled="updatingPaymentOrderId === order.id"
                                                title="Tandai pesanan lunas"
                                                @click="markOrderAsPaid(order)"
                                            >
                                                <CheckCircle2 class="w-2.5 h-2.5 inline" />
                                                <span>{{ updatingPaymentOrderId === order.id ? '...' : 'Lunas' }}</span>
                                            </button>
                                        </div>

                                        <!-- Tombol Minimize / Maximize Card -->
                                        <button
                                            type="button"
                                            class="p-0.5 rounded text-pink-600 hover:text-pink-900 hover:bg-pink-100/70 transition"
                                            :title="isOrderMinimized(order.id) ? 'Buka rincian order (Maximize)' : 'Sederhanakan kartu (Minimize)'"
                                            @click="toggleOrderMinimize(order.id)"
                                        >
                                            <component :is="isOrderMinimized(order.id) ? ChevronDown : ChevronUp" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Customer Info Section (Selalu Tampil) -->
                                <div class="px-2.5 py-1.5" :class="!isOrderMinimized(order.id) ? 'border-b border-pink-100/70' : ''">
                                    <h4 class="text-xs font-bold text-pink-950 group-hover:text-pink-600 transition truncate leading-snug">
                                        {{ order.customer?.name ?? 'Guest' }}
                                    </h4>
                                    <p class="text-[10px] text-pink-700/80 flex items-center gap-1 mt-0.5">
                                        <Phone class="w-2.5 h-2.5 text-pink-400 shrink-0" />
                                        <span class="truncate">{{ order.customer?.phone_number ?? '-' }}</span>
                                    </p>
                                </div>

                                <!-- Bagian Isi Order & Info Lain (Hanya Tampil Saat Maximized) -->
                                <template v-if="!isOrderMinimized(order.id)">
                                    <!-- Items summary Section -->
                                    <div class="px-2.5 py-1.5 border-b border-pink-100/70 bg-pink-50/20 text-xs">
                                        <div v-for="detail in order.order_details" :key="detail.id" class="flex justify-between items-center text-[10px] py-0.5 leading-tight">
                                            <span class="truncate text-pink-900 max-w-[140px]">{{ resolveDetailName(detail) }}</span>
                                            <span class="text-pink-600 font-bold ml-1">x{{ detail.quantity }}</span>
                                        </div>
                                        <div v-if="!order.order_details?.length" class="text-[10px] text-pink-400 italic">
                                            Tidak ada item
                                        </div>
                                    </div>

                                    <!-- Schedule & Total Price Bar -->
                                    <div class="px-2.5 py-1.5 border-b border-pink-100/70 flex items-center justify-between text-[10px]">
                                        <div class="flex items-center gap-1 text-pink-700">
                                            <Calendar class="w-2.5 h-2.5 text-pink-500 shrink-0" />
                                            <span>{{ formatShippingDate(order.shipping_date) }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-pink-950 font-black text-xs">{{ formatCurrency(order.total) }}</span>
                                        </div>
                                    </div>

                                    <!-- Action Buttons Footer -->
                                    <div class="px-2.5 py-1.5 bg-pink-50/20 flex items-center justify-between gap-1">
                                        <div class="flex items-center gap-1">
                                            <Link
                                                :href="route('orders.print', order.id)"
                                                class="h-7 w-7 rounded-lg border border-blue-200 bg-blue-50/70 flex items-center justify-center text-blue-700 hover:bg-blue-100 transition shadow-2xs"
                                                title="Cetak Struk"
                                            >
                                                <Printer class="h-3.5 w-3.5" />
                                            </Link>
                                            <Link
                                                :href="route('orders.edit', order.id)"
                                                class="h-7 w-7 rounded-lg border border-pink-200 bg-white flex items-center justify-center text-pink-700 hover:bg-pink-50 transition shadow-2xs"
                                                title="Edit Order"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                            </Link>
                                            <button
                                                type="button"
                                                class="h-7 w-7 rounded-lg border border-pink-200 bg-white flex items-center justify-center text-pink-700 hover:bg-pink-50 transition shadow-2xs"
                                                title="Lihat Detail"
                                                @click="openDetailModal(order)"
                                            >
                                                <Eye class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <!-- Quick Cancel/Delete/Hide actions -->
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-if="canManageOrderStatus && (order.order_status === 'completed' || order.order_status === 'canceled' || order.is_hidden)"
                                                type="button"
                                                class="h-7 w-7 rounded-lg border flex items-center justify-center transition shadow-2xs"
                                                :class="order.is_hidden ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                                :title="order.is_hidden ? 'Tampilkan order kembali' : 'Sembunyikan (Hide) order dari papan status'"
                                                @click="toggleHideOrder(order)"
                                            >
                                                <component :is="order.is_hidden ? Eye : EyeOff" class="w-3.5 h-3.5" />
                                            </button>

                                            <button
                                                v-if="canManageOrderStatus && order.order_status !== 'canceled' && order.order_status !== 'completed'"
                                                type="button"
                                                class="h-7 w-7 rounded-lg border border-rose-200 bg-white flex items-center justify-center text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition shadow-2xs"
                                                title="Batalkan Pesanan"
                                                @click="cancelOrder(order)"
                                            >
                                                <XCircle class="w-3.5 h-3.5" />
                                            </button>

                                            <button
                                                v-if="canDeleteOrder && order.order_status === 'canceled'"
                                                type="button"
                                                class="h-7 w-7 rounded-lg border border-rose-300 bg-rose-50 flex items-center justify-center text-rose-600 hover:bg-rose-100 transition shadow-2xs"
                                                title="Hapus Permanen"
                                                @click="discardOrder(order)"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Empty Column State -->
                            <div
                                v-if="!ordersByStatus[col.key]?.length"
                                class="h-28 border border-dashed border-pink-200/80 rounded-lg flex flex-col items-center justify-center text-center p-3 text-pink-400"
                            >
                                <span class="text-xs font-semibold">Tidak ada order</span>
                                <span class="text-[10px] text-pink-300 mt-0.5">Tarik kartu ke sini</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLE VIEW (ALTERNATIF) -->
            <section v-else class="rounded-2xl border border-pink-200/80 bg-white p-4 sm:p-5 shadow-xs">
                <!-- Status Tabs (Semua Status + 5 Status Lainnya) -->
                <div class="mb-4 flex flex-wrap gap-2">
                    <button
                        v-for="status in orderStatusSummaryWithAll"
                        :key="`status-tab-${status.value || 'all'}`"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition"
                        :class="activeOrderStatus === status.value
                            ? 'border-pink-600 bg-pink-600 text-white shadow-xs'
                            : 'border-pink-200 bg-pink-50 text-pink-700 hover:border-pink-300 hover:bg-pink-100'"
                        @click="filterOrdersByStatus(status.value)"
                    >
                        <span>{{ status.label }}</span>
                        <span
                            class="rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                            :class="activeOrderStatus === status.value ? 'bg-white/20 text-white' : 'bg-pink-200/70 text-pink-800'"
                        >
                            {{ status.count }}
                        </span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-pink-100">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-pink-600">
                                <th class="px-3 py-2 cursor-pointer select-none hover:text-pink-800" @click="handleSort('id')">
                                    <div class="flex items-center gap-1">
                                        ID
                                        <ChevronUp v-if="sortBy === 'id' && sortDir === 'asc'" class="w-3 h-3" />
                                        <ChevronDown v-else-if="sortBy === 'id' && sortDir === 'desc'" class="w-3 h-3" />
                                        <ArrowUpDown v-else class="w-3 h-3 opacity-20" />
                                    </div>
                                </th>
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
                                        Tanggal Pengiriman
                                        <ChevronUp v-if="sortBy === 'shipping_date' && sortDir === 'asc'" class="w-3 h-3" />
                                        <ChevronDown v-else-if="sortBy === 'shipping_date' && sortDir === 'desc'" class="w-3 h-3" />
                                        <ArrowUpDown v-else class="w-3 h-3 opacity-20" />
                                    </div>
                                </th>
                                <th class="px-3 py-2">Tipe</th>
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
                                <th class="px-3 py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-100 text-sm">
                            <tr v-for="order in filteredOrdersList" :key="order.id" class="hover:bg-pink-50/40">
                                <td class="px-3 py-2 font-bold text-pink-900">#{{ order.id }}</td>
                                <td class="px-3 py-2 font-medium text-pink-950">
                                    {{ order.customer?.name ?? '-' }}
                                    <span class="block text-[11px] text-pink-700/70 font-normal">{{ order.customer?.phone_number ?? '' }}</span>
                                </td>
                                <td class="px-3 py-2 text-pink-800 text-xs">{{ formatShippingDate(order.shipping_date) }} {{ formatShippingTime(order.shipping_time) }}</td>
                                <td class="px-3 py-2 text-xs">
                                    <div class="flex items-center gap-1">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold capitalize"
                                            :class="order.shipping_type === 'delivery' ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-amber-50 text-amber-700 border border-amber-100'"
                                        >
                                            {{ order.shipping_type }}
                                        </span>
                                        <span
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                            :class="order.order_type === 'inventory' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'"
                                        >
                                            {{ order.order_type === 'inventory' ? 'Supply' : 'Bouquet' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 font-semibold text-pink-900 text-xs">{{ formatCurrency(order.total) }}</td>
                                <td class="px-3 py-2 text-pink-800 text-xs">{{ formatCurrency(order.shipping_fee ?? 0) }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-lg bg-pink-100 px-2.5 py-1 text-xs font-semibold text-pink-700">
                                        {{ formatOrderStatus(order.order_status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded-lg px-2.5 py-1 text-xs font-semibold"
                                        :class="order.payment_status === 'paid'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : order.payment_status === 'dp'
                                                ? 'bg-amber-100 text-amber-700'
                                                : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ formatPaymentStatus(order.payment_status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
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
                                        <BaseButton
                                            as="Link"
                                            :href="route('orders.edit', order.id)"
                                            variant="secondary"
                                            size="icon"
                                            class="h-8 w-8 text-blue-600 border-blue-100 hover:bg-blue-50"
                                            title="Edit Order"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </BaseButton>
                                        <button
                                            v-if="canManageOrderStatus && (order.order_status === 'completed' || order.order_status === 'canceled' || order.is_hidden)"
                                            type="button"
                                            class="h-8 w-8 rounded-lg border flex items-center justify-center transition"
                                            :class="order.is_hidden ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                            :title="order.is_hidden ? 'Tampilkan order kembali' : 'Sembunyikan (Hide) order dari papan status'"
                                            @click="toggleHideOrder(order)"
                                        >
                                            <component :is="order.is_hidden ? Eye : EyeOff" class="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-pink-200 bg-white px-3 py-1.5 text-xs font-semibold text-pink-700 hover:bg-pink-50"
                                            @click="openDetailModal(order)"
                                        >
                                            Detail
                                        </button>
                                        <button
                                            v-if="canManageOrderStatus && order.payment_status !== 'paid' && order.order_status !== 'canceled'"
                                            type="button"
                                            class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition disabled:opacity-50"
                                            :disabled="updatingPaymentOrderId === order.id"
                                            title="Tandai Lunas"
                                            @click="markOrderAsPaid(order)"
                                        >
                                            {{ updatingPaymentOrderId === order.id ? '...' : 'Lunas' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="ordersList.length === 0">
                                <td colspan="9" class="px-3 py-6 text-center text-sm text-pink-700">Belum ada order.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="orders.links && orders.links.length > 3" class="mt-6 flex flex-col items-center justify-between gap-4 border-t border-pink-100 pt-4 sm:flex-row">
                    <div class="text-xs text-pink-700">
                        Menampilkan {{ orders.from }} sampai {{ orders.to }} dari {{ orders.total }} order
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-1">
                        <template v-for="(link, k) in orders.links" :key="k">
                            <button
                                v-if="link.url === null"
                                class="rounded-lg border border-pink-100 bg-pink-50/50 px-3 py-1.5 text-xs text-pink-300"
                                v-html="link.label"
                            />
                            <button
                                v-else
                                class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                                :class="link.active
                                    ? 'border-pink-600 bg-pink-600 text-white'
                                    : 'border-pink-200 bg-white text-pink-700 hover:bg-pink-50'"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="showDetailModal" max-width="2xl" @close="closeDetailModal">
            <div v-if="selectedOrder" class="space-y-4 p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-pink-950">Detail Order #{{ selectedOrder.id }}</h3>
                        <p class="mt-1 text-xs text-pink-700">
                            {{ selectedOrder.customer?.name ?? '-' }} ({{ selectedOrder.customer?.phone_number ?? '-' }}) •
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
                        <BaseButton
                            as="Link"
                            :href="route('orders.edit', selectedOrder.id)"
                            variant="secondary"
                            size="sm"
                            class="text-blue-600 border-blue-200 hover:bg-blue-50"
                        >
                            <Pencil class="w-4 h-4" />
                            Edit Order
                        </BaseButton>
                        <BaseButton
                            v-if="canManageOrderStatus && (selectedOrder.order_status === 'completed' || selectedOrder.order_status === 'canceled' || selectedOrder.is_hidden)"
                            variant="secondary"
                            size="sm"
                            :class="selectedOrder.is_hidden ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'text-slate-600 border-slate-200 hover:bg-slate-50'"
                            @click="toggleHideOrder(selectedOrder)"
                        >
                            <component :is="selectedOrder.is_hidden ? Eye : EyeOff" class="w-4 h-4" />
                            {{ selectedOrder.is_hidden ? 'Tampilkan' : 'Hide' }}
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

                <div class="grid gap-3 sm:grid-cols-3" :class="Number(selectedOrder.discount || 0) > 0 ? 'sm:grid-cols-4' : 'sm:grid-cols-3'">
                    <div class="rounded-xl bg-pink-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-pink-600">Total</p>
                        <p class="mt-1 text-sm font-bold text-pink-950">{{ formatCurrency(selectedOrder.total) }}</p>
                    </div>
                    <div class="rounded-xl bg-pink-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-pink-600">Ongkir</p>
                        <p class="mt-1 text-sm font-bold text-pink-950">{{ formatCurrency(selectedOrder.shipping_fee ?? 0) }}</p>
                    </div>
                    <div v-if="Number(selectedOrder.discount || 0) > 0" class="rounded-xl bg-rose-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-600">Diskon</p>
                        <p class="mt-1 text-sm font-bold text-rose-700">- {{ formatCurrency(selectedOrder.discount) }}</p>
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
                                <td class="px-3 py-2 text-pink-800 font-semibold">{{ detail.item_type === 'inventory_item' ? 'Supply' : 'Bouquet' }}</td>
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
