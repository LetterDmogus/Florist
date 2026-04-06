<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import BaseButton from '@/Components/BaseButton.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch, onMounted } from 'vue';
import { ShoppingBag, ShoppingCart, Trash2, WandSparkles, Package, User, Truck, MapPin, Search, XCircle, MessageSquare, UserPlus, CreditCard, Eye, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    bouquetUnits: {
        type: Object,
        required: true,
    },
    inventoryItems: {
        type: Object,
        required: true,
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

// Detail Modal State
const showDetailModal = ref(false);
const selectedItemDetail = ref(null);

// Customer State
const customerMode = ref('existing');
const customerSearch = ref('');
const customerOptions = ref([]);
const customerLookupLoading = ref(false);
const selectedCustomerSnapshot = ref(props.order.customer);

// Delivery State
const deliveryMode = ref(props.order.delivery ? 'existing' : 'new');
const deliverySearch = ref('');
const deliveryOptions = ref([]);
const deliveryLookupLoading = ref(false);
const selectedDeliverySnapshot = ref(props.order.delivery);

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
    customer_mode: 'existing',
    customer_id: props.order.customer_id,
    new_customer_name: '',
    new_customer_phone_number: '',
    shipping_date: props.order.shipping_date ? props.order.shipping_date.split('T')[0] : '',
    shipping_time: props.order.shipping_time?.slice(0, 5),
    shipping_type: props.order.shipping_type,
    shipping_fee: props.order.shipping_fee,
    delivery_mode: props.order.delivery ? 'existing' : 'new',
    delivery_id: props.order.delivery?.id || '',
    delivery_recipient_name: props.order.delivery?.recipient_name || '',
    delivery_recipient_phone: props.order.delivery?.recipient_phone || '',
    delivery_full_address: props.order.delivery?.full_address || '',
    down_payment: props.order.down_payment || '',
    description: props.order.description || '',
    payment_status: props.order.payment_status,
    order_status: props.order.order_status,
    details: [],
    _method: 'PUT',
});

onMounted(() => {
    // Initialize cart items from order details
    cartItems.value = props.order.order_details.map((detail) => {
        const isBouquet = detail.item_type === 'bouquet';
        const unit = isBouquet ? detail.bouquet_unit : detail.inventory_item;
        
        return {
            id: detail.id,
            cart_id: `${detail.item_type}-${detail.id}`,
            item_type: detail.item_type,
            mode: isBouquet ? (unit?.type?.is_custom ? 'custom' : 'catalog') : null,
            bouquet_unit_id: detail.bouquet_unit_id,
            inventory_item_id: detail.inventory_item_id,
            quantity: detail.quantity,
            greeting_card: detail.greeting_card || '',
            sender_name: detail.sender_name || '',
            money_amount: detail.money_bouquet,
            display_name: unit?.name || 'Unknown Item',
            display_category: isBouquet ? (unit?.type?.name || '-') : (unit?.category?.name || 'Inventory'),
            unit_price: isBouquet ? (unit?.price ?? 0) : (unit?.price ?? 0),
            image_url: unit?.image_url || '',
        };
    });
});

const formatCurrency = (value) => {
    const amount = Number(value) || 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);
};

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

const orderGrandTotal = computed(() => cartTotal.value + shippingFeeAmount.value);

const lineTotal = (item) => {
    const price = Number(item.unit_price || 0);
    const money = Number(item.money_amount || 0);
    return (price + money) * (item.quantity || 1);
};

// Handlers
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
        cart_id: `catalog-new-${Date.now()}-${unit.id}`,
        item_type: 'bouquet',
        mode: 'catalog',
        bouquet_unit_id: unit.id,
        quantity: 1,
        greeting_card: '',
        sender_name: '',
        display_name: unit.name,
        display_category: unit.type?.category?.name ?? '-',
        unit_price: Number(unit.price || 0),
        image_url: unit.image_url || '',
    });
};

