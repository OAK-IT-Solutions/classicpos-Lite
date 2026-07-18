<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';
import { useAuth } from '@/composables/useAuth';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{ id: string }>();

const { formatCurrency, formatDate } = useLocale();

const customer = ref<any>(null);
const loading = ref(true);
const error = ref('');

const memberLevelColors: Record<string, string> = {
    bronze: 'bg-amber-100 text-amber-800',
    silver: 'bg-gray-100 text-gray-800',
    gold: 'bg-yellow-100 text-yellow-800',
    platinum: 'bg-purple-100 text-purple-800',
};

const statusColors: Record<string, string> = {
    completed: 'bg-success-light text-success-theme',
    pending: 'bg-warning-light text-warning-theme',
    pending_sync: 'bg-warning-light text-warning-theme',
    voided: 'bg-danger-light text-danger-theme',
    refunded: 'bg-primary-light text-primary',
};

onMounted(async () => {
    try {
        const res = await api.get(`/customers/${props.id}`);
        customer.value = res.data.data;
    } catch (err: any) {
        const status = err.response?.status;
        const msg = err.response?.data?.error?.message || err.response?.data?.message || err.message;
        error.value = msg || `Failed to load customer (HTTP ${status || 'error'}).`;
    } finally {
        loading.value = false;
    }
});

const deleting = ref(false);

function goBack() {
    router.visit('/customers');
}

async function handleDelete() {
    if (!confirm('Delete this customer? This action cannot be undone.')) return;
    deleting.value = true;
    try {
        await api.delete(`/customers/${props.id}`);
        router.visit('/customers');
    } catch {
        alert('Failed to delete customer.');
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <button @click="goBack" class="flex items-center gap-1 text-sm text-text-secondary hover:text-text-theme transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Customers
                </button>
                <div class="flex items-center gap-2">
                    <router-link :href="`/customers`"
                        class="px-4 py-2 bg-surface-raised border border-border-input rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt transition-colors">
                        Edit
                    </router-link>
                    <button @click="handleDelete" :disabled="deleting"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50">
                        {{ deleting ? 'Deleting...' : 'Delete' }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <div v-else-if="error" class="p-4 bg-danger-light border border-danger-theme/20 rounded-xl text-danger-theme text-sm">
                {{ error }}
            </div>

            <div v-else-if="customer" class="space-y-6">
                <!-- Profile Header -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold">
                                {{ customer.name?.charAt(0)?.toUpperCase() || 'C' }}
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-text-theme">{{ customer.name }}</h1>
                                <p class="text-text-secondary">{{ customer.phone }}</p>
                                <p v-if="customer.email" class="text-text-tertiary text-sm">{{ customer.email }}</p>
                                <p v-if="customer.location" class="text-text-tertiary text-sm mt-1">{{ customer.location }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span :class="[memberLevelColors[customer.member_level] || 'bg-gray-100 text-gray-800', 'px-3 py-1 rounded-full text-xs font-medium capitalize']">
                                {{ customer.member_level }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4 text-center">
                        <p class="text-2xl font-bold text-text-theme">{{ formatCurrency(customer.total_spend) }}</p>
                        <p class="text-xs text-text-tertiary mt-1">Total Spend</p>
                    </div>
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4 text-center">
                        <p class="text-2xl font-bold text-text-theme">{{ customer.total_visits }}</p>
                        <p class="text-xs text-text-tertiary mt-1">Total Visits</p>
                    </div>
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4 text-center">
                        <p class="text-2xl font-bold text-text-theme">{{ formatCurrency(customer.avg_order_value) }}</p>
                        <p class="text-xs text-text-tertiary mt-1">Avg Order</p>
                    </div>
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4 text-center">
                        <p class="text-2xl font-bold text-text-theme">{{ customer.loyalty_points }}</p>
                        <p class="text-xs text-text-tertiary mt-1">Loyalty Points</p>
                    </div>
                </div>

                <!-- Purchase History -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                    <div class="px-6 py-4 border-b border-border-theme">
                        <h2 class="text-lg font-semibold text-text-theme">Purchase History</h2>
                    </div>

                    <div v-if="customer.sales?.length === 0" class="px-6 py-8 text-center text-text-tertiary text-sm">
                        No purchases yet.
                    </div>

                    <table v-else class="w-full text-sm">
                        <thead class="bg-table-header border-b border-table-border">
                            <tr>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Invoice</th>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Date</th>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Payment</th>
                                <th class="text-left px-6 py-3 font-semibold text-text-secondary">Status</th>
                                <th class="text-right px-6 py-3 font-semibold text-text-secondary">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light">
                            <tr v-for="sale in customer.sales" :key="sale.id" class="hover:bg-table-row-hover transition-colors">
                                <td class="px-6 py-4 text-text-theme font-medium">{{ sale.invoice_number }}</td>
                                <td class="px-6 py-4 text-text-secondary">{{ formatDate(sale.created_at) }}</td>
                                <td class="px-6 py-4 text-text-secondary capitalize">{{ sale.payment_method }}</td>
                                <td class="px-6 py-4">
                                    <span :class="[statusColors[sale.status] || 'bg-gray-100 text-gray-800', 'px-2 py-0.5 rounded-full text-xs font-medium capitalize']">
                                        {{ sale.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-text-theme">{{ formatCurrency(sale.total_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Member Since -->
                <div class="text-sm text-text-tertiary">
                    Customer since {{ formatDate(customer.created_at) }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>
