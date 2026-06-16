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
import Toggle from '@/components/ui/Toggle.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import Wizard from '@/components/ui/Wizard.vue';

const toast = useToast();
const { confirm } = useConfirm();

const tickers = ref([]);
const loading = ref(true);

const showModal = ref(false);
const saving = ref(false);
const editingId = ref(null);
const step = ref(0);
const errors = reactive({});

const wizardSteps = [
    { label: 'Текст' },
    { label: 'Оформление' },
    { label: 'Расписание' },
    { label: 'Назначение' },
];

function validateStep(i) {
    if (i === 0 && !form.text.trim()) return 'Введите текст бегущей строки';
    return true;
}

const positions = [
    { value: 'top', label: 'Сверху' },
    { value: 'bottom', label: 'Снизу' },
];

const targetTypes = [
    { value: 'all', label: 'Все экраны' },
    { value: 'branch', label: 'Филиал' },
    { value: 'zone', label: 'Зона' },
    { value: 'device', label: 'Устройство' },
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
    return targetTypes.find((t) => t.value === type)?.label ?? type ?? '—';
}

function positionLabel(pos) {
    return positions.find((p) => p.value === pos)?.label ?? pos ?? '—';
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
        toast.error(e?.response?.data?.message || 'Не удалось загрузить бегущие строки.');
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
        toast.success(editingId.value ? 'Бегущая строка обновлена' : 'Бегущая строка создана');
        await load();
    } catch (e) {
        applyValidationErrors(e);
        toast.error(e?.response?.data?.message || 'Не удалось сохранить бегущую строку.');
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
        toast.success(next ? 'Строка включена' : 'Строка выключена');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось изменить статус.');
    }
}

