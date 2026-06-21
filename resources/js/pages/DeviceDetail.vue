<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Badge from '@/components/ui/Badge.vue';
import StatusDot from '@/components/ui/StatusDot.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { t } = useI18n();

const deviceId = computed(() => route.params.id);

const device = ref(null);
const loading = ref(true);

const logs = ref([]);
const logsLoading = ref(true);
const autoRefresh = ref(true);
let logsTimer = null;

const deviceTypeLabels = computed(() => ({
    android_tv: 'Android TV',
    android_box: 'Android Box',
    browser_player: t('deviceDetail.deviceTypeBrowserPlayer'),
    windows_player: t('deviceDetail.deviceTypeWindowsPlayer'),
    raspberry_player: t('deviceDetail.deviceTypeRaspberryPlayer'),
}));

const orientationLabels = computed(() => ({
    landscape: t('deviceDetail.orientationLandscape'),
    portrait: t('deviceDetail.orientationPortrait'),
}));

const logLevelColor = {
    info: 'slate',
    warning: 'amber',
    error: 'red',
};

function formatDate(value) {
    if (!value) return '—';
    const dt = new Date(value);
    return Number.isNaN(dt.getTime()) ? value : dt.toLocaleString('ru-RU');
}

function val(v) {
    return v === null || v === undefined || v === '' ? '—' : v;
}

const branchName = computed(() => device.value?.branch?.name ?? device.value?.branch_name ?? '—');
const zoneName = computed(() => device.value?.zone?.name ?? device.value?.zone_name ?? '—');
const playlistName = computed(
    () => device.value?.current_playlist?.name ?? device.value?.current_playlist_name ?? '—'
);
const deviceTypeLabel = computed(() => deviceTypeLabels.value[device.value?.device_type] ?? val(device.value?.device_type));
const orientationLabel = computed(() => orientationLabels.value[device.value?.screen_orientation] ?? val(device.value?.screen_orientation));

const freeStorage = computed(() => {
    const bytes = device.value?.free_storage;
    if (bytes === null || bytes === undefined) return '—';
    const mb = Number(bytes) / (1024 * 1024);
    if (Number.isNaN(mb)) return '—';
    if (mb >= 1024) return `${(mb / 1024).toFixed(1)} ${t('deviceDetail.unitGb')}`;
    return `${mb.toFixed(0)} ${t('deviceDetail.unitMb')}`;
});

const fields = computed(() => {
    const d = device.value;
    if (!d) return [];
    return [
        { label: t('common.name'), value: val(d.name) },
        { label: t('deviceDetail.deviceCode'), value: val(d.device_code) },
        { label: t('deviceDetail.branch'), value: branchName.value },
        { label: t('deviceDetail.zone'), value: zoneName.value },
        { label: t('deviceDetail.type'), value: deviceTypeLabel.value, badge: true },
        { label: t('deviceDetail.orientation'), value: orientationLabel.value },
        { label: t('deviceDetail.ipAddress'), value: val(d.ip_address) },
        { label: t('deviceDetail.appVersion'), value: val(d.app_version) },
        { label: t('deviceDetail.lastSeen'), value: d.last_seen_human ?? formatDate(d.last_seen_at) },
        { label: t('deviceDetail.currentPlaylist'), value: playlistName.value },
        { label: t('deviceDetail.freeStorage'), value: freeStorage.value },
    ];
});

async function loadDevice() {
    loading.value = true;
    try {
        const { data } = await api.get(`/devices/${deviceId.value}`);
        device.value = data.data ?? data;
    } catch (e) {
        toast.error(e?.response?.data?.message || t('deviceDetail.loadDeviceError'));
    } finally {
        loading.value = false;
    }
}

async function loadLogs(notify = false) {
    try {
        const { data } = await api.get(`/devices/${deviceId.value}/logs`);
        logs.value = data.data ?? data;
        if (notify) toast.success(t('deviceDetail.logsRefreshed'));
    } catch (e) {
        toast.error(e?.response?.data?.message || t('deviceDetail.loadLogsError'));
    } finally {
        logsLoading.value = false;
    }
}

function startAutoRefresh() {
    stopAutoRefresh();
    if (autoRefresh.value) {
        logsTimer = setInterval(loadLogs, 15000);
    }
}

