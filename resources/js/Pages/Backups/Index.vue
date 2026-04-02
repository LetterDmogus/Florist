<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { 
    Download, 
    Trash2, 
    Database, 
    Plus, 
    FileArchive, 
    HardDrive,
    AlertCircle,
    CheckCircle2,
    Loader2
} from 'lucide-vue-next';

const props = defineProps({
    backups: Array,
});

const isBackingUp = ref(false);

const runBackup = () => {
    isBackingUp.value = true;
    router.post(route('backups.create'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isBackingUp.value = false;
        }
    });
};

const downloadBackup = (path) => {
    window.location.href = route('backups.download', { path });
};

const deleteBackup = (path) => {
    if (confirm('Apakah Anda yakin ingin menghapus file backup ini?')) {
        router.delete(route('backups.destroy'), {
            data: { path },
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout title="System Backup">
        <Head title="System Backup" />
        
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-pink-100 rounded-xl text-pink-600">
                        <Database class="w-6 h-6" />
                    </div>
                    <h2 class="font-semibold text-xl text-pink-950 leading-tight">
                        Database Backup
                    </h2>
                </div>
                
                <BaseButton 
                    variant="primary" 
                    size="lg" 
                    @click="runBackup" 
                    :disabled="isBackingUp"
                    class="shadow-lg shadow-pink-100"
                >
                    <Loader2 v-if="isBackingUp" class="w-5 h-5 mr-2 animate-spin" />
                    <Plus v-else class="w-5 h-5 mr-2" />
                    {{ isBackingUp ? 'Proses Backup...' : 'Jalankan Backup Sekarang' }}
                </BaseButton>
            </div>
        </template>

        <div class="max-w-5xl space-y-8">
            <!-- Warning/Info Info -->
            <div class="bg-blue-50 border border-blue-100 p-6 rounded-[2rem] flex items-start gap-4">
                <div class="p-2 bg-white rounded-xl text-blue-600 shadow-sm">
                    <AlertCircle class="w-5 h-5" />
                </div>
                <div>
                    <h4 class="font-bold text-blue-900">Tentang Backup</h4>
                    <p class="text-sm text-blue-800/80 leading-relaxed mt-1">
                        Sistem ini akan melakukan snapshot database dan menyimpannya dalam format ZIP. 
                        Sangat disarankan untuk mendownload file backup secara berkala dan menyimpannya di tempat yang aman (Cloud Storage atau Harddisk Eksternal).
                    </p>
                </div>
            </div>

            <!-- Backup List -->
            <div class="bg-white rounded-[2rem] border border-pink-100 shadow-sm overflow-hidden">
                <div class="bg-pink-50/50 px-8 py-4 border-b border-pink-100 flex items-center justify-between">
                    <h3 class="font-bold text-pink-950">Daftar File Backup</h3>
                    <span class="text-xs font-medium text-pink-600 bg-white px-3 py-1 rounded-full border border-pink-100">
                        {{ backups.length }} File Tersedia
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-white text-muted-foreground uppercase text-[10px] font-bold tracking-widest">
                            <tr>
                                <th class="px-8 py-4">Nama File</th>
                                <th class="px-8 py-4">Ukuran</th>
                                <th class="px-8 py-4">Tanggal Dibuat</th>
                                <th class="px-8 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-50">
                            <tr v-if="backups.length === 0">
                                <td colspan="4" class="px-8 py-12 text-center text-muted-foreground">
                                    <HardDrive class="w-12 h-12 mx-auto mb-4 opacity-20" />
                                    <p>Belum ada file backup. Silakan klik tombol di atas untuk memulai.</p>
                                </td>
                            </tr>
                            <tr v-for="backup in backups" :key="backup.path" class="hover:bg-pink-50/30 transition-colors group">
                                <td class="px-8 py-4">
                                    <div class="flex items-center gap-3">
                                        <FileArchive class="w-5 h-5 text-pink-400" />
                                        <span class="font-medium text-pink-950">{{ backup.file_name }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-4 text-muted-foreground">{{ backup.file_size }}</td>
                                <td class="px-8 py-4 text-muted-foreground">{{ backup.last_modified }}</td>
                                <td class="px-8 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <BaseButton 
                                            variant="ghost" 
                                            size="icon" 
                                            class="h-9 w-9" 
                                            @click="downloadBackup(backup.path)"
                                            title="Download Backup"
                                        >
                                            <Download class="w-4 h-4" />
                                        </BaseButton>
                                        <BaseButton 
                                            variant="ghost" 
                                            size="icon" 
                                            class="h-9 w-9 text-red-400 hover:text-red-600 hover:bg-red-50" 
                                            @click="deleteBackup(backup.path)"
                                            title="Hapus"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </BaseButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
