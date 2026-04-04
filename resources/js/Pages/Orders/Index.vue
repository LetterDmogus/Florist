<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { ShoppingBag, ShoppingCart, Trash2, WandSparkles } from 'lucide-vue-next';

const props = defineProps({
    customers: {
        type: Array,
        default: () => [],
    },
    bouquetUnits: {
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

const today = new Date().toISOString().slice(0, 10);
const nowTime = new Date().toTimeString().slice(0, 5);
const LOOKUP_LIMIT = 8;
const createRequestId = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `req-${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
};

const catalogMode = ref('catalog');
const catalogSearch = ref(props.catalogFilters?.search || '');
const selectedCategoryId = ref(props.catalogFilters?.category_id || '');
const cartItems = ref([]);
const customFormError = ref('');
const customerMode = ref('existing');
const customerSearch = ref('');
const customerOptions = ref(props.customers || []);
const customerLookupLoading = ref(false);
const selectedCustomerSnapshot = ref(null);
const deliveryMode = ref('new');
const deliverySearch = ref('');
const deliveryOptions = ref(props.deliveryReferences || []);
const deliveryLookupLoading = ref(false);
const selectedDeliverySnapshot = ref(null);

const customDraft = ref({
    custom_category_id: '',
    custom_name: '',
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
    request_id: createRequestId(),
    customer_mode: 'existing',
    customer_id: '',
    new_customer_name: '',
    new_customer_phone_number: '',
    shipping_date: today,
    shipping_time: nowTime,
    shipping_type: 'delivery',
    shipping_fee: '',
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

const catalogBouquets = computed(() => props.bouquetUnits?.data || []);

const selectedCustomer = computed(() => {
    if (!form.customer_id) {
        return null;
    }

    const selected = customerOptions.value.find((customer) => String(customer.id) === String(form.customer_id));

    return selected ?? selectedCustomerSnapshot.value;
});

const selectedDelivery = computed(() => {
    if (!form.delivery_id) {
        return null;
    }

    const selected = deliveryOptions.value.find((delivery) => String(delivery.id) === String(form.delivery_id));

    return selected ?? selectedDeliverySnapshot.value;
});

const cartTotal = computed(() => {
    return cartItems.value.reduce((total, item) => total + lineTotal(item), 0);
});

const shippingFeeAmount = computed(() => {
    if (form.shipping_type !== 'delivery') {
        return 0;
    }

    const value = Number(form.shipping_fee ?? 0);

    return Number.isFinite(value) && value > 0 ? value : 0;
});

const orderGrandTotal = computed(() => {
    return cartTotal.value + shippingFeeAmount.value;
});

const downPaymentAmount = computed(() => {
    const value = Number(form.down_payment ?? 0);

    return Number.isFinite(value) && value > 0 ? value : 0;
});

const maxDownPayment = computed(() => {
    return Math.max(0, cartTotal.value);
});

const remainingPayment = computed(() => {
    if (downPaymentAmount.value <= 0) {
        return 0;
    }

    return Math.max(0, orderGrandTotal.value - downPaymentAmount.value);
});

const paymentStatusPreview = computed(() => {
    if (orderGrandTotal.value <= 0) {
        return 'Belum Bayar';
    }

    if (downPaymentAmount.value <= 0) {
        return 'Lunas';
    }

    if (downPaymentAmount.value >= orderGrandTotal.value) {
        return 'Lunas';
    }

    return 'DP';
});

const lineTotal = (item) => {
    return item.mode === 'custom'
        ? Number(item.custom_price || 0)
        : Number(item.unit_price || 0);
};

const addCatalogItem = (unit) => {
    const existing = cartItems.value.find((item) => item.mode === 'catalog' && item.bouquet_unit_id === unit.id);

    if (existing) {
        return;
    }

    cartItems.value.push({
        cart_id: `catalog-${unit.id}`,
        item_type: 'bouquet',
        mode: 'catalog',
        bouquet_unit_id: unit.id,
        quantity: 1,
        greeting_card: '',
        sender_name: '',
        money_bouquet: null,
        display_name: unit.name,
        display_category: unit.type?.category?.name ?? '-',
        unit_price: Number(unit.price || 0),
        image_url: unit.image_url || '',
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
        cart_id: `custom-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        item_type: 'bouquet',
        mode: 'custom',
        bouquet_unit_id: null,
        custom_category_id: Number(customDraft.value.custom_category_id),
        custom_name: customDraft.value.custom_name.trim(),
        custom_price: Number(customDraft.value.custom_price),
        custom_note: customDraft.value.custom_note?.trim() || null,
        quantity: 1,
        greeting_card: customDraft.value.greeting_card?.trim() || null,
        sender_name: customDraft.value.sender_name?.trim() || null,
        money_bouquet: Number(customDraft.value.custom_price),
        money_amount: customDraft.value.money_amount ? Number(customDraft.value.money_amount) : null,
        custom_image: customDraft.value.custom_image,
        image_preview: customImagePreview.value,
        display_name: customDraft.value.custom_name.trim(),
        display_category: category?.name ?? 'Custom',
        unit_price: Number(customDraft.value.custom_price),
        image_url: customImagePreview.value || '',
    });

    customDraft.value = {
        custom_category_id: '',
        custom_name: '',
        custom_price: '',
        custom_note: '',
        greeting_card: '',
        sender_name: '',
        money_amount: '',
        custom_image: null,
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
        catalog_page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['bouquetUnits', 'catalogFilters'],
    });
};

