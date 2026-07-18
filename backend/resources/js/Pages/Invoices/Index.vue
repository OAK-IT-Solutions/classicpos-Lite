<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import api from '@/composables/axios'
import { useAuth } from '@/composables/useAuth'

const auth = useAuth()
const documents = ref<any[]>([])
const selectedDoc = ref<any>(null)
const loading = ref(true)
const filterType = ref('')
const filterStatus = ref('')
const searchQuery = ref('')
const customers = ref<{ id: string; name: string }[]>([])
const products = ref<{ id: string; name: string; price: number }[]>([])
const showForm = ref(false)
const formLoading = ref(false)
const formError = ref('')
const formData = ref({
    document_type: 'quote', customer_id: '', issue_date: new Date().toISOString().split('T')[0],
    expiry_date: '', due_date: '', notes: '', terms_conditions: '',
    items: [] as { product_id: string; description: string; quantity: number; unit_price: number; discount: number; tax_rate: number }[],
})
const newItem = ref({ product_id: '', description: '', quantity: 1, unit_price: 0, discount: 0, tax_rate: 0 })
const showPaymentForm = ref(false)
const paymentData = ref({ amount: 0, method: 'cash', reference: '', payment_date: new Date().toISOString().split('T')[0], notes: '' })

function formatCurrency(v: number): string { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v) }
function formatDate(d: string): string { return new Date(d).toLocaleDateString() }

function statusClass(s: string): string {
    const m: Record<string, string> = {
        draft: 'bg-surface-alt text-text-secondary', sent: 'bg-blue-100 text-blue-800',
        accepted: 'bg-green-100 text-green-800', expired: 'bg-red-100 text-red-800',
        converted: 'bg-purple-100 text-purple-800',
        unpaid: 'bg-yellow-100 text-yellow-800', partial: 'bg-orange-100 text-orange-800',
        paid: 'bg-success-light text-green-800', cancelled: 'bg-red-100 text-red-800',
    }; return m[s] || 'bg-surface-alt'
}

async function fetchDocs() {
    loading.value = true
    try {
        const params: Record<string, string> = { per_page: '50' }
        if (filterType.value) params.type = filterType.value
        if (filterStatus.value) params.status = filterStatus.value
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
        const r = await api.get('/documents', { params })
        documents.value = r.data.data ?? []
        if (!selectedDoc.value && documents.value.length) selectedDoc.value = documents.value[0]
    } finally { loading.value = false }
}

async function loadFormData() {
    const [c, p] = await Promise.all([
        api.get('/customers').catch(() => ({ data: { data: [] } })),
        api.get('/products?per_page=200').catch(() => ({ data: { data: [] } })),
    ])
    customers.value = c.data?.data ?? []
    products.value = p.data?.data ?? []
}

function openAddForm(type: string) {
    formData.value = {
        document_type: type, customer_id: '', issue_date: new Date().toISOString().split('T')[0],
        expiry_date: '', due_date: '', notes: '', terms_conditions: '',
        items: [],
    }
    formError.value = ''; showForm.value = true; loadFormData()
}

function addItem() {
    if (!newItem.value.description) return
    formData.value.items.push({ ...newItem.value })
    newItem.value = { product_id: '', description: '', quantity: 1, unit_price: 0, discount: 0, tax_rate: 0 }
}

function removeItem(i: number) { formData.value.items.splice(i, 1) }

function onProductSelect(idx: number) {
    const p = products.value.find(x => x.id === formData.value.items[idx]?.product_id)
    if (p) {
        formData.value.items[idx].description = p.name
        formData.value.items[idx].unit_price = p.price
    }
}

async function handleSubmit() {
    formLoading.value = true; formError.value = ''
    try {
        const r = await api.post('/documents', formData.value)
        selectedDoc.value = r.data.data
        showForm.value = false; await fetchDocs()
    } catch (err: any) {
        const d = err?.response?.data
        formError.value = d?.errors ? Object.values(d.errors).flat().join('; ') : d?.message || err?.message || 'Failed'
    } finally { formLoading.value = false }
}

async function updateStatus(id: string, status: string) {
    try { await api.post(`/documents/${id}/status?status=${status}`); await fetchDocs() }
    catch (err: any) { alert(err?.response?.data?.error?.message || 'Failed') }
}

async function convertToInvoice(id: string) {
    try { const r = await api.post(`/documents/${id}/convert-to-invoice`); selectedDoc.value = r.data.data; await fetchDocs() }
    catch (err: any) { alert(err?.response?.data?.error?.message || 'Conversion failed') }
}

