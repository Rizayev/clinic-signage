<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
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
const { t } = useI18n();

const route = useRoute();
const playlistId = route.params.id;

const playlist = ref(null);
const items = ref([]);
const loading = ref(true);

const TRANSITIONS = computed(() => [
    { value: 'none', label: t('playlistEditor.transitionNone') },
    { value: 'fade', label: t('playlistEditor.transitionFade') },
    { value: 'slide_left', label: t('playlistEditor.transitionSlideLeft') },
    { value: 'slide_right', label: t('playlistEditor.transitionSlideRight') },
    { value: 'zoom', label: t('playlistEditor.transitionZoom') },
    { value: 'crossfade', label: t('playlistEditor.transitionCrossfade') },
]);

const MEDIA_TYPE_LABELS = computed(() => ({
    video: t('playlistEditor.mediaTypeVideo'),
    image: t('playlistEditor.mediaTypeImage'),
    audio: t('playlistEditor.mediaTypeAudio'),
    html: 'HTML',
    text: t('playlistEditor.mediaTypeText'),
}));

const MEDIA_TYPE_COLORS = {
    video: 'indigo',
    image: 'blue',
    audio: 'violet',
    html: 'amber',
    text: 'slate',
};

function transitionLabel(value) {
    return TRANSITIONS.value.find((tr) => tr.value === value)?.label ?? (value || t('playlistEditor.transitionNone'));
}

function mediaTypeLabel(type) {
    return MEDIA_TYPE_LABELS.value[type] ?? type ?? '—';
}

function mediaTypeColor(type) {
    return MEDIA_TYPE_COLORS[type] ?? 'slate';
}

function formatDuration(item) {
    const seconds = item.duration_seconds ?? item.media?.duration ?? null;
    return seconds == null ? '—' : t('playlistEditor.secondsShort', { count: seconds });
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get(`/playlists/${playlistId}`);
        const payload = data.data ?? data;
        playlist.value = payload;
        items.value = (payload.items ?? []).slice();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('playlistEditor.loadPlaylistError'));
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
        toast.success(t('playlistEditor.orderSaved'));
    } catch (e) {
        toast.error(e?.response?.data?.message || t('playlistEditor.orderSaveError'));
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
    const title = item.media?.title ?? t('playlistEditor.itemFallback');
    if (!(await confirm({
        title: t('playlistEditor.removeItemTitle'),
        message: t('playlistEditor.removeItemMessage', { title }),
        confirmText: t('common.delete'),
    }))) return;
    try {
        await api.delete(`/playlists/${playlistId}/items/${item.id}`);
        await load();
        toast.success(t('playlistEditor.itemRemoved'));
    } catch (e) {
        toast.error(e?.response?.data?.message || t('playlistEditor.itemRemoveError'));
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
        toast.success(t('playlistEditor.changesSaved'));
    } catch (e) {
        editError.value = e?.response?.data?.message || t('playlistEditor.changesSaveError');
        toast.error(e?.response?.data?.message || t('playlistEditor.changesSaveError'));
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
    return m
        ? t('playlistEditor.durationMinSec', { min: m, sec: s })
        : t('playlistEditor.secondsShort', { count: s });
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
        toast.error(e?.response?.data?.message || t('playlistEditor.loadMediaError'));
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
        addError.value = t('playlistEditor.selectMediaError');
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
        toast.success(t('playlistEditor.mediaAdded'));
    } catch (e) {
        addError.value = e?.response?.data?.message || t('playlistEditor.mediaAddError');
        toast.error(e?.response?.data?.message || t('playlistEditor.mediaAddError'));
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

const TARGET_TYPE_OPTIONS = computed(() => [
    { value: 'device', label: t('playlistEditor.targetDevice') },
    { value: 'zone', label: t('playlistEditor.targetZone') },
    { value: 'branch', label: t('playlistEditor.targetBranch') },
    { value: 'all', label: t('playlistEditor.targetAllScreens') },
]);

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
        toast.error(e?.response?.data?.message || t('playlistEditor.loadTargetsError'));
    } finally {
        targetsLoading.value = false;
    }
}

