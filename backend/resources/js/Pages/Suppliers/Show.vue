<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';

const props = defineProps<{ id: string }>();

const supplier = ref<any>(null);
const loading = ref(true);
const error = ref('');

function formatDate(d: string | null): string { return d ? new Date(d).toLocaleDateString() : '—'; }

onMounted(async () => {
    try {
        const res = await api.get(`/suppliers/${props.id}`);
        supplier.value = res.data.data;
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to load supplier.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <button @click="router.visit('/suppliers')" class="flex items-center gap-1 text-sm text-text-secondary hover:text-text-theme transition-colors mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Suppliers
            </button>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>
            <div v-else-if="error" class="p-4 bg-danger-light border border-danger-theme/20 rounded-xl text-danger-theme text-sm">{{ error }}</div>

            <div v-else-if="supplier" class="space-y-6">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-text-theme">{{ supplier.name }}</h1>
                            <p v-if="supplier.contact_person" class="text-text-secondary mt-1">Contact: {{ supplier.contact_person }}</p>
                        </div>
                        <span :class="[supplier.is_active ? 'bg-success-light text-success-theme' : 'bg-surface-alt text-text-tertiary', 'px-3 py-1 rounded-full text-xs font-medium']">
                            {{ supplier.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4">
                        <p class="text-xs text-text-tertiary">Phone</p>
                        <p class="text-sm font-semibold text-text-theme mt-1">{{ supplier.phone || '—' }}</p>
                    </div>
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4">
                        <p class="text-xs text-text-tertiary">Email</p>
                        <p class="text-sm font-semibold text-text-theme mt-1">{{ supplier.email || '—' }}</p>
                    </div>
                </div>

                <div v-if="supplier.address" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4">
                    <p class="text-xs text-text-tertiary">Address</p>
                    <p class="text-sm text-text-theme mt-1">{{ supplier.address }}</p>
                </div>

                <div v-if="supplier.purchase_orders?.length" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                    <div class="px-6 py-4 border-b border-border-theme">
                        <h2 class="text-lg font-semibold text-text-theme">Purchase Orders</h2>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-table-header border-b border-table-border">
                            <tr>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">PO Number</th>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Status</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Total</th>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light">
                            <tr v-for="po in supplier.purchase_orders" :key="po.id" class="hover:bg-table-row-hover transition-colors cursor-pointer" @click="router.visit(`/purchase-orders/${po.id}`)">
                                <td class="px-6 py-4 text-text-theme font-medium">{{ po.po_number }}</td>
                                <td class="px-6 py-4"><span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize bg-surface-alt text-text-secondary">{{ po.status }}</span></td>
                                <td class="px-6 py-4 text-right font-semibold text-text-theme">{{ new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(po.total_amount) }}</td>
                                <td class="px-6 py-4 text-text-secondary">{{ formatDate(po.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-sm text-text-tertiary">Supplier since {{ formatDate(supplier.created_at) }}</p>
            </div>
        </div>
    </AppLayout>
</template>
