import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '@/components/AppLayout.vue';

const routes = [
    { path: '/login', name: 'login', component: () => import('@/pages/Login.vue'), meta: { public: true } },
    {
        path: '/',
        component: AppLayout,
        children: [
            { path: '', name: 'dashboard', component: () => import('@/pages/Dashboard.vue') },
            { path: 'branches', name: 'branches', component: () => import('@/pages/Branches.vue') },
            { path: 'zones', name: 'zones', component: () => import('@/pages/Zones.vue') },
            { path: 'devices', name: 'devices', component: () => import('@/pages/Devices.vue') },
            { path: 'devices/:id', name: 'device-detail', component: () => import('@/pages/DeviceDetail.vue') },
            { path: 'media', name: 'media', component: () => import('@/pages/Media.vue') },
            { path: 'playlists', name: 'playlists', component: () => import('@/pages/Playlists.vue') },
            { path: 'playlists/:id', name: 'playlist-editor', component: () => import('@/pages/PlaylistEditor.vue') },
            { path: 'tickers', name: 'tickers', component: () => import('@/pages/Tickers.vue') },
            { path: 'emergency', name: 'emergency', component: () => import('@/pages/Emergency.vue') },
            { path: 'users', name: 'users', component: () => import('@/pages/Users.vue') },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    const token = localStorage.getItem('signage_token');
    if (!to.meta.public && !token) {
        return { name: 'login' };
    }
    if (to.name === 'login' && token) {
        return { name: 'dashboard' };
    }
});

export default router;
