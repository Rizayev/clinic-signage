<script setup>
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm|md|lg|xl
});
const emit = defineEmits(['update:modelValue']);
function close() {
    emit('update:modelValue', false);
}
const sizes = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="close" />
            <div class="relative bg-white rounded-xl shadow-xl w-full max-h-[90vh] overflow-y-auto" :class="sizes[size] || sizes.md">
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-800">{{ title }}</h3>
                    <button class="text-slate-400 hover:text-slate-700 text-xl leading-none" @click="close">&times;</button>
                </div>
                <div class="p-5">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
