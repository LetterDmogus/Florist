<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, Trash2, Filter, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
    columns: {
        type: Array,
        required: true, // { label: string, key: string, sortable?: boolean, render?: function }
    },
    filters: {
        type: Object,
        default: () => ({ search: '', trashed: false }),
    },
    searchPlaceholder: {
        type: String,
        default: 'Search...',
    },
    routeName: {
        type: String,
        required: true,
    },
    // Optional additional filters for the route
    additionalParams: {
        type: Object,
        default: () => ({}),
    }
});

const search = ref(props.filters.search || '');
const isTrashed = ref(props.filters.trashed || false);

const updateFilters = debounce(() => {
    router.get(route(props.routeName), {
        ...props.additionalParams,
        search: search.value,
        trashed: isTrashed.value ? 'with' : '',
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch(search, () => {
    updateFilters();
});

const toggleTrashed = () => {
    isTrashed.value = !isTrashed.value;
    updateFilters();
};

const goToPage = (url) => {
    if (url) {
        router.get(url, {
            ...props.additionalParams,
            search: search.value,
            trashed: isTrashed.value ? 'with' : '',
        }, {
            preserveState: true,
        });
    }
};

</script>

<template>
    <div class="space-y-4">
        <!-- Header: Search & Filters -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-2xl shadow-sm border border-secondary/50">
            <div class="relative flex-1 max-w-md">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <input
                    v-model="search"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-full pl-10 pr-4 py-2 bg-secondary/30 border-none rounded-xl focus:ring-2 focus:ring-primary/50 text-sm transition-all"
                />
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="toggleTrashed"
                    :class="cn(
                        'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all border',
                        isTrashed 
                            ? 'bg-destructive/10 text-destructive border-destructive/20' 
                            : 'bg-white text-muted-foreground border-secondary hover:bg-secondary/50'
                    )"
                >
                    <Trash2 class="w-4 h-4" />
                    {{ isTrashed ? 'Viewing Trashed' : 'Recycle Bin' }}
                </button>
                <slot name="extra-filters" />
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-secondary/50 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-secondary/50 border-b border-secondary/50">
                        <tr>
                            <th v-for="col in columns" :key="col.key" class="px-6 py-4 font-semibold text-muted-foreground">
                                {{ col.label }}
                            </th>
                            <th class="px-6 py-4 font-semibold text-muted-foreground text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/50">
                        <tr v-if="data.data.length === 0">
                            <td :colspan="columns.length + 1" class="px-6 py-12 text-center text-muted-foreground">
                                No data found.
                            </td>
                        </tr>
                        <tr 
                            v-for="item in data.data" 
                            :key="item.id" 
                            class="hover:bg-secondary/20 transition-colors group"
                        >
                            <td v-for="col in columns" :key="col.key" class="px-6 py-4">
                                <slot :name="`cell-${col.key}`" :item="item">
                                    {{ col.render ? col.render(item) : item[col.key] }}
                                </slot>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center gap-2">
                                    <slot name="actions" :item="item" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="data.links.length > 3" class="px-6 py-4 bg-secondary/10 border-t border-secondary/50 flex items-center justify-between">
                <div class="text-xs text-muted-foreground">
                    Showing {{ data.from }} to {{ data.to }} of {{ data.total }} entries
                </div>
                <div class="flex items-center gap-1">
                    <template v-for="(link, k) in data.links" :key="k">
                        <button
                            v-if="k === 0"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="p-2 rounded-lg border border-secondary bg-white hover:bg-secondary/50 disabled:opacity-50 transition-all"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button
                            v-else-if="k === data.links.length - 1"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="p-2 rounded-lg border border-secondary bg-white hover:bg-secondary/50 disabled:opacity-50 transition-all"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                        <button
                            v-else
                            @click="goToPage(link.url)"
                            :class="cn(
                                'px-3 py-1 rounded-lg text-xs font-medium border transition-all',
                                link.active 
                                    ? 'bg-primary text-primary-foreground border-primary' 
                                    : 'bg-white text-muted-foreground border-secondary hover:bg-secondary/50'
                            )"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
