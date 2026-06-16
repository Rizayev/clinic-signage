<script setup>
defineProps({
    label: { type: String, default: '' },
    center: { type: Boolean, default: true },
});
</script>

<template>
    <div :class="center ? 'flex flex-col items-center justify-center py-12 gap-3' : 'inline-flex items-center gap-2'">
        <span class="inline-block w-6 h-6 border-2 border-slate-200 border-t-indigo-600 rounded-full animate-spin" />
        <span v-if="label" class="text-sm text-slate-500">{{ label }}</span>
    </div>
</template>
