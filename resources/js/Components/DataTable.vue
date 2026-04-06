<script setup>
import { ref, watch, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, Trash2, ChevronLeft, ChevronRight, ArrowUpDown, ChevronUp, ChevronDown, Eye } from 'lucide-vue-next';
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
        default: () => ({ search: '', trashed: false, sort_by: 'created_at', sort_dir: 'desc', per_page: 10 }),
    },
    searchPlaceholder: {
        type: String,
        default: 'Search...',
    },
    routeName: {
        type: String,
        required: true,
    },
    viewRoute: {
        type: String,
        default: null,
    },
    showRecycleBin: {
        type: Boolean,
        default: true,
    },
    // Optional additional filters for the route
    additionalParams: {
        type: Object,
        default: () => ({}),
    },
    extraFilters: {
        type: Object,
        default: () => ({}),
    }
});

const emit = defineEmits(['view-detail']);

const search = ref(props.filters.search || '');
const sortBy = ref(props.filters.sort_by || 'created_at');
const sortDir = ref(props.filters.sort_dir || 'desc');
const perPage = ref(props.filters.per_page || 10);

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
    ...props.extraFilters,
    search: search.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    per_page: perPage.value,
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

watch(perPage, () => {
    updateFilters();
});

watch(() => props.extraFilters, () => {
    updateFilters();
}, { deep: true });

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
                <slot name="extra-filters" />
                
                <select 
                    v-model="perPage"
                    class="bg-white border border-secondary rounded-xl text-sm focus:ring-2 focus:ring-primary/50 transition-all py-2"
                >
                    <option :value="10">10 / page</option>
                    <option :value="20">20 / page</option>
                    <option :value="30">30 / page</option>
                </select>

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
                    {{ isTrashed ? 'Trashed' : 'Recycle' }}
                </button>
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
                            <td v-for="col in columns" :key="col.key" class="px-6 py-4 whitespace-nowrap">
                                <slot :name="`cell-${col.key}`" :item="item">
                                    {{ col.render ? col.render(item) : item[col.key] }}
                                </slot>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center gap-2">
                                    <button
                                        v-if="viewRoute"
                                        type="button"
                                        @click="emit('view-detail', item)"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors border border-blue-100"
                                        title="Lihat Detail & Audit Trail"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </button>
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
