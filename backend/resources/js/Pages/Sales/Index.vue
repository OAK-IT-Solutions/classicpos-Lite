<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSales } from '@/composables/useSales';
import type { Sale, SaleDetail } from '@/composables/useSales';
import { useAuth } from '@/composables/useAuth';
import { Receipt, Eye, ChevronLeft, ChevronRight, AlertCircle } from 'lucide-vue-next';

const { sales, saleDetail, loading, error, pagination, fetchSales, fetchSaleDetail, voidSale } = useSales();
const auth = useAuth();

const dateFrom = ref('');
const dateTo = ref('');
const searchQuery = ref('');
const filterStatus = ref('');
const filterPaymentMethod = ref('');
const filterMinAmount = ref('');
const filterMaxAmount = ref('');
const selectedSale = ref<SaleDetail | null>(null);
const showDetail = ref(false);

const voiding = ref(false);
const confirmVoid = ref(false);

const canVoid = computed(() =>
    selectedSale.value && ['completed', 'pending', 'pending_sync'].includes(selectedSale.value.status)
);

const branchId = computed(() => auth.user.value?.branch?.id || auth.user.value?.branch_id);

onMounted(() => {
    if (branchId.value) {
        fetchSales(1, { branch_id: branchId.value }).catch(() => {});
    }
});

let debounceTimer: ReturnType<typeof setTimeout>;

watch([searchQuery, dateFrom, dateTo, filterStatus, filterPaymentMethod, filterMinAmount, filterMaxAmount], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
});

function buildParams(): Record<string, string> {
    const params: Record<string, string> = {};
    if (branchId.value) params.branch_id = branchId.value;
    if (dateFrom.value) params.date_from = dateFrom.value;
    if (dateTo.value) params.date_to = dateTo.value;
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (filterStatus.value) params.status = filterStatus.value;
    if (filterPaymentMethod.value) params.payment_method = filterPaymentMethod.value;
    const minAmount = filterMinAmount.value.toString().trim();
    const maxAmount = filterMaxAmount.value.toString().trim();
    if (minAmount !== '' && minAmount !== '0') params.min_amount = minAmount;
    if (maxAmount !== '' && maxAmount !== '0') params.max_amount = maxAmount;
    return params;
}

function applyFilters() {
    fetchSales(1, buildParams()).catch(() => {});
}

function changePage(page: number) {
    fetchSales(page, buildParams()).catch(() => {});
}

async function openDetail(id: string) {
    try {
        await fetchSaleDetail(id);
        selectedSale.value = saleDetail.value;
        showDetail.value = true;
    } catch {
        // error is set in composable
    }
}

async function handleVoid() {
    if (!selectedSale.value) return;
    voiding.value = true;
    confirmVoid.value = false;
    try {
        await voidSale(selectedSale.value.id);
    } catch {
        // error set in composable
    } finally {
        voiding.value = false;
    }
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString();
}

function formatAmount(amount: number): string {
    return amount.toFixed(2);
}

function statusClass(status: string): string {
    switch (status.toLowerCase()) {
        case 'completed': return 'bg-success-light text-success-theme';
        case 'pending': return 'bg-warning-light text-warning-theme';
        case 'cancelled':
        case 'voided':
        case 'refunded': return 'bg-danger-light text-danger-theme';
        default: return 'bg-surface-alt text-text-secondary';
    }
}
</script>

