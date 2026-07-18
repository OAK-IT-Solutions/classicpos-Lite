<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';

const props = defineProps<{ id: string }>();

const grn = ref<any>(null);
const loading = ref(true);
const error = ref('');

function formatCurrency(v: number): string { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v); }
function formatDate(d: string | null): string { return d ? new Date(d).toLocaleDateString() : '—'; }

onMounted(async () => {
    try {
        const res = await api.get(`/grn/${props.id}`);
        grn.value = res.data.data;
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to load GRN.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <button @click="router.visit('/grn')" class="flex items-center gap-1 text-sm text-text-secondary hover:text-text-theme transition-colors mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Goods Received
            </button>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>
            <div v-else-if="error" class="p-4 bg-danger-light border border-danger-theme/20 rounded-xl text-danger-theme text-sm">{{ error }}</div>

            <div v-else-if="grn" class="space-y-6">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-text-theme">GRN — {{ grn.purchase_order?.po_number || 'N/A' }}</h1>
                            <p class="text-sm text-text-tertiary mt-1">Received {{ formatDate(grn.created_at) }}</p>
                        </div>
                        <button v-if="grn.purchase_order" @click="router.visit(`/purchase-orders/${grn.purchase_order.id}`)" class="text-sm text-primary hover:underline">View PO</button>
                    </div>
                </div>

                <div v-if="grn.notes" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4">
                    <p class="text-xs text-text-tertiary">Notes</p>
                    <p class="text-sm text-text-theme mt-1">{{ grn.notes }}</p>
                </div>

                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                    <div class="px-6 py-4 border-b border-border-theme">
                        <h2 class="text-lg font-semibold text-text-theme">Items Received</h2>
                    </div>
                    <div v-if="!grn.grn_items?.length" class="px-6 py-8 text-center text-text-tertiary text-sm">No items.</div>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-table-header border-b border-table-border">
                            <tr>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Product</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Qty</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Unit Cost</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Subtotal</th>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Batch</th>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Expiry</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light">
                            <tr v-for="item in grn.grn_items" :key="item.id" class="hover:bg-table-row-hover transition-colors">
                                <td class="px-6 py-4 text-text-theme font-medium">{{ item.product?.name || item.product_id }}</td>
                                <td class="px-6 py-4 text-right text-text-secondary">{{ item.quantity }}</td>
                                <td class="px-6 py-4 text-right text-text-secondary">{{ formatCurrency(item.unit_cost) }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-text-theme">{{ formatCurrency(item.quantity * item.unit_cost) }}</td>
                                <td class="px-6 py-4 text-text-secondary">{{ item.batch_number || '—' }}</td>
                                <td class="px-6 py-4 text-text-secondary">{{ item.expiry_date || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
