<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Modal from '@/components/ui/Modal.vue';
import Badge from '@/components/ui/Badge.vue';
import StatusDot from '@/components/ui/StatusDot.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import Toggle from '@/components/ui/Toggle.vue';
import Wizard from '@/components/ui/Wizard.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();
const { confirm } = useConfirm();

const messages = ref([]);
const loading = ref(true);

const showModal = ref(false);
const saving = ref(false);
const errors = reactive({});
const activateAfterCreate = ref(false);
const editingId = ref(null);
const step = ref(0);

const wizardSteps = [
    { label: t('emergency.stepText') },
    { label: t('emergency.stepDesign') },
    { label: t('emergency.stepSchedule') },
];

function validateStep(i) {
    if (i === 0 && !form.text.trim()) return t('emergency.validateText');
    return true;
}

const targetTypes = [
    { value: 'all', label: t('emergency.targetAll') },
    { value: 'branch', label: t('emergency.targetBranch') },
    { value: 'zone', label: t('emergency.targetZone') },
    { value: 'device', label: t('emergency.targetDevice') },
];

const displayStyles = [
    { value: 'fullscreen', label: t('emergency.styleFullscreen') },
    { value: 'banner', label: t('emergency.styleBanner') },
];

const positions = [
    { value: 'top', label: t('emergency.positionTop') },
    { value: 'bottom', label: t('emergency.positionBottom') },
];

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
        target_type: 'all',
        duration_seconds: null,
        scheduled_start: '',
        scheduled_end: '',
        display_style: 'fullscreen',
        position: 'bottom',
        font_size: 48,
        blink: false,
        background_color: '#b00020',
        text_color: '#ffffff',
    };
}

const form = reactive(emptyForm());

function targetLabel(type) {
    return targetTypes.find((t) => t.value === type)?.label ?? type ?? '—';
}

function targetColor(type) {
    return targetColors[type] ?? 'slate';
}

function durationLabel(seconds) {
    if (seconds == null || seconds === '') return t('emergency.manual');
    return t('emergency.secondsValue', { n: seconds });
}

function clearErrors() {
    Object.keys(errors).forEach((k) => delete errors[k]);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/emergency-messages');
        messages.value = data.data ?? [];
    } catch (e) {
        toast.error(e?.response?.data?.message || t('emergency.loadError'));
    } finally {
        loading.value = false;
    }
}

function openCreate(activate = false) {
    Object.assign(form, emptyForm());
    activateAfterCreate.value = activate;
    editingId.value = null;
    clearErrors();
    step.value = 0;
    showModal.value = true;
}

function openEdit(message) {
    Object.assign(form, emptyForm(), {
        title: message.title ?? '',
        text: message.text ?? '',
        target_type: message.target_type ?? 'all',
        duration_seconds: message.duration_seconds ?? null,
        scheduled_start: message.scheduled_start ?? '',
        scheduled_end: message.scheduled_end ?? '',
        display_style: message.display_style ?? 'fullscreen',
        position: message.position ?? 'bottom',
        font_size: message.font_size ?? 48,
        blink: !!message.blink,
        background_color: message.background_color ?? '#b00020',
        text_color: message.text_color ?? '#ffffff',
    });
    activateAfterCreate.value = false;
    editingId.value = message.id;
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
        target_type: form.target_type,
        duration_seconds:
            form.duration_seconds === '' || form.duration_seconds == null
                ? null
                : Number(form.duration_seconds),
        scheduled_start: form.scheduled_start || null,
        scheduled_end: form.scheduled_end || null,
        display_style: form.display_style,
        position: form.position,
        font_size: form.font_size === '' || form.font_size == null ? 48 : Number(form.font_size),
        blink: !!form.blink,
        background_color: form.background_color,
        text_color: form.text_color,
    };
    try {
        if (editingId.value) {
            await api.put(`/emergency-messages/${editingId.value}`, payload);
            showModal.value = false;
            toast.success(t('emergency.updated'));
        } else {
            const { data } = await api.post('/emergency-messages', payload);
            const created = data.data ?? data;
            if (activateAfterCreate.value && created?.id) {
                await api.post(`/emergency-messages/${created.id}/activate`);
            }
            showModal.value = false;
            toast.success(activateAfterCreate.value ? t('emergency.shownOnScreens') : t('emergency.saved'));
        }
        await load();
    } catch (e) {
        if (e?.response?.status === 422 && e.response.data?.errors) {
            Object.entries(e.response.data.errors).forEach(([k, v]) => {
                errors[k] = Array.isArray(v) ? v[0] : v;
            });
        }
        toast.error(e?.response?.data?.message || t('emergency.saveError'));
    } finally {
        saving.value = false;
    }
}

