<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import GlobalDetailModal from '@/Components/GlobalDetailModal.vue';
import axios from 'axios';
import { Plus, Edit2, Trash, RotateCcw, XCircle } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { cn } from '@/lib/utils';

const props = defineProps({
    tab: String,
    categories: Object, // Paginator
    categoryOptions: Array, // For select
    types: Object, // Paginator
    typeOptions: Array, // For select
    units: Object, // Paginator
    filters: Object,
});

const resolvedTypeOptions = computed(() => props.typeOptions || []);
const resolvedCategoryOptions = computed(() => props.categoryOptions || []);

// ─────────────────────────────────────────────────────────────────────────────
// Common State
// ─────────────────────────────────────────────────────────────────────────────
const activeTab = computed(() => props.tab || 'units');
const showModal = ref(false);
const showQuickDetail = ref(false);
const editingItem = ref(null);
const detailItem = ref(null);
const auditData = ref(null);

const openQuickDetail = async (item) => {
    detailItem.value = item;
    showQuickDetail.value = true;
    
    // Fetch audit trail data
    try {
        const routeName = activeTab.value === 'categories' ? 'bouquet-categories.show'
                        : activeTab.value === 'types' ? 'bouquet-types.show'
                        : 'bouquet-units.show';
        const { data } = await axios.get(route(routeName, item.id));
        auditData.value = data.audit_trail;
    } catch (e) {
        console.error('Failed to fetch audit trail', e);
    }
};

const closeQuickDetail = () => {
    showQuickDetail.value = false;
    detailItem.value = null;
    auditData.value = null;
};

// ─────────────────────────────────────────────────────────────────────────────
// Forms
// ─────────────────────────────────────────────────────────────────────────────
const categoryForm = useForm({
    name: '',
    slug: '',
});

const typeForm = useForm({
    category_id: '',
    name: '',
    description: '',
    is_custom: false,
});

const unitForm = useForm({
    type_id: '',
    serial_number: '',
    name: '',
    description: '',
    price: '',
    is_active: true,
    image: null,
    _method: 'POST', // For updates with files
});

const imagePreview = ref(null);

