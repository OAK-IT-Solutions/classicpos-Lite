<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import api from '@/composables/axios'
import { Plus, Pencil, Trash2, DollarSign, AlertCircle } from 'lucide-vue-next'

interface Expense {
    id: string; payee: string; amount: number; method: string; category: string
    reference: string | null; expense_date: string; notes: string | null
    purchase_order_id: string | null; created_at: string
}

const categories = [
    'Inventory Purchase', 'Rent', 'Utilities', 'Wages & Salaries', 'Maintenance',
    'Transport', 'Marketing', 'Insurance', 'Licenses & Permits', 'Office Supplies',
    'Professional Fees', 'Taxes', 'Other',
]

const expenses = ref<Expense[]>([])
const summary = ref({ total: 0, month_total: 0, by_category: [] as any[] })
const loading = ref(true)
const error = ref('')
const selectedExpense = ref<Expense | null>(null)
const searchQuery = ref('')
const filterCategory = ref('')
const filterMethod = ref('')
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const suppliers = ref<{ id: string; name: string }[]>([])
const purchaseOrders = ref<{ id: string; po_number: string; supplier?: { name: string } }[]>([])
const showForm = ref(false)
const editingExpense = ref<Expense | null>(null)
const formLoading = ref(false)
const formError = ref('')
const formData = ref({ payee: '', amount: 0, method: 'cash', category: 'Other', reference: '', expense_date: new Date().toISOString().split('T')[0], notes: '', purchase_order_id: '' })

function formatCurrency(v: number): string { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v) }
function formatDate(d: string): string { return new Date(d).toLocaleDateString() }

async function fetchExpenses(page = 1) {
    loading.value = true; error.value = ''
    try {
        const params: Record<string, string> = { page: String(page), per_page: '20' }
        if (filterCategory.value) params.category = filterCategory.value
        if (filterMethod.value) params.method = filterMethod.value
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
        const [expRes, sumRes] = await Promise.all([
            api.get('/expenses', { params }),
            api.get('/expenses/summary').catch(() => ({ data: { data: { total: 0, month_total: 0, by_category: [] } } })),
        ])
        expenses.value = expRes.data.data ?? []
        pagination.value = { current_page: expRes.data.current_page, last_page: expRes.data.last_page, total: expRes.data.total }
        summary.value = sumRes.data.data
        if (!selectedExpense.value && expenses.value.length) selectedExpense.value = expenses.value[0]
    } catch (err: any) { error.value = err?.message || 'Failed to load expenses' } finally { loading.value = false }
}

async function loadFormData() {
    const [supRes, poRes] = await Promise.all([
        api.get('/suppliers').catch(() => ({ data: { data: [] } })),
        api.get('/purchase-orders').catch(() => ({ data: { data: [] } })),
    ])
    suppliers.value = supRes.data?.data ?? []
    purchaseOrders.value = poRes.data?.data ?? []
}

function openAddForm() {
    editingExpense.value = null
    formData.value = { payee: '', amount: 0, method: 'cash', category: 'Other', reference: '', expense_date: new Date().toISOString().split('T')[0], notes: '', purchase_order_id: '' }
    formError.value = ''; showForm.value = true; loadFormData()
}

function openEditForm() {
    if (!selectedExpense.value) return
    const e = selectedExpense.value
    editingExpense.value = e
    formData.value = { payee: e.payee, amount: e.amount, method: e.method, category: e.category, reference: e.reference || '', expense_date: e.expense_date, notes: e.notes || '', purchase_order_id: e.purchase_order_id || '' }
    formError.value = ''; showForm.value = true
}

async function handleSubmit() {
    formLoading.value = true; formError.value = ''
    try {
        const payload = { ...formData.value }
        if (!payload.reference) delete payload.reference
        if (!payload.notes) delete payload.notes
        if (!payload.purchase_order_id) delete payload.purchase_order_id
        if (editingExpense.value) { await api.put(`/expenses/${editingExpense.value.id}`, payload) }
        else { await api.post('/expenses', payload) }
        showForm.value = false; await fetchExpenses(pagination.value.current_page)
    } catch (err: any) {
        const data = err?.response?.data
        formError.value = data?.errors ? Object.values(data.errors).flat().join('; ') : data?.message || err?.message || 'Failed'
    } finally { formLoading.value = false }
}

async function handleDelete(id: string) {
    if (!confirm('Delete this expense?')) return
    try { await api.delete(`/expenses/${id}`); if (selectedExpense.value?.id === id) selectedExpense.value = null; await fetchExpenses() }
    catch { alert('Failed to delete.') }
}