<template>
    <AppLayout>
        <!-- Page header -->
        <div class="mb-8 flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <Receipt class="w-5 h-5 text-primary" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-text-theme">Sales History</h1>
                <p class="text-text-tertiary text-sm mt-0.5">View and filter all sales transactions</p>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4 mb-6">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[180px]">
                    <label class="text-xs font-medium text-text-secondary mb-1 block">Search Invoice</label>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Invoice number..."
                        class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring"
                        @keydown.enter="applyFilters"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-text-secondary mb-1 block">From</label>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-text-secondary mb-1 block">To</label>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-text-secondary mb-1 block">Status</label>
                    <select
                        v-model="filterStatus"
                        class="px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring"
                        @change="applyFilters"
                    >
                        <option value="">All</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="voided">Voided</option>
                        <option value="refunded">Refunded</option>
                        <option value="pending_sync">Pending Sync</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-text-secondary mb-1 block">Payment</label>
                    <select
                        v-model="filterPaymentMethod"
                        class="px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring"
                        @change="applyFilters"
                    >
                        <option value="">All</option>
                        <option value="cash">Cash</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="card">Card</option>
                        <option value="qr">QR</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-text-secondary mb-1 block">Min Amount</label>
                    <input
                        v-model="filterMinAmount"
                        type="text"
                        inputmode="decimal"
                        placeholder="Min"
                        class="w-24 px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-text-secondary mb-1 block">Max Amount</label>
                    <input
                        v-model="filterMaxAmount"
                        type="text"
                        inputmode="decimal"
                        placeholder="Max"
                        class="w-24 px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring"
                    />
                </div>
                <button
                    @click="applyFilters"
                    class="px-5 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors"
                >
                    Filter
                </button>
                <button
                    v-if="searchQuery || dateFrom || dateTo || filterStatus || filterPaymentMethod || filterMinAmount || filterMaxAmount"
                    @click="searchQuery = ''; dateFrom = ''; dateTo = ''; filterStatus = ''; filterPaymentMethod = ''; filterMinAmount = ''; filterMaxAmount = ''; applyFilters()"
                    class="px-3 py-2 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- Inline error alert -->
        <div v-if="error" class="mb-6 p-4 bg-danger-light border border-danger-theme/20 rounded-xl flex items-start gap-3">
            <AlertCircle class="w-5 h-5 text-danger-theme flex-shrink-0 mt-0.5" />
            <p class="text-sm text-danger-theme">{{ error }}</p>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-12 text-center">
            <p class="text-text-tertiary text-sm">Loading sales...</p>
        </div>

        <!-- Sales table -->
        <div v-else class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden mb-6">
            <div v-if="sales.length === 0" class="p-12 text-center text-text-tertiary text-sm">
                No sales found for the selected filters.
            </div>
            <table v-else class="w-full text-sm">
                <thead class="bg-surface-alt border-b border-border-theme">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Total Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Payment Method</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="sale in sales"
                        :key="sale.id"
                        class="hover:bg-surface-alt cursor-pointer transition-colors"
                        @click="router.visit(`/sales/${sale.id}`)"
                    >
                        <td class="px-6 py-4 font-medium text-text-theme">{{ sale.invoice_number }}</td>
                        <td class="px-6 py-4 text-text-secondary">{{ formatAmount(sale.total_amount) }}</td>
                        <td class="px-6 py-4 text-text-secondary">{{ sale.payment_method }}</td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="statusClass(sale.status)"
                            >
                                {{ sale.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-text-tertiary">{{ formatDate(sale.created_at) }}</td>
                        <td class="px-6 py-4">
                            <button
                                @click.stop="router.visit(`/sales/${sale.id}`)"
                                class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-lg transition-colors"
                                title="View details"
                            >
                                <Eye class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination controls -->
        <div v-if="!loading && pagination.last_page > 1" class="flex items-center justify-center gap-3">
            <button
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page <= 1"
                class="p-2 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <ChevronLeft class="w-5 h-5" />
            </button>
            <span class="text-sm text-text-secondary">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </span>
            <button
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.last_page"
                class="p-2 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <ChevronRight class="w-5 h-5" />
            </button>
        </div>

        <!-- Detail modal -->
        <div v-if="showDetail && saleDetail" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Overlay -->
            <div class="absolute inset-0 bg-black/40" @click="showDetail = false" />

            <!-- Modal panel -->
            <div class="relative bg-surface-raised rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
                <!-- Modal header -->
                <div class="flex items-center justify-between p-6 border-b border-border-theme">
                    <div class="flex items-center gap-3">
                        <Receipt class="w-5 h-5 text-primary" />
                        <div>
                            <h2 class="text-lg font-semibold text-text-theme">{{ saleDetail.invoice_number }}</h2>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="statusClass(saleDetail.status)"
                                >
                                    {{ saleDetail.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <button
                        @click="showDetail = false"
                        class="p-2 text-text-tertiary hover:text-text-secondary hover:bg-surface-alt rounded-lg transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal body -->
                <div class="overflow-y-auto flex-1 p-6 space-y-5">
                    <!-- Summary row -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-surface-alt rounded-xl p-4">
                            <p class="text-xs text-text-tertiary font-medium mb-1">Customer</p>
                            <p class="text-sm font-semibold text-text-theme">
                                {{ saleDetail.customer?.name ?? 'Walk-in' }}
                            </p>
                        </div>
                        <div class="bg-surface-alt rounded-xl p-4">
                            <p class="text-xs text-text-tertiary font-medium mb-1">Total Amount</p>
                            <p class="text-sm font-semibold text-text-theme">{{ formatAmount(saleDetail.total_amount) }}</p>
                        </div>
                    </div>

                    <!-- Line items table -->
                    <div>
                        <h3 class="text-sm font-semibold text-text-secondary mb-3">Line Items</h3>
                        <div class="rounded-xl border border-border-theme overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-alt border-b border-border-theme">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Product</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="item in saleDetail.items" :key="item.product_id">
                                        <td class="px-4 py-3 text-text-theme">{{ item.name }}</td>
                                        <td class="px-4 py-3 text-right text-text-secondary">{{ item.qty }}</td>
                                        <td class="px-4 py-3 text-right text-text-secondary">{{ formatAmount(item.price) }}</td>
                                        <td class="px-4 py-3 text-right text-text-theme font-medium">{{ formatAmount(item.qty * item.price) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-surface-alt border-t border-border-theme">
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-sm font-semibold text-text-secondary text-right">Total</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-text-theme">{{ formatAmount(saleDetail.total_amount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal footer -->
                <div class="p-6 border-t border-border-theme flex items-center justify-between">
                    <button
                        v-if="canVoid"
                        @click="confirmVoid = true"
                        class="px-5 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors"
                    >
                        Void Sale
                    </button>
                    <div v-else />
                    <button
                        @click="showDetail = false"
                        class="px-5 py-2 bg-surface-alt text-text-secondary text-sm font-medium rounded-lg hover:bg-border-light transition-colors"
                    >
                        Close
                    </button>
                </div>

                <!-- Void confirmation modal -->
                <div v-if="confirmVoid" class="absolute inset-0 z-60 flex items-center justify-center bg-black/30 rounded-2xl">
                    <div class="bg-surface-raised rounded-xl shadow-xl p-6 mx-4 max-w-sm w-full">
                        <h3 class="text-lg font-semibold text-text-theme mb-2">Void Sale</h3>
                        <p class="text-sm text-text-secondary mb-6">
                            Are you sure you want to void {{ saleDetail.invoice_number }}? This will restock all items and mark the payment as voided. This action cannot be undone.
                        </p>
                        <div class="flex justify-end gap-3">
                            <button
                                @click="confirmVoid = false"
                                class="px-4 py-2 bg-surface-alt text-text-secondary text-sm font-medium rounded-lg hover:bg-border-light transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="handleVoid"
                                :disabled="voiding"
                                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50"
                            >
                                {{ voiding ? 'Voiding...' : 'Confirm Void' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
