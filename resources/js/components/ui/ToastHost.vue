<script setup>
import { useToast } from '@/composables/useToast';

const { state, dismiss } = useToast();

const styles = {
    success: 'bg-white border-l-4 border-green-500 text-slate-800',
    error: 'bg-white border-l-4 border-red-500 text-slate-800',
    info: 'bg-white border-l-4 border-indigo-500 text-slate-800',
    warning: 'bg-white border-l-4 border-amber-500 text-slate-800',
};
const icons = { success: '✓', error: '✕', info: 'ℹ', warning: '⚠' };
const iconColor = {
    success: 'text-green-600',
    error: 'text-red-600',
    info: 'text-indigo-600',
    warning: 'text-amber-600',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-80 max-w-[calc(100vw-2rem)]">
            <TransitionGroup name="toast">
                <div
                    v-for="t in state.toasts"
                    :key="t.id"
                    class="flex items-start gap-3 rounded-lg shadow-lg px-4 py-3 text-sm"
                    :class="styles[t.type] || styles.info"
                    role="status"
                >
                    <span class="font-bold mt-0.5" :class="iconColor[t.type] || iconColor.info">{{ icons[t.type] || icons.info }}</span>
                    <span class="flex-1 leading-snug">{{ t.message }}</span>
                    <button class="text-slate-400 hover:text-slate-700 leading-none" @click="dismiss(t.id)">&times;</button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(20px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
</style>
