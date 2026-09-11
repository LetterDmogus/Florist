<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import BaseButton from '@/Components/BaseButton.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch, onMounted } from 'vue';
import { 
    Boxes, 
    ShoppingCart, 
    Trash2, 
    Package, 
    User, 
    Truck, 
    MapPin, 
    Search, 
    XCircle, 
    CreditCard, 
    Eye, 
    ChevronLeft, 
    ChevronRight,
    Plus,
    Minus,
    AlertCircle
} from 'lucide-vue-next';

const props = defineProps({
    inventoryItems: {
        type: Object,
        required: true,
    },
    itemCategories: {
        type: Array,
        default: () => [],
    },
    catalogFilters: {
        type: Object,
        default: () => ({}),
    },
    deliveryReferences: {
        type: Array,
        default: () => [],
    },
});

const LOOKUP_LIMIT = 8;

const catalogSearch = ref(props.catalogFilters?.search || '');
const selectedCategoryId = ref(props.catalogFilters?.category_id || '');
const cartItems = ref([]);
const rightPanelStep = ref(1); // 1 = Keranjang, 2 = Pembayaran & Pengiriman

// Detail Modal State
const showDetailModal = ref(false);
const selectedItemDetail = ref(null);

// Customer State
const customerMode = ref('existing');
const customerSearch = ref('');
const customerOptions = ref([]);
const customerLookupLoading = ref(false);
const selectedCustomerSnapshot = ref(null);

// Delivery State
const deliveryMode = ref('new');
const deliverySearch = ref('');
const deliveryOptions = ref([]);
const deliveryLookupLoading = ref(false);
const selectedDeliverySnapshot = ref(null);

const form = useForm({
    request_id: '',
    order_type: 'inventory',
    customer_mode: 'existing',
    customer_id: '',
    new_customer_name: '',
    new_customer_phone_number: '',
    shipping_date: new Date().toISOString().split('T')[0],
    shipping_time: new Date().toTimeString().slice(0, 5),
    shipping_type: 'pickup',
    shipping_fee: 0,
    delivery_mode: 'new',
    delivery_id: '',
    delivery_recipient_name: '',
    delivery_recipient_phone: '',
    delivery_full_address: '',
    down_payment: '',
    description: '',
    details: [],
});