const goToCatalogPage = (url) => {
    if (!url) {
        return;
    }

    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['bouquetUnits', 'catalogFilters'],
    });
};

let customerLookupRequest = 0;
const fetchCustomerOptions = async () => {
    if (customerMode.value !== 'existing') {
        return;
    }

    const search = customerSearch.value.trim();
    if (search === '') {
        customerOptions.value = [];
        customerLookupLoading.value = false;
        return;
    }

    const requestId = ++customerLookupRequest;
    customerLookupLoading.value = true;

    try {
        const { data } = await axios.get(route('orders.lookups.customers'), {
            params: {
                search,
                limit: LOOKUP_LIMIT,
            },
        });

        if (requestId !== customerLookupRequest) {
            return;
        }

        customerOptions.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        if (requestId !== customerLookupRequest) {
            return;
        }

        customerOptions.value = [];
    } finally {
        if (requestId === customerLookupRequest) {
            customerLookupLoading.value = false;
        }
    }
};

let deliveryLookupRequest = 0;
const fetchDeliveryOptions = async () => {
    if (form.shipping_type !== 'delivery' || deliveryMode.value !== 'existing') {
        return;
    }

    const search = deliverySearch.value.trim();
    if (search === '') {
        deliveryOptions.value = [];
        deliveryLookupLoading.value = false;
        return;
    }

    const requestId = ++deliveryLookupRequest;
    deliveryLookupLoading.value = true;

    try {
        const { data } = await axios.get(route('orders.lookups.deliveries'), {
            params: {
                search,
                limit: LOOKUP_LIMIT,
            },
        });

        if (requestId !== deliveryLookupRequest) {
            return;
        }

        deliveryOptions.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        if (requestId !== deliveryLookupRequest) {
            return;
        }

        deliveryOptions.value = [];
    } finally {
        if (requestId === deliveryLookupRequest) {
            deliveryLookupLoading.value = false;
        }
    }
};

const debouncedFetchCustomerOptions = debounce(fetchCustomerOptions, 300);
const debouncedFetchDeliveryOptions = debounce(fetchDeliveryOptions, 300);

