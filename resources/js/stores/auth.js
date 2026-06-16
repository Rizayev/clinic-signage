import { defineStore } from 'pinia';
import api from '@/services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('signage_token') || null,
    }),

    getters: {
        isAuthed: (state) => !!state.token,
        role: (state) => state.user?.role || null,
    },

    actions: {
        async login(email, password) {
            const { data } = await api.post('/login', { email, password });
            this.token = data.token;
            this.user = data.user;
            localStorage.setItem('signage_token', data.token);
        },

        async fetchMe() {
            const { data } = await api.get('/me');
            this.user = data.data ?? data;
        },

        async logout() {
            try {
                await api.post('/logout');
            } catch (e) {
                // ignore
            }
            this.token = null;
            this.user = null;
            localStorage.removeItem('signage_token');
        },
    },
});
