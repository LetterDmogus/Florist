<script setup>
import { CheckCircle, AlertCircle, X } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    }
});

const emit = defineEmits(['remove']);
</script>

<template>
    <div 
        :class="cn(
            'group pointer-events-auto relative flex w-full items-center justify-between space-x-4 overflow-hidden rounded-2xl border p-4 pr-8 shadow-lg transition-all duration-300',
            item.type === 'success' 
                ? 'bg-emerald-50 border-emerald-100 text-emerald-900' 
                : 'bg-rose-50 border-rose-100 text-rose-900'
        )"
    >
        <div class="flex items-center gap-3">
            <div :class="cn(
                'flex h-8 w-8 items-center justify-center rounded-xl',
                item.type === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'
            )">
                <CheckCircle v-if="item.type === 'success'" class="h-5 w-5" />
                <AlertCircle v-else class="h-5 w-5" />
            </div>
            
            <div class="grid gap-1">
                <div class="text-sm font-bold leading-none tracking-tight">{{ item.title }}</div>
                <div class="text-xs opacity-80 font-medium">{{ item.message }}</div>
            </div>
        </div>

        <button 
            @click="emit('remove', item.id)"
            class="absolute right-2 top-2 rounded-lg p-1 text-foreground/50 opacity-0 transition-opacity hover:text-foreground group-hover:opacity-100"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
</template>