const submitOrder = () => {
    if (!form.request_id) {
        form.request_id = createRequestId();
    }

    form.customer_mode = customerMode.value;
    form.delivery_mode = deliveryMode.value;

    if (customerMode.value === 'new') {
        form.customer_id = '';
    } else {
        form.new_customer_name = '';
        form.new_customer_phone_number = '';
    }

    if (form.shipping_type !== 'delivery') {
        form.delivery_mode = 'new';
        form.shipping_fee = '';
        form.delivery_id = '';
        form.delivery_recipient_name = '';
        form.delivery_recipient_phone = '';
        form.delivery_full_address = '';
    } else if (deliveryMode.value === 'existing') {
        form.delivery_recipient_name = '';
        form.delivery_recipient_phone = '';
        form.delivery_full_address = '';
    } else {
        form.delivery_id = '';
    }

    form.details = cartItems.value.map((item) => ({
        item_type: item.item_type,
        mode: item.mode,
        quantity: 1,
        bouquet_unit_id: item.bouquet_unit_id,
        inventory_item_id: null,
        money_bouquet: item.mode === 'custom' ? Number(item.custom_price) : null,
        greeting_card: item.greeting_card || null,
        sender_name: item.sender_name || null,
        custom_category_id: item.mode === 'custom' ? Number(item.custom_category_id) : null,
        custom_name: item.mode === 'custom' ? item.custom_name : null,
        custom_price: item.mode === 'custom' ? Number(item.custom_price) : null,
        custom_note: item.mode === 'custom' ? item.custom_note : null,
        custom_image: item.mode === 'custom' ? item.custom_image : null,
        money_amount: item.mode === 'custom' ? item.money_amount : null,
    }));

    form.post(route('orders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cartItems.value = [];
            form.reset(
                'customer_mode',
                'customer_id',
                'new_customer_name',
                'new_customer_phone_number',
                'shipping_fee',
                'delivery_mode',
                'delivery_id',
                'delivery_recipient_name',
                'delivery_recipient_phone',
                'delivery_full_address',
                'down_payment',
                'description',
                'details',
            );
            form.shipping_date = today;
            form.shipping_time = new Date().toTimeString().slice(0, 5);
            form.shipping_type = 'delivery';
            form.request_id = createRequestId();
            customerMode.value = 'existing';
            customerSearch.value = '';
            customerOptions.value = [];
            selectedCustomerSnapshot.value = null;
            deliveryMode.value = 'new';
            deliverySearch.value = '';
            deliveryOptions.value = [];
            selectedDeliverySnapshot.value = null;
        },
    });
};

watch(customerMode, (mode) => {
    form.customer_mode = mode;

    if (mode === 'existing') {
        form.new_customer_name = '';
        form.new_customer_phone_number = '';
        if (customerSearch.value.trim() !== '') {
            debouncedFetchCustomerOptions();
        }

        return;
    }

    form.customer_id = '';
    customerSearch.value = '';
    customerOptions.value = [];
    selectedCustomerSnapshot.value = null;
});

watch(deliveryMode, (mode) => {
    form.delivery_mode = mode;

    if (mode === 'existing') {
        form.delivery_recipient_name = '';
        form.delivery_recipient_phone = '';
        form.delivery_full_address = '';
        if (deliverySearch.value.trim() !== '' && form.shipping_type === 'delivery') {
            debouncedFetchDeliveryOptions();
        }

        return;
    }

    form.delivery_id = '';
    deliverySearch.value = '';
    deliveryOptions.value = [];
    selectedDeliverySnapshot.value = null;
});

watch(() => form.shipping_type, (shippingType) => {
    if (shippingType === 'delivery') {
        return;
    }

    deliveryMode.value = 'new';
    deliverySearch.value = '';
    form.shipping_fee = '';
    form.delivery_id = '';
    form.delivery_recipient_name = '';
    form.delivery_recipient_phone = '';
    form.delivery_full_address = '';
    deliveryOptions.value = [];
    selectedDeliverySnapshot.value = null;
});

watch(customerSearch, () => {
    if (customerMode.value !== 'existing') {
        return;
    }

    debouncedFetchCustomerOptions();
});

watch(deliverySearch, () => {
    if (form.shipping_type !== 'delivery' || deliveryMode.value !== 'existing') {
        return;
    }

    debouncedFetchDeliveryOptions();
});

watch(
    () => form.customer_id,
    (id) => {
        if (!id) {
            selectedCustomerSnapshot.value = null;
            return;
        }

        const selected = customerOptions.value.find((customer) => String(customer.id) === String(id));
        if (selected) {
            selectedCustomerSnapshot.value = selected;
        }
    },
);

watch(
    () => form.delivery_id,
    (id) => {
        if (!id) {
            selectedDeliverySnapshot.value = null;
            return;
        }

        const selected = deliveryOptions.value.find((delivery) => String(delivery.id) === String(id));
        if (selected) {
            selectedDeliverySnapshot.value = selected;
        }
    },
);

watch(
    () => props.catalogFilters?.search,
    (value) => {
        catalogSearch.value = value || '';
    },
);

watch(
    () => props.catalogFilters?.category_id,
    (value) => {
        selectedCategoryId.value = value || '';
    },
);
</script>

