<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t } = useI18n();
const toast = useToast();

const stats = ref({});
const recentLogs = ref([]);
const emergencyActive = ref(false);
const loading = ref(true);

const cards = computed(() => [
    { label: t('dashboard.statTotalTvs'), value: stats.value.devices_total ?? 0, color: 'text-slate-800', icon: '📺' },
    { label: t('dashboard.statOnline'), value: stats.value.devices_online ?? 0, color: 'text-green-600', icon: '🟢' },
    { label: t('dashboard.statOffline'), value: stats.value.devices_offline ?? 0, color: 'text-slate-500', icon: '⚪' },
    { label: t('dashboard.statError'), value: stats.value.devices_error ?? 0, color: 'text-red-600', icon: '🔴' },
    { label: t('dashboard.statActivePlaylists'), value: stats.value.playlists_active ?? 0, color: 'text-indigo-600', icon: '🎞️' },
    { label: t('dashboard.statActiveTickers'), value: stats.value.tickers_active ?? 0, color: 'text-amber-600', icon: '📝' },
    { label: t('dashboard.statMedia'), value: stats.value.media_total ?? 0, color: 'text-slate-800', icon: '🖼️' },
]);

const levelBadge = {
    info: 'blue',
    warning: 'amber',
    error: 'red',
};

const levelLabel = computed(() => ({
    info: t('dashboard.levelInfo'),
    warning: t('dashboard.levelWarning'),
    error: t('dashboard.levelError'),
}));

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('ru-RU');
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/dashboard');
        const payload = data.data ?? data;
        stats.value = payload.stats ?? payload;
        recentLogs.value = payload.recent_logs ?? [];
        emergencyActive.value = !!payload.emergency_active;
    } catch (e) {
        toast.error(e?.response?.data?.message || t('dashboard.loadDataError'));
    } finally {
        loading.value = false;
    }
}

const resyncing = ref(false);
async function resyncScreens() {
    resyncing.value = true;
    try {
        await api.post('/resync');
        toast.success(t('dashboard.resyncSuccess'));
    } catch (e) {
        toast.error(e?.response?.data?.message || t('dashboard.resyncError'));
    } finally {
        resyncing.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader :title="$t('dashboard.title')" :subtitle="$t('dashboard.subtitle')">
            <template #actions>
                <Btn variant="secondary" :loading="loading" @click="load">🔄 {{ $t('dashboard.refresh') }}</Btn>
            </template>
        </PageHeader>

        <div
            v-if="emergencyActive"
            class="mb-5 rounded-xl border border-red-300 bg-red-600 text-white px-5 py-4 flex items-center gap-3 shadow-sm"
        >
            <span class="text-xl">⚠</span>
            <div>
                <p class="font-semibold">{{ $t('dashboard.emergencyActiveTitle') }}</p>
                <p class="text-sm text-red-100">{{ $t('dashboard.emergencyActiveText') }}</p>
            </div>
            <RouterLink to="/emergency" class="ml-auto">
                <Btn variant="secondary">{{ $t('dashboard.manage') }}</Btn>
            </RouterLink>
        </div>

        <Spinner v-if="loading" :label="$t('common.loading')" />

        <template v-else>
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4 mb-6">
                <Card v-for="card in cards" :key="card.label">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs text-slate-500">{{ card.label }}</p>
                        <span class="text-lg leading-none">{{ card.icon }}</span>
                    </div>
                    <p class="text-3xl font-semibold mt-2" :class="card.color">{{ card.value }}</p>
                </Card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <Card :title="$t('dashboard.recentEvents')">
                        <EmptyState
                            v-if="recentLogs.length === 0"
                            icon="📭"
                            :title="$t('dashboard.noEventsTitle')"
                            :hint="$t('dashboard.noEventsHint')"
                        />
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-slate-500 border-b border-slate-100">
                                        <th class="py-2.5 px-3 font-medium">{{ $t('dashboard.colTime') }}</th>
                                        <th class="py-2.5 px-3 font-medium">{{ $t('dashboard.colDevice') }}</th>
                                        <th class="py-2.5 px-3 font-medium">{{ $t('dashboard.colLevel') }}</th>
                                        <th class="py-2.5 px-3 font-medium">{{ $t('dashboard.colEvent') }}</th>
                                        <th class="py-2.5 px-3 font-medium">{{ $t('dashboard.colMessage') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="log in recentLogs"
                                        :key="log.id"
                                        class="border-b border-slate-50 hover:bg-slate-50 transition"
                                    >
                                        <td class="py-2.5 px-3 text-slate-500 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
                                        <td class="py-2.5 px-3 text-slate-700">{{ log.device?.name ?? log.device_name ?? log.device_id ?? '—' }}</td>
                                        <td class="py-2.5 px-3">
                                            <Badge :color="levelBadge[log.level] || 'slate'">
                                                {{ levelLabel[log.level] ?? log.level ?? $t('dashboard.levelInfo') }}
                                            </Badge>
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-700">{{ log.event ?? '—' }}</td>
                                        <td class="py-2.5 px-3 text-slate-500 max-w-[280px] truncate" :title="log.message ?? ''">{{ log.message ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                <div>
                    <Card :title="$t('dashboard.quickActions')">
                        <div class="flex flex-col gap-2">
                            <Btn variant="secondary" class="w-full" :loading="resyncing" @click="resyncScreens">
                                🔁 {{ $t('dashboard.syncScreens') }}
                            </Btn>
                            <RouterLink to="/media">
                                <Btn variant="secondary" class="w-full">🖼️ {{ $t('nav.media') }}</Btn>
                            </RouterLink>
                            <RouterLink to="/playlists">
                                <Btn variant="secondary" class="w-full">🎞️ {{ $t('nav.playlists') }}</Btn>
                            </RouterLink>
                            <RouterLink to="/emergency">
                                <Btn variant="danger" class="w-full">⚠ {{ $t('dashboard.emergencyMessage') }}</Btn>
                            </RouterLink>
                            <RouterLink to="/player">
                                <Btn variant="primary" class="w-full">▶ {{ $t('dashboard.player') }}</Btn>
                            </RouterLink>
                        </div>
                    </Card>
                </div>
            </div>
        </template>
    </div>
</template>
