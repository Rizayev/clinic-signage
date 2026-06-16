<script setup>
const props = defineProps({ status: { type: String, default: 'offline' } });

const map = {
    online: { c: 'bg-green-500', t: 'Онлайн' },
    offline: { c: 'bg-slate-400', t: 'Оффлайн' },
    error: { c: 'bg-red-500', t: 'Ошибка' },
    updating: { c: 'bg-amber-500', t: 'Обновление' },
    disabled: { c: 'bg-slate-300', t: 'Отключён' },
    active: { c: 'bg-green-500', t: 'Активен' },
    inactive: { c: 'bg-slate-400', t: 'Неактивен' },
};
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-sm">
        <span class="w-2.5 h-2.5 rounded-full" :class="(map[props.status] || map.offline).c" />
        {{ (map[props.status] || { t: props.status }).t }}
    </span>
</template>