function stopAutoRefresh() {
    if (logsTimer) {
        clearInterval(logsTimer);
        logsTimer = null;
    }
}

function toggleAutoRefresh() {
    autoRefresh.value = !autoRefresh.value;
    startAutoRefresh();
    toast.info(autoRefresh.value ? t('deviceDetail.autoRefreshOn') : t('deviceDetail.autoRefreshOff'));
}

function goBack() {
    router.push('/devices');
}

onMounted(() => {
    loadDevice();
    loadLogs();
    startAutoRefresh();
});

onBeforeUnmount(stopAutoRefresh);
</script>

<template>
    <div>
        <PageHeader
            :title="device?.name || $t('deviceDetail.device')"
            :subtitle="device?.device_code ? `${$t('deviceDetail.code')}: ${device.device_code}` : $t('deviceDetail.deviceCard')"
        >
            <template #actions>
                <Btn variant="secondary" @click="goBack">← {{ $t('common.back') }}</Btn>
                <Btn variant="ghost" :loading="loading" @click="loadDevice">{{ $t('deviceDetail.refresh') }}</Btn>
            </template>
        </PageHeader>

        <Spinner v-if="loading" :label="$t('common.loading')" />

        <template v-else-if="device">
            <Card class="mb-5">
                <template #actions>
                    <StatusDot :status="device.status" />
                </template>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                    <div
                        v-for="f in fields"
                        :key="f.label"
                        class="flex justify-between items-center gap-4 border-b border-slate-50 pb-2"
                    >
                        <span class="text-sm text-slate-500">{{ f.label }}</span>
                        <Badge v-if="f.badge" color="indigo">{{ f.value }}</Badge>
                        <span v-else class="text-sm text-slate-800 font-medium text-right truncate max-w-[60%]" :title="String(f.value)">
                            {{ f.value }}
                        </span>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-100">
                    <p class="text-xs text-slate-500 mb-1">{{ $t('deviceDetail.pairingCode') }}</p>
                    <div
                        v-if="device.pairing_code"
                        class="inline-block font-mono text-lg font-bold tracking-widest bg-amber-50 text-amber-700 border border-amber-200 rounded-lg px-4 py-2"
                    >
                        {{ device.pairing_code }}
                    </div>
                    <p v-else class="text-sm text-slate-400">{{ $t('deviceDetail.alreadyPaired') }}</p>
                </div>
            </Card>

            <Card :title="$t('deviceDetail.logs')">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Btn variant="ghost" size="sm" @click="toggleAutoRefresh">
                            {{ autoRefresh ? $t('deviceDetail.autoRefreshLabelOn') : $t('deviceDetail.autoRefreshLabelOff') }}
                        </Btn>
                        <Btn variant="secondary" size="sm" :loading="logsLoading" @click="loadLogs(true)">{{ $t('deviceDetail.refresh') }}</Btn>
                    </div>
                </template>

                <Spinner v-if="logsLoading" :label="$t('common.loading')" />
                <EmptyState
                    v-else-if="!logs.length"
                    icon="📋"
                    :title="$t('deviceDetail.noLogsTitle')"
                    :hint="$t('deviceDetail.noLogsHint')"
                />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500 border-b border-slate-100">
                                <th class="py-2.5 px-3 font-medium">{{ $t('deviceDetail.time') }}</th>
                                <th class="py-2.5 px-3 font-medium">{{ $t('deviceDetail.level') }}</th>
                                <th class="py-2.5 px-3 font-medium">{{ $t('deviceDetail.event') }}</th>
                                <th class="py-2.5 px-3 font-medium">{{ $t('deviceDetail.message') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="log in logs"
                                :key="log.id"
                                class="border-b border-slate-50 hover:bg-slate-50 transition"
                            >
                                <td class="py-2.5 px-3 text-slate-500 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
                                <td class="py-2.5 px-3">
                                    <Badge :color="logLevelColor[log.level] || 'slate'">{{ log.level ?? 'info' }}</Badge>
                                </td>
                                <td class="py-2.5 px-3 text-slate-700">{{ log.event ?? '—' }}</td>
                                <td class="py-2.5 px-3 text-slate-500">{{ log.message ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </template>
    </div>
</template>
