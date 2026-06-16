<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Modal from '@/components/ui/Modal.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

const toast = useToast();
const { confirm } = useConfirm();

const items = ref([]);
const loading = ref(true);

// filters
const typeFilter = ref('');
const search = ref('');

const typeOptions = [
    { value: '', label: 'Все типы' },
    { value: 'video', label: 'Видео' },
    { value: 'image', label: 'Изображение' },
];

// upload state
const uploadFile = ref(null);
const uploadTitle = ref('');
const uploadCategory = ref('');
const uploadInput = ref(null);
const uploading = ref(false);
const uploadProgress = ref(0);

// preview modal
const previewOpen = ref(false);
const previewItem = ref(null);

// rename modal
const renameOpen = ref(false);
const renameItem = ref(null);
const renameTitle = ref('');
const renameSaving = ref(false);

// replace state
const replaceInput = ref(null);
const replaceItem = ref(null);
const replaceSaving = ref(false);

const typeLabels = {
    video: 'Видео',
    image: 'Изображение',
    audio: 'Аудио',
    html: 'HTML',
    text: 'Текст',
};

const typeBadgeColor = {
    video: 'indigo',
    image: 'green',
    audio: 'amber',
    html: 'blue',
    text: 'slate',
};

const typeIcon = {
    video: '🎬',
    image: '🖼️',
    audio: '🎵',
    html: '📄',
    text: '📝',
};

function formatBytes(bytes) {
    if (bytes === null || bytes === undefined || Number.isNaN(Number(bytes))) return '—';
    const b = Number(bytes);
    if (b === 0) return '0 Б';
    const units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    const i = Math.floor(Math.log(b) / Math.log(1024));
    const value = b / Math.pow(1024, i);
    return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined || Number.isNaN(Number(seconds))) return '—';
    const s = Math.round(Number(seconds));
    const m = Math.floor(s / 60);
    const rest = s % 60;
    return `${m}:${String(rest).padStart(2, '0')}`;
}

async function load() {
    loading.value = true;
    try {
        const params = {};
        if (typeFilter.value) params.type = typeFilter.value;
        if (search.value) params.q = search.value;
        const { data } = await api.get('/media', { params });
        items.value = data.data ?? data ?? [];
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось загрузить медиатеку.');
    } finally {
        loading.value = false;
    }
}

function onFileChange(e) {
    const file = e.target.files?.[0] ?? null;
    uploadFile.value = file;
    if (file && !uploadTitle.value) {
        uploadTitle.value = file.name.replace(/\.[^.]+$/, '');
    }
}

async function submitUpload() {
    if (!uploadFile.value) {
        toast.error('Выберите файл.');
        return;
    }
    uploading.value = true;
    uploadProgress.value = 0;
    try {
        const form = new FormData();
        form.append('file', uploadFile.value);
        form.append('title', uploadTitle.value || uploadFile.value.name);
        if (uploadCategory.value) form.append('category', uploadCategory.value);
        await api.post('/media', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (evt) => {
                if (evt.total) {
                    uploadProgress.value = Math.round((evt.loaded / evt.total) * 100);
                }
            },
        });
        uploadFile.value = null;
        uploadTitle.value = '';
        uploadCategory.value = '';
        if (uploadInput.value) uploadInput.value.value = '';
        toast.success('Файл загружен');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось загрузить файл.');
    } finally {
        uploading.value = false;
        uploadProgress.value = 0;
    }
}

function openPreview(item) {
    previewItem.value = item;
    previewOpen.value = true;
}

function openRename(item) {
    renameItem.value = item;
    renameTitle.value = item.title ?? '';
    renameOpen.value = true;
}

async function submitRename() {
    if (!renameItem.value || !renameTitle.value) return;
    renameSaving.value = true;
    try {
        await api.put(`/media/${renameItem.value.id}`, { title: renameTitle.value });
        renameOpen.value = false;
        toast.success('Название обновлено');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось переименовать.');
    } finally {
        renameSaving.value = false;
    }
}