const onFileChange = (e) => {
    const file = e.target.files[0];
    unitForm.image = file;
    if (file) {
        imagePreview.value = URL.createObjectURL(file);
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// Actions
// ─────────────────────────────────────────────────────────────────────────────
const openCreateModal = () => {
    editingItem.value = null;
    imagePreview.value = null;
    if (activeTab.value === 'categories') categoryForm.reset();
    else if (activeTab.value === 'types') typeForm.reset();
    else if (activeTab.value === 'units') {
        unitForm.reset();
        unitForm.is_active = true;
        unitForm._method = 'POST';
    }
    showModal.value = true;
};

const openEditModal = (item) => {
    editingItem.value = item;
    if (activeTab.value === 'categories') {
        categoryForm.name = item.name;
        categoryForm.slug = item.slug;
    } else if (activeTab.value === 'types') {
        typeForm.category_id = item.category_id;
        typeForm.name = item.name;
        typeForm.description = item.description;
        typeForm.is_custom = !!item.is_custom;
    } else if (activeTab.value === 'units') {
        unitForm.type_id = item.type_id;
        unitForm.serial_number = item.serial_number;
        unitForm.name = item.name;
        unitForm.description = item.description;
        unitForm.price = item.price;
        unitForm.is_active = !!item.is_active;
        unitForm.image = null;
        unitForm._method = 'PUT';
        unitForm.clearErrors();
        imagePreview.value = item.image_url;
    }
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingItem.value = null;
    imagePreview.value = null;
};

const submitForm = () => {
    if (activeTab.value === 'categories') {
        if (editingItem.value) {
            categoryForm.put(route('bouquet-categories.update', editingItem.value.id), {
                onSuccess: () => { closeModal(); toast.success('Category updated'); },
            });
        } else {
            categoryForm.post(route('bouquet-categories.store'), {
                onSuccess: () => { closeModal(); toast.success('Category created'); },
            });
        }
    } else if (activeTab.value === 'types') {
        if (editingItem.value) {
            typeForm.put(route('bouquet-types.update', editingItem.value.id), {
                onSuccess: () => { closeModal(); toast.success('Type updated'); },
            });
        } else {
            typeForm.post(route('bouquet-types.store'), {
                onSuccess: () => { closeModal(); toast.success('Type created'); },
            });
        }
    } else if (activeTab.value === 'units') {
        if (editingItem.value) {
            // Use post with _method PUT for multipart form updates
            unitForm.post(route('bouquet-units.update', editingItem.value.id), {
                onSuccess: () => { closeModal(); toast.success('Bouquet updated'); },
                forceFormData: true,
            });
        } else {
            unitForm.post(route('bouquet-units.store'), {
                onSuccess: () => { closeModal(); toast.success('Bouquet created'); },
            });
        }
    }
};

const deleteItem = (id) => {
    if (confirm('Are you sure you want to delete this item?')) {
        const routeName = activeTab.value === 'categories' ? 'bouquet-categories.destroy' 
                        : activeTab.value === 'types' ? 'bouquet-types.destroy' 
                        : 'bouquet-units.destroy';
        router.delete(route(routeName, id), {
            onSuccess: () => toast.success('Item moved to trash'),
        });
    }
};

const restoreItem = (id) => {
    const routeName = activeTab.value === 'categories' ? 'bouquet-categories.restore' 
                    : activeTab.value === 'types' ? 'bouquet-types.restore' 
                    : 'bouquet-units.restore';
    router.post(route(routeName, id), {}, {
        onSuccess: () => toast.success('Item restored'),
    });
};

const forceDeleteItem = (id) => {
    if (confirm('Permanently delete this item? This cannot be undone.')) {
        const routeName = activeTab.value === 'categories' ? 'bouquet-categories.force-delete' 
                        : activeTab.value === 'types' ? 'bouquet-types.force-delete' 
                        : 'bouquet-units.force-delete';
        router.delete(route(routeName, id), {
            onSuccess: () => toast.success('Item deleted permanently'),
        });
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// Column Definitions
// ─────────────────────────────────────────────────────────────────────────────
const extraFilters = reactive({
    active_status: props.filters?.active_status || 'active',
    item_type: props.filters?.item_type || 'catalog',
});

watch(() => props.filters, (newFilters) => {
    extraFilters.active_status = newFilters?.active_status || 'active';
    extraFilters.item_type = newFilters?.item_type || 'catalog';
}, { deep: true });

const categoryColumns = [
    { label: 'Name', key: 'name' },
    { label: 'Slug', key: 'slug' },
    { label: 'Types Count', key: 'bouquet_types_count' },
];

const typeColumns = [
    { label: 'Name', key: 'name' },
    { label: 'Category', key: 'category', sortKey: 'category_id' },
    { label: 'Custom?', key: 'is_custom' },
];

const unitColumns = [
    { label: 'Image', key: 'image_url', sortable: false },
    { label: 'SKU / Serial', key: 'serial_number' },
    { label: 'Name', key: 'name' },
    { label: 'Source', key: 'source', sortable: false },
    { label: 'Type', key: 'type', sortKey: 'type_id' },
    { label: 'Harga', key: 'price' },
    { label: 'Status', key: 'is_active' },
];

// Helper to auto-generate slug
const generateSlug = () => {
    categoryForm.slug = categoryForm.name
        .toLowerCase()
        .replace(/ /g, '-')
        .replace(/[^\w-]+/g, '');
};

</script>

<template>
    <AppLayout title="Bouquet Management">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Bouquet Management
                </h2>
                <PrimaryButton @click="openCreateModal" class="rounded-xl flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    New {{ activeTab === 'categories' ? 'Category' : activeTab === 'types' ? 'Type' : 'Bouquet' }}
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Tab Navigation -->
                <div class="flex items-center gap-2 mb-6 bg-secondary/30 p-1 rounded-2xl w-full overflow-x-auto scrollbar-hide whitespace-nowrap sm:w-fit">
                    <Link 
                        :href="route('bouquet-units.index')"
                        :class="cn(
                            'px-6 py-2.5 rounded-xl text-sm font-medium transition-all',
                            activeTab === 'units' ? 'bg-white shadow-sm text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                        )"
                    >
                        Bouquets
                    </Link>
                    <Link 
                        :href="route('bouquet-types.index')"
                        :class="cn(
                            'px-6 py-2.5 rounded-xl text-sm font-medium transition-all',
                            activeTab === 'types' ? 'bg-white shadow-sm text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                        )"
                    >
                        Types
                    </Link>
                    <Link 
                        :href="route('bouquet-categories.index')"
                        :class="cn(
                            'px-6 py-2.5 rounded-xl text-sm font-medium transition-all',
                            activeTab === 'categories' ? 'bg-white shadow-sm text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                        )"
                    >
                        Categories
                    </Link>
                </div>

                <!-- Units Tab -->
                <div v-if="activeTab === 'units'">
                    <DataTable 
                        :data="units" 
                        :columns="unitColumns" 
                        :filters="filters"
                        :extraFilters="extraFilters"
                        routeName="bouquet-units.index"
                        viewRoute="bouquet-units.show"
                        searchPlaceholder="Search by name or serial..."
                        @view-detail="openQuickDetail"
                    >
                        <template #extra-filters>
                            <select 
                                v-model="extraFilters.active_status"
                                class="bg-white border border-secondary rounded-xl text-sm focus:ring-2 focus:ring-primary/50 transition-all py-2"
                            >
                                <option value="active">Status: Aktif</option>
                                <option value="inactive">Status: Non-Aktif</option>
                                <option value="all">Status: Semua</option>
                            </select>
                            <select 
                                v-model="extraFilters.item_type"
                                class="bg-white border border-secondary rounded-xl text-sm focus:ring-2 focus:ring-primary/50 transition-all py-2"
                            >
                                <option value="catalog">Tipe: Katalog</option>
                                <option value="custom">Tipe: Custom</option>
                                <option value="all">Tipe: Semua</option>
                            </select>
                        </template>
                        <template #cell-image_url="{ item }">
                            <div class="w-12 h-12 rounded-lg bg-secondary/50 overflow-hidden border border-secondary">
                                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-[10px] text-muted-foreground uppercase font-bold">
                                    No Pic
                                </div>
                            </div>
                        </template>
                        <template #cell-type="{ item }">
                            <span class="px-2 py-1 bg-primary/10 text-primary-foreground rounded-lg text-xs font-semibold">
                                {{ item.type?.name }}
                            </span>
                        </template>
                        <template #cell-source="{ item }">
                            <span :class="cn('px-2 py-1 rounded-lg text-xs font-semibold', item.type?.is_custom ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700')">
                                {{ item.type?.is_custom ? 'Custom' : 'Katalog' }}
                            </span>
                        </template>
                        <template #cell-price="{ item }">
                            Rp {{ new Intl.NumberFormat('id-ID').format(item.money_bouquet ?? item.price) }}
                        </template>
                        <template #cell-is_active="{ item }">
                            <span :class="cn('px-2 py-1 rounded-lg text-xs font-semibold', item.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')">
                                {{ item.is_active ? 'Aktif' : 'Non-aktif' }}
                            </span>
                        </template>
                        <template #actions="{ item }">
                            <template v-if="item.deleted_at">
                                <button @click="restoreItem(item.id)" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" title="Restore">
                                    <RotateCcw class="w-4 h-4" />
                                </button>
                                <button @click="forceDeleteItem(item.id)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Force Delete">
                                    <Trash class="w-4 h-4" />
                                </button>
                            </template>
                            <template v-else>
                                <button @click="openEditModal(item)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                                    <Edit2 class="w-4 h-4" />
                                </button>
                                <button @click="deleteItem(item.id)" class="p-2 text-destructive hover:bg-destructive/10 rounded-lg">
                                    <Trash class="w-4 h-4" />
                                </button>
                            </template>
                        </template>
                    </DataTable>
                </div>

                <!-- Types Tab -->
                <div v-if="activeTab === 'types'">
                    <DataTable 
                        :data="types" 
                        :columns="typeColumns" 
                        :filters="filters"
                        routeName="bouquet-types.index"
                        viewRoute="bouquet-types.show"
                        @view-detail="openQuickDetail"
                    >
                        <template #cell-category="{ item }">
                            {{ item.category?.name }}
                        </template>
                        <template #cell-is_custom="{ item }">
                            <span :class="cn('px-2 py-1 rounded-lg text-xs font-semibold', item.is_custom ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700')">
                                {{ item.is_custom ? 'Custom' : 'Standard' }}
                            </span>
                        </template>
                        <template #actions="{ item }">
                            <template v-if="item.deleted_at">
                                <button @click="restoreItem(item.id)" class="p-2 text-green-600 hover:bg-green-50 rounded-lg">
                                    <RotateCcw class="w-4 h-4" />
                                </button>
                                <button @click="forceDeleteItem(item.id)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                                    <Trash class="w-4 h-4" />
                                </button>
                            </template>
                            <template v-else>
                                <button @click="openEditModal(item)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                                    <Edit2 class="w-4 h-4" />
                                </button>
                                <button @click="deleteItem(item.id)" class="p-2 text-destructive hover:bg-destructive/10 rounded-lg">
                                    <Trash class="w-4 h-4" />
                                </button>
                            </template>
                        </template>
                    </DataTable>
                </div>

                <!-- Categories Tab -->
                <div v-if="activeTab === 'categories'">
                    <DataTable 
                        :data="categories" 
                        :columns="categoryColumns" 
                        :filters="filters"
                        routeName="bouquet-categories.index"
                        viewRoute="bouquet-categories.show"
                        @view-detail="openQuickDetail"
                    >
                        <template #actions="{ item }">
                            <template v-if="item.deleted_at">
                                <button @click="restoreItem(item.id)" class="p-2 text-green-600 hover:bg-green-50 rounded-lg">
                                    <RotateCcw class="w-4 h-4" />
                                </button>
                                <button @click="forceDeleteItem(item.id)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                                    <Trash class="w-4 h-4" />
                                </button>
                            </template>
                            <template v-else>
                                <button @click="openEditModal(item)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                                    <Edit2 class="w-4 h-4" />
                                </button>
                                <button @click="deleteItem(item.id)" class="p-2 text-destructive hover:bg-destructive/10 rounded-lg">
                                    <Trash class="w-4 h-4" />
                                </button>
                            </template>
                        </template>
                    </DataTable>
                </div>

            </div>
        </div>

        <!-- Modals -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-bold text-foreground mb-6">
                    {{ editingItem ? 'Edit' : 'New' }} {{ activeTab === 'categories' ? 'Category' : activeTab === 'types' ? 'Type' : 'Bouquet' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Category Form -->
                    <template v-if="activeTab === 'categories'">
                        <div>
                            <InputLabel for="name" value="Category Name" />
                            <TextInput 
                                id="name" 
                                v-model="categoryForm.name" 
                                type="text" 
                                class="mt-1 block w-full" 
                                required 
                                @input="generateSlug"
                            />
                            <InputError :message="categoryForm.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="slug" value="Slug" />
                            <TextInput 
                                id="slug" 
                                v-model="categoryForm.slug" 
                                type="text" 
                                class="mt-1 block w-full bg-secondary/20" 
                                required 
                            />
                            <InputError :message="categoryForm.errors.slug" class="mt-2" />
                        </div>
                    </template>

                    <!-- Type Form -->
                    <template v-if="activeTab === 'types'">
                        <div>
                            <InputLabel for="category_id" value="Parent Category" />
                            <select 
                                id="category_id" 
                                v-model="typeForm.category_id" 
                                class="mt-1 block w-full border-secondary rounded-xl shadow-sm focus:ring-primary/50"
                                required
                            >
                                <option value="" disabled>Select Category</option>
                                <option v-for="cat in resolvedCategoryOptions" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                            <InputError :message="typeForm.errors.category_id" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="type_name" value="Type Name" />
                            <TextInput id="type_name" v-model="typeForm.name" type="text" class="mt-1 block w-full" required />
                            <InputError :message="typeForm.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="description" value="Description" />
                            <textarea 
                                id="description" 
                                v-model="typeForm.description" 
                                class="mt-1 block w-full border-secondary rounded-xl shadow-sm focus:ring-primary/50"
                                rows="3"
                            ></textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="is_custom" v-model="typeForm.is_custom" class="rounded border-secondary text-primary focus:ring-primary" />
                            <InputLabel for="is_custom" value="Is Custom / Money Bouquet?" />
                        </div>
                    </template>

                    <!-- Unit Form -->
                    <template v-if="activeTab === 'units'">
                        <div class="flex items-start gap-6 mb-4">
                            <div class="w-24 h-24 rounded-2xl bg-secondary/30 border-2 border-dashed border-secondary flex-shrink-0 flex items-center justify-center overflow-hidden relative group">
                                <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
                                <div v-else class="text-center p-2">
                                    <Plus class="w-6 h-6 mx-auto text-muted-foreground" />
                                    <span class="text-[10px] text-muted-foreground font-medium">Add Photo</span>
                                </div>
                                <input 
                                    type="file" 
                                    class="absolute inset-0 opacity-0 cursor-pointer" 
                                    @change="onFileChange" 
                                    accept="image/*"
                                />
                            </div>
                            <div class="flex-1">
                                <InputLabel for="type_id" value="Bouquet Type" />
                                <select 
                                    id="type_id" 
                                    v-model="unitForm.type_id" 
                                    class="mt-1 block w-full border-secondary rounded-xl shadow-sm focus:ring-primary/50"
                                    required
                                >
                                    <option value="" disabled>Select Type</option>
                                    <option v-for="t in resolvedTypeOptions" :key="t.id" :value="t.id">
                                        {{ t.name }} ({{ t.category?.name }})
                                    </option>
                                </select>
                                <InputError :message="unitForm.errors.type_id" class="mt-2" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="sku" value="SKU / Serial" />
                                <TextInput id="sku" v-model="unitForm.serial_number" type="text" class="mt-1 block w-full" required />
                                <InputError :message="unitForm.errors.serial_number" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="price" value="Harga Bouquet (IDR)" />
                                <TextInput id="price" v-model="unitForm.price" type="number" class="mt-1 block w-full" required />
                                <InputError :message="unitForm.errors.price" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <InputLabel for="unit_name" value="Display Name" />
                            <TextInput id="unit_name" v-model="unitForm.name" type="text" class="mt-1 block w-full" required />
                            <InputError :message="unitForm.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="unit_desc" value="Description" />
                            <textarea 
                                id="unit_desc" 
                                v-model="unitForm.description" 
                                class="mt-1 block w-full border-secondary rounded-xl shadow-sm focus:ring-primary/50"
                                rows="3"
                            ></textarea>
                        </div>
                        <div class="flex items-center gap-2 bg-pink-50/50 p-4 rounded-xl border border-pink-100">
                            <input 
                                type="checkbox" 
                                id="unit_is_active" 
                                v-model="unitForm.is_active" 
                                class="rounded-md border-pink-200 text-pink-600 focus:ring-pink-500" 
                            />
                            <div>
                                <InputLabel for="unit_is_active" value="Status Aktif" class="font-bold text-pink-900" />
                                <p class="text-xs text-pink-800/60">Non-aktifkan agar bouquet tidak muncul di pilihan kasir.</p>
                            </div>
                        </div>
                    </template>

                    <div class="flex justify-end gap-3 mt-8">
                        <SecondaryButton @click="closeModal" type="button">Cancel</SecondaryButton>
                        <PrimaryButton 
                            class="rounded-xl px-8" 
                            :class="{ 'opacity-25': categoryForm.processing || typeForm.processing || unitForm.processing }"
                            :disabled="categoryForm.processing || typeForm.processing || unitForm.processing"
                        >
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Global Quick Detail Modal -->
        <GlobalDetailModal 
            :show="showQuickDetail" 
            :item="detailItem" 
            :audit="auditData" 
            :type="activeTab === 'categories' ? 'Category' : activeTab === 'types' ? 'Type' : 'Bouquet'"
            @close="closeQuickDetail"
        />
    </AppLayout>
</template>

<style scoped>
/* Smooth transitions */
.v-enter-active,
.v-leave-active {
    transition: opacity 0.3s ease;
}

.v-enter-from,
.v-leave-to {
    opacity: 0;
}
</style>
