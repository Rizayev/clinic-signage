<script setup>
defineProps({
    icon: { type: String, default: '📭' },
    title: { type: String, default: 'Пока нет данных' },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center py-14 px-6">
        <div class="text-5xl mb-3 opacity-60">{{ icon }}</div>
        <h3 class="text-slate-700 font-medium">{{ title }}</h3>
        <p v-if="hint" class="text-sm text-slate-400 mt-1 max-w-sm">{{ hint }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
