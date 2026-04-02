<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, Trash2, Filter, ChevronLeft, ChevronRight, ArrowUpDown, ChevronUp, ChevronDown } from 'lucide-vue-next';
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
        default: () => ({ search: '', trashed: false, sort_by: 'created_at', sort_dir: 'desc' }),
    },
    searchPlaceholder: {
        type: String,
        default: 'Search...',
    },
    routeName: {
        type: String,
        required: true,
    },
    showRecycleBin: {
        type: Boolean,
        default: true,
    },
    // Optional additional filters for the route
    additionalParams: {
        type: Object,
        default: () => ({}),
    }
});

const search = ref(props.filters.search || '');
const sortBy = ref(props.filters.sort_by || 'created_at');
const sortDir = ref(props.filters.sort_dir || 'desc');

const parseTrashedFilter = (value) => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value === 1;
    if (typeof value === 'string') {
        return ['1', 'true', 'with', 'yes', 'on'].includes(value.toLowerCase());
    }
    return false;
};

const isTrashed = ref(parseTrashedFilter(props.filters.trashed));

const buildQueryParams = () => ({
    ...props.additionalParams,
    search: search.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(isTrashed.value ? { trashed: 1 } : {}),
});

const updateFilters = debounce(() => {
    router.get(route(props.routeName), buildQueryParams(), {
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

const handleSort = (key) => {
    if (sortBy.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = key;
        sortDir.value = 'asc';
    }
    updateFilters();
};

const resolveColumnSortKey = (column) => {
    return column.sortKey || column.key;
};

const goToPage = (url) => {
    if (url) {
        router.get(url, buildQueryParams(), {
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
                    v-if="showRecycleBin"
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
            <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-secondary/50 scrollbar-track-transparent">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-secondary/50 border-b border-secondary/50">
                        <tr>
                            <th 
                                v-for="col in columns" 
                                :key="col.key" 
                                class="px-6 py-4 font-semibold text-muted-foreground whitespace-nowrap"
                                :class="col.sortable !== false ? 'cursor-pointer select-none hover:text-pink-700' : ''"
                                @click="col.sortable !== false ? handleSort(resolveColumnSortKey(col)) : null"
                            >
                                <div class="flex items-center gap-2">
                                    {{ col.label }}
                                    <template v-if="col.sortable !== false">
                                        <ChevronUp v-if="sortBy === resolveColumnSortKey(col) && sortDir === 'asc'" class="w-3.5 h-3.5" />
                                        <ChevronDown v-else-if="sortBy === resolveColumnSortKey(col) && sortDir === 'desc'" class="w-3.5 h-3.5" />
                                        <ArrowUpDown v-else class="w-3.5 h-3.5 opacity-20" />
                                    </template>
                                </div>
                            </th>
                            <th class="px-6 py-4 font-semibold text-muted-foreground text-right sticky right-0 bg-secondary/50 shadow-[-10px_0_10px_-10px_rgba(0,0,0,0.1)]">Actions</th>
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
                            <td v-for="col in columns" :key="col.key" class="px-6 py-4 whitespace-nowrap">
                                <slot :name="`cell-${col.key}`" :item="item">
                                    {{ col.render ? col.render(item) : item[col.key] }}
                                </slot>
                            </td>
                            <td class="px-6 py-4 text-right sticky right-0 bg-white group-hover:bg-secondary/20 transition-colors shadow-[-10px_0_10px_-10px_rgba(0,0,0,0.1)]">
                                <div class="flex justify-end items-center gap-2">
                                    <slot name="actions" :item="item" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="data.links && data.links.length > 3" class="px-6 py-4 bg-secondary/10 border-t border-secondary/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs text-muted-foreground order-2 sm:order-1">
                    Showing {{ data.from }} to {{ data.to }} of {{ data.total }} entries
                </div>
                <div class="flex flex-wrap items-center justify-center gap-1 order-1 sm:order-2">
                    <template v-for="(link, k) in data.links" :key="k">
                        <button
                            v-if="k === 0"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="p-2 rounded-lg border border-secondary bg-white hover:bg-secondary/50 disabled:opacity-50 transition-all"
                            title="Previous"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button
                            v-else-if="k === data.links.length - 1"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="p-2 rounded-lg border border-secondary bg-white hover:bg-secondary/50 disabled:opacity-50 transition-all"
                            title="Next"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                        <button
                            v-else
                            @click="goToPage(link.url)"
                            :class="cn(
                                'px-3 py-1 rounded-lg text-xs font-medium border transition-all min-w-[32px]',
                                link.active 
                                    ? 'bg-pink-600 text-white border-pink-600' 
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
