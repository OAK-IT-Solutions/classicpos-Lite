<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import FormSlideOver from '@/Components/FormSlideOver.vue';
import { useAuth } from '@/composables/useAuth';
import api from '@/composables/axios';
import { Plus, Eye, X } from 'lucide-vue-next';

const auth = useAuth();

interface JournalEntryLine {
    id: string;
    account_id: string;
    account: { code: string; name: string } | null;
    debit_amount: number;
    credit_amount: number;
    description: string | null;
}

interface JournalEntry {
    id: string;
    entry_number: string;
    entry_date: string;
    description: string;
    reference_type: string | null;
    reference_id: string | null;
    lines: JournalEntryLine[];
    created_by: string | null;
    created_at: string;
}

const items = ref<JournalEntry[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const showForm = ref(false);
const showDetail = ref(false);
const detailEntry = ref<JournalEntry | null>(null);
const saving = ref(false);
const formError = ref('');

const form = ref({
    entry_date: new Date().toISOString().split('T')[0],
    description: '',
    reference_type: '',
    reference_id: '',
    lines: [] as { account_code: string; debit: number; credit: number; description: string }[],
});

const accountSearch = ref('');
const accountOptions = ref<{ code: string; name: string; id: string }[]>([]);

async function fetchAccounts() {
    try {
        const res = await api.get('/chart-of-accounts', { params: { per_page: 200, is_active: true } });
        accountOptions.value = (res.data.data ?? []).map((a: any) => ({ code: a.code, name: a.name, id: a.id }));
    } catch {}
}

async function fetchAll(page = 1) {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get('/journal-entries', { params: { page, per_page: pagination.value.per_page } });
        items.value = res.data.data ?? [];
        pagination.value = {
            current_page: res.data.current_page ?? page,
            last_page: res.data.last_page ?? 1,
            per_page: res.data.per_page ?? 15,
            total: res.data.total ?? 0,
        };
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || err.message || 'Failed to load journal entries.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    form.value = {
        entry_date: new Date().toISOString().split('T')[0],
        description: '',
        reference_type: '',
        reference_id: '',
        lines: [{ account_code: '', debit: 0, credit: 0, description: '' }],
    };
    formError.value = '';
    showForm.value = true;
    fetchAccounts();
}

function addLine() {
    form.value.lines.push({ account_code: '', debit: 0, credit: 0, description: '' });
}

function removeLine(index: number) {
    if (form.value.lines.length > 1) {
        form.value.lines.splice(index, 1);
    }
}

async function submit() {
    saving.value = true;
    formError.value = '';
    try {
        await api.post('/journal-entries', form.value);
        showForm.value = false;
        await fetchAll();
    } catch (err: any) {
        formError.value = err.response?.data?.error?.message || err.message || 'Failed to create journal entry.';
    } finally {
        saving.value = false;
    }
}

function viewDetail(entry: JournalEntry) {
    detailEntry.value = entry;
    showDetail.value = true;
}

const columns = [
    { key: 'entry_number', label: 'Entry #' },
    { key: 'entry_date', label: 'Date' },
    { key: 'description', label: 'Description' },
    { key: 'reference_type', label: 'Reference' },
    { key: 'reference_id', label: 'Source ID' },
    { key: 'created_at', label: 'Created' },
];

const typeBadge = (type: string | null) => {
    if (!type) return 'bg-surface-alt text-text-tertiary';
    const map: Record<string, string> = {
        sale: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        payment: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        expense: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        return: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        grn: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        adjustment: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        manual: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
    };
    return map[type] || 'bg-surface-alt text-text-tertiary';
};

onMounted(() => fetchAll());
</script>

