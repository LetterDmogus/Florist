<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
import { Plus, Pencil, Trash2, RotateCcw, XCircle, Upload, Download, Package, Tag } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
    tab: {
        type: String,
        default: 'units',
    },
    items: {
        type: Object,
        default: null,
    },
    categories: {
        type: Object,
        default: null,
    },
    categoryOptions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    importPreview: {
        type: Object,
        default: null,
    },
});

const activeTab = ref(props.tab || 'units');
const showModal = ref(false);
const showQuickDetail = ref(false);
const showImportModal = ref(false);
const editingItem = ref(null);
const detailItem = ref(null);
const auditData = ref(null);
const imagePreview = ref(null);
const selectedCategory = ref(props.filters.category_id || '');

const openQuickDetail = async (item) => {
    detailItem.value = item;
    showQuickDetail.value = true;
    
    try {
        const routeName = activeTab.value === 'categories' ? 'item-categories.show' : 'item-units.show';
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

const parseTruthy = (value) => ['1', 1, true, 'true', 'with', 'yes', 'on'].includes(value);
const isViewingTrashed = computed(() => parseTruthy(props.filters.trashed));

const categoryColumns = [
    { label: 'Name', key: 'name' },
    { label: 'Slug', key: 'slug' },
    { label: 'Items Count', key: 'item_units_count' },
];

const unitColumns = [
    { label: 'Image', key: 'image_url', sortable: false },
    { label: 'Serial Number', key: 'serial_number' },
    { label: 'Name', key: 'name' },
    { label: 'Category', key: 'category', sortKey: 'category_id' },
    { label: 'Price', key: 'price' },
    { label: 'Individual', key: 'individual' },
    { label: 'Stock', key: 'stock' },
];

const categoryForm = useForm({
    name: '',
    slug: '',
});

const unitForm = useForm({
    category_id: '',
    serial_number: '',
    name: '',
    price: '',
    individual: '',
    description: '',
    stock: 0,
    image: null,
    _method: 'POST',
});

const importForm = useForm({
    category_id: '',
    file: null,
});

const importCommitForm = useForm({
    token: '',
});

const resetCategoryForm = () => {
    categoryForm.reset();
    categoryForm.clearErrors();
};

const resetUnitForm = () => {
    unitForm.reset();
    unitForm.clearErrors();
    unitForm._method = 'POST';
    imagePreview.value = null;
};

const openCreateModal = () => {
    editingItem.value = null;
    if (activeTab.value === 'categories') {
        resetCategoryForm();
    } else {
        resetUnitForm();
    }
    showModal.value = true;
};

const openEditModal = (item) => {
    editingItem.value = item;
    if (activeTab.value === 'categories') {
        categoryForm.name = item.name;
        categoryForm.slug = item.slug;
        categoryForm.clearErrors();
    } else {
        unitForm.category_id = item.category_id;
        unitForm.serial_number = item.serial_number;
        unitForm.name = item.name;
        unitForm.price = item.price;
        unitForm.individual = item.individual;
        unitForm.description = item.description || '';
        unitForm.stock = item.stock ?? 0;
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
    resetCategoryForm();
    resetUnitForm();
};

const openImportModal = () => {
    if (!props.importPreview?.token) {
        importForm.reset();
        importForm.clearErrors();
        importForm.category_id = selectedCategory.value || props.categoryOptions[0]?.id || '';
        importForm.file = null;
    }
    showImportModal.value = true;
};

const closeImportModal = () => {
    showImportModal.value = false;
    importForm.reset();
    importForm.clearErrors();
    importCommitForm.reset();
    importCommitForm.clearErrors();
};

const onImportFileChange = (event) => {
    const [file] = event.target.files || [];
    importForm.file = file ?? null;
};

const submitImport = () => {
    importForm.post(route('item-units.import'), {
        preserveScroll: true,
        forceFormData: true,
    });
};

const commitImport = () => {
    if (!props.importPreview?.token) {
        return;
    }

    importCommitForm.token = props.importPreview.token;
    importCommitForm.post(route('item-units.import.commit'), {
        preserveScroll: true,
        onSuccess: () => closeImportModal(),
    });
};

const discardImportPreview = () => {
    importCommitForm.token = props.importPreview?.token || '';
    importCommitForm.post(route('item-units.import.discard'), {
        preserveScroll: true,
        onSuccess: () => closeImportModal(),
    });
};

const submitForm = () => {
    if (activeTab.value === 'categories') {
        if (editingItem.value) {
            categoryForm.put(route('item-categories.update', editingItem.value.id), {
                preserveScroll: true,
                onSuccess: closeModal,
            });
        } else {
            categoryForm.post(route('item-categories.store'), {
                preserveScroll: true,
                onSuccess: closeModal,
            });
        }
        return;
    }

    if (editingItem.value) {
        unitForm.post(route('item-units.update', editingItem.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
        });
        return;
    }

    unitForm.post(route('item-units.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
    });
};

const deleteItem = (item) => {
    if (!confirm(`Hapus ${item.name}?`)) {
        return;
    }

    const routeName = activeTab.value === 'categories'
        ? 'item-categories.destroy'
        : 'item-units.destroy';

    router.delete(route(routeName, item.id), {
        preserveScroll: true,
    });
};

const restoreItem = (item) => {
    const routeName = activeTab.value === 'categories'
        ? 'item-categories.restore'
        : 'item-units.restore';

    router.post(route(routeName, item.id), {}, {
        preserveScroll: true,
    });
};

const forceDeleteItem = (item) => {
    if (!confirm(`Hapus permanen ${item.name}?`)) {
        return;
    }

    const routeName = activeTab.value === 'categories'
        ? 'item-categories.force-delete'
        : 'item-units.force-delete';

    router.delete(route(routeName, item.id), {
        preserveScroll: true,
    });
};

const onFileChange = (event) => {
    const file = event.target.files[0];
    unitForm.image = file;
    imagePreview.value = file ? URL.createObjectURL(file) : null;
};

const formatCurrency = (value) => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
};

const generateSlug = () => {
    categoryForm.slug = (categoryForm.name || '')
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w-]+/g, '');
};

