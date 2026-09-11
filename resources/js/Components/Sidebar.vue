<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { 
    LayoutDashboard, 
    Users, 
    ShoppingCart, 
    Package, 
    Flower2, 
    Truck, 
    BarChart3, 
    Settings, 
    History,
    ShieldCheck,
    UserCog,
    ChevronLeft,
    ChevronRight,
    Boxes
} from 'lucide-vue-next';

const props = defineProps({
    isCollapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggleCollapse']);

const page = usePage();
const user = computed(() => page.props.auth.user);

const can = (permission) => {
    if (user.value?.permissions?.includes('*')) {
        return true;
    }

    return user.value?.permissions?.includes(permission);
};

const hasRole = (role) => {
    return user.value?.roles?.includes(role);
};

const hasAnyRole = (roles) => {
    return user.value?.roles?.some(role => roles.includes(role));
};

const isItemActive = (item) => {
    // Current route name
    const current = route().current();
    // Exact match for cashier routes to avoid cashier.index matching cashier.inventory
    if (item.route === 'cashier.index') {
        return current === 'cashier.index';
    }

    // Direct match or wildcard match for the main item
    if (route().current(item.route + '*')) {
        return true;
    }

    // Special case for reports prefix
    if (item.name === 'Reports' && current.startsWith('reports.')) {
        return true;
    }

    // Check children match
    if (item.children) {
        return item.children.some(child => {
            // Using wildcard to ignore parameters
            return route().current(child.route + '*');
        });
    }

    return false;
};

const menuItems = computed(() => {
    const items = [
        {
            name: 'Dashboard',
            icon: LayoutDashboard,
            route: 'dashboard',
            show: can('dashboard.view')
        },
        {
            name: 'POS Bouquet',
            icon: ShoppingCart,
            route: 'cashier.index',
            show: can('orders.view')
        },
        {
            name: 'POS Barang Gudang',
            icon: Boxes,
            route: 'cashier.inventory',
            show: can('orders.view')
        },
        {
            name: 'Status Pesanan',
            icon: History,
            route: 'orders.status.index',
            show: can('orders.status.view')
        },
        {
            name: 'Customers',
            icon: Users,
            route: 'customers.index',
            show: can('customers.view')
        },
        {
            name: 'Inventory',
            icon: Package,
            route: 'item-units.index',
            show: can('inventory.view'),
            children: [
                { name: 'Inventory Units', route: 'item-units.index' },
                { name: 'Categories', route: 'item-categories.index' },
            ]
        },
        {
            name: 'Bouquets',
            icon: Flower2,
            route: 'bouquet-units.index',
            show: can('bouquets.view'),
            children: [
                { name: 'Bundles / Units', route: 'bouquet-units.index' },
                { name: 'Bouquet Types', route: 'bouquet-types.index' },
                { name: 'Categories', route: 'bouquet-categories.index' },
            ]
        },
        {
            name: 'Deliveries',
            icon: Truck,
            route: 'deliveries.index',
            show: can('deliveries.view')
        },
        {
            name: 'Stock Movements',
            icon: History,
            route: 'stock-movements.index',
            show: can('stock.view')
        },
        {
            name: 'Reports',
            icon: BarChart3,
            route: 'reports.index',
            show: can('reports.view'),
            children: [
                { name: 'Laporan Penjualan', route: 'reports.sales.index' },
                { name: 'Laporan Pembelian', route: 'reports.purchases.index' },
            ]
        },
        {
            name: 'User Management',
            icon: UserCog,
            route: 'users.index',
            show: can('users.manage')
        },
        {
            name: 'Role Management',
            icon: ShieldCheck,
            route: 'roles.index',
            show: can('roles.manage'),
            children: [
                ...(page.props.roles_list || []).map(role => ({
                    name: role.name,
                    route: 'roles.edit',
                    params: { role: role.id }
                })),
                { name: '+ Tambah Role', route: 'roles.create' },
            ]
        },
        {
            name: 'Activity Log',
            icon: History,
            route: 'activities.index',
            show: can('logs.view')
        },
        {
            name: 'Backup System',
            icon: Package,
            route: 'backups.index',
            show: can('logs.view')
        },
        {
            name: 'Settings',
            icon: Settings,
            route: 'settings.index',
            show: can('settings.manage')
        }
    ];

    return items.filter(item => item.show);
});
</script>

<template>
    <div 
        class="flex flex-col h-full bg-gradient-to-b from-[#fffafc] via-[#fff4fa] to-[#ffeff8] border-r border-pink-200/80 shadow-xs transition-all duration-300 relative"
        :class="isCollapsed ? 'w-18 md:w-20' : 'w-60 md:w-64'"
    >
        <!-- Logo (Tanpa container kotak tebal, minimalis dan bersih) -->
        <div class="px-4 py-4 transition-all duration-300 flex items-center justify-center">
            <Link 
                :href="route('dashboard')" 
                class="flex items-center justify-center transition-transform hover:opacity-90 active:scale-95"
            >
                <img 
                    v-if="isCollapsed" 
                    src="/images/bees-fleur.png" 
                    alt="Bees Fleur" 
                    class="h-10 w-10 object-contain"
                />
                <img 
                    v-else 
                    src="/images/bees-fleur.png" 
                    alt="Bees Fleur Logo" 
                    class="h-12 w-auto object-contain max-w-[210px]"
                />
            </Link>
        </div>

        <!-- Navigation (Menu lebih compact & rapi) -->
        <nav class="flex-1 px-3 space-y-1 overflow-y-auto custom-scrollbar">
            <template v-for="item in menuItems" :key="item.name">
                <div class="relative group">
                    <Link 
                        :href="route(item.route)" 
                        :title="isCollapsed ? item.name : ''"
                        class="flex items-center text-xs font-semibold rounded-xl border"
                        :class="[
                            isCollapsed 
                                ? 'justify-center p-2.5 transition-none' 
                                : 'px-3 py-2 transition-all duration-150',
                            isItemActive(item) 
                                ? 'bg-pink-600 border-pink-600 text-white shadow-xs' 
                                : isCollapsed
                                    ? 'border-transparent text-pink-800 hover:bg-white hover:border-pink-200/70 hover:text-pink-950'
                                    : 'border-transparent text-pink-800 hover:bg-white hover:border-pink-200/70 hover:text-pink-950'
                        ]"
                    >
                        <component 
                            :is="item.icon" 
                            class="h-3.5 w-3.5 shrink-0" 
                            :class="[
                                isCollapsed 
                                    ? 'mr-0' 
                                    : 'mr-2.5 transition-transform duration-150 group-hover:scale-105',
                                isItemActive(item) 
                                    ? 'text-white' 
                                    : isCollapsed 
                                        ? 'text-pink-600 hover:text-pink-800'
                                        : 'text-pink-600 group-hover:text-pink-800'
                            ]"
                        />
                        <span v-if="!isCollapsed" class="truncate">{{ item.name }}</span>
                    </Link>
                    
                    <!-- Sub-menu (Expanded only) -->
                    <div v-if="!isCollapsed && item.children && isItemActive(item)" class="mt-0.5 ml-7 space-y-0.5">
                        <Link 
                            v-for="child in item.children" 
                            :key="child.name"
                            :href="route(child.route, child.params || {})"
                            class="block px-3 py-1.5 text-[11px] font-medium rounded-lg transition-all duration-150"
                            :class="route().current(child.route + '*') ? 'text-pink-950 font-bold bg-white border border-pink-200/80 shadow-2xs' : 'text-pink-700 hover:text-pink-950 hover:bg-white/80'"
                        >
                            {{ child.name }}
                        </Link>
                    </div>
                </div>
            </template>
        </nav>

        <!-- User Profile (Quick Access) -->
        <div class="p-2.5 border-t border-pink-200/80 bg-pink-100/50 transition-all duration-300">
            <div 
                class="flex items-center gap-2.5 rounded-xl"
                :class="isCollapsed ? 'justify-center p-1' : 'px-2 py-1.5'"
                :title="isCollapsed ? `${user?.name} (${user?.roles?.[0] || 'User'})` : ''"
            >
                <div class="w-8 h-8 shrink-0 rounded-full bg-pink-300 flex items-center justify-center text-[11px] font-bold text-pink-900 uppercase shadow-2xs">
                    {{ user?.name?.charAt(0) }}
                </div>
                <div v-if="!isCollapsed" class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-pink-950 truncate leading-tight">{{ user?.name }}</p>
                    <p class="text-[10px] text-pink-700 truncate capitalize">
                        {{ user?.roles?.[0] || 'User' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgb(244 114 182 / 35%);
    border-radius: 10px;
}
</style>
