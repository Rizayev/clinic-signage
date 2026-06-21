<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
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
import Wizard from '@/components/ui/Wizard.vue';

const router = useRouter();
const toast = useToast();
const { confirm } = useConfirm();
const { t } = useI18n();

const devices = ref([]);
const branches = ref([]);
const zones = ref([]);
const playlists = ref([]);
const loading = ref(true);

const filters = ref({ status: '', zone_id: '', q: '' });

const statusOptions = computed(() => [
    { value: 'online', label: t('devices.statusOnline') },
    { value: 'offline', label: t('devices.statusOffline') },
    { value: 'error', label: t('devices.statusError') },
    { value: 'updating', label: t('devices.statusUpdating') },
    { value: 'disabled', label: t('devices.statusDisabled') },
]);

const deviceTypeOptions = computed(() => [
    { value: 'android_tv', label: 'Android TV' },
    { value: 'android_box', label: 'Android Box' },
    { value: 'browser_player', label: t('devices.typeBrowserPlayer') },
    { value: 'windows_player', label: t('devices.typeWindowsPlayer') },
    { value: 'raspberry_player', label: t('devices.typeRaspberryPlayer') },
]);

const orientationOptions = computed(() => [
    { value: 'landscape', label: t('devices.orientationLandscape') },
    { value: 'portrait', label: t('devices.orientationPortrait') },
]);

const statusFilterOptions = computed(() => [
    { value: '', label: t('devices.allStatuses') },
    ...statusOptions.value,
]);
const zoneFilterOptions = computed(() => [
    { value: '', label: t('devices.allZones') },
    ...zones.value.map((z) => ({ value: z.id, label: z.name })),
]);

const deviceTypeLabels = computed(() =>
    Object.fromEntries(deviceTypeOptions.value.map((o) => [o.value, o.label])),
);
function deviceTypeLabel(type) {
    return deviceTypeLabels.value[type] ?? type ?? '—';
}

// --- Add device modal ---
const createModal = ref(false);
const createForm = ref({
    name: '',
    device_code: '',
    branch_id: '',
    zone_id: '',
    device_type: 'android_tv',
    screen_orientation: 'landscape',
    audio_enabled: false,
});
const createSaving = ref(false);
const createErrors = ref({});
const createdDevice = ref(null);
const createStep = ref(0);

const deviceWizardSteps = computed(() => [
    { label: t('devices.stepDevice') },
    { label: t('devices.stepPlacement') },
]);

function validateCreateStep(i) {
    if (i === 0) {
        if (!createForm.value.name.trim()) return t('devices.validateName');
        if (!createForm.value.device_code.trim()) return t('devices.validateDeviceCode');
    }
    if (i === 1 && !createForm.value.branch_id) return t('devices.validateBranch');
    return true;
}

const branchSelectOptions = computed(() =>
    branches.value.map((b) => ({ value: b.id, label: b.name })),
);

const createZoneOptions = computed(() => {
    const list = !createForm.value.branch_id
        ? zones.value
        : zones.value.filter((z) => String(z.branch_id) === String(createForm.value.branch_id));
    return [{ value: '', label: t('devices.noZone') }, ...list.map((z) => ({ value: z.id, label: z.name }))];
});

// --- Assign playlist modal ---
const assignModal = ref(false);
const assignDevice = ref(null);
const assignPlaylistId = ref('');
const assignSaving = ref(false);

const assignPlaylistOptions = computed(() => [
    { value: '', label: t('devices.noPlaylist') },
    ...playlists.value.map((p) => ({ value: p.id, label: p.name })),
]);

function zoneName(d) {
    return d.zone?.name ?? d.zone_name ?? '—';
}

function playlistName(d) {
    return d.current_playlist?.name ?? d.current_playlist_name ?? '—';
}

function lastSeen(d) {
    return d.last_seen_human ?? formatDate(d.last_seen_at);
}

function formatDate(value) {
    if (!value) return '—';
    const dt = new Date(value);
    return Number.isNaN(dt.getTime()) ? value : dt.toLocaleString('ru-RU');
}

