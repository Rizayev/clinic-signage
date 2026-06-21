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
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();
const { confirm } = useConfirm();

const users = ref([]);
const branches = ref([]);
const loading = ref(true);

const showModal = ref(false);
const saving = ref(false);
const editingId = ref(null);
const errors = reactive({});

const roles = computed(() => [
    { value: 'super_admin', label: t('roles.super_admin') },
    { value: 'branch_admin', label: t('roles.branch_admin') },
    { value: 'content_manager', label: t('roles.content_manager') },
    { value: 'viewer', label: t('roles.viewer') },
]);

const roleColors = {
    super_admin: 'violet',
    branch_admin: 'indigo',
    content_manager: 'blue',
    viewer: 'slate',
};

const branchOptions = computed(() => [
    { value: '', label: t('users.noBranchOption') },
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
    return roles.value.find((r) => r.value === role)?.label ?? role ?? '—';
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
        toast.error(e?.response?.data?.message || t('users.loadFailed'));
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
        toast.success(editingId.value ? t('users.updated') : t('users.created'));
        await load();
    } catch (e) {
        applyValidationErrors(e);
        toast.error(e?.response?.data?.message || t('users.saveFailed'));
    } finally {
        saving.value = false;
    }
}

async function remove(user) {
    if (
        !(await confirm({
            title: t('users.deleteTitle'),
            message: t('users.deleteMessage', { name: user.name }),
            confirmText: t('common.delete'),
        }))
    )
        return;
    try {
        await api.delete(`/users/${user.id}`);
        toast.success(t('users.deleted'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('users.deleteFailed'));
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader :title="$t('users.title')" :subtitle="$t('users.subtitle')">
            <template #actions>
                <Btn variant="secondary" @click="load">{{ $t('users.refresh') }}</Btn>
                <Btn variant="primary" @click="openCreate">+ {{ $t('users.addUser') }}</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" :label="$t('common.loading')" />

            <EmptyState
                v-else-if="!users.length"
                icon="👤"
                :title="$t('users.emptyTitle')"
                :hint="$t('users.emptyHint')"
            >
                <template #action>
                    <Btn @click="openCreate">+ {{ $t('users.addUser') }}</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">{{ $t('users.name') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('users.email') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('users.role') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('users.branch') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.status') }}</th>
                            <th class="py-2.5 px-3 font-medium text-right">{{ $t('common.actions') }}</th>
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
                                <Btn size="sm" variant="ghost" @click="openEdit(user)">{{ $t('common.edit') }}</Btn>
                                <Btn size="sm" variant="danger" @click="remove(user)">🗑 {{ $t('common.delete') }}</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal
            v-model="showModal"
            :title="editingId ? $t('users.editTitle') : $t('users.newTitle')"
        >
            <form class="space-y-4" @submit.prevent="save">
                <FormField :label="$t('users.name')" required :error="errors.name">
                    <TextInput v-model="form.name" :placeholder="$t('users.namePlaceholder')" />
                </FormField>

                <FormField :label="$t('users.email')" required :error="errors.email">
                    <TextInput v-model="form.email" type="email" placeholder="user@example.com" />
                </FormField>

                <FormField
                    :label="$t('users.password')"
                    :required="!editingId"
                    :error="errors.password"
                    :hint="editingId ? $t('users.passwordHint') : ''"
                >
                    <TextInput v-model="form.password" type="password" placeholder="••••••••" />
                </FormField>

                <div class="grid grid-cols-2 gap-4">
                    <FormField :label="$t('users.role')" required :error="errors.role">
                        <SelectInput v-model="form.role" :options="roles" />
                    </FormField>

                    <FormField :label="$t('users.branch')" :error="errors.branch_id">
                        <SelectInput v-model="form.branch_id" :options="branchOptions" />
                    </FormField>
                </div>

                <FormField :label="$t('common.active')">
                    <Toggle v-model="form.is_active" />
                </FormField>
            </form>

            <template #footer>
                <Btn variant="secondary" @click="showModal = false">{{ $t('common.cancel') }}</Btn>
                <Btn variant="primary" :loading="saving" @click="save">{{ $t('common.save') }}</Btn>
            </template>
        </Modal>
    </div>
</template>
