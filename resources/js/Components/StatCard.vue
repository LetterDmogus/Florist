<script setup>
import { defineProps } from 'vue';

defineProps({
    title: String,
    value: [String, Number],
    icon: [Object, Function],
    description: String,
    trend: String,
    trendUp: Boolean,
});
</script>

<template>
    <div class="bg-white p-6 rounded-3xl border border-pink-100 shadow-sm hover:shadow-md transition-shadow duration-300">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-pink-50 rounded-2xl text-pink-600">
                <component :is="icon" class="w-6 h-6" />
            </div>
            <div v-if="trend" :class="['text-xs font-medium px-2 py-1 rounded-full', trendUp ? 'bg-green-50 text-green-600' : 'bg-pink-50 text-pink-600']">
                {{ trend }}
            </div>
        </div>
        <div>
            <p class="text-sm font-medium text-muted-foreground">{{ title }}</p>
            <h3 class="text-2xl font-bold text-pink-950 mt-1">{{ value }}</h3>
            <p v-if="description" class="text-xs text-muted-foreground mt-2">{{ description }}</p>
        </div>
    </div>
</template>