async function assign() {
    if (needsTarget.value && !assignForm.value.target_id) {
        assignError.value = t('playlistEditor.selectTargetError');
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
        toast.success(t('playlistEditor.playlistAssigned'));
    } catch (e) {
        assignError.value = e?.response?.data?.message || t('playlistEditor.playlistAssignError');
        toast.error(e?.response?.data?.message || t('playlistEditor.playlistAssignError'));
    } finally {
        assignSaving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader
            :title="playlist?.name ?? $t('playlistEditor.playlistFallback')"
            :subtitle="playlist?.description ?? $t('playlistEditor.subtitle')"
        >
            <template #actions>
                <RouterLink to="/playlists">
                    <Btn variant="secondary">{{ $t('common.back') }}</Btn>
                </RouterLink>
                <Btn variant="secondary" @click="load">{{ $t('playlistEditor.refresh') }}</Btn>
            </template>
        </PageHeader>

        <Card v-if="loading">
            <Spinner :label="$t('common.loading')" />
        </Card>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: items list -->
            <div class="lg:col-span-2">
                <Card :title="$t('playlistEditor.itemsTitle')">
                    <template #actions>
                        <Btn variant="primary" @click="openAdd">{{ $t('playlistEditor.addMediaBtn') }}</Btn>
                    </template>

                    <EmptyState
                        v-if="items.length === 0"
                        icon="🎬"
                        :title="$t('playlistEditor.emptyTitle')"
                        :hint="$t('playlistEditor.emptyHint')"
                    >
                        <template #action>
                            <Btn variant="primary" @click="openAdd">{{ $t('playlistEditor.addMediaBtn') }}</Btn>
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
                                    :title="item.media?.title ?? $t('playlistEditor.untitled')"
                                >
                                    {{ item.media?.title ?? $t('playlistEditor.untitled') }}
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
                                <Btn variant="secondary" size="sm" @click="openEdit(item)">{{ $t('common.edit') }}</Btn>
                                <Btn variant="danger" size="sm" @click="removeItem(item)">🗑</Btn>
                            </div>
                        </li>
                    </ul>
                </Card>
            </div>

            <!-- Right: info + assign -->
            <div class="space-y-6">
                <Card :title="$t('playlistEditor.detailsTitle')">
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between items-center">
                            <dt class="text-slate-500">{{ $t('common.status') }}</dt>
                            <dd><StatusDot :status="playlist?.status" /></dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-slate-500">{{ $t('playlistEditor.version') }}</dt>
                            <dd class="text-slate-700">v{{ playlist?.version ?? 1 }}</dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-slate-500">{{ $t('playlistEditor.itemsCount') }}</dt>
                            <dd class="text-slate-700">{{ items.length }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card :title="$t('playlistEditor.assignTitle')">
                    <div
                        v-if="assignError"
                        class="mb-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"
                    >
                        {{ assignError }}
                    </div>
                    <div class="space-y-4">
                        <FormField :label="$t('playlistEditor.targetType')">
                            <SelectInput
                                v-model="assignForm.target_type"
                                :options="TARGET_TYPE_OPTIONS"
                                @update:modelValue="loadTargets"
                            />
                        </FormField>
                        <FormField v-if="needsTarget" :label="$t('playlistEditor.target')">
                            <SelectInput
                                v-model="assignForm.target_id"
                                :options="targetSelectOptions"
                                :disabled="targetsLoading"
                                :placeholder="targetsLoading ? $t('common.loading') : $t('common.select')"
                            />
                        </FormField>
                        <FormField :label="$t('playlistEditor.priority')" :hint="$t('playlistEditor.priorityHint')">
                            <TextInput v-model.number="assignForm.priority" type="number" />
                        </FormField>
                        <Btn
                            variant="primary"
                            class="w-full"
                            :loading="assignSaving"
                            @click="assign"
                        >
                            {{ $t('playlistEditor.assignBtn') }}
                        </Btn>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Add media modal -->
        <Modal v-model="showAdd" :title="$t('playlistEditor.addMediaTitle')">
            <div
                v-if="addError"
                class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"
            >
                {{ addError }}
            </div>
            <Spinner v-if="mediaLoading" :label="$t('playlistEditor.loadingMedia')" />
            <form v-else class="space-y-4" @submit.prevent="addMedia">
                <FormField :label="$t('playlistEditor.mediaLabel')" required>
                    <SelectInput
                        v-model="addForm.media_id"
                        :options="mediaOptions"
                        :placeholder="$t('playlistEditor.selectMediaPlaceholder')"
                    />
                </FormField>
                <FormField
                    :label="$t('playlistEditor.durationLabel')"
                    :hint="addIsVideo
                        ? $t('playlistEditor.videoFullHint', { dur: fmtDuration(selectedAddMedia?.duration) })
                        : $t('playlistEditor.imageDurationHint')"
                >
                    <div class="flex items-center gap-2">
                        <TextInput v-model="addForm.duration_seconds" type="number" />
                        <Btn
                            v-if="addIsVideo && selectedAddMedia?.duration"
                            size="sm"
                            variant="ghost"
                            @click="addForm.duration_seconds = selectedAddMedia.duration"
                        >{{ $t('playlistEditor.fullLength') }}</Btn>
                    </div>
                </FormField>
                <FormField :label="$t('playlistEditor.transitionLabel')">
                    <SelectInput v-model="addForm.transition_effect" :options="TRANSITIONS" />
                </FormField>
            </form>
            <template #footer>
                <Btn variant="secondary" @click="showAdd = false">{{ $t('common.cancel') }}</Btn>
                <Btn variant="primary" :loading="addSaving" @click="addMedia">{{ $t('common.add') }}</Btn>
            </template>
        </Modal>

        <!-- Edit item modal -->
        <Modal v-model="showEdit" :title="$t('playlistEditor.editItemTitle')">
            <div
                v-if="editError"
                class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"
            >
                {{ editError }}
            </div>
            <p v-if="editItem" class="text-sm text-slate-600 mb-4">
                {{ editItem.media?.title ?? $t('playlistEditor.itemFallbackCap') }}
            </p>
            <form class="space-y-4" @submit.prevent="saveEdit">
                <FormField :label="$t('playlistEditor.durationLabel')">
                    <TextInput
                        v-model="editForm.duration_seconds"
                        type="number"
                        :placeholder="$t('playlistEditor.durationDefaultPlaceholder')"
                    />
                </FormField>
                <FormField :label="$t('playlistEditor.transitionLabel')">
                    <SelectInput v-model="editForm.transition_effect" :options="TRANSITIONS" />
                </FormField>
            </form>
            <template #footer>
                <Btn variant="secondary" @click="showEdit = false">{{ $t('common.cancel') }}</Btn>
                <Btn variant="primary" :loading="editSaving" @click="saveEdit">{{ $t('common.save') }}</Btn>
            </template>
        </Modal>
    </div>
</template>
