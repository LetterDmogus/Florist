<script setup>
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
</script>

<template>
    <Link :href="'/'" class="inline-flex items-center justify-center">
        <ApplicationLogo class="h-[7rem] w-auto" />
    </Link>
</template>
