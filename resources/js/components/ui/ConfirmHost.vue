<script setup>
import { useConfirm } from '@/composables/useConfirm';

const { state, answer } = useConfirm();
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="state.open" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="answer(false)" />
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <span
                                class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-lg"
                                :class="state.variant === 'danger' ? 'bg-red-100 text-red-600' : 'bg-indigo-100 text-indigo-600'"
                            >{{ state.variant === 'danger' ? '⚠' : '?' }}</span>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-800">{{ state.title }}</h3>
                                <p v-if="state.message" class="text-sm text-slate-500 mt-1">{{ state.message }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-3 bg-slate-50 rounded-b-xl flex justify-end gap-2">
                        <button
                            class="rounded-lg px-4 py-2 text-sm font-medium bg-white border border-slate-200 hover:bg-slate-100 text-slate-700"
                            @click="answer(false)"
                        >{{ state.cancelText }}</button>
                        <button
                            class="rounded-lg px-4 py-2 text-sm font-medium text-white"
                            :class="state.variant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                            @click="answer(true)"
                        >{{ state.confirmText }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