async function recordPayment() {
    if (!selectedDoc.value) return
    try {
        const params = new URLSearchParams(paymentData.value as any).toString()
        const r = await api.post(`/documents/${selectedDoc.value.id}/payments?${params}`)
        selectedDoc.value = r.data.data
        showPaymentForm.value = false
    } catch (err: any) { alert(err?.response?.data?.error?.message || 'Payment failed') }
}

async function deleteDoc(id: string) {
    if (!confirm('Delete this document?')) return
    try { await api.delete(`/documents/${id}`); selectedDoc.value = null; await fetchDocs() }
    catch { alert('Failed to delete') }
}

function printDoc() {
    if (!selectedDoc.value) return
    const d = selectedDoc.value
    const itemsHtml = (d.items || []).map((i: any) =>
        `<tr><td>${i.description}</td><td class="r">${i.quantity}</td><td class="r">${i.unit_price.toFixed(2)}</td><td class="r">${i.total.toFixed(2)}</td></tr>`
    ).join('')
    const win = window.open('', '_blank', 'width=600,height=800')
    if (!win) return
    win.document.write(`<!DOCTYPE html><html><head><title>${d.document_number}</title>
    <style>body{font-family:Arial;padding:40px;max-width:700px;margin:0 auto}
    h1{font-size:24px;margin:0 0 4px}.meta{color:#666;font-size:13px;margin:2px 0}
    table{width:100%;border-collapse:collapse;margin:20px 0}
    th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #ddd}
    .r{text-align:right}.b{font-weight:bold}.total{border-top:2px solid #333}
    .footer{margin-top:40px;font-size:12px;color:#999;text-align:center}</style></head><body>
    <h1>${d.document_type === 'quote' ? 'QUOTE' : 'INVOICE'}</h1>
    <p class="meta">${d.document_number}</p>
    <p class="meta">Date: ${d.issue_date}</p>
    <p class="meta">Customer: ${d.customer?.name || 'N/A'}</p>
    <table><tr><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Total</th></tr>${itemsHtml}</table>
    <p class="r">Subtotal: ${formatCurrency(d.subtotal)}</p>
    <p class="r">Tax: ${formatCurrency(d.tax_amount)}</p>
    <p class="r b">Total: ${formatCurrency(d.total_amount)}</p>
    <p class="footer">Thank you for your business!</p></body></html>`)
    win.document.close(); win.print()
}