<template>
    <AppLayout>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-text-theme">Journal Entries</h1>
                <p class="text-sm text-text-tertiary">General ledger journal entries</p>
            </div>
            <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                <Plus class="w-4 h-4" />
                Manual Entry
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
            <template #cell-entry_date="{ item }">
                <span class="text-sm">{{ (item as any).entry_date }}</span>
            </template>
            <template #cell-reference_type="{ item }">
                <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize" :class="typeBadge((item as any).reference_type)">
                    {{ (item as any).reference_type || 'manual' }}
                </span>
            </template>
            <template #cell-reference_id="{ item }">
                <span class="text-xs text-text-tertiary font-mono" :title="(item as any).reference_id">
                    {{ (item as any).reference_id ? (item as any).reference_id.substring(0, 8) + '...' : '—' }}
                </span>
            </template>
            <template #cell-created_at="{ item }">
                <span class="text-sm text-text-tertiary">{{ new Date((item as any).created_at).toLocaleDateString() }}</span>
            </template>
            <template #actions="{ item }">
                <button @click="viewDetail(item as unknown as JournalEntry)" class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-md transition-colors" title="View Details">
                    <Eye class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

        <!-- Create Journal Entry -->
        <FormSlideOver :title="'Manual Journal Entry'" :visible="showForm" :loading="saving" :error="formError" @close="showForm = false">
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Date <span class="text-danger-theme">*</span></label>
                        <input v-model="form.entry_date" type="date" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Reference</label>
                        <input v-model="form.reference_type" type="text" placeholder="manual" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Description <span class="text-danger-theme">*</span></label>
                    <textarea v-model="form.description" required rows="2" placeholder="Describe the purpose of this entry" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"></textarea>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-text-secondary">Journal Lines</label>
                        <button type="button" @click="addLine" class="text-xs text-primary hover:text-primary/80 font-medium">+ Add Line</button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(line, i) in form.lines" :key="i" class="p-3 border border-border-theme rounded-lg space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-text-tertiary">Line {{ i + 1 }}</span>
                                <button v-if="form.lines.length > 1" type="button" @click="removeLine(i)" class="text-danger-theme hover:text-danger-theme/80">
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <div>
                                <label class="block text-xs text-text-tertiary mb-1">Account Code</label>
                                <input v-model="line.account_code" type="text" required placeholder="e.g. 1100" class="w-full border border-border-input rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-ring" list="account-list" />
                                <datalist id="account-list">
                                    <option v-for="a in accountOptions" :key="a.code" :value="a.code">{{ a.code }} - {{ a.name }}</option>
                                </datalist>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-text-tertiary mb-1">Debit</label>
                                    <input v-model.number="line.debit" type="number" min="0" step="0.01" placeholder="0.00" class="w-full border border-border-input rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                                </div>
                                <div>
                                    <label class="block text-xs text-text-tertiary mb-1">Credit</label>
                                    <input v-model.number="line.credit" type="number" min="0" step="0.01" placeholder="0.00" class="w-full border border-border-input rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-text-tertiary mb-1">Description</label>
                                <input v-model="line.description" type="text" placeholder="Line description" class="w-full border border-border-input rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showForm = false" class="px-4 py-2.5 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors">Cancel</button>
                    <button type="submit" :disabled="saving" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                        {{ saving ? 'Posting...' : 'Post Entry' }}
                    </button>
                </div>
            </form>
        </FormSlideOver>

        <!-- Detail Slide-over -->
        <div v-if="showDetail && detailEntry" class="fixed inset-0 z-50 flex justify-end">
            <div class="fixed inset-0 bg-surface-overlay" @click="showDetail = false"></div>
            <div class="relative w-full max-w-lg bg-surface-raised shadow-xl border-l border-border-theme overflow-y-auto">
                <div class="sticky top-0 bg-surface-raised border-b border-border-theme px-6 py-4 flex items-center justify-between z-10">
                    <h2 class="text-lg font-semibold text-text-theme">{{ detailEntry.entry_number }}</h2>
                    <button @click="showDetail = false" class="p-1.5 rounded-lg hover:bg-surface-alt text-text-tertiary">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-text-tertiary uppercase tracking-wider">Date</p>
                            <p class="text-sm font-medium text-text-theme">{{ detailEntry.entry_date }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text-tertiary uppercase tracking-wider">Reference</p>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full capitalize" :class="typeBadge(detailEntry.reference_type)">{{ detailEntry.reference_type || 'Manual' }}</span>
                        </div>
                        <div>
                            <p class="text-xs text-text-tertiary uppercase tracking-wider">Source ID</p>
                            <p class="text-sm font-mono text-text-theme break-all">{{ detailEntry.reference_id || '—' }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-text-tertiary uppercase tracking-wider">Description</p>
                        <p class="text-sm text-text-theme">{{ detailEntry.description }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-text-tertiary uppercase tracking-wider mb-2">Lines</p>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border-theme">
                                    <th class="text-left py-2 text-text-tertiary font-medium">Account</th>
                                    <th class="text-right py-2 text-text-tertiary font-medium">Debit</th>
                                    <th class="text-right py-2 text-text-tertiary font-medium">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in detailEntry.lines" :key="line.id" class="border-b border-border-light">
                                    <td class="py-2 text-text-theme">
                                        <span class="font-medium">{{ line.account?.code }}</span>
                                        <span class="text-text-tertiary ml-1">{{ line.account?.name }}</span>
                                    </td>
                                    <td class="py-2 text-right text-text-theme">{{ line.debit_amount > 0 ? Number(line.debit_amount).toFixed(2) : '-' }}</td>
                                    <td class="py-2 text-right text-text-theme">{{ line.credit_amount > 0 ? Number(line.credit_amount).toFixed(2) : '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-xs text-text-tertiary pt-2">
                        Created: {{ new Date(detailEntry.created_at).toLocaleString() }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
