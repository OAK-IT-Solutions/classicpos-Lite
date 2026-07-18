<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import FormSlideOver from '@/Components/FormSlideOver.vue';
import api from '@/composables/axios';
import { Plus, Eye, X, CheckCircle } from 'lucide-vue-next';

interface Reconciliation {
    id: string;
    operating_account_id: string;
    operating_account: { name: string; account_number: string | null; bank_name: string | null } | null;
    statement_date: string;
    statement_balance: number;
    ledger_balance: number;
    difference: number;
    status: string;
    reconciled_at: string | null;
    notes: string | null;
    items: any[];
    created_at: string;
}

const items = ref<Reconciliation[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const showForm = ref(false);
const showDetail = ref(false);
const detailItem = ref<Reconciliation | null>(null);
const saving = ref(false);
const formError = ref('');
const opAccounts = ref<{ id: string; name: string }[]>([]);

const form = ref({
    operating_account_id: '',
    statement_date: new Date().toISOString().split('T')[0],
    statement_balance: 0,
    notes: '',
});

async function fetchOpAccounts() {
    try {
        const res = await api.get('/operating-accounts', { params: { per_page: 100, is_active: true } });
        opAccounts.value = (res.data.data ?? []).map((a: any) => ({ id: a.id, name: a.name }));
    } catch {}
}

async function fetchAll(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get('/bank-reconciliations', { params: { page, per_page: pagination.value.per_page } });
        items.value = res.data.data ?? [];
        pagination.value = {
            current_page: res.data.current_page ?? page,
            last_page: res.data.last_page ?? 1,
            per_page: res.data.per_page ?? 15,
            total: res.data.total ?? 0,
        };
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || err.message || 'Failed to load reconciliations.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    form.value = {
        operating_account_id: '',
        statement_date: new Date().toISOString().split('T')[0],
        statement_balance: 0,
        notes: '',
    };
    formError.value = '';
    showForm.value = true;
    fetchOpAccounts();
}

async function submit() {
    saving.value = true;
    formError.value = '';
    try {
        await api.post('/bank-reconciliations', form.value);
        showForm.value = false;
        await fetchAll();
    } catch (err: any) {
        formError.value = err.response?.data?.error?.message || err.message || 'Failed to create reconciliation.';
    } finally {
        saving.value = false;
    }
}

async function completeReconciliation(id: string) {
    if (!confirm('Mark this reconciliation as completed? This cannot be undone.')) return;
    try {
        await api.post(`/bank-reconciliations/${id}/complete`);
        await fetchAll();
        if (detailItem.value?.id === id) {
            detailItem.value.status = 'completed';
        }
    } catch {}
}

function viewDetail(item: Reconciliation) {
    detailItem.value = item;
    showDetail.value = true;
}

const statusBadge = (status: string) => {
    const map: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        completed: 'bg-success-light text-success-theme',
    };
    return map[status] || 'bg-surface-alt text-text-tertiary';
};

const columns = [
    { key: 'operating_account', label: 'Account' },
    { key: 'statement_date', label: 'Statement Date' },
    { key: 'statement_balance', label: 'Statement Balance' },
    { key: 'ledger_balance', label: 'Ledger Balance' },
    { key: 'difference', label: 'Difference' },
    { key: 'status', label: 'Status' },
];

onMounted(() => fetchAll());
</script>

