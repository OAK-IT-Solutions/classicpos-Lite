<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import api from '@/composables/axios'

const receipts = ref<any[]>([])
const selectedReceipt = ref<any>(null)
const loading = ref(true)

function formatCurrency(v: number): string { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v) }
function formatDate(d: string): string { return new Date(d).toLocaleDateString() }

async function fetchReceipts() {
    loading.value = true
    try {
        const r = await api.get('/documents', { params: { type: 'invoice', per_page: '50' } })
        receipts.value = (r.data.data ?? []).filter((d: any) => ['paid', 'partial'].includes(d.status))
        if (!selectedReceipt.value && receipts.value.length) selectedReceipt.value = receipts.value[0]
    } finally { loading.value = false }
}

function printReceipt() {
    if (!selectedReceipt.value) return
    const d = selectedReceipt.value
    const itemsHtml = (d.items || []).map((i: any) =>
        `<tr><td>${i.description}</td><td class="r">${i.quantity}</td><td class="r">${i.unit_price.toFixed(2)}</td><td class="r">${i.total.toFixed(2)}</td></tr>`
    ).join('')
    const paymentsHtml = (d.payments || []).map((p: any) =>
        `<tr><td>${formatDate(p.payment_date)}</td><td class="r">${formatCurrency(p.amount)}</td><td class="capitalize">${p.method.replace(/_/g, ' ')}</td><td>${p.reference || '—'}</td></tr>`
    ).join('')
    const win = window.open('', '_blank', 'width=700,height=900')
    if (!win) return
    win.document.write(`<!DOCTYPE html><html><head><title>Receipt - ${d.document_number}</title>
    <style>body{font-family:Arial;padding:30px;max-width:700px;margin:0 auto}
    h1{font-size:28px;margin:0 0 4px;color:#16a34a}.paid{color:#16a34a;font-size:14px;font-weight:bold;margin:8px 0}
    .meta{color:#666;font-size:13px;margin:2px 0}
    table{width:100%;border-collapse:collapse;margin:20px 0}
    th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #ddd}
    .r{text-align:right}.capitalize{text-transform:capitalize}.b{font-weight:bold}
    .footer{margin-top:40px;font-size:12px;color:#999;text-align:center}
    .paid-badge{display:inline-block;background:#16a34a;color:white;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold}</style></head><body>
    <h1>RECEIPT</h1>
    <div class="paid"><span class="paid-badge">✓ PAID</span></div>
    <p class="meta">Invoice: ${d.document_number}</p>
    <p class="meta">Date: ${d.issue_date}</p>
    <p class="meta">Customer: ${d.customer?.name || 'N/A'}</p>
    <table><tr><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Total</th></tr>${itemsHtml}</table>
    <p class="r">Subtotal: ${formatCurrency(d.subtotal)}</p>
    <p class="r">Tax: ${formatCurrency(d.tax_amount)}</p>
    <p class="r b">Total: ${formatCurrency(d.total_amount)}</p>
    <p class="r" style="color:#16a34a">Paid: ${formatCurrency(d.paid_amount)}</p>
    ${paymentsHtml ? `<h3>Payments</h3><table><tr><th>Date</th><th class="r">Amount</th><th>Method</th><th>Reference</th></tr>${paymentsHtml}</table>` : ''}
    <p class="footer">Thank you for your business!</p></body></html>`)
    win.document.close(); win.print()
}

onMounted(fetchReceipts)
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <div class="w-72 flex-shrink-0 space-y-3">
                <h1 class="text-lg font-bold text-text-theme">Receipts</h1>
                <p class="text-xs text-text-tertiary">Paid invoices and payment records</p>
                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 180px);">
                    <button v-for="r in receipts" :key="r.id" @click="selectedReceipt = r"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedReceipt?.id === r.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ r.document_number }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs px-1.5 py-0.5 rounded font-medium capitalize" :class="r.status === 'paid' ? 'bg-success-light text-green-800' : 'bg-orange-100 text-orange-800'">{{ r.status }}</span>
                            <span class="text-xs opacity-80">{{ r.customer?.name?.slice(0, 14) || '' }}</span>
                            <span class="text-xs opacity-80">{{ formatCurrency(r.total_amount) }}</span>
                        </div>
                    </button>
                    <p v-if="!receipts.length" class="text-center py-8 text-text-tertiary text-sm">No paid invoices yet.</p>
                </div>
            </div>

            <div v-if="selectedReceipt" class="flex-1 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-text-theme">{{ selectedReceipt.document_number }}</h2>
                            <span class="text-xs px-2 py-0.5 rounded font-medium capitalize bg-success-light text-green-800">{{ selectedReceipt.status }}</span>
                        </div>
                        <p class="text-sm text-text-tertiary mt-1">{{ selectedReceipt.customer?.name || 'N/A' }} · {{ formatDate(selectedReceipt.issue_date) }}</p>
                    </div>
                    <button @click="printReceipt" class="px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover">Print Receipt</button>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Total</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedReceipt.total_amount) }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Paid</p><p class="text-lg font-bold text-green-600 mt-1">{{ formatCurrency(selectedReceipt.paid_amount) }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Balance</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedReceipt.total_amount - selectedReceipt.paid_amount) }}</p></div>
                </div>

                <div><h3 class="text-sm font-semibold text-text-secondary mb-2">Items</h3>
                    <div class="space-y-1">
                        <div v-for="item in selectedReceipt.items" :key="item.id" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm">
                            <span class="flex-1">{{ item.description }}</span>
                            <span>Qty: {{ item.quantity }}</span>
                            <span>@ {{ item.unit_price.toFixed(2) }}</span>
                            <span class="font-medium">{{ formatCurrency(item.total) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="selectedReceipt.payments?.length">
                    <h3 class="text-sm font-semibold text-text-secondary mb-2">Payments</h3>
                    <div class="space-y-1">
                        <div v-for="p in selectedReceipt.payments" :key="p.id" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm">
                            <span class="text-green-600 font-medium">{{ formatCurrency(p.amount) }}</span>
                            <span class="capitalize">{{ p.method.replace(/_/g, ' ') }}</span>
                            <span class="text-text-tertiary">{{ p.reference || '—' }}</span>
                            <span class="text-text-tertiary">{{ formatDate(p.payment_date) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button @click="printReceipt" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Print Receipt</button>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">No receipts yet. Pay an invoice to generate a receipt.</div>
        </div>
    </AppLayout>
</template>
