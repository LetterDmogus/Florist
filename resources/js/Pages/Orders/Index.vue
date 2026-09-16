<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import BaseButton from '@/Components/BaseButton.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch, onMounted } from 'vue';
import { ShoppingBag, ShoppingCart, Trash2, WandSparkles, User, Truck, MapPin, Search, XCircle, MessageSquare, UserPlus, CreditCard, Eye, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    bouquetUnits: {
        type: Object,
        required: true,
    },
    inventoryItems: {
        type: Object,
        default: () => ({ data: [] }),
    },
    bouquetCategories: {
        type: Array,
        required: true,
    },
    catalogFilters: {
        type: Object,
        default: () => ({}),
    },
    deliveryReferences: {
        type: Array,
        default: () => [],
    },
    canCustomBouquet: {
        type: Boolean,
        default: false,
    },
});

const LOOKUP_LIMIT = 8;

const catalogMode = ref('catalog'); // 'catalog', 'custom', 'inventory'
const catalogSearch = ref(props.catalogFilters?.search || '');
const selectedCategoryId = ref(props.catalogFilters?.category_id || '');
const cartItems = ref([]);
const customFormError = ref('');
const rightPanelStep = ref(1); // 1 = Keranjang (Cart), 2 = Pembayaran & Pengiriman (Payment)

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

const customDraft = ref({
    custom_category_id: '',
    custom_name: '',
    custom_serial_number: '',
    custom_price: '',
    custom_note: '',
    greeting_card: '',
    sender_name: '',
    money_amount: '',
    custom_image: null,
});

const customImagePreview = ref(null);

const onCustomImageChange = (e) => {
    const file = e.target.files[0];
    customDraft.value.custom_image = file;
    customImagePreview.value = file ? URL.createObjectURL(file) : null;
};