async function load() {
    loading.value = true;
    try {
        const params = {};
        if (filters.value.status) params.status = filters.value.status;
        if (filters.value.zone_id) params.zone_id = filters.value.zone_id;
        if (filters.value.q) params.q = filters.value.q;
        const { data } = await api.get('/devices', { params });
        devices.value = data.data ?? data;
    } catch (e) {
        toast.error(e?.response?.data?.message || t('devices.loadDevicesError'));
    } finally {
        loading.value = false;
    }
}

async function loadRefs() {
    try {
        const [b, z] = await Promise.all([
            api.get('/branches'),
            api.get('/zones'),
        ]);
        branches.value = b.data.data ?? b.data;
        zones.value = z.data.data ?? z.data;
    } catch (e) {
        // refs are optional for the table; ignore silently
    }
}

function openCreate() {
    createForm.value = {
        name: '',
        device_code: '',
        branch_id: branches.value[0]?.id ?? '',
        zone_id: '',
        device_type: 'android_tv',
        screen_orientation: 'landscape',
        audio_enabled: false,
    };
    createErrors.value = {};
    createdDevice.value = null;
    createStep.value = 0;
    createModal.value = true;
}

async function submitCreate() {
    createSaving.value = true;
    createErrors.value = {};
    try {
        const payload = {
            name: createForm.value.name,
            device_code: createForm.value.device_code,
            branch_id: createForm.value.branch_id || null,
            zone_id: createForm.value.zone_id || null,
            device_type: createForm.value.device_type,
            screen_orientation: createForm.value.screen_orientation,
            audio_enabled: createForm.value.audio_enabled,
        };
        const { data } = await api.post('/devices', payload);
        createdDevice.value = data.data ?? data;
        toast.success(t('devices.deviceCreated'));
        await load();
    } catch (e) {
        if (e?.response?.status === 422 && e.response.data?.errors) {
            const mapped = {};
            for (const [k, v] of Object.entries(e.response.data.errors)) {
                mapped[k] = Array.isArray(v) ? v[0] : v;
            }
            createErrors.value = mapped;
        }
        toast.error(e?.response?.data?.message || t('devices.createDeviceError'));
    } finally {
        createSaving.value = false;
    }
}

function closeCreate() {
    createModal.value = false;
    createdDevice.value = null;
}

async function copyPairingCode() {
    const code = createdDevice.value?.pairing_code;
    if (!code) return;
    try {
        await navigator.clipboard.writeText(code);
        toast.success(t('devices.pairingCodeCopied'));
    } catch (e) {
        toast.error(t('devices.copyCodeError'));
    }
}

function openAssign(device) {
    assignDevice.value = device;
    assignPlaylistId.value = device.current_playlist?.id ?? device.current_playlist_id ?? '';
    assignModal.value = true;
    loadPlaylists();
}

async function loadPlaylists() {
    try {
        const { data } = await api.get('/playlists');
        playlists.value = data.data ?? data;
    } catch (e) {
        toast.error(e?.response?.data?.message || t('devices.loadPlaylistsError'));
    }
}