onMounted(() => fetchExpenses())
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <!-- Sidebar -->
            <div class="w-72 flex-shrink-0 space-y-3">
                <div class="flex items-center justify-between">
                    <h1 class="text-lg font-bold text-text-theme">Payments</h1>
                    <button @click="openAddForm" class="p-1.5 bg-btn-primary text-white rounded-lg hover:bg-btn-primary-hover transition-colors"><Plus class="w-4 h-4" /></button>
                </div>
                <div class="grid grid-cols-3 gap-2 text-xs bg-surface-raised rounded-xl p-3 border border-border-theme">
                    <div><p class="text-text-tertiary">Total</p><p class="font-bold text-text-theme">{{ formatCurrency(summary.total) }}</p></div>
                    <div><p class="text-text-tertiary">Month</p><p class="font-bold text-text-theme">{{ formatCurrency(summary.month_total) }}</p></div>
                    <div><p class="text-text-tertiary">Count</p><p class="font-bold text-text-theme">{{ pagination.total }}</p></div>
                </div>
                <input v-model="searchQuery" type="text" placeholder="Search..." @input="fetchExpenses()" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring" />
                <div class="flex gap-2">
                    <select v-model="filterCategory" @change="fetchExpenses()" class="flex-1 px-2 py-1.5 text-xs border border-border-input rounded-lg"><option value="">All</option><option v-for="c in categories" :key="c" :value="c">{{ c }}</option></select>
                    <select v-model="filterMethod" @change="fetchExpenses()" class="w-24 px-2 py-1.5 text-xs border border-border-input rounded-lg"><option value="">All</option><option value="cash">Cash</option><option value="bank_transfer">Bank</option><option value="mobile_money">Mobile</option><option value="cheque">Cheque</option></select>
                </div>
                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 280px);">
                    <button v-for="e in expenses" :key="e.id" @click="selectedExpense = e"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedExpense?.id === e.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ e.payee }}</p>
                        <p class="text-xs opacity-80 mt-0.5">{{ formatCurrency(e.amount) }} · {{ formatDate(e.expense_date) }}</p>
                    </button>
                    <p v-if="!expenses.length" class="text-center py-8 text-text-tertiary text-sm">No payments recorded.</p>
                </div>
            </div>

            <!-- Detail -->
            <div v-if="selectedExpense" class="flex-1 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-text-theme">{{ selectedExpense.payee }}</h2>
                        <p class="text-sm text-text-tertiary mt-0.5">{{ formatDate(selectedExpense.expense_date) }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ selectedExpense.category }}</span>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Amount</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedExpense.amount) }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Method</p><p class="text-sm font-semibold text-text-theme mt-1 capitalize">{{ selectedExpense.method.replace(/_/g, ' ') }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Reference</p><p class="text-sm font-semibold text-text-theme mt-1">{{ selectedExpense.reference || '—' }}</p></div>
                </div>

                <div v-if="selectedExpense.notes"><p class="text-xs text-text-tertiary font-medium mb-1">Notes</p><p class="text-sm text-text-theme">{{ selectedExpense.notes }}</p></div>

                <div class="flex gap-2 pt-2">
                    <button @click="openEditForm" class="px-4 py-2 bg-surface-raised border border-border-input rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt transition-colors"><Pencil class="w-4 h-4 inline mr-1" />Edit</button>
                    <button @click="handleDelete(selectedExpense.id)" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"><Trash2 class="w-4 h-4 inline mr-1" />Delete</button>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">Select a payment to view details.</div>
        </div>

        <!-- Form Slide-over -->
        <div v-if="showForm" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="showForm = false" />
            <div class="relative z-50 w-full max-w-md bg-surface-raised shadow-xl flex flex-col overflow-y-auto p-6 space-y-4">
                <h2 class="text-lg font-semibold text-text-theme">{{ editingExpense ? 'Edit Payment' : 'New Payment' }}</h2>
                <form @submit.prevent="handleSubmit" class="space-y-4">
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Payee *</label><input v-model="formData.payee" type="text" required list="supplier-list" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /><datalist id="supplier-list"><option v-for="s in suppliers" :key="s.id" :value="s.name" /></datalist></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Amount *</label><input v-model.number="formData.amount" type="number" min="0" step="0.01" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Date *</label><input v-model="formData.expense_date" type="date" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                    </div>
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Category *</label><select v-model="formData.category" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option v-for="c in categories" :key="c" :value="c">{{ c }}</option></select></div>
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Method *</label><select v-model="formData.method" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_money">Mobile Money</option><option value="cheque">Cheque</option></select></div>
                    <div v-if="formData.category === 'Inventory Purchase' && purchaseOrders.length"><label class="block text-sm font-medium text-text-secondary mb-1">Purchase Order</label><select v-model="formData.purchase_order_id" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="">— None —</option><option v-for="po in purchaseOrders" :key="po.id" :value="po.id">{{ po.po_number }} {{ po.supplier?.name ? '- ' + po.supplier.name : '' }}</option></select></div>
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Reference</label><input v-model="formData.reference" type="text" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Notes</label><textarea v-model="formData.notes" rows="2" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                    <div v-if="formError" class="p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-xs text-danger-theme">{{ formError }}</div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" :disabled="formLoading" class="flex-1 px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50">{{ formLoading ? 'Saving...' : editingExpense ? 'Save Changes' : 'Record Payment' }}</button>
                        <button type="button" @click="showForm = false" class="flex-1 px-4 py-2 border border-border-input rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
