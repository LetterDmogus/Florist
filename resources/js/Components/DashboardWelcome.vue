<script setup>
import { ShoppingBag, Users, Truck, AlertCircle, Plus, ClipboardList, Package } from 'lucide-vue-next';
import StatCard from '@/Components/StatCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import SalesChart from '@/Components/SalesChart.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    chartData: {
        type: Object,
        required: true,
    }
});

const stats = [
    { title: 'Pesanan Hari Ini', value: '12', icon: ShoppingBag, description: '3 pesanan sedang diproses', trend: '+15%', trendUp: true },
    { title: 'Pelanggan Baru', value: '5', icon: Users, description: 'Minggu ini', trend: '+2', trendUp: true },
    { title: 'Pengiriman Aktif', value: '4', icon: Truck, description: 'Selesai tepat waktu', trend: 'Sedang Berjalan', trendUp: true },
    { title: 'Stok Menipis', value: '2', icon: AlertCircle, description: 'Perlu restok segera', trend: 'Penting', trendUp: false },
];
</script>

<template>
    <div class="space-y-8">
        <!-- Hero Header -->
        <div class="relative overflow-hidden bg-gradient-to-br from-pink-100/50 via-white to-pink-50/30 p-8 rounded-[2rem] border border-pink-100/60 shadow-sm">
            <div class="relative z-10 max-w-2xl">
                <h1 class="text-3xl font-bold text-pink-950">Halo, {{ $page.props.auth.user.name }}! 👋</h1>
                <p class="mt-2 text-pink-900/70 text-lg leading-relaxed">
                    Selamat datang di sistem manajemen Bees Fleur. Mari kita buat hari ini penuh dengan keindahan bunga dan layanan terbaik untuk pelanggan.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <BaseButton :href="route('orders.index')" variant="primary">
                        <Plus class="w-5 h-5" />
                        Buat Pesanan Baru
                    </BaseButton>
                    <BaseButton :href="route('stock-movements.index')" variant="secondary">
                        <ClipboardList class="w-5 h-5" />
                        Cek Inventori
                    </BaseButton>
                </div>
            </div>
            
            <!-- Abstract Shapes for Vibe -->
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-pink-200/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-24 w-32 h-32 bg-pink-300/10 rounded-full blur-2xl"></div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard v-for="(stat, index) in stats" :key="index" v-bind="stat" />
        </div>

        <!-- Sales Chart -->
        <SalesChart :chart-data="chartData" />

        <!-- Secondary Section (Recent Orders or Shortcuts) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-pink-950">Pesanan Terakhir</h3>
                    <Link :href="route('orders.index')" class="text-sm font-medium text-pink-600 hover:text-pink-700 underline underline-offset-4">
                        Lihat Semua
                    </Link>
                </div>
                <div class="bg-white rounded-3xl border border-pink-100 overflow-hidden shadow-sm">
                    <div class="p-8 text-center text-muted-foreground/60">
                        <Package class="w-12 h-12 mx-auto mb-4 text-pink-200" />
                        <p>Belum ada aktivitas pesanan baru untuk ditampilkan saat ini.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-xl font-bold text-pink-950">Akses Cepat</h3>
                <div class="space-y-3">
                    <Link v-for="link in [
                        { name: 'Daftar Bouquet', icon: ShoppingBag, href: route('bouquet-units.index') },
                        { name: 'Data Pelanggan', icon: Users, href: route('customers.index') },
                        { name: 'Laporan Penjualan', icon: ClipboardList, href: route('dashboard') },
                    ]" :key="link.name" :href="link.href" 
                    class="flex items-center gap-4 p-4 bg-white border border-pink-100 rounded-2xl hover:bg-pink-50/50 hover:border-pink-200 transition-all group">
                        <div class="p-2 bg-pink-50 rounded-xl text-pink-600 group-hover:scale-110 transition-transform">
                            <component :is="link.icon" class="w-5 h-5" />
                        </div>
                        <span class="font-medium text-pink-900">{{ link.name }}</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