<template>
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-text-theme">Bank Reconciliation</h1>
                <p class="text-sm text-text-tertiary">Match your bank statements against the general ledger</p>
            </div>
            <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                <Plus class="w-4 h-4" />
                New Reconciliation
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
            <template #cell-operating_account="{ item }">
                <span class="text-sm font-medium text-text-theme">{{ (item as any).operating_account?.name || 'N/A' }}</span>
            </template>
            <template #cell-statement_balance="{ item }">
                <span class="font-medium">{{ Number((item as any).statement_balance).toFixed(2) }}</span>
            </template>
            <template #cell-ledger_balance="{ item }">
                <span class="font-medium">{{ Number((item as any).ledger_balance).toFixed(2) }}</span>
            </template>
            <template #cell-difference="{ item }">
                <span class="font-medium" :class="Math.abs((item as any).difference) < 0.01 ? 'text-success-theme' : 'text-danger-theme'">
                    {{ Number((item as any).difference).toFixed(2) }}
                </span>
            </template>
            <template #cell-status="{ item }">
                <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize" :class="statusBadge((item as any).status)">{{ (item as any).status }}</span>
            </template>
            <template #actions="{ item }">
                <button @click="viewDetail(item as unknown as Reconciliation)" class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-md transition-colors" title="View Details">
                    <Eye class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

        <!-- Create Reconciliation -->
        <FormSlideOver :title="'New Bank Reconciliation'" :visible="showForm" :loading="saving" :error="formError" @close="showForm = false">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Operating Account <span class="text-danger-theme">*</span></label>
                    <select v-model="form.operating_account_id" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                        <option value="" disabled>Select account...</option>
                        <option v-for="a in opAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Statement Date <span class="text-danger-theme">*</span></label>
                        <input v-model="form.statement_date" type="date" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Statement Balance <span class="text-danger-theme">*</span></label>
                        <input v-model.number="form.statement_balance" type="number" required step="0.01" placeholder="0.00" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Notes</label>
                    <textarea v-model="form.notes" rows="2" placeholder="Optional notes" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showForm = false" class="px-4 py-2.5 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors">Cancel</button>
                    <button type="submit" :disabled="saving" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                        {{ saving ? 'Creating...' : 'Create' }}
                    </button>
                </div>
            </form>
        </FormSlideOver>

        <!-- Detail Slide-over -->
        <div v-if="showDetail && detailItem" class="fixed inset-0 z-50 flex justify-end">
            <div class="fixed inset-0 bg-surface-overlay" @click="showDetail = false"></div>
            <div class="relative w-full max-w-lg bg-surface-raised shadow-xl border-l border-border-theme overflow-y-auto">
                <div class="sticky top-0 bg-surface-raised border-b border-border-theme px-6 py-4 flex items-center justify-between z-10">
                    <h2 class="text-lg font-semibold text-text-theme">Reconciliation Details</h2>
                    <button @click="showDetail = false" class="p-1.5 rounded-lg hover:bg-surface-alt text-text-tertiary">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-text-tertiary uppercase tracking-wider">Account</p>
                            <p class="text-sm font-medium text-text-theme">{{ detailItem.operating_account?.name || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text-tertiary uppercase tracking-wider">Statement Date</p>
                            <p class="text-sm text-text-theme">{{ detailItem.statement_date }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 p-4 bg-surface-alt rounded-lg">
                        <div class="text-center">
                            <p class="text-xs text-text-tertiary uppercase tracking-wider">Statement</p>
                            <p class="text-lg font-bold text-text-theme">{{ Number(detailItem.statement_balance).toFixed(2) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-text-tertiary uppercase tracking-wider">Ledger</p>
                            <p class="text-lg font-bold text-text-theme">{{ Number(detailItem.ledger_balance).toFixed(2) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-text-tertiary uppercase tracking-wider">Difference</p>
                            <p class="text-lg font-bold" :class="Math.abs(detailItem.difference) < 0.01 ? 'text-success-theme' : 'text-danger-theme'">
                                {{ Number(detailItem.difference).toFixed(2) }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs text-text-tertiary uppercase tracking-wider mb-1">Status</p>
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize" :class="statusBadge(detailItem.status)">{{ detailItem.status }}</span>
                    </div>

                    <div v-if="detailItem.notes">
                        <p class="text-xs text-text-tertiary uppercase tracking-wider mb-1">Notes</p>
                        <p class="text-sm text-text-theme">{{ detailItem.notes }}</p>
                    </div>

                    <div v-if="detailItem.status !== 'completed'">
                        <button @click="completeReconciliation(detailItem.id)" class="flex items-center gap-2 px-4 py-2 bg-success-light text-success-theme text-sm font-medium rounded-lg hover:bg-success-light/80 transition-colors">
                            <CheckCircle class="w-4 h-4" />
                            Complete Reconciliation
                        </button>
                    </div>

                    <div v-if="detailItem.reconciled_at">
                        <p class="text-xs text-text-tertiary">Completed: {{ new Date(detailItem.reconciled_at).toLocaleString() }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
