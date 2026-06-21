<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
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

const router = useRouter();
const toast = useToast();
const { confirm } = useConfirm();
const { t } = useI18n();

const playlists = ref([]);
const branches = ref([]);
const loading = ref(true);

const showCreate = ref(false);
const saving = ref(false);
const form = ref({ name: '', description: '', branch_id: '' });
const errors = ref({});

function itemsCount(p) {
    return p.items_count ?? p.items?.length ?? 0;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/playlists');
        playlists.value = data.data ?? data;
    } catch (e) {
        toast.error(e?.response?.data?.message || t('playlists.loadError'));
    } finally {
        loading.value = false;
    }
}

async function loadBranches() {
    try {
        const { data } = await api.get('/branches');
        branches.value = data.data ?? data;
    } catch (e) {
        branches.value = [];
    }
}

function openCreate() {
    form.value = { name: '', description: '', branch_id: '' };
    errors.value = {};
    showCreate.value = true;
}

async function create() {
    errors.value = {};
    if (!form.value.name.trim()) {
        errors.value.name = t('playlists.nameRequired');
        return;
    }
    saving.value = true;
    try {
        const payload = {
            name: form.value.name,
            description: form.value.description || null,
        };
        if (form.value.branch_id) {
            payload.branch_id = form.value.branch_id;
        }
        const { data } = await api.post('/playlists', payload);
        const created = data.data ?? data;
        showCreate.value = false;
        toast.success(t('playlists.created'));
        if (created?.id) {
            router.push(`/playlists/${created.id}`);
        } else {
            await load();
        }
    } catch (e) {
        if (e?.response?.status === 422 && e.response.data?.errors) {
            const mapped = {};
            for (const [k, v] of Object.entries(e.response.data.errors)) {
                mapped[k] = Array.isArray(v) ? v[0] : v;
            }
            errors.value = mapped;
        }
        toast.error(e?.response?.data?.message || t('playlists.createError'));
    } finally {
        saving.value = false;
    }
}

async function remove(playlist) {
    if (!(await confirm({
        title: t('playlists.deleteTitle'),
        message: t('playlists.deleteMessage', { name: playlist.name }),
        confirmText: t('common.delete'),
    }))) return;
    try {
        await api.delete(`/playlists/${playlist.id}`);
        toast.success(t('playlists.deleted'));
        await load();
    } catch (e) {
        toast.error(e?.response?.data?.message || t('playlists.deleteError'));
    }
}

const branchOptions = () =>
    branches.value.map((b) => ({ value: b.id, label: b.name }));

onMounted(() => {
    load();
    loadBranches();
});
</script>

<template>
    <div>
        <PageHeader :title="$t('playlists.title')" :subtitle="$t('playlists.subtitle')">
            <template #actions>
                <Btn variant="secondary" @click="load">{{ $t('playlists.refresh') }}</Btn>
                <Btn variant="primary" @click="openCreate">{{ $t('playlists.createPlaylist') }}</Btn>
            </template>
        </PageHeader>

        <Card>
            <Spinner v-if="loading" :label="$t('common.loading')" center />

            <EmptyState
                v-else-if="!playlists.length"
                icon="🎬"
                :title="$t('playlists.emptyTitle')"
                :hint="$t('playlists.emptyHint')"
            >
                <template #action>
                    <Btn variant="primary" @click="openCreate">{{ $t('playlists.createPlaylist') }}</Btn>
                </template>
            </EmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.name') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('playlists.items') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('common.status') }}</th>
                            <th class="py-2.5 px-3 font-medium">{{ $t('playlists.version') }}</th>
                            <th class="py-2.5 px-3 font-medium text-right">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in playlists"
                            :key="p.id"
                            class="border-b border-slate-50 hover:bg-slate-50 transition"
                        >
                            <td class="py-2.5 px-3">
                                <RouterLink
                                    :to="`/playlists/${p.id}`"
                                    class="font-medium text-indigo-600 hover:text-indigo-700"
                                >
                                    {{ p.name }}
                                </RouterLink>
                                <p
                                    v-if="p.description"
                                    class="text-xs text-slate-400 truncate max-w-[28rem]"
                                    :title="p.description"
                                >
                                    {{ p.description }}
                                </p>
                            </td>
                            <td class="py-2.5 px-3 text-slate-700">{{ itemsCount(p) }}</td>
                            <td class="py-2.5 px-3">
                                <StatusDot :status="p.status" />
                            </td>
                            <td class="py-2.5 px-3">
                                <Badge color="slate">v{{ p.version ?? 1 }}</Badge>
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                <RouterLink :to="`/playlists/${p.id}`" class="inline-block mr-2">
                                    <Btn variant="secondary" size="sm">{{ $t('common.edit') }}</Btn>
                                </RouterLink>
                                <Btn variant="danger" size="sm" @click="remove(p)">🗑 {{ $t('common.delete') }}</Btn>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal v-model="showCreate" :title="$t('playlists.createTitle')">
            <form @submit.prevent="create" class="space-y-4">
                <FormField :label="$t('common.name')" required :error="errors.name">
                    <TextInput v-model="form.name" :placeholder="$t('playlists.namePlaceholder')" />
                </FormField>
                <FormField :label="$t('common.description')" :error="errors.description">
                    <TextInput v-model="form.description" :placeholder="$t('common.optional')" />
                </FormField>
                <FormField :label="$t('playlists.branch')" :error="errors.branch_id">
                    <SelectInput
                        v-model="form.branch_id"
                        :options="branchOptions()"
                        :placeholder="$t('playlists.allBranches')"
                    />
                </FormField>
            </form>
            <template #footer>
                <Btn variant="secondary" @click="showCreate = false">{{ $t('common.cancel') }}</Btn>
                <Btn variant="primary" :loading="saving" @click="create">{{ $t('common.create') }}</Btn>
            </template>
        </Modal>
    </div>
</template>
