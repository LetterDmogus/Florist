<script setup>
import { defineAsyncComponent, computed, ref } from 'vue';
import { ShoppingBag, Users, Truck, AlertCircle, Plus, ClipboardList, Package, RefreshCw } from 'lucide-vue-next';
import StatCard from '@/Components/StatCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { Link, router } from '@inertiajs/vue3';

const SalesChart = defineAsyncComponent(() => import('@/Components/SalesChart.vue'));

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    chartData: {
        type: Object,
        required: true,
    },
    recentOrders: {
        type: Array,
        required: true,
    }
});

const isRefreshing = ref(false);

const refreshData = () => {
    isRefreshing.value = true;
    router.get(route('dashboard'), { refresh: 1 }, {
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
        }
    });
};

const statsData = computed(() => [
    { title: 'Pesanan Hari Ini', value: props.stats.ordersToday.value, icon: ShoppingBag, description: props.stats.ordersToday.description, trend: props.stats.ordersToday.trend, trendUp: props.stats.ordersToday.trendUp },
    { title: 'Pelanggan Baru', value: props.stats.newCustomers.value, icon: Users, description: props.stats.newCustomers.description, trend: props.stats.newCustomers.trend, trendUp: props.stats.newCustomers.trendUp },
    { title: 'Pengiriman Aktif', value: props.stats.activeDeliveries.value, icon: Truck, description: props.stats.activeDeliveries.description, trend: props.stats.activeDeliveries.trend, trendUp: props.stats.activeDeliveries.trendUp },
    { title: 'Stok Menipis', value: props.stats.lowStock.value, icon: AlertCircle, description: props.stats.lowStock.description, trend: props.stats.lowStock.trend, trendUp: props.stats.lowStock.trendUp },
]);
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
                                    <BaseButton :href="route('cashier.index')" variant="primary">
                                        <Plus class="w-5 h-5" />
                                        Buat Pesanan Baru
                                    </BaseButton>
                                    <BaseButton :href="route('stock-movements.index')" variant="secondary">
                                        <ClipboardList class="w-5 h-5" />
                                        Cek Inventori
                                    </BaseButton>
                                    <BaseButton 
                                        @click="refreshData" 
                                        variant="secondary" 
                                        class="bg-white/80"
                                        :disabled="isRefreshing"
                                    >
                                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" />
                                        Perbarui Data
                                    </BaseButton>
                                </div>
            </div>
            
            <!-- Abstract Shapes for Vibe -->
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-pink-200/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-24 w-32 h-32 bg-pink-300/10 rounded-full blur-2xl"></div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard v-for="(stat, index) in statsData" :key="index" v-bind="stat" />
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
                    <div v-if="recentOrders.length > 0" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-pink-50/50 text-pink-900 border-b border-pink-100">
                                <tr>
                                    <th class="px-6 py-4 text-left font-bold uppercase tracking-wider text-[10px]">Pelanggan</th>
                                    <th class="px-6 py-4 text-left font-bold uppercase tracking-wider text-[10px]">Total</th>
                                    <th class="px-6 py-4 text-left font-bold uppercase tracking-wider text-[10px]">Status</th>
                                    <th class="px-6 py-4 text-right font-bold uppercase tracking-wider text-[10px]">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-pink-50">
                                <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-pink-50/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-pink-950">{{ order.customer }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-pink-900">Rp {{ new Intl.NumberFormat('id-ID').format(order.total) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="[
                                            'px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wide',
                                            order.status === 'completed' ? 'bg-green-100 text-green-700' : 
                                            order.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-pink-100 text-pink-700'
                                        ]">
                                            {{ order.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-pink-800/60 text-xs">{{ order.time }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-8 text-center text-muted-foreground/60">
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
