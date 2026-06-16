<script setup>
defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-slate-700 mb-1">
            {{ label }}<span v-if="required" class="text-red-500"> *</span>
        </label>
        <slot />
        <p v-if="error" class="text-xs text-red-600 mt-1">{{ error }}</p>
        <p v-else-if="hint" class="text-xs text-slate-400 mt-1">{{ hint }}</p>
    </div>
</template>
