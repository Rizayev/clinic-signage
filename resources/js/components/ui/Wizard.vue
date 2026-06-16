<script setup>
import { computed } from 'vue';
import Btn from '@/components/ui/Btn.vue';

const props = defineProps({
    modelValue: { type: Number, default: 0 },   // current step index (v-model)
    steps: { type: Array, required: true },      // [{ label }]
    loading: { type: Boolean, default: false },
    finishText: { type: String, default: 'Сохранить' },
    // validate(stepIndex) => true | string(error message). Blocks "Next"/"Finish".
    validate: { type: Function, default: null },
});
const emit = defineEmits(['update:modelValue', 'finish', 'cancel']);

const current = computed(() => props.modelValue);
const isFirst = computed(() => current.value === 0);
const isLast = computed(() => current.value === props.steps.length - 1);

const error = computed(() => {
    if (!props.validate) return null;
    const r = props.validate(current.value);
    return typeof r === 'string' ? r : null;
});

function go(i) {
    if (i < 0 || i > props.steps.length - 1) return;
    // forward navigation must pass validation of the current step
    if (i > current.value && error.value) return;
    emit('update:modelValue', i);
}

function next() {
    if (error.value) return;
    if (isLast.value) emit('finish');
    else emit('update:modelValue', current.value + 1);
}
</script>

<template>
    <div>
        <!-- Stepper -->
        <div class="flex items-center mb-6">
            <template v-for="(s, i) in steps" :key="i">
                <button
                    type="button"
                    class="flex items-center gap-2 group"
                    :class="i <= current ? 'cursor-pointer' : 'cursor-default'"
                    @click="i < current && go(i)"
                >
                    <span
                        class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-xs font-semibold transition"
                        :class="i < current ? 'bg-indigo-600 text-white'
                            : i === current ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500'
                            : 'bg-slate-100 text-slate-400'"
                    >
                        <span v-if="i < current">✓</span>
                        <span v-else>{{ i + 1 }}</span>
                    </span>
                    <span
                        class="text-sm hidden sm:block"
                        :class="i === current ? 'text-slate-800 font-medium' : 'text-slate-400'"
                    >{{ s.label }}</span>
                </button>
                <div v-if="i < steps.length - 1" class="flex-1 h-px mx-2" :class="i < current ? 'bg-indigo-500' : 'bg-slate-200'" />
            </template>
        </div>

        <!-- Step content -->
        <div class="min-h-[1px]">
            <slot :step="current" :isFirst="isFirst" :isLast="isLast" />
        </div>

        <p v-if="error" class="text-sm text-red-600 mt-3">{{ error }}</p>

        <!-- Footer -->
        <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-100">
            <Btn variant="ghost" @click="isFirst ? emit('cancel') : go(current - 1)">
                {{ isFirst ? 'Отмена' : '← Назад' }}
            </Btn>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400">Шаг {{ current + 1 }} из {{ steps.length }}</span>
                <Btn variant="primary" :loading="loading" :disabled="!!error" @click="next">
                    {{ isLast ? finishText : 'Далее →' }}
                </Btn>
            </div>
        </div>
    </div>
</template>
