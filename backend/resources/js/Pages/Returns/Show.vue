<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';
import { ArrowLeft, AlertCircle } from 'lucide-vue-next';

const page = usePage();
const returnId = computed(() => {
    const parts = page.url.split('/');
    return parts[parts.length - 1];
});

const returnData = ref<any>(null);
const loading = ref(true);
const error = ref('');

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString();
}

function formatCurrency(v: number): string {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v);
}

function statusClass(status: string): string {
    const map: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-success-light text-green-800',
        rejected: 'bg-danger-light text-red-800',
    };
    return map[status] || 'bg-surface-alt text-gray-800';
}

onMounted(async () => {
    if (returnId.value) {
        try {
            const res = await api.get(`/returns/${returnId.value}`);
            returnData.value = res.data.data;
        } catch (err: any) {
            error.value = err?.response?.data?.error?.message || 'Failed to load return.';
        } finally {
            loading.value = false;
        }
    }
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="router.visit('/returns')"
                    class="p-2 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-lg transition-colors">
                    <ArrowLeft class="w-5 h-5" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">Return Detail</h1>
                    <p v-if="returnData" class="text-text-tertiary text-sm mt-0.5">
                        Sale: {{ returnData.sale?.invoice_number || returnData.sale_id?.slice(0, 8) }}
                    </p>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>
        <div v-else-if="error" class="p-4 bg-danger-light border border-danger-theme/20 rounded-xl text-sm text-danger-theme">{{ error }}</div>

        <div v-else-if="returnData" class="space-y-6 max-w-3xl">
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium">Status</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1 capitalize"
                        :class="statusClass(returnData.status)">
                        {{ returnData.status }}
                    </span>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium">Refund Amount</p>
                    <p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(returnData.refund_amount) }}</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium">Date</p>
                    <p class="text-sm font-semibold text-text-theme mt-1">{{ formatDate(returnData.created_at) }}</p>
                </div>
            </div>

            <div v-if="returnData.reason" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                <p class="text-xs text-text-tertiary font-medium mb-1">Reason</p>
                <p class="text-sm text-text-theme">{{ returnData.reason }}</p>
            </div>

            <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                <div class="px-6 py-4 border-b border-border-theme">
                    <h2 class="text-lg font-semibold text-text-theme">Return Items</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-surface-alt border-b border-border-theme">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase">Product</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-text-tertiary uppercase">Qty</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in returnData.items" :key="item.id">
                            <td class="px-6 py-4 text-text-theme">{{ item.product?.name || item.product_id.slice(0, 8) }}</td>
                            <td class="px-6 py-4 text-right text-text-secondary">{{ item.quantity }}</td>
                            <td class="px-6 py-4 text-text-tertiary">{{ item.reason || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button @click="router.visit(`/sales/${returnData.sale_id}`)"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                View Sale
            </button>
        </div>
    </AppLayout>
</template>
