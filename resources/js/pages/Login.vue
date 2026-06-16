<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import Btn from '@/components/ui/Btn.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';

const router = useRouter();
const auth = useAuthStore();
const toast = useToast();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
    error.value = '';
    loading.value = true;
    try {
        await auth.login(email.value, password.value);
        toast.success('Вход выполнен');
        router.push('/');
    } catch (e) {
        const msg =
            e?.response?.data?.message ||
            'Неверный email или пароль. Попробуйте снова.';
        error.value = msg;
        toast.error(msg);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-600 text-white text-xl font-bold mb-3">
                    CS
                </div>
                <h1 class="text-2xl font-semibold text-slate-800">Clinic Signage</h1>
                <p class="text-sm text-slate-500 mt-1">Вход в панель управления</p>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <form class="space-y-4" @submit.prevent="submit">
                    <div
                        v-if="error"
                        class="rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"
                    >
                        {{ error }}
                    </div>

                    <FormField label="Email" required>
                        <TextInput
                            v-model="email"
                            type="email"
                            placeholder="admin@clinic.local"
                        />
                    </FormField>

                    <FormField label="Пароль" required>
                        <TextInput
                            v-model="password"
                            type="password"
                            placeholder="••••••••"
                        />
                    </FormField>

                    <Btn type="submit" :loading="loading" class="w-full">
                        Войти
                    </Btn>
                </form>

                <p class="text-center text-xs text-slate-400 mt-4">
                    Демо-доступ: super@clinic.local / password
                </p>
            </div>

            <p class="text-center text-xs text-slate-400 mt-4">
                Clinic Signage — система цифровых вывесок
            </p>
        </div>
    </div>
</template>
