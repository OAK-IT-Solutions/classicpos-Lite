<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import DataTable from '@/Components/DataTable.vue';
import FormSlideOver from '@/Components/FormSlideOver.vue';
import { useCrud } from '@/composables/useCrud';

defineProps<{ embedded?: boolean }>();

interface Promotion {
    id: string;
    code: string;
    type: 'percentage' | 'flat';
    value: number;
    min_order_amount: number;
    max_discount_amount: number | null;
    usage_limit: number | null;
    used_count: number;
    valid_from: string | null;
    valid_until: string | null;
    is_active: boolean;
    description: string | null;
    created_at: string;
}

const {
    items, loading, error, pagination,
    fetchAll, create, update: updateRecord, destroy
} = useCrud<Promotion>('/promotions', { searchFields: ['code', 'description'] });

const columns = [
    { key: 'code', label: 'Code' },
    { key: 'type', label: 'Type' },
    { key: 'value', label: 'Value' },
    { key: 'used_count', label: 'Used' },
    { key: 'valid_from', label: 'Valid From' },
    { key: 'valid_until', label: 'Valid Until' },
    { key: 'is_active', label: 'Active' },
];

const showForm = ref(false);
const editing = ref<Promotion | null>(null);
const saving = ref(false);
const formError = ref('');

const form = ref({
    code: '',
    type: 'percentage' as 'percentage' | 'flat',
    value: 0,
    min_order_amount: 0,
    max_discount_amount: null as number | null,
    usage_limit: null as number | null,
    valid_from: '',
    valid_until: '',
    is_active: true,
    description: '',
});

function openCreate() {
    editing.value = null;
    form.value = {
        code: '',
        type: 'percentage',
        value: 0,
        min_order_amount: 0,
        max_discount_amount: null,
        usage_limit: null,
        valid_from: '',
        valid_until: '',
        is_active: true,
        description: '',
    };
    formError.value = '';
    showForm.value = true;
}

function openEdit(promotion: Promotion) {
    editing.value = promotion;
    form.value = {
        code: promotion.code,
        type: promotion.type,
        value: promotion.value,
        min_order_amount: promotion.min_order_amount,
        max_discount_amount: promotion.max_discount_amount,
        usage_limit: promotion.usage_limit,
        valid_from: promotion.valid_from ?? '',
        valid_until: promotion.valid_until ?? '',
        is_active: promotion.is_active,
        description: promotion.description ?? '',
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
        formError.value = err.response?.data?.error?.message || err.message || 'Failed to save promotion.';
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(promotion: Promotion) {
    if (!confirm(`Delete promotion "${promotion.code}"?`)) return;
    try {
        await destroy(promotion.id);
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
                <p class="text-sm text-text-tertiary">Create and manage discount promo codes</p>
                <button @click="openCreate" class="px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover transition-colors">
                    Add Promotion
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
                <template #cell-type="{ item }">
                    <span class="capitalize">{{ (item as any).type }}</span>
                </template>
                <template #cell-value="{ item }">
                    <span v-if="(item as any).type === 'percentage'">{{ (item as any).value }}%</span>
                    <span v-else>${{ (item as any).value }}</span>
                </template>
                <template #cell-is_active="{ item }">
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="(item as any).is_active ? 'bg-success-light text-success-theme' : 'bg-surface-alt text-text-tertiary'">
                        {{ (item as any).is_active ? 'Active' : 'Inactive' }}
                    </span>
                </template>
                <template #cell-valid_from="{ item }">
                    {{ (item as any).valid_from ? new Date((item as any).valid_from).toLocaleDateString() : '—' }}
                </template>
                <template #cell-valid_until="{ item }">
                    {{ (item as any).valid_until ? new Date((item as any).valid_until).toLocaleDateString() : '—' }}
                </template>
                <template #cell-used_count="{ item }">
                    {{ (item as any).used_count }}{{ (item as any).usage_limit ? ' / ' + (item as any).usage_limit : '' }}
                </template>
                <template #actions="{ item }">
                    <button @click="openEdit(item as any)" class="text-sm text-primary hover:text-primary mr-3">Edit</button>
                    <button @click="confirmDelete(item as any)" class="text-sm text-danger-theme hover:text-danger-theme">Delete</button>
                </template>
            </DataTable>
        <FormSlideOver :visible="showForm" :title="editing ? 'Edit Promotion' : 'Add Promotion'" @close="showForm = false">
            <form @submit.prevent="save" class="space-y-4">
                <div v-if="formError" class="p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ formError }}</div>
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Code</label>
                    <input v-model="form.code" required maxlength="50" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" placeholder="SUMMER20">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Type</label>
                        <select v-model="form.type" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option value="percentage">Percentage</option>
                            <option value="flat">Flat Amount</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Value</label>
                        <input v-model.number="form.value" required type="number" min="0" step="0.01" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Min Order Amount</label>
                        <input v-model.number="form.min_order_amount" type="number" min="0" step="0.01" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Max Discount</label>
                        <input v-model.number="form.max_discount_amount" type="number" min="0" step="0.01" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Usage Limit</label>
                        <input v-model.number="form.usage_limit" type="number" min="1" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Active</label>
                        <select v-model="form.is_active" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option :value="true">Yes</option>
                            <option :value="false">No</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Valid From</label>
                        <input v-model="form.valid_from" type="date" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Valid Until</label>
                        <input v-model="form.valid_until" type="date" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
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
