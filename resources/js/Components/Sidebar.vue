<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    UserCog
} from 'lucide-vue-next';

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
    if (route().current(item.route + '*')) {
        return true;
    }
    if (item.children) {
        return item.children.some(child => route().current(child.route + '*'));
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
            name: 'Orders',
            icon: ShoppingCart,
            route: 'orders.index',
            show: can('orders.view')
        },
        {
            name: 'Order Status',
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
    <div class="flex flex-col h-full bg-gradient-to-b from-[#fff9fd] via-[#fff4fa] to-[#ffeff8] border-r border-pink-200/80 shadow-sm transition-all duration-300 w-64 md:w-72">
        <!-- Logo -->
        <div class="px-6 py-8 flex items-center gap-3">
            <div class="w-10 h-10 bg-pink-200 rounded-xl flex items-center justify-center border border-pink-300/90">
                <Flower2 class="w-6 h-6 text-pink-700" />
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-pink-950">Bees Fleur</h1>
                <p class="text-xs text-pink-700 uppercase tracking-widest font-semibold">Florist POS</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
            <template v-for="item in menuItems" :key="item.name">
                <div>
                    <Link 
                        :href="route(item.route)" 
                        class="group flex items-center px-4 py-3 text-sm font-medium rounded-2xl border transition-all duration-200"
                        :class="isItemActive(item) ? 'bg-pink-600 border-pink-600 text-white shadow-sm' : 'border-transparent text-pink-800 hover:bg-white hover:border-pink-200 hover:text-pink-950 hover:shadow-sm'"
                    >
                        <component 
                            :is="item.icon" 
                            class="mr-3 h-5 w-5 transition-transform duration-200 group-hover:scale-110" 
                            :class="isItemActive(item) ? 'text-white' : 'text-pink-700 group-hover:text-pink-900'"
                        />
                        {{ item.name }}
                    </Link>
                    
                    <!-- Sub-menu -->
                    <div v-if="item.children && isItemActive(item)" class="mt-1 ml-9 space-y-1">
                        <Link 
                            v-for="child in item.children" 
                            :key="child.name"
                            :href="route(child.route, child.params || {})"
                            class="block px-4 py-2 text-xs font-medium rounded-xl transition-all duration-200"
                            :class="route().current(child.route, child.params || {}) ? 'text-pink-950 font-bold bg-white border border-pink-200' : 'text-pink-700 hover:text-pink-950 hover:bg-white/90'"
                        >
                            {{ child.name }}
                        </Link>
                    </div>
                </div>
            </template>
        </nav>

        <!-- User Profile (Quick Access) -->
        <div class="p-4 border-t border-pink-200/80 bg-pink-100/60">
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="w-10 h-10 rounded-full bg-pink-300 flex items-center justify-center text-xs font-bold text-pink-900 uppercase">
                    {{ user?.name?.charAt(0) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-pink-950 truncate">{{ user?.name }}</p>
                    <p class="text-xs text-pink-700 truncate capitalize">
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

/* Base Transition for hover effects */
a {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
