<script setup>
import { toastState, toast } from '@/lib/toast';
import ToastItem from './ToastItem.vue';
</script>

<template>
    <Teleport to="body">
        <div 
            aria-live="assertive" 
            class="pointer-events-none fixed inset-0 z-[100] flex flex-col items-end gap-3 p-4 sm:p-6"
        >
            <TransitionGroup 
                enter-active-class="transform ease-out duration-300 transition"
                enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
                enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
                move-class="transition-all duration-500"
            >
                <ToastItem 
                    v-for="item in toastState.items" 
                    :key="item.id" 
                    :item="item" 
                    @remove="toast.remove"
                    class="w-full max-w-sm"
                />
            </TransitionGroup>
        </div>
    </Teleport>
</template>
