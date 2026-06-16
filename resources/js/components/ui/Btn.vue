<script setup>
const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary|secondary|danger|ghost
    size: { type: String, default: 'md' }, // sm|md
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const classes = {
    primary: 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm',
    secondary: 'bg-white border border-slate-200 hover:bg-slate-50 text-slate-700',
    danger: 'bg-red-600 hover:bg-red-700 text-white shadow-sm',
    ghost: 'bg-transparent hover:bg-slate-100 text-slate-700',
};
const sizes = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
        :class="[classes[props.variant], sizes[props.size]]"
    >
        <span v-if="loading" class="inline-block w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin" />
        <slot />
    </button>
</template>
