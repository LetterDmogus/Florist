<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { 
    ShieldCheck, 
    ArrowLeft, 
    Save, 
    ShieldAlert, 
    CheckSquare, 
    Square,
    Trash2
} from 'lucide-vue-next';

const props = defineProps({
    role: {
        type: Object,
        default: null,
    },
    permissions: Object, // Grouped permissions
});

const isEditing = computed(() => !!props.role);

const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions ?? [],
});

const togglePermission = (permissionName) => {
    const index = form.permissions.indexOf(permissionName);
    if (index > -1) {
        form.permissions.splice(index, 1);
    } else {
        form.permissions.push(permissionName);
    }
};

const toggleModule = (modulePermissions) => {
    const names = modulePermissions.map(p => p.name);
    const allSelected = names.every(name => form.permissions.includes(name));
    
    if (allSelected) {
        form.permissions = form.permissions.filter(p => !names.includes(p));
    } else {
        const toAdd = names.filter(name => !form.permissions.includes(name));
        form.permissions.push(...toAdd);
    }
};

const isModuleSelected = (modulePermissions) => {
    return modulePermissions.map(p => p.name).every(name => form.permissions.includes(name));
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('roles.update', props.role.id));
    } else {
        form.post(route('roles.store'));
    }
};

const deleteRole = () => {
    if (confirm('Are you sure you want to delete this role?')) {
        router.delete(route('roles.destroy', props.role.id));
    }
};
</script>

<template>
    <AppLayout :title="isEditing ? 'Edit Role' : 'Tambah Role'">
        <Head :title="isEditing ? `Edit Role: ${role.name}` : 'Tambah Role Baru'" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('roles.index')" class="p-2 hover:bg-pink-100 rounded-xl transition-colors text-pink-600">
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <h2 class="font-semibold text-xl text-pink-950 leading-tight">
                        {{ isEditing ? 'Edit Role' : 'Buat Role Baru' }}
                    </h2>
                </div>
                
                <div class="flex items-center gap-3">
                    <BaseButton 
                        v-if="isEditing && !role.is_system" 
                        variant="destructive" 
                        size="sm"
                        @click="deleteRole"
                    >
                        <Trash2 class="w-4 h-4 mr-1" />
                        Hapus Role
                    </BaseButton>
                    <BaseButton variant="success" size="lg" @click="submit" :disabled="form.processing">
                        <Save class="w-5 h-5 mr-2" />
                        {{ isEditing ? 'Simpan Perubahan' : 'Simpan Role' }}
                    </BaseButton>
                </div>
            </div>
        </template>

        <div class="max-w-5xl space-y-8">
            <!-- Basic Info Card -->
            <div class="bg-white p-8 rounded-[2rem] border border-pink-100 shadow-sm space-y-6">
                <div class="flex items-center gap-3 mb-2">
                    <div class="p-2 bg-pink-50 rounded-xl text-pink-600">
                        <ShieldCheck class="w-5 h-5" />
                    </div>
                    <h3 class="text-lg font-bold text-pink-950">Informasi Dasar</h3>
                </div>

                <div class="max-w-md space-y-2">
                    <InputLabel for="name" value="Nama Role" />
                    <TextInput 
                        id="name" 
                        v-model="form.name" 
                        class="w-full" 
                        placeholder="Contoh: Senior Kasir" 
                        :disabled="role?.is_system"
                    />
                    <InputError :message="form.errors.name" />
                    <p v-if="role?.is_system" class="text-[10px] text-pink-600 italic">Nama role sistem tidak dapat diubah.</p>
                </div>
            </div>

            <!-- Permissions Card -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-2">
                    <h4 class="font-bold text-pink-950 flex items-center gap-2">
                        <ShieldAlert class="w-4 h-4" />
                        Hak Akses & Fitur
                    </h4>
                    <span class="text-xs font-medium text-pink-600 bg-pink-50 px-3 py-1 rounded-full border border-pink-100">
                        {{ form.permissions.length }} Akses Terpilih
                    </span>
                </div>

                <!-- Grouped Permissions Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div 
                        v-for="(modulePermissions, moduleName) in permissions" 
                        :key="moduleName"
                        class="bg-white rounded-[2rem] border border-pink-100 shadow-sm overflow-hidden flex flex-col"
                    >
                        <div class="bg-gradient-to-r from-pink-50/80 to-white px-6 py-4 border-b border-pink-100 flex items-center justify-between">
                            <h5 class="text-sm font-bold text-pink-900 uppercase tracking-widest">{{ moduleName }}</h5>
                            <button 
                                type="button"
                                @click="toggleModule(modulePermissions)"
                                class="text-[10px] font-bold text-pink-600 hover:text-pink-800 flex items-center gap-1.5 px-2 py-1 rounded-lg hover:bg-white transition-colors"
                            >
                                <CheckSquare v-if="isModuleSelected(modulePermissions)" class="w-3.5 h-3.5" />
                                <Square v-else class="w-3.5 h-3.5" />
                                {{ isModuleSelected(modulePermissions) ? 'Unselect All' : 'Select All' }}
                            </button>
                        </div>
                        <div class="p-6 grid grid-cols-1 gap-3">
                            <label 
                                v-for="permission in modulePermissions" 
                                :key="permission.id"
                                class="flex items-center gap-3 p-3 rounded-2xl hover:bg-pink-50/50 cursor-pointer transition-all group border border-transparent hover:border-pink-100"
                            >
                                <div class="relative flex items-center">
                                    <input 
                                        type="checkbox" 
                                        :value="permission.name" 
                                        v-model="form.permissions"
                                        class="peer h-5 w-5 rounded-lg border-pink-200 text-pink-600 focus:ring-pink-500 transition-all"
                                    >
                                </div>
                                <span class="text-sm text-pink-950 group-hover:text-pink-700 transition-colors capitalize">
                                    {{ permission.name.split('.').slice(1).join(' ') || permission.name }}
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end gap-4 border-t border-pink-100 pt-8 pb-12">
                <Link :href="route('roles.index')">
                    <SecondaryButton type="button" class="rounded-2xl">Batal</SecondaryButton>
                </Link>
                <BaseButton variant="success" size="lg" @click="submit" :disabled="form.processing" class="shadow-lg shadow-green-100 px-10">
                    {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Update Role' : 'Simpan Role Baru') }}
                </BaseButton>
            </div>
        </div>
    </AppLayout>
</template>
