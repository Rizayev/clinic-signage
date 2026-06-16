<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">{{ title }}</h1>
            <p v-if="subtitle" class="text-sm text-slate-500 mt-0.5">{{ subtitle }}</p>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
