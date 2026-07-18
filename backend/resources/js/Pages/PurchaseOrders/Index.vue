<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useCrud } from '@/composables/useCrud'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import api from '@/composables/axios'

interface PO { id: string; supplier_id: string; branch_id: string; po_number: string; status: string; total_amount: number; notes: string | null; supplier?: { name: string }; branch?: { name: string }; items?: { id?: string; product_id: string; quantity: number; unit_cost: number; product?: { name: string } }[] }
interface Product { id: string; name: string }

const poCrud = useCrud<PO>('/purchase-orders')
const { items, loading, error, fetchAll, create } = poCrud
const selectedPO = ref<PO | null>(null)
const suppliers = ref<{ id: string; name: string }[]>([])
const products = ref<Product[]>([])
const branches = ref<{ id: string; name: string }[]>([])
const showForm = ref(false); const formLoading = ref(false); const formError = ref('')
const form = ref({ supplier_id: '', branch_id: '', po_number: '', notes: '', items: [] as { product_id: string; quantity: number; unit_cost: number }[] })
const newItem = ref({ product_id: '', quantity: 1, unit_cost: 0 })

function formatCurrency(v: number): string { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v) }
function statusClass(s: string): string { const m: Record<string, string> = { draft: 'bg-surface-alt text-text-secondary', pending: 'bg-yellow-100 text-yellow-800', approved: 'bg-blue-100 text-primary', received: 'bg-success-light text-success-theme', cancelled: 'bg-danger-light text-danger-theme' }; return m[s] || 'bg-surface-alt' }

async function transitionStatus(id: string, status: string) {
    try { await api.put(`/purchase-orders/${id}/status`, { status }); await fetchAll(); if (selectedPO.value?.id === id) selectedPO.value!.status = status }
    catch (err: any) { alert(err?.response?.data?.error?.message || 'Failed') }
}

function addItem() { if (!newItem.value.product_id) return; form.value.items.push({ ...newItem.value }); newItem.value = { product_id: '', quantity: 1, unit_cost: 0 } }
function removeItem(i: number) { form.value.items.splice(i, 1) }
function resetForm() { form.value = { supplier_id: '', branch_id: '', po_number: 'PO-' + Date.now().toString(36).toUpperCase(), notes: '', items: [] }; formError.value = '' }
async function handleSubmit() {
    formLoading.value = true; formError.value = ''
    try { await create({ supplier_id: form.value.supplier_id, branch_id: form.value.branch_id, po_number: form.value.po_number, notes: form.value.notes || undefined, items: form.value.items.length > 0 ? form.value.items : undefined }); showForm.value = false; resetForm(); await fetchAll() }
    catch (err: any) { const d = err?.response?.data; formError.value = d?.errors ? Object.values(d.errors).flat().join('; ') : d?.message || err?.message || 'Failed' }
    finally { formLoading.value = false }
}

