<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Sidebar from '@/Components/Sidebar.vue';
import { Menu, X, Bell } from 'lucide-vue-next';

defineProps({
    title: String,
});

const isSidebarOpen = ref(false);

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

// Close sidebar on navigation (mobile)
router.on('navigate', () => {
    isSidebarOpen.value = false;
});

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-[#fff8fc] font-sans text-foreground flex">
        <Head :title="title" />
        <Banner />

        <!-- Sidebar (Desktop) -->
        <aside class="hidden md:flex md:w-72 md:flex-col md:fixed md:inset-y-0 z-[50]">
            <Sidebar />
        </aside>

        <!-- Sidebar (Mobile Overlay) -->
        <div v-if="isSidebarOpen" 
             class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm md:hidden transition-all duration-300"
             @click="isSidebarOpen = false"
        ></div>
        
        <aside :class="[
            'fixed inset-y-0 left-0 z-[70] w-72 bg-[#fff1f7] transform transition-transform duration-300 ease-in-out md:hidden',
            isSidebarOpen ? 'translate-x-0' : '-translate-x-full'
        ]">
            <Sidebar />
            <!-- Close button for mobile -->
            <button @click="isSidebarOpen = false" class="absolute top-4 -right-12 p-2 bg-pink-100 rounded-full shadow-lg text-pink-900 border border-pink-200 md:hidden">
                <X class="w-6 h-6" />
            </button>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col md:pl-72 min-h-screen transition-all duration-300">
            <!-- Top Header -->
            <header class="sticky top-0 z-40 bg-[#fff3f9]/95 backdrop-blur-md border-b border-pink-200/80 h-16 flex items-center justify-between px-4 md:px-8">
                <div class="flex items-center gap-4">
                    <button @click="toggleSidebar" class="p-2 -ml-2 rounded-xl text-pink-800 hover:bg-pink-100 md:hidden transition-colors">
                        <Menu class="w-6 h-6" />
                    </button>
                    <h2 class="text-lg font-semibold text-pink-950 hidden sm:block">
                        {{ title }}
                    </h2>
                </div>

                <div class="flex items-center gap-2 md:gap-4">
                    <!-- Notifications (Placeholder) -->
                    <button class="p-2 rounded-xl text-pink-700 hover:bg-pink-100 hover:text-pink-900 transition-all duration-200">
                        <Bell class="w-5 h-5" />
                    </button>

                    <!-- Profile Dropdown -->
                    <div class="relative ms-2">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-pink-200 rounded-2xl focus:outline-none focus:border-pink-400 transition p-0.5 hover:scale-105 duration-200">
                                    <img class="size-9 rounded-[14px] object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                </button>
                                <button v-else class="flex items-center gap-2 px-3 py-1.5 rounded-2xl bg-pink-100/80 text-pink-900 hover:bg-pink-100 transition-colors border border-pink-200/70">
                                    <span class="text-sm font-medium">{{ $page.props.auth.user.name }}</span>
                                    <svg class="size-4 text-pink-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="p-1">
                                    <div class="px-3 py-2 text-xs font-semibold text-muted-foreground/60 uppercase tracking-wider">
                                        Akun Saya
                                    </div>
                                    <DropdownLink :href="route('profile.show')" class="rounded-xl">
                                        Profil & Keamanan
                                    </DropdownLink>
                                    <div class="border-t border-secondary/50 my-1" />
                                    <form @submit.prevent="logout">
                                        <DropdownLink as="button" class="rounded-xl text-destructive hover:bg-destructive/10">
                                            Keluar Sistem
                                        </DropdownLink>
                                    </form>
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 md:p-8">
                <!-- Page Heading -->
                <div v-if="$slots.header" class="mb-8">
                    <slot name="header" />
                </div>

                <slot />
            </main>

            <!-- Footer Small (optional) -->
            <footer class="p-4 text-center text-xs text-muted-foreground/50">
                &copy; 2026 Bees Fleur Florist POS
            </footer>
        </div>
    </div>
</template>

<style>
/* Smooth slide transition for sidebar on mobile */
.sidebar-enter-active, .sidebar-leave-active {
    transition: transform 0.3s ease;
}
.sidebar-enter-from, .sidebar-leave-to {
    transform: translateX(-100%);
}

/* Base styles for peaceful vibe */
body {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Scrollbar styling for main content */
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #E5E7EB;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #D1D5DB;
}
</style>
