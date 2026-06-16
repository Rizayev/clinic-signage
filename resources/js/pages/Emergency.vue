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
    { label: 'Текст' },
    { label: 'Оформление' },
    { label: 'Расписание' },
];

function validateStep(i) {
    if (i === 0 && !form.text.trim()) return 'Введите текст сообщения';
    return true;
}

const targetTypes = [
    { value: 'all', label: 'Все экраны' },
    { value: 'branch', label: 'Филиал' },
    { value: 'zone', label: 'Зона' },
    { value: 'device', label: 'Устройство' },
];

const displayStyles = [
    { value: 'fullscreen', label: 'На весь экран' },
    { value: 'banner', label: 'Плашка' },
];

const positions = [
    { value: 'top', label: 'Сверху' },
    { value: 'bottom', label: 'Снизу' },
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
    if (seconds == null || seconds === '') return 'Вручную';
    return `${seconds} сек`;
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
        toast.error(e?.response?.data?.message || 'Не удалось загрузить экстренные сообщения.');
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
            toast.success('Сообщение обновлено');
        } else {
            const { data } = await api.post('/emergency-messages', payload);
            const created = data.data ?? data;
            if (activateAfterCreate.value && created?.id) {
                await api.post(`/emergency-messages/${created.id}/activate`);
            }
            showModal.value = false;
            toast.success(activateAfterCreate.value ? 'Сообщение показано на экранах' : 'Сообщение сохранено');
        }
        await load();
    } catch (e) {
        if (e?.response?.status === 422 && e.response.data?.errors) {
            Object.entries(e.response.data.errors).forEach(([k, v]) => {
                errors[k] = Array.isArray(v) ? v[0] : v;
            });
        }
        toast.error(e?.response?.data?.message || 'Не удалось сохранить сообщение.');
    } finally {
        saving.value = false;
    }
}

async function activate(message) {
    try {
        await api.post(`/emergency-messages/${message.id}/activate`);
        toast.success('Сообщение активировано');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось активировать сообщение.');
    }
}

async function deactivate(message) {
    try {
        await api.post(`/emergency-messages/${message.id}/deactivate`);
        toast.success('Сообщение остановлено');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось остановить сообщение.');
    }
}

async function remove(message) {
    if (
        !(await confirm({
            title: 'Удалить сообщение?',
            message: 'Экстренное сообщение будет удалено без возможности восстановления.',
            confirmText: 'Удалить',
        }))
    ) {
        return;
    }
    try {
        await api.delete(`/emergency-messages/${message.id}`);
        toast.success('Сообщение удалено');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось удалить сообщение.');
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Экстренные сообщения" subtitle="Срочные оповещения на всех экранах">
            <template #actions>
                <Btn variant="secondary" @click="load">Обновить</Btn>
                <Btn variant="secondary" @click="openCreate(false)">Создать</Btn>
                <Btn variant="danger" @click="openCreate(true)">🚨 Показать срочно</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" label="Загрузка…" />

            <EmptyState
                v-else-if="!messages.length"
                icon="🚨"
                title="Экстренных сообщений пока нет"
                hint="Создайте сообщение или сразу покажите срочное оповещение на всех экранах."
            >
                <template #action>
                    <Btn variant="danger" @click="openCreate(true)">🚨 Показать срочно</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">Текст</th>
                            <th class="py-2.5 px-3 font-medium">Назначение</th>
                            <th class="py-2.5 px-3 font-medium">Статус</th>
                            <th class="py-2.5 px-3 font-medium">Длительность</th>
                            <th class="py-2.5 px-3 font-medium text-right">Действия</th>
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
                                        ▶ Активировать
                                    </Btn>
                                    <Btn
                                        v-else
                                        size="sm"
                                        variant="secondary"
                                        @click="deactivate(message)"
                                    >
                                        ⏹ Остановить
                                    </Btn>
                                    <Btn size="sm" variant="ghost" @click="openEdit(message)">Изменить</Btn>
                                    <Btn size="sm" variant="danger" @click="remove(message)">🗑 Удалить</Btn>
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
            :title="editingId ? 'Изменить сообщение' : activateAfterCreate ? '🚨 Показать срочно' : 'Новое экстренное сообщение'"
        >
            <Wizard
                v-model="step"
                :steps="wizardSteps"
                :validate="validateStep"
                :loading="saving"
                :finish-text="editingId ? 'Сохранить' : activateAfterCreate ? '🚨 Показать срочно' : 'Сохранить'"
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
                        {{ form.text || 'Предпросмотр сообщения' }}
                    </div>

                    <!-- Step 1: Текст -->
                    <div v-if="s === 0" class="space-y-4">
                        <FormField label="Текст" required :error="errors.text">
                            <textarea
                                v-model="form.text"
                                rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            />
                        </FormField>
                        <FormField label="Заголовок" hint="Необязательно, для админки" :error="errors.title">
                            <TextInput v-model="form.title" placeholder="Например: Внимание" />
                        </FormField>
                    </div>

                    <!-- Step 2: Оформление -->
                    <div v-else-if="s === 1" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <FormField label="Стиль показа" :error="errors.display_style">
                                <SelectInput v-model="form.display_style" :options="displayStyles" />
                            </FormField>
                            <FormField v-if="form.display_style === 'banner'" label="Позиция плашки" :error="errors.position">
                                <SelectInput v-model="form.position" :options="positions" />
                            </FormField>
                            <FormField label="Размер шрифта (px)" :error="errors.font_size">
                                <TextInput v-model="form.font_size" type="number" />
                            </FormField>
                            <FormField label="Мигание">
                                <div class="flex items-center gap-2 h-9">
                                    <Toggle v-model="form.blink" />
                                    <span class="text-sm text-slate-600">{{ form.blink ? 'Вкл' : 'Выкл' }}</span>
                                </div>
                            </FormField>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <FormField label="Цвет фона" :error="errors.background_color">
                                <div class="flex items-center gap-2">
                                    <input v-model="form.background_color" type="color" class="h-10 w-12 shrink-0 rounded-lg border border-slate-300 cursor-pointer" />
                                    <span class="text-xs text-slate-500 font-mono">{{ form.background_color }}</span>
                                </div>
                            </FormField>
                            <FormField label="Цвет текста" :error="errors.text_color">
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
                            <FormField label="Назначение" :error="errors.target_type">
                                <SelectInput v-model="form.target_type" :options="targetTypes" />
                            </FormField>
                            <FormField label="Длительность (сек)" hint="Пусто = вручную" :error="errors.duration_seconds">
                                <TextInput v-model="form.duration_seconds" type="number" placeholder="Вручную" />
                            </FormField>
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <p class="text-sm font-medium text-slate-600 mb-2">Расписание <span class="text-slate-400 font-normal">(необязательно)</span></p>
                            <div class="grid grid-cols-2 gap-4">
                                <FormField label="Показать с" hint="Отложенный старт" :error="errors.scheduled_start">
                                    <TextInput v-model="form.scheduled_start" type="datetime-local" />
                                </FormField>
                                <FormField label="Снять в" hint="Авто-выключение" :error="errors.scheduled_end">
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
