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
    users: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '', role: '' }),
    },
});

const showModal = ref(false);
const editingUser = ref(null);
const roleFilter = ref(props.filters.role || '');
const parseTruthy = (value) => ['1', 1, true, 'true', 'with', 'yes', 'on'].includes(value);
const isViewingTrashed = computed(() => parseTruthy(props.filters.trashed));

const userColumns = [
    { label: 'Name', key: 'name' },
    { label: 'Email', key: 'email' },
    { label: 'Role', key: 'primary_role', sortable: false },
    { label: 'Created At', key: 'created_at' },
];

const form = useForm({
    name: '',
    email: '',
    role_name: '',
    password: '',
    password_confirmation: '',
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.role_name = props.roles[0] || '';
};

const openCreateModal = () => {
    editingUser.value = null;
    resetForm();
    showModal.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.role_name = user.primary_role || props.roles[0] || '';
    form.password = '';
    form.password_confirmation = '';
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingUser.value = null;
    resetForm();
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        password: data.password || null,
        password_confirmation: data.password ? data.password_confirmation : null,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingUser.value) {
        form.put(route('users.update', editingUser.value.id), options);
        return;
    }

    form.post(route('users.store'), options);
};

const destroyUser = (user) => {
    if (! confirm(`Hapus user ${user.name}?`)) {
        return;
    }

    router.delete(route('users.destroy', user.id), {
        preserveScroll: true,
    });
};

const restoreUser = (user) => {
    router.post(route('users.restore', user.id), {}, {
        preserveScroll: true,
    });
};

const forceDeleteUser = (user) => {
    if (! confirm(`Hapus permanen user ${user.name}?`)) {
        return;
    }

    router.delete(route('users.force-delete', user.id), {
        preserveScroll: true,
    });
};

const applyRoleFilter = () => {
    router.get(route('users.index'), {
        search: props.filters.search || '',
        role: roleFilter.value || '',
        sort_by: props.filters.sort_by || 'name',
        sort_dir: props.filters.sort_dir || 'asc',
        ...(isViewingTrashed.value ? { trashed: 1 } : {}),
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="User Management">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    User Management
                </h2>
                <PrimaryButton class="rounded-xl flex items-center gap-2" @click="openCreateModal">
                    <Plus class="w-4 h-4" />
                    New User
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <DataTable
                    :data="users"
                    :columns="userColumns"
                    :filters="filters"
                    routeName="users.index"
                    searchPlaceholder="Search users by name or email..."
                    :additional-params="{ role: roleFilter || '' }"
                >
                    <template #extra-filters>
                        <select
                            v-model="roleFilter"
                            class="px-3 py-2 text-sm rounded-xl border border-secondary bg-white focus:ring-2 focus:ring-primary/50"
                            @change="applyRoleFilter"
                        >
                            <option value="">All Roles</option>
                            <option v-for="role in roles" :key="role" :value="role">
                                {{ role }}
                            </option>
                        </select>
                    </template>

                    <template #cell-primary_role="{ item }">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-pink-100 text-pink-800 capitalize">
                            {{ item.primary_role || 'No role' }}
                        </span>
                    </template>

                    <template #actions="{ item }">
                        <template v-if="item.deleted_at">
                            <button
                                class="px-3 py-1.5 text-xs rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition"
                                @click="restoreUser(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <RotateCcw class="w-3.5 h-3.5" />
                                    Restore
                                </span>
                            </button>
                            <button
                                class="px-3 py-1.5 text-xs rounded-lg bg-red-50 hover:bg-red-100 text-red-700 transition"
                                @click="forceDeleteUser(item)"
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
                                @click="destroyUser(item)"
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
                    {{ editingUser ? 'Edit User' : 'Create User' }}
                </h3>

                <div class="space-y-1">
                    <InputLabel value="Name" />
                    <TextInput v-model="form.name" class="w-full" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-1">
                    <InputLabel value="Email" />
                    <TextInput v-model="form.email" type="email" class="w-full" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="space-y-1">
                    <InputLabel value="Role" />
                    <select
                        v-model="form.role_name"
                        class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    >
                        <option disabled value="">Select role</option>
                        <option v-for="role in roles" :key="role" :value="role" class="capitalize">
                            {{ role }}
                        </option>
                    </select>
                    <InputError :message="form.errors.role_name" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <InputLabel :value="editingUser ? 'Password (optional)' : 'Password'" />
                        <TextInput v-model="form.password" type="password" class="w-full" />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel :value="editingUser ? 'Confirm Password (optional)' : 'Confirm Password'" />
                        <TextInput v-model="form.password_confirmation" type="password" class="w-full" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <SecondaryButton type="button" @click="closeModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editingUser ? 'Update User' : 'Create User' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