function triggerReplace(item) {
    replaceItem.value = item;
    if (replaceInput.value) {
        replaceInput.value.value = '';
        replaceInput.value.click();
    }
}

async function onReplaceChange(e) {
    const file = e.target.files?.[0];
    if (!file || !replaceItem.value) return;
    replaceSaving.value = true;
    try {
        const form = new FormData();
        form.append('file', file);
        await api.post(`/media/${replaceItem.value.id}/replace`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('Файл заменён');
        await load();
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Не удалось заменить файл.');
    } finally {
        replaceSaving.value = false;
        replaceItem.value = null;
        if (replaceInput.value) replaceInput.value.value = '';
    }
}

async function remove(item) {
    if (!(await confirm({ title: 'Удалить файл?', message: `Удалить «${item.title}»?`, confirmText: 'Удалить' }))) return;
    try {
        await api.delete(`/media/${item.id}`);
        toast.success('Файл удалён');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось удалить.');
    }
}

const hasItems = computed(() => items.value.length > 0);

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Медиатека" subtitle="Загрузка и управление медиафайлами" />

        <!-- Upload -->
        <Card title="Загрузить файл" class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <div class="md:col-span-2">
                    <FormField label="Файл">
                        <input
                            ref="uploadInput"
                            type="file"
                            accept="video/*,image/*"
                            :disabled="uploading"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-200 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-800 hover:file:bg-slate-300"
                            @change="onFileChange"
                        />
                    </FormField>
                </div>
                <FormField label="Название">
                    <TextInput v-model="uploadTitle" placeholder="Название" :disabled="uploading" />
                </FormField>
                <FormField label="Категория">
                    <TextInput v-model="uploadCategory" placeholder="Категория" :disabled="uploading" />
                </FormField>
            </div>

            <div v-if="uploading" class="mt-3">
                <div class="h-2 w-full rounded-full bg-slate-200 overflow-hidden">
                    <div
                        class="h-full bg-indigo-600 transition-all"
                        :style="{ width: uploadProgress + '%' }"
                    />
                </div>
                <p class="text-xs text-slate-500 mt-1">Загрузка… {{ uploadProgress }}%</p>
            </div>

            <div class="mt-4 flex justify-end">
                <Btn :loading="uploading" :disabled="!uploadFile" @click="submitUpload">
                    {{ uploading ? 'Загрузка…' : '⬆ Загрузить' }}
                </Btn>
            </div>
        </Card>

        <!-- Filters -->
        <Card class="mb-4">
            <div class="flex flex-wrap items-center gap-3">
                <SelectInput
                    v-model="typeFilter"
                    :options="typeOptions"
                    class="w-44"
                    @update:modelValue="load"
                />
                <TextInput
                    v-model="search"
                    placeholder="Поиск…"
                    class="flex-1 min-w-[200px]"
                    @keyup.enter="load"
                />
                <Btn variant="secondary" @click="load">Найти</Btn>
            </div>
        </Card>

        <!-- Grid -->
        <Card v-if="loading || !hasItems">
            <Spinner v-if="loading" label="Загрузка…" />
            <EmptyState
                v-else
                icon="🎞"
                title="Пока нет медиафайлов"
                hint="Загрузите видео или изображение с помощью формы выше."
            />
        </Card>

        <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <Card v-for="item in items" :key="item.id" class="overflow-hidden">
                <div
                    class="aspect-video -m-5 mb-3 flex items-center justify-center bg-slate-100 cursor-pointer"
                    @click="openPreview(item)"
                >
                    <img
                        v-if="item.thumbnail_url"
                        :src="item.thumbnail_url"
                        :alt="item.title"
                        class="h-full w-full object-cover"
                    />
                    <img
                        v-else-if="item.type === 'image' && item.file_url"
                        :src="item.file_url"
                        :alt="item.title"
                        class="h-full w-full object-cover"
                    />
                    <span v-else class="text-4xl">{{ typeIcon[item.type] || '📁' }}</span>
                </div>

                <div class="flex items-start justify-between gap-2">
                    <p class="font-medium text-slate-800 text-sm truncate" :title="item.title">
                        {{ item.title }}
                    </p>
                    <Badge :color="typeBadgeColor[item.type] || 'slate'" class="shrink-0">
                        {{ typeLabels[item.type] || item.type }}
                    </Badge>
                </div>

                <div class="mt-1 flex items-center gap-3 text-xs text-slate-500">
                    <span v-if="item.type === 'video'">⏱ {{ formatDuration(item.duration) }}</span>
                    <span>{{ formatBytes(item.size) }}</span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Btn size="sm" variant="ghost" @click="openRename(item)">Переименовать</Btn>
                    <Btn size="sm" variant="ghost" :loading="replaceSaving && replaceItem?.id === item.id" @click="triggerReplace(item)">Заменить</Btn>
                    <Btn size="sm" variant="danger" @click="remove(item)">🗑 Удалить</Btn>
                </div>
            </Card>
        </div>

        <!-- Hidden input for replace -->
        <input
            ref="replaceInput"
            type="file"
            accept="video/*,image/*"
            class="hidden"
            @change="onReplaceChange"
        />

        <!-- Preview modal -->
        <Modal v-model="previewOpen" :title="previewItem?.title || 'Просмотр'" size="lg">
            <div v-if="previewItem" class="flex items-center justify-center bg-slate-900 rounded-lg overflow-hidden">
                <video
                    v-if="previewItem.type === 'video' && previewItem.file_url"
                    :src="previewItem.file_url"
                    controls
                    autoplay
                    class="max-h-[60vh] w-full"
                />
                <img
                    v-else-if="previewItem.type === 'image' && previewItem.file_url"
                    :src="previewItem.file_url"
                    :alt="previewItem.title"
                    class="max-h-[60vh] w-full object-contain"
                />
                <div v-else class="py-16 text-center text-slate-300">
                    <div class="text-5xl mb-2">{{ typeIcon[previewItem.type] || '📁' }}</div>
                    <p class="text-sm">Предпросмотр недоступен</p>
                </div>
            </div>
            <div v-if="previewItem" class="mt-4 grid grid-cols-2 gap-y-1 text-sm">
                <span class="text-slate-500">Тип</span>
                <span class="text-slate-800">{{ typeLabels[previewItem.type] || previewItem.type }}</span>
                <template v-if="previewItem.type === 'video'">
                    <span class="text-slate-500">Длительность</span>
                    <span class="text-slate-800">{{ formatDuration(previewItem.duration) }}</span>
                </template>
                <span class="text-slate-500">Размер</span>
                <span class="text-slate-800">{{ formatBytes(previewItem.size) }}</span>
                <template v-if="previewItem.width && previewItem.height">
                    <span class="text-slate-500">Разрешение</span>
                    <span class="text-slate-800">{{ previewItem.width }}×{{ previewItem.height }}</span>
                </template>
                <template v-if="previewItem.category">
                    <span class="text-slate-500">Категория</span>
                    <span class="text-slate-800">{{ previewItem.category }}</span>
                </template>
            </div>
            <template #footer>
                <Btn variant="secondary" @click="previewOpen = false">Закрыть</Btn>
            </template>
        </Modal>

        <!-- Rename modal -->
        <Modal v-model="renameOpen" title="Переименовать">
            <form @submit.prevent="submitRename" class="space-y-4">
                <FormField label="Название" required>
                    <TextInput v-model="renameTitle" placeholder="Название" @keyup.enter="submitRename" />
                </FormField>
            </form>
            <template #footer>
                <Btn variant="secondary" @click="renameOpen = false">Отмена</Btn>
                <Btn :loading="renameSaving" :disabled="!renameTitle" @click="submitRename">Сохранить</Btn>
            </template>
        </Modal>
    </div>
</template>