const form = useForm({
    request_id: '',
    order_type: 'bouquet',
    customer_mode: 'existing',
    customer_id: '',
    new_customer_name: '',
    new_customer_phone_number: '',
    shipping_date: new Date().toISOString().split('T')[0],
    shipping_time: new Date().toTimeString().slice(0, 5),
    shipping_type: 'pickup',
    shipping_fee: 0,
    discount: 0,
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

const discountAmount = computed(() => {
    const value = Number(form.discount ?? 0);
    return Number.isFinite(value) && value > 0 ? value : 0;
});

const orderGrandTotal = computed(() => Math.max(0, cartTotal.value + shippingFeeAmount.value - discountAmount.value));

const lineTotal = (item) => {
    const price = Number(item.unit_price || 0);
    const money = Number(item.money_amount || 0);
    return (price + money) * (item.quantity || 1);
};

// Handlers
const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const openItemDetail = (item, type) => {
    selectedItemDetail.value = { ...item, _detail_type: type };
    showDetailModal.value = true;
};

const closeItemDetail = () => {
    showDetailModal.value = false;
    selectedItemDetail.value = null;
};

const addCatalogItem = (unit) => {
    cartItems.value.push({
        cart_id: `catalog-${Date.now()}-${unit.id}`,
        item_type: 'bouquet',
        mode: 'catalog',
        bouquet_unit_id: unit.id,
        quantity: 1,
        greeting_card: '',
        sender_name: '',
        display_name: unit.name,
        display_category: unit.type?.category?.name ?? '-',
        unit_price: Number(unit.price || 0),
        money_amount: '',
        image_url: unit.image_url || '',
    });
};

const addInventoryItem = (item) => {
    const existing = cartItems.value.find((i) => i.item_type === 'inventory_item' && i.inventory_item_id === item.id);
    if (existing) {
        existing.quantity++;
        return;
    }

    cartItems.value.push({
        cart_id: `inv-${Date.now()}-${item.id}`,
        item_type: 'inventory_item',
        mode: null,
        inventory_item_id: item.id,
        quantity: 1,
        display_name: item.name,
        display_category: item.category?.name ?? 'Inventory',
        unit_price: Number(item.price || 0),
        image_url: item.image_url || '',
    });
};

const addCustomItem = () => {
    customFormError.value = '';
    if (!customDraft.value.custom_category_id || !customDraft.value.custom_name || !customDraft.value.custom_price) {
        customFormError.value = 'Kategori, nama bouquet, dan harga wajib diisi.';
        return;
    }

    const category = props.bouquetCategories.find((item) => String(item.id) === String(customDraft.value.custom_category_id));

    cartItems.value.push({
        cart_id: `custom-${Date.now()}`,
        item_type: 'bouquet',
        mode: 'custom',
        custom_category_id: Number(customDraft.value.custom_category_id),
        custom_name: customDraft.value.custom_name.trim(),
        custom_serial_number: customDraft.value.custom_serial_number?.trim() || null,
        custom_price: Number(customDraft.value.custom_price),
        custom_note: customDraft.value.custom_note?.trim() || null,
        quantity: 1,
        greeting_card: customDraft.value.greeting_card?.trim() || '',
        sender_name: customDraft.value.sender_name?.trim() || '',
        money_amount: customDraft.value.money_amount ? Number(customDraft.value.money_amount) : null,
        custom_image: customDraft.value.custom_image,
        image_preview: customImagePreview.value,
        display_name: customDraft.value.custom_name.trim(),
        display_category: category?.name ?? 'Custom',
        unit_price: Number(customDraft.value.custom_price),
        image_url: customImagePreview.value || '',
    });

    customDraft.value = {
        custom_category_id: '', custom_name: '', custom_serial_number: '', custom_price: '',
        custom_note: '', greeting_card: '', sender_name: '', money_amount: '', custom_image: null
    };
    customImagePreview.value = null;
};

const removeCartItem = (cartId) => {
    cartItems.value = cartItems.value.filter((item) => item.cart_id !== cartId);
};

const applyCatalogFilters = () => {
    router.get(route('cashier.index'), {
        catalog_search: catalogSearch.value || '',
        catalog_category_id: selectedCategoryId.value || '',
        bouquet_page: 1,
        inventory_page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['bouquetUnits', 'inventoryItems', 'catalogFilters'],
    });
};

const goToCatalogPage = (url, type) => {
    if (!url) return;
    router.get(url, {
        catalog_search: catalogSearch.value || '',
        catalog_category_id: selectedCategoryId.value || '',
    }, {
        preserveState: true,
        preserveScroll: true,
        only: [type === 'bouquet' ? 'bouquetUnits' : 'inventoryItems'],
    });
};

const fetchCustomerOptions = async () => {
    if (customerMode.value !== 'existing') return;
    const search = customerSearch.value.trim();
    if (!search) { customerOptions.value = []; return; }
    customerLookupLoading.value = true;
    try {
        const { data } = await axios.get(route('orders.lookups.customers'), { params: { search, limit: LOOKUP_LIMIT } });
        customerOptions.value = Array.isArray(data?.data) ? data.data : [];
    } finally { customerLookupLoading.value = false; }
};

const fetchDeliveryOptions = async () => {
    if (deliveryMode.value !== 'existing') return;
    const search = deliverySearch.value.trim();
    if (!search) { deliveryOptions.value = []; return; }
    deliveryLookupLoading.value = true;
    try {
        const { data } = await axios.get(route('orders.lookups.deliveries'), { params: { search, limit: LOOKUP_LIMIT } });
        deliveryOptions.value = Array.isArray(data?.data) ? data.data : [];
    } finally { deliveryLookupLoading.value = false; }
};

const selectCustomer = (customer) => {
    form.customer_id = customer.id;
    selectedCustomerSnapshot.value = customer;
    customerSearch.value = '';
    customerOptions.value = [];
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

const submitOrder = () => {
    form.customer_mode = customerMode.value;
    form.details = cartItems.value.map((item) => ({
        item_type: item.item_type,
        mode: item.mode,
        quantity: item.quantity,
        bouquet_unit_id: item.bouquet_unit_id,
        inventory_item_id: item.inventory_item_id || null,
        unit_price: Number(item.unit_price || 0),
        price: Number(item.unit_price || 0),
        money_bouquet: item.money_amount ? Number(item.money_amount) : null,
        greeting_card: item.greeting_card || null,
        sender_name: item.sender_name || null,
        custom_category_id: item.custom_category_id || null,
        custom_name: item.custom_name || null,
        custom_serial_number: item.custom_serial_number || null,
        custom_price: item.custom_price || null,
        custom_note: item.custom_note || null,
        custom_image: item.custom_image || null,
        money_amount: item.money_amount || null,
    }));

    form.post(route('orders.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            cartItems.value = [];
            form.reset();
            selectedCustomerSnapshot.value = null;
            selectedDeliverySnapshot.value = null;
        }
    });
};

watch(customerSearch, debounce(() => fetchCustomerOptions(), 300));
watch(deliverySearch, debounce(() => fetchDeliveryOptions(), 300));
</script>

<template>
    <AppLayout title="POS Bouquet">
        <Head title="POS Bouquet" />

        <div class="space-y-6 max-w-[1600px] mx-auto pb-20">
            <!-- Header -->
            <div class="rounded-2xl border border-pink-200 bg-gradient-to-r from-pink-100/70 via-white to-pink-50 p-4 sm:p-5 shadow-xs">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-pink-600 rounded-xl text-white shadow-xs">
                            <ShoppingCart class="w-5 h-5" />
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-pink-600">Florist POS</p>
                            <h1 class="text-xl font-bold text-pink-950">POS Bouquet</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <BaseButton as="Link" :href="route('cashier.inventory')" variant="secondary" size="sm" class="rounded-xl border border-pink-200 text-xs py-2 px-3">
                            Ke POS Gudang
                        </BaseButton>
                        <BaseButton as="Link" :href="route('orders.status.index')" variant="secondary" size="sm" class="rounded-xl border border-pink-200 text-xs py-2 px-3">
                            Status Order
                        </BaseButton>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-12 items-start">
                <!-- Left Column: Catalog & Items -->
                <div class="lg:col-span-7 space-y-4">
                    <!-- Tab Switcher -->
                    <section class="rounded-xl border border-pink-200 bg-white p-1 shadow-xs inline-flex gap-1">
                        <button @click="catalogMode = 'catalog'" :class="['px-4 py-1.5 text-xs font-bold rounded-lg transition-all', catalogMode === 'catalog' ? 'bg-pink-600 text-white shadow-xs' : 'text-pink-700 hover:bg-pink-50']">
                            <ShoppingBag class="w-3.5 h-3.5 inline mr-1.5" /> Bouquet
                        </button>
                        <button v-if="canCustomBouquet" @click="catalogMode = 'custom'" :class="['px-4 py-1.5 text-xs font-bold rounded-lg transition-all', catalogMode === 'custom' ? 'bg-pink-600 text-white shadow-xs' : 'text-pink-700 hover:bg-pink-50']">
                            <WandSparkles class="w-3.5 h-3.5 inline mr-1.5" /> Custom
                        </button>
                    </section>

                    <!-- Catalog/Inventory Area -->
                    <div v-if="catalogMode !== 'custom'" class="bg-white rounded-2xl border border-pink-200 p-4 sm:p-5 shadow-xs min-h-[480px]">
                        <div class="flex flex-col sm:flex-row gap-2.5 mb-4">
                            <div class="relative flex-1">
                                <input v-model="catalogSearch" type="text" placeholder="Cari nama atau kode buket..." class="w-full pl-9 pr-3 py-2 rounded-xl border border-pink-200 bg-pink-50/20 text-xs focus:border-pink-400 focus:ring-1 focus:ring-pink-400 transition-all" @keyup.enter="applyCatalogFilters">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-pink-400" />
                            </div>
                            <select v-model="selectedCategoryId" class="rounded-xl border border-pink-200 bg-pink-50/20 text-xs py-2 px-3 focus:border-pink-400 focus:ring-1 focus:ring-pink-400" @change="applyCatalogFilters">
                                <option value="">Semua Kategori</option>
                                <option v-for="cat in bouquetCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <BaseButton @click="applyCatalogFilters" size="sm" class="rounded-xl py-2 px-4 text-xs font-bold">Cari</BaseButton>
                        </div>

                        <!-- Bouquet Catalog -->
                        <div v-if="catalogMode === 'catalog'" class="space-y-4">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div v-for="unit in bouquetUnits.data" :key="unit.id" class="group relative bg-white border border-pink-200/90 rounded-xl p-2.5 hover:border-pink-400 hover:shadow-sm transition-all overflow-hidden">
                                    <div class="aspect-square rounded-lg bg-pink-50/60 overflow-hidden mb-2 relative">
                                        <img v-if="unit.image_url" :src="unit.image_url" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <ShoppingBag class="w-8 h-8 text-pink-200" />
                                        </div>
                                        
                                        <!-- Hover Overlay -->
                                        <div class="absolute inset-0 bg-pink-950/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 backdrop-blur-[1px]">
                                            <button @click="openItemDetail(unit, 'bouquet')" class="p-2 bg-white rounded-lg text-pink-700 hover:scale-110 transition-transform shadow-xs" title="Lihat Detail">
                                                <Eye class="w-4 h-4" />
                                            </button>
                                            <button @click="addCatalogItem(unit)" class="p-2 bg-pink-600 rounded-lg text-white hover:scale-110 transition-transform shadow-xs" title="Tambah ke Keranjang">
                                                <ShoppingCart class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-pink-500 font-bold uppercase tracking-wider mb-0.5 truncate">{{ unit.type?.category?.name }}</p>
                                    <h3 class="font-bold text-pink-950 truncate text-xs">{{ unit.name }}</h3>
                                    <p class="text-xs font-black text-pink-600 mt-0.5">{{ formatCurrency(unit.price) }}</p>
                                </div>
                            </div>

                            <!-- Pagination Bouquet -->
                            <div v-if="bouquetUnits.links && bouquetUnits.links.length > 3" class="flex items-center justify-center gap-1 border-t border-pink-100 pt-3">
                                <template v-for="(link, k) in bouquetUnits.links" :key="k">
                                    <button
                                        v-if="link.url"
                                        @click="goToCatalogPage(link.url, 'bouquet')"
                                        :class="['px-2.5 py-1 text-xs font-semibold rounded-lg transition-all border', link.active ? 'bg-pink-600 border-pink-600 text-white' : 'bg-white border-pink-200 text-pink-600 hover:bg-pink-50']"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Form Area -->
                    <div v-else class="bg-white rounded-2xl border border-pink-200 p-5 sm:p-6 shadow-xs">
                        <div class="flex items-center gap-2 mb-4">
                            <WandSparkles class="w-5 h-5 text-pink-600" />
                            <h2 class="text-base font-bold text-pink-950">Buat Bouquet Custom</h2>
                        </div>
                        
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-[11px] font-bold text-pink-800 uppercase tracking-wider mb-1 block">Kategori</label>
                                    <select v-model="customDraft.custom_category_id" class="w-full rounded-xl border border-pink-200 bg-pink-50/20 py-2 px-3 text-xs focus:ring-1 focus:ring-pink-400">
                                        <option value="">Pilih Kategori</option>
                                        <option v-for="cat in bouquetCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-pink-800 uppercase tracking-wider mb-1 block">Nama Bouquet</label>
                                    <input v-model="customDraft.custom_name" type="text" placeholder="Buket Spesial..." class="w-full rounded-xl border border-pink-200 bg-pink-50/20 py-2 px-3 text-xs focus:ring-1 focus:ring-pink-400">
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-pink-800 uppercase tracking-wider mb-1 block">Harga Jual Jasa</label>
                                    <input v-model="customDraft.custom_price" type="number" placeholder="Contoh: 150000" class="w-full rounded-xl border border-pink-200 bg-pink-50/20 py-2 px-3 text-xs font-bold focus:ring-1 focus:ring-pink-400">
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-pink-800 uppercase tracking-wider mb-1 block">Uang di Buket (Money Bouquet)</label>
                                    <input v-model="customDraft.money_amount" type="number" placeholder="Rp 0" class="w-full rounded-xl border border-pink-200 bg-pink-50/20 py-2 px-3 text-xs font-bold text-emerald-600 focus:ring-1 focus:ring-pink-400">
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-[11px] font-bold text-pink-800 uppercase tracking-wider mb-1 block">Foto Referensi (Opsional)</label>
                                    <div class="relative aspect-video rounded-xl border border-dashed border-pink-200 bg-pink-50/20 overflow-hidden group">
                                        <img v-if="customImagePreview" :src="customImagePreview" class="w-full h-full object-cover">
                                        <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-pink-400">
                                            <WandSparkles class="w-6 h-6 mb-1" />
                                            <span class="text-[11px] font-bold uppercase tracking-wider">Upload Foto</span>
                                        </div>
                                        <input type="file" @change="onCustomImageChange" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-pink-800 uppercase tracking-wider mb-1 block">Catatan Produksi</label>
                                    <textarea v-model="customDraft.custom_note" rows="2" placeholder="Warna kertas, pita, dll..." class="w-full rounded-xl border border-pink-200 bg-pink-50/20 text-xs focus:ring-1 focus:ring-pink-400"></textarea>
                                </div>
                            </div>
                        </div>
                        <div v-if="customFormError" class="mt-3 p-2.5 bg-red-50 text-red-600 text-xs rounded-xl font-medium border border-red-200">{{ customFormError }}</div>
                        <BaseButton variant="primary" class="w-full mt-4 py-2.5 rounded-xl text-xs font-bold shadow-xs" @click="addCustomItem">
                            Tambahkan ke Keranjang
                        </BaseButton>
                    </div>
                </div>

                <!-- Right Column: Cart & Payment Stepper (2 Page Next/Before) -->
                <div class="lg:col-span-5 space-y-4">
                    <section class="bg-white rounded-2xl border border-pink-200 p-5 shadow-sm">
                        <!-- Top Bar: Stepper Navigation (2 Pages) -->
                        <div class="flex items-center justify-between border-b border-pink-100 pb-3.5 mb-4">
                            <!-- Step Tabs -->
                            <div class="flex items-center gap-1.5 p-1 bg-pink-50/70 rounded-xl border border-pink-100">
                                <button 
                                    type="button"
                                    @click="rightPanelStep = 1"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg transition-all"
                                    :class="rightPanelStep === 1 
                                        ? 'bg-white text-pink-700 shadow-xs border border-pink-200' 
                                        : 'text-pink-500 hover:text-pink-800'"
                                >
                                    <ShoppingCart class="w-3.5 h-3.5" />
                                    <span>1. Keranjang</span>
                                    <span v-if="cartItems.length" class="ml-1 px-1.5 py-0.2 text-[10px] bg-pink-100 text-pink-700 rounded-full font-extrabold">
                                        {{ cartItems.length }}
                                    </span>
                                </button>
                                <button 
                                    type="button"
                                    @click="rightPanelStep = 2"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg transition-all"
                                    :class="rightPanelStep === 2 
                                        ? 'bg-white text-pink-700 shadow-xs border border-pink-200' 
                                        : 'text-pink-500 hover:text-pink-800'"
                                >
                                    <CreditCard class="w-3.5 h-3.5" />
                                    <span>2. Pembayaran</span>
                                </button>
                            </div>

                            <!-- Indicator Step Title -->
                            <span class="text-[11px] font-semibold text-pink-400">
                                Halaman {{ rightPanelStep }} dari 2
                            </span>
                        </div>

                        <!-- ================= PAGE 1: KERANJANG ================= -->
                        <div v-show="rightPanelStep === 1" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <ShoppingCart class="w-4 h-4 text-pink-600" />
                                    <h2 class="text-sm font-bold text-pink-950">Daftar Belanja</h2>
                                </div>
                                <span class="text-xs text-pink-600 font-medium">
                                    {{ cartItems.length }} item terpilih
                                </span>
                            </div>

                            <!-- Cart List -->
                            <div class="space-y-2.5 max-h-[460px] overflow-y-auto custom-scrollbar pr-1">
                                <div v-if="cartItems.length === 0" class="py-14 text-center border border-dashed border-pink-200 rounded-xl bg-pink-50/20">
                                    <ShoppingBag class="w-10 h-10 text-pink-200 mx-auto mb-2" />
                                    <p class="text-xs text-pink-500 font-medium">Keranjang masih kosong</p>
                                    <p class="text-[11px] text-pink-400 mt-0.5">Pilih produk dari katalog di sebelah kiri</p>
                                </div>
                                
                                <div 
                                    v-for="item in cartItems" 
                                    :key="item.cart_id" 
                                    class="bg-pink-50/20 border border-pink-200/80 rounded-xl p-3 hover:border-pink-300 hover:bg-white transition-colors"
                                >
                                    <div class="flex gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-white border border-pink-100 overflow-hidden shrink-0 flex items-center justify-center">
                                            <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover">
                                            <ShoppingBag v-else class="w-5 h-5 text-pink-200" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start gap-2">
                                                <h4 class="font-bold text-pink-950 truncate text-xs">{{ item.display_name }}</h4>
                                                <button 
                                                    @click="removeCartItem(item.cart_id)" 
                                                    class="text-pink-300 hover:text-red-500 transition-colors p-0.5"
                                                    title="Hapus"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                            <p class="text-[10px] text-pink-500 font-semibold uppercase tracking-wider mb-1">{{ item.display_category }}</p>
                                            
                                            <div v-if="item.money_amount" class="mb-1.5">
                                                <span class="text-[10px] bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded font-bold border border-emerald-200">
                                                    + Uang: {{ formatCurrency(item.money_amount) }}
                                                </span>
                                            </div>
                                            
                                            <div class="flex items-center justify-between mt-1">
                                                <div class="flex items-center bg-white rounded-lg border border-pink-200 overflow-hidden">
                                                    <button 
                                                        @click="item.quantity > 1 && item.quantity--" 
                                                        class="px-2 py-0.5 hover:bg-pink-50 text-pink-600 disabled:opacity-30 text-xs font-bold" 
                                                        :disabled="item.item_type === 'bouquet'"
                                                    >-</button>
                                                    <input 
                                                        v-model="item.quantity" 
                                                        type="number" 
                                                        min="1" 
                                                        class="w-8 text-center border-none p-0 text-xs font-bold focus:ring-0" 
                                                        :disabled="item.item_type === 'bouquet'"
                                                    >
                                                    <button 
                                                        @click="item.quantity++" 
                                                        class="px-2 py-0.5 hover:bg-pink-50 text-pink-600 disabled:opacity-30 text-xs font-bold" 
                                                        :disabled="item.item_type === 'bouquet'"
                                                    >+</button>
                                                </div>
                                                <p class="font-bold text-pink-900 text-xs">{{ formatCurrency(lineTotal(item)) }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Price / Jasa, Greeting, Sender & Money (Bouquet) -->
                                    <div v-if="item.item_type === 'bouquet'" class="mt-2.5 pt-2 border-t border-pink-100/80 space-y-2">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="text-[9px] font-bold text-pink-700 uppercase tracking-wider block mb-0.5">Harga / Jasa</label>
                                                <div class="relative">
                                                    <input 
                                                        v-model.number="item.unit_price" 
                                                        type="number" 
                                                        min="0"
                                                        placeholder="Harga / Jasa" 
                                                        class="w-full pl-7 pr-2 py-1.5 text-[11px] font-bold rounded-lg border border-pink-200 bg-white text-pink-950 focus:ring-1 focus:ring-pink-400 focus:border-pink-400"
                                                    >
                                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-pink-400">Rp</span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-[9px] font-bold text-emerald-700 uppercase tracking-wider block mb-0.5">Money Bouquet</label>
                                                <div class="relative">
                                                    <input 
                                                        v-model.number="item.money_amount" 
                                                        type="number" 
                                                        min="0"
                                                        placeholder="Uang di Buket" 
                                                        class="w-full pl-7 pr-2 py-1.5 text-[11px] font-bold rounded-lg border border-pink-200 bg-white text-emerald-600 focus:ring-1 focus:ring-pink-400 focus:border-pink-400"
                                                    >
                                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-500">Rp</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2">
                                            <div class="relative">
                                                <input 
                                                    v-model="item.sender_name" 
                                                    type="text" 
                                                    placeholder="Nama Pengirim (Opsional)" 
                                                    class="w-full pl-7 pr-2 py-1.5 text-[11px] rounded-lg border border-pink-200 bg-white focus:ring-1 focus:ring-pink-400 focus:border-pink-400"
                                                >
                                                <User class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-pink-300" />
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <textarea 
                                                v-model="item.greeting_card" 
                                                placeholder="Kartu Ucapan..." 
                                                rows="1" 
                                                class="w-full pl-7 pr-2 py-1.5 text-[11px] rounded-lg border border-pink-200 bg-white focus:ring-1 focus:ring-pink-400 focus:border-pink-400 resize-none"
                                            ></textarea>
                                            <MessageSquare class="absolute left-2 top-2.5 w-3 h-3 text-pink-300" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Page 1 Bottom: Cart Subtotal & Next Button -->
                            <div class="border-t border-pink-100 pt-3 space-y-3">
                                <div class="flex justify-between items-center bg-pink-50/50 p-2.5 rounded-xl border border-pink-100">
                                    <span class="text-xs font-semibold text-pink-800">Subtotal Belanja</span>
                                    <span class="text-sm font-black text-pink-900">{{ formatCurrency(cartTotal) }}</span>
                                </div>
                                <BaseButton 
                                    variant="primary" 
                                    class="w-full py-2.5 rounded-xl text-xs font-bold shadow-xs flex items-center justify-center gap-1.5"
                                    :disabled="cartItems.length === 0"
                                    @click="rightPanelStep = 2"
                                >
                                    <span>Lanjut ke Pembayaran & Pengiriman</span>
                                    <ChevronRight class="w-4 h-4" />
                                </BaseButton>
                            </div>
                        </div>

                        <!-- ================= PAGE 2: PEMBAYARAN & PENGIRIMAN ================= -->
                        <div v-show="rightPanelStep === 2" class="space-y-4">
                            <!-- Customer Selection -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <User class="w-3.5 h-3.5 text-pink-600" />
                                        <h3 class="text-xs font-bold text-pink-900">Data Pembeli</h3>
                                    </div>
                                    <div class="inline-flex rounded-lg bg-pink-50 p-0.5 border border-pink-100">
                                        <button 
                                            @click="customerMode = 'existing'" 
                                            :class="['px-2.5 py-1 text-[10px] font-bold rounded-md transition-all', customerMode === 'existing' ? 'bg-white text-pink-700 shadow-xs border border-pink-200/60' : 'text-pink-500 hover:text-pink-700']"
                                        >Terdaftar</button>
                                        <button 
                                            @click="customerMode = 'new'" 
                                            :class="['px-2.5 py-1 text-[10px] font-bold rounded-md transition-all', customerMode === 'new' ? 'bg-white text-pink-700 shadow-xs border border-pink-200/60' : 'text-pink-500 hover:text-pink-700']"
                                        >Baru</button>
                                    </div>
                                </div>

                                <div v-if="customerMode === 'existing'" class="space-y-1.5 relative">
                                    <div class="relative">
                                        <input 
                                            v-model="customerSearch" 
                                            type="text" 
                                            placeholder="Ketik nama atau No HP..." 
                                            class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-pink-200 bg-white text-xs focus:ring-1 focus:ring-pink-400 focus:border-pink-400"
                                        >
                                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-pink-300" />
                                    </div>
                                    
                                    <div v-if="customerLookupLoading" class="absolute z-10 w-full p-3 bg-white border border-pink-200 rounded-xl shadow-lg flex items-center justify-center">
                                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-pink-600"></div>
                                    </div>

                                    <div v-if="customerOptions.length > 0" class="absolute z-10 w-full bg-white border border-pink-200 rounded-xl shadow-xl overflow-hidden max-h-52 overflow-y-auto">
                                        <button 
                                            v-for="c in customerOptions" 
                                            :key="c.id" 
                                            @click="selectCustomer(c)" 
                                            class="w-full p-2.5 text-left hover:bg-pink-50 flex items-center justify-between border-b border-pink-50 last:border-0 transition-colors"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-6 h-6 rounded-full bg-pink-100 flex items-center justify-center text-pink-700 font-bold text-[10px]">{{ c.name.charAt(0) }}</div>
                                                <div>
                                                    <p class="text-xs font-bold text-pink-900">{{ c.name }}</p>
                                                    <p class="text-[10px] text-pink-500">{{ c.phone_number }}</p>
                                                </div>
                                            </div>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-pink-100 text-pink-700">
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

                                <div v-else class="grid grid-cols-2 gap-2 p-2.5 rounded-xl bg-pink-50/30 border border-pink-100">
                                    <div class="col-span-2">
                                        <input v-model="form.new_customer_name" type="text" placeholder="Nama Lengkap Pembeli" class="w-full px-3 py-1.5 rounded-lg border border-pink-200 text-xs focus:ring-1 focus:ring-pink-400">
                                        <InputError :message="form.errors.new_customer_name" />
                                    </div>
                                    <div class="col-span-2">
                                        <input v-model="form.new_customer_phone_number" type="text" placeholder="Nomor WhatsApp / HP" class="w-full px-3 py-1.5 rounded-lg border border-pink-200 text-xs focus:ring-1 focus:ring-pink-400">
                                        <InputError :message="form.errors.new_customer_phone_number" />
                                    </div>
                                </div>
                            </div>

                            <!-- Delivery Section -->
                            <div class="space-y-2 border-t border-pink-100 pt-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <Truck class="w-3.5 h-3.5 text-pink-600" />
                                        <h3 class="text-xs font-bold text-pink-900">Metode Pengiriman</h3>
                                    </div>
                                    <div class="inline-flex rounded-lg bg-pink-50 p-0.5 border border-pink-100">
                                        <button 
                                            @click="form.shipping_type = 'pickup'" 
                                            :class="['px-2.5 py-1 text-[10px] font-bold rounded-md transition-all', form.shipping_type === 'pickup' ? 'bg-white text-pink-700 shadow-xs border border-pink-200/60' : 'text-pink-500 hover:text-pink-700']"
                                        >Pickup</button>
                                        <button 
                                            @click="form.shipping_type = 'delivery'" 
                                            :class="['px-2.5 py-1 text-[10px] font-bold rounded-md transition-all', form.shipping_type === 'delivery' ? 'bg-white text-pink-700 shadow-xs border border-pink-200/60' : 'text-pink-500 hover:text-pink-700']"
                                        >Delivery</button>
                                    </div>
                                </div>

                                <div v-if="form.shipping_type === 'delivery'" class="space-y-2">
                                    <div class="inline-flex rounded-lg bg-pink-50/80 p-0.5 border border-pink-100">
                                        <button 
                                            @click="deliveryMode = 'existing'" 
                                            :class="['px-2.5 py-0.5 text-[10px] font-semibold rounded transition-all', deliveryMode === 'existing' ? 'bg-white text-pink-700 shadow-2xs' : 'text-pink-500']"
                                        >Alamat Tersimpan</button>
                                        <button 
                                            @click="deliveryMode = 'new'" 
                                            :class="['px-2.5 py-0.5 text-[10px] font-semibold rounded transition-all', deliveryMode === 'new' ? 'bg-white text-pink-700 shadow-2xs' : 'text-pink-500']"
                                        >Alamat Baru</button>
                                    </div>

                                    <div v-if="deliveryMode === 'existing'" class="space-y-1.5 relative">
                                        <div class="relative">
                                            <input 
                                                v-model="deliverySearch" 
                                                type="text" 
                                                placeholder="Cari alamat / penerima..." 
                                                class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-pink-200 bg-white text-xs focus:ring-1 focus:ring-pink-400"
                                            >
                                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-pink-300" />
                                        </div>

                                        <div v-if="deliveryOptions.length > 0" class="absolute z-10 w-full bg-white border border-pink-200 rounded-xl shadow-xl overflow-hidden max-h-52 overflow-y-auto">
                                            <button 
                                                v-for="d in deliveryOptions" 
                                                :key="d.id" 
                                                @click="selectDelivery(d)" 
                                                class="w-full p-2.5 text-left hover:bg-pink-50 border-b border-pink-50 last:border-0 transition-colors"
                                            >
                                                <p class="text-xs font-bold text-pink-900">{{ d.recipient_name }}</p>
                                                <p class="text-[10px] text-pink-500 truncate">{{ d.full_address }}</p>
                                            </button>
                                        </div>

                                        <div v-if="selectedDeliverySnapshot && form.delivery_id" class="p-2.5 rounded-xl bg-blue-50 border border-blue-200">
                                            <p class="text-xs font-bold text-blue-900">{{ selectedDeliverySnapshot.recipient_name }}</p>
                                            <p class="text-[11px] text-blue-700">{{ selectedDeliverySnapshot.full_address }}</p>
                                        </div>
                                        <InputError :message="form.errors.delivery_id" />
                                    </div>

                                    <div v-else class="space-y-1.5 p-2.5 rounded-xl bg-pink-50/30 border border-pink-100">
                                        <input v-model="form.delivery_recipient_name" type="text" placeholder="Nama Penerima" class="w-full px-3 py-1.5 rounded-lg border border-pink-200 text-xs focus:ring-1 focus:ring-pink-400">
                                        <input v-model="form.delivery_recipient_phone" type="text" placeholder="WhatsApp Penerima" class="w-full px-3 py-1.5 rounded-lg border border-pink-200 text-xs focus:ring-1 focus:ring-pink-400">
                                        <textarea v-model="form.delivery_full_address" rows="2" placeholder="Alamat Lengkap (Jl, No, Patokan)..." class="w-full px-3 py-1.5 rounded-lg border border-pink-200 text-xs focus:ring-1 focus:ring-pink-400 resize-none"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Date, Time, Note -->
                            <div class="space-y-2 border-t border-pink-100 pt-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[10px] font-bold text-pink-800 uppercase mb-1 block">Tgl Pengiriman</label>
                                        <input v-model="form.shipping_date" type="date" class="w-full px-2.5 py-1.5 rounded-xl border border-pink-200 text-xs bg-pink-50/20 focus:ring-1 focus:ring-pink-400">
                                        <InputError :message="form.errors.shipping_date" />
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-pink-800 uppercase mb-1 block">Jam Estimasi</label>
                                        <input v-model="form.shipping_time" type="time" class="w-full px-2.5 py-1.5 rounded-xl border border-pink-200 text-xs bg-pink-50/20 focus:ring-1 focus:ring-pink-400">
                                        <InputError :message="form.errors.shipping_time" />
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-pink-800 uppercase mb-1 block">Catatan Order</label>
                                    <input 
                                        v-model="form.description" 
                                        type="text"
                                        placeholder="Contoh: Titip di satpam..." 
                                        class="w-full px-2.5 py-1.5 rounded-xl border border-pink-200 text-xs bg-pink-50/20 focus:ring-1 focus:ring-pink-400"
                                    >
                                    <InputError :message="form.errors.description" />
                                </div>
                            </div>

                            <!-- Financial Inputs (Ongkir, Diskon & DP) -->
                            <div class="space-y-2 border-t border-pink-100 pt-3">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="text-xs font-bold text-pink-900">Ongkos Kirim</label>
                                    <div class="relative w-32">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-pink-400 font-bold">Rp</span>
                                        <input v-model="form.shipping_fee" type="number" class="w-full pl-7 pr-2 py-1 rounded-lg border border-pink-200 text-right text-xs font-bold focus:ring-1 focus:ring-pink-400">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <label class="text-xs font-bold text-rose-700">Diskon (Manual)</label>
                                    <div class="relative w-32">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-rose-400 font-bold">Rp</span>
                                        <input v-model="form.discount" type="number" min="0" placeholder="0" class="w-full pl-7 pr-2 py-1 rounded-lg border border-rose-200 text-right text-xs font-bold text-rose-750 focus:ring-1 focus:ring-rose-400">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <label class="text-xs font-bold text-pink-900">Down Payment (DP)</label>
                                    <div class="relative w-32">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-pink-400 font-bold">Rp</span>
                                        <input v-model="form.down_payment" type="number" class="w-full pl-7 pr-2 py-1 rounded-lg border border-pink-200 text-right text-xs font-bold focus:ring-1 focus:ring-pink-400">
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Summary Box & Actions -->
                            <div class="bg-pink-950 rounded-xl p-4 text-white shadow-md">
                                <div class="space-y-1.5 mb-3 text-xs">
                                    <div class="flex justify-between text-pink-200">
                                        <span>Subtotal Item</span>
                                        <span class="font-bold text-white">{{ formatCurrency(cartTotal) }}</span>
                                    </div>
                                    <div v-if="shippingFeeAmount > 0" class="flex justify-between text-pink-200">
                                        <span>Ongkos Kirim</span>
                                        <span class="font-bold text-white">+ {{ formatCurrency(shippingFeeAmount) }}</span>
                                    </div>
                                    <div v-if="discountAmount > 0" class="flex justify-between text-rose-300 font-semibold">
                                        <span>Diskon</span>
                                        <span>- {{ formatCurrency(discountAmount) }}</span>
                                    </div>
                                    <div class="border-t border-pink-800/80 pt-2 flex justify-between items-end">
                                        <div>
                                            <p class="text-[9px] font-bold text-pink-400 uppercase tracking-wider">Total Tagihan</p>
                                            <p class="text-lg font-black text-pink-100">{{ formatCurrency(orderGrandTotal) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[9px] font-bold text-pink-400 uppercase tracking-wider">Sisa Bayar</p>
                                            <p class="text-sm font-bold text-white">{{ formatCurrency(Math.max(0, orderGrandTotal - (form.down_payment || 0))) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons: Before & Checkout -->
                                <div class="flex gap-2">
                                    <button 
                                        type="button" 
                                        @click="rightPanelStep = 1"
                                        class="px-3 py-2 rounded-xl bg-pink-900/90 hover:bg-pink-900 text-pink-200 text-xs font-bold flex items-center gap-1 transition-colors"
                                    >
                                        <ChevronLeft class="w-3.5 h-3.5" />
                                        <span>Kembali</span>
                                    </button>
                                    <BaseButton 
                                        variant="primary" 
                                        class="flex-1 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-xs font-bold tracking-wide border-none shadow-sm flex items-center justify-center gap-1.5" 
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
        <Modal :show="showDetailModal" @close="closeItemDetail" max-width="3xl">
            <div v-if="selectedItemDetail" class="p-0 overflow-hidden">
                <div class="grid md:grid-cols-12">
                    <!-- Left: Image -->
                    <div class="md:col-span-5 bg-pink-50 min-h-[300px] relative border-r-2 border-pink-100 flex items-center justify-center">
                        <img v-if="selectedItemDetail.image_url" :src="selectedItemDetail.image_url" class="w-full h-full object-cover">
                        <div v-else class="flex flex-col items-center justify-center text-pink-200">
                            <ShoppingBag v-if="selectedItemDetail._detail_type === 'bouquet'" class="w-24 h-24 mb-2" />
                            <Package v-else class="w-24 h-24 mb-2" />
                            <span class="text-xs font-bold uppercase tracking-widest">No Image Available</span>
                        </div>
                    </div>

                    <!-- Right: Info -->
                    <div class="md:col-span-7 p-8 space-y-6">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border-2 bg-pink-50 text-pink-600 border-pink-100">
                                    {{ selectedItemDetail.display_category || selectedItemDetail.type?.name || 'Catalog Item' }}
                                </span>
                                <span v-if="selectedItemDetail.serial_number" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border-2 bg-secondary/30 text-muted-foreground border-secondary/50">
                                    SKU: {{ selectedItemDetail.serial_number }}
                                </span>
                            </div>
                            <h2 class="text-3xl font-black text-pink-950 leading-tight">{{ selectedItemDetail.name }}</h2>
                            <p class="text-2xl font-bold text-pink-600 mt-1">{{ formatCurrency(selectedItemDetail.price) }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 border-y-2 border-pink-50 py-4">
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Dibuat Pada</p>
                                <p class="text-xs font-semibold text-pink-900">{{ formatDateTime(selectedItemDetail.created_at) }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Update Terakhir</p>
                                <p class="text-xs font-semibold text-pink-900">{{ formatDateTime(selectedItemDetail.updated_at) }}</p>
                            </div>
                        </div>

                        <div v-if="selectedItemDetail.description" class="space-y-2">
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Deskripsi / Spesifikasi</p>
                            <p class="text-sm text-pink-900 leading-relaxed bg-pink-50/50 p-4 rounded-2xl border-2 border-pink-100/50 italic">
                                "{{ selectedItemDetail.description }}"
                            </p>
                        </div>

                        <div v-if="selectedItemDetail._detail_type === 'inventory'" class="flex items-center justify-between p-3 bg-blue-50 rounded-xl border border-blue-200 text-blue-900 shadow-2xs">
                            <div class="flex items-center gap-2">
                                <Package class="w-4 h-4" />
                                <span class="text-xs font-bold uppercase tracking-wider">Stok Tersedia</span>
                            </div>
                            <span class="text-base font-black">{{ selectedItemDetail.stock }} {{ selectedItemDetail.unit?.name || 'Pcs' }}</span>
                        </div>

                        <div class="pt-3 flex gap-2.5">
                            <BaseButton variant="secondary" class="flex-1 rounded-xl border border-pink-200 text-xs py-2" @click="closeItemDetail">Tutup</BaseButton>
                            <BaseButton 
                                variant="primary" 
                                class="flex-1 rounded-xl border-none shadow-xs py-2 text-xs font-bold" 
                                @click="selectedItemDetail._detail_type === 'bouquet' ? addCatalogItem(selectedItemDetail) : addInventoryItem(selectedItemDetail); closeItemDetail()"
                            >
                                Tambahkan ke Order
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #fbcfe8;
    border-radius: 10px;
}
</style>
