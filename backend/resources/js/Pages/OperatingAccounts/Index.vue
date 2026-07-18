<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import FormSlideOver from '@/Components/FormSlideOver.vue';
import { useCrud } from '@/composables/useCrud';
import { Plus, Pencil } from 'lucide-vue-next';

interface OperatingAccount {
    id: string;
    branch_id: string;
    account_id: string;
    account: { code: string; name: string } | null;
    name: string;
    type: string;
    account_number: string | null;
    bank_name: string | null;
    currency: string;
    is_default: boolean;
    opening_balance: number;
    current_balance: number;
    is_system: boolean;
    is_active: boolean;
}

const { items, loading, error, pagination, fetchAll, create, update: updateRecord, destroy } = useCrud<OperatingAccount>('/operating-accounts', { searchFields: ['name', 'account_number', 'bank_name'] });

const showForm = ref(false);
const editingItem = ref<OperatingAccount | null>(null);
const saving = ref(false);
const formError = ref('');
const accountOptions = ref<{ id: string; code: string; name: string }[]>([]);

const form = ref({
    account_id: '',
    name: '',
    type: 'bank',
    account_number: '',
    bank_name: '',
    currency: 'KES',
    is_default: false,
    opening_balance: 0,
    is_active: true,
});

const accountTypes = [
    { value: 'bank', label: 'Bank Account' },
    { value: 'petty_cash', label: 'Petty Cash' },
    { value: 'cash', label: 'Cash Drawer' },
    { value: 'mobile_money', label: 'Mobile Money' },
];

const typeIcon = (type: string) => {
    const map: Record<string, string> = {
        bank: '🏦',
        petty_cash: '💰',
        cash: '💵',
        mobile_money: '📱',
    };
    return map[type] || '🏦';
};

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'type', label: 'Type' },
    { key: 'current_balance', label: 'Balance' },
    { key: 'currency', label: 'Currency' },
    { key: 'is_default', label: 'Default' },
    { key: 'is_active', label: 'Active' },
];

async function fetchAccounts() {
    try {
        const res = await api.get('/chart-of-accounts', { params: { per_page: 200, is_active: true } });
        accountOptions.value = (res.data.data ?? []).map((a: any) => ({ id: a.id, code: a.code, name: a.name }));
    } catch {}
}

import api from '@/composables/axios';

function openAdd() {
    editingItem.value = null;
    form.value = { account_id: '', name: '', type: 'bank', account_number: '', bank_name: '', currency: 'KES', is_default: false, opening_balance: 0, is_active: true };
    formError.value = '';
    showForm.value = true;
    fetchAccounts();
}

function openEdit(item: OperatingAccount) {
    editingItem.value = item;
    form.value = {
        account_id: item.account_id,
        name: item.name,
        type: item.type,
        account_number: item.account_number ?? '',
        bank_name: item.bank_name ?? '',
        currency: item.currency,
        is_default: item.is_default,
        opening_balance: item.opening_balance,
        is_active: item.is_active,
    };
    formError.value = '';
    showForm.value = true;
    fetchAccounts();
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
        formError.value = err.response?.data?.error?.message || err.message || 'Failed to save operating account.';
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(item: OperatingAccount) {
    if (item.is_system) {
        alert('System operating accounts cannot be deleted.');
        return;
    }
    if (!confirm(`Delete operating account "${item.name}"?`)) return;
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
                <h1 class="text-xl font-bold text-text-theme">Operating Accounts</h1>
                <p class="text-sm text-text-tertiary">Manage bank accounts, cash drawers, and mobile money wallets</p>
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
                <span class="flex items-center gap-1.5">
                    <span>{{ typeIcon((item as any).type) }}</span>
                    <span class="capitalize">{{ (item as any).type.replace('_', ' ') }}</span>
                </span>
            </template>
            <template #cell-current_balance="{ item }">
                <span class="font-medium" :class="(item as any).current_balance >= 0 ? 'text-success-theme' : 'text-danger-theme'">
                    {{ Number((item as any).current_balance).toFixed(2) }}
                </span>
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
                <button v-if="(item as any).is_system" disabled class="p-1.5 text-text-tertiary opacity-40 cursor-not-allowed rounded-md" title="System account">
                    <Pencil class="w-4 h-4" />
                </button>
                <button v-else @click="openEdit(item as unknown as OperatingAccount)" class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-md transition-colors" title="Edit">
                    <Pencil class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

        <FormSlideOver :title="editingItem ? 'Edit Operating Account' : 'Add Operating Account'" :visible="showForm" :loading="saving" :error="formError" @close="showForm = false">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Name <span class="text-danger-theme">*</span></label>
                    <input v-model="form.name" type="text" required placeholder="Main Bank Account" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Type <span class="text-danger-theme">*</span></label>
                        <select v-model="form.type" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option v-for="t in accountTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Currency</label>
                        <input v-model="form.currency" type="text" maxlength="3" placeholder="KES" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">GL Account <span class="text-danger-theme">*</span></label>
                    <select v-model="form.account_id" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                        <option value="" disabled>Select account...</option>
                        <option v-for="a in accountOptions" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Account Number</label>
                        <input v-model="form.account_number" type="text" placeholder="e.g. 1234567890" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Bank Name</label>
                        <input v-model="form.bank_name" type="text" placeholder="e.g. Equity Bank" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Opening Balance</label>
                        <input v-model.number="form.opening_balance" type="number" min="0" step="0.01" placeholder="0.00" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div class="flex items-end pb-2.5">
                        <label class="flex items-center gap-2 text-sm font-medium text-text-secondary">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-border-input" />
                            Default Account
                        </label>
                    </div>
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
