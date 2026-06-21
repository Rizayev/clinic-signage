<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Modal from '@/components/ui/Modal.vue';
import Badge from '@/components/ui/Badge.vue';
import Toggle from '@/components/ui/Toggle.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import Wizard from '@/components/ui/Wizard.vue';

const toast = useToast();
const { confirm } = useConfirm();
const { t } = useI18n();

const tickers = ref([]);
const loading = ref(true);

const showModal = ref(false);
const saving = ref(false);
const editingId = ref(null);
const step = ref(0);
const errors = reactive({});

const wizardSteps = computed(() => [
    { label: t('tickers.stepText') },
    { label: t('tickers.stepStyling') },
    { label: t('tickers.stepSchedule') },
    { label: t('tickers.stepTargeting') },
]);

function validateStep(i) {
    if (i === 0 && !form.text.trim()) return t('tickers.validationText');
    return true;
}

const positions = computed(() => [
    { value: 'top', label: t('tickers.positionTop') },
    { value: 'bottom', label: t('tickers.positionBottom') },
]);

const targetTypes = computed(() => [
    { value: 'all', label: t('tickers.targetAll') },
    { value: 'branch', label: t('tickers.targetBranch') },
    { value: 'zone', label: t('tickers.targetZone') },
    { value: 'device', label: t('tickers.targetDevice') },
]);

const targetColors = {
    all: 'indigo',
    branch: 'blue',
    zone: 'violet',
    device: 'amber',
};

function emptyForm() {
    return {
        title: '',
        text: '',
        position: 'bottom',
        speed: 60,
        font_size: 28,
        text_color: '#ffffff',
        background_color: '#000000',
        opacity: 0.8,
        target_type: 'all',
        start_date: '',
        end_date: '',
        start_time: '',
        end_time: '',
        repeat_count: null,
        duration_minutes: null,
        interval_minutes: null,
        is_active: true,
    };
}

const toDateInput = (v) => (v ? String(v).slice(0, 10) : '');
const toTimeInput = (v) => {
    if (!v) return '';
    const m = String(v).match(/(\d{2}):(\d{2})/);
    return m ? `${m[1]}:${m[2]}` : '';
};

const form = reactive(emptyForm());

function targetLabel(type) {
    return targetTypes.value.find((x) => x.value === type)?.label ?? type ?? '—';
}

function positionLabel(pos) {
    return positions.value.find((p) => p.value === pos)?.label ?? pos ?? '—';
}

function clearErrors() {
    Object.keys(errors).forEach((k) => delete errors[k]);
}

