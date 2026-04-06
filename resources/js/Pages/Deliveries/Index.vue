<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Plus, Pencil, RotateCcw, Trash2, XCircle } from 'lucide-vue-next';

const props = defineProps({
    deliveries: {
        type: Object,
        required: true,
    },
    orderOptions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const showModal = ref(false);
const editingDelivery = ref(null);
const orderSearch = ref('');
const orderOptions = ref(Array.isArray(props.orderOptions) ? [...props.orderOptions] : []);
const orderLookupLoading = ref(false);
const selectedOrderOption = ref(null);
const selectedDate = ref(props.filters?.date || '');

let orderLookupTimer = null;
let orderLookupRequestId = 0;

const columns = [
    { label: 'Order', key: 'order', sortKey: 'order_id' },
    { label: 'Recipient', key: 'recipient_name' },
    { label: 'Phone', key: 'recipient_phone' },
    { label: 'Address', key: 'full_address' },
    { label: 'Schedule', key: 'schedule', sortKey: 'shipping_date' },
];

const form = useForm({
    order_id: '',
    recipient_name: '',
    recipient_phone: '',
    full_address: '',
});

const filteredOrders = computed(() => {
    return orderOptions.value;
});

const selectedOrder = computed(() => {
    const selectedId = Number(form.order_id);
    if (!selectedId) {
        return null;
    }

    if (selectedOrderOption.value && Number(selectedOrderOption.value.id) === selectedId) {
        return selectedOrderOption.value;
    }

    return orderOptions.value.find((order) => Number(order.id) === selectedId) || null;
});

const syncSelectedOrderOption = () => {
    const selectedId = Number(form.order_id);
    if (!selectedId) {
        selectedOrderOption.value = null;

        return;
    }

    const found = orderOptions.value.find((order) => Number(order.id) === selectedId);
    if (found) {
        selectedOrderOption.value = found;
    }
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    orderSearch.value = '';
};

const openCreateModal = () => {
    editingDelivery.value = null;
    resetForm();
    orderOptions.value = [];
    selectedOrderOption.value = null;
    orderLookupLoading.value = false;
    showModal.value = true;
};

const openEditModal = (delivery) => {
    editingDelivery.value = delivery;
    form.order_id = delivery.order_id;
    form.recipient_name = delivery.recipient_name;
    form.recipient_phone = delivery.recipient_phone;
    form.full_address = delivery.full_address;
    orderSearch.value = '';
    selectedOrderOption.value = {
        id: delivery.order_id,
        customer_name: delivery.order?.customer?.name || null,
        customer_phone_number: delivery.order?.customer?.phone_number || null,
        shipping_date: delivery.order?.shipping_date || null,
        shipping_time: delivery.order?.shipping_time || null,
        shipping_type: delivery.order?.shipping_type || null,
    };
    orderOptions.value = [selectedOrderOption.value];
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingDelivery.value = null;
    resetForm();
    orderOptions.value = [];
    selectedOrderOption.value = null;
    orderLookupLoading.value = false;
};

const fetchOrderOptions = async () => {
    const search = orderSearch.value.trim();
    const requestId = ++orderLookupRequestId;

    if (search === '') {
        orderOptions.value = selectedOrderOption.value ? [selectedOrderOption.value] : [];
        orderLookupLoading.value = false;
        syncSelectedOrderOption();

        return;
    }

    orderLookupLoading.value = true;

    try {
        const url = new URL(route('deliveries.lookups.orders'), window.location.origin);
        url.searchParams.set('search', search);
        url.searchParams.set('limit', '8');

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Lookup failed with status ${response.status}`);
        }

        const payload = await response.json();
        if (requestId !== orderLookupRequestId) {
            return;
        }

        const nextOptions = Array.isArray(payload?.data) ? payload.data : [];
        if (
            selectedOrderOption.value &&
            !nextOptions.some((order) => Number(order.id) === Number(selectedOrderOption.value.id))
        ) {
            nextOptions.unshift(selectedOrderOption.value);
        }

        orderOptions.value = nextOptions;
        syncSelectedOrderOption();
    } catch {
        if (requestId !== orderLookupRequestId) {
            return;
        }

        orderOptions.value = selectedOrderOption.value ? [selectedOrderOption.value] : [];
    } finally {
        if (requestId === orderLookupRequestId) {
            orderLookupLoading.value = false;
        }
    }
};

const queueOrderLookup = () => {
    if (orderLookupTimer) {
        clearTimeout(orderLookupTimer);
    }

    orderLookupTimer = window.setTimeout(() => {
        void fetchOrderOptions();
    }, 250);
};

watch(
    () => form.order_id,
    () => {
        syncSelectedOrderOption();
    },
);

onBeforeUnmount(() => {
    if (orderLookupTimer) {
        clearTimeout(orderLookupTimer);
    }
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingDelivery.value) {
        form.put(route('deliveries.update', editingDelivery.value.id), options);
        return;
    }

    form.post(route('deliveries.store'), options);
};

const destroyDelivery = (delivery) => {
    if (!confirm(`Hapus data delivery untuk order #${delivery.order_id}?`)) {
        return;
    }

    router.delete(route('deliveries.destroy', delivery.id), {
        preserveScroll: true,
    });
};

const restoreDelivery = (delivery) => {
    router.post(route('deliveries.restore', delivery.id), {}, {
        preserveScroll: true,
    });
};

const forceDeleteDelivery = (delivery) => {
    if (!confirm(`Hapus permanen data delivery #${delivery.id}?`)) {
        return;
    }

    router.delete(route('deliveries.force-delete', delivery.id), {
        preserveScroll: true,
    });
};

const applyDateFilter = () => {
    router.get(route('deliveries.index'), {
        search: props.filters?.search || '',
        sort_by: props.filters?.sort_by || 'created_at',
        sort_dir: props.filters?.sort_dir || 'desc',
        ...(selectedDate.value ? { date: selectedDate.value } : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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

const formatSchedule = (delivery) => {
    const date = formatShippingDate(delivery.order?.shipping_date);
    const time = String(delivery.order?.shipping_time || '').split(':').slice(0, 2).join(':');

    return `${date} ${time || ''}`.trim();
};
</script>

<template>
    <AppLayout title="Deliveries">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Delivery Management
                </h2>
                <PrimaryButton class="rounded-xl flex items-center gap-2" @click="openCreateModal">
                    <Plus class="w-4 h-4" />
                    New Delivery
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <DataTable
                    :data="deliveries"
                    :columns="columns"
                    :filters="filters"
                    routeName="deliveries.index"
                    searchPlaceholder="Search recipient / phone / order / customer..."
                    :additional-params="{ date: selectedDate || '' }"
                >
                    <template #extra-filters>
                        <input
                            v-model="selectedDate"
                            type="date"
                            class="px-3 py-2 text-sm rounded-xl border border-secondary bg-white focus:ring-2 focus:ring-primary/50"
                            @change="applyDateFilter"
                        >
                    </template>

                    <template #cell-order="{ item }">
                        <div class="space-y-0.5">
                            <p class="font-semibold text-pink-950">#{{ item.order_id }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.order?.customer?.name || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-full_address="{ item }">
                        <p class="max-w-sm truncate" :title="item.full_address">{{ item.full_address }}</p>
                    </template>

                    <template #cell-schedule="{ item }">
                        <span class="text-xs text-muted-foreground">{{ formatSchedule(item) }}</span>
                    </template>

                    <template #actions="{ item }">
                        <template v-if="item.deleted_at">
                            <button
                                class="px-3 py-1.5 text-xs rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition"
                                @click="restoreDelivery(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <RotateCcw class="w-3.5 h-3.5" />
                                    Restore
                                </span>
                            </button>
                            <button
                                class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                @click="forceDeleteDelivery(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <XCircle class="w-3.5 h-3.5" />
                                    Delete Forever
                                </span>
                            </button>
                        </template>
                        <template v-else>
                            <button
                                class="px-3 py-1.5 text-xs rounded-lg bg-secondary/50 hover:bg-secondary text-foreground transition"
                                @click="openEditModal(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <Pencil class="w-3.5 h-3.5" />
                                    Edit
                                </span>
                            </button>
                            <button
                                class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                @click="destroyDelivery(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <Trash2 class="w-3.5 h-3.5" />
                                    Delete
                                </span>
                            </button>
                        </template>
                    </template>
                </DataTable>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal" max-width="2xl">
            <form class="p-6 space-y-5" @submit.prevent="submit">
                <h3 class="text-lg font-semibold text-foreground">
                    {{ editingDelivery ? 'Edit Delivery' : 'Create Delivery' }}
                </h3>

                <div class="space-y-2">
                    <InputLabel value="Order" />
                    <TextInput
                        v-model="orderSearch"
                        class="w-full"
                        placeholder="Cari order id / nama customer / no hp..."
                        @input="queueOrderLookup"
                    />
                    <p class="text-[11px] text-muted-foreground">
                        {{
                            orderLookupLoading
                                ? 'Mencari order...'
                                : orderSearch
                                    ? `Menampilkan ${filteredOrders.length} hasil teratas`
                                    : 'Ketik untuk menampilkan data order'
                        }}
                    </p>
                    <select
                        v-model="form.order_id"
                        class="w-full rounded-xl border-secondary focus:border-primary focus:ring-primary/40"
                        @change="syncSelectedOrderOption"
                    >
                        <option value="">Pilih order</option>
                        <option
                            v-for="order in filteredOrders"
                            :key="order.id"
                            :value="order.id"
                        >
                            #{{ order.id }} - {{ order.customer_name || '-' }} ({{ order.customer_phone_number || '-' }})
                        </option>
                    </select>
                    <p v-if="selectedOrder" class="text-xs text-muted-foreground">
                        Jadwal: {{ formatShippingDate(selectedOrder.shipping_date) }} {{ String(selectedOrder.shipping_time || '').split(':').slice(0, 2).join(':') }} • {{ selectedOrder.shipping_type }}
                    </p>
                    <InputError :message="form.errors.order_id" />
                </div>

                <div class="space-y-1">
                    <InputLabel value="Recipient Name" />
                    <TextInput v-model="form.recipient_name" class="w-full" />
                    <InputError :message="form.errors.recipient_name" />
                </div>

                <div class="space-y-1">
                    <InputLabel value="Recipient Phone" />
                    <TextInput v-model="form.recipient_phone" class="w-full" />
                    <InputError :message="form.errors.recipient_phone" />
                </div>

                <div class="space-y-1">
                    <InputLabel value="Full Address" />
                    <textarea
                        v-model="form.full_address"
                        rows="3"
                        class="w-full rounded-xl border-secondary focus:border-primary focus:ring-primary/40"
                    />
                    <InputError :message="form.errors.full_address" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <SecondaryButton type="button" @click="closeModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editingDelivery ? 'Update Delivery' : 'Create Delivery' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
