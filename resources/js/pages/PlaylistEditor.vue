<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Modal from '@/components/ui/Modal.vue';
import StatusDot from '@/components/ui/StatusDot.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

const toast = useToast();
const { confirm } = useConfirm();

const route = useRoute();
const playlistId = route.params.id;

const playlist = ref(null);
const items = ref([]);
const loading = ref(true);

const TRANSITIONS = [
    { value: 'none', label: 'Без эффекта' },
    { value: 'fade', label: 'Затухание' },
    { value: 'slide_left', label: 'Сдвиг влево' },
    { value: 'slide_right', label: 'Сдвиг вправо' },
    { value: 'zoom', label: 'Масштаб' },
    { value: 'crossfade', label: 'Перекрёстное затухание' },
];

const MEDIA_TYPE_LABELS = {
    video: 'Видео',
    image: 'Изображение',
    audio: 'Аудио',
    html: 'HTML',
    text: 'Текст',
};

const MEDIA_TYPE_COLORS = {
    video: 'indigo',
    image: 'blue',
    audio: 'violet',
    html: 'amber',
    text: 'slate',
};

function transitionLabel(value) {
    return TRANSITIONS.find((t) => t.value === value)?.label ?? (value || 'Без эффекта');
}

function mediaTypeLabel(type) {
    return MEDIA_TYPE_LABELS[type] ?? type ?? '—';
}

function mediaTypeColor(type) {
    return MEDIA_TYPE_COLORS[type] ?? 'slate';
}

function formatDuration(item) {
    const seconds = item.duration_seconds ?? item.media?.duration ?? null;
    return seconds == null ? '—' : `${seconds} сек`;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get(`/playlists/${playlistId}`);
        const payload = data.data ?? data;
        playlist.value = payload;
        items.value = (payload.items ?? []).slice();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось загрузить плейлист.');
    } finally {
        loading.value = false;
    }
}

/* ---------- Reorder ---------- */
async function persistOrder() {
    try {
        await api.post(`/playlists/${playlistId}/reorder`, {
            order: items.value.map((i) => i.id),
        });
        toast.success('Порядок сохранён.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось сохранить порядок.');
        await load();
    }
}

function moveUp(index) {
    if (index <= 0) return;
    const arr = items.value;
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    items.value = arr.slice();
    persistOrder();
}

function moveDown(index) {
    if (index >= items.value.length - 1) return;
    const arr = items.value;
    [arr[index + 1], arr[index]] = [arr[index], arr[index + 1]];
    items.value = arr.slice();
    persistOrder();
}

/* ---------- Remove item ---------- */
async function removeItem(item) {
    const title = item.media?.title ?? 'элемент';
    if (!(await confirm({
        title: 'Удалить элемент?',
        message: `Удалить «${title}» из плейлиста?`,
        confirmText: 'Удалить',
    }))) return;
    try {
        await api.delete(`/playlists/${playlistId}/items/${item.id}`);
        await load();
        toast.success('Элемент удалён.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось удалить элемент.');
    }
}

/* ---------- Edit item ---------- */
const showEdit = ref(false);
const editSaving = ref(false);
const editError = ref('');
const editItem = ref(null);
const editForm = ref({ duration_seconds: '', transition_effect: 'none' });

function openEdit(item) {
    editItem.value = item;
    editForm.value = {
        duration_seconds: item.duration_seconds ?? '',
        transition_effect: item.transition_effect ?? 'none',
    };
    editError.value = '';
    showEdit.value = true;
}

async function saveEdit() {
    editSaving.value = true;
    editError.value = '';
    try {
        await api.put(`/playlists/${playlistId}/items/${editItem.value.id}`, {
            duration_seconds: editForm.value.duration_seconds === '' ? null : Number(editForm.value.duration_seconds),
            transition_effect: editForm.value.transition_effect,
        });
        showEdit.value = false;
        await load();
        toast.success('Изменения сохранены.');
    } catch (e) {
        editError.value = e?.response?.data?.message || 'Не удалось сохранить изменения.';
        toast.error(e?.response?.data?.message || 'Не удалось сохранить изменения.');
    } finally {
        editSaving.value = false;
    }
}

