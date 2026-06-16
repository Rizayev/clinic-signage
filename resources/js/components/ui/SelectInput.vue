<script setup>
defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    options: { type: Array, default: () => [] }, // [{value,label}] or strings
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);

function optValue(o) { return typeof o === 'object' ? o.value : o; }
function optLabel(o) { return typeof o === 'object' ? o.label : o; }
</script>

<template>
    <select
        :value="modelValue"
        :disabled="disabled"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 bg-white outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 disabled:bg-slate-50"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <option v-if="placeholder" value="">{{ placeholder }}</option>
        <option v-for="o in options" :key="optValue(o)" :value="optValue(o)">{{ optLabel(o) }}</option>
    </select>
</template>
