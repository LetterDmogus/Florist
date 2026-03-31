<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { 
    Store, 
    MapPin, 
    Phone, 
    MessageSquare,
    Clock,
    Save
} from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    settings: Object,
    business_hours: Object,
});

const form = useForm({
    store_name: props.settings.store_name,
    address: props.settings.address,
    phone: props.settings.phone,
    receipt_note: props.settings.receipt_note,
    mon_fri: props.business_hours.mon_fri,
    sat_sun: props.business_hours.sat_sun,
});

const submit = () => {
    form.patch(route('settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Pengaturan Sistem">
        <template #header>
            <h2 class="font-semibold text-xl text-pink-950 leading-tight">
                Pengaturan Toko
            </h2>
        </template>

        <div class="max-w-4xl space-y-8">
            <!-- Store Information -->
            <div class="bg-white p-8 rounded-[2rem] border border-pink-100 shadow-sm space-y-6">
                <div class="flex items-center gap-3 mb-2">
                    <div class="p-2 bg-pink-50 rounded-xl text-pink-600">
                        <Store class="w-5 h-5" />
                    </div>
                    <h3 class="text-lg font-bold text-pink-950">Informasi Toko</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1">
                        <InputLabel value="Nama Toko" />
                        <div class="relative">
                            <TextInput v-model="form.store_name" class="w-full pl-10" />
                            <Store class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-pink-300" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <InputLabel value="Nomor Telepon" />
                        <div class="relative">
                            <TextInput v-model="form.phone" class="w-full pl-10" />
                            <Phone class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-pink-300" />
                        </div>
                    </div>

                    <div class="md:col-span-2 space-y-1">
                        <InputLabel value="Alamat Toko" />
                        <div class="relative">
                            <textarea 
                                v-model="form.address" 
                                rows="3"
                                class="w-full rounded-xl border-pink-200 text-sm focus:border-pink-400 focus:ring-pink-300"
                            ></textarea>
                            <MapPin class="w-4 h-4 absolute left-3 top-3 text-pink-300" />
                        </div>
                    </div>

                    <div class="md:col-span-2 space-y-1">
                        <InputLabel value="Catatan di Struk" />
                        <div class="relative">
                            <TextInput v-model="form.receipt_note" class="w-full pl-10" />
                            <MessageSquare class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-pink-300" />
                        </div>
                        <p class="text-xs text-muted-foreground mt-1 italic">Muncul di bagian paling bawah struk belanja.</p>
                    </div>
                </div>
            </div>

            <!-- Business Hours -->
            <div class="bg-white p-8 rounded-[2rem] border border-pink-100 shadow-sm space-y-6">
                <div class="flex items-center gap-3 mb-2">
                    <div class="p-2 bg-blue-50 rounded-xl text-blue-600">
                        <Clock class="w-5 h-5" />
                    </div>
                    <h3 class="text-lg font-bold text-pink-950">Jam Operasional</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1">
                        <InputLabel value="Senin - Jumat" />
                        <TextInput v-model="form.mon_fri" class="w-full" placeholder="Contoh: 09:00 - 18:00" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel value="Sabtu - Minggu" />
                        <TextInput v-model="form.sat_sun" class="w-full" placeholder="Contoh: 10:00 - 16:00" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <BaseButton 
                    variant="success" 
                    size="lg" 
                    class="shadow-lg shadow-green-100" 
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Save class="w-5 h-5" v-if="!form.processing" />
                    <span v-if="form.processing">Menyimpan...</span>
                    <span v-else>Simpan Perubahan</span>
                </BaseButton>
            </div>
        </div>
    </AppLayout>
</template>
