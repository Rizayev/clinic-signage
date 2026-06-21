<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Modal from '@/components/ui/Modal.vue';
import StatusDot from '@/components/ui/StatusDot.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

const { t } = useI18n();
const toast = useToast();
const { confirm } = useConfirm();

const branches = ref([]);
const loading = ref(true);

const modalOpen = ref(false);
const saving = ref(false);
const editingId = ref(null);
const errors = reactive({});

const statusOptions = [
    { value: 'active', label: t('common.active') },
    { value: 'disabled', label: t('branches.statusDisabled') },
];

const blankForm = () => ({
    name: '',
    address: '',
    timezone: 'Asia/Baku',
    status: 'active',
});

const form = reactive(blankForm());

function clearErrors() {
    Object.keys(errors).forEach((k) => delete errors[k]);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/branches');
        branches.value = data.data ?? [];
    } catch (e) {
        toast.error(e?.response?.data?.message || t('branches.loadError'));
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    clearErrors();
    Object.assign(form, blankForm());
    modalOpen.value = true;
}

function openEdit(branch) {
    editingId.value = branch.id;
    clearErrors();
    Object.assign(form, {
        name: branch.name ?? '',
        address: branch.address ?? '',
        timezone: branch.timezone ?? 'Asia/Baku',
        status: branch.status ?? 'active',
    });
    modalOpen.value = true;
}

async function save() {
    saving.value = true;
    clearErrors();
    try {
        const payload = {
            name: form.name,
            address: form.address,
            timezone: form.timezone,
            status: form.status,
        };
        if (editingId.value) {
            await api.put(`/branches/${editingId.value}`, payload);
        } else {
            await api.post('/branches', payload);
        }
        modalOpen.value = false;
        toast.success(editingId.value ? t('branches.updated') : t('branches.created'));
        await load();
    } catch (e) {
        if (e?.response?.status === 422 && e.response.data?.errors) {
            Object.entries(e.response.data.errors).forEach(([k, v]) => {
                errors[k] = Array.isArray(v) ? v[0] : v;
            });
        }
        toast.error(e?.response?.data?.message || t('branches.saveError'));
    } finally {
        saving.value = false;
    }
}

async function remove(branch) {
    if (!(await confirm({
        title: t('branches.deleteTitle'),
        message: t('branches.deleteMessage', { name: branch.name }),
        confirmText: t('common.delete'),
    }))) return;
    try {
        await api.delete(`/branches/${branch.id}`);
        toast.success(t('branches.deleted'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('branches.deleteError'));
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader :title="$t('branches.title')" :subtitle="$t('branches.subtitle')">
            <template #actions>
                <Btn variant="secondary" @click="load">{{ $t('branches.refresh') }}</Btn>
                <Btn variant="primary" @click="openCreate">{{ $t('branches.add') }}</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" :label="$t('common.loading')" center />

            <EmptyState
                v-else-if="!branches.length"
                icon="🏢"
                :title="$t('branches.emptyTitle')"
                :hint="$t('branches.emptyHint')"
            >
                <template #action>
                    <Btn variant="primary" @click="openCreate">{{ $t('branches.add') }}</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.name') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('branches.address') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('branches.timezone') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.status') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('branches.zones') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('branches.devices') }}</th>
                            <th class="py-2.5 px-3 font-medium text-right">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="branch in branches"
                            :key="branch.id"
                            class="border-b border-slate-50 hover:bg-slate-50 transition"
                        >
                            <td class="py-2.5 px-3 text-slate-800 font-medium">{{ branch.name }}</td>
                            <td class="py-2.5 px-3 text-slate-600">
                                <span class="block truncate max-w-[220px]" :title="branch.address || ''">
                                    {{ branch.address || '—' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-slate-600">{{ branch.timezone || '—' }}</td>
                            <td class="py-2.5 px-3">
                                <StatusDot :status="branch.status" />
                            </td>
                            <td class="py-2.5 px-3 text-slate-600">{{ branch.zones_count ?? 0 }}</td>
                            <td class="py-2.5 px-3 text-slate-600">{{ branch.devices_count ?? 0 }}</td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <Btn size="sm" variant="ghost" @click="openEdit(branch)">{{ $t('common.edit') }}</Btn>
                                <Btn size="sm" variant="danger" @click="remove(branch)">🗑 {{ $t('common.delete') }}</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal v-model="modalOpen" :title="editingId ? $t('branches.editTitle') : $t('branches.createTitle')">
            <form class="space-y-4" @submit.prevent="save">
                <FormField :label="$t('common.name')" required :error="errors.name">
                    <TextInput v-model="form.name" :placeholder="$t('branches.namePlaceholder')" />
                </FormField>

                <FormField :label="$t('branches.address')" :error="errors.address">
                    <TextInput v-model="form.address" :placeholder="$t('branches.address')" />
                </FormField>

                <FormField :label="$t('branches.timezone')" :error="errors.timezone">
                    <TextInput v-model="form.timezone" placeholder="Asia/Baku" />
                </FormField>

                <FormField :label="$t('common.status')" :error="errors.status">
                    <SelectInput v-model="form.status" :options="statusOptions" />
                </FormField>
            </form>

            <template #footer>
                <Btn variant="secondary" @click="modalOpen = false">{{ $t('common.cancel') }}</Btn>
                <Btn variant="primary" :loading="saving" @click="save">{{ $t('common.save') }}</Btn>
            </template>
        </Modal>
    </div>
</template>
