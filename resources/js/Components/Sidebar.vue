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
    ShieldCheck
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user);

const hasRole = (role) => {
    return user.value?.roles?.some(r => r.name === role);
};

const hasAnyRole = (roles) => {
    return user.value?.roles?.some(r => roles.includes(r.name));
};

const isItemActive = (item) => {
    if (route().current(item.route + '*') && (item.name !== 'Reports' || route().current('reports.*'))) {
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
            show: true
        },
        {
            name: 'Orders',
            icon: ShoppingCart,
            route: 'orders.index',
            show: hasAnyRole(['super-admin', 'admin', 'kasir', 'manager'])
        },
        {
            name: 'Customers',
            icon: Users,
            route: 'customers.index',
            show: hasAnyRole(['super-admin', 'admin', 'kasir', 'manager'])
        },
        {
            name: 'Inventory',
            icon: Package,
            route: 'item-units.index',
            show: hasAnyRole(['super-admin', 'admin', 'manager'])
        },
        {
            name: 'Bouquets',
            icon: Flower2,
            route: 'bouquet-units.index',
            show: hasAnyRole(['super-admin', 'admin', 'manager', 'kasir']),
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
            show: hasAnyRole(['super-admin', 'admin', 'manager'])
        },
        {
            name: 'Stock Movements',
            icon: History,
            route: 'stock-movements.index',
            show: hasAnyRole(['super-admin', 'admin', 'manager'])
        },
        {
            name: 'Reports',
            icon: BarChart3,
            route: 'dashboard', // Placeholder
            show: hasAnyRole(['super-admin', 'admin', 'manager'])
        },
        {
            name: 'Role Management',
            icon: ShieldCheck,
            route: 'roles.index',
            show: hasRole('super-admin')
        },
        {
            name: 'Settings',
            icon: Settings,
            route: 'dashboard', // Placeholder
            show: hasRole('super-admin')
        }
    ];

    return items.filter(item => item.show);
});
</script>

<template>
    <div class="flex flex-col h-full bg-white border-r border-secondary/50 shadow-sm transition-all duration-300 w-64 md:w-72">
        <!-- Logo -->
        <div class="px-6 py-8 flex items-center gap-3">
            <div class="w-10 h-10 bg-primary/20 rounded-xl flex items-center justify-center text-primary-foreground border border-primary/30">
                <Flower2 class="w-6 h-6 text-accent" />
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground">Bees Fleur</h1>
                <p class="text-xs text-muted-foreground uppercase tracking-widest font-semibold">Florist POS</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
            <template v-for="item in menuItems" :key="item.name">
                <div>
                    <Link 
                        :href="route(item.route)" 
                        class="group flex items-center px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200"
                        :class="isItemActive(item) ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-secondary hover:text-black'"
                    >
                        <component 
                            :is="item.icon" 
                            class="mr-3 h-5 w-5 transition-transform duration-200 group-hover:scale-110" 
                            :class="isItemActive(item) ? 'text-primary-foreground' : 'text-muted-foreground/70 group-hover:text-black'"
                        />
                        {{ item.name }}
                    </Link>
                    
                    <!-- Sub-menu -->
                    <div v-if="item.children && isItemActive(item)" class="mt-1 ml-9 space-y-1">
                        <Link 
                            v-for="child in item.children" 
                            :key="child.name"
                            :href="route(child.route)"
                            class="block px-4 py-2 text-xs font-medium rounded-xl transition-all duration-200"
                            :class="route().current(child.route) ? 'text-primary font-bold bg-primary/5' : 'text-muted-foreground hover:text-foreground hover:bg-secondary/50'"
                        >
                            {{ child.name }}
                        </Link>
                    </div>
                </div>
            </template>
        </nav>

        <!-- User Profile (Quick Access) -->
        <div class="p-4 border-t border-secondary/50 bg-secondary/20">
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="w-10 h-10 rounded-full bg-primary/30 flex items-center justify-center text-xs font-bold text-accent uppercase">
                    {{ user?.name?.charAt(0) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-foreground truncate">{{ user?.name }}</p>
                    <p class="text-xs text-muted-foreground truncate capitalize">
                        {{ user?.roles?.[0]?.name || 'User' }}
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
    background: var(--color-secondary);
    border-radius: 10px;
}

/* Base Transition for hover effects */
a {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
