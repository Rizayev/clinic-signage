<script setup>
import { onMounted, computed } from 'vue';
import { useRouter, RouterLink, RouterView } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const roleLabels = {
    super_admin: 'Супер-администратор',
    branch_admin: 'Администратор филиала',
    content_manager: 'Контент-менеджер',
    viewer: 'Наблюдатель',
};
const roleLabel = computed(() => roleLabels[auth.user?.role] || auth.user?.role || '');
const initial = computed(() => (auth.user?.name || '?').trim().charAt(0).toUpperCase());

const nav = [
    { to: '/', label: 'Дашборд', icon: '▦', exact: true },
    { to: '/branches', label: 'Филиалы', icon: '🏢' },
    { to: '/zones', label: 'Зоны', icon: '🗺' },
    { to: '/devices', label: 'Устройства', icon: '📺' },
    { to: '/media', label: 'Медиатека', icon: '🎞' },
    { to: '/playlists', label: 'Плейлисты', icon: '🎬' },
    { to: '/tickers', label: 'Бегущие строки', icon: '↔' },
    { to: '/emergency', label: 'Срочные', icon: '🚨' },
    { to: '/users', label: 'Пользователи', icon: '👤' },
];

onMounted(() => {
    if (!auth.user) auth.fetchMe().catch(() => {});
});

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-screen bg-slate-100">
        <aside class="w-60 shrink-0 bg-slate-900 text-slate-200 flex flex-col">
            <div class="px-5 py-5 border-b border-slate-700/60">
                <div class="text-lg font-semibold text-white">Clinic Signage</div>
                <div class="text-xs text-slate-400">Панель управления</div>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <RouterLink
                    v-for="item in nav"
                    :key="item.to"
                    :to="item.to"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition hover:bg-slate-800"
                    active-class="bg-slate-800 text-white"
                    :exact-active-class="item.exact ? 'bg-slate-800 text-white' : ''"
                >
                    <span class="w-5 text-center">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </RouterLink>
            </nav>
            <a href="/player" target="_blank"
               class="mx-3 mb-3 text-center text-xs text-slate-400 hover:text-white border border-slate-700 rounded-lg py-2">
                ▶ Открыть плеер
            </a>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-14 bg-white border-b border-slate-200 flex items-center justify-end px-6 gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-semibold">
                        {{ initial }}
                    </div>
                    <div class="leading-tight">
                        <div class="text-sm font-medium text-slate-700">{{ auth.user?.name || '—' }}</div>
                        <div class="text-xs text-slate-400">{{ roleLabel }}</div>
                    </div>
                </div>
                <button @click="logout"
                        class="text-sm text-red-600 hover:text-red-700 font-medium ml-2">
                    Выйти
                </button>
            </header>
            <main class="flex-1 p-6 overflow-y-auto">
                <RouterView />
            </main>
        </div>
    </div>
</template>
