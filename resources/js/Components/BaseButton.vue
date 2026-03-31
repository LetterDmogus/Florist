<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => [
            'primary', 
            'secondary', 
            'success', 
            'warning', 
            'info', 
            'destructive', 
            'ghost'
        ].includes(value)
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'icon'].includes(value)
    },
    href: {
        type: String,
        default: null
    },
    as: {
        type: String,
        default: 'button'
    },
    disabled: {
        type: Boolean,
        default: false
    }
});

const variantClasses = {
    primary: 'bg-pink-600 text-white hover:bg-pink-700 shadow-sm shadow-pink-100',
    secondary: 'bg-white text-pink-700 border border-pink-200 hover:bg-pink-50',
    success: 'bg-success text-success-foreground hover:bg-opacity-80 shadow-sm shadow-green-100',
    warning: 'bg-warning text-warning-foreground hover:bg-opacity-80 shadow-sm shadow-orange-100',
    info: 'bg-info text-info-foreground hover:bg-opacity-80 shadow-sm shadow-blue-100',
    destructive: 'bg-destructive text-destructive-foreground hover:bg-opacity-90 shadow-sm shadow-red-100',
    ghost: 'bg-transparent text-pink-700 hover:bg-pink-50'
};

const sizeClasses = {
    sm: 'px-3 py-1.5 text-xs rounded-xl',
    md: 'px-5 py-2.5 text-sm rounded-2xl',
    lg: 'px-8 py-4 text-base rounded-[1.25rem]',
    icon: 'p-2.5 rounded-xl'
};

const classes = computed(() => {
    return [
        'inline-flex items-center justify-center gap-2 font-semibold transition-all duration-200 active:scale-95 disabled:opacity-50 disabled:pointer-events-none',
        variantClasses[props.variant],
        sizeClasses[props.size]
    ].join(' ');
});
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <slot />
    </Link>
    <button v-else :type="as" :class="classes" :disabled="disabled">
        <slot />
    </button>
</template>