async function submitAssign() {
    if (!assignDevice.value) return;
    assignSaving.value = true;
    try {
        await api.post(`/devices/${assignDevice.value.id}/assign-playlist`, {
            playlist_id: assignPlaylistId.value || null,
        });
        assignModal.value = false;
        toast.success(t('devices.playlistAssigned'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('devices.assignPlaylistError'));
    } finally {
        assignSaving.value = false;
    }
}

async function removeDevice(device) {
    if (!(await confirm({
        title: t('devices.deleteTitle'),
        message: t('devices.deleteMessage', { name: device.name }),
        confirmText: t('common.delete'),
    }))) return;
    try {
        await api.delete(`/devices/${device.id}`);
        toast.success(t('devices.deviceDeleted'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('devices.deleteDeviceError'));
    }
}

async function toggleAudio(device) {
    try {
        const { data } = await api.put(`/devices/${device.id}`, { audio_enabled: !device.audio_enabled });
        device.audio_enabled = (data.data ?? data).audio_enabled;
        toast.success(device.audio_enabled ? t('devices.soundOn') : t('devices.soundOff'));
    } catch (e) {
        toast.error(e?.response?.data?.message || t('devices.toggleAudioError'));
    }
}

function openDetail(device) {
    router.push(`/devices/${device.id}`);
}

onMounted(() => {
    load();
    loadRefs();
});
</script>

<template>
    <div>
        <PageHeader :title="$t('devices.pageTitle')" :subtitle="$t('devices.pageSubtitle')">
            <template #actions>
                <Btn variant="primary" @click="openCreate">+ {{ $t('devices.addDevice') }}</Btn>
            </template>
        </PageHeader>

        <Card class="mb-5">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <FormField :label="$t('common.status')">
                    <SelectInput
                        v-model="filters.status"
                        :options="statusFilterOptions"
                        @update:modelValue="load"
                    />
                </FormField>
                <FormField :label="$t('devices.zone')">
                    <SelectInput
                        v-model="filters.zone_id"
                        :options="zoneFilterOptions"
                        @update:modelValue="load"
                    />
                </FormField>
                <FormField :label="$t('common.search')" class="md:col-span-2">
                    <div class="flex gap-2">
                        <TextInput
                            v-model="filters.q"
                            :placeholder="$t('devices.searchPlaceholder')"
                            @keyup.enter="load"
                        />
                        <Btn variant="secondary" @click="load">{{ $t('devices.find') }}</Btn>
                    </div>
                </FormField>
            </div>
        </Card>

        <Card>
            <Spinner v-if="loading" :label="$t('common.loading')" />
            <EmptyState
                v-else-if="!devices.length"
                icon="📺"
                :title="$t('devices.emptyTitle')"
                :hint="$t('devices.emptyHint')"
            >
                <template #action>
                    <Btn variant="primary" @click="openCreate">+ {{ $t('devices.addDevice') }}</Btn>
                </template>
            </EmptyState>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.name') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('devices.code') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('devices.type') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('devices.zone') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.status') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('devices.playlist') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('devices.activity') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('devices.pairingCode') }}</th>
                            <th class="py-2.5 px-3 font-medium text-right">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="d in devices"
                            :key="d.id"
                            class="border-b border-slate-50 hover:bg-slate-50 transition"
                        >
                            <td class="py-2.5 px-3 text-slate-800 font-medium">{{ d.name }}</td>
                            <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">{{ d.device_code }}</td>
                            <td class="py-2.5 px-3">
                                <Badge color="indigo">{{ deviceTypeLabel(d.device_type) }}</Badge>
                            </td>
                            <td class="py-2.5 px-3 text-slate-600">{{ zoneName(d) }}</td>
                            <td class="py-2.5 px-3"><StatusDot :status="d.status" /></td>
                            <td class="py-2.5 px-3 text-slate-600">{{ playlistName(d) }}</td>
                            <td class="py-2.5 px-3 text-slate-500 whitespace-nowrap">{{ lastSeen(d) }}</td>
                            <td class="py-2.5 px-3">
                                <Badge v-if="d.pairing_code" color="amber">
                                    <span class="font-mono tracking-wide">{{ d.pairing_code }}</span>
                                </Badge>
                                <span v-else class="text-slate-400 text-xs">—</span>
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <div class="inline-flex gap-1">
                                    <Btn size="sm" variant="ghost" @click="openDetail(d)" :title="$t('devices.openCard')">{{ $t('common.open') }}</Btn>
                                    <Btn size="sm" variant="secondary" @click="openAssign(d)" :title="$t('devices.assignPlaylist')">🎬 {{ $t('devices.playlist') }}</Btn>
                                    <Btn
                                        size="sm"
                                        :variant="d.audio_enabled ? 'primary' : 'ghost'"
                                        @click="toggleAudio(d)"
                                        :title="d.audio_enabled ? $t('devices.audioOnTitle') : $t('devices.audioOffTitle')"
                                    >{{ d.audio_enabled ? '🔊' : '🔇' }}</Btn>
                                    <Btn size="sm" variant="danger" @click="removeDevice(d)" :title="$t('devices.deleteDevice')">🗑</Btn>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Add device modal -->
        <Modal v-model="createModal" :title="$t('devices.addDevice')">
            <!-- Success: pairing code -->
            <div v-if="createdDevice" class="text-center py-2">
                <div class="text-4xl mb-2">✅</div>
                <p class="text-sm text-slate-600 mb-3">
                    {{ $t('devices.createdEnterCode') }}
                </p>
                <div
                    class="inline-block font-mono text-2xl font-bold tracking-widest bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg px-6 py-4 mb-3"
                >
                    {{ createdDevice.pairing_code || '—' }}
                </div>
                <div v-if="createdDevice.pairing_code" class="mb-3">
                    <Btn size="sm" variant="secondary" @click="copyPairingCode">📋 {{ $t('devices.copyCode') }}</Btn>
                    <p class="text-xs text-slate-400 mt-2">{{ $t('devices.codeValidHint') }}</p>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                    {{ $t('devices.deviceLabel') }}: {{ createdDevice.name }} ({{ createdDevice.device_code }})
                </p>
                <div class="flex justify-center">
                    <Btn variant="primary" @click="closeCreate">{{ $t('common.done') }}</Btn>
                </div>
            </div>

            <!-- Wizard -->
            <Wizard
                v-else
                v-model="createStep"
                :steps="deviceWizardSteps"
                :validate="validateCreateStep"
                :loading="createSaving"
                :finish-text="$t('common.create')"
                @finish="submitCreate"
                @cancel="createModal = false"
            >
                <template #default="{ step: s }">
                    <!-- Step 1: Устройство -->
                    <div v-if="s === 0" class="space-y-4">
                        <FormField :label="$t('common.name')" required :error="createErrors.name">
                            <TextInput v-model="createForm.name" :placeholder="$t('devices.namePlaceholder')" />
                        </FormField>
                        <FormField :label="$t('devices.deviceCode')" required :error="createErrors.device_code" :hint="$t('devices.deviceCodeHint')">
                            <TextInput v-model="createForm.device_code" placeholder="TV-01" />
                        </FormField>
                        <div class="grid grid-cols-2 gap-4">
                            <FormField :label="$t('devices.deviceType')" :error="createErrors.device_type">
                                <SelectInput v-model="createForm.device_type" :options="deviceTypeOptions" />
                            </FormField>
                            <FormField :label="$t('devices.screenOrientation')" :error="createErrors.screen_orientation">
                                <SelectInput v-model="createForm.screen_orientation" :options="orientationOptions" />
                            </FormField>
                        </div>
                        <FormField :label="$t('devices.sound')" :hint="$t('devices.soundHint')">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="createForm.audio_enabled" class="h-4 w-4 rounded border-slate-300" />
                                <span class="text-sm text-slate-600">{{ createForm.audio_enabled ? `🔊 ${$t('devices.withSound')}` : `🔇 ${$t('devices.withoutSound')}` }}</span>
                            </label>
                        </FormField>
                    </div>

                    <!-- Step 2: Размещение -->
                    <div v-else-if="s === 1" class="space-y-4">
                        <FormField :label="$t('devices.branch')" required :error="createErrors.branch_id">
                            <SelectInput
                                v-model="createForm.branch_id"
                                :options="branchSelectOptions"
                                :placeholder="$t('devices.selectBranch')"
                            />
                        </FormField>
                        <FormField :label="$t('devices.zone')" :error="createErrors.zone_id" :hint="$t('common.optional')">
                            <SelectInput v-model="createForm.zone_id" :options="createZoneOptions" />
                        </FormField>
                        <p class="text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2">
                            {{ $t('devices.placementHint') }}
                        </p>
                    </div>
                </template>
            </Wizard>
        </Modal>

        <!-- Assign playlist modal -->
        <Modal v-model="assignModal" :title="$t('devices.assignPlaylist')">
            <div class="space-y-4">
                <p v-if="assignDevice" class="text-sm text-slate-600">
                    {{ $t('devices.deviceLabel') }}: <span class="font-medium text-slate-800">{{ assignDevice.name }}</span>
                </p>
                <FormField :label="$t('devices.playlist')">
                    <SelectInput v-model="assignPlaylistId" :options="assignPlaylistOptions" />
                </FormField>
            </div>
            <template #footer>
                <Btn variant="secondary" @click="assignModal = false">{{ $t('common.cancel') }}</Btn>
                <Btn variant="primary" :loading="assignSaving" @click="submitAssign">{{ $t('devices.assign') }}</Btn>
            </template>
        </Modal>
    </div>
</template>
