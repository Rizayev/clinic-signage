<script setup>
import { ref, reactive, onMounted } from 'vue';
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

const toast = useToast();
const { confirm } = useConfirm();

const branches = ref([]);
const loading = ref(true);

const modalOpen = ref(false);
const saving = ref(false);
const editingId = ref(null);
const errors = reactive({});

const statusOptions = [
    { value: 'active', label: 'Активен' },
    { value: 'disabled', label: 'Отключён' },
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
        toast.error(e?.response?.data?.message || 'Не удалось загрузить филиалы.');
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
        toast.success(editingId.value ? 'Филиал обновлён' : 'Филиал создан');
        await load();
    } catch (e) {
        if (e?.response?.status === 422 && e.response.data?.errors) {
            Object.entries(e.response.data.errors).forEach(([k, v]) => {
                errors[k] = Array.isArray(v) ? v[0] : v;
            });
        }
        toast.error(e?.response?.data?.message || 'Не удалось сохранить филиал.');
    } finally {
        saving.value = false;
    }
}

async function remove(branch) {
    if (!(await confirm({
        title: 'Удалить филиал?',
        message: `Удалить филиал «${branch.name}»?`,
        confirmText: 'Удалить',
    }))) return;
    try {
        await api.delete(`/branches/${branch.id}`);
        toast.success('Филиал удалён');
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Не удалось удалить филиал.');
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Филиалы" subtitle="Управление филиалами клиники">
            <template #actions>
                <Btn variant="secondary" @click="load">Обновить</Btn>
                <Btn variant="primary" @click="openCreate">+ Добавить</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" label="Загрузка…" center />

            <EmptyState
                v-else-if="!branches.length"
                icon="🏢"
                title="Пока нет филиалов"
                hint="Создайте первый филиал, чтобы начать управление зонами и устройствами."
            >
                <template #action>
                    <Btn variant="primary" @click="openCreate">+ Добавить</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">Название</th>
                            <th class="py-2.5 px-3 font-medium">Адрес</th>
                            <th class="py-2.5 px-3 font-medium">Часовой пояс</th>
                            <th class="py-2.5 px-3 font-medium">Статус</th>
                            <th class="py-2.5 px-3 font-medium">Зоны</th>
                            <th class="py-2.5 px-3 font-medium">Устройства</th>
                            <th class="py-2.5 px-3 font-medium text-right">Действия</th>
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
                                <Btn size="sm" variant="ghost" @click="openEdit(branch)">Изменить</Btn>
                                <Btn size="sm" variant="danger" @click="remove(branch)">🗑 Удалить</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal v-model="modalOpen" :title="editingId ? 'Редактировать филиал' : 'Новый филиал'">
            <form class="space-y-4" @submit.prevent="save">
                <FormField label="Название" required :error="errors.name">
                    <TextInput v-model="form.name" placeholder="Название филиала" />
                </FormField>

                <FormField label="Адрес" :error="errors.address">
                    <TextInput v-model="form.address" placeholder="Адрес" />
                </FormField>

                <FormField label="Часовой пояс" :error="errors.timezone">
                    <TextInput v-model="form.timezone" placeholder="Asia/Baku" />
                </FormField>

                <FormField label="Статус" :error="errors.status">
                    <SelectInput v-model="form.status" :options="statusOptions" />
                </FormField>
            </form>

            <template #footer>
                <Btn variant="secondary" @click="modalOpen = false">Отмена</Btn>
                <Btn variant="primary" :loading="saving" @click="save">Сохранить</Btn>
            </template>
        </Modal>
    </div>
</template>
