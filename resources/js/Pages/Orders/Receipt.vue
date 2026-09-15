<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Printer, ArrowLeft, Download, FileText, Receipt as ReceiptIcon, Calendar, User, Phone, MapPin, Truck } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import html2canvas from 'html2canvas';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
});

const layoutMode = ref('thermal'); // 'thermal' or 'landscape'

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value) || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTime = (timeStr) => {
    if (!timeStr) return '';
    return timeStr.slice(0, 5);
};

const itemSubtotal = () => {
    const details = Array.isArray(props.order?.order_details) ? props.order.order_details : [];
    return details.reduce((sum, item) => sum + (Number(item?.subtotal) || 0), 0);
};

const orderTypeLabel = computed(() => {
    return props.order.order_type === 'inventory' ? 'Supply' : 'Bouquet';
});

const printReceipt = () => {
    window.print();
};

const goBack = () => {
    window.history.back();
};

const exportAsPNG = async () => {
    const selector = layoutMode.value === 'thermal' ? '.receipt-thermal-content' : '.sales-order-landscape';
    const el = document.querySelector(selector);
    if (!el) return;
    
    try {
        const canvas = await html2canvas(el, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            scrollX: 0,
            scrollY: 0,
            logging: false,
            onclone: (clonedDoc) => {
                const target = clonedDoc.querySelector(selector);
                if (target) {
                    target.style.transform = 'none';
                    target.style.margin = '0 auto';
                }
            }
        });
        
        const prefix = layoutMode.value === 'thermal' ? 'Struk_Kasir' : 'Sales_Order';
        const link = document.createElement('a');
        link.download = `${prefix}_#${props.order.id}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (e) {
        console.error('Failed to export to PNG', e);
        alert('Gagal mengekspor dokumen menjadi PNG.');
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 flex flex-col items-center">
        <Head :title="`${layoutMode === 'thermal' ? 'Struk' : 'Sales Order'} #${order.id}`" />

        <!-- Dedicated Full-Width Top Navigation Bar (Hidden on Print) -->
        <header class="w-full bg-white border-b border-gray-200 shadow-sm sticky top-0 z-30 print:hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <!-- Back Button -->
                <div class="flex items-center">
                    <BaseButton variant="secondary" size="sm" @click="goBack" class="rounded-xl">
                        <ArrowLeft class="w-4 h-4 mr-1.5" />
                        Kembali
                    </BaseButton>
                </div>

                <!-- Mode Switcher Tabs (Center) -->
                <div class="flex bg-slate-100 p-1 rounded-xl border border-gray-200 text-xs font-semibold">
                    <button 
                        @click="layoutMode = 'thermal'"
                        class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg transition-all"
                        :class="layoutMode === 'thermal' ? 'bg-white text-gray-950 shadow-sm font-bold' : 'text-gray-600 hover:text-gray-900'"
                    >
                        <ReceiptIcon class="w-4 h-4 text-emerald-600" />
                        Struk Thermal (58mm)
                    </button>
                    <button 
                        @click="layoutMode = 'landscape'"
                        class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg transition-all"
                        :class="layoutMode === 'landscape' ? 'bg-white text-gray-950 shadow-sm font-bold' : 'text-gray-600 hover:text-gray-900'"
                    >
                        <FileText class="w-4 h-4 text-blue-600" />
                        Sales Order (Landscape A4/A5)
                    </button>
                </div>

                <!-- Action Buttons (Right) -->
                <div class="flex items-center gap-2">
                    <BaseButton variant="secondary" size="sm" @click="exportAsPNG" class="rounded-xl">
                        <Download class="w-4 h-4 mr-1.5" />
                        Download PNG
                    </BaseButton>
                    <BaseButton variant="primary" size="sm" @click="printReceipt" class="rounded-xl">
                        <Printer class="w-4 h-4 mr-1.5" />
                        {{ layoutMode === 'thermal' ? 'Cetak Struk' : 'Cetak Dokumen' }}
                    </BaseButton>
                </div>
            </div>
        </header>

        <!-- Document Preview Canvas Area -->
        <main class="w-full flex-1 p-4 md:p-8 flex flex-col items-center justify-start">
            <!-- ========================================== -->
            <!-- 1. THERMAL RECEIPT MODE (PORTRAIT 58mm)    -->
            <!-- ========================================== -->
            <div 
                v-if="layoutMode === 'thermal'"
                class="bg-white shadow-xl rounded-xl p-4 sm:p-5 w-full max-w-[58mm] font-mono text-[11px] text-gray-900 receipt-thermal-content border border-gray-200"
            >
            <div class="text-center space-y-1.5 pb-4 border-b border-dashed border-gray-300">
                <div class="flex justify-center mb-1.5">
                    <img 
                        v-if="settings.logo_url" 
                        :src="settings.logo_url" 
                        alt="Logo" 
                        class="h-12 w-auto object-contain"
                    />
                </div>
                <h1 class="text-base font-extrabold uppercase tracking-wide text-gray-950">{{ settings.store_name || 'Bees Fleur Florist' }}</h1>
                <p class="text-[11px] text-gray-600 whitespace-pre-line leading-relaxed">{{ settings.address }}</p>
                <p class="text-[11px] font-semibold text-gray-700">WhatsApp / Telp: {{ settings.phone }}</p>
            </div>

            <!-- Invoice & Customer Meta Section -->
            <div class="py-2.5 border-b border-gray-400 space-y-1 text-[10px]">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">NO. INVOICE:</span>
                    <span class="font-bold text-xs text-gray-900">#{{ order.id }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">TIPE TRANSAKSI:</span>
                    <span class="font-bold text-xs text-gray-900">
                        [{{ orderTypeLabel }}]
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">TANGGAL PESAN:</span>
                    <span>{{ formatDate(order.created_at) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">KASIR:</span>
                    <span class="font-medium">{{ order.user?.name || '-' }}</span>
                </div>

                <!-- Customer Details -->
                <div class="pt-2 mt-1.5 border-t border-gray-300 space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-600">NAMA CUSTOMER:</span>
                        <span class="font-bold text-gray-900">{{ order.customer?.name || 'Umum / Walk-in' }}</span>
                    </div>
                    <div v-if="order.customer?.phone_number" class="flex justify-between">
                        <span class="text-gray-600">NO. TELP:</span>
                        <span class="font-medium text-gray-800">{{ order.customer.phone_number }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">METODE:</span>
                        <span class="font-semibold uppercase text-[11px] text-gray-900">
                            {{ order.shipping_type === 'delivery' ? 'Pengiriman (Delivery)' : 'Ambil di Toko (Pickup)' }}
                        </span>
                    </div>
                    <div v-if="order.shipping_date" class="flex justify-between">
                        <span class="text-gray-600">JADWAL {{ order.shipping_type === 'delivery' ? 'KIRIM' : 'AMBIL' }}:</span>
                        <span class="font-medium">{{ formatDate(order.shipping_date) }} {{ formatTime(order.shipping_time) }}</span>
                    </div>
                    <!-- Penerima Delivery jika ada -->
                    <div v-if="order.shipping_type === 'delivery' && order.delivery" class="pt-1 text-[10px] space-y-0.5 border-t border-gray-300 mt-1">
                        <p class="font-bold text-gray-800">Tujuan Penerima:</p>
                        <p class="text-gray-900 font-semibold">{{ order.delivery.recipient_name }} ({{ order.delivery.recipient_phone }})</p>
                        <p class="text-gray-600">{{ order.delivery.full_address }}</p>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="py-2.5">
                <table class="w-full text-[10px]">
                    <thead>
                        <tr class="border-b border-gray-400 text-gray-800 font-bold">
                            <th class="pb-1 text-left">ITEM / PRODUK</th>
                            <th class="pb-1 text-center w-8">QTY</th>
                            <th class="pb-1 text-right w-20">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="item in order.order_details" :key="item.id" class="align-top">
                            <td class="py-1.5 pr-1">
                                <div class="font-bold text-gray-900">
                                    {{ item.item_type === 'bouquet' ? (item.bouquet_unit?.name || 'Custom Bouquet') : (item.inventory_item?.name || 'Barang Retail') }}
                                </div>
                                <div class="text-[9px] text-gray-500 mt-0.5">
                                    ({{ item.item_type === 'bouquet' ? (item.bouquet_unit?.type?.category?.name || 'Bouquet') : (item.inventory_item?.category?.name || 'Supply') }})
                                </div>

                                <div v-if="item.money_bouquet" class="text-[9px] text-gray-800 font-bold mt-0.5">
                                    + Uang Buket: {{ formatCurrency(item.money_bouquet) }}
                                </div>
                                <div v-if="item.sender_name || item.greeting_card" class="text-[9px] text-gray-500 mt-0.5 italic">
                                    <span v-if="item.sender_name">Dari: {{ item.sender_name }}. </span>
                                    <span v-if="item.greeting_card">Ucapan: "{{ item.greeting_card }}"</span>
                                </div>
                            </td>
                            <td class="py-1.5 text-center text-gray-800 font-medium">{{ item.quantity }}</td>
                            <td class="py-1.5 text-right font-bold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals & Payment Details -->
            <div class="pt-2 border-t border-gray-400 space-y-1 text-[10px]">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal Barang:</span>
                    <span class="font-semibold text-gray-900">{{ formatCurrency(itemSubtotal()) }}</span>
                </div>
                <div v-if="Number(order.shipping_fee || 0) > 0" class="flex justify-between text-gray-600">
                    <span>Ongkos Kirim:</span>
                    <span class="font-semibold text-gray-900">+ {{ formatCurrency(order.shipping_fee) }}</span>
                </div>
                
                <div class="flex justify-between text-xs font-black text-gray-950 border-t border-gray-400 pt-1 mt-1">
                    <span>TOTAL TAGIHAN:</span>
                    <span>{{ formatCurrency(order.total) }}</span>
                </div>

                <div class="flex justify-between items-center text-gray-700 pt-0.5">
                    <span>Status Pembayaran:</span>
                    <span class="font-bold text-[11px] uppercase text-gray-900">
                        [{{ order.payment_status === 'paid' ? 'Lunas' : order.payment_status === 'dp' ? 'Down Payment (DP)' : 'Belum Lunas' }}]
                    </span>
                </div>

                <div v-if="order.down_payment > 0" class="flex justify-between text-gray-600">
                    <span>Telah Dibayar (DP):</span>
                    <span class="font-semibold text-gray-900">{{ formatCurrency(order.down_payment) }}</span>
                </div>
                <div v-if="order.payment_status !== 'paid'" class="flex justify-between font-bold text-gray-900 border-t border-gray-300 pt-1">
                    <span>SISA PEMBAYARAN:</span>
                    <span class="text-gray-950">{{ formatCurrency(Math.max(0, order.total - (order.down_payment || 0))) }}</span>
                </div>
            </div>

            <!-- Customer / Order Notes if any -->
            <div v-if="order.description" class="mt-2 pt-1.5 border-t border-gray-300 text-[9px] text-gray-600">
                <span class="font-bold text-gray-800">Catatan:</span> {{ order.description }}
            </div>

            <div class="border-t border-gray-400 my-3"></div>

            <!-- Footer & Terms -->
            <div class="text-center space-y-1.5 text-[10px] text-gray-600">
                <p class="italic whitespace-pre-line leading-relaxed">{{ settings.receipt_note || 'Terima kasih telah berbelanja di tempat kami!' }}</p>
                <p class="font-bold uppercase tracking-widest text-gray-900">*** NOTA PEMBAYARAN SAH ***</p>
                <p class="text-[9px] text-gray-400">Simpan nota ini sebagai bukti transaksi</p>
            </div>

            <!-- Feed Spacer (Area kosong bawah agar kertas terdorong keluar penuh & tidak terpotong pisau printer) -->
            <div class="h-20 md:h-24 receipt-feed-spacer print:block" aria-hidden="true"></div>
        </div>

        <!-- ========================================== -->
        <!-- 2. LANDSCAPE SALES ORDER / SURAT JALAN     -->
        <!-- ========================================== -->
        <div 
            v-else
            class="bg-white shadow-xl rounded-xl p-8 w-full max-w-5xl text-gray-900 sales-order-landscape border border-gray-200"
        >
            <!-- Header (Company Info & Document Title) -->
            <div class="flex justify-between items-start pb-5 border-b-2 border-gray-800">
                <div class="flex items-center gap-4">
                    <img 
                        v-if="settings.logo_url" 
                        :src="settings.logo_url" 
                        alt="Logo" 
                        class="h-16 w-auto object-contain"
                    />
                    <div>
                        <h1 class="text-xl font-black uppercase tracking-wider text-gray-950">{{ settings.store_name || 'Bees Fleur Florist' }}</h1>
                        <p class="text-xs text-gray-600 max-w-md mt-0.5 leading-relaxed">{{ settings.address }}</p>
                        <p class="text-xs font-semibold text-gray-700 mt-1">Telp / WA: {{ settings.phone }}</p>
                    </div>
                </div>

                <div class="text-right">
                    <h2 class="text-2xl font-black tracking-tight text-gray-950 uppercase">SALES ORDER</h2>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mt-0.5">& SURAT JALAN PENGIRIMAN</p>
                    <div class="mt-2 text-xs font-mono inline-block bg-gray-100 px-3 py-1 rounded border border-gray-300 font-bold">
                        NO: #{{ order.id }}
                    </div>
                </div>
            </div>

            <!-- Two-Column Meta Section (Order Info & Customer/Shipping Info) -->
            <div class="grid grid-cols-2 gap-8 py-5 border-b border-gray-200 text-xs">
                <!-- Left: Order Details -->
                <div class="space-y-1.5">
                    <h3 class="font-bold uppercase tracking-wider text-gray-700 text-[11px] pb-1 border-b border-gray-100">Informasi Pemesanan</h3>
                    <div class="flex justify-between py-0.5">
                        <span class="text-gray-500">Tipe Transaksi:</span>
                        <span class="font-bold text-gray-900 uppercase">[{{ orderTypeLabel }}]</span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span class="text-gray-500">Tanggal Transaksi:</span>
                        <span class="font-medium text-gray-900">{{ formatDate(order.created_at) }}</span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span class="text-gray-500">Kasir / Admin:</span>
                        <span class="font-medium text-gray-900">{{ order.user?.name || '-' }}</span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span class="text-gray-500">Status Pembayaran:</span>
                        <span class="font-bold text-gray-900 uppercase">
                            [{{ order.payment_status === 'paid' ? 'Lunas' : order.payment_status === 'dp' ? 'Down Payment (DP)' : 'Belum Lunas' }}]
                        </span>
                    </div>
                </div>

                <!-- Right: Customer & Delivery Target -->
                <div class="space-y-1.5">
                    <h3 class="font-bold uppercase tracking-wider text-gray-700 text-[11px] pb-1 border-b border-gray-100">Tujuan & Pengiriman</h3>
                    <div class="flex justify-between py-0.5">
                        <span class="text-gray-500">Customer:</span>
                        <span class="font-bold text-gray-900">{{ order.customer?.name || 'Umum / Walk-in' }} ({{ order.customer?.phone_number || '-' }})</span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span class="text-gray-500">Metode Penyerahan:</span>
                        <span class="font-bold text-gray-900 uppercase">
                            {{ order.shipping_type === 'delivery' ? 'Pengiriman (Delivery Kurir)' : 'Ambil Sendiri di Toko (Pickup)' }}
                        </span>
                    </div>
                    <div v-if="order.shipping_date" class="flex justify-between py-0.5">
                        <span class="text-gray-500">Jadwal {{ order.shipping_type === 'delivery' ? 'Kirim' : 'Ambil' }}:</span>
                        <span class="font-bold text-gray-900">{{ formatDate(order.shipping_date) }} {{ formatTime(order.shipping_time) }}</span>
                    </div>
                    <div v-if="order.shipping_type === 'delivery' && order.delivery" class="text-gray-700 pt-1">
                        <span class="text-gray-500 block">Penerima & Alamat:</span>
                        <span class="font-semibold text-gray-900 block">{{ order.delivery.recipient_name }} ({{ order.delivery.recipient_phone }})</span>
                        <span class="text-gray-600 block text-[11px]">{{ order.delivery.full_address }}</span>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="py-5">
                <table class="w-full text-xs text-left border border-gray-200">
                    <thead class="bg-gray-100 uppercase text-gray-700 font-bold border-b border-gray-200 text-[11px]">
                        <tr>
                            <th class="p-2.5 w-10 text-center">No</th>
                            <th class="p-2.5">Deskripsi Produk & Keterangan Rangkaian</th>
                            <th class="p-2.5 w-24 text-center">Kategori</th>
                            <th class="p-2.5 w-16 text-center">Qty</th>
                            <th class="p-2.5 w-32 text-right">Harga Satuan</th>
                            <th class="p-2.5 w-36 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="(item, idx) in order.order_details" :key="item.id" class="align-top">
                            <td class="p-2.5 text-center font-medium text-gray-500">{{ idx + 1 }}</td>
                            <td class="p-2.5">
                                <div class="font-bold text-gray-950 text-sm">
                                    {{ item.item_type === 'bouquet' ? (item.bouquet_unit?.name || 'Custom Bouquet') : (item.inventory_item?.name || 'Barang Retail') }}
                                </div>

                                <!-- Detail Khusus Bouquet / Tim Perangkai -->
                                <div v-if="item.money_bouquet" class="mt-1 font-semibold text-gray-800">
                                    • Pecahan Uang Buket: {{ formatCurrency(item.money_bouquet) }}
                                </div>
                                <div v-if="item.sender_name" class="mt-0.5 text-gray-600">
                                    • Pengirim (Sender): <span class="font-medium text-gray-900">{{ item.sender_name }}</span>
                                </div>
                                <div v-if="item.greeting_card" class="mt-1 p-2 bg-gray-50 rounded border border-gray-200 font-sans italic text-gray-700">
                                    <span class="font-bold not-italic text-gray-900">Kartu Ucapan:</span> "{{ item.greeting_card }}"
                                </div>
                            </td>
                            <td class="p-2.5 text-center text-gray-600 font-medium">
                                {{ item.item_type === 'bouquet' ? (item.bouquet_unit?.type?.category?.name || 'Bouquet') : (item.inventory_item?.category?.name || 'Supply') }}
                            </td>
                            <td class="p-2.5 text-center font-bold text-gray-900 text-sm">{{ item.quantity }}</td>
                            <td class="p-2.5 text-right font-mono text-gray-700">{{ formatCurrency(item.unit_price ?? item.price ?? (item.item_type === 'bouquet' ? item.bouquet_unit?.price : item.inventory_item?.price)) }}</td>
                            <td class="p-2.5 text-right font-mono font-bold text-gray-950 text-sm">{{ formatCurrency(item.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Summary & Signatures Section (Landscape 2-Columns) -->
            <div class="grid grid-cols-12 gap-6 pt-2 border-t border-gray-200 text-xs">
                <!-- Left: Notes & Signature Boxes (7 Columns) -->
                <div class="col-span-7 flex flex-col justify-between">
                    <div>
                        <div v-if="order.description" class="p-2.5 bg-gray-50 rounded border border-gray-200 mb-3">
                            <span class="font-bold text-gray-900">Catatan Khusus Pesanan:</span>
                            <p class="text-gray-700 mt-0.5 whitespace-pre-line">{{ order.description }}</p>
                        </div>
                        <div v-if="settings.receipt_note" class="p-2.5 bg-pink-50/50 rounded border border-pink-100 text-[11px] text-gray-700 mb-3 whitespace-pre-line leading-relaxed">
                            <p class="font-semibold text-pink-900 mb-0.5">Catatan Pembayaran & Rekening:</p>
                            {{ settings.receipt_note }}
                        </div>
                        <p class="text-[11px] text-gray-500 italic mb-4">
                            * Barang yang sudah diterima dalam kondisi baik tidak dapat dikembalikan tanpa persetujuan pihak Florist.
                        </p>
                    </div>

                    <!-- 3 Signature Boxes: Pengirim, Kurir, Penerima -->
                    <div class="grid grid-cols-3 gap-3 text-center text-[11px] pt-2">
                        <div class="border border-gray-300 rounded p-2 flex flex-col justify-between h-28">
                            <span class="text-gray-500 font-semibold">Dibuat / Kasir</span>
                            <span class="border-t border-gray-300 pt-1 font-bold text-gray-900">{{ order.user?.name || '( .................. )' }}</span>
                        </div>
                        <div class="border border-gray-300 rounded p-2 flex flex-col justify-between h-28">
                            <span class="text-gray-500 font-semibold">Kurir / Driver</span>
                            <span class="border-t border-gray-300 pt-1 font-bold text-gray-900">( .................. )</span>
                        </div>
                        <div class="border border-gray-300 rounded p-2 flex flex-col justify-between h-28">
                            <span class="text-gray-500 font-semibold">Tanda Terima Pelanggan</span>
                            <span class="border-t border-gray-300 pt-1 font-bold text-gray-900">{{ order.customer?.name || '( .................. )' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Financial Summary Calculation (5 Columns) -->
                <div class="col-span-5 bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-2 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal Produk:</span>
                        <span class="font-semibold font-mono text-gray-900">{{ formatCurrency(itemSubtotal()) }}</span>
                    </div>
                    <div v-if="Number(order.shipping_fee || 0) > 0" class="flex justify-between text-gray-600">
                        <span>Biaya Ongkos Kirim:</span>
                        <span class="font-semibold font-mono text-gray-900">+ {{ formatCurrency(order.shipping_fee) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-black text-gray-950 border-t-2 border-gray-800 pt-2 mt-2">
                        <span>TOTAL TAGIHAN:</span>
                        <span class="font-mono text-base">{{ formatCurrency(order.total) }}</span>
                    </div>
                    <div v-if="order.down_payment > 0" class="flex justify-between text-emerald-700 font-semibold pt-1">
                        <span>Telah Dibayar (DP):</span>
                        <span class="font-mono">{{ formatCurrency(order.down_payment) }}</span>
                    </div>
                    <div class="flex justify-between font-extrabold text-sm border-t border-dashed border-gray-300 pt-2"
                         :class="order.payment_status === 'paid' ? 'text-emerald-700' : 'text-rose-700'">
                        <span>{{ order.payment_status === 'paid' ? 'STATUS:' : 'SISA TAGIHAN:' }}</span>
                        <span class="font-mono">{{ order.payment_status === 'paid' ? 'LUNAS' : formatCurrency(Math.max(0, order.total - (order.down_payment || 0))) }}</span>
                    </div>
                </div>
            </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
@media print {
    @page {
        margin: 0 !important;
        size: auto;
    }
    body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .min-h-screen {
        min-height: auto !important;
        background: transparent !important;
        padding: 0 !important;
    }
    .receipt-thermal-content {
        width: 48mm !important;
        max-width: 48mm !important;
        padding: 0 !important;
        margin: 0 auto !important;
        border: none !important;
        box-shadow: none !important;
        font-size: 9px !important;
        line-height: 1.3 !important;
        color: #000000 !important;
        word-break: break-word !important;
    }
    .receipt-thermal-content * {
        color: #000000 !important;
        border-color: #000000 !important;
    }
    .receipt-feed-spacer {
        display: block !important;
        height: 28mm !important;
        width: 100% !important;
    }
    .sales-order-landscape {
        max-width: 100% !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
    }
}

/* Base styles for thermal receipt */
.receipt-thermal-content {
    line-height: 1.35;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    -webkit-font-smoothing: antialiased;
}

/* Styles for landscape sales order */
.sales-order-landscape {
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}
</style>