function applyValidationErrors(e) {
    const bag = e?.response?.data?.errors;
    if (bag) {
        Object.entries(bag).forEach(([key, msgs]) => {
            errors[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
        });
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/tickers');
        tickers.value = data.data ?? [];
    } catch (e) {
        toast.error(e?.response?.data?.message || t('tickers.loadError'));
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    Object.assign(form, emptyForm());
    clearErrors();
    step.value = 0;
    showModal.value = true;
}

function openEdit(ticker) {
    editingId.value = ticker.id;
    Object.assign(form, {
        title: ticker.title ?? '',
        text: ticker.text ?? '',
        position: ticker.position ?? 'bottom',
        speed: ticker.speed ?? 60,
        font_size: ticker.font_size ?? 28,
        text_color: ticker.text_color ?? '#ffffff',
        background_color: ticker.background_color ?? '#000000',
        opacity: ticker.opacity != null ? Number(ticker.opacity) : 0.8,
        target_type: ticker.target_type ?? 'all',
        start_date: toDateInput(ticker.start_date),
        end_date: toDateInput(ticker.end_date),
        start_time: toTimeInput(ticker.start_time),
        end_time: toTimeInput(ticker.end_time),
        repeat_count: ticker.repeat_count ?? null,
        duration_minutes: ticker.duration_minutes ?? null,
        interval_minutes: ticker.interval_minutes ?? null,
        is_active: !!ticker.is_active,
    });
    clearErrors();
    step.value = 0;
    showModal.value = true;
}

async function save() {
    saving.value = true;
    clearErrors();
    const payload = {
        title: form.title || null,
        text: form.text,
        position: form.position,
        speed: Number(form.speed),
        font_size: Number(form.font_size),
        text_color: form.text_color,
        background_color: form.background_color,
        opacity: Number(form.opacity),
        target_type: form.target_type,
        start_date: form.start_date || null,
        end_date: form.end_date || null,
        start_time: form.start_time || null,
        end_time: form.end_time || null,
        repeat_count: form.repeat_count === '' || form.repeat_count == null ? null : Number(form.repeat_count),
        duration_minutes: form.duration_minutes === '' || form.duration_minutes == null ? null : Number(form.duration_minutes),
        interval_minutes: form.interval_minutes === '' || form.interval_minutes == null ? null : Number(form.interval_minutes),
        is_active: form.is_active,
    };
    try {
        if (editingId.value) {
            await api.put(`/tickers/${editingId.value}`, payload);
        } else {
            await api.post('/tickers', payload);
        }
        showModal.value = false;
        toast.success(editingId.value ? t('tickers.updated') : t('tickers.created'));
        await load();
    } catch (e) {
        applyValidationErrors(e);
        toast.error(e?.response?.data?.message || t('tickers.saveError'));
    } finally {
        saving.value = false;
    }
}

async function toggleActive(ticker) {
    const next = !ticker.is_active;
    try {
        // PUT replaces the record — send the full row with only is_active flipped.
        await api.put(`/tickers/${ticker.id}`, {
            title: ticker.title,
            text: ticker.text,
            position: ticker.position,
            speed: ticker.speed,
            font_size: ticker.font_size,
            text_color: ticker.text_color,
            background_color: ticker.background_color,
            opacity: ticker.opacity != null ? Number(ticker.opacity) : 0.8,
            target_type: ticker.target_type,
            target_id: ticker.target_id ?? null,
            is_active: next,
        });
        toast.success(next ? t('tickers.enabled') : t('tickers.disabled'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('tickers.statusError'));
    }
}

async function remove(ticker) {
    const name = ticker.title || ticker.text || t('tickers.defaultName');
    if (
        !(await confirm({
            title: t('tickers.confirmDeleteTitle'),
            message: t('tickers.confirmDeleteMessage', { name }),
            confirmText: t('common.delete'),
        }))
    )
        return;
    try {
        await api.delete(`/tickers/${ticker.id}`);
        toast.success(t('tickers.deleted'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('tickers.deleteError'));
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader :title="$t('tickers.pageTitle')" :subtitle="$t('tickers.pageSubtitle')">
            <template #actions>
                <Btn variant="secondary" @click="load">{{ $t('tickers.refresh') }}</Btn>
                <Btn variant="primary" @click="openCreate">{{ $t('tickers.addTicker') }}</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" :label="$t('common.loading')" />

            <EmptyState
                v-else-if="!tickers.length"
                icon="↔"
                :title="$t('tickers.emptyTitle')"
                :hint="$t('tickers.emptyHint')"
            >
                <template #action>
                    <Btn variant="primary" @click="openCreate">{{ $t('tickers.addTicker') }}</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">{{ $t('tickers.colText') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('tickers.colPosition') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('tickers.colSpeed') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('tickers.colColors') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('tickers.colTarget') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('tickers.colActive') }}</th>
                            <th class="py-2.5 px-3 font-medium text-right">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ticker in tickers"
                            :key="ticker.id"
                            class="border-b border-slate-50 hover:bg-slate-50 transition"
                        >
                            <td class="py-2.5 px-3">
                                <div
                                    class="text-slate-800 truncate max-w-[260px]"
                                    :title="ticker.text"
                                >
                                    {{ ticker.text }}
                                </div>
                                <div
                                    v-if="ticker.title"
                                    class="text-xs text-slate-400 truncate max-w-[260px]"
                                    :title="ticker.title"
                                >
                                    {{ ticker.title }}
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-slate-600">{{ positionLabel(ticker.position) }}</td>
                            <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">{{ ticker.speed }} {{ $t('tickers.speedUnit') }}</td>
                            <td class="py-2.5 px-3">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span
                                            class="w-4 h-4 rounded border border-slate-200"
                                            :style="{ backgroundColor: ticker.text_color }"
                                        />
                                        <span class="text-xs text-slate-500">{{ ticker.text_color }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <span
                                            class="w-4 h-4 rounded border border-slate-200"
                                            :style="{ backgroundColor: ticker.background_color }"
                                        />
                                        <span class="text-xs text-slate-500">{{ ticker.background_color }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="py-2.5 px-3">
                                <Badge :color="targetColors[ticker.target_type] || 'slate'">
                                    {{ targetLabel(ticker.target_type) }}
                                </Badge>
                            </td>
                            <td class="py-2.5 px-3">
                                <Toggle
                                    :model-value="!!ticker.is_active"
                                    @update:model-value="toggleActive(ticker)"
                                />
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <Btn size="sm" variant="ghost" @click="openEdit(ticker)">{{ $t('common.edit') }}</Btn>
                                <Btn size="sm" variant="danger" @click="remove(ticker)">🗑 {{ $t('common.delete') }}</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal
            v-model="showModal"
            :title="editingId ? $t('tickers.modalEditTitle') : $t('tickers.modalCreateTitle')"
            size="lg"
        >
            <Wizard
                v-model="step"
                :steps="wizardSteps"
                :validate="validateStep"
                :loading="saving"
                :finish-text="editingId ? $t('common.save') : $t('common.create')"
                @finish="save"
                @cancel="showModal = false"
            >
                <template #default="{ step: s }">
                    <!-- Live preview (always visible) -->
                    <div
                        class="mb-5 rounded-lg overflow-hidden whitespace-nowrap px-4 py-2 text-center"
                        :style="{ background: form.background_color, color: form.text_color, opacity: form.opacity }"
                    >
                        <span :style="{ fontSize: Math.min(Number(form.font_size) || 28, 22) + 'px' }">
                            {{ form.text || $t('tickers.previewPlaceholder') }}
                        </span>
                    </div>

                    <!-- Step 1: Текст -->
                    <div v-if="s === 0" class="space-y-4">
                        <FormField :label="$t('tickers.fieldText')" required :error="errors.text">
                            <TextInput v-model="form.text" :placeholder="$t('tickers.placeholderText')" />
                        </FormField>
                        <FormField :label="$t('tickers.fieldTitle')" :error="errors.title" :hint="$t('tickers.hintTitle')">
                            <TextInput v-model="form.title" :placeholder="$t('tickers.placeholderTitle')" />
                        </FormField>
                    </div>

                    <!-- Step 2: Оформление -->
                    <div v-else-if="s === 1" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <FormField :label="$t('tickers.fieldPosition')" :error="errors.position">
                                <SelectInput v-model="form.position" :options="positions" />
                            </FormField>
                            <FormField :label="$t('tickers.fieldSpeed')" :error="errors.speed">
                                <TextInput v-model="form.speed" type="number" />
                            </FormField>
                        </div>
                        <FormField :label="$t('tickers.fieldFontSize')" :error="errors.font_size">
                            <TextInput v-model="form.font_size" type="number" />
                        </FormField>
                        <div class="grid grid-cols-2 gap-4">
                            <FormField :label="$t('tickers.fieldTextColor')" :error="errors.text_color">
                                <div class="flex items-center gap-2">
                                    <input v-model="form.text_color" type="color" class="h-9 w-12 rounded-lg border border-slate-300 cursor-pointer" />
                                    <span class="text-xs text-slate-500">{{ form.text_color }}</span>
                                </div>
                            </FormField>
                            <FormField :label="$t('tickers.fieldBgColor')" :error="errors.background_color">
                                <div class="flex items-center gap-2">
                                    <input v-model="form.background_color" type="color" class="h-9 w-12 rounded-lg border border-slate-300 cursor-pointer" />
                                    <span class="text-xs text-slate-500">{{ form.background_color }}</span>
                                </div>
                            </FormField>
                        </div>
                        <FormField :label="$t('tickers.fieldOpacity')" :error="errors.opacity" :hint="$t('tickers.hintOpacity', { value: Number(form.opacity).toFixed(2) })">
                            <TextInput v-model="form.opacity" type="number" />
                        </FormField>
                    </div>

                    <!-- Step 3: Расписание -->
                    <div v-else-if="s === 2" class="space-y-4">
                        <p class="text-sm text-slate-500">{{ $t('tickers.scheduleIntro') }}</p>
                        <div>
                            <p class="text-sm font-medium text-slate-600 mb-2">{{ $t('tickers.displayWindow') }}</p>
                            <div class="grid grid-cols-2 gap-4">
                                <FormField :label="$t('tickers.fieldStartDate')" :error="errors.start_date"><TextInput v-model="form.start_date" type="date" /></FormField>
                                <FormField :label="$t('tickers.fieldEndDate')" :error="errors.end_date"><TextInput v-model="form.end_date" type="date" /></FormField>
                                <FormField :label="$t('tickers.fieldStartTime')" :error="errors.start_time"><TextInput v-model="form.start_time" type="time" /></FormField>
                                <FormField :label="$t('tickers.fieldEndTime')" :error="errors.end_time"><TextInput v-model="form.end_time" type="time" /></FormField>
                            </div>
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <p class="text-sm font-medium text-slate-600 mb-2">{{ $t('tickers.recurringDisplay') }}</p>
                            <div class="grid grid-cols-2 gap-4">
                                <FormField :label="$t('tickers.fieldInterval')" :hint="$t('tickers.hintInterval')" :error="errors.interval_minutes">
                                    <TextInput v-model="form.interval_minutes" type="number" :placeholder="$t('tickers.placeholderInterval')" />
                                </FormField>
                                <FormField :label="$t('tickers.fieldDuration')" :hint="$t('tickers.hintDuration')" :error="errors.duration_minutes">
                                    <TextInput v-model="form.duration_minutes" type="number" :placeholder="$t('tickers.placeholderDuration')" />
                                </FormField>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ $t('tickers.recurringExplanation') }}
                            </p>
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <FormField :label="$t('tickers.fieldRepeatCount')" :hint="$t('tickers.hintRepeatCount')" :error="errors.repeat_count">
                                <TextInput v-model="form.repeat_count" type="number" placeholder="∞" />
                            </FormField>
                        </div>
                    </div>

                    <!-- Step 4: Назначение -->
                    <div v-else-if="s === 3" class="space-y-4">
                        <FormField :label="$t('tickers.fieldTarget')" :error="errors.target_type" :hint="$t('tickers.hintTarget')">
                            <SelectInput v-model="form.target_type" :options="targetTypes" />
                        </FormField>
                        <FormField :label="$t('tickers.fieldActive')">
                            <div class="flex items-center gap-2">
                                <Toggle v-model="form.is_active" />
                                <span class="text-sm text-slate-600">{{ form.is_active ? $t('tickers.toggleEnabled') : $t('tickers.toggleDisabled') }}</span>
                            </div>
                        </FormField>
                    </div>
                </template>
            </Wizard>
        </Modal>
    </div>
</template>
