<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';

const props = defineProps<{ id: string }>();

const po = ref<any>(null);
const loading = ref(true);
const error = ref('');

function formatCurrency(v: number): string { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v); }
function formatDate(d: string | null): string { return d ? new Date(d).toLocaleDateString() : '—'; }
function statusClass(s: string): string { const m: Record<string, string> = { draft: 'bg-surface-alt text-text-secondary', pending: 'bg-yellow-100 text-yellow-800', approved: 'bg-blue-100 text-primary', received: 'bg-success-light text-success-theme', cancelled: 'bg-danger-light text-danger-theme' }; return m[s] || 'bg-surface-alt'; }

async function transitionStatus(status: string) {
    if (!confirm(`Mark as ${status}?`)) return;
    try { await api.put(`/purchase-orders/${po.value.id}/status`, { status }); po.value.status = status; }
    catch (err: any) { alert(err?.response?.data?.error?.message || 'Failed'); }
}

onMounted(async () => {
    try {
        const res = await api.get(`/purchase-orders/${props.id}`);
        po.value = res.data.data;
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to load purchase order.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <button @click="router.visit('/purchase-orders')" class="flex items-center gap-1 text-sm text-text-secondary hover:text-text-theme transition-colors mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Purchase Orders
            </button>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>
            <div v-else-if="error" class="p-4 bg-danger-light border border-danger-theme/20 rounded-xl text-danger-theme text-sm">{{ error }}</div>

            <div v-else-if="po" class="space-y-6">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-text-theme">{{ po.po_number }}</h1>
                            <span :class="[statusClass(po.status), 'px-3 py-1 rounded-full text-xs font-medium capitalize mt-2 inline-block']">{{ po.status }}</span>
                        </div>
                        <div class="text-right text-sm text-text-tertiary">
                            <p>{{ po.supplier?.name || '—' }}</p>
                            <p>{{ po.branch?.name || '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4">
                        <p class="text-xs text-text-tertiary">Total Amount</p>
                        <p class="text-2xl font-bold text-text-theme mt-1">{{ formatCurrency(po.total_amount) }}</p>
                    </div>
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4">
                        <p class="text-xs text-text-tertiary">Created</p>
                        <p class="text-sm font-semibold text-text-theme mt-1">{{ formatDate(po.created_at) }}</p>
                    </div>
                </div>

                <div v-if="po.notes" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4">
                    <p class="text-xs text-text-tertiary">Notes</p>
                    <p class="text-sm text-text-theme mt-1">{{ po.notes }}</p>
                </div>

                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                    <div class="px-6 py-4 border-b border-border-theme">
                        <h2 class="text-lg font-semibold text-text-theme">Line Items</h2>
                    </div>
                    <div v-if="!po.items?.length" class="px-6 py-8 text-center text-text-tertiary text-sm">No items.</div>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-table-header border-b border-table-border">
                            <tr>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Product</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Qty</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Unit Cost</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light">
                            <tr v-for="item in po.items" :key="item.id" class="hover:bg-table-row-hover transition-colors">
                                <td class="px-6 py-4 text-text-theme font-medium">{{ item.product?.name || item.product_id }}</td>
                                <td class="px-6 py-4 text-right text-text-secondary">{{ item.quantity }}</td>
                                <td class="px-6 py-4 text-right text-text-secondary">{{ formatCurrency(item.unit_cost) }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-text-theme">{{ formatCurrency(item.quantity * item.unit_cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-if="po.status === 'draft'" @click="transitionStatus('pending')" class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-medium hover:bg-yellow-700">Submit</button>
                    <button v-if="po.status === 'pending'" @click="transitionStatus('approved')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Approve</button>
                    <button v-if="po.status === 'approved'" @click="transitionStatus('received')" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Receive</button>
                    <button v-if="['draft', 'pending', 'approved'].includes(po.status)" @click="transitionStatus('cancelled')" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Cancel</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