onMounted(fetchDocs)
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <div class="w-72 flex-shrink-0 space-y-3">
                <div class="flex items-center justify-between"><h1 class="text-lg font-bold text-text-theme">Quotes & Invoices</h1>
                    <div class="flex gap-1">
                        <button @click="openAddForm('quote')" class="p-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700">+ Quote</button>
                        <button @click="openAddForm('invoice')" class="p-1.5 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-700">+ Invoice</button>
                    </div>
                </div>
                <div class="flex gap-2">
                    <select v-model="filterType" @change="fetchDocs" class="flex-1 px-2 py-1.5 text-xs border border-border-input rounded-lg"><option value="">All</option><option value="quote">Quotes</option><option value="invoice">Invoices</option></select>
                    <select v-model="filterStatus" @change="fetchDocs" class="flex-1 px-2 py-1.5 text-xs border border-border-input rounded-lg"><option value="">All</option><option value="draft">Draft</option><option value="sent">Sent</option><option value="accepted">Accepted</option><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select>
                </div>
                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 200px);">
                    <button v-for="d in documents" :key="d.id" @click="selectedDoc = d"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedDoc?.id === d.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ d.document_number }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs px-1.5 py-0.5 rounded font-medium uppercase" :class="d.document_type === 'quote' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">{{ d.document_type }}</span>
                            <span class="text-xs px-1.5 py-0.5 rounded font-medium capitalize" :class="statusClass(d.status)">{{ d.status }}</span>
                            <span class="text-xs opacity-80">{{ d.customer?.name?.slice(0, 12) || '' }}</span>
                        </div>
                    </button>
                    <p v-if="!documents.length" class="text-center py-8 text-text-tertiary text-sm">No documents yet.</p>
                </div>
            </div>

            <div v-if="selectedDoc" class="flex-1 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-text-theme">{{ selectedDoc.document_number }}</h2>
                            <span class="text-xs px-2 py-0.5 rounded font-medium uppercase" :class="selectedDoc.document_type === 'quote' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">{{ selectedDoc.document_type }}</span>
                            <span class="text-xs px-2 py-0.5 rounded font-medium capitalize" :class="statusClass(selectedDoc.status)">{{ selectedDoc.status }}</span>
                        </div>
                        <p class="text-sm text-text-tertiary mt-1">{{ selectedDoc.customer?.name || 'Walk-in' }} · {{ formatDate(selectedDoc.issue_date) }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button @click="printDoc" class="px-3 py-1.5 border border-border-input rounded-lg text-xs font-medium text-text-secondary hover:bg-surface-alt">Print</button>
                        <button @click="deleteDoc(selectedDoc.id)" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-medium hover:bg-red-700">Delete</button>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4">
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Total</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedDoc.total_amount) }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Paid</p><p class="text-lg font-bold text-green-600 mt-1">{{ formatCurrency(selectedDoc.paid_amount) }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Balance</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedDoc.total_amount - selectedDoc.paid_amount) }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Items</p><p class="text-lg font-bold text-text-theme mt-1">{{ selectedDoc.items?.length || 0 }}</p></div>
                </div>

                <div><h3 class="text-sm font-semibold text-text-secondary mb-2">Line Items</h3>
                    <div class="space-y-1">
                        <div v-for="item in selectedDoc.items" :key="item.id" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm">
                            <span class="flex-1">{{ item.description }}</span>
                            <span>Qty: {{ item.quantity }}</span>
                            <span>@ {{ item.unit_price.toFixed(2) }}</span>
                            <span class="font-medium">{{ formatCurrency(item.total) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="selectedDoc.notes" class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Notes</p><p class="text-sm text-text-theme mt-1">{{ selectedDoc.notes }}</p></div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <!-- Quote actions -->
                    <button v-if="selectedDoc.document_type === 'quote' && selectedDoc.status === 'draft'" @click="updateStatus(selectedDoc.id, 'sent')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Mark Sent</button>
                    <button v-if="selectedDoc.document_type === 'quote' && selectedDoc.status === 'sent'" @click="updateStatus(selectedDoc.id, 'accepted')" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Accept Quote</button>
                    <button v-if="selectedDoc.document_type === 'quote' && selectedDoc.status === 'accepted'" @click="convertToInvoice(selectedDoc.id)" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover">Convert to Invoice</button>
                    <button v-if="selectedDoc.document_type === 'quote' && ['draft','sent'].includes(selectedDoc.status)" @click="updateStatus(selectedDoc.id, 'expired')" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Mark Expired</button>

                    <!-- Invoice actions -->
                    <button v-if="selectedDoc.document_type === 'invoice' && ['unpaid','partial'].includes(selectedDoc.status)" @click="showPaymentForm = true; paymentData.amount = selectedDoc.total_amount - selectedDoc.paid_amount" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Record Payment</button>
                    <button v-if="selectedDoc.document_type === 'invoice' && ['unpaid','partial'].includes(selectedDoc.status)" @click="updateStatus(selectedDoc.id, 'cancelled')" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Cancel Invoice</button>
                </div>

                <div v-if="selectedDoc.payments?.length" class="border-t pt-4">
                    <h3 class="text-sm font-semibold text-text-secondary mb-2">Payment History</h3>
                    <div v-for="p in selectedDoc.payments" :key="p.id" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm">
                        <span class="text-green-600 font-medium">{{ formatCurrency(p.amount) }}</span>
                        <span class="capitalize">{{ p.method.replace(/_/g, ' ') }}</span>
                        <span class="text-text-tertiary">{{ p.reference || '—' }}</span>
                        <span class="text-text-tertiary">{{ formatDate(p.payment_date) }}</span>
                    </div>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">Create a quote or invoice to get started.</div>
        </div>

        <!-- Form Slide-over -->
        <div v-if="showForm" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="showForm = false" />
            <div class="relative z-50 w-full max-w-2xl bg-surface-raised shadow-xl flex flex-col overflow-y-auto p-6 space-y-4">
                <h2 class="text-lg font-semibold text-text-theme">New {{ formData.document_type === 'quote' ? 'Quote' : 'Invoice' }}</h2>
                <form @submit.prevent="handleSubmit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Customer</label>
                            <select v-model="formData.customer_id" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="">— None —</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Issue Date *</label><input v-model="formData.issue_date" type="date" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div v-if="formData.document_type === 'quote'"><label class="block text-sm font-medium text-text-secondary mb-1">Expiry Date</label><input v-model="formData.expiry_date" type="date" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div v-if="formData.document_type === 'invoice'"><label class="block text-sm font-medium text-text-secondary mb-1">Due Date</label><input v-model="formData.due_date" type="date" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                    </div>

                    <div class="border-t pt-4"><h3 class="text-sm font-semibold text-text-theme mb-3">Line Items</h3>
                        <div v-for="(item, idx) in formData.items" :key="idx" class="flex items-center gap-2 mb-2 p-2 bg-surface-alt rounded text-sm flex-wrap">
                            <span class="flex-[2]">{{ item.description }}</span>
                            <span>Qty: {{ item.quantity }}</span>
                            <span>@ {{ item.unit_price.toFixed(2) }}</span>
                            <span class="font-medium">={{ (item.quantity * item.unit_price).toFixed(2) }}</span>
                            <button @click="removeItem(idx)" class="text-red-500">&times;</button>
                        </div>
                        <div class="grid grid-cols-5 gap-2">
                            <div class="col-span-2"><select v-model="newItem.product_id" @change="onProductSelect(-1)" class="w-full px-2 py-1.5 text-sm border border-border-input rounded-lg"><option value="">Select product</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
                            <div class="col-span-2"><input v-model="newItem.description" type="text" placeholder="Description" class="w-full px-2 py-1.5 text-sm border border-border-input rounded-lg" /></div>
                            <div><button @click="addItem" type="button" class="w-full px-2 py-1.5 bg-primary text-white rounded-lg text-sm">+</button></div>
                            <div><input v-model.number="newItem.quantity" type="number" min="0.001" step="any" placeholder="Qty" class="w-full px-2 py-1.5 text-sm border border-border-input rounded-lg" /></div>
                            <div><input v-model.number="newItem.unit_price" type="number" min="0" step="0.01" placeholder="Price" class="w-full px-2 py-1.5 text-sm border border-border-input rounded-lg" /></div>
                            <div><input v-model.number="newItem.discount" type="number" min="0" step="0.01" placeholder="Disc" class="w-full px-2 py-1.5 text-sm border border-border-input rounded-lg" /></div>
                            <div><input v-model.number="newItem.tax_rate" type="number" min="0" step="0.01" placeholder="Tax %" class="w-full px-2 py-1.5 text-sm border border-border-input rounded-lg" /></div>
                        </div>
                        <p v-if="!formData.items.length" class="text-xs text-text-tertiary mt-2">Add at least one item.</p>
                    </div>

                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Notes</label><textarea v-model="formData.notes" rows="2" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Terms & Conditions</label><textarea v-model="formData.terms_conditions" rows="2" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>

                    <div v-if="formError" class="p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-xs text-danger-theme">{{ formError }}</div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" :disabled="formLoading" class="flex-1 px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium disabled:opacity-50">{{ formLoading ? 'Saving...' : 'Create' }}</button>
                        <button type="button" @click="showForm = false" class="flex-1 px-4 py-2 border border-border-input rounded-lg text-sm font-medium text-text-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment form -->
        <div v-if="showPaymentForm" class="fixed inset-0 z-40 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" @click="showPaymentForm = false" />
            <div class="relative bg-surface-raised rounded-xl shadow-xl p-6 max-w-sm w-full mx-4 space-y-4">
                <h2 class="text-lg font-semibold text-text-theme">Record Payment</h2>
                <div><label class="block text-sm font-medium text-text-secondary mb-1">Amount *</label><input v-model.number="paymentData.amount" type="number" min="0.01" step="0.01" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                <div><label class="block text-sm font-medium text-text-secondary mb-1">Method *</label><select v-model="paymentData.method" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_money">Mobile Money</option><option value="cheque">Cheque</option></select></div>
                <div><label class="block text-sm font-medium text-text-secondary mb-1">Reference</label><input v-model="paymentData.reference" type="text" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                <div><label class="block text-sm font-medium text-text-secondary mb-1">Date *</label><input v-model="paymentData.payment_date" type="date" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                <div class="flex gap-3 pt-2">
                    <button @click="recordPayment" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Record Payment</button>
                    <button @click="showPaymentForm = false" class="flex-1 px-4 py-2 border border-border-input rounded-lg text-sm font-medium text-text-secondary">Cancel</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
