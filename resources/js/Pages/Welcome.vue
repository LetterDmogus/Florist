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

    <div class="min-h-screen bg-background text-foreground font-sans selection:bg-primary/30">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="container flex h-16 items-center justify-between px-4 md:px-8">
                <div class="flex items-center gap-2">
                    <span class="text-xl font-bold tracking-tight text-foreground">Bees Fleur</span>
                </div>
                
                <div v-if="canLogin" class="flex items-center gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="text-sm font-medium transition-colors hover:text-primary"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-sm font-medium transition-colors hover:text-primary"
                        >
                            Log in
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main>
            <!-- Hero Section -->
            <section class="container flex flex-col items-center justify-center space-y-4 py-24 text-center md:py-32">
                <div class="space-y-4">
                    <h1 class="text-4xl font-bold tracking-tighter sm:text-5xl md:text-6xl lg:text-7xl">
                        Bees Fleur
                    </h1>
                    <p class="mx-auto max-w-[700px] text-muted-foreground md:text-xl/relaxed lg:text-2xl/relaxed">
                        Sistem Informasi Kasir Terintegrasi Manajemen Stok Berbasis SKU
                    </p>
                    <p class="mx-auto max-w-[600px] text-muted-foreground/80 md:text-lg">
                         Solusi manajemen bisnis florist yang praktis, tenang, dan efisien.
                    </p>
                </div>
                
                <div class="flex flex-col gap-4 min-[400px]:flex-row py-8">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="route('login')"
                        class="inline-flex h-11 items-center justify-center rounded-md bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        Mulai Sekarang
                    </Link>
                    <Link
                        v-else
                        :href="route('dashboard')"
                        class="inline-flex h-11 items-center justify-center rounded-md bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        Kembali ke Dashboard
                    </Link>
                </div>
            </section>

            <!-- Simple Features Overview -->
            <section class="container py-12 md:py-24 border-t border-border/40">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="flex flex-col items-start gap-2 border border-border/40 p-6">
                        <div class="rounded-full bg-primary/10 p-2 text-primary">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">Manajemen POS</h3>
                        <p class="text-muted-foreground text-sm">Transaksi cepat dan akurat untuk setiap pesanan bunga Anda.</p>
                    </div>
                    
                    <div class="flex flex-col items-start gap-2 border border-border/40 p-6">
                        <div class="rounded-full bg-primary/10 p-2 text-primary">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">Stok Berbasis SKU</h3>
                        <p class="text-muted-foreground text-sm">Kelola inventaris bouquet dan unit barang secara terorganisir.</p>
                    </div>

                    <div class="flex flex-col items-start gap-2 border border-border/40 p-6">
                        <div class="rounded-full bg-primary/10 p-2 text-primary">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">Laporan Real-time</h3>
                        <p class="text-muted-foreground text-sm">Pantau pergerakan stok dan penjualan kapan saja secara instan.</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t py-12">
            <div class="container flex flex-col items-center justify-between gap-4 md:flex-row px-4">
                <p class="text-sm text-muted-foreground">
                    &copy; 2026 Bees Fleur. All rights reserved.
                </p>
                <p class="text-xs text-muted-foreground/50">
                    Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})
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