const addInventoryItem = (item) => {
    const existing = cartItems.value.find((i) => i.item_type === 'inventory_item' && i.inventory_item_id === item.id && !i.id);
    if (existing) {
        existing.quantity++;
        return;
    }

    cartItems.value.push({
        cart_id: `inv-new-${Date.now()}-${item.id}`,
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
        cart_id: `custom-new-${Date.now()}`,
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
    router.get(route('orders.edit', props.order.id), {
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
    customerSearch.value = '';
    customerOptions.value = [];
};

const selectDelivery = (delivery) => {
    form.delivery_id = delivery.id;
    form.delivery_recipient_name = delivery.recipient_name;
    form.delivery_recipient_phone = delivery.recipient_phone;
    form.delivery_full_address = delivery.full_address;
    deliverySearch.value = '';
    deliveryOptions.value = [];
};

const submitUpdate = () => {
    form.customer_mode = customerMode.value;
    form.details = cartItems.value.map((item) => ({
        id: item.id || null, // Essential for UpdateOrderAction to recognize existing details
        item_type: item.item_type,
        mode: item.mode,
        quantity: item.quantity,
        bouquet_unit_id: item.bouquet_unit_id,
        inventory_item_id: item.inventory_item_id,
        money_bouquet: item.mode === 'custom' ? Number(item.unit_price) : (item.money_amount || null),
        greeting_card: item.greeting_card || null,
        sender_name: item.sender_name || null,
        custom_category_id: item.custom_category_id || null,
        custom_name: item.custom_name || null,
        custom_serial_number: item.custom_serial_number || null,
        custom_price: item.custom_price || null,
        custom_note: item.custom_note || null,
        custom_image: item.custom_image || null,
    }));

    form.post(route('orders.update', props.order.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.success('Order berhasil diperbarui');
        },
        onError: (errors) => {
            console.error('Update Order Failed:', errors);
            toast.error('Gagal menyimpan. Cek rincian error di console atau di bawah input.');
            
            // Auto-scroll to first error
            const firstError = Object.keys(errors)[0];
            if (firstError) {
                const el = document.getElementsByName(firstError)[0] || document.querySelector(`[id*="${firstError}"]`);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },
    });
};

watch(customerSearch, debounce(() => fetchCustomerOptions(), 300));
watch(deliverySearch, debounce(() => fetchDeliveryOptions(), 300));
</script>

<template>
    <AppLayout title="Edit Order">
        <Head title="Edit Order" />

        <div class="space-y-6 max-w-[1600px] mx-auto pb-20">
            <!-- Header -->
            <div class="rounded-3xl border-2 border-blue-200 bg-gradient-to-r from-blue-100/70 via-white to-blue-50 p-6 shadow-sm">
                <!-- Global Error Debugger -->
                <div v-if="Object.keys(form.errors).length > 0" class="mb-4 p-4 bg-red-50 border-2 border-red-200 rounded-2xl">
                    <div class="flex items-center gap-2 mb-2 text-red-800">
                        <XCircle class="w-5 h-5" />
                        <h3 class="font-bold">Daftar Error (Debugging):</h3>
                    </div>
                    <ul class="list-disc list-inside text-xs text-red-600 space-y-1">
                        <li v-for="(error, key) in form.errors" :key="key">
                            <span class="font-bold uppercase">{{ key }}:</span> {{ error }}
                        </li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-blue-600 rounded-2xl text-white shadow-lg shadow-blue-200">
                            <ShoppingCart class="w-6 h-6" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-blue-600">Order Ref: #{{ order.id }}</p>
                            <h1 class="text-2xl font-bold text-blue-950">Update Pesanan</h1>
                        </div>
                    </div>
                    <BaseButton as="Link" :href="route('orders.status.index')" variant="secondary" size="lg" class="rounded-2xl border-2">
                        Kembali ke Status
                    </BaseButton>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-12 items-start">
                <!-- Left Column: Catalog & Items -->
                <div class="lg:col-span-7 space-y-6">
                    <!-- Tab Switcher -->
                    <section class="rounded-3xl border-2 border-pink-200 bg-white p-2 shadow-sm inline-flex">
                        <button @click="catalogMode = 'catalog'" :class="['px-6 py-2.5 text-sm font-bold rounded-2xl transition-all', catalogMode === 'catalog' ? 'bg-pink-600 text-white shadow-md shadow-pink-200' : 'text-pink-700 hover:bg-pink-50']">
                            <ShoppingBag class="w-4 h-4 inline mr-2" /> Bouquet
                        </button>
                        <button @click="catalogMode = 'inventory'" :class="['px-6 py-2.5 text-sm font-bold rounded-2xl transition-all', catalogMode === 'inventory' ? 'bg-pink-600 text-white shadow-md shadow-pink-200' : 'text-pink-700 hover:bg-pink-50']">
                            <Package class="w-4 h-4 inline mr-2" /> Inventory
                        </button>
                        <button v-if="canCustomBouquet" @click="catalogMode = 'custom'" :class="['px-6 py-2.5 text-sm font-bold rounded-2xl transition-all', catalogMode === 'custom' ? 'bg-pink-600 text-white shadow-md shadow-pink-200' : 'text-pink-700 hover:bg-pink-50']">
                            <WandSparkles class="w-4 h-4 inline mr-2" /> Custom
                        </button>
                    </section>

                    <!-- Catalog/Inventory Area -->
                    <div v-if="catalogMode !== 'custom'" class="bg-white rounded-3xl border-2 border-pink-100 p-6 shadow-sm min-h-[500px]">
                        <div class="flex flex-col sm:flex-row gap-4 mb-6">
                            <div class="relative flex-1">
                                <input v-model="catalogSearch" type="text" placeholder="Cari nama atau kode..." class="w-full pl-10 pr-4 py-3 rounded-2xl border-2 border-pink-100 bg-pink-50/30 text-sm focus:border-pink-400 focus:ring-pink-300 transition-all" @keyup.enter="applyCatalogFilters">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-pink-400" />
                            </div>
                            <select v-model="selectedCategoryId" class="rounded-2xl border-2 border-pink-100 bg-pink-50/30 text-sm focus:border-pink-400 focus:ring-pink-300" @change="applyCatalogFilters">
                                <option value="">Semua Kategori</option>
                                <option v-for="cat in bouquetCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <BaseButton @click="applyCatalogFilters" class="rounded-2xl">Cari</BaseButton>
                        </div>

                        <!-- Bouquet Catalog -->
                        <div v-if="catalogMode === 'catalog'" class="space-y-6">
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div v-for="unit in bouquetUnits.data" :key="unit.id" class="group relative bg-white border-2 border-pink-100 rounded-3xl p-3 hover:border-pink-400 hover:shadow-xl hover:shadow-pink-100/50 transition-all overflow-hidden">
                                    <div class="aspect-square rounded-2xl bg-pink-50 overflow-hidden mb-3 relative">
                                        <img v-if="unit.image_url" :src="unit.image_url" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <ShoppingBag class="w-10 h-10 text-pink-200" />
                                        </div>
                                        
                                        <!-- Hover Overlay -->
                                        <div class="absolute inset-0 bg-pink-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 backdrop-blur-[2px]">
                                            <button @click="openItemDetail(unit, 'bouquet')" class="p-3 bg-white rounded-full text-pink-600 hover:scale-110 transition-transform shadow-lg" title="Lihat Detail">
                                                <Eye class="w-5 h-5" />
                                            </button>
                                            <button @click="addCatalogItem(unit)" class="p-3 bg-pink-600 rounded-full text-white hover:scale-110 transition-transform shadow-lg" title="Tambah ke Order">
                                                <ShoppingCart class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-pink-500 font-bold uppercase tracking-wider mb-1">{{ unit.type?.category?.name }}</p>
                                    <h3 class="font-bold text-pink-950 truncate">{{ unit.name }}</h3>
                                    <p class="text-sm font-black text-pink-600 mt-1">{{ formatCurrency(unit.price) }}</p>
                                </div>
                            </div>

                            <!-- Pagination Bouquet -->
                            <div v-if="bouquetUnits.links && bouquetUnits.links.length > 3" class="flex items-center justify-center gap-1 border-t-2 border-pink-50 pt-4">
                                <template v-for="(link, k) in bouquetUnits.links" :key="k">
                                    <button
                                        v-if="link.url"
                                        @click="goToCatalogPage(link.url, 'bouquet')"
                                        :class="['px-3 py-1 text-xs font-bold rounded-lg transition-all border-2', link.active ? 'bg-pink-600 border-pink-600 text-white' : 'bg-white border-pink-100 text-pink-400 hover:border-pink-300']"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </div>

                        <!-- Inventory Items -->
                        <div v-if="catalogMode === 'inventory'" class="space-y-6">
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div v-for="item in inventoryItems.data" :key="item.id" class="group bg-white border-2 border-blue-100 rounded-3xl p-3 hover:border-blue-400 hover:shadow-xl hover:shadow-blue-100/50 transition-all overflow-hidden relative">
                                    <div class="aspect-square rounded-2xl bg-blue-50 overflow-hidden mb-3 relative">
                                        <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-blue-200">
                                            <Package class="w-10 h-10" />
                                        </div>

                                        <!-- Hover Overlay -->
                                        <div class="absolute inset-0 bg-blue-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 backdrop-blur-[2px]">
                                            <button @click="openItemDetail(item, 'inventory')" class="p-3 bg-white rounded-full text-blue-600 hover:scale-110 transition-transform shadow-lg" title="Lihat Detail">
                                                <Eye class="w-5 h-5" />
                                            </button>
                                            <button @click="addInventoryItem(item)" class="p-3 bg-blue-600 rounded-full text-white hover:scale-110 transition-transform shadow-lg" title="Tambah ke Keranjang">
                                                <ShoppingCart class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-blue-500 font-bold uppercase tracking-wider mb-1">{{ item.category?.name || 'Inventory' }}</p>
                                    <h3 class="font-bold text-blue-950 truncate">{{ item.name }}</h3>
                                    <div class="flex items-center justify-between mt-1">
                                        <p class="text-sm font-black text-blue-600">{{ formatCurrency(item.price) }}</p>
                                        <span class="text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">Stok: {{ item.stock }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination Inventory -->
                            <div v-if="inventoryItems.links && inventoryItems.links.length > 3" class="flex items-center justify-center gap-1 border-t-2 border-blue-50 pt-4">
                                <template v-for="(link, k) in inventoryItems.links" :key="k">
                                    <button
                                        v-if="link.url"
                                        @click="goToCatalogPage(link.url, 'inventory')"
                                        :class="['px-3 py-1 text-xs font-bold rounded-lg transition-all border-2', link.active ? 'bg-pink-600 border-pink-600 text-white' : 'bg-white border-blue-100 text-blue-400 hover:border-blue-300']"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Form Area -->
                    <div v-else class="bg-white rounded-3xl border-2 border-pink-100 p-8 shadow-sm">
                        <div class="flex items-center gap-3 mb-6">
                            <WandSparkles class="w-6 h-6 text-pink-600" />
                            <h2 class="text-xl font-bold text-pink-950">Buat Bouquet Custom</h2>
                        </div>
                        
                        <div class="grid sm:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-bold text-pink-800 uppercase tracking-wider mb-1.5 block">Kategori</label>
                                    <select v-model="customDraft.custom_category_id" class="w-full rounded-2xl border-2 border-pink-100 bg-pink-50/30 py-3 text-sm focus:ring-pink-300">
                                        <option value="">Pilih Kategori</option>
                                        <option v-for="cat in bouquetCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-pink-800 uppercase tracking-wider mb-1.5 block">Nama Bouquet</label>
                                    <input v-model="customDraft.custom_name" type="text" placeholder="Buket Spesial Ultah..." class="w-full rounded-2xl border-2 border-pink-100 bg-pink-50/30 py-3 text-sm focus:ring-pink-300">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-pink-800 uppercase tracking-wider mb-1.5 block">Harga Jual Jasa</label>
                                    <input v-model="customDraft.custom_price" type="number" placeholder="Contoh: 150000" class="w-full rounded-2xl border-2 border-pink-100 bg-pink-50/30 py-3 text-sm font-bold focus:ring-pink-300">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-pink-800 uppercase tracking-wider mb-1.5 block">Uang di Buket (Money Bouquet)</label>
                                    <input v-model="customDraft.money_amount" type="number" placeholder="Rp 0" class="w-full rounded-2xl border-2 border-pink-100 bg-pink-50/30 py-3 text-sm font-bold text-emerald-600 focus:ring-pink-300">
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-bold text-pink-800 uppercase tracking-wider mb-1.5 block">Foto Referensi (Opsional)</label>
                                    <div class="relative aspect-video rounded-2xl border-2 border-dashed border-pink-200 bg-pink-50/30 overflow-hidden group">
                                        <img v-if="customImagePreview" :src="customImagePreview" class="w-full h-full object-cover">
                                        <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-pink-400">
                                            <WandSparkles class="w-8 h-8 mb-2" />
                                            <span class="text-xs font-bold uppercase tracking-widest">Klik untuk upload</span>
                                        </div>
                                        <input type="file" @change="onCustomImageChange" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-pink-800 uppercase tracking-wider mb-1.5 block">Catatan Produksi</label>
                                    <textarea v-model="customDraft.custom_note" rows="3" placeholder="Warna kertas pink, pita putih..." class="w-full rounded-2xl border-2 border-pink-100 bg-pink-50/30 text-sm focus:ring-pink-300"></textarea>
                                </div>
                            </div>
                        </div>
                        <div v-if="customFormError" class="mt-4 p-3 bg-red-50 text-red-600 text-xs rounded-xl font-medium border-2 border-red-100">{{ customFormError }}</div>
                        <BaseButton variant="primary" class="w-full mt-8 py-4 rounded-2xl shadow-lg shadow-pink-200" @click="addCustomItem">
                            Tambahkan ke Pesanan
                        </BaseButton>
                    </div>
                </div>

                <!-- Right Column: Checkout & Settings -->
                <div class="lg:col-span-5 space-y-6">
                    <section class="bg-white rounded-3xl border-2 border-pink-200 p-6 shadow-xl shadow-pink-100/20">
                        <div class="flex items-center gap-3 mb-6">
                            <ShoppingCart class="w-5 h-5 text-pink-600" />
                            <h2 class="text-xl font-bold text-pink-950 text-right flex-1">Isi Keranjang</h2>
                        </div>

                        <!-- Cart List -->
                        <div class="space-y-4 max-h-[450px] overflow-y-auto custom-scrollbar pr-2 mb-6">
                            <div v-if="cartItems.length === 0" class="py-20 text-center border-2 border-dashed border-pink-100 rounded-3xl">
                                <ShoppingBag class="w-12 h-12 text-pink-100 mx-auto mb-3" />
                                <p class="text-sm text-pink-400 font-medium">Keranjang masih kosong</p>
                            </div>
                            
                            <div v-for="item in cartItems" :key="item.cart_id" class="group bg-pink-50/30 border-2 border-pink-100 rounded-3xl p-4 transition-all hover:border-pink-300 hover:bg-white">
                                <div class="flex gap-4">
                                    <div class="w-16 h-16 rounded-2xl bg-white border-2 border-pink-50 overflow-hidden shrink-0 shadow-sm text-center flex items-center justify-center">
                                        <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover">
                                        <ShoppingBag v-else class="w-6 h-6 text-pink-100" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start">
                                            <h4 class="font-bold text-pink-950 truncate text-sm">{{ item.display_name }}</h4>
                                            <button @click="removeCartItem(item.cart_id)" class="text-pink-300 hover:text-red-500 transition-colors">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                        <p class="text-[10px] text-pink-500 font-bold uppercase tracking-wider mb-1">{{ item.display_category }}</p>
                                        <div v-if="item.money_amount" class="mb-2">
                                            <span class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-lg font-bold border border-emerald-200">
                                                + Uang: {{ formatCurrency(item.money_amount) }}
                                            </span>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center bg-white rounded-xl border-2 border-pink-100 overflow-hidden">
                                                <button @click="item.quantity > 1 && item.quantity--" class="px-2 py-1 hover:bg-pink-50 text-pink-600 disabled:opacity-30" :disabled="item.item_type === 'bouquet'">-</button>
                                                <input v-model="item.quantity" type="number" min="1" class="w-10 text-center border-none text-xs font-bold focus:ring-0" :disabled="item.item_type === 'bouquet'">
                                                <button @click="item.quantity++" class="px-2 py-1 hover:bg-pink-50 text-pink-600 disabled:opacity-30" :disabled="item.item_type === 'bouquet'">+</button>
                                            </div>
                                            <p class="font-black text-pink-900 text-sm">{{ formatCurrency(lineTotal(item)) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Greeting & Sender -->
                                <div v-if="item.item_type === 'bouquet'" class="mt-4 grid grid-cols-2 gap-3">
                                    <div class="relative">
                                        <input v-model="item.sender_name" type="text" placeholder="Nama Pengirim" class="w-full pl-8 py-2 text-[11px] rounded-xl border-2 border-pink-100 bg-white focus:ring-pink-300 transition-all">
                                        <User class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-pink-300" />
                                    </div>
                                    <div class="relative">
                                        <input v-model="item.greeting_card" type="text" placeholder="Kartu Ucapan..." class="w-full pl-8 py-2 text-[11px] rounded-xl border-2 border-pink-100 bg-white focus:ring-pink-300 transition-all">
                                        <MessageSquare class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-pink-300" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Selection -->
                        <div class="mb-6 space-y-4">
                            <div class="flex items-center gap-2 mb-2">
                                <User class="w-4 h-4 text-pink-600" />
                                <h3 class="text-sm font-bold text-pink-900">Data Pembeli</h3>
                            </div>
                            
                            <div class="flex rounded-2xl bg-pink-50/50 p-1 border-2 border-pink-100">
                                <button @click="customerMode = 'existing'" :class="['flex-1 py-2 text-xs font-bold rounded-xl transition-all', customerMode === 'existing' ? 'bg-white text-pink-600 shadow-sm' : 'text-pink-400 hover:text-pink-600']">Terdaftar</button>
                                <button @click="customerMode = 'new'" :class="['flex-1 py-2 text-xs font-bold rounded-xl transition-all', customerMode === 'new' ? 'bg-white text-pink-600 shadow-sm' : 'text-pink-400 hover:text-pink-600']">Pembeli Baru</button>
                            </div>

                            <div v-if="customerMode === 'existing'" class="space-y-2 relative">
                                <div class="relative">
                                    <input v-model="customerSearch" type="text" placeholder="Ketik nama atau No HP..." class="w-full pl-10 py-3 rounded-2xl border-2 border-pink-100 bg-white text-sm focus:ring-pink-300">
                                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-pink-300" />
                                </div>
                                
                                <div v-if="customerLookupLoading" class="absolute z-10 w-full p-4 bg-white border-2 border-pink-100 rounded-2xl shadow-xl flex items-center justify-center">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-pink-600"></div>
                                </div>

                                <div v-if="customerOptions.length > 0" class="absolute z-10 w-full bg-white border-2 border-pink-100 rounded-2xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto">
                                    <button v-for="c in customerOptions" :key="c.id" @click="selectCustomer(c)" class="w-full p-4 text-left hover:bg-pink-50 flex items-center justify-between border-b border-pink-50 last:border-0 transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-pink-100 flex items-center justify-center text-pink-600 font-bold text-xs">{{ c.name.charAt(0) }}</div>
                                            <div>
                                                <p class="text-sm font-bold text-pink-900">{{ c.name }}</p>
                                                <p class="text-[10px] text-pink-500">{{ c.phone_number }}</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-pink-100 text-pink-700">
                                                {{ c.orders_count || 0 }}x Order
                                            </span>
                                        </div>
                                    </button>
                                </div>

                                <div v-if="selectedCustomer" class="p-4 rounded-2xl bg-emerald-50 border-2 border-emerald-100 flex items-center justify-between shadow-sm">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-10 h-10 rounded-full bg-emerald-600 flex items-center justify-center text-white font-bold">{{ selectedCustomer.name.charAt(0) }}</div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-emerald-900 truncate">{{ selectedCustomer.name }}</p>
                                            <p class="text-[10px] text-emerald-600 font-medium">Pembeli Terpilih • {{ selectedCustomer.orders_count || 0 }}x Beli</p>
                                        </div>
                                    </div>
                                    <button @click="form.customer_id = ''; selectedCustomerSnapshot = null" class="text-emerald-400 hover:text-red-500 ml-2 transition-colors"><XCircle class="w-5 h-5" /></button>
                                </div>
                                <InputError :message="form.errors.customer_id" />
                            </div>

                            <div v-else class="grid grid-cols-2 gap-3 p-4 rounded-3xl bg-pink-50/30 border-2 border-pink-100">
                                <div class="col-span-2">
                                    <input v-model="form.new_customer_name" type="text" placeholder="Nama Lengkap" class="w-full px-4 py-2.5 rounded-xl border-2 border-pink-100 text-sm focus:ring-pink-300 transition-all">
                                    <InputError :message="form.errors.new_customer_name" />
                                </div>
                                <div class="col-span-2">
                                    <input v-model="form.new_customer_phone_number" type="text" placeholder="No HP / WhatsApp" class="w-full px-4 py-2.5 rounded-xl border-2 border-pink-100 text-sm focus:ring-pink-300 transition-all">
                                    <InputError :message="form.errors.new_customer_phone_number" />
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Selection -->
                        <div class="mb-6 space-y-4 border-t-2 border-pink-50 pt-6">
                            <div class="flex items-center gap-2 mb-2">
                                <Truck class="w-4 h-4 text-pink-600" />
                                <h3 class="text-sm font-bold text-pink-900">Metode Pengiriman</h3>
                            </div>

                            <div class="flex rounded-2xl bg-pink-50/50 p-1 border-2 border-pink-100">
                                <button @click="form.shipping_type = 'pickup'" :class="['flex-1 py-2 text-xs font-bold rounded-xl transition-all', form.shipping_type === 'pickup' ? 'bg-white text-pink-600 shadow-sm' : 'text-pink-400 hover:text-pink-600']">Pickup</button>
                                <button @click="form.shipping_type = 'delivery'" :class="['flex-1 py-2 text-xs font-bold rounded-xl transition-all', form.shipping_type === 'delivery' ? 'bg-white text-pink-600 shadow-sm' : 'text-pink-400 hover:text-pink-600']">Delivery</button>
                            </div>

                            <div v-if="form.shipping_type === 'delivery'" class="space-y-4">
                                <div class="flex rounded-2xl bg-pink-50/50 p-1 border-2 border-pink-100">
                                    <button @click="deliveryMode = 'existing'" :class="['flex-1 py-2 text-xs font-bold rounded-xl transition-all', deliveryMode === 'existing' ? 'bg-white text-pink-600 shadow-sm' : 'text-pink-400 hover:text-pink-600']">Terdaftar</button>
                                    <button @click="deliveryMode = 'new'" :class="['flex-1 py-2 text-xs font-bold rounded-xl transition-all', deliveryMode === 'new' ? 'bg-white text-pink-600 shadow-sm' : 'text-pink-400 hover:text-pink-600']">Alamat Baru</button>
                                </div>

                                <div v-if="deliveryMode === 'existing'" class="space-y-2 relative">
                                    <div class="relative">
                                        <input v-model="deliverySearch" type="text" placeholder="Cari alamat / penerima..." class="w-full pl-10 py-3 rounded-2xl border-2 border-pink-100 bg-white text-sm focus:ring-pink-300">
                                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-pink-300" />
                                    </div>

                                    <div v-if="deliveryOptions.length > 0" class="absolute z-10 w-full bg-white border-2 border-pink-100 rounded-2xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto">
                                        <button v-for="d in deliveryOptions" :key="d.id" @click="selectDelivery(d)" class="w-full p-4 text-left hover:bg-pink-50 border-b border-pink-50 last:border-0 transition-colors">
                                            <p class="text-sm font-bold text-pink-900">{{ d.recipient_name }}</p>
                                            <p class="text-[10px] text-pink-500 truncate">{{ d.full_address }}</p>
                                        </button>
                                    </div>

                                    <div v-if="selectedDeliverySnapshot && form.delivery_id" class="p-4 rounded-2xl bg-blue-50 border-2 border-blue-100 shadow-sm">
                                        <p class="text-sm font-bold text-blue-900">{{ selectedDeliverySnapshot.recipient_name }}</p>
                                        <p class="text-xs text-blue-600">{{ selectedDeliverySnapshot.full_address }}</p>
                                    </div>
                                    <InputError :message="form.errors.delivery_id" />
                                </div>

                                <div v-else class="grid grid-cols-2 gap-3 p-4 rounded-3xl bg-pink-50/30 border-2 border-pink-100">
                                    <div class="col-span-2">
                                        <input v-model="form.delivery_recipient_name" type="text" placeholder="Nama Penerima" class="w-full px-4 py-2.5 rounded-xl border-2 border-pink-100 text-sm focus:ring-pink-300 transition-all">
                                        <InputError :message="form.errors.delivery_recipient_name" />
                                    </div>
                                    <div class="col-span-2">
                                        <input v-model="form.delivery_recipient_phone" type="text" placeholder="WhatsApp Penerima" class="w-full px-4 py-2.5 rounded-xl border-2 border-pink-100 text-sm focus:ring-pink-300 transition-all">
                                        <InputError :message="form.errors.delivery_recipient_phone" />
                                    </div>
                                    <div class="col-span-2">
                                        <textarea v-model="form.delivery_full_address" rows="3" placeholder="Alamat Lengkap (Jl, No, Patokan)..." class="w-full px-4 py-2.5 rounded-xl border-2 border-pink-100 text-sm focus:ring-pink-300 transition-all"></textarea>
                                        <InputError :message="form.errors.delivery_full_address" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date & Time -->
                        <div class="grid grid-cols-2 gap-4 mb-4 border-t-2 border-pink-50 pt-6">
                            <div>
                                <label class="text-[10px] font-bold text-pink-800 uppercase mb-1.5 block">Tgl Pengiriman</label>
                                <input v-model="form.shipping_date" type="date" class="w-full px-4 py-3 rounded-2xl border-2 border-pink-100 text-sm bg-pink-50/20 focus:ring-pink-300 transition-all">
                                <InputError :message="form.errors.shipping_date" />
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-pink-800 uppercase mb-1.5 block">Jam Estimasi</label>
                                <input v-model="form.shipping_time" type="time" class="w-full px-4 py-3 rounded-2xl border-2 border-pink-100 text-sm bg-pink-50/20 focus:ring-pink-300 transition-all">
                                <InputError :message="form.errors.shipping_time" />
                            </div>
                        </div>

                        <!-- Order Note -->
                        <div class="mb-6">
                            <label class="text-[10px] font-bold text-pink-800 uppercase mb-1.5 block">Catatan Order (Umum)</label>
                            <textarea 
                                v-model="form.description" 
                                rows="2" 
                                placeholder="Contoh: Titip di satpam, jangan pakai kartu ucapan..." 
                                class="w-full px-4 py-3 rounded-2xl border-2 border-pink-100 text-sm bg-pink-50/20 focus:ring-pink-300 transition-all"
                            ></textarea>
                            <InputError :message="form.errors.description" />
                        </div>

                        <!-- Financial Inputs -->
                        <div class="space-y-4 mb-6 border-t-2 border-pink-50 pt-6">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    <Truck class="w-4 h-4 text-pink-600" />
                                    <label class="text-sm font-bold text-pink-900">Ongkos Kirim</label>
                                </div>
                                <div class="relative w-36">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-pink-400 font-bold">Rp</span>
                                    <input v-model="form.shipping_fee" type="number" class="w-full pl-8 py-2 rounded-xl border-2 border-pink-100 text-right text-sm font-bold focus:ring-pink-300 transition-all shadow-sm">
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    <CreditCard class="w-4 h-4 text-pink-600" />
                                    <label class="text-sm font-bold text-pink-900">Down Payment (DP)</label>
                                </div>
                                <div class="relative w-36">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-pink-400 font-bold">Rp</span>
                                    <input v-model="form.down_payment" type="number" class="w-full pl-8 py-2 rounded-xl border-2 border-pink-100 text-right text-sm font-bold focus:ring-pink-300 transition-all shadow-sm">
                                </div>
                            </div>
                        </div>

                        <!-- Financial Summary Box -->
                        <div class="bg-pink-950 rounded-3xl p-6 text-white shadow-xl shadow-pink-950/20">
                            <div class="space-y-3 mb-6">
                                <div class="flex justify-between text-pink-200">
                                    <span class="text-sm">Subtotal Item</span>
                                    <span class="font-bold text-white">{{ formatCurrency(cartTotal) }}</span>
                                </div>
                                <div class="flex justify-between text-pink-200">
                                    <span class="text-sm">Biaya Pengiriman</span>
                                    <span class="font-bold text-white">+ {{ formatCurrency(shippingFeeAmount) }}</span>
                                </div>
                                <div class="border-t border-pink-800 pt-3 flex justify-between items-end">
                                    <div>
                                        <p class="text-[10px] font-bold text-pink-400 uppercase tracking-widest">Total Tagihan</p>
                                        <p class="text-2xl font-black text-pink-100">{{ formatCurrency(orderGrandTotal) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] font-bold text-pink-400 uppercase tracking-widest">Sisa Bayar</p>
                                        <p class="text-lg font-bold text-white">{{ formatCurrency(Math.max(0, orderGrandTotal - (form.down_payment || 0))) }}</p>
                                    </div>
                                </div>
                            </div>

                            <BaseButton variant="primary" class="w-full py-4 rounded-2xl bg-pink-600 hover:bg-pink-500 shadow-lg shadow-pink-950/50 text-white border-none font-black tracking-widest transition-all active:scale-95" :disabled="form.processing || cartItems.length === 0" @click="submitUpdate">
                                {{ form.processing ? 'SEDANG MEMPROSES...' : 'SIMPAN PERUBAHAN' }}
                            </BaseButton>
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

                        <div v-if="selectedItemDetail._detail_type === 'inventory'" class="flex items-center justify-between p-4 bg-blue-50 rounded-2xl border-2 border-blue-100 text-blue-900 shadow-sm">
                            <div class="flex items-center gap-3">
                                <Package class="w-5 h-5" />
                                <span class="text-sm font-bold uppercase tracking-widest">Stok Tersedia</span>
                            </div>
                            <span class="text-xl font-black">{{ selectedItemDetail.stock }} {{ selectedItemDetail.unit?.name || 'Pcs' }}</span>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <BaseButton variant="secondary" class="flex-1 rounded-2xl border-2" @click="closeItemDetail">Tutup</BaseButton>
                            <BaseButton 
                                variant="primary" 
                                class="flex-1 rounded-2xl border-2 shadow-lg shadow-pink-200 py-4" 
                                @click="selectedItemDetail._detail_type === 'bouquet' ? addCatalogItem(selectedItemDetail) : addInventoryItem(selectedItemDetail); closeItemDetail()"
                            >
                                Tambahkan ke Pesanan
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
