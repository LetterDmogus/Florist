<script setup>
import { ref } from 'vue';
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

defineProps({
    roles: Object,
    permissions: Array,
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

const systemRoles = ['super-admin', 'admin', 'kasir', 'manager'];

const roleColumns = [
    { label: 'Role Name', key: 'name' },
    { label: 'Permissions', key: 'permissions' },
    { label: 'Users', key: 'users_count' },
];

const showModal = ref(false);
const editingRole = ref(null);

const form = useForm({
    name: '',
    permissions: [],
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
};

const openCreateModal = () => {
    editingRole.value = null;
    resetForm();
    showModal.value = true;
};

const openEditModal = (role) => {
    editingRole.value = role;
    form.name = role.name;
    form.permissions = role.permissions || [];
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingRole.value = null;
    resetForm();
};

const isSystemRole = (roleName) => systemRoles.includes(roleName);

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingRole.value) {
        form.put(route('roles.update', editingRole.value.id), options);
        return;
    }

    form.post(route('roles.store'), options);
};

const destroyRole = (role) => {
    if (role.is_system) {
        return;
    }

    if (! confirm(`Hapus role ${role.name}?`)) {
        return;
    }

    router.delete(route('roles.destroy', role.id), {
        preserveScroll: true,
    });
};

const restoreRole = (role) => {
    router.post(route('roles.restore', role.id), {}, {
        preserveScroll: true,
    });
};

const forceDeleteRole = (role) => {
    if (role.is_system) {
        return;
    }

    if (! confirm(`Hapus permanen role ${role.name}?`)) {
        return;
    }

    router.delete(route('roles.force-delete', role.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Role Management">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Role Management
                </h2>
                <PrimaryButton class="rounded-xl flex items-center gap-2" @click="openCreateModal">
                    <Plus class="w-4 h-4" />
                    New Role
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <DataTable
                    :data="roles"
                    :columns="roleColumns"
                    :filters="filters"
                    routeName="roles.index"
                    searchPlaceholder="Search role..."
                >
                    <template #cell-name="{ item }">
                        <span
                            class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold capitalize"
                            :class="item.is_system ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-800'"
                        >
                            {{ item.name }}
                        </span>
                    </template>

                    <template #cell-permissions="{ item }">
                        <div class="flex flex-wrap gap-1.5 max-w-md">
                            <template v-if="item.permissions?.length">
                                <span
                                    v-for="permission in item.permissions.slice(0, 3)"
                                    :key="permission"
                                    class="inline-flex px-2 py-1 rounded-md text-[11px] font-medium bg-secondary/60 text-foreground"
                                >
                                    {{ permission }}
                                </span>
                                <span
                                    v-if="item.permissions.length > 3"
                                    class="inline-flex px-2 py-1 rounded-md text-[11px] font-medium bg-pink-50 text-pink-700"
                                >
                                    +{{ item.permissions.length - 3 }} more
                                </span>
                            </template>
                            <span v-else class="text-xs text-muted-foreground">No permission</span>
                        </div>
                    </template>

                    <template #cell-users_count="{ item }">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-secondary text-foreground">
                            {{ item.users_count }}
                        </span>
                    </template>

                    <template #actions="{ item }">
                        <template v-if="item.deleted_at">
                            <button
                                class="px-3 py-1.5 text-xs rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition"
                                @click="restoreRole(item)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <RotateCcw class="w-3.5 h-3.5" />
                                    Restore
                                </span>
                            </button>
                            <button
                                :disabled="item.is_system"
                                class="px-3 py-1.5 text-xs rounded-lg transition"
                                :class="item.is_system
                                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    : 'bg-red-50 hover:bg-red-100 text-red-700'"
                                @click="forceDeleteRole(item)"
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
                                :disabled="item.is_system"
                                class="px-3 py-1.5 text-xs rounded-lg transition"
                                :class="item.is_system
                                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    : 'bg-red-50 hover:bg-red-100 text-red-700'"
                                @click="destroyRole(item)"
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
                    {{ editingRole ? 'Edit Role' : 'Create Role' }}
                </h3>

                <div class="space-y-1">
                    <InputLabel value="Role Name" />
                    <TextInput
                        v-model="form.name"
                        class="w-full"
                        :disabled="editingRole && isSystemRole(editingRole.name)"
                    />
                    <InputError :message="form.errors.name" />
                    <p v-if="editingRole && isSystemRole(editingRole.name)" class="text-xs text-muted-foreground">
                        Nama role sistem tidak bisa diubah.
                    </p>
                </div>

                <div class="space-y-2">
                    <InputLabel value="Permissions" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-72 overflow-y-auto border border-secondary rounded-xl p-3">
                        <label
                            v-for="permission in permissions"
                            :key="permission"
                            class="flex items-center gap-2 text-sm text-foreground"
                        >
                            <input
                                v-model="form.permissions"
                                type="checkbox"
                                :value="permission"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            >
                            <span>{{ permission }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.permissions" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <SecondaryButton type="button" @click="closeModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editingRole ? 'Update Role' : 'Create Role' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
