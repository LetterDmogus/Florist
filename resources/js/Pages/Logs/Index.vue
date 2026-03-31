<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { Head } from '@inertiajs/vue3';
import { History, Eye, User, FileText, Activity } from 'lucide-vue-next';

const props = defineProps({
    activities: Object,
    users: Array,
    filters: Object,
});

const selectedActivity = ref(null);

const columns = [
    { label: 'Time', key: 'created_at' },
    { label: 'User', key: 'causer' },
    { label: 'Action', key: 'description' },
    { label: 'Subject', key: 'subject' },
    { label: 'Event', key: 'event' },
];

const formatDate = (date) => {
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getEventColor = (event) => {
    switch (event) {
        case 'created': return 'bg-emerald-100 text-emerald-700';
        case 'updated': return 'bg-blue-100 text-blue-700';
        case 'deleted': return 'bg-red-100 text-red-700';
        case 'restored': return 'bg-purple-100 text-purple-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};

const openDetail = (activity) => {
    selectedActivity.value = activity;
};

const closeDetail = () => {
    selectedActivity.value = null;
};
</script>

<template>
    <AppLayout title="Activity Log">
        <Head title="Activity Log" />
        
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-pink-100 rounded-xl text-pink-600">
                    <History class="w-6 h-6" />
                </div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Log Aktivitas Sistem
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <DataTable 
                    :data="activities" 
                    :columns="columns" 
                    :filters="filters"
                    routeName="activities.index"
                    :showRecycleBin="false"
                    searchPlaceholder="Cari deskripsi, log name..."
                >
                    <template #extra-filters>
                        <select 
                            v-model="filters.causer_id" 
                            class="rounded-xl border-secondary text-sm focus:ring-primary/50"
                            @change="$inertia.get(route('activities.index'), { ...filters, causer_id: $event.target.value }, { preserveState: true })"
                        >
                            <option value="">Semua User</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </template>

                    <template #cell-created_at="{ item }">
                        <span class="text-xs text-muted-foreground">{{ formatDate(item.created_at) }}</span>
                    </template>

                    <template #cell-causer="{ item }">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-pink-50 flex items-center justify-center text-[10px] font-bold text-pink-600 uppercase">
                                {{ item.causer?.name?.charAt(0) || 'S' }}
                            </div>
                            <span class="text-sm font-medium">{{ item.causer?.name || 'System' }}</span>
                        </div>
                    </template>

                    <template #cell-subject="{ item }">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-pink-900">{{ item.subject_type?.split('\\').pop() }}</span>
                            <span class="text-[10px] text-muted-foreground">ID: {{ item.subject_id }}</span>
                        </div>
                    </template>

                    <template #cell-event="{ item }">
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider', getEventColor(item.event)]">
                            {{ item.event }}
                        </span>
                    </template>

                    <template #actions="{ item }">
                        <button 
                            @click="openDetail(item)"
                            class="p-2 text-pink-600 hover:bg-pink-50 rounded-lg transition-colors"
                            title="Lihat Detail"
                        >
                            <Eye class="w-4 h-4" />
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Detail Modal -->
        <Modal :show="!!selectedActivity" @close="closeDetail" max-width="2xl">
            <div v-if="selectedActivity" class="p-8 space-y-6">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div :class="['p-3 rounded-2xl', getEventColor(selectedActivity.event)]">
                            <Activity class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-pink-950">Detail Aktivitas</h3>
                            <p class="text-sm text-muted-foreground">{{ formatDate(selectedActivity.created_at) }}</p>
                        </div>
                    </div>
                    <button @click="closeDetail" class="text-muted-foreground hover:text-foreground">
                        <XCircle class="w-6 h-6" />
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-pink-50/50 p-4 rounded-2xl border border-pink-100">
                        <p class="text-xs font-bold text-pink-600 uppercase mb-1">Pelaku (Causer)</p>
                        <p class="text-sm font-semibold">{{ selectedActivity.causer?.name || 'System' }}</p>
                        <p class="text-xs text-muted-foreground">{{ selectedActivity.causer?.email || '-' }}</p>
                    </div>
                    <div class="bg-blue-50/50 p-4 rounded-2xl border border-blue-100">
                        <p class="text-xs font-bold text-blue-600 uppercase mb-1">Objek (Subject)</p>
                        <p class="text-sm font-semibold">{{ selectedActivity.subject_type?.split('\\').pop() }}</p>
                        <p class="text-xs text-muted-foreground">ID: {{ selectedActivity.subject_id }}</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="font-bold text-pink-950 flex items-center gap-2">
                        <FileText class="w-4 h-4" />
                        Perubahan Data
                    </h4>
                    
                    <div class="bg-gray-900 rounded-2xl p-6 overflow-hidden">
                        <pre class="text-xs text-emerald-400 font-mono overflow-auto max-h-[300px]">{{ JSON.stringify(selectedActivity.properties, null, 2) }}</pre>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button 
                        @click="closeDetail"
                        class="px-6 py-2.5 bg-secondary text-foreground rounded-xl font-semibold hover:bg-secondary/80 transition-all"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