async function remove(ticker) {
    const name = ticker.title || ticker.text || 'бегущую строку';
    if (
        !(await confirm({
            title: 'Удалить бегущую строку?',
            message: `Удалить «${name}»?`,
            confirmText: 'Удалить',
        }))
    )
        return;
    try {
        await api.delete(`/tickers/${ticker.id}`);
        toast.success('Бегущая строка удалена');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось удалить бегущую строку.');
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Бегущие строки" subtitle="Текстовые сообщения на экранах">
            <template #actions>
                <Btn variant="secondary" @click="load">Обновить</Btn>
                <Btn variant="primary" @click="openCreate">+ Добавить строку</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" label="Загрузка…" />

            <EmptyState
                v-else-if="!tickers.length"
                icon="↔"
                title="Пока нет бегущих строк"
                hint="Создайте первую строку, чтобы показывать текстовые сообщения на экранах."
            >
                <template #action>
                    <Btn variant="primary" @click="openCreate">+ Добавить строку</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">Текст</th>
                            <th class="py-2.5 px-3 font-medium">Позиция</th>
                            <th class="py-2.5 px-3 font-medium">Скорость</th>
                            <th class="py-2.5 px-3 font-medium">Цвета</th>
                            <th class="py-2.5 px-3 font-medium">Назначение</th>
                            <th class="py-2.5 px-3 font-medium">Активна</th>
                            <th class="py-2.5 px-3 font-medium text-right">Действия</th>
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
                            <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">{{ ticker.speed }} px/с</td>
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
                                <Btn size="sm" variant="ghost" @click="openEdit(ticker)">Изменить</Btn>
                                <Btn size="sm" variant="danger" @click="remove(ticker)">🗑 Удалить</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal
            v-model="showModal"
            :title="editingId ? 'Изменить строку' : 'Новая бегущая строка'"
            size="lg"
        >
            <Wizard
                v-model="step"
                :steps="wizardSteps"
                :validate="validateStep"
                :loading="saving"
                :finish-text="editingId ? 'Сохранить' : 'Создать'"
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
                            {{ form.text || 'Предпросмотр бегущей строки' }}
                        </span>
                    </div>

                    <!-- Step 1: Текст -->
                    <div v-if="s === 0" class="space-y-4">
                        <FormField label="Текст" required :error="errors.text">
                            <TextInput v-model="form.text" placeholder="Текст бегущей строки" />
                        </FormField>
                        <FormField label="Заголовок" :error="errors.title" hint="Необязательно, для админки">
                            <TextInput v-model="form.title" placeholder="Например: Объявление" />
                        </FormField>
                    </div>

                    <!-- Step 2: Оформление -->
                    <div v-else-if="s === 1" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <FormField label="Позиция" :error="errors.position">
                                <SelectInput v-model="form.position" :options="positions" />
                            </FormField>
                            <FormField label="Скорость (px/с)" :error="errors.speed">
                                <TextInput v-model="form.speed" type="number" />
                            </FormField>
                        </div>
                        <FormField label="Размер шрифта" :error="errors.font_size">
                            <TextInput v-model="form.font_size" type="number" />
                        </FormField>
                        <div class="grid grid-cols-2 gap-4">
                            <FormField label="Цвет текста" :error="errors.text_color">
                                <div class="flex items-center gap-2">
                                    <input v-model="form.text_color" type="color" class="h-9 w-12 rounded-lg border border-slate-300 cursor-pointer" />
                                    <span class="text-xs text-slate-500">{{ form.text_color }}</span>
                                </div>
                            </FormField>
                            <FormField label="Цвет фона" :error="errors.background_color">
                                <div class="flex items-center gap-2">
                                    <input v-model="form.background_color" type="color" class="h-9 w-12 rounded-lg border border-slate-300 cursor-pointer" />
                                    <span class="text-xs text-slate-500">{{ form.background_color }}</span>
                                </div>
                            </FormField>
                        </div>
                        <FormField label="Прозрачность" :error="errors.opacity" :hint="`0 — прозрачно, 1 — непрозрачно (${Number(form.opacity).toFixed(2)})`">
                            <TextInput v-model="form.opacity" type="number" />
                        </FormField>
                    </div>

                    <!-- Step 3: Расписание -->
                    <div v-else-if="s === 2" class="space-y-4">
                        <p class="text-sm text-slate-500">Все поля необязательны. Оставьте пустыми для постоянного показа.</p>
                        <div>
                            <p class="text-sm font-medium text-slate-600 mb-2">Окно показа</p>
                            <div class="grid grid-cols-2 gap-4">
                                <FormField label="С даты" :error="errors.start_date"><TextInput v-model="form.start_date" type="date" /></FormField>
                                <FormField label="По дату" :error="errors.end_date"><TextInput v-model="form.end_date" type="date" /></FormField>
                                <FormField label="С времени" :error="errors.start_time"><TextInput v-model="form.start_time" type="time" /></FormField>
                                <FormField label="По время" :error="errors.end_time"><TextInput v-model="form.end_time" type="time" /></FormField>
                            </div>
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <p class="text-sm font-medium text-slate-600 mb-2">Периодический показ</p>
                            <div class="grid grid-cols-2 gap-4">
                                <FormField label="Показывать каждые (мин)" hint="Пусто = постоянно" :error="errors.interval_minutes">
                                    <TextInput v-model="form.interval_minutes" type="number" placeholder="напр. 30" />
                                </FormField>
                                <FormField label="Длительность показа (мин)" hint="Сколько минут видна за цикл" :error="errors.duration_minutes">
                                    <TextInput v-model="form.duration_minutes" type="number" placeholder="напр. 5" />
                                </FormField>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">
                                Напр. «каждые 30 / длительность 5» — строка показывается 5 минут, затем
                                скрывается на 25, и так по кругу. Без «каждые …» — длительность считается один раз от включения.
                            </p>
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <FormField label="Повторов прокрутки" hint="Пусто = бесконечно (за время показа)" :error="errors.repeat_count">
                                <TextInput v-model="form.repeat_count" type="number" placeholder="∞" />
                            </FormField>
                        </div>
                    </div>

                    <!-- Step 4: Назначение -->
                    <div v-else-if="s === 3" class="space-y-4">
                        <FormField label="Назначение" :error="errors.target_type" hint="На какие экраны показывать">
                            <SelectInput v-model="form.target_type" :options="targetTypes" />
                        </FormField>
                        <FormField label="Активна">
                            <div class="flex items-center gap-2">
                                <Toggle v-model="form.is_active" />
                                <span class="text-sm text-slate-600">{{ form.is_active ? 'Включена' : 'Выключена' }}</span>
                            </div>
                        </FormField>
                    </div>
                </template>
            </Wizard>
        </Modal>
    </div>
</template>
