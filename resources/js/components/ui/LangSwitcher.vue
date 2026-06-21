<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { SUPPORTED_LOCALES, setLocale } from '@/i18n';

const { locale } = useI18n();
const open = ref(false);
const root = ref(null);

function choose(code) {
    setLocale(code);
    open.value = false;
}

function onClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) open.value = false;
}
onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50 transition"
            @click="open = !open"
        >
            <span>🌐</span>
            <span class="font-medium uppercase">{{ locale }}</span>
            <span class="text-xs text-slate-400">▾</span>
        </button>
        <div
            v-if="open"
            class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-lg shadow-lg py-1 z-50"
        >
            <button
                v-for="l in SUPPORTED_LOCALES"
                :key="l.code"
                type="button"
                class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center justify-between"
                :class="l.code === locale ? 'text-indigo-600 font-medium' : 'text-slate-700'"
                @click="choose(l.code)"
            >
                <span>{{ l.label }}</span>
                <span class="text-xs text-slate-400">{{ l.short }}</span>
            </button>
        </div>
    </div>
</template>