const formatCurrency = (value) => {
    const amount = Number(value) || 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

const debounce = (fn, delay = 300) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

const selectedCustomer = computed(() => {
    if (!form.customer_id) return null;
    return customerOptions.value.find((c) => String(c.id) === String(form.customer_id)) ?? selectedCustomerSnapshot.value;
});

const selectedDelivery = computed(() => {
    if (!form.delivery_id) return null;
    return deliveryOptions.value.find((d) => String(d.id) === String(form.delivery_id)) ?? selectedDeliverySnapshot.value;
});

const cartTotal = computed(() => {
    return cartItems.value.reduce((total, item) => total + lineTotal(item), 0);
});

const shippingFeeAmount = computed(() => {
    if (form.shipping_type !== 'delivery') return 0;
    const value = Number(form.shipping_fee ?? 0);
    return Number.isFinite(value) && value > 0 ? value : 0;
});

const orderGrandTotal = computed(() => {
    return cartTotal.value + shippingFeeAmount.value;
});

const lineTotal = (item) => {
    const qty = Number(item.quantity) || 1;
    const price = Number(item.unit_price) || 0;
    return qty * price;
};

const addInventoryItem = (item) => {
    if (item.stock <= 0) return;

    const existing = cartItems.value.find((i) => i.inventory_item_id === item.id);
    if (existing) {
        if (existing.quantity < item.stock) {
            existing.quantity++;
        }
        return;
    }

    cartItems.value.push({
        cart_id: `inv-${Date.now()}-${item.id}`,
        item_type: 'inventory_item',
        mode: null,
        inventory_item_id: item.id,
        quantity: 1,
        max_stock: item.stock,
        display_name: item.name,
        display_category: item.category?.name ?? 'Inventory',
        unit_price: Number(item.price || 0),
        image_url: item.image_url || '',
    });
};

const removeCartItem = (cartId) => {
    cartItems.value = cartItems.value.filter((item) => item.cart_id !== cartId);
};

const applyCatalogFilters = () => {
    router.get(
        route('cashier.inventory'),
        {
            catalog_search: catalogSearch.value || undefined,
            catalog_category_id: selectedCategoryId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const goToCatalogPage = (url) => {
    if (!url) return;
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openItemDetail = (item) => {
    selectedItemDetail.value = item;
    showDetailModal.value = true;
};

const closeItemDetail = () => {
    showDetailModal.value = false;
    selectedItemDetail.value = null;
};

// Customer Lookups
const fetchCustomerOptions = async () => {
    const search = customerSearch.value.trim();
    if (search === '') {
        customerOptions.value = [];
        return;
    }
    customerLookupLoading.value = true;
    try {
        const response = await axios.get(route('orders.lookups.customers'), {
            params: { search, limit: LOOKUP_LIMIT },
        });
        customerOptions.value = response.data?.data ?? [];
    } catch (e) {
        customerOptions.value = [];
    } finally {
        customerLookupLoading.value = false;
    }
};

const selectCustomer = (customer) => {
    form.customer_id = customer.id;
    selectedCustomerSnapshot.value = customer;
    customerSearch.value = '';
    customerOptions.value = [];
};

// Delivery Lookups
const fetchDeliveryOptions = async () => {
    const search = deliverySearch.value.trim();
    if (search === '') {
        deliveryOptions.value = [];
        return;
    }
    deliveryLookupLoading.value = true;
    try {
        const response = await axios.get(route('orders.lookups.deliveries'), {
            params: { search, limit: LOOKUP_LIMIT },
        });
        deliveryOptions.value = response.data?.data ?? [];
    } catch (e) {
        deliveryOptions.value = [];
    } finally {
        deliveryLookupLoading.value = false;
    }
};

const selectDelivery = (delivery) => {
    form.delivery_id = delivery.id;
    selectedDeliverySnapshot.value = delivery;
    form.delivery_recipient_name = delivery.recipient_name;
    form.delivery_recipient_phone = delivery.recipient_phone;
    form.delivery_full_address = delivery.full_address;
    deliverySearch.value = '';
    deliveryOptions.value = [];
};

const generateRequestId = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
};

const submitOrder = () => {
    form.request_id = generateRequestId();
    form.order_type = 'inventory';
    form.customer_mode = customerMode.value;
    form.details = cartItems.value.map((item) => ({
        item_type: 'inventory_item',
        mode: null,
        quantity: item.quantity,
        inventory_item_id: item.inventory_item_id,
    }));

    form.post(route('orders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cartItems.value = [];
            form.reset();
            selectedCustomerSnapshot.value = null;
            selectedDeliverySnapshot.value = null;
            rightPanelStep.value = 1;
        }
    });
};

watch(customerSearch, debounce(() => fetchCustomerOptions(), 300));
watch(deliverySearch, debounce(() => fetchDeliveryOptions(), 300));
</script>

<template>
    <AppLayout title="POS Barang Gudang">
        <Head title="POS Barang Gudang" />

        <div class="space-y-6 max-w-[1600px] mx-auto pb-20">
            <!-- Header -->
            <div class="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-100/70 via-white to-blue-50 p-4 sm:p-5 shadow-xs">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-blue-600 rounded-xl text-white shadow-xs">
                            <Boxes class="w-5 h-5" />
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-600">Inventory POS</p>
                            <h1 class="text-xl font-bold text-blue-950">POS Barang Gudang</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <BaseButton as="Link" :href="route('cashier.index')" variant="secondary" size="sm" class="rounded-xl border border-blue-200 text-xs py-2 px-3">
                            Ke POS Bouquet
                        </BaseButton>
                        <BaseButton as="Link" :href="route('orders.status.index')" variant="secondary" size="sm" class="rounded-xl border border-blue-200 text-xs py-2 px-3">
                            Status Order
                        </BaseButton>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-12 items-start">
                <!-- Left Column: Inventory Items List -->
                <div class="lg:col-span-7 space-y-4">
                    <!-- Filters Area -->
                    <div class="bg-white rounded-2xl border border-blue-200 p-4 sm:p-5 shadow-xs min-h-[480px]">
                        <div class="flex flex-col sm:flex-row gap-2.5 mb-4">
                            <div class="relative flex-1">
                                <input 
                                    v-model="catalogSearch" 
                                    type="text" 
                                    placeholder="Cari nama barang, SKU, barcode..." 
                                    class="w-full pl-9 pr-3 py-2 rounded-xl border border-blue-200 bg-blue-50/20 text-xs focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition-all" 
                                    @keyup.enter="applyCatalogFilters"
                                >
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-blue-400" />
                            </div>
                            <select 
                                v-model="selectedCategoryId" 
                                class="rounded-xl border border-blue-200 bg-blue-50/20 text-xs py-2 px-3 focus:border-blue-400 focus:ring-1 focus:ring-blue-400" 
                                @change="applyCatalogFilters"
                            >
                                <option value="">Semua Kategori</option>
                                <option v-for="cat in itemCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <BaseButton @click="applyCatalogFilters" size="sm" class="rounded-xl py-2 px-4 text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white">Cari</BaseButton>
                        </div>

                        <!-- Inventory Items Grid -->
                        <div class="space-y-4">
                            <div v-if="!inventoryItems.data || inventoryItems.data.length === 0" class="py-16 text-center border border-dashed border-blue-200 rounded-xl bg-blue-50/20">
                                <Package class="w-10 h-10 text-blue-200 mx-auto mb-2" />
                                <p class="text-xs text-blue-600 font-bold">Tidak ada barang gudang ditemukan</p>
                                <p class="text-[11px] text-blue-400 mt-0.5">Coba ubah kata kunci pencarian atau filter kategori</p>
                            </div>

                            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div 
                                    v-for="item in inventoryItems.data" 
                                    :key="item.id" 
                                    class="group bg-white border border-blue-200/90 rounded-xl p-2.5 hover:border-blue-400 hover:shadow-sm transition-all overflow-hidden relative flex flex-col justify-between"
                                >
                                    <div>
                                        <div class="aspect-square rounded-lg bg-blue-50/60 overflow-hidden mb-2 relative">
                                            <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                            <div v-else class="w-full h-full flex items-center justify-center text-blue-200">
                                                <Package class="w-8 h-8" />
                                            </div>

                                            <!-- Hover Overlay -->
                                            <div class="absolute inset-0 bg-blue-950/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 backdrop-blur-[1px]">
                                                <button @click="openItemDetail(item)" class="p-2 bg-white rounded-lg text-blue-700 hover:scale-110 transition-transform shadow-xs" title="Lihat Detail">
                                                    <Eye class="w-4 h-4" />
                                                </button>
                                                <button 
                                                    @click="addInventoryItem(item)" 
                                                    :disabled="item.stock <= 0"
                                                    class="p-2 bg-blue-600 rounded-lg text-white hover:scale-110 transition-transform shadow-xs disabled:opacity-40 disabled:cursor-not-allowed" 
                                                    title="Tambah ke Keranjang"
                                                >
                                                    <ShoppingCart class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>

                                        <p class="text-[10px] text-blue-500 font-bold uppercase tracking-wider mb-0.5 truncate">{{ item.category?.name || 'Inventory' }}</p>
                                        <h3 class="font-bold text-blue-950 truncate text-xs">{{ item.name }}</h3>
                                        <p v-if="item.serial_number" class="text-[10px] text-gray-400 font-mono">SKU: {{ item.serial_number }}</p>
                                    </div>

                                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-blue-50">
                                        <p class="text-xs font-black text-blue-600">{{ formatCurrency(item.price) }}</p>
                                        <span 
                                            :class="['text-[10px] px-1.5 py-0.5 rounded font-bold', item.stock > 0 ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-700']"
                                        >
                                            Stok: {{ item.stock }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination Inventory -->
                            <div v-if="inventoryItems.links && inventoryItems.links.length > 3" class="flex items-center justify-center gap-1 border-t border-blue-100 pt-3">
                                <template v-for="(link, k) in inventoryItems.links" :key="k">
                                    <button
                                        v-if="link.url"
                                        @click="goToCatalogPage(link.url)"
                                        :class="['px-2.5 py-1 text-xs font-semibold rounded-lg transition-all border', link.active ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-blue-200 text-blue-600 hover:bg-blue-50']"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Cart & Payment Stepper (2 Page Next/Before) -->
                <div class="lg:col-span-5 space-y-4">
                    <section class="bg-white rounded-2xl border border-blue-200 p-5 shadow-sm">
                        <!-- Top Bar: Stepper Navigation (2 Pages) -->
                        <div class="flex items-center justify-between border-b border-blue-100 pb-3.5 mb-4">
                            <!-- Step Tabs -->
                            <div class="flex items-center gap-1.5 p-1 bg-blue-50/70 rounded-xl border border-blue-100">
                                <button 
                                    type="button"
                                    @click="rightPanelStep = 1"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg transition-all"
                                    :class="rightPanelStep === 1 
                                        ? 'bg-white text-blue-700 shadow-xs border border-blue-200' 
                                        : 'text-blue-500 hover:text-blue-800'"
                                >
                                    <ShoppingCart class="w-3.5 h-3.5" />
                                    <span>1. Keranjang</span>
                                    <span v-if="cartItems.length" class="ml-1 px-1.5 py-0.2 text-[10px] bg-blue-100 text-blue-700 rounded-full font-extrabold">
                                        {{ cartItems.length }}
                                    </span>
                                </button>
                                <button 
                                    type="button"
                                    @click="rightPanelStep = 2"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg transition-all"
                                    :class="rightPanelStep === 2 
                                        ? 'bg-white text-blue-700 shadow-xs border border-blue-200' 
                                        : 'text-blue-500 hover:text-blue-800'"
                                >
                                    <CreditCard class="w-3.5 h-3.5" />
                                    <span>2. Pembayaran</span>
                                </button>
                            </div>

                            <!-- Indicator Step Title -->
                            <span class="text-[11px] font-semibold text-blue-400">
                                Halaman {{ rightPanelStep }} dari 2
                            </span>
                        </div>

                        <!-- ================= PAGE 1: KERANJANG ================= -->
                        <div v-show="rightPanelStep === 1" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <ShoppingCart class="w-4 h-4 text-blue-600" />
                                    <h2 class="text-sm font-bold text-blue-950">Daftar Barang Dibeli</h2>
                                </div>
                                <span class="text-xs text-blue-600 font-medium">
                                    {{ cartItems.length }} item terpilih
                                </span>
                            </div>

                            <!-- Cart List -->
                            <div class="space-y-2.5 max-h-[460px] overflow-y-auto custom-scrollbar pr-1">
                                <div v-if="cartItems.length === 0" class="py-14 text-center border border-dashed border-blue-200 rounded-xl bg-blue-50/20">
                                    <Boxes class="w-10 h-10 text-blue-200 mx-auto mb-2" />
                                    <p class="text-xs text-blue-500 font-medium">Keranjang masih kosong</p>
                                    <p class="text-[11px] text-blue-400 mt-0.5">Pilih barang dari daftar di sebelah kiri</p>
                                </div>
                                
                                <div 
                                    v-for="item in cartItems" 
                                    :key="item.cart_id" 
                                    class="bg-blue-50/20 border border-blue-200/80 rounded-xl p-3 hover:border-blue-300 hover:bg-white transition-colors"
                                >
                                    <div class="flex gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-white border border-blue-100 overflow-hidden shrink-0 flex items-center justify-center">
                                            <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover">
                                            <Package v-else class="w-5 h-5 text-blue-200" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start gap-2">
                                                <h4 class="font-bold text-blue-950 truncate text-xs">{{ item.display_name }}</h4>
                                                <button 
                                                    @click="removeCartItem(item.cart_id)" 
                                                    class="text-blue-300 hover:text-red-500 transition-colors p-0.5"
                                                    title="Hapus"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                            <p class="text-[10px] text-blue-500 font-semibold uppercase tracking-wider mb-1">{{ item.display_category }}</p>
                                            
                                            <div class="flex items-center justify-between mt-1">
                                                <div class="flex items-center bg-white rounded-lg border border-blue-200 overflow-hidden">
                                                    <button 
                                                        @click="item.quantity > 1 && item.quantity--" 
                                                        class="px-2 py-0.5 hover:bg-blue-50 text-blue-600 disabled:opacity-30 text-xs font-bold"
                                                    >-</button>
                                                    <input 
                                                        v-model="item.quantity" 
                                                        type="number" 
                                                        min="1" 
                                                        :max="item.max_stock"
                                                        class="w-10 text-center border-none p-0 text-xs font-bold focus:ring-0" 
                                                    >
                                                    <button 
                                                        @click="item.quantity < item.max_stock && item.quantity++" 
                                                        class="px-2 py-0.5 hover:bg-blue-50 text-blue-600 disabled:opacity-30 text-xs font-bold"
                                                        :disabled="item.quantity >= item.max_stock"
                                                    >+</button>
                                                </div>
                                                <div class="text-right">
                                                    <p class="font-bold text-blue-900 text-xs">{{ formatCurrency(lineTotal(item)) }}</p>
                                                    <p class="text-[10px] text-blue-400">@ {{ formatCurrency(item.unit_price) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Page 1 Bottom: Cart Subtotal & Next Button -->
                            <div class="border-t border-blue-100 pt-3 space-y-3">
                                <div class="flex justify-between items-center bg-blue-50/50 p-2.5 rounded-xl border border-blue-100">
                                    <span class="text-xs font-semibold text-blue-800">Subtotal Belanja</span>
                                    <span class="text-sm font-black text-blue-900">{{ formatCurrency(cartTotal) }}</span>
                                </div>
                                <BaseButton 
                                    variant="primary" 
                                    class="w-full py-2.5 rounded-xl text-xs font-bold shadow-xs flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white"
                                    :disabled="cartItems.length === 0"
                                    @click="rightPanelStep = 2"
                                >
                                    <span>Lanjut ke Pembayaran</span>
                                    <ChevronRight class="w-4 h-4" />
                                </BaseButton>
                            </div>
                        </div>

                        <!-- ================= PAGE 2: PEMBAYARAN ================= -->
                        <div v-show="rightPanelStep === 2" class="space-y-4">
                            <!-- Customer Selection -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <User class="w-3.5 h-3.5 text-blue-600" />
                                        <h3 class="text-xs font-bold text-blue-900">Data Pembeli</h3>
                                    </div>
                                    <div class="inline-flex rounded-lg bg-blue-50 p-0.5 border border-blue-100">
                                        <button 
                                            @click="customerMode = 'existing'" 
                                            :class="['px-2.5 py-1 text-[10px] font-bold rounded-md transition-all', customerMode === 'existing' ? 'bg-white text-blue-700 shadow-xs border border-blue-200/60' : 'text-blue-500 hover:text-blue-700']"
                                        >Terdaftar</button>
                                        <button 
                                            @click="customerMode = 'new'" 
                                            :class="['px-2.5 py-1 text-[10px] font-bold rounded-md transition-all', customerMode === 'new' ? 'bg-white text-blue-700 shadow-xs border border-blue-200/60' : 'text-blue-500 hover:text-blue-700']"
                                        >Baru</button>
                                    </div>
                                </div>

                                <div v-if="customerMode === 'existing'" class="space-y-1.5 relative">
                                    <div class="relative">
                                        <input 
                                            v-model="customerSearch" 
                                            type="text" 
                                            placeholder="Ketik nama atau No HP..." 
                                            class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-blue-200 bg-white text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
                                        >
                                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-blue-300" />
                                    </div>
                                    
                                    <div v-if="customerLookupLoading" class="absolute z-10 w-full p-3 bg-white border border-blue-200 rounded-xl shadow-lg flex items-center justify-center">
                                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                    </div>

                                    <div v-if="customerOptions.length > 0" class="absolute z-10 w-full bg-white border border-blue-200 rounded-xl shadow-xl overflow-hidden max-h-52 overflow-y-auto">
                                        <button 
                                            v-for="c in customerOptions" 
                                            :key="c.id" 
                                            @click="selectCustomer(c)" 
                                            class="w-full p-2.5 text-left hover:bg-blue-50 flex items-center justify-between border-b border-blue-50 last:border-0 transition-colors"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-[10px]">{{ c.name.charAt(0) }}</div>
                                                <div>
                                                    <p class="text-xs font-bold text-blue-900">{{ c.name }}</p>
                                                    <p class="text-[10px] text-blue-500">{{ c.phone_number }}</p>
                                                </div>
                                            </div>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700">
                                                {{ c.orders_count || 0 }}x
                                            </span>
                                        </button>
                                    </div>

                                    <div v-if="selectedCustomer" class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                            <div class="w-7 h-7 rounded-full bg-emerald-600 flex items-center justify-center text-white text-xs font-bold">{{ selectedCustomer.name.charAt(0) }}</div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-bold text-emerald-950 truncate">{{ selectedCustomer.name }}</p>
                                                <p class="text-[10px] text-emerald-700 font-medium">{{ selectedCustomer.phone_number }} • {{ selectedCustomer.orders_count || 0 }}x order</p>
                                            </div>
                                        </div>
                                        <button @click="form.customer_id = ''; selectedCustomerSnapshot = null" class="text-emerald-500 hover:text-red-500 ml-2"><XCircle class="w-4 h-4" /></button>
                                    </div>
                                    <InputError :message="form.errors.customer_id" />
                                </div>

                                <div v-else class="grid grid-cols-2 gap-2 p-2.5 rounded-xl bg-blue-50/30 border border-blue-100">
                                    <div class="col-span-2">
                                        <input v-model="form.new_customer_name" type="text" placeholder="Nama Lengkap Pembeli" class="w-full px-3 py-1.5 rounded-lg border border-blue-200 text-xs focus:ring-1 focus:ring-blue-400">
                                        <InputError :message="form.errors.new_customer_name" />
                                    </div>
                                    <div class="col-span-2">
                                        <input v-model="form.new_customer_phone_number" type="text" placeholder="Nomor WhatsApp / HP" class="w-full px-3 py-1.5 rounded-lg border border-blue-200 text-xs focus:ring-1 focus:ring-blue-400">
                                        <InputError :message="form.errors.new_customer_phone_number" />
                                    </div>
                                </div>
                            </div>

                            <!-- Delivery Section -->
                            <div class="space-y-2 border-t border-blue-100 pt-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <Truck class="w-3.5 h-3.5 text-blue-600" />
                                        <h3 class="text-xs font-bold text-blue-900">Metode Penyerahan</h3>
                                    </div>
                                    <div class="inline-flex rounded-lg bg-blue-50 p-0.5 border border-blue-100">
                                        <button 
                                            @click="form.shipping_type = 'pickup'" 
                                            :class="['px-2.5 py-1 text-[10px] font-bold rounded-md transition-all', form.shipping_type === 'pickup' ? 'bg-white text-blue-700 shadow-xs border border-blue-200/60' : 'text-blue-500 hover:text-blue-700']"
                                        >Bawa Langsung / Pickup</button>
                                        <button 
                                            @click="form.shipping_type = 'delivery'" 
                                            :class="['px-2.5 py-1 text-[10px] font-bold rounded-md transition-all', form.shipping_type === 'delivery' ? 'bg-white text-blue-700 shadow-xs border border-blue-200/60' : 'text-blue-500 hover:text-blue-700']"
                                        >Kirim (Delivery)</button>
                                    </div>
                                </div>

                                <div v-if="form.shipping_type === 'delivery'" class="space-y-2">
                                    <div class="inline-flex rounded-lg bg-blue-50/80 p-0.5 border border-blue-100">
                                        <button 
                                            @click="deliveryMode = 'existing'" 
                                            :class="['px-2.5 py-0.5 text-[10px] font-semibold rounded transition-all', deliveryMode === 'existing' ? 'bg-white text-blue-700 shadow-2xs' : 'text-blue-500']"
                                        >Alamat Tersimpan</button>
                                        <button 
                                            @click="deliveryMode = 'new'" 
                                            :class="['px-2.5 py-0.5 text-[10px] font-semibold rounded transition-all', deliveryMode === 'new' ? 'bg-white text-blue-700 shadow-2xs' : 'text-blue-500']"
                                        >Alamat Baru</button>
                                    </div>

                                    <div v-if="deliveryMode === 'existing'" class="space-y-1.5 relative">
                                        <div class="relative">
                                            <input 
                                                v-model="deliverySearch" 
                                                type="text" 
                                                placeholder="Cari alamat / penerima..." 
                                                class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-blue-200 bg-white text-xs focus:ring-1 focus:ring-blue-400"
                                            >
                                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-blue-300" />
                                        </div>

                                        <div v-if="deliveryOptions.length > 0" class="absolute z-10 w-full bg-white border border-blue-200 rounded-xl shadow-xl overflow-hidden max-h-52 overflow-y-auto">
                                            <button 
                                                v-for="d in deliveryOptions" 
                                                :key="d.id" 
                                                @click="selectDelivery(d)" 
                                                class="w-full p-2.5 text-left hover:bg-blue-50 border-b border-blue-50 last:border-0 transition-colors"
                                            >
                                                <p class="text-xs font-bold text-blue-900">{{ d.recipient_name }}</p>
                                                <p class="text-[10px] text-blue-500 truncate">{{ d.full_address }}</p>
                                            </button>
                                        </div>

                                        <div v-if="selectedDeliverySnapshot && form.delivery_id" class="p-2.5 rounded-xl bg-blue-50 border border-blue-200">
                                            <p class="text-xs font-bold text-blue-900">{{ selectedDeliverySnapshot.recipient_name }}</p>
                                            <p class="text-[11px] text-blue-700">{{ selectedDeliverySnapshot.full_address }}</p>
                                        </div>
                                        <InputError :message="form.errors.delivery_id" />
                                    </div>

                                    <div v-else class="space-y-1.5 p-2.5 rounded-xl bg-blue-50/30 border border-blue-100">
                                        <input v-model="form.delivery_recipient_name" type="text" placeholder="Nama Penerima" class="w-full px-3 py-1.5 rounded-lg border border-blue-200 text-xs focus:ring-1 focus:ring-blue-400">
                                        <input v-model="form.delivery_recipient_phone" type="text" placeholder="WhatsApp Penerima" class="w-full px-3 py-1.5 rounded-lg border border-blue-200 text-xs focus:ring-1 focus:ring-blue-400">
                                        <textarea v-model="form.delivery_full_address" rows="2" placeholder="Alamat Lengkap (Jl, No, Patokan)..." class="w-full px-3 py-1.5 rounded-lg border border-blue-200 text-xs focus:ring-1 focus:ring-blue-400 resize-none"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Date, Time, Note -->
                            <div class="space-y-2 border-t border-blue-100 pt-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[10px] font-bold text-blue-800 uppercase mb-1 block">Tgl Transaksi</label>
                                        <input v-model="form.shipping_date" type="date" class="w-full px-2.5 py-1.5 rounded-xl border border-blue-200 text-xs bg-blue-50/20 focus:ring-1 focus:ring-blue-400">
                                        <InputError :message="form.errors.shipping_date" />
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-blue-800 uppercase mb-1 block">Jam</label>
                                        <input v-model="form.shipping_time" type="time" class="w-full px-2.5 py-1.5 rounded-xl border border-blue-200 text-xs bg-blue-50/20 focus:ring-1 focus:ring-blue-400">
                                        <InputError :message="form.errors.shipping_time" />
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-blue-800 uppercase mb-1 block">Catatan Transaksi</label>
                                    <input 
                                        v-model="form.description" 
                                        type="text"
                                        placeholder="Contoh: Pembelian retail toko, packing kardus..." 
                                        class="w-full px-2.5 py-1.5 rounded-xl border border-blue-200 text-xs bg-blue-50/20 focus:ring-1 focus:ring-blue-400"
                                    >
                                    <InputError :message="form.errors.description" />
                                </div>
                            </div>

                            <!-- Financial Inputs (Ongkir & DP) -->
                            <div class="space-y-2 border-t border-blue-100 pt-3">
                                <div v-if="form.shipping_type === 'delivery'" class="flex items-center justify-between gap-2">
                                    <label class="text-xs font-bold text-blue-900">Ongkos Kirim</label>
                                    <div class="relative w-32">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-blue-400 font-bold">Rp</span>
                                        <input v-model="form.shipping_fee" type="number" class="w-full pl-7 pr-2 py-1 rounded-lg border border-blue-200 text-right text-xs font-bold focus:ring-1 focus:ring-blue-400">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <label class="text-xs font-bold text-blue-900">Down Payment (DP) / Bayar Sekarang</label>
                                    <div class="relative w-32">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-blue-400 font-bold">Rp</span>
                                        <input v-model="form.down_payment" type="number" class="w-full pl-7 pr-2 py-1 rounded-lg border border-blue-200 text-right text-xs font-bold focus:ring-1 focus:ring-blue-400">
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Summary Box & Actions -->
                            <div class="bg-blue-950 rounded-xl p-4 text-white shadow-md">
                                <div class="space-y-1.5 mb-3 text-xs">
                                    <div class="flex justify-between text-blue-200">
                                        <span>Subtotal Item</span>
                                        <span class="font-bold text-white">{{ formatCurrency(cartTotal) }}</span>
                                    </div>
                                    <div v-if="shippingFeeAmount > 0" class="flex justify-between text-blue-200">
                                        <span>Ongkos Kirim</span>
                                        <span class="font-bold text-white">+ {{ formatCurrency(shippingFeeAmount) }}</span>
                                    </div>
                                    <div class="border-t border-blue-800/80 pt-2 flex justify-between items-end">
                                        <div>
                                            <p class="text-[9px] font-bold text-blue-400 uppercase tracking-wider">Total Tagihan</p>
                                            <p class="text-lg font-black text-blue-100">{{ formatCurrency(orderGrandTotal) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[9px] font-bold text-blue-400 uppercase tracking-wider">Sisa Bayar</p>
                                            <p class="text-sm font-bold text-white">{{ formatCurrency(Math.max(0, orderGrandTotal - (form.down_payment || 0))) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons: Before & Checkout -->
                                <div class="flex gap-2">
                                    <button 
                                        type="button" 
                                        @click="rightPanelStep = 1"
                                        class="px-3 py-2 rounded-xl bg-blue-900/90 hover:bg-blue-900 text-blue-200 text-xs font-bold flex items-center gap-1 transition-colors"
                                    >
                                        <ChevronLeft class="w-3.5 h-3.5" />
                                        <span>Kembali</span>
                                    </button>
                                    <BaseButton 
                                        variant="primary" 
                                        class="flex-1 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold tracking-wide border-none shadow-sm flex items-center justify-center gap-1.5" 
                                        :disabled="form.processing || cartItems.length === 0" 
                                        @click="submitOrder"
                                    >
                                        <span>{{ form.processing ? 'MEMPROSES...' : 'CHECKOUT ORDER' }}</span>
                                    </BaseButton>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <Modal :show="showDetailModal" @close="closeItemDetail" max-width="lg">
            <div v-if="selectedItemDetail" class="p-5">
                <div class="flex items-start gap-4 mb-4">
                    <div class="w-24 h-24 rounded-xl bg-blue-50 border border-blue-100 overflow-hidden shrink-0 flex items-center justify-center">
                        <img v-if="selectedItemDetail.image_url" :src="selectedItemDetail.image_url" class="w-full h-full object-cover">
                        <Package v-else class="w-10 h-10 text-blue-200" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">{{ selectedItemDetail.category?.name || 'Inventory' }}</span>
                        <h3 class="text-base font-bold text-blue-950 truncate">{{ selectedItemDetail.name }}</h3>
                        <p v-if="selectedItemDetail.serial_number" class="text-xs text-gray-500 font-mono mt-0.5">SKU: {{ selectedItemDetail.serial_number }}</p>
                        <p class="text-sm font-black text-blue-600 mt-1">{{ formatCurrency(selectedItemDetail.price) }}</p>
                        <p class="text-xs text-gray-600 mt-1">Stok Tersedia: <strong class="text-blue-900">{{ selectedItemDetail.stock }}</strong></p>
                    </div>
                </div>
                <div v-if="selectedItemDetail.description" class="p-3 bg-blue-50/50 rounded-xl border border-blue-100 text-xs text-gray-700 mb-4">
                    {{ selectedItemDetail.description }}
                </div>
                <div class="flex justify-end gap-2">
                    <BaseButton @click="closeItemDetail" variant="secondary" size="sm" class="rounded-xl text-xs py-2 px-4">
                        Tutup
                    </BaseButton>
                    <BaseButton 
                        @click="addInventoryItem(selectedItemDetail); closeItemDetail()" 
                        :disabled="selectedItemDetail.stock <= 0"
                        variant="primary" 
                        size="sm" 
                        class="rounded-xl text-xs py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white"
                    >
                        + Tambah ke Keranjang
                    </BaseButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>