<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import FormSlideOver from '@/Components/FormSlideOver.vue';
import { useCrud } from '@/composables/useCrud';
import { Plus, Pencil } from 'lucide-vue-next';

interface ChartOfAccount {
    id: string;
    branch_id: string;
    code: string;
    name: string;
    type: string;
    group: string | null;
    normal_balance: string;
    description: string | null;
    is_system: boolean;
    is_active: boolean;
}

const { items, loading, error, pagination, fetchAll, create, update: updateRecord, destroy } = useCrud<ChartOfAccount>('/chart-of-accounts', { searchFields: ['code', 'name', 'description'] });

const showForm = ref(false);
const editingItem = ref<ChartOfAccount | null>(null);
const saving = ref(false);
const formError = ref('');

const form = ref({
    code: '',
    name: '',
    type: 'asset',
    group: '',
    normal_balance: 'debit',
    description: '',
    is_active: true,
});

const accountTypes = [
    { value: 'asset', label: 'Asset' },
    { value: 'liability', label: 'Liability' },
    { value: 'equity', label: 'Equity' },
    { value: 'revenue', label: 'Revenue' },
    { value: 'expense', label: 'Expense' },
];

const balanceTypes = [
    { value: 'debit', label: 'Debit' },
    { value: 'credit', label: 'Credit' },
];

const typeBadge = (type: string) => {
    const map: Record<string, string> = {
        asset: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        liability: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        equity: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        revenue: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        expense: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    };
    return map[type] || 'bg-surface-alt text-text-tertiary';
};

const columns = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Name' },
    { key: 'type', label: 'Type' },
    { key: 'normal_balance', label: 'Normal Balance' },
    { key: 'is_active', label: 'Status' },
];

const isSystem = (item: any) => (item as ChartOfAccount).is_system;

function openAdd() {
    editingItem.value = null;
    form.value = { code: '', name: '', type: 'asset', group: '', normal_balance: 'debit', description: '', is_active: true };
    formError.value = '';
    showForm.value = true;
}

function openEdit(item: ChartOfAccount) {
    editingItem.value = item;
    form.value = {
        code: item.code,
        name: item.name,
        type: item.type,
        group: item.group ?? '',
        normal_balance: item.normal_balance,
        description: item.description ?? '',
        is_active: item.is_active,
    };
    formError.value = '';
    showForm.value = true;
}

async function submit() {
    saving.value = true;
    formError.value = '';
    try {
        const payload = { ...form.value };
        if (editingItem.value) {
            await updateRecord(editingItem.value.id, payload);
        } else {
            await create(payload as any);
        }
        showForm.value = false;
        await fetchAll();
    } catch (err: any) {
        formError.value = err.response?.data?.error?.message || err.message || 'Failed to save account.';
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(item: ChartOfAccount) {
    if (item.is_system) {
        alert('System accounts cannot be deleted.');
        return;
    }
    if (!confirm(`Delete account "${item.code} - ${item.name}"?`)) return;
    try {
        await destroy(item.id);
        await fetchAll();
    } catch {}
}

onMounted(() => fetchAll());
</script>

<template>
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-text-theme">Chart of Accounts</h1>
                <p class="text-sm text-text-tertiary">Manage your general ledger accounts</p>
            </div>
            <button @click="openAdd" class="flex items-center gap-2 px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                <Plus class="w-4 h-4" />
                Add Account
            </button>
        </div>

        <DataTable
            :columns="columns"
            :items="items as unknown as Record<string, unknown>[]"
            :loading="loading"
            :error="error"
            :pagination="pagination"
            @page-change="(p: number) => fetchAll(p)"
        >
            <template #cell-type="{ item }">
                <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize" :class="typeBadge((item as any).type)">{{ (item as any).type }}</span>
            </template>
            <template #cell-normal_balance="{ item }">
                <span class="capitalize">{{ (item as any).normal_balance }}</span>
            </template>
            <template #cell-is_active="{ item }">
                <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="(item as any).is_active ? 'bg-success-light text-success-theme' : 'bg-surface-alt text-text-tertiary'">
                    {{ (item as any).is_active ? 'Active' : 'Inactive' }}
                </span>
            </template>
            <template #actions="{ item }">
                <button v-if="(item as any).is_system" disabled class="p-1.5 text-text-tertiary opacity-40 cursor-not-allowed rounded-md" title="System account - limited editing">
                    <Pencil class="w-4 h-4" />
                </button>
                <button v-else @click="openEdit(item as unknown as ChartOfAccount)" class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-md transition-colors" title="Edit">
                    <Pencil class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

        <FormSlideOver :title="editingItem ? 'Edit Account' : 'Add Account'" :visible="showForm" :loading="saving" :error="formError" @close="showForm = false">
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Code <span class="text-danger-theme">*</span></label>
                        <input v-model="form.code" :disabled="editingItem?.is_system" type="text" required placeholder="1100" maxlength="20" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Type <span class="text-danger-theme">*</span></label>
                        <select v-model="form.type" :disabled="editingItem?.is_system" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option v-for="t in accountTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Name <span class="text-danger-theme">*</span></label>
                    <input v-model="form.name" :disabled="editingItem?.is_system" type="text" required placeholder="Cash on Hand" maxlength="200" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Group</label>
                        <input v-model="form.group" type="text" placeholder="current_asset" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Normal Balance <span class="text-danger-theme">*</span></label>
                        <select v-model="form.normal_balance" :disabled="editingItem?.is_system" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option v-for="b in balanceTypes" :key="b.value" :value="b.value">{{ b.label }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Description</label>
                    <textarea v-model="form.description" rows="2" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"></textarea>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-text-secondary">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-border-input" />
                        Active
                    </label>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showForm = false" class="px-4 py-2.5 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors">Cancel</button>
                    <button type="submit" :disabled="saving" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                        {{ saving ? 'Saving...' : editingItem ? 'Update' : 'Create' }}
                    </button>
                </div>
            </form>
        </FormSlideOver>
    </AppLayout>
</template>
