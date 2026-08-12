<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import DataTable from '@/Components/DataTable.vue';
import FormSlideOver from '@/Components/FormSlideOver.vue';
import { useCrud } from '@/composables/useCrud';

defineProps<{ embedded?: boolean }>();

interface TaxProfile {
    id: string;
    name: string;
    rate: number;
    type: 'inclusive' | 'exclusive';
    is_default: boolean;
    is_active: boolean;
    description: string | null;
    created_at: string;
}

const {
    items, loading, error, pagination,
    fetchAll, create, update: updateRecord, destroy
} = useCrud<TaxProfile>('/tax-profiles', { searchFields: ['name', 'description'] });

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'rate', label: 'Rate' },
    { key: 'type', label: 'Type' },
    { key: 'is_default', label: 'Default' },
    { key: 'is_active', label: 'Active' },
];

const showForm = ref(false);
const editing = ref<TaxProfile | null>(null);
const saving = ref(false);
const formError = ref('');

const form = ref({
    name: '',
    rate: 0,
    type: 'exclusive' as 'inclusive' | 'exclusive',
    is_default: false,
    is_active: true,
    description: '',
});

function openCreate() {
    editing.value = null;
    form.value = { name: '', rate: 0, type: 'exclusive', is_default: false, is_active: true, description: '' };
    formError.value = '';
    showForm.value = true;
}

function openEdit(profile: TaxProfile) {
    editing.value = profile;
    form.value = {
        name: profile.name,
        rate: profile.rate,
        type: profile.type,
        is_default: profile.is_default,
        is_active: profile.is_active,
        description: profile.description ?? '',
    };
    formError.value = '';
    showForm.value = true;
}

async function save() {
    saving.value = true;
    formError.value = '';
    try {
        const payload = { ...form.value };
        if (editing.value) {
            await updateRecord(editing.value.id, payload);
        } else {
            await create(payload as any);
        }
        showForm.value = false;
        await fetchAll();
    } catch (err: any) {
        formError.value = err.response?.data?.error?.message || err.message || 'Failed to save tax profile.';
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(profile: TaxProfile) {
    if (!confirm(`Delete tax profile "${profile.name}"?`)) return;
    try {
        await destroy(profile.id);
        await fetchAll();
    } catch {}
}

function pageChange(page: number) {
    fetchAll(page);
}

onMounted(() => fetchAll());
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <SettingsNav v-if="!embedded" />
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-text-tertiary">Configure tax rates for your business</p>
                <button @click="openCreate" class="px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover transition-colors">
                    Add Tax Profile
                </button>
            </div>
            <DataTable
                :columns="columns"
                :items="items as unknown as Record<string, unknown>[]"
                :loading="loading"
                :error="error"
                :pagination="pagination"
                @page-change="pageChange"
            >
                <template #cell-rate="{ item }">
                    {{ (item as any).rate }}%
                </template>
                <template #cell-type="{ item }">
                    <span class="capitalize">{{ (item as any).type }}</span>
                </template>
                <template #cell-is_default="{ item }">
                    <span v-if="(item as any).is_default" class="px-2 py-0.5 text-xs font-medium rounded-full bg-primary-light text-primary">Default</span>
                    <span v-else class="text-text-tertiary">&mdash;</span>
                </template>
                <template #cell-is_active="{ item }">
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="(item as any).is_active ? 'bg-success-light text-success-theme' : 'bg-surface-alt text-text-tertiary'">
                        {{ (item as any).is_active ? 'Active' : 'Inactive' }}
                    </span>
                </template>
                <template #actions="{ item }">
                    <button @click="openEdit(item as any)" class="text-sm text-primary hover:text-primary mr-3">Edit</button>
                    <button @click="confirmDelete(item as any)" class="text-sm text-danger-theme hover:text-danger-theme">Delete</button>
                </template>
            </DataTable>
        <FormSlideOver :visible="showForm" :title="editing ? 'Edit Tax Profile' : 'Add Tax Profile'" @close="showForm = false">
            <form @submit.prevent="save" class="space-y-4">
                <div v-if="formError" class="p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ formError }}</div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Name</label>
                    <input v-model="form.name" required maxlength="100" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" placeholder="VAT 16%">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Rate (%)</label>
                        <input v-model.number="form.rate" required type="number" min="0" max="100" step="0.01" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Type</label>
                        <select v-model="form.type" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option value="exclusive">Exclusive (added to subtotal)</option>
                            <option value="inclusive">Inclusive (included in price)</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-text-secondary">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-border-input">
                            Default Tax Profile
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-text-secondary">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-border-input">
                            Active
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Description</label>
                    <textarea v-model="form.description" rows="2" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showForm = false" class="px-4 py-2.5 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors">Cancel</button>
                    <button type="submit" :disabled="saving" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                        {{ saving ? 'Saving...' : editing ? 'Update' : 'Create' }}
                    </button>
                </div>
            </form>
        </FormSlideOver>
    </component>
</template>
