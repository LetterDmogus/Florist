<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowUpRight, ArrowDownLeft, Plus, History, ShoppingBag, User, Truck, Clock } from 'lucide-vue-next';

const props = defineProps({
    movements: Object,
    items: Array,
    customers: Array,
    deliveryReferences: Array,
    filters: Object,
    typeOptions: Array,
    canCreateStockMovement: Boolean,
});

const showModal = ref(false);
const deliveryMode = ref('new');
const deliverySearch = ref('');
const itemSearch = ref('');
const itemOptions = ref([]);
const itemLookupLoading = ref(false);
const selectedItemOption = ref(null);

let itemLookupTimer = null;
let itemLookupRequestId = 0;

const form = useForm({
    item_id: '',
    type: 'in',
    quantity: '',
    price_at_the_time: '',
    description: '',
    customer_mode: 'existing',
    customer_id: '',
    new_customer_name: '',
    new_customer_phone_number: '',
    shipping_date: new Date().toISOString().split('T')[0],
    shipping_time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }),
    shipping_type: 'pickup',
    delivery_mode: 'new',
    delivery_id: '',
    delivery_recipient_name: '',
    delivery_recipient_phone: '',
    delivery_full_address: '',
    down_payment: 0,
});

const filteredDeliveries = computed(() => {
    const search = deliverySearch.value.toLowerCase().trim();
    if (!search) return props.deliveryReferences || [];

    return props.deliveryReferences.filter((delivery) => {
        return delivery.recipient_name?.toLowerCase().includes(search)
            || delivery.recipient_phone?.toLowerCase().includes(search)
            || delivery.full_address?.toLowerCase().includes(search);
    });
});

const selectedDelivery = computed(() => {
    return props.deliveryReferences.find((delivery) => String(delivery.id) === String(form.delivery_id)) ?? null;
});

watch(() => form.delivery_id, (id) => {
    if (id && deliveryMode.value === 'existing') {
        const d = props.deliveryReferences.find(item => String(item.id) === String(id));
        if (d) {
            form.delivery_recipient_name = d.recipient_name;
            form.delivery_recipient_phone = d.recipient_phone;
            form.delivery_full_address = d.full_address;
        }
    }
});

const columns = [
    { label: 'Date', key: 'created_at' },
    { label: 'Item', key: 'item', sortKey: 'item_id' },
    { label: 'Type', key: 'type' },
    { label: 'Quantity', key: 'quantity' },
    { label: 'Reference', key: 'reference', sortKey: 'order_id' },
    { label: 'Notes', key: 'description' },
];

const selectedItem = computed(() => {
    const selectedId = Number(form.item_id);
    if (!selectedId) {
        return null;
    }

    if (selectedItemOption.value && Number(selectedItemOption.value.id) === selectedId) {
        return selectedItemOption.value;
    }

    return itemOptions.value.find(item => Number(item.id) === selectedId) ?? null;
});

