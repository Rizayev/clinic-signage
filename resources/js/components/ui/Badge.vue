<script setup>
const props = defineProps({
    color: { type: String, default: 'slate' }, // slate|green|red|amber|indigo|blue|violet
});
const map = {
    slate: 'bg-slate-100 text-slate-600',
    green: 'bg-green-100 text-green-700',
    red: 'bg-red-100 text-red-700',
    amber: 'bg-amber-100 text-amber-700',
    indigo: 'bg-indigo-100 text-indigo-700',
    blue: 'bg-blue-100 text-blue-700',
    violet: 'bg-violet-100 text-violet-700',
};
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="map[props.color] || map.slate">
        <slot />
    </span>
</template>
