<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-5 py-2.5 bg-pink-600 border border-transparent rounded-2xl font-bold text-sm text-white transition-all duration-200 hover:bg-pink-700 focus:bg-pink-700 active:scale-95 active:bg-pink-800 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 disabled:opacity-50 shadow-md shadow-pink-100/50">
        <slot />
    </button>
</template>
