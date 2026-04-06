<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { ShieldCheck, Plus, Pencil, Trash2, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
    roles: Object,
    filters: Object,
});

const columns = [
    { label: 'Role Name', key: 'name' },
    { label: 'Users', key: 'users_count', sortable: false },
    { label: 'Permissions', key: 'permissions', sortable: false },
];

const deleteRole = (id) => {
    if (confirm('Are you sure you want to delete this role?')) {
        router.delete(route('roles.destroy', id));
    }
};

const restoreRole = (id) => {
    router.post(route('roles.restore', id));
};
</script>

<template>
    <AppLayout title="Role Management">
        <Head title="Role Management" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-pink-950 leading-tight">
                    Role & Permissions
                </h2>
                <Link v-if="$can('roles.manage')" :href="route('roles.create')">
                    <BaseButton variant="primary" class="rounded-xl flex items-center gap-2">
                        <Plus class="w-4 h-4" />
                        Tambah Role Baru
                    </BaseButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <DataTable 
                    :data="roles" 
                    :columns="columns" 
                    :filters="filters"
                    routeName="roles.index"
                >
                    <template #cell-name="{ item }">
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="w-4 h-4 text-pink-600" />
                            <span class="font-bold text-pink-950">{{ item.name }}</span>
                            <span v-if="item.is_system" class="text-[10px] bg-pink-100 text-pink-700 px-1.5 py-0.5 rounded uppercase font-bold">System</span>
                        </div>
                    </template>

                    <template #cell-users_count="{ item }">
                        <span class="text-sm text-muted-foreground">{{ item.users_count }} users assigned</span>
                    </template>

                    <template #cell-permissions="{ item }">
                        <div class="flex flex-wrap gap-1 max-w-md">
                            <span 
                                v-for="p in item.permissions.slice(0, 5)" 
                                :key="p"
                                class="text-[10px] bg-secondary/50 px-1.5 py-0.5 rounded text-muted-foreground"
                            >
                                {{ p }}
                            </span>
                            <span v-if="item.permissions.length > 5" class="text-[10px] text-pink-600 font-bold">
                                +{{ item.permissions.length - 5 }} more
                            </span>
                        </div>
                    </template>

                    <template #actions="{ item }">
                        <div class="flex items-center gap-1" v-if="!item.deleted_at && $can('roles.manage')">
                            <Link :href="route('roles.edit', item.id)">
                                <BaseButton variant="ghost" size="icon" class="h-8 w-8">
                                    <Pencil class="w-4 h-4" />
                                </BaseButton>
                            </Link>
                            <BaseButton v-if="!item.is_system" variant="destructive" size="icon" class="h-8 w-8" @click="deleteRole(item.id)">
                                <Trash2 class="w-4 h-4" />
                            </BaseButton>
                        </div>
                        <BaseButton v-else-if="item.deleted_at && $can('roles.manage')" variant="secondary" size="sm" class="h-8" @click="restoreRole(item.id)">
                            <RotateCcw class="w-4 h-4 mr-1" />
                            Restore
                        </BaseButton>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
