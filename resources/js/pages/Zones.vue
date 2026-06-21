<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import PageHeader from '@/components/ui/PageHeader.vue';
import Card from '@/components/ui/Card.vue';
import Btn from '@/components/ui/Btn.vue';
import Modal from '@/components/ui/Modal.vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

const toast = useToast();
const { confirm } = useConfirm();
const { t } = useI18n();

const zones = ref([]);
const branches = ref([]);
const loading = ref(true);

const branchFilter = ref('');

const modalOpen = ref(false);
const saving = ref(false);
const editingId = ref(null);
const errors = reactive({});

const blankForm = () => ({
    branch_id: '',
    name: '',
    description: '',
    sort_order: 0,
});

const form = reactive(blankForm());

const branchOptions = computed(() =>
    branches.value.map((b) => ({ value: b.id, label: b.name }))
);

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

function branchName(zone) {
    if (zone.branch?.name) return zone.branch.name;
    const b = branches.value.find((x) => x.id === zone.branch_id);
    return b?.name ?? '—';
}

async function loadBranches() {
    try {
        const { data } = await api.get('/branches');
        branches.value = data.data ?? [];
    } catch (e) {
        branches.value = [];
        toast.error(e?.response?.data?.message || t('zones.loadBranchesError'));
    }
}

async function load() {
    loading.value = true;
    try {
        const params = {};
        if (branchFilter.value) params.branch_id = branchFilter.value;
        const { data } = await api.get('/zones', { params });
        zones.value = data.data ?? [];
    } catch (e) {
        zones.value = [];
        toast.error(e?.response?.data?.message || t('zones.loadError'));
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    clearErrors();
    Object.assign(form, blankForm());
    if (branchFilter.value) form.branch_id = branchFilter.value;
    modalOpen.value = true;
}

function openEdit(zone) {
    editingId.value = zone.id;
    clearErrors();
    Object.assign(form, {
        branch_id: zone.branch_id ?? '',
        name: zone.name ?? '',
        description: zone.description ?? '',
        sort_order: zone.sort_order ?? 0,
    });
    modalOpen.value = true;
}

async function save() {
    saving.value = true;
    clearErrors();
    try {
        const payload = {
            branch_id: form.branch_id,
            name: form.name,
            description: form.description,
            sort_order: Number(form.sort_order) || 0,
        };
        if (editingId.value) {
            await api.put(`/zones/${editingId.value}`, payload);
        } else {
            await api.post('/zones', payload);
        }
        modalOpen.value = false;
        toast.success(editingId.value ? t('zones.updated') : t('zones.created'));
        await load();
    } catch (e) {
        applyValidationErrors(e);
        toast.error(e?.response?.data?.message || t('zones.saveError'));
    } finally {
        saving.value = false;
    }
}

async function remove(zone) {
    if (
        !(await confirm({
            title: t('zones.deleteConfirmTitle'),
            message: t('zones.deleteConfirmMessage', { name: zone.name }),
            confirmText: t('common.delete'),
        }))
    )
        return;
    try {
        await api.delete(`/zones/${zone.id}`);
        toast.success(t('zones.deleted'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('zones.deleteError'));
    }
}

onMounted(async () => {
    await loadBranches();
    await load();
});
</script>

<template>
    <div>
        <PageHeader :title="$t('zones.title')" :subtitle="$t('zones.subtitle')">
            <template #actions>
                <Btn variant="secondary" @click="load">{{ $t('zones.refresh') }}</Btn>
                <Btn variant="primary" @click="openCreate">+ {{ $t('zones.addZone') }}</Btn>
            </template>
        </PageHeader>

        <Card class="mb-4">
            <div class="flex items-end gap-3">
                <FormField :label="$t('zones.branch')" class="w-64">
                    <SelectInput
                        v-model="branchFilter"
                        :options="branchOptions"
                        :placeholder="$t('zones.allBranches')"
                        @update:modelValue="load"
                    />
                </FormField>
            </div>
        </Card>

        <Card>
            <Spinner v-if="loading" :label="$t('common.loading')" />

            <EmptyState
                v-else-if="!zones.length"
                icon="🗺"
                :title="$t('zones.emptyTitle')"
                :hint="$t('zones.emptyHint')"
            >
                <template #action>
                    <Btn variant="primary" @click="openCreate">+ {{ $t('zones.addZone') }}</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.name') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('zones.branch') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.description') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('zones.order') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('zones.devices') }}</th>
                            <th class="py-2.5 px-3 font-medium text-right">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="zone in zones"
                            :key="zone.id"
                            class="border-b border-slate-50 hover:bg-slate-50 transition"
                        >
                            <td class="py-2.5 px-3 text-slate-800 font-medium">{{ zone.name }}</td>
                            <td class="py-2.5 px-3 text-slate-600">{{ branchName(zone) }}</td>
                            <td class="py-2.5 px-3 text-slate-600">
                                <span
                                    class="block truncate max-w-[18rem]"
                                    :title="zone.description || ''"
                                >
                                    {{ zone.description || '—' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-slate-600">{{ zone.sort_order ?? 0 }}</td>
                            <td class="py-2.5 px-3">
                                <Badge color="indigo">{{ zone.devices_count ?? 0 }}</Badge>
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <Btn size="sm" variant="ghost" @click="openEdit(zone)">{{ $t('common.edit') }}</Btn>
                                <Btn size="sm" variant="danger" @click="remove(zone)">🗑 {{ $t('common.delete') }}</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal v-model="modalOpen" :title="editingId ? $t('zones.editTitle') : $t('zones.newTitle')">
            <form class="space-y-4" @submit.prevent="save">
                <FormField :label="$t('zones.branch')" required :error="errors.branch_id">
                    <SelectInput
                        v-model="form.branch_id"
                        :options="branchOptions"
                        :placeholder="$t('zones.selectBranch')"
                    />
                </FormField>

                <FormField :label="$t('common.name')" required :error="errors.name">
                    <TextInput v-model="form.name" :placeholder="$t('zones.namePlaceholder')" />
                </FormField>

                <FormField :label="$t('common.description')" :error="errors.description">
                    <TextInput v-model="form.description" :placeholder="$t('zones.descriptionPlaceholder')" />
                </FormField>

                <FormField :label="$t('zones.sortOrder')" :error="errors.sort_order">
                    <TextInput v-model="form.sort_order" type="number" />
                </FormField>
            </form>

            <template #footer>
                <Btn variant="secondary" @click="modalOpen = false">{{ $t('common.cancel') }}</Btn>
                <Btn variant="primary" :loading="saving" @click="save">{{ $t('common.save') }}</Btn>
            </template>
        </Modal>
    </div>
</template>