/* ---------- Add media ---------- */
const showAdd = ref(false);
const addSaving = ref(false);
const addError = ref('');
const mediaList = ref([]);
const mediaLoading = ref(false);
const addForm = ref({ media_id: '', duration_seconds: 10, transition_effect: 'none' });

const mediaOptions = computed(() =>
    mediaList.value.map((m) => ({
        value: m.id,
        label: `${m.title} (${mediaTypeLabel(m.type)})`,
    })),
);

const selectedAddMedia = computed(() =>
    mediaList.value.find((m) => String(m.id) === String(addForm.value.media_id)) || null,
);
const addIsVideo = computed(() => selectedAddMedia.value?.type === 'video');

function fmtDuration(sec) {
    if (!sec && sec !== 0) return '—';
    const m = Math.floor(sec / 60);
    const s = Math.round(sec % 60);
    return m ? `${m} мин ${s} сек` : `${s} сек`;
}

// When picking a video, default its slot duration to the video's full length
// (so a 5-minute clip plays in full, not cut to 10s). Images keep a manual 10s.
watch(
    () => addForm.value.media_id,
    () => {
        const m = selectedAddMedia.value;
        if (!m) return;
        if (m.type === 'video') {
            addForm.value.duration_seconds = m.duration ?? '';
        } else {
            addForm.value.duration_seconds = 10;
        }
    },
);

async function loadMedia() {
    mediaLoading.value = true;
    try {
        const { data } = await api.get('/media');
        mediaList.value = data.data ?? data;
    } catch (e) {
        mediaList.value = [];
        toast.error(e?.response?.data?.message || 'Не удалось загрузить медиа.');
    } finally {
        mediaLoading.value = false;
    }
}

function openAdd() {
    addForm.value = { media_id: '', duration_seconds: 10, transition_effect: 'none' };
    addError.value = '';
    showAdd.value = true;
    if (mediaList.value.length === 0) loadMedia();
}

async function addMedia() {
    if (!addForm.value.media_id) {
        addError.value = 'Выберите медиа.';
        return;
    }
    addSaving.value = true;
    addError.value = '';
    try {
        await api.post(`/playlists/${playlistId}/items`, {
            media_id: addForm.value.media_id,
            duration_seconds: addForm.value.duration_seconds === '' ? null : Number(addForm.value.duration_seconds),
            transition_effect: addForm.value.transition_effect,
        });
        showAdd.value = false;
        await load();
        toast.success('Медиа добавлено.');
    } catch (e) {
        addError.value = e?.response?.data?.message || 'Не удалось добавить медиа.';
        toast.error(e?.response?.data?.message || 'Не удалось добавить медиа.');
    } finally {
        addSaving.value = false;
    }
}

/* ---------- Assign ---------- */
const assignSaving = ref(false);
const assignError = ref('');
const assignForm = ref({ target_type: 'all', target_id: '', priority: 0 });
const targetOptions = ref([]);
const targetsLoading = ref(false);

const TARGET_TYPE_OPTIONS = [
    { value: 'device', label: 'Устройство' },
    { value: 'zone', label: 'Зона' },
    { value: 'branch', label: 'Филиал' },
    { value: 'all', label: 'Все экраны' },
];

const needsTarget = computed(() => assignForm.value.target_type !== 'all');

const targetSelectOptions = computed(() =>
    targetOptions.value.map((t) => ({ value: t.id, label: t.name })),
);

