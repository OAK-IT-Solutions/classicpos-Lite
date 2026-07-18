<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';
import { useSales } from '@/composables/useSales';
import { Receipt, ArrowLeft, AlertCircle, Printer, Mail, ShieldCheck } from 'lucide-vue-next';

const { saleDetail, loading, error, fetchSaleDetail, voidSale } = useSales();
const page = usePage();

const saleId = computed(() => {
    const parts = page.url.split('/');
    return parts[parts.length - 1];
});

const emailing = ref(false);
const voiding = ref(false);
const confirmVoid = ref(false);

const canVoid = computed(() =>
    saleDetail.value && ['completed', 'pending', 'pending_sync'].includes(saleDetail.value.status)
);

const canEmail = computed(() =>
    saleDetail.value?.customer?.email
);

async function handleEmailInvoice() {
    if (!saleDetail.value) return;
    emailing.value = true;
    try {
        await api.post(`/sales/${saleDetail.value.id}/email-invoice`);
    } catch {
        //
    } finally {
        emailing.value = false;
    }
}

function printReceipt() {
    if (!saleDetail.value) return;
    const s = saleDetail.value;
    const itemsHtml = (s.items || []).map(item =>
        `<tr><td>${item.name}</td><td class="r">${item.qty}</td><td class="r">${item.price.toFixed(2)}</td><td class="r">${(item.qty * item.price).toFixed(2)}</td></tr>`
    ).join('');
    const win = window.open('', '_blank', 'width=400,height=600');
    if (!win) return;
    win.document.write(`<!DOCTYPE html>
<html><head><title>Receipt</title>
<style>
    body { font-family: 'Courier New', monospace; font-size: 12px; width: 280px; margin: 0 auto; padding: 16px; }
    h2 { text-align: center; margin: 0 0 4px; font-size: 14px; }
    .center { text-align: center; }
    .line { border-top: 1px dashed #999; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 2px 0; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .total-row td { border-top: 1px solid #999; padding-top: 4px; }
</style></head><body>
    <h2>${s.customer?.name || ''}</h2>
    <p class="center">${new Date(s.created_at).toLocaleString()}</p>
    <p class="center">${s.invoice_number}</p>
    <div class="line"></div>
    <table>${itemsHtml}</table>
    <div class="line"></div>
    <table>
        <tr><td>Subtotal</td><td class="r">${s.subtotal ? s.subtotal.toFixed(2) : s.total_amount.toFixed(2)}</td></tr>
        <tr><td>Tax</td><td class="r">${s.tax_amount ? s.tax_amount.toFixed(2) : '0.00'}</td></tr>
        <tr class="total-row"><td class="b">Total</td><td class="r b">${s.total_amount.toFixed(2)}</td></tr>
    </table>
    <div class="line"></div>
    <p class="center">${s.payment_method || ''}</p>
    <p class="center" style="margin-top:12px;font-size:10px;color:#999;">Thank you for your purchase!</p>
    ${s.efris_fdn ? `<div class="line"></div><p class="center" style="font-size:10px;">EFRIS FDN: ${s.efris_fdn}</p>${s.efris_verification_code ? `<p class="center" style="font-size:10px;">Verify: ${s.efris_verification_code}</p>` : ''}` : ''}
</body></html>`);
    win.document.close();
    setTimeout(() => { win.focus(); win.print(); }, 300);
}

onMounted(() => {
    if (saleId.value) {
        fetchSaleDetail(saleId.value).catch(() => {});
    }
});