<template>
    <AppLayout title="Cashier">
        <Head title="Cashier" />

        <div class="space-y-6">
            <div class="rounded-3xl border border-pink-200 bg-gradient-to-r from-pink-100/70 via-white to-pink-50 p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-pink-600">Cashier Desk</p>
                <h1 class="mt-2 text-2xl font-bold text-pink-950">Buat Order Baru</h1>
                <p class="mt-1 text-sm text-pink-700">Pilih bouquet dari katalog atau buat custom bouquet langsung dari kasir.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <section class="space-y-4 rounded-3xl border border-pink-200/80 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-pink-950">Catalog</h2>
                            <p class="text-xs text-pink-700">Klik item untuk menambahkan ke cart.</p>
                        </div>
                        <div class="inline-flex rounded-xl border border-pink-200 bg-pink-50 p-1">
                            <BaseButton
                                :variant="catalogMode === 'catalog' ? 'primary' : 'ghost'"
                                size="sm"
                                @click="catalogMode = 'catalog'"
                            >
                                Bouquet
                            </BaseButton>
                            <BaseButton
                                v-if="canCustomBouquet"
                                :variant="catalogMode === 'custom' ? 'primary' : 'ghost'"
                                size="sm"
                                @click="catalogMode = 'custom'"
                            >
                                Custom
                            </BaseButton>
                        </div>
                    </div>

                    <div v-if="catalogMode === 'catalog'" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input
                                v-model="catalogSearch"
                                type="text"
                                placeholder="Cari nama / serial bouquet..."
                                class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                @keyup.enter="applyCatalogFilters"
                            >
                            <select
                                v-model="selectedCategoryId"
                                class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                @change="applyCatalogFilters"
                            >
                                <option value="">Semua kategori</option>
                                <option
                                    v-for="category in bouquetCategories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex justify-end">
                            <BaseButton size="sm" @click="applyCatalogFilters">
                                Terapkan Filter
                            </BaseButton>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <button
                                v-for="unit in catalogBouquets"
                                :key="unit.id"
                                type="button"
                                class="group rounded-2xl border border-pink-200 p-3 text-left transition hover:border-pink-400 hover:bg-pink-50"
                                @click="addCatalogItem(unit)"
                            >
                                <div class="flex items-start gap-3">
                                    <img
                                        v-if="unit.image_url"
                                        :src="unit.image_url"
                                        :alt="unit.name"
                                        class="h-16 w-16 rounded-xl object-cover"
                                    >
                                    <div
                                        v-else
                                        class="flex h-16 w-16 items-center justify-center rounded-xl bg-pink-100 text-pink-500"
                                    >
                                        <ShoppingBag class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-pink-950">{{ unit.name }}</p>
                                        <p class="text-xs text-pink-700">{{ unit.type?.category?.name }} • {{ unit.type?.name }}</p>
                                        <p class="mt-2 text-sm font-bold text-pink-800">{{ formatCurrency(unit.price) }}</p>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div
                            v-if="catalogBouquets.length === 0"
                            class="rounded-2xl border border-dashed border-pink-200 bg-pink-50/50 p-6 text-center text-sm text-pink-700"
                        >
                            Bouquet tidak ditemukan untuk filter ini.
                        </div>

                        <div
                            v-if="bouquetUnits.links && bouquetUnits.links.length > 3"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-pink-200 bg-pink-50/40 px-3 py-2"
                        >
                            <p class="text-xs text-pink-700">
                                Menampilkan {{ bouquetUnits.from || 0 }} - {{ bouquetUnits.to || 0 }} dari {{ bouquetUnits.total || 0 }}
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="(link, index) in bouquetUnits.links"
                                    :key="`catalog-page-${index}`"
                                    type="button"
                                    :disabled="!link.url"
                                    class="rounded-lg border px-2.5 py-1 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="link.active
                                        ? 'border-pink-600 bg-pink-600 text-white'
                                        : 'border-pink-200 bg-white text-pink-700 hover:bg-pink-50'"
                                    @click="goToCatalogPage(link.url)"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-3 rounded-2xl border border-pink-200 bg-pink-50/50 p-4">
                        <div class="flex items-center gap-2 text-pink-900">
                            <WandSparkles class="h-4 w-4" />
                            <p class="text-sm font-semibold">Tambah Custom Bouquet</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-pink-800">Kategori</label>
                                <select
                                    v-model="customDraft.custom_category_id"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                >
                                    <option value="">Pilih kategori</option>
                                    <option
                                        v-for="category in bouquetCategories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-pink-800">Nama Bouquet</label>
                                <input
                                    v-model="customDraft.custom_name"
                                    type="text"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    placeholder="Contoh: Buket Surprise Pagi"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-pink-800">Harga Jual</label>
                                <input
                                    v-model="customDraft.custom_price"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    placeholder="0"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-pink-800">Amount Uang (jika ada)</label>
                                <input
                                    v-model="customDraft.money_amount"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    placeholder="0"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-pink-800">Foto Referensi / Custom</label>
                            <div class="flex items-center gap-4">
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="w-full text-xs text-pink-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100"
                                    @change="onCustomImageChange"
                                >
                                <div v-if="customImagePreview" class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-pink-200">
                                    <img :src="customImagePreview" class="h-full w-full object-cover">
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-pink-800">Sender Name (opsional)</label>
                                <input
                                    v-model="customDraft.sender_name"
                                    type="text"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    placeholder="Nama pengirim"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-pink-800">Greeting Card (opsional)</label>
                                <input
                                    v-model="customDraft.greeting_card"
                                    type="text"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    placeholder="Teks ucapan"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-pink-800">Catatan Produksi (opsional)</label>
                            <textarea
                                v-model="customDraft.custom_note"
                                rows="2"
                                class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                placeholder="Contoh: dominan mawar peach, wrapping nude"
                            />
                        </div>

                        <InputError :message="customFormError" />

                        <BaseButton
                            variant="success"
                            class="inline-flex items-center"
                            @click="addCustomItem"
                        >
                            Tambah Custom ke Cart
                        </BaseButton>
                    </div>
                </section>

                <section class="space-y-4 rounded-3xl border border-pink-200/80 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-pink-950">Cart</h2>
                        <div class="inline-flex items-center gap-2 rounded-xl bg-pink-50 px-3 py-1.5 text-xs font-medium text-pink-700">
                            <ShoppingCart class="h-4 w-4" />
                            {{ cartItems.length }} item
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-pink-800">Customer</label>
                            <div class="mb-2 inline-flex rounded-xl border border-pink-200 bg-pink-50 p-1">
                                <BaseButton
                                    :variant="customerMode === 'existing' ? 'primary' : 'ghost'"
                                    size="sm"
                                    @click="customerMode = 'existing'"
                                >
                                    Existing
                                </BaseButton>
                                <BaseButton
                                    :variant="customerMode === 'new' ? 'primary' : 'ghost'"
                                    size="sm"
                                    @click="customerMode = 'new'"
                                >
                                    Customer Baru
                                </BaseButton>
                            </div>

                            <div v-if="customerMode === 'existing'" class="space-y-2">
                                <input
                                    v-model="customerSearch"
                                    type="text"
                                    placeholder="Cari nama / no hp customer..."
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                >
                                <p class="text-[11px] text-pink-700">
                                    Ketik untuk cari customer. Menampilkan hingga {{ LOOKUP_LIMIT }} hasil teratas.
                                </p>
                                <select
                                    v-model="form.customer_id"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    :disabled="customerOptions.length === 0"
                                >
                                    <option value="">Pilih customer</option>
                                    <option
                                        v-for="customer in customerOptions"
                                        :key="customer.id"
                                        :value="customer.id"
                                    >
                                        {{ customer.name }} - {{ customer.phone_number }} | {{ customer.orders_count ?? 0 }} order | {{ customer.order_items_count ?? customer.order_details_count ?? 0 }} barang
                                    </option>
                                </select>
                                <p v-if="customerLookupLoading" class="text-[11px] text-pink-700">
                                    Mencari customer...
                                </p>
                                <p v-else-if="customerSearch.trim() && customerOptions.length === 0" class="text-[11px] text-pink-700">
                                    Customer tidak ditemukan.
                                </p>
                                <p v-else-if="!customerSearch.trim()" class="text-[11px] text-pink-700">
                                    Data belum ditampilkan. Mulai ketik kata kunci customer.
                                </p>
                                <div
                                    v-if="selectedCustomer"
                                    class="rounded-xl border border-pink-200 bg-pink-50 px-3 py-2 text-xs text-pink-800"
                                >
                                    <p class="font-semibold text-pink-900">{{ selectedCustomer.name }} ({{ selectedCustomer.phone_number }})</p>
                                    <p>{{ selectedCustomer.orders_count ?? 0 }} order, {{ selectedCustomer.order_items_count ?? selectedCustomer.order_details_count ?? 0 }} barang</p>
                                </div>
                            </div>

                            <div v-else class="space-y-2">
                                <input
                                    v-model="form.new_customer_name"
                                    type="text"
                                    placeholder="Nama customer baru"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                >
                                <input
                                    v-model="form.new_customer_phone_number"
                                    type="text"
                                    placeholder="No. HP customer baru"
                                    class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                >
                            </div>

                            <InputError :message="form.errors.customer_id" class="mt-1" />
                            <InputError :message="form.errors.new_customer_name" class="mt-1" />
                            <InputError :message="form.errors.new_customer_phone_number" class="mt-1" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-pink-800">Mode Pengiriman</label>
                            <div class="inline-flex rounded-xl border border-pink-200 bg-pink-50 p-1">
                                <BaseButton
                                    :variant="form.shipping_type === 'delivery' ? 'primary' : 'ghost'"
                                    size="sm"
                                    @click="form.shipping_type = 'delivery'"
                                >
                                    Delivery
                                </BaseButton>
                                <BaseButton
                                    :variant="form.shipping_type === 'pickup' ? 'primary' : 'ghost'"
                                    size="sm"
                                    @click="form.shipping_type = 'pickup'"
                                >
                                    Pickup
                                </BaseButton>
                            </div>
                            <InputError :message="form.errors.shipping_type" class="mt-1" />

                            <div v-if="form.shipping_type === 'delivery'" class="mt-3 space-y-2 rounded-2xl border border-pink-200 bg-pink-50/50 p-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-pink-800">Ongkir</label>
                                    <input
                                        v-model="form.shipping_fee"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                        placeholder="0"
                                    >
                                    <InputError :message="form.errors.shipping_fee" class="mt-1" />
                                </div>

                                <div class="inline-flex rounded-xl border border-pink-200 bg-white p-1">
                                    <BaseButton
                                        :variant="deliveryMode === 'existing' ? 'primary' : 'ghost'"
                                        size="sm"
                                        @click="deliveryMode = 'existing'"
                                    >
                                        Terdaftar
                                    </BaseButton>
                                    <BaseButton
                                        :variant="deliveryMode === 'new' ? 'primary' : 'ghost'"
                                        size="sm"
                                        @click="deliveryMode = 'new'"
                                    >
                                        Delivery Baru
                                    </BaseButton>
                                </div>

                                <div v-if="deliveryMode === 'existing'" class="space-y-2">
                                    <input
                                        v-model="deliverySearch"
                                        type="text"
                                        placeholder="Cari nama / no hp / alamat delivery..."
                                        class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    >
                                    <p class="text-[11px] text-pink-700">
                                        Ketik untuk cari referensi delivery. Menampilkan hingga {{ LOOKUP_LIMIT }} hasil teratas.
                                    </p>
                                    <select
                                        v-model="form.delivery_id"
                                        class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                        :disabled="deliveryOptions.length === 0"
                                    >
                                        <option value="">Pilih data delivery</option>
                                        <option
                                            v-for="delivery in deliveryOptions"
                                            :key="delivery.id"
                                            :value="delivery.id"
                                        >
                                            {{ delivery.recipient_name }} - {{ delivery.recipient_phone }}
                                        </option>
                                    </select>
                                    <p v-if="deliveryLookupLoading" class="text-[11px] text-pink-700">
                                        Mencari referensi delivery...
                                    </p>
                                    <p v-else-if="deliverySearch.trim() && deliveryOptions.length === 0" class="text-[11px] text-pink-700">
                                        Referensi delivery tidak ditemukan.
                                    </p>
                                    <p v-else-if="!deliverySearch.trim()" class="text-[11px] text-pink-700">
                                        Data belum ditampilkan. Mulai ketik kata kunci delivery.
                                    </p>

                                    <div
                                        v-if="selectedDelivery"
                                        class="rounded-xl border border-pink-200 bg-white px-3 py-2 text-xs text-pink-800"
                                    >
                                        <p class="font-semibold text-pink-900">{{ selectedDelivery.recipient_name }} ({{ selectedDelivery.recipient_phone }})</p>
                                        <p>{{ selectedDelivery.full_address }}</p>
                                    </div>
                                </div>

                                <div v-else class="space-y-2">
                                    <input
                                        v-model="form.delivery_recipient_name"
                                        type="text"
                                        placeholder="Nama penerima"
                                        class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    >
                                    <input
                                        v-model="form.delivery_recipient_phone"
                                        type="text"
                                        placeholder="No. HP penerima"
                                        class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                    >
                                    <textarea
                                        v-model="form.delivery_full_address"
                                        rows="2"
                                        class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                        placeholder="Alamat lengkap penerima"
                                    />
                                </div>
                            </div>

                            <InputError :message="form.errors.delivery_mode" class="mt-1" />
                            <InputError :message="form.errors.delivery_id" class="mt-1" />
                            <InputError :message="form.errors.delivery_recipient_name" class="mt-1" />
                            <InputError :message="form.errors.delivery_recipient_phone" class="mt-1" />
                            <InputError :message="form.errors.delivery_full_address" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-pink-800">Shipping Date</label>
                            <input
                                v-model="form.shipping_date"
                                type="date"
                                class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                            >
                            <InputError :message="form.errors.shipping_date" class="mt-1" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-pink-800">Shipping Time</label>
                            <input
                                v-model="form.shipping_time"
                                type="time"
                                class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                            >
                            <InputError :message="form.errors.shipping_time" class="mt-1" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-pink-800">Down Payment (opsional)</label>
                            <input
                                v-model="form.down_payment"
                                type="number"
                                min="0"
                                :max="maxDownPayment > 0 ? maxDownPayment : null"
                                class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                                placeholder="0"
                            >
                            <p class="mt-1 text-[11px] text-pink-700">
                                Status pembayaran: <span class="font-semibold">{{ paymentStatusPreview }}</span>
                                <span v-if="orderGrandTotal > 0"> • Sisa bayar: <span class="font-semibold">{{ formatCurrency(remainingPayment) }}</span></span>
                            </p>
                            <p class="mt-1 text-[11px] text-pink-700">
                                Maksimal DP: <span class="font-semibold">{{ formatCurrency(maxDownPayment) }}</span> (subtotal item).
                            </p>
                            <InputError :message="form.errors.down_payment" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-pink-800">Catatan Order (opsional)</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                            placeholder="Catatan tambahan untuk order"
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <div v-if="cartItems.length === 0" class="rounded-2xl border border-dashed border-pink-200 bg-pink-50/50 p-8 text-center text-sm text-pink-700">
                        Cart masih kosong. Tambahkan item dari catalog.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="item in cartItems"
                            :key="item.cart_id"
                            class="rounded-2xl border border-pink-200 bg-pink-50/40 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-pink-950">{{ item.display_name }}</p>
                                    <p class="text-xs text-pink-700">{{ item.display_category }} • {{ item.mode === 'custom' ? 'Custom' : 'Catalog' }}</p>
                                </div>
                                <BaseButton
                                    variant="destructive"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click="removeCartItem(item.cart_id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </BaseButton>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <span class="text-xs font-medium text-pink-700">Bouquet Item</span>
                                <p class="text-sm font-bold text-pink-900">{{ formatCurrency(lineTotal(item)) }}</p>
                            </div>

                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <input
                                    v-model="item.sender_name"
                                    type="text"
                                    class="w-full rounded-xl border-pink-200 text-xs focus:border-pink-400 focus:ring-pink-300"
                                    placeholder="Sender name (opsional)"
                                >
                                <input
                                    v-model="item.greeting_card"
                                    type="text"
                                    class="w-full rounded-xl border-pink-200 text-xs focus:border-pink-400 focus:ring-pink-300"
                                    placeholder="Greeting card (opsional)"
                                >
                            </div>
                        </div>
                    </div>

                    <InputError :message="form.errors.details" />
                    <InputError :message="form.errors['details.0.mode']" />

                    <div class="rounded-2xl bg-pink-100/80 p-4">
                        <div class="space-y-2 text-sm text-pink-800">
                            <div class="flex items-center justify-between">
                                <span>Subtotal Item</span>
                                <span class="text-xl font-bold text-pink-950">{{ formatCurrency(cartTotal) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Ongkir</span>
                                <span class="font-semibold text-pink-900">{{ formatCurrency(shippingFeeAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Total</span>
                                <span class="font-semibold text-pink-900">{{ formatCurrency(orderGrandTotal) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>DP</span>
                                <span class="font-semibold text-pink-900">{{ formatCurrency(downPaymentAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Sisa Bayar</span>
                                <span class="font-semibold text-pink-900">{{ formatCurrency(remainingPayment) }}</span>
                            </div>
                        </div>
                    </div>

                    <BaseButton
                        variant="success"
                        class="w-full text-lg"
                        size="lg"
                        :disabled="form.processing || cartItems.length === 0"
                        @click="submitOrder"
                    >
                        {{ form.processing ? 'Menyimpan order...' : 'Checkout Order' }}
                    </BaseButton>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