async function loadTargets() {
    assignForm.value.target_id = '';
    targetOptions.value = [];
    const type = assignForm.value.target_type;
    if (type === 'all') return;
    const endpoint = { device: '/devices', zone: '/zones', branch: '/branches' }[type];
    if (!endpoint) return;
    targetsLoading.value = true;
    try {
        const { data } = await api.get(endpoint);
        targetOptions.value = data.data ?? data;
    } catch (e) {
        targetOptions.value = [];
        toast.error(e?.response?.data?.message || 'Не удалось загрузить цели назначения.');
    } finally {
        targetsLoading.value = false;
    }
}

async function assign() {
    if (needsTarget.value && !assignForm.value.target_id) {
        assignError.value = 'Выберите цель назначения.';
        return;
    }
    assignSaving.value = true;
    assignError.value = '';
    try {
        await api.post(`/playlists/${playlistId}/assign`, {
            target_type: assignForm.value.target_type,
            target_id: needsTarget.value ? assignForm.value.target_id : null,
            priority: Number(assignForm.value.priority) || 0,
        });
        toast.success('Плейлист назначен.');
    } catch (e) {
        assignError.value = e?.response?.data?.message || 'Не удалось назначить плейлист.';
        toast.error(e?.response?.data?.message || 'Не удалось назначить плейлист.');
    } finally {
        assignSaving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader
            :title="playlist?.name ?? 'Плейлист'"
            :subtitle="playlist?.description ?? 'Редактор плейлиста'"
        >
            <template #actions>
                <RouterLink to="/playlists">
                    <Btn variant="secondary">Назад</Btn>
                </RouterLink>
                <Btn variant="secondary" @click="load">Обновить</Btn>
            </template>
        </PageHeader>

        <Card v-if="loading">
            <Spinner label="Загрузка…" />
        </Card>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: items list -->
            <div class="lg:col-span-2">
                <Card title="Элементы плейлиста">
                    <template #actions>
                        <Btn variant="primary" @click="openAdd">+ Добавить медиа</Btn>
                    </template>

                    <EmptyState
                        v-if="items.length === 0"
                        icon="🎬"
                        title="Пока нет элементов"
                        hint="Добавьте медиафайлы, чтобы они отображались на экранах."
                    >
                        <template #action>
                            <Btn variant="primary" @click="openAdd">+ Добавить медиа</Btn>
                        </template>
                    </EmptyState>

                    <ul v-else class="divide-y divide-slate-50">
                        <li
                            v-for="(item, index) in items"
                            :key="item.id"
                            class="flex items-center gap-3 py-3 hover:bg-slate-50 transition rounded-lg px-2 -mx-2"
                        >
                            <span class="w-6 text-center text-xs text-slate-400">{{ index + 1 }}</span>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="font-medium text-slate-800 truncate max-w-[28rem]"
                                    :title="item.media?.title ?? 'Без названия'"
                                >
                                    {{ item.media?.title ?? 'Без названия' }}
                                </p>
                                <p class="text-xs text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                                    <Badge :color="mediaTypeColor(item.media?.type)">
                                        {{ mediaTypeLabel(item.media?.type) }}
                                    </Badge>
                                    <span>{{ formatDuration(item) }}</span>
                                    <span>{{ transitionLabel(item.transition_effect) }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Btn
                                    variant="ghost"
                                    size="sm"
                                    :disabled="index === 0"
                                    @click="moveUp(index)"
                                >↑</Btn>
                                <Btn
                                    variant="ghost"
                                    size="sm"
                                    :disabled="index === items.length - 1"
                                    @click="moveDown(index)"
                                >↓</Btn>
                                <Btn variant="secondary" size="sm" @click="openEdit(item)">Изменить</Btn>
                                <Btn variant="danger" size="sm" @click="removeItem(item)">🗑</Btn>
                            </div>
                        </li>
                    </ul>
                </Card>
            </div>

            <!-- Right: info + assign -->
            <div class="space-y-6">
                <Card title="Сведения">
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between items-center">
                            <dt class="text-slate-500">Статус</dt>
                            <dd><StatusDot :status="playlist?.status" /></dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-slate-500">Версия</dt>
                            <dd class="text-slate-700">v{{ playlist?.version ?? 1 }}</dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-slate-500">Элементов</dt>
                            <dd class="text-slate-700">{{ items.length }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card title="Назначить">
                    <div
                        v-if="assignError"
                        class="mb-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"
                    >
                        {{ assignError }}
                    </div>
                    <div class="space-y-4">
                        <FormField label="Тип цели">
                            <SelectInput
                                v-model="assignForm.target_type"
                                :options="TARGET_TYPE_OPTIONS"
                                @update:modelValue="loadTargets"
                            />
                        </FormField>
                        <FormField v-if="needsTarget" label="Цель">
                            <SelectInput
                                v-model="assignForm.target_id"
                                :options="targetSelectOptions"
                                :disabled="targetsLoading"
                                :placeholder="targetsLoading ? 'Загрузка…' : 'Выберите…'"
                            />
                        </FormField>
                        <FormField label="Приоритет" hint="Чем выше число, тем выше приоритет.">
                            <TextInput v-model.number="assignForm.priority" type="number" />
                        </FormField>
                        <Btn
                            variant="primary"
                            class="w-full"
                            :loading="assignSaving"
                            @click="assign"
                        >
                            Назначить
                        </Btn>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Add media modal -->
        <Modal v-model="showAdd" title="Добавить медиа">
            <div
                v-if="addError"
                class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"
            >
                {{ addError }}
            </div>
            <Spinner v-if="mediaLoading" label="Загрузка медиа…" />
            <form v-else class="space-y-4" @submit.prevent="addMedia">
                <FormField label="Медиа" required>
                    <SelectInput
                        v-model="addForm.media_id"
                        :options="mediaOptions"
                        placeholder="Выберите медиа…"
                    />
                </FormField>
                <FormField
                    label="Длительность (сек)"
                    :hint="addIsVideo
                        ? `Видео целиком — ${fmtDuration(selectedAddMedia?.duration)}. Уменьшите, чтобы показывать только начало.`
                        : 'Сколько секунд показывать изображение'"
                >
                    <div class="flex items-center gap-2">
                        <TextInput v-model="addForm.duration_seconds" type="number" />
                        <Btn
                            v-if="addIsVideo && selectedAddMedia?.duration"
                            size="sm"
                            variant="ghost"
                            @click="addForm.duration_seconds = selectedAddMedia.duration"
                        >Вся длина</Btn>
                    </div>
                </FormField>
                <FormField label="Переход">
                    <SelectInput v-model="addForm.transition_effect" :options="TRANSITIONS" />
                </FormField>
            </form>
            <template #footer>
                <Btn variant="secondary" @click="showAdd = false">Отмена</Btn>
                <Btn variant="primary" :loading="addSaving" @click="addMedia">Добавить</Btn>
            </template>
        </Modal>

        <!-- Edit item modal -->
        <Modal v-model="showEdit" title="Изменить элемент">
            <div
                v-if="editError"
                class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"
            >
                {{ editError }}
            </div>
            <p v-if="editItem" class="text-sm text-slate-600 mb-4">
                {{ editItem.media?.title ?? 'Элемент' }}
            </p>
            <form class="space-y-4" @submit.prevent="saveEdit">
                <FormField label="Длительность (сек)">
                    <TextInput
                        v-model="editForm.duration_seconds"
                        type="number"
                        placeholder="По умолчанию"
                    />
                </FormField>
                <FormField label="Переход">
                    <SelectInput v-model="editForm.transition_effect" :options="TRANSITIONS" />
                </FormField>
            </form>
            <template #footer>
                <Btn variant="secondary" @click="showEdit = false">Отмена</Btn>
                <Btn variant="primary" :loading="editSaving" @click="saveEdit">Сохранить</Btn>
            </template>
        </Modal>
    </div>
</template>
