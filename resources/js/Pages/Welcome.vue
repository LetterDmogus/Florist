<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
    },
    phpVersion: {
        type: String,
    },
});
</script>

<template>
    <Head title="Welcome - Bees Fleur" />

    <div class="min-h-screen bg-[#fff8fc] text-foreground font-sans selection:bg-pink-100">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 w-full border-b border-pink-100 bg-white/80 backdrop-blur-md">
            <div class="container flex h-16 items-center justify-between px-4 md:px-8">
                <div class="flex items-center gap-2">
                    <img src="/images/bees-fleur-logo.svg" alt="Bees Fleur Logo" class="h-8 w-auto">
                    <span class="sr-only">Bees Fleur</span>
                </div>
                
                <div v-if="canLogin" class="flex items-center gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="text-sm font-semibold text-pink-800 transition-colors hover:text-pink-600"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="inline-flex h-9 items-center justify-center rounded-xl bg-pink-100 px-5 text-sm font-bold text-pink-700 shadow-sm transition-all hover:bg-pink-200 hover:scale-105 active:scale-95"
                        >
                            Login
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main>
            <!-- Hero Section -->
            <section class="container flex flex-col items-center justify-center space-y-8 py-24 text-center md:py-32">
                <div class="space-y-6 max-w-4xl">
                    <div class="flex justify-center mb-6">
                        <img src="/images/bees-fleur.png" alt="Bees Fleur Logo" class="h-32 md:h-48 w-auto">
                    </div>
                    <h1 class="sr-only">Bees Fleur Florist</h1>
                    <p class="mx-auto max-w-[700px] text-pink-900/70 md:text-xl/relaxed lg:text-2xl/relaxed font-medium">
                        Sistem Informasi Kasir Terintegrasi Manajemen Stok Berbasis SKU
                    </p>
                    <p class="mx-auto max-w-[600px] text-pink-800/60 md:text-lg">
                         Solusi manajemen bisnis florist yang praktis, tenang, dan efisien.
                    </p>
                </div>
                
                <div class="flex flex-col gap-4 min-[400px]:flex-row py-8">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="route('login')"
                        class="inline-flex h-12 items-center justify-center rounded-2xl bg-pink-600 px-10 text-sm font-bold text-white shadow-lg shadow-pink-200 transition-all hover:bg-pink-700 hover:scale-105 active:scale-95"
                    >
                        Mulai Sekarang
                    </Link>
                    <Link
                        v-else
                        :href="route('dashboard')"
                        class="inline-flex h-12 items-center justify-center rounded-2xl bg-pink-600 px-10 text-sm font-bold text-white shadow-lg shadow-pink-200 transition-all hover:bg-pink-700 hover:scale-105 active:scale-95"
                    >
                        Kembali ke Dashboard
                    </Link>
                </div>
            </section>

            <!-- Simple Features Overview -->
            <section class="container py-12 md:py-24">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 px-4">
                    <div class="flex flex-col items-start gap-4 bg-white/60 backdrop-blur-sm border border-pink-100 p-8 rounded-[2rem] shadow-sm hover:shadow-md transition-shadow">
                        <div class="rounded-2xl bg-pink-100 p-3 text-pink-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-pink-950">Manajemen POS</h3>
                        <p class="text-pink-900/60 text-sm leading-relaxed">Transaksi cepat dan akurat untuk setiap pesanan bunga Anda dengan antarmuka yang nyaman.</p>
                    </div>
                    
                    <div class="flex flex-col items-start gap-4 bg-white/60 backdrop-blur-sm border border-pink-100 p-8 rounded-[2rem] shadow-sm hover:shadow-md transition-shadow">
                        <div class="rounded-2xl bg-pink-100 p-3 text-pink-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-pink-950">Stok Berbasis SKU</h3>
                        <p class="text-pink-900/60 text-sm leading-relaxed">Kelola inventaris bouquet dan unit barang secara terorganisir, menghindari selisih stok.</p>
                    </div>

                    <div class="flex flex-col items-start gap-4 bg-white/60 backdrop-blur-sm border border-pink-100 p-8 rounded-[2rem] shadow-sm hover:shadow-md transition-shadow">
                        <div class="rounded-2xl bg-pink-100 p-3 text-pink-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-pink-950">Laporan Real-time</h3>
                        <p class="text-pink-900/60 text-sm leading-relaxed">Pantau pergerakan stok dan penjualan kapan saja secara instan untuk keputusan bisnis yang tepat.</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-pink-100 py-12 bg-white/30">
            <div class="container flex flex-col items-center justify-center gap-4 px-4 md:px-8">
                <p class="text-sm text-pink-900/50 font-medium">
                    &copy; 2026 Bees Fleur Florist POS
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Minimalist styles to ensure variables are used fallback is provided */
.container {
    max-width: 1200px;
    margin: 0 auto;
}
</style>