const syncSelectedItemOption = () => {
    const selectedId = Number(form.item_id);
    if (!selectedId) {
        selectedItemOption.value = null;

        return;
    }

    const found = itemOptions.value.find(item => Number(item.id) === selectedId);
    if (found) {
        selectedItemOption.value = found;
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

const formatDate = (date) => {
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const openCreateModal = () => {
    form.reset();
    itemSearch.value = '';
    itemOptions.value = [];
    selectedItemOption.value = null;
    itemLookupLoading.value = false;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    itemSearch.value = '';
    itemOptions.value = [];
    selectedItemOption.value = null;
    itemLookupLoading.value = false;
};

const fetchItemOptions = async () => {
    const search = itemSearch.value.trim();
    const requestId = ++itemLookupRequestId;

    if (search === '') {
        itemOptions.value = [];
        itemLookupLoading.value = false;
        syncSelectedItemOption();

        return;
    }

    itemLookupLoading.value = true;

    try {
        const url = new URL(route('stock-movements.lookups.items'), window.location.origin);
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
        if (requestId !== itemLookupRequestId) {
            return;
        }

        itemOptions.value = Array.isArray(payload?.data) ? payload.data : [];
        syncSelectedItemOption();
    } catch {
        if (requestId !== itemLookupRequestId) {
            return;
        }

        itemOptions.value = [];
    } finally {
        if (requestId === itemLookupRequestId) {
            itemLookupLoading.value = false;
        }
    }
};

const queueItemLookup = () => {
    if (itemLookupTimer) {
        clearTimeout(itemLookupTimer);
    }

    itemLookupTimer = window.setTimeout(() => {
        void fetchItemOptions();
    }, 250);
};

watch(
    () => form.item_id,
    () => {
        syncSelectedItemOption();
    },
);

onBeforeUnmount(() => {
    if (itemLookupTimer) {
        clearTimeout(itemLookupTimer);
    }
});

const submit = () => {
    form.post(route('stock-movements.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <AppLayout title="Stock Movements">
        <Head title="Stock Movements" />
        
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Stock Movements
                </h2>
                <PrimaryButton v-if="canCreateStockMovement" class="rounded-xl flex items-center gap-2" @click="openCreateModal">
                    <Plus class="w-4 h-4" />
                    New Movement
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <DataTable 
                    :data="movements" 
                    :columns="columns" 
                    :filters="filters"
                    routeName="stock-movements.index"
                    :showRecycleBin="false"
                    searchPlaceholder="Search movements..."
                >
                    <template #cell-created_at="{ item }">
                        <span class="text-xs text-muted-foreground">{{ formatDate(item.created_at) }}</span>
                    </template>

                    <template #cell-item="{ item }">
                        <div class="flex flex-col">
                            <span class="font-medium text-pink-950">{{ item.item?.name }}</span>
                            <span class="text-[10px] text-muted-foreground uppercase">{{ item.item?.serial_number }}</span>
                        </div>
                    </template>

                    <template #cell-type="{ item }">
                        <div class="flex items-center gap-1.5">
                            <div :class="[
                                'p-1 rounded-md',
                                item.type === 'in' ? 'bg-emerald-100 text-emerald-600' : 
                                item.type === 'sold' ? 'bg-blue-100 text-blue-600' : 'bg-pink-100 text-pink-600'
                            ]">
                                <ArrowUpRight v-if="item.type === 'in'" class="w-3 h-3" />
                                <ShoppingBag v-else-if="item.type === 'sold'" class="w-3 h-3" />
                                <ArrowDownLeft v-else class="w-3 h-3" />
                            </div>
                            <span :class="[
                                'text-xs font-bold uppercase tracking-wider',
                                item.type === 'in' ? 'text-emerald-700' : 
                                item.type === 'sold' ? 'text-blue-700' : 'text-pink-700'
                            ]">
                                {{ item.type }}
                            </span>
                        </div>
                    </template>

                    <template #cell-quantity="{ item }">
                        <span :class="[
                            'font-bold',
                            item.type === 'in' ? 'text-emerald-600' : 'text-pink-600'
                        ]">
                            {{ item.type === 'in' ? '+' : '-' }}{{ item.quantity }}
                        </span>
                    </template>

                    <template #cell-reference="{ item }">
                        <div v-if="item.order_id" class="flex flex-col">
                            <span class="text-xs font-bold text-blue-600">Order #{{ item.order_id }}</span>
                            <span class="text-[10px] text-muted-foreground">{{ item.order?.customer?.name }}</span>
                        </div>
                        <span v-else class="text-xs font-mono bg-secondary/50 px-2 py-1 rounded border border-secondary text-muted-foreground">
                            {{ item.reference_type || 'Manual' }}
                        </span>
                    </template>

                    <template #actions="{ item }">
                        <BaseButton v-if="item.order_id" as="Link" :href="route('orders.print', item.order_id)" variant="ghost" size="icon" class="h-8 w-8">
                            <History class="w-4 h-4" />
                        </BaseButton>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showModal" @close="closeModal" max-width="3xl">
            <form @submit.prevent="submit" class="p-8 space-y-8">
                <div class="flex items-center gap-3 border-b border-pink-100 pb-4">
                    <div class="p-3 bg-pink-50 rounded-2xl text-pink-600">
                        <Plus class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-pink-950">Catat Pergerakan Stok</h3>
                        <p class="text-sm text-muted-foreground">Tambah, kurangi, atau catat penjualan item inventory.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Left Column: Item & Basic Info -->
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <InputLabel for="item_id" value="Pilih Item" />
                            <TextInput
                                v-model="itemSearch"
                                type="text"
                                class="w-full"
                                placeholder="Ketik nama / serial item..."
                                @input="queueItemLookup"
                            />
                            <p class="text-[11px] text-muted-foreground">
                                {{
                                    itemLookupLoading
                                        ? 'Mencari item...'
                                        : itemSearch
                                            ? `Menampilkan ${itemOptions.length} hasil teratas`
                                            : 'Ketik untuk menampilkan data item'
                                }}
                            </p>
                            <select v-model="form.item_id" id="item_id" class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300" @change="syncSelectedItemOption">
                                <option value="" disabled>Pilih item dari hasil pencarian</option>
                                <option v-for="item in itemOptions" :key="item.id" :value="item.id">
                                    {{ item.name }} (Stok: {{ item.stock }})
                                </option>
                            </select>
                            <InputError :message="form.errors.item_id" />
                        </div>

                        <div class="space-y-2">
                            <InputLabel for="type" value="Tipe Pergerakan" />
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <button 
                                    v-for="opt in typeOptions" 
                                    :key="opt.value"
                                    type="button"
                                    @click="form.type = opt.value"
                                    class="px-3 py-2 text-xs font-bold rounded-xl border transition-all"
                                    :class="form.type === opt.value ? 'bg-pink-600 border-pink-600 text-white shadow-md' : 'bg-white border-pink-100 text-pink-700 hover:bg-pink-50'"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.type" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <InputLabel for="quantity" value="Jumlah Qty" />
                                <TextInput v-model="form.quantity" id="quantity" type="number" min="1" class="w-full" placeholder="0" />
                                <InputError :message="form.errors.quantity" />
                            </div>
                            <div v-if="form.type === 'in'" class="space-y-2">
                                <InputLabel for="price" value="Harga Modal (IDR)" />
                                <TextInput v-model="form.price_at_the_time" id="price" type="number" class="w-full" placeholder="0" />
                                <InputError :message="form.errors.price_at_the_time" />
                            </div>
                            <div v-else class="space-y-2">
                                <InputLabel value="Estimasi Total" />
                                <div class="px-4 py-2.5 bg-pink-50/50 rounded-xl border border-pink-100 text-sm font-bold text-pink-900">
                                    {{ formatCurrency((selectedItem?.price || 0) * (form.quantity || 0)) }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <InputLabel for="description" value="Catatan / Keterangan" />
                            <textarea v-model="form.description" id="description" rows="3" class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300" placeholder="Contoh: Pembelian stok baru, rusak saat pengiriman..."></textarea>
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <!-- Right Column: Order Info (Only for SOLD) -->
                    <div v-if="form.type === 'sold'" class="space-y-6 bg-blue-50/30 p-6 rounded-[2rem] border border-blue-100 animate-in fade-in slide-in-from-right-4">
                        <div class="flex items-center gap-2 text-blue-700 mb-2">
                            <ShoppingBag class="w-5 h-5" />
                            <h4 class="font-bold">Informasi Penjualan</h4>
                        </div>

                        <div class="space-y-4">
                            <!-- Customer Mode Toggle -->
                            <div class="inline-flex rounded-xl border border-blue-200 bg-white p-1">
                                <BaseButton 
                                    type="button"
                                    :variant="form.customer_mode === 'existing' ? 'primary' : 'ghost'"
                                    size="sm"
                                    @click="form.customer_mode = 'existing'"
                                    class="!text-[10px]"
                                >
                                    Customer Lama
                                </BaseButton>
                                <BaseButton 
                                    type="button"
                                    :variant="form.customer_mode === 'new' ? 'primary' : 'ghost'"
                                    size="sm"
                                    @click="form.customer_mode = 'new'"
                                    class="!text-[10px]"
                                >
                                    Baru
                                </BaseButton>
                            </div>

                            <!-- Existing Customer -->
                            <div v-if="form.customer_mode === 'existing'" class="space-y-2">
                                <InputLabel value="Pilih Customer" />
                                <select v-model="form.customer_id" class="w-full rounded-xl border-blue-200 text-sm focus:border-blue-400 focus:ring-blue-300">
                                    <option value="" disabled>Cari nama customer...</option>
                                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.phone_number }})</option>
                                </select>
                                <InputError :message="form.errors.customer_id" />
                            </div>

                            <!-- New Customer -->
                            <div v-else class="grid grid-cols-1 gap-3">
                                <div class="space-y-1">
                                    <InputLabel value="Nama Lengkap" />
                                    <TextInput v-model="form.new_customer_name" class="w-full border-blue-200" />
                                    <InputError :message="form.errors.new_customer_name" />
                                </div>
                                <div class="space-y-1">
                                    <InputLabel value="No. WhatsApp" />
                                    <TextInput v-model="form.new_customer_phone_number" class="w-full border-blue-200" placeholder="08xxx" />
                                    <InputError :message="form.errors.new_customer_phone_number" />
                                </div>
                            </div>

                            <div class="border-t border-blue-100 my-2"></div>

                            <!-- Shipping Info -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <InputLabel value="Tgl Ambil/Kirim" />
                                    <TextInput v-model="form.shipping_date" type="date" class="w-full border-blue-200" />
                                </div>
                                <div class="space-y-1">
                                    <InputLabel value="Jam" />
                                    <TextInput v-model="form.shipping_time" type="time" class="w-full border-blue-200" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <InputLabel value="Metode Pengambilan" />
                                <div class="flex gap-2">
                                    <BaseButton 
                                        type="button"
                                        v-for="st in ['pickup', 'delivery']" 
                                        :key="st"
                                        :variant="form.shipping_type === st ? 'primary' : 'secondary'"
                                        size="sm"
                                        @click="form.shipping_type = st"
                                        class="flex-1 capitalize"
                                    >
                                        <Truck v-if="st === 'delivery'" class="w-3 h-3" />
                                        {{ st }}
                                    </BaseButton>
                                </div>
                            </div>

                            <!-- Delivery Details -->
                            <div v-if="form.shipping_type === 'delivery'" class="space-y-3 rounded-2xl border border-blue-200 bg-white p-3">
                                <div class="inline-flex rounded-xl border border-blue-100 bg-blue-50 p-1">
                                    <BaseButton 
                                        type="button"
                                        :variant="deliveryMode === 'new' ? 'primary' : 'ghost'"
                                        size="sm"
                                        @click="deliveryMode = 'new'; form.delivery_mode = 'new'"
                                        class="!text-[10px]"
                                    >
                                        Penerima Baru
                                    </BaseButton>
                                    <BaseButton 
                                        type="button"
                                        :variant="deliveryMode === 'existing' ? 'primary' : 'ghost'"
                                        size="sm"
                                        @click="deliveryMode = 'existing'; form.delivery_mode = 'existing'"
                                        class="!text-[10px]"
                                    >
                                        Alamat Terdaftar
                                    </BaseButton>
                                </div>

                                <div v-if="deliveryMode === 'existing'" class="space-y-2">
                                    <input v-model="deliverySearch" type="text" placeholder="Cari alamat/penerima..." class="w-full rounded-xl border-blue-100 text-[10px] focus:ring-blue-300">
                                    <select v-model="form.delivery_id" class="w-full rounded-xl border-blue-200 text-xs">
                                        <option value="">Pilih alamat...</option>
                                        <option v-for="d in filteredDeliveries" :key="d.id" :value="d.id">
                                            {{ d.recipient_name }} - {{ d.full_address }}
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <TextInput v-model="form.delivery_recipient_name" placeholder="Nama Penerima" class="w-full text-xs border-blue-100" :disabled="deliveryMode === 'existing'" />
                                    <TextInput v-model="form.delivery_recipient_phone" placeholder="No HP Penerima" class="w-full text-xs border-blue-100" :disabled="deliveryMode === 'existing'" />
                                    <textarea v-model="form.delivery_full_address" placeholder="Alamat Lengkap" rows="2" class="w-full rounded-xl border-blue-100 text-xs focus:ring-blue-300" :disabled="deliveryMode === 'existing'"></textarea>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <InputLabel value="Down Payment / Bayar (IDR)" />
                                <TextInput v-model="form.down_payment" type="number" class="w-full border-blue-200 font-bold text-blue-700" />
                                <InputError :message="form.errors.down_payment" />
                            </div>
                        </div>
                    </div>

                    <!-- Placeholder for other types to keep layout balanced -->
                    <div v-else class="hidden md:flex flex-col items-center justify-center bg-gray-50 rounded-[2rem] border border-dashed border-gray-200 p-8 text-center text-muted-foreground">
                        <History class="w-12 h-12 mb-4 opacity-20" />
                        <p class="text-sm">Informasi tambahan akan muncul jika memilih tipe <b>Jual</b>.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-pink-100">
                    <SecondaryButton @click="closeModal" type="button">Batal</SecondaryButton>
                    <BaseButton variant="success" size="lg" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pergerakan' }}
                    </BaseButton>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
