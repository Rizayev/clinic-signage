<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const toast = useToast();

const stats = ref({});
const recentLogs = ref([]);
const emergencyActive = ref(false);
const loading = ref(true);

const cards = computed(() => [
    { label: 'Всего ТВ', value: stats.value.devices_total ?? 0, color: 'text-slate-800', icon: '📺' },
    { label: 'Онлайн', value: stats.value.devices_online ?? 0, color: 'text-green-600', icon: '🟢' },
    { label: 'Оффлайн', value: stats.value.devices_offline ?? 0, color: 'text-slate-500', icon: '⚪' },
    { label: 'С ошибкой', value: stats.value.devices_error ?? 0, color: 'text-red-600', icon: '🔴' },
    { label: 'Активные плейлисты', value: stats.value.playlists_active ?? 0, color: 'text-indigo-600', icon: '🎞️' },
    { label: 'Активные строки', value: stats.value.tickers_active ?? 0, color: 'text-amber-600', icon: '📝' },
    { label: 'Медиа', value: stats.value.media_total ?? 0, color: 'text-slate-800', icon: '🖼️' },
]);

const levelBadge = {
    info: 'blue',
    warning: 'amber',
    error: 'red',
};

const levelLabel = {
    info: 'Инфо',
    warning: 'Предупреждение',
    error: 'Ошибка',
};

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
        toast.error(e?.response?.data?.message || 'Не удалось загрузить данные панели.');
    } finally {
        loading.value = false;
    }
}

const resyncing = ref(false);
async function resyncScreens() {
    resyncing.value = true;
    try {
        await api.post('/resync');
        toast.success('Команда синхронизации отправлена на все экраны');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось отправить команду синхронизации.');
    } finally {
        resyncing.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Панель управления" subtitle="Обзор системы цифровых вывесок">
            <template #actions>
                <Btn variant="secondary" :loading="loading" @click="load">🔄 Обновить</Btn>
            </template>
        </PageHeader>

        <div
            v-if="emergencyActive"
            class="mb-5 rounded-xl border border-red-300 bg-red-600 text-white px-5 py-4 flex items-center gap-3 shadow-sm"
        >
            <span class="text-xl">⚠</span>
            <div>
                <p class="font-semibold">Активен экстренный режим</p>
                <p class="text-sm text-red-100">На экранах сейчас отображается экстренное сообщение.</p>
            </div>
            <RouterLink to="/emergency" class="ml-auto">
                <Btn variant="secondary">Управление</Btn>
            </RouterLink>
        </div>

        <Spinner v-if="loading" label="Загрузка…" />

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
                    <Card title="Последние события">
                        <EmptyState
                            v-if="recentLogs.length === 0"
                            icon="📭"
                            title="Пока нет событий"
                            hint="Здесь появятся последние события устройств."
                        />
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-slate-500 border-b border-slate-100">
                                        <th class="py-2.5 px-3 font-medium">Время</th>
                                        <th class="py-2.5 px-3 font-medium">Устройство</th>
                                        <th class="py-2.5 px-3 font-medium">Уровень</th>
                                        <th class="py-2.5 px-3 font-medium">Событие</th>
                                        <th class="py-2.5 px-3 font-medium">Сообщение</th>
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
                                                {{ levelLabel[log.level] ?? log.level ?? 'Инфо' }}
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
                    <Card title="Быстрые действия">
                        <div class="flex flex-col gap-2">
                            <Btn variant="secondary" class="w-full" :loading="resyncing" @click="resyncScreens">
                                🔁 Синхронизировать экраны
                            </Btn>
                            <RouterLink to="/media">
                                <Btn variant="secondary" class="w-full">🖼️ Медиа</Btn>
                            </RouterLink>
                            <RouterLink to="/playlists">
                                <Btn variant="secondary" class="w-full">🎞️ Плейлисты</Btn>
                            </RouterLink>
                            <RouterLink to="/emergency">
                                <Btn variant="danger" class="w-full">⚠ Экстренное сообщение</Btn>
                            </RouterLink>
                            <RouterLink to="/player">
                                <Btn variant="primary" class="w-full">▶ Плеер</Btn>
                            </RouterLink>
                        </div>
                    </Card>
                </div>
            </div>
        </template>
    </div>
</template>