async function handleVoid() {
    if (!saleDetail.value) return;
    voiding.value = true;
    confirmVoid.value = false;
    try {
        await voidSale(saleDetail.value.id);
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
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button
                    @click="router.visit('/sales')"
                    class="p-2 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-lg transition-colors"
                >
                    <ArrowLeft class="w-5 h-5" />
                </button>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <Receipt class="w-5 h-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">Sale Details</h1>
                    <p class="text-text-tertiary text-sm mt-0.5">{{ saleDetail?.invoice_number || 'Loading...' }}</p>
                </div>
            </div>
        </div>

        <div v-if="loading" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-12 text-center">
            <p class="text-text-tertiary text-sm">Loading sale details...</p>
        </div>

        <div v-else-if="error" class="p-6 bg-danger-light border border-danger-theme/20 rounded-xl flex items-start gap-3">
            <AlertCircle class="w-5 h-5 text-danger-theme flex-shrink-0 mt-0.5" />
            <p class="text-sm text-danger-theme">{{ error }}</p>
        </div>

        <div v-else-if="saleDetail" class="space-y-6">
            <!-- Actions bar -->
            <div class="flex items-center gap-3">
                <button @click="printReceipt"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-surface-raised border border-border-input rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt transition-colors">
                    <Printer class="w-4 h-4" />
                    Print Receipt
                </button>
                <button v-if="canEmail" @click="handleEmailInvoice" :disabled="emailing"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-surface-raised border border-border-input rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt transition-colors disabled:opacity-50">
                    <Mail class="w-4 h-4" />
                    {{ emailing ? 'Sending...' : 'Email Invoice' }}
                </button>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium mb-1">Invoice</p>
                    <p class="text-sm font-semibold text-text-theme">{{ saleDetail.invoice_number }}</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium mb-1">Status</p>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="statusClass(saleDetail.status)"
                    >
                        {{ saleDetail.status }}
                    </span>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium mb-1">Date</p>
                    <p class="text-sm font-semibold text-text-theme">{{ formatDate(saleDetail.created_at) }}</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium mb-1">Customer</p>
                    <p class="text-sm font-semibold text-text-theme">{{ saleDetail.customer?.name ?? 'Walk-in' }}</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium mb-1">Total Amount</p>
                    <p class="text-sm font-bold text-text-theme">{{ formatAmount(saleDetail.total_amount) }}</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium mb-1">Payment Method</p>
                    <p class="text-sm font-semibold text-text-theme">{{ saleDetail.payment_method || 'N/A' }}</p>
                </div>
            </div>

            <!-- EFRIS Fiscal Data -->
            <div v-if="saleDetail.efris_fdn || saleDetail.efris_fiscal_status" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                         :class="saleDetail.efris_fiscal_status === 'success' ? 'bg-green-100' : saleDetail.efris_fiscal_status === 'failed' ? 'bg-red-100' : 'bg-yellow-100'">
                        <ShieldCheck class="w-4 h-4"
                            :class="saleDetail.efris_fiscal_status === 'success' ? 'text-green-600' : saleDetail.efris_fiscal_status === 'failed' ? 'text-red-600' : 'text-yellow-600'" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-text-theme">EFRIS Fiscal Data</h3>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                              :class="saleDetail.efris_fiscal_status === 'success' ? 'bg-green-100 text-green-700' : saleDetail.efris_fiscal_status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'">
                            {{ saleDetail.efris_fiscal_status || 'pending' }}
                        </span>
                    </div>
                </div>
                <div v-if="saleDetail.efris_fdn" class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-text-tertiary text-xs">Fiscal Document Number</p>
                        <p class="font-mono font-semibold text-text-theme">{{ saleDetail.efris_fdn }}</p>
                    </div>
                    <div v-if="saleDetail.efris_verification_code">
                        <p class="text-text-tertiary text-xs">Verification Code</p>
                        <p class="font-mono font-semibold text-text-theme">{{ saleDetail.efris_verification_code }}</p>
                    </div>
                </div>
                <div v-if="saleDetail.efris_qr_code" class="mt-3 text-center">
                    <img :src="saleDetail.efris_qr_code" alt="EFRIS QR Code" class="inline-block w-24 h-24 border rounded" />
                    <p class="text-xs text-text-tertiary mt-1">EFRIS Verification QR Code</p>
                </div>
            </div>

            <!-- Line items -->
            <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                <div class="px-6 py-4 border-b border-border-theme">
                    <h2 class="text-lg font-semibold text-text-theme">Line Items</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-alt border-b border-border-theme">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Price</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in saleDetail.items" :key="item.product_id">
                                <td class="px-6 py-4 text-text-theme">{{ item.name }}</td>
                                <td class="px-6 py-4 text-right text-text-secondary">{{ item.qty }}</td>
                                <td class="px-6 py-4 text-right text-text-secondary">{{ formatAmount(item.price) }}</td>
                                <td class="px-6 py-4 text-right text-text-theme font-medium">{{ formatAmount(item.qty * item.price) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-alt border-t border-border-theme">
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-sm font-semibold text-text-secondary text-right">Total</td>
                                <td class="px-6 py-4 text-right text-sm font-bold text-text-theme">{{ formatAmount(saleDetail.total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Void action -->
            <div v-if="canVoid" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-text-theme">Void This Sale</h3>
                        <p class="text-xs text-text-tertiary mt-1">This will restock all items and mark the payment as voided.</p>
                    </div>
                    <button
                        @click="confirmVoid = true"
                        class="px-5 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors"
                    >
                        Void Sale
                    </button>
                </div>
            </div>
        </div>

        <!-- Void confirmation modal -->
        <div v-if="confirmVoid" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="confirmVoid = false" />
            <div class="relative bg-surface-raised rounded-xl shadow-xl p-6 mx-4 max-w-sm w-full">
                <h3 class="text-lg font-semibold text-text-theme mb-2">Void Sale</h3>
                <p class="text-sm text-text-secondary mb-6">
                    Are you sure you want to void {{ saleDetail?.invoice_number }}? This will restock all items and mark the payment as voided. This action cannot be undone.
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
    </AppLayout>
</template>
