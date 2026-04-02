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
import { Plus, Pencil, RotateCcw, Trash2, XCircle } from 'lucide-vue-next';

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
const editingCustomer = ref(null);
const aliasesInput = ref('');

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
                    searchPlaceholder="Search customer by name or phone..."
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
                                class="px-3 py-1.5 text-xs rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition"
                                @click="restoreCustomer(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <RotateCcw class="w-3.5 h-3.5" />
                                    Restore
                                </span>
                            </button>
                            <button
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
    </AppLayout>
</template>
