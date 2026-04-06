<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import GlobalDetailModal from '@/Components/GlobalDetailModal.vue';
import axios from 'axios';
import { Plus, Pencil, RotateCcw, Trash2, XCircle, Phone, Users } from 'lucide-vue-next';

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

const showModal = ref(false);
const showQuickDetail = ref(false);
const editingCustomer = ref(null);
const detailItem = ref(null);
const auditData = ref(null);
const aliasesInput = ref('');

const openQuickDetail = async (item) => {
    detailItem.value = item;
    showQuickDetail.value = true;
    
    try {
        const { data } = await axios.get(route('customers.show', item.id));
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

const customerColumns = [
    { label: 'Name', key: 'name' },
    { label: 'Phone Number', key: 'phone_number' },
    { label: 'Aliases', key: 'aliases', sortable: false },
    { label: 'Orders', key: 'orders_count' },
];

const form = useForm({
    name: '',
    phone_number: '',
    aliases: [],
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    aliasesInput.value = '';
};

const parseAliases = (input) => {
    if (!input.trim()) {
        return [];
    }

    return input
        .split(/[\n,]/)
        .map((value) => value.trim())
        .filter((value, index, list) => value.length > 0 && list.indexOf(value) === index);
};

const openCreateModal = () => {
    editingCustomer.value = null;
    resetForm();
    showModal.value = true;
};

const openEditModal = (customer) => {
    editingCustomer.value = customer;
    form.name = customer.name;
    form.phone_number = customer.phone_number;
    form.aliases = customer.aliases || [];
    aliasesInput.value = (customer.aliases || []).join(', ');
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingCustomer.value = null;
    resetForm();
};

const submit = () => {
    form.aliases = parseAliases(aliasesInput.value);

    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingCustomer.value) {
        form.put(route('customers.update', editingCustomer.value.id), options);
        return;
    }

    form.post(route('customers.store'), options);
};

const destroyCustomer = (customer) => {
    if (!confirm(`Hapus customer ${customer.name}?`)) {
        return;
    }

    router.delete(route('customers.destroy', customer.id), {
        preserveScroll: true,
    });
};

const restoreCustomer = (customer) => {
    router.post(route('customers.restore', customer.id), {}, {
        preserveScroll: true,
    });
};

const forceDeleteCustomer = (customer) => {
    if (!confirm(`Hapus permanen customer ${customer.name}?`)) {
        return;
    }

    router.delete(route('customers.force-delete', customer.id), {
        preserveScroll: true,
    });
};

const aliasError = computed(() => form.errors.aliases || form.errors['aliases.0']);
</script>

<template>
    <AppLayout title="Customers">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Customer Management
                </h2>
                <PrimaryButton class="rounded-xl flex items-center gap-2" @click="openCreateModal">
                    <Plus class="w-4 h-4" />
                    New Customer
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <DataTable
                    :data="customers"
                    :columns="customerColumns"
                    :filters="filters"
                    routeName="customers.index"
                    viewRoute="customers.show"
                    searchPlaceholder="Search customer by name or phone..."
                    @view-detail="openQuickDetail"
                >
                    <template #cell-aliases="{ item }">
                        <div v-if="item.aliases?.length" class="flex flex-wrap gap-1.5 max-w-md">
                            <span
                                v-for="alias in item.aliases.slice(0, 3)"
                                :key="alias"
                                class="inline-flex px-2 py-1 rounded-md text-[11px] font-medium bg-secondary/60 text-foreground"
                            >
                                {{ alias }}
                            </span>
                            <span
                                v-if="item.aliases.length > 3"
                                class="inline-flex px-2 py-1 rounded-md text-[11px] font-medium bg-pink-50 text-pink-700"
                            >
                                +{{ item.aliases.length - 3 }} more
                            </span>
                        </div>
                        <span v-else class="text-xs text-muted-foreground">-</span>
                    </template>

                    <template #cell-orders_count="{ item }">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-secondary text-foreground">
                            {{ item.orders_count ?? 0 }}
                        </span>
                    </template>

                    <template #actions="{ item }">
                        <template v-if="item.deleted_at">
                            <button
                                v-if="$can('customers.delete')"
                                class="px-3 py-1.5 text-xs rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition"
                                @click="restoreCustomer(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <RotateCcw class="w-3.5 h-3.5" />
                                    Restore
                                </span>
                            </button>
                            <button
                                v-if="$can('customers.delete')"
                                class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                @click="forceDeleteCustomer(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <XCircle class="w-3.5 h-3.5" />
                                    Delete Forever
                                </span>
                            </button>
                        </template>
                        <template v-else>
                            <button
                                v-if="$can('customers.manage')"
                                class="px-3 py-1.5 text-xs rounded-lg bg-secondary/50 hover:bg-secondary text-foreground transition"
                                @click="openEditModal(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <Pencil class="w-3.5 h-3.5" />
                                    Edit
                                </span>
                            </button>
                            <button
                                v-if="$can('customers.manage')"
                                class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                @click="destroyCustomer(item)"
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

        <Modal :show="showModal" @close="closeModal" max-width="2xl">
            <form class="p-6 space-y-5" @submit.prevent="submit">
                <h3 class="text-lg font-semibold text-foreground">
                    {{ editingCustomer ? 'Edit Customer' : 'Create Customer' }}
                </h3>

                <div class="space-y-1">
                    <InputLabel value="Name" />
                    <TextInput v-model="form.name" class="w-full" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-1">
                    <InputLabel value="Phone Number" />
                    <TextInput v-model="form.phone_number" class="w-full" />
                    <InputError :message="form.errors.phone_number" />
                </div>

                <div class="space-y-1">
                    <InputLabel value="Aliases (optional)" />
                    <TextInput
                        v-model="aliasesInput"
                        class="w-full"
                        placeholder="Pisahkan dengan koma, contoh: Budi, Pak Budi"
                    />
                    <InputError :message="aliasError" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <SecondaryButton type="button" @click="closeModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editingCustomer ? 'Update Customer' : 'Create Customer' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Global Quick Detail Modal -->
        <GlobalDetailModal 
            :show="showQuickDetail" 
            :item="detailItem" 
            :audit="auditData" 
            type="Customer"
            @close="closeQuickDetail"
        >
            <template #extra-info="{ item }">
                <div class="col-span-2 p-4 bg-pink-50/50 rounded-2xl border-2 border-pink-100/50 space-y-3">
                    <div class="flex items-center justify-between text-pink-900">
                        <div class="flex items-center gap-2">
                            <Phone class="w-4 h-4 text-pink-400" />
                            <span class="text-[10px] font-bold uppercase tracking-widest">WhatsApp</span>
                        </div>
                        <span class="text-sm font-black">{{ item.phone_number }}</span>
                    </div>
                    <div v-if="item.aliases?.length" class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Users class="w-4 h-4 text-pink-400" />
                            <span class="text-[10px] font-bold uppercase tracking-widest">Dikenal Sebagai</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="alias in item.aliases" :key="alias" class="px-2 py-0.5 bg-white border border-pink-100 rounded-lg text-[10px] font-bold text-pink-600">
                                {{ alias }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </GlobalDetailModal>
    </AppLayout>
</template>
