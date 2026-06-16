<script setup>
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
function toggle() {
    if (!props.disabled) emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        class="relative inline-flex h-6 w-11 items-center rounded-full transition disabled:opacity-50"
        :class="modelValue ? 'bg-indigo-600' : 'bg-slate-300'"
        @click="toggle"
        role="switch"
        :aria-checked="modelValue"
    >
        <span
            class="inline-block h-4 w-4 transform rounded-full bg-white transition"
            :class="modelValue ? 'translate-x-6' : 'translate-x-1'"
        />
    </button>
</template>