async function activate(message) {
    try {
        await api.post(`/emergency-messages/${message.id}/activate`);
        toast.success(t('emergency.activated'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('emergency.activateError'));
    }
}

async function deactivate(message) {
    try {
        await api.post(`/emergency-messages/${message.id}/deactivate`);
        toast.success(t('emergency.stopped'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('emergency.stopError'));
    }
}

async function remove(message) {
    if (
        !(await confirm({
            title: t('emergency.deleteTitle'),
            message: t('emergency.deleteMessage'),
            confirmText: t('common.delete'),
        }))
    ) {
        return;
    }
    try {
        await api.delete(`/emergency-messages/${message.id}`);
        toast.success(t('emergency.deleted'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('emergency.deleteError'));
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader :title="$t('emergency.pageTitle')" :subtitle="$t('emergency.pageSubtitle')">
            <template #actions>
                <Btn variant="secondary" @click="load">{{ $t('emergency.refresh') }}</Btn>
                <Btn variant="secondary" @click="openCreate(false)">{{ $t('common.create') }}</Btn>
                <Btn variant="danger" @click="openCreate(true)">🚨 {{ $t('emergency.showUrgent') }}</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" :label="$t('common.loading')" />

            <EmptyState
                v-else-if="!messages.length"
                icon="🚨"
                :title="$t('emergency.emptyTitle')"
                :hint="$t('emergency.emptyHint')"
            >
                <template #action>
                    <Btn variant="danger" @click="openCreate(true)">🚨 {{ $t('emergency.showUrgent') }}</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">{{ $t('emergency.colText') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('emergency.colTarget') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.status') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('emergency.colDuration') }}</th>
                            <th class="py-2.5 px-3 font-medium text-right">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="message in messages"
                            :key="message.id"
                            class="border-b border-slate-50 transition"
                            :class="message.is_active ? 'bg-red-50 hover:bg-red-100/70' : 'hover:bg-slate-50'"
                        >
                            <td class="py-2.5 px-3 text-slate-800">
                                <div class="max-w-xs truncate" :title="message.text">{{ message.text }}</div>
                                <div
                                    v-if="message.title"
                                    class="text-xs text-slate-400 truncate max-w-xs"
                                    :title="message.title"
                                >
                                    {{ message.title }}
                                </div>
                            </td>
                            <td class="py-2.5 px-3">
                                <Badge :color="targetColor(message.target_type)">
                                    {{ targetLabel(message.target_type) }}
                                </Badge>
                            </td>
                            <td class="py-2.5 px-3">
                                <StatusDot :status="message.is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="py-2.5 px-3 text-slate-600">{{ durationLabel(message.duration_seconds) }}</td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <Btn
                                        v-if="!message.is_active"
                                        size="sm"
                                        variant="primary"
                                        @click="activate(message)"
                                    >
                                        ▶ {{ $t('emergency.activate') }}
                                    </Btn>
                                    <Btn
                                        v-else
                                        size="sm"
                                        variant="secondary"
                                        @click="deactivate(message)"
                                    >
                                        ⏹ {{ $t('emergency.stop') }}
                                    </Btn>
                                    <Btn size="sm" variant="ghost" @click="openEdit(message)">{{ $t('common.edit') }}</Btn>
                                    <Btn size="sm" variant="danger" @click="remove(message)">🗑 {{ $t('common.delete') }}</Btn>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal
            v-model="showModal"
            size="lg"
            :title="editingId ? $t('emergency.editTitle') : activateAfterCreate ? '🚨 ' + $t('emergency.showUrgent') : $t('emergency.newTitle')"
        >
            <Wizard
                v-model="step"
                :steps="wizardSteps"
                :validate="validateStep"
                :loading="saving"
                :finish-text="editingId ? $t('common.save') : activateAfterCreate ? '🚨 ' + $t('emergency.showUrgent') : $t('common.save')"
                @finish="save"
                @cancel="showModal = false"
            >
                <template #default="{ step: s }">
                    <!-- Live preview -->
                    <div
                        class="mb-5 rounded-lg px-4 py-3 font-medium text-center"
                        :style="{ backgroundColor: form.background_color, color: form.text_color, fontSize: Math.min(Number(form.font_size) || 48, 24) + 'px' }"
                        :class="{ 'animate-pulse': form.blink }"
                    >
                        {{ form.text || $t('emergency.previewPlaceholder') }}
                    </div>

                    <!-- Step 1: Текст -->
                    <div v-if="s === 0" class="space-y-4">
                        <FormField :label="$t('emergency.colText')" required :error="errors.text">
                            <textarea
                                v-model="form.text"
                                rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            />
                        </FormField>
                        <FormField :label="$t('emergency.titleLabel')" :hint="$t('emergency.titleHint')" :error="errors.title">
                            <TextInput v-model="form.title" :placeholder="$t('emergency.titlePlaceholder')" />
                        </FormField>
                    </div>

                    <!-- Step 2: Оформление -->
                    <div v-else-if="s === 1" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <FormField :label="$t('emergency.displayStyle')" :error="errors.display_style">
                                <SelectInput v-model="form.display_style" :options="displayStyles" />
                            </FormField>
                            <FormField v-if="form.display_style === 'banner'" :label="$t('emergency.bannerPosition')" :error="errors.position">
                                <SelectInput v-model="form.position" :options="positions" />
                            </FormField>
                            <FormField :label="$t('emergency.fontSize')" :error="errors.font_size">
                                <TextInput v-model="form.font_size" type="number" />
                            </FormField>
                            <FormField :label="$t('emergency.blink')">
                                <div class="flex items-center gap-2 h-9">
                                    <Toggle v-model="form.blink" />
                                    <span class="text-sm text-slate-600">{{ form.blink ? $t('emergency.on') : $t('emergency.off') }}</span>
                                </div>
                            </FormField>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <FormField :label="$t('emergency.backgroundColor')" :error="errors.background_color">
                                <div class="flex items-center gap-2">
                                    <input v-model="form.background_color" type="color" class="h-10 w-12 shrink-0 rounded-lg border border-slate-300 cursor-pointer" />
                                    <span class="text-xs text-slate-500 font-mono">{{ form.background_color }}</span>
                                </div>
                            </FormField>
                            <FormField :label="$t('emergency.textColor')" :error="errors.text_color">
                                <div class="flex items-center gap-2">
                                    <input v-model="form.text_color" type="color" class="h-10 w-12 shrink-0 rounded-lg border border-slate-300 cursor-pointer" />
                                    <span class="text-xs text-slate-500 font-mono">{{ form.text_color }}</span>
                                </div>
                            </FormField>
                        </div>
                    </div>

                    <!-- Step 3: Расписание / назначение -->
                    <div v-else-if="s === 2" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <FormField :label="$t('emergency.colTarget')" :error="errors.target_type">
                                <SelectInput v-model="form.target_type" :options="targetTypes" />
                            </FormField>
                            <FormField :label="$t('emergency.durationLabel')" :hint="$t('emergency.durationHint')" :error="errors.duration_seconds">
                                <TextInput v-model="form.duration_seconds" type="number" :placeholder="$t('emergency.manual')" />
                            </FormField>
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <p class="text-sm font-medium text-slate-600 mb-2">{{ $t('emergency.stepSchedule') }} <span class="text-slate-400 font-normal">({{ $t('common.optional') }})</span></p>
                            <div class="grid grid-cols-2 gap-4">
                                <FormField :label="$t('emergency.showFrom')" :hint="$t('emergency.showFromHint')" :error="errors.scheduled_start">
                                    <TextInput v-model="form.scheduled_start" type="datetime-local" />
                                </FormField>
                                <FormField :label="$t('emergency.hideAt')" :hint="$t('emergency.hideAtHint')" :error="errors.scheduled_end">
                                    <TextInput v-model="form.scheduled_end" type="datetime-local" />
                                </FormField>
                            </div>
                        </div>
                    </div>
                </template>
            </Wizard>
        </Modal>
    </div>
</template>