const exportExcel = () => {
    const params = new URLSearchParams({
        category_id: selectedCategory.value || '',
        search: props.filters.search || '',
    });
    window.location.href = route('item-units.export') + '?' + params.toString();
};

const applyCategoryFilter = () => {
    router.get(route('item-units.index'), {
        search: props.filters.search || '',
        category_id: selectedCategory.value || '',
        sort_by: props.filters.sort_by || 'created_at',
        sort_dir: props.filters.sort_dir || 'desc',
        ...(isViewingTrashed.value ? { trashed: 1 } : {}),
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

if (props.importPreview?.token) {
    showImportModal.value = true;
}
</script>

<template>
    <AppLayout title="Inventory">
        <Head title="Inventory" />
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Inventory Management
                </h2>
                <div class="flex items-center gap-2">
                    <SecondaryButton
                        v-if="activeTab === 'units'"
                        class="rounded-xl flex items-center gap-2"
                        @click="exportExcel"
                    >
                        <Download class="w-4 h-4" />
                        Export Excel
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="activeTab === 'units'"
                        class="rounded-xl flex items-center gap-2"
                        @click="openImportModal"
                    >
                        <Upload class="w-4 h-4" />
                        Import Inventory
                    </SecondaryButton>
                    <PrimaryButton class="rounded-xl flex items-center gap-2" @click="openCreateModal">
                        <Plus class="w-4 h-4" />
                        New {{ activeTab === 'categories' ? 'Category' : 'Inventory Unit' }}
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-2 mb-6 bg-secondary/30 p-1 rounded-2xl w-full overflow-x-auto scrollbar-hide whitespace-nowrap sm:w-fit">
                    <Link
                        :href="route('item-units.index')"
                        :class="cn(
                            'px-6 py-2.5 rounded-xl text-sm font-medium transition-all',
                            activeTab === 'units' ? 'bg-white shadow-sm text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                        )"
                    >
                        Inventory Units
                    </Link>
                    <Link
                        :href="route('item-categories.index')"
                        :class="cn(
                            'px-6 py-2.5 rounded-xl text-sm font-medium transition-all',
                            activeTab === 'categories' ? 'bg-white shadow-sm text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                        )"
                    >
                        Categories
                    </Link>
                </div>

                <div v-if="activeTab === 'units' && items">
                    <DataTable
                        :data="items"
                        :columns="unitColumns"
                        :filters="filters"
                        routeName="item-units.index"
                        viewRoute="item-units.show"
                        searchPlaceholder="Search item by name or serial number..."
                        :additional-params="{ category_id: selectedCategory || '' }"
                        @view-detail="openQuickDetail"
                    >
                        <template #extra-filters>
                            <select
                                v-model="selectedCategory"
                                class="px-3 py-2 text-sm rounded-xl border border-secondary bg-white focus:ring-2 focus:ring-primary/50"
                                @change="applyCategoryFilter"
                            >
                                <option value="">All Categories</option>
                                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </template>

                        <template #cell-image_url="{ item }">
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.name"
                                class="w-12 h-12 object-cover rounded-lg border border-secondary/50"
                            >
                            <div v-else class="w-12 h-12 rounded-lg bg-secondary/40 border border-secondary/50" />
                        </template>

                        <template #cell-category="{ item }">
                            <span class="text-sm text-foreground">
                                {{ item.category?.name || '-' }}
                            </span>
                        </template>

                        <template #cell-price="{ item }">
                            {{ formatCurrency(item.price) }}
                        </template>

                        <template #cell-stock="{ item }">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-secondary text-foreground">
                                {{ item.stock }}
                            </span>
                        </template>

                        <template #actions="{ item }">
                            <template v-if="item.deleted_at">
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition"
                                    @click="restoreItem(item)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <RotateCcw class="w-3.5 h-3.5" />
                                        Restore
                                    </span>
                                </button>
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                    @click="forceDeleteItem(item)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <XCircle class="w-3.5 h-3.5" />
                                        Delete Forever
                                    </span>
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-secondary/50 hover:bg-secondary text-foreground transition"
                                    @click="openEditModal(item)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <Pencil class="w-3.5 h-3.5" />
                                        Edit
                                    </span>
                                </button>
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                    @click="deleteItem(item)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <Trash2 class="w-3.5 h-3.5" />
                                        Delete
                                    </span>
                                </button>
                            </template>
                        </template>
                    </DataTable>
                </div>

                <div v-if="activeTab === 'categories' && categories">
                    <DataTable
                        :data="categories"
                        :columns="categoryColumns"
                        :filters="filters"
                        routeName="item-categories.index"
                        viewRoute="item-categories.show"
                        searchPlaceholder="Search category..."
                        @view-detail="openQuickDetail"
                    >
                        <template #cell-item_units_count="{ item }">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-secondary text-foreground">
                                {{ item.item_units_count }}
                            </span>
                        </template>

                        <template #actions="{ item }">
                            <template v-if="item.deleted_at">
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition"
                                    @click="restoreItem(item)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <RotateCcw class="w-3.5 h-3.5" />
                                        Restore
                                    </span>
                                </button>
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                    @click="forceDeleteItem(item)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <XCircle class="w-3.5 h-3.5" />
                                        Delete Forever
                                    </span>
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-secondary/50 hover:bg-secondary text-foreground transition"
                                    @click="openEditModal(item)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <Pencil class="w-3.5 h-3.5" />
                                        Edit
                                    </span>
                                </button>
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                    @click="deleteItem(item)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        <Trash2 class="w-3.5 h-3.5" />
                                        Delete
                                    </span>
                                </button>
                            </template>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <Modal :show="showImportModal" @close="closeImportModal" max-width="2xl">
            <div class="p-6 space-y-5">
                <h3 class="text-lg font-semibold text-foreground">
                    Import Inventory Supply
                </h3>

                <div class="rounded-xl border border-secondary/60 bg-secondary/20 p-3 text-sm text-muted-foreground space-y-1">
                    <p>File harus berisi kolom: Kode, Name, Harga Jual, Sheets, Size, Sisa.</p>
                    <p>Mapping otomatis: Size -> Description, Sisa -> Stock.</p>
                </div>

                <template v-if="props.importPreview?.token">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-sm text-emerald-800 space-y-1">
                        <p class="font-semibold">Preview siap dikonfirmasi</p>
                        <p>File: {{ props.importPreview.file_name }}</p>
                        <p>Target Category: {{ props.importPreview.category_name || '-' }}</p>
                        <p>Total data: {{ props.importPreview.total_rows }}</p>
                        <p>Estimasi: {{ props.importPreview.estimated_create }} data baru, {{ props.importPreview.estimated_update }} data update.</p>
                    </div>

                    <div class="max-h-72 overflow-auto rounded-xl border border-secondary/60">
                        <table class="min-w-full text-xs">
                            <thead class="bg-secondary/40">
                                <tr>
                                    <th class="px-3 py-2 text-left">Serial</th>
                                    <th class="px-3 py-2 text-left">Name</th>
                                    <th class="px-3 py-2 text-left">Price</th>
                                    <th class="px-3 py-2 text-left">Individual</th>
                                    <th class="px-3 py-2 text-left">Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in props.importPreview.sample_rows || []" :key="`preview-row-${index}`" class="border-t border-secondary/40">
                                    <td class="px-3 py-2 font-medium">{{ row.serial_number }}</td>
                                    <td class="px-3 py-2">{{ row.name }}</td>
                                    <td class="px-3 py-2">{{ formatCurrency(row.price) }}</td>
                                    <td class="px-3 py-2">{{ row.individual }}</td>
                                    <td class="px-3 py-2">{{ row.stock }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <SecondaryButton type="button" @click="discardImportPreview" :disabled="importCommitForm.processing">
                            Batal Preview
                        </SecondaryButton>
                        <PrimaryButton type="button" @click="commitImport" :disabled="importCommitForm.processing">
                            {{ importCommitForm.processing ? 'Importing...' : 'Konfirmasi Import' }}
                        </PrimaryButton>
                    </div>
                </template>

                <form v-else class="space-y-5" @submit.prevent="submitImport">
                    <div class="space-y-1">
                        <InputLabel value="Target Category" />
                        <select
                            v-model="importForm.category_id"
                            class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        >
                            <option disabled value="">Select category</option>
                            <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                        <InputError :message="importForm.errors.category_id" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel value="Excel File (.xlsx / .xls)" />
                        <input
                            type="file"
                            accept=".xlsx,.xls"
                            class="w-full text-sm border border-gray-300 rounded-md p-2"
                            @change="onImportFileChange"
                        >
                        <InputError :message="importForm.errors.file" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <SecondaryButton type="button" @click="closeImportModal">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton :disabled="importForm.processing">
                            {{ importForm.processing ? 'Memproses...' : 'Preview Data' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showModal" @close="closeModal" max-width="2xl">
            <form class="p-6 space-y-5" @submit.prevent="submitForm">
                <h3 class="text-lg font-semibold text-foreground">
                    {{ activeTab === 'categories'
                        ? (editingItem ? 'Edit Category' : 'Create Category')
                        : (editingItem ? 'Edit Inventory Unit' : 'Create Inventory Unit') }}
                </h3>

                <template v-if="activeTab === 'categories'">
                    <div class="space-y-1">
                        <InputLabel value="Name" />
                        <TextInput v-model="categoryForm.name" class="w-full" @input="generateSlug" />
                        <InputError :message="categoryForm.errors.name" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel value="Slug" />
                        <TextInput v-model="categoryForm.slug" class="w-full" />
                        <InputError :message="categoryForm.errors.slug" />
                    </div>
                </template>

                <template v-else>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <InputLabel value="Category" />
                            <select
                                v-model="unitForm.category_id"
                                class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            >
                                <option disabled value="">Select category</option>
                                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                            <InputError :message="unitForm.errors.category_id" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel value="Serial Number" />
                            <TextInput v-model="unitForm.serial_number" class="w-full" />
                            <InputError :message="unitForm.errors.serial_number" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <InputLabel value="Name" />
                        <TextInput v-model="unitForm.name" class="w-full" />
                        <InputError :message="unitForm.errors.name" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <InputLabel value="Price" />
                            <TextInput v-model="unitForm.price" type="number" step="0.01" min="0" class="w-full" />
                            <InputError :message="unitForm.errors.price" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel value="Individual" />
                            <TextInput v-model="unitForm.individual" class="w-full" />
                            <InputError :message="unitForm.errors.individual" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel value="Stock" />
                            <TextInput v-model="unitForm.stock" type="number" min="0" class="w-full" />
                            <InputError :message="unitForm.errors.stock" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <InputLabel value="Description" />
                        <textarea
                            v-model="unitForm.description"
                            rows="3"
                            class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        />
                        <InputError :message="unitForm.errors.description" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel value="Image (optional)" />
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/jpg,image/webp"
                            class="w-full text-sm border border-gray-300 rounded-md p-2"
                            @change="onFileChange"
                        >
                        <InputError :message="unitForm.errors.image" />
                        <img
                            v-if="imagePreview"
                            :src="imagePreview"
                            alt="Preview"
                            class="mt-2 w-24 h-24 object-cover rounded-lg border border-secondary/50"
                        >
                    </div>
                </template>

                <div class="flex justify-end gap-2 pt-2">
                    <SecondaryButton type="button" @click="closeModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="categoryForm.processing || unitForm.processing">
                        {{ editingItem ? 'Update' : 'Create' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Global Quick Detail Modal -->
        <GlobalDetailModal 
            :show="showQuickDetail" 
            :item="detailItem" 
            :audit="auditData" 
            :type="activeTab === 'categories' ? 'Category' : 'Inventory'"
            @close="closeQuickDetail"
        >
            <template #extra-info="{ item }">
                <div v-if="activeTab === 'units'" class="col-span-2 grid grid-cols-2 gap-4">
                    <div class="p-4 bg-pink-50/50 rounded-2xl border-2 border-pink-100/50 flex flex-col gap-1">
                        <div class="flex items-center gap-2 text-pink-400">
                            <Tag class="w-3.5 h-3.5" />
                            <span class="text-[10px] font-bold uppercase tracking-widest text-pink-900">Kategori</span>
                        </div>
                        <span class="text-sm font-black text-pink-950">{{ item.category?.name || '-' }}</span>
                    </div>
                    <div class="p-4 bg-pink-50/50 rounded-2xl border-2 border-pink-100/50 flex flex-col gap-1">
                        <div class="flex items-center gap-2 text-pink-400">
                            <Package class="w-3.5 h-3.5" />
                            <span class="text-[10px] font-bold uppercase tracking-widest text-pink-900">Unit Satuan</span>
                        </div>
                        <span class="text-sm font-black text-pink-950">{{ item.individual || '-' }}</span>
                    </div>
                </div>
            </template>
        </GlobalDetailModal>
    </AppLayout>
</template>
