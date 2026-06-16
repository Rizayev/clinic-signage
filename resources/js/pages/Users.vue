<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
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
import Toggle from '@/components/ui/Toggle.vue';

const toast = useToast();
const { confirm } = useConfirm();

const users = ref([]);
const branches = ref([]);
const loading = ref(true);

const showModal = ref(false);
const saving = ref(false);
const editingId = ref(null);
const errors = reactive({});

const roles = [
    { value: 'super_admin', label: 'Супер-администратор' },
    { value: 'branch_admin', label: 'Администратор филиала' },
    { value: 'content_manager', label: 'Контент-менеджер' },
    { value: 'viewer', label: 'Наблюдатель' },
];

const roleColors = {
    super_admin: 'violet',
    branch_admin: 'indigo',
    content_manager: 'blue',
    viewer: 'slate',
};

const branchOptions = computed(() => [
    { value: '', label: '— Без филиала —' },
    ...branches.value.map((b) => ({ value: b.id, label: b.name })),
]);

function emptyForm() {
    return {
        name: '',
        email: '',
        password: '',
        role: 'viewer',
        branch_id: '',
        is_active: true,
    };
}

const form = reactive(emptyForm());

function roleLabel(role) {
    return roles.find((r) => r.value === role)?.label ?? role ?? '—';
}

function roleColor(role) {
    return roleColors[role] || 'slate';
}

function branchName(branchId) {
    if (branchId == null || branchId === '') return '—';
    return branches.value.find((b) => b.id === branchId)?.name ?? `#${branchId}`;
}

function clearErrors() {
    Object.keys(errors).forEach((k) => delete errors[k]);
}

function applyValidationErrors(e) {
    const fieldErrors = e?.response?.data?.errors;
    if (fieldErrors) {
        Object.entries(fieldErrors).forEach(([key, msgs]) => {
            errors[key] = Array.isArray(msgs) ? msgs[0] : msgs;
        });
    }
}

async function load() {
    loading.value = true;
    try {
        const [usersRes, branchesRes] = await Promise.all([
            api.get('/users'),
            api.get('/branches'),
        ]);
        users.value = usersRes.data.data ?? [];
        branches.value = branchesRes.data.data ?? [];
    } catch (e) {
        users.value = [];
        toast.error(e?.response?.data?.message || 'Не удалось загрузить пользователей.');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    clearErrors();
    Object.assign(form, emptyForm());
    showModal.value = true;
}

function openEdit(user) {
    editingId.value = user.id;
    clearErrors();
    Object.assign(form, {
        name: user.name ?? '',
        email: user.email ?? '',
        password: '',
        role: user.role ?? 'viewer',
        branch_id: user.branch_id ?? '',
        is_active: !!user.is_active,
    });
    showModal.value = true;
}

async function save() {
    saving.value = true;
    clearErrors();
    const payload = {
        name: form.name,
        email: form.email,
        role: form.role,
        branch_id: form.branch_id || null,
        is_active: form.is_active,
    };
    if (form.password) {
        payload.password = form.password;
    }
    try {
        if (editingId.value) {
            await api.put(`/users/${editingId.value}`, payload);
        } else {
            await api.post('/users', payload);
        }
        showModal.value = false;
        toast.success(editingId.value ? 'Пользователь обновлён' : 'Пользователь создан');
        await load();
    } catch (e) {
        applyValidationErrors(e);
        toast.error(e?.response?.data?.message || 'Не удалось сохранить пользователя.');
    } finally {
        saving.value = false;
    }
}

async function remove(user) {
    if (
        !(await confirm({
            title: 'Удалить пользователя?',
            message: `Удалить пользователя «${user.name}»?`,
            confirmText: 'Удалить',
        }))
    )
        return;
    try {
        await api.delete(`/users/${user.id}`);
        toast.success('Пользователь удалён');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось удалить пользователя.');
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Пользователи" subtitle="Учётные записи панели управления">
            <template #actions>
                <Btn variant="secondary" @click="load">Обновить</Btn>
                <Btn variant="primary" @click="openCreate">+ Добавить пользователя</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" label="Загрузка…" />

            <EmptyState
                v-else-if="!users.length"
                icon="👤"
                title="Пока нет пользователей"
                hint="Добавьте первую учётную запись для доступа к панели управления."
            >
                <template #action>
                    <Btn @click="openCreate">+ Добавить пользователя</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">Имя</th>
                            <th class="py-2.5 px-3 font-medium">Email</th>
                            <th class="py-2.5 px-3 font-medium">Роль</th>
                            <th class="py-2.5 px-3 font-medium">Филиал</th>
                            <th class="py-2.5 px-3 font-medium">Статус</th>
                            <th class="py-2.5 px-3 font-medium text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-b border-slate-50 hover:bg-slate-50 transition"
                        >
                            <td class="py-2.5 px-3 text-slate-800 font-medium">{{ user.name }}</td>
                            <td class="py-2.5 px-3 text-slate-600">{{ user.email }}</td>
                            <td class="py-2.5 px-3">
                                <Badge :color="roleColor(user.role)">{{ roleLabel(user.role) }}</Badge>
                            </td>
                            <td class="py-2.5 px-3 text-slate-600">{{ branchName(user.branch_id) }}</td>
                            <td class="py-2.5 px-3">
                                <StatusDot :status="user.is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <Btn size="sm" variant="ghost" @click="openEdit(user)">Изменить</Btn>
                                <Btn size="sm" variant="danger" @click="remove(user)">🗑 Удалить</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal
            v-model="showModal"
            :title="editingId ? 'Редактировать пользователя' : 'Новый пользователь'"
        >
            <form class="space-y-4" @submit.prevent="save">
                <FormField label="Имя" required :error="errors.name">
                    <TextInput v-model="form.name" placeholder="Иван Иванов" />
                </FormField>

                <FormField label="Email" required :error="errors.email">
                    <TextInput v-model="form.email" type="email" placeholder="user@example.com" />
                </FormField>

                <FormField
                    label="Пароль"
                    :required="!editingId"
                    :error="errors.password"
                    :hint="editingId ? 'оставьте пустым чтобы не менять' : ''"
                >
                    <TextInput v-model="form.password" type="password" placeholder="••••••••" />
                </FormField>

                <div class="grid grid-cols-2 gap-4">
                    <FormField label="Роль" required :error="errors.role">
                        <SelectInput v-model="form.role" :options="roles" />
                    </FormField>

                    <FormField label="Филиал" :error="errors.branch_id">
                        <SelectInput v-model="form.branch_id" :options="branchOptions" />
                    </FormField>
                </div>

                <FormField label="Активен">
                    <Toggle v-model="form.is_active" />
                </FormField>
            </form>

            <template #footer>
                <Btn variant="secondary" @click="showModal = false">Отмена</Btn>
                <Btn variant="primary" :loading="saving" @click="save">Сохранить</Btn>
            </template>
        </Modal>
    </div>
</template>
