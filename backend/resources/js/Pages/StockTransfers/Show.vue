<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';

const props = defineProps<{ id: string }>();

const transfer = ref<any>(null);
const loading = ref(true);
const error = ref('');

function formatDate(d: string | null): string { return d ? new Date(d).toLocaleDateString() : '—'; }
function statusClass(s: string): string { const m: Record<string, string> = { pending: 'bg-yellow-100 text-yellow-800', in_transit: 'bg-blue-100 text-blue-800', completed: 'bg-success-light text-green-800', cancelled: 'bg-danger-light text-red-800' }; return m[s] || 'bg-surface-alt'; }

async function completeTransfer() {
    if (!confirm('Complete this transfer?')) return;
    try { await api.post(`/stock-transfers/${transfer.value.id}/complete`); transfer.value.status = 'completed'; }
    catch (err: any) { alert(err?.response?.data?.message || 'Failed'); }
}

async function cancelTransfer() {
    if (!confirm('Cancel this transfer?')) return;
    try { await api.post(`/stock-transfers/${transfer.value.id}/cancel`); transfer.value.status = 'cancelled'; }
    catch (err: any) { alert(err?.response?.data?.message || 'Failed'); }
}

onMounted(async () => {
    try {
        const res = await api.get(`/stock-transfers/${props.id}`);
        transfer.value = res.data.data;
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to load transfer.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <button @click="router.visit('/stock-transfers')" class="flex items-center gap-1 text-sm text-text-secondary hover:text-text-theme transition-colors mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Stock Transfers
            </button>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>
            <div v-else-if="error" class="p-4 bg-danger-light border border-danger-theme/20 rounded-xl text-danger-theme text-sm">{{ error }}</div>

            <div v-else-if="transfer" class="space-y-6">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-text-theme">{{ transfer.from_warehouse?.name || 'Unknown' }} → {{ transfer.to_warehouse?.name || 'Unknown' }}</h1>
                            <span :class="[statusClass(transfer.status), 'px-3 py-1 rounded-full text-xs font-medium capitalize mt-2 inline-block']">{{ transfer.status }}</span>
                        </div>
                        <p class="text-sm text-text-tertiary">{{ formatDate(transfer.created_at) }}</p>
                    </div>
                </div>

                <div v-if="transfer.notes" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4">
                    <p class="text-xs text-text-tertiary">Notes</p>
                    <p class="text-sm text-text-theme mt-1">{{ transfer.notes }}</p>
                </div>

                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                    <div class="px-6 py-4 border-b border-border-theme">
                        <h2 class="text-lg font-semibold text-text-theme">Items</h2>
                    </div>
                    <div v-if="!transfer.items?.length" class="px-6 py-8 text-center text-text-tertiary text-sm">No items.</div>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-table-header border-b border-table-border">
                            <tr>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Product</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Quantity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light">
                            <tr v-for="item in transfer.items" :key="item.id" class="hover:bg-table-row-hover transition-colors">
                                <td class="px-6 py-4 text-text-theme font-medium">{{ item.product?.name || item.product_id }}</td>
                                <td class="px-6 py-4 text-right text-text-secondary">{{ item.quantity }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex gap-2">
                    <button v-if="transfer.status === 'pending' || transfer.status === 'in_transit'" @click="completeTransfer" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Complete</button>
                    <button v-if="transfer.status === 'pending'" @click="cancelTransfer" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Cancel</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
