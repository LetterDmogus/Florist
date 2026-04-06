<script setup>
import Modal from '@/Components/Modal.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { User, Clock, Calendar, ShieldCheck, Tag, Info, Package, DollarSign, History, Plus, Edit2, Trash2, Activity } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
    show: Boolean,
    title: String,
    item: Object,
    audit: Object,
    type: String, // 'bouquet', 'inventory', 'customer', etc
});

const emit = defineEmits(['close']);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value) || 0);
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="3xl">
        <div v-if="item" class="p-0 overflow-hidden bg-white rounded-3xl border-2 border-pink-100 shadow-2xl">
            <div class="grid md:grid-cols-12">
                <!-- Left Column: Visual -->
                <div class="md:col-span-5 bg-pink-50 min-h-[300px] border-r-2 border-pink-100 flex items-center justify-center relative overflow-hidden">
                    <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover transition-transform hover:scale-110 duration-700" />
                    <div v-else class="flex flex-col items-center justify-center text-pink-200">
                        <Tag v-if="type === 'bouquet'" class="w-24 h-24 mb-4" />
                        <Package v-else-if="type === 'inventory'" class="w-24 h-24 mb-4" />
                        <User v-else class="w-24 h-24 mb-4" />
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]">No Media Preview</span>
                    </div>
                    
                    <!-- Type Badge -->
                    <div class="absolute top-4 left-4">
                        <span class="px-3 py-1 bg-white/90 backdrop-blur rounded-full text-[10px] font-black uppercase tracking-widest text-pink-600 border-2 border-pink-100 shadow-sm">
                            {{ type }}
                        </span>
                    </div>
                </div>

                <!-- Right Column: Details & Audit -->
                <div class="md:col-span-7 p-8">
                    <div class="space-y-6">
                        <!-- Header Info -->
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span v-if="item.serial_number" class="px-2 py-0.5 bg-secondary/30 text-muted-foreground rounded-lg text-[10px] font-bold border border-secondary/50">
                                    {{ item.serial_number }}
                                </span>
                                <span v-if="item.category?.name || item.type?.name" class="px-2 py-0.5 bg-pink-100 text-pink-700 rounded-lg text-[10px] font-bold border border-pink-200">
                                    {{ item.category?.name || item.type?.name }}
                                </span>
                            </div>
                            <h2 class="text-3xl font-black text-pink-950 leading-tight">{{ item.name }}</h2>
                            <p v-if="item.price" class="text-2xl font-bold text-pink-600 mt-1">{{ formatCurrency(item.price) }}</p>
                        </div>

                        <!-- Main Info Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <slot name="extra-info" :item="item" />
                            
                            <div v-if="item.stock !== undefined" class="col-span-2 p-4 bg-blue-50 rounded-2xl border-2 border-blue-100 flex items-center justify-between text-blue-900">
                                <div class="flex items-center gap-3">
                                    <Package class="w-5 h-5" />
                                    <span class="text-xs font-bold uppercase tracking-widest">Stok Saat Ini</span>
                                </div>
                                <span class="text-xl font-black">{{ item.stock }} {{ item.unit?.name || 'Pcs' }}</span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div v-if="item.description" class="space-y-2">
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <Info class="w-3.5 h-3.5" />
                                <span class="text-[10px] font-bold uppercase tracking-widest">Deskripsi</span>
                            </div>
                            <p class="text-sm text-pink-900 leading-relaxed bg-pink-50/30 p-4 rounded-2xl border-2 border-pink-100/50 italic font-medium">
                                "{{ item.description }}"
                            </p>
                        </div>

                        <!-- Audit Section (Audit Trail) -->
                        <div class="bg-secondary/10 rounded-2xl border-2 border-secondary/20 overflow-hidden">
                            <div class="px-4 py-2 bg-secondary/20 border-b border-secondary/20 flex items-center gap-2">
                                <History class="w-3.5 h-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Log & Audit Trail</span>
                            </div>
                            <div class="p-4 space-y-4">
                                <!-- Created -->
                                <div v-if="audit?.creator" class="flex items-start gap-3">
                                    <div class="mt-1 p-1.5 bg-emerald-100 rounded-lg text-emerald-600">
                                        <ShieldCheck class="w-3.5 h-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-tighter">Dibuat Oleh</p>
                                        <p class="text-xs font-bold text-pink-950">{{ audit.creator.name }}</p>
                                        <p class="text-[10px] text-muted-foreground">{{ formatDateTime(audit.creator.time) }}</p>
                                    </div>
                                </div>

                                <!-- Updated -->
                                <div v-if="audit?.updater" class="flex items-start gap-3">
                                    <div class="mt-1 p-1.5 bg-blue-100 rounded-lg text-blue-600">
                                        <Clock class="w-3.5 h-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-tighter">Update Terakhir</p>
                                        <p class="text-xs font-bold text-pink-950">{{ audit.updater.name }}</p>
                                        <p class="text-[10px] text-muted-foreground">{{ formatDateTime(audit.updater.time) }}</p>
                                    </div>
                                </div>

                                <!-- Deleted -->
                                <div v-if="audit?.remover" class="flex items-start gap-3">
                                    <div class="mt-1 p-1.5 bg-red-100 rounded-lg text-red-600">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-tighter">Dihapus Oleh</p>
                                        <p class="text-xs font-bold text-pink-950">{{ audit.remover.name }}</p>
                                        <p class="text-[10px] text-muted-foreground">{{ formatDateTime(audit.remover.time) }}</p>
                                    </div>
                                </div>

                                <!-- History Timeline -->
                                <div v-if="audit?.history?.length > 0" class="pt-4 mt-2 border-t border-secondary/20">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/50 mb-6 flex items-center gap-2">
                                        <History class="w-3 h-3" />
                                        Timeline Aktivitas
                                    </p>
                                    
                                    <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-thin scrollbar-thumb-pink-200 scrollbar-track-transparent snap-x">
                                        <div v-for="log in audit.history" :key="log.id" class="min-w-[240px] relative group flex-shrink-0 snap-start">
                                            <!-- Icon & Connector -->
                                            <div class="flex items-center gap-2 mb-3">
                                                <div :class="cn(
                                                    'w-7 h-7 rounded-full border-2 border-white shadow-sm flex items-center justify-center transition-all group-hover:scale-110 z-10',
                                                    log.event === 'created' ? 'bg-emerald-500 text-white' : 
                                                    log.event === 'updated' ? 'bg-blue-500 text-white' : 
                                                    log.event === 'deleted' ? 'bg-red-500 text-white' : 
                                                    'bg-secondary text-muted-foreground'
                                                )">
                                                    <Plus v-if="log.event === 'created'" class="w-3 h-3" />
                                                    <Edit2 v-else-if="log.event === 'updated'" class="w-3 h-3" />
                                                    <Trash2 v-else-if="log.event === 'deleted'" class="w-3 h-3" />
                                                    <Activity v-else class="w-3 h-3" />
                                                </div>
                                                <div class="h-0.5 flex-1 bg-secondary/30 group-last:hidden"></div>
                                            </div>

                                            <div class="bg-white/50 p-4 rounded-2xl border border-secondary/30 transition-all group-hover:bg-white group-hover:shadow-md group-hover:border-pink-200">
                                                <p class="text-[11px] font-black text-pink-950 leading-tight mb-2">{{ log.description }}</p>
                                                
                                                <!-- Optional: Show dirty properties summary if it's an update -->
                                                <div v-if="log.event === 'updated' && log.properties?.attributes" class="mb-3 flex flex-wrap gap-1">
                                                    <span v-for="(val, key) in log.properties.attributes" :key="key" class="text-[8px] bg-secondary/30 px-1.5 py-0.5 rounded-sm text-muted-foreground font-bold uppercase tracking-tighter">
                                                        {{ key }}
                                                    </span>
                                                </div>

                                                <div class="flex flex-col gap-1.5 pt-2 border-t border-secondary/20">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="w-4 h-4 rounded-full bg-pink-100 flex items-center justify-center text-[8px] font-black text-pink-600 uppercase">{{ log.causer_name.charAt(0) }}</div>
                                                        <span class="text-[9px] font-black text-pink-600 uppercase tracking-tighter">{{ log.causer_name }}</span>
                                                    </div>
                                                    <span class="text-[9px] text-muted-foreground font-medium flex items-center gap-1">
                                                        <Clock class="w-2.5 h-2.5 opacity-50" />
                                                        {{ formatDateTime(log.time) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="pt-2">
                            <BaseButton variant="secondary" class="w-full rounded-2xl border-2 py-3" @click="emit('close')">
                                Tutup Detail
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
