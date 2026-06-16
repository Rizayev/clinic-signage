<script setup>
defineProps({ title: { type: String, default: '' } });
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div v-if="title || $slots.actions" class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800">{{ title }}</h3>
            <div><slot name="actions" /></div>
        </div>
        <div class="p-5">
            <slot />
        </div>
    </div>
</template>