onMounted(async () => {
    await Promise.all([fetchAll(), api.get('/suppliers').then(r => suppliers.value = r.data?.data ?? []).catch(() => {}), api.get('/products?per_page=200').then(r => products.value = r.data?.data ?? []).catch(() => {}), api.get('/branches').then(r => branches.value = r.data?.data ?? []).catch(() => {})])
})
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <div class="w-72 flex-shrink-0 space-y-3">
                <div class="flex items-center justify-between"><h1 class="text-lg font-bold text-text-theme">Purchase Orders</h1><button @click="showForm = true; resetForm()" class="p-1.5 bg-btn-primary text-white rounded-lg hover:bg-btn-primary-hover transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></button></div>
                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 160px);">
                    <button v-for="po in items" :key="po.id" @click="selectedPO = po as unknown as PO"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedPO?.id === po.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ (po as any).po_number }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize" :class="statusClass((po as any).status)">{{ (po as any).status }}</span>
                            <span class="text-xs opacity-80">{{ (po as any).supplier?.name?.slice(0, 20) || '' }}</span>
                        </div>
                    </button>
                    <p v-if="!items.length" class="text-center py-8 text-text-tertiary text-sm">No purchase orders.</p>
                </div>
            </div>

            <div v-if="selectedPO" class="flex-1 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div><h2 class="text-xl font-bold text-text-theme">{{ selectedPO.po_number }}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1 capitalize" :class="statusClass(selectedPO.status)">{{ selectedPO.status }}</span>
                    </div>
                    <div class="text-right text-sm text-text-tertiary"><p>{{ selectedPO.supplier?.name || '—' }}</p><p>{{ selectedPO.branch?.name || '—' }}</p></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Total</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedPO.total_amount) }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Notes</p><p class="text-sm text-text-theme mt-1">{{ selectedPO.notes || '—' }}</p></div>
                </div>

                <div><h3 class="text-sm font-semibold text-text-secondary mb-2">Line Items</h3>
                    <div class="space-y-1">
                        <div v-for="item in selectedPO.items" :key="item.id" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm">
                            <span class="flex-1">{{ item.product?.name || item.product_id.slice(0, 8) }}</span>
                            <span>Qty: {{ item.quantity }}</span>
                            <span>@ {{ item.unit_cost }}</span>
                            <span class="font-medium">= {{ formatCurrency(item.quantity * item.unit_cost) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button v-if="selectedPO.status === 'draft'" @click="transitionStatus(selectedPO.id, 'pending')" class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-medium hover:bg-yellow-700 transition-colors">Submit (Pending)</button>
                    <button v-if="selectedPO.status === 'pending'" @click="transitionStatus(selectedPO.id, 'approved')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Approve</button>
                    <button v-if="selectedPO.status === 'approved'" @click="transitionStatus(selectedPO.id, 'received')" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">Receive</button>
                    <button v-if="['draft', 'pending', 'approved'].includes(selectedPO.status)" @click="transitionStatus(selectedPO.id, 'cancelled')" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">Cancel</button>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">Select a PO.</div>

            <FormSlideOver :visible="showForm" title="New Purchase Order" :loading="formLoading" :error="formError" @close="showForm = false; resetForm()" @submit="handleSubmit">
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">PO Number</label><input v-model="form.po_number" type="text" readonly class="w-full px-3 py-2 text-sm bg-surface-alt border border-border-input rounded-lg" /></div>
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Supplier</label><select v-model="form.supplier_id" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="" disabled>Select</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Branch</label><select v-model="form.branch_id" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="" disabled>Select</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select></div>
                    <div><label class="block text-sm font-medium text-text-secondary mb-1">Notes</label><textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                    <div class="border-t pt-4"><h3 class="text-sm font-semibold text-text-theme mb-3">Line Items</h3>
                        <div v-for="(it, idx) in form.items" :key="idx" class="flex items-center gap-2 mb-2 p-2 bg-surface-alt rounded text-sm"><span class="flex-1">{{ products.find(p => p.id === it.product_id)?.name || it.product_id.slice(0, 8) }}</span><span>Qty: {{ it.quantity }}</span><span>@ {{ it.unit_cost }}</span><button @click="removeItem(idx)" class="text-red-500">&times;</button></div>
                        <div class="grid grid-cols-4 gap-2">
                            <div class="col-span-2"><select v-model="newItem.product_id" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="" disabled>Product</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
                            <div><input v-model.number="newItem.quantity" type="number" min="0.001" step="any" placeholder="Qty" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                            <div><input v-model.number="newItem.unit_cost" type="number" min="0" step="0.01" placeholder="Cost" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        </div>
                        <button @click="addItem" type="button" class="mt-2 px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium w-full">+ Add Item</button>
                        <p v-if="!form.items.length" class="text-xs text-text-tertiary mt-2 text-center">Add items.</p>
                    </div>
                </div>
            </FormSlideOver>
        </div>
    </AppLayout>
</template>
