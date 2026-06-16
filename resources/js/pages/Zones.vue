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
import EmptyState from '@/components/ui/EmptyState.vue';
import Spinner from '@/components/ui/Spinner.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

const toast = useToast();
const { confirm } = useConfirm();

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
        toast.error(e?.response?.data?.message || 'Не удалось загрузить филиалы.');
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
        toast.error(e?.response?.data?.message || 'Не удалось загрузить зоны.');
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
        toast.success(editingId.value ? 'Зона обновлена' : 'Зона создана');
        await load();
    } catch (e) {
        applyValidationErrors(e);
        toast.error(e?.response?.data?.message || 'Не удалось сохранить зону.');
    } finally {
        saving.value = false;
    }
}

async function remove(zone) {
    if (
        !(await confirm({
            title: 'Удалить зону?',
            message: `Удалить зону «${zone.name}»?`,
            confirmText: 'Удалить',
        }))
    )
        return;
    try {
        await api.delete(`/zones/${zone.id}`);
        toast.success('Зона удалена');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось удалить зону.');
    }
}

onMounted(async () => {
    await loadBranches();
    await load();
});
</script>

<template>
    <div>
        <PageHeader title="Зоны" subtitle="Управление зонами в филиалах">
            <template #actions>
                <Btn variant="secondary" @click="load">Обновить</Btn>
                <Btn variant="primary" @click="openCreate">+ Добавить зону</Btn>
            </template>
        </PageHeader>

        <Card class="mb-4">
            <div class="flex items-end gap-3">
                <FormField label="Филиал" class="w-64">
                    <SelectInput
                        v-model="branchFilter"
                        :options="branchOptions"
                        placeholder="Все филиалы"
                        @update:modelValue="load"
                    />
                </FormField>
            </div>
        </Card>

        <Card>
            <Spinner v-if="loading" label="Загрузка…" />

            <EmptyState
                v-else-if="!zones.length"
                icon="🗺"
                title="Пока нет зон"
                hint="Создайте первую зону, чтобы сгруппировать устройства внутри филиала."
            >
                <template #action>
                    <Btn variant="primary" @click="openCreate">+ Добавить зону</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">Название</th>
                            <th class="py-2.5 px-3 font-medium">Филиал</th>
                            <th class="py-2.5 px-3 font-medium">Описание</th>
                            <th class="py-2.5 px-3 font-medium">Порядок</th>
                            <th class="py-2.5 px-3 font-medium">Устройства</th>
                            <th class="py-2.5 px-3 font-medium text-right">Действия</th>
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
                                <Btn size="sm" variant="ghost" @click="openEdit(zone)">Изменить</Btn>
                                <Btn size="sm" variant="danger" @click="remove(zone)">🗑 Удалить</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal v-model="modalOpen" :title="editingId ? 'Изменить зону' : 'Новая зона'">
            <form class="space-y-4" @submit.prevent="save">
                <FormField label="Филиал" required :error="errors.branch_id">
                    <SelectInput
                        v-model="form.branch_id"
                        :options="branchOptions"
                        placeholder="Выберите филиал"
                    />
                </FormField>

                <FormField label="Название" required :error="errors.name">
                    <TextInput v-model="form.name" placeholder="Например, Регистратура" />
                </FormField>

                <FormField label="Описание" :error="errors.description">
                    <TextInput v-model="form.description" placeholder="Краткое описание зоны" />
                </FormField>

                <FormField label="Порядок сортировки" :error="errors.sort_order">
                    <TextInput v-model="form.sort_order" type="number" />
                </FormField>
            </form>

            <template #footer>
                <Btn variant="secondary" @click="modalOpen = false">Отмена</Btn>
                <Btn variant="primary" :loading="saving" @click="save">Сохранить</Btn>
            </template>
        </Modal>
    </div>
</template>
