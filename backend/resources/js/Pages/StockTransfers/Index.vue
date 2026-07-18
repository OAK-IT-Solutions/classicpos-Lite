<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useCrud } from '@/composables/useCrud'
import DataTable from '@/Components/DataTable.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import api from '@/composables/axios'

interface StockTransfer { id: string; from_warehouse_id: string; to_warehouse_id: string; status: string; notes: string | null; transferred_at: string | null; created_at: string; from_warehouse?: { name: string }; to_warehouse?: { name: string }; items?: { id: string; product_id: string; quantity: number; product?: { name: string } }[] }
interface Supplier { id: string; name: string }
interface Product { id: string; name: string }
interface Branch { id: string; name: string }

const transferCrud = useCrud<StockTransfer>('/stock-transfers')
const { items, loading, error, fetchAll, create } = transferCrud
const selectedTransfer = ref<StockTransfer | null>(null)
const warehouses = ref<{ id: string; name: string }[]>([])
const products = ref<Product[]>([])
const showForm = ref(false); const formLoading = ref(false); const formError = ref('')
const form = ref({ from_warehouse_id: '', to_warehouse_id: '', notes: '', items: [] as { product_id: string; quantity: number }[] })
const newItem = ref({ product_id: '', quantity: 1 })

function formatDate(d: string | null): string { return d ? new Date(d).toLocaleDateString() : '—' }
function statusClass(s: string): string { const m: Record<string, string> = { pending: 'bg-yellow-100 text-yellow-800', in_transit: 'bg-blue-100 text-blue-800', completed: 'bg-success-light text-green-800', cancelled: 'bg-danger-light text-red-800' }; return m[s] || 'bg-surface-alt' }

async function loadWarehouses() { try { const r = await api.get('/warehouses'); warehouses.value = r.data?.data ?? [] } catch {} }
async function loadProducts() { try { const r = await api.get('/products?per_page=200'); products.value = r.data?.data ?? [] } catch {} }

function addItem() { if (!newItem.value.product_id) return; form.value.items.push({ ...newItem.value }); newItem.value = { product_id: '', quantity: 1 } }
function removeItem(i: number) { form.value.items.splice(i, 1) }
function resetForm() { form.value = { from_warehouse_id: '', to_warehouse_id: '', notes: '', items: [] }; formError.value = '' }

async function handleSubmit() {
    formLoading.value = true; formError.value = ''
    try {
        await create({ from_warehouse_id: form.value.from_warehouse_id, to_warehouse_id: form.value.to_warehouse_id, notes: form.value.notes || undefined, items: form.value.items.length > 0 ? form.value.items : undefined })
        showForm.value = false; resetForm(); await fetchAll()
    } catch (err: any) { const d = err?.response?.data; formError.value = d?.errors ? Object.values(d.errors).flat().join('; ') : d?.message || err?.message || 'Failed' }
    finally { formLoading.value = false }
}

async function completeTransfer(id: string) {
    if (!confirm('Complete this transfer?')) return
    try { await api.post(`/stock-transfers/${id}/complete`); await fetchAll() } catch (err: any) { alert(err?.response?.data?.message || 'Failed') }
}

async function cancelTransfer(id: string) {
    if (!confirm('Cancel this transfer?')) return
    try { await api.post(`/stock-transfers/${id}/cancel`); await fetchAll() } catch { alert('Failed to cancel') }
}

onMounted(async () => { await Promise.all([fetchAll(), loadWarehouses(), loadProducts()]) })
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <div class="w-72 flex-shrink-0 space-y-3">
                <div class="flex items-center justify-between"><h1 class="text-lg font-bold text-text-theme">Transfers</h1><button @click="showForm = true" class="p-1.5 bg-btn-primary text-white rounded-lg hover:bg-btn-primary-hover transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></button></div>
                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 160px);">
                    <button v-for="t in items" :key="t.id" @click="selectedTransfer = t"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedTransfer?.id === t.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ t.from_warehouse?.name?.slice(0, 14) || '' }} → {{ t.to_warehouse?.name?.slice(0, 14) || '' }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize" :class="statusClass(t.status)">{{ t.status }}</span>
                            <span class="text-xs opacity-80">{{ formatDate(t.created_at) }}</span>
                        </div>
                    </button>
                    <p v-if="!items.length" class="text-center py-8 text-text-tertiary text-sm">No transfers.</p>
                </div>
            </div>

            <div v-if="selectedTransfer" class="flex-1 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div><h2 class="text-xl font-bold text-text-theme">{{ selectedTransfer.from_warehouse?.name || 'Unknown' }} → {{ selectedTransfer.to_warehouse?.name || 'Unknown' }}</h2><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1 capitalize" :class="statusClass(selectedTransfer.status)">{{ selectedTransfer.status }}</span></div>
                    <p class="text-sm text-text-tertiary">{{ formatDate(selectedTransfer.created_at) }}</p>
                </div>

                <div v-if="selectedTransfer.notes" class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Notes</p><p class="text-sm text-text-theme mt-1">{{ selectedTransfer.notes }}</p></div>

                <div><h3 class="text-sm font-semibold text-text-secondary mb-2">Items</h3>
                    <div class="space-y-1">
                        <div v-for="item in selectedTransfer.items" :key="item.id" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm">
                            <span class="flex-1">{{ item.product?.name || item.product_id.slice(0, 8) }}</span>
                            <span>Qty: {{ item.quantity }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button v-if="selectedTransfer.status === 'pending' || selectedTransfer.status === 'in_transit'" @click="completeTransfer(selectedTransfer.id)" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Complete</button>
                    <button v-if="selectedTransfer.status === 'pending'" @click="cancelTransfer(selectedTransfer.id)" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Cancel</button>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">Select a transfer to view details.</div>

            <!-- Form Slide-over -->
            <div v-if="showForm" class="fixed inset-0 z-40 flex justify-end">
                <div class="absolute inset-0 bg-black/30" @click="showForm = false" />
                <div class="relative z-50 w-full max-w-md bg-surface-raised shadow-xl flex flex-col overflow-y-auto p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-text-theme">New Stock Transfer</h2>
                    <form @submit.prevent="handleSubmit" class="space-y-4">
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">From *</label><select v-model="form.from_warehouse_id" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="" disabled>Select source</option><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">To *</label><select v-model="form.to_warehouse_id" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="" disabled>Select destination</option><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Notes</label><textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div class="border-t pt-4"><h3 class="text-sm font-semibold text-text-theme mb-3">Items</h3>
                            <div v-for="(item, idx) in form.items" :key="idx" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm mb-2">
                                <span class="flex-1">{{ products.find(p => p.id === item.product_id)?.name || item.product_id.slice(0, 8) }}</span><span>Qty: {{ item.quantity }}</span>
                                <button @click="removeItem(idx)" class="text-red-500">&times;</button>
                            </div>
                            <div class="flex gap-2"><select v-model="newItem.product_id" class="flex-1 px-3 py-2 text-sm border border-border-input rounded-lg"><option value="" disabled>Select product</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                                <input v-model.number="newItem.quantity" type="number" min="0.001" step="any" class="w-20 px-3 py-2 text-sm border border-border-input rounded-lg" />
                                <button @click="addItem" type="button" class="px-3 py-2 bg-primary text-white rounded-lg text-sm">+</button>
                            </div>
                            <p v-if="!form.items.length" class="text-xs text-text-tertiary mt-2">No items added.</p>
                        </div>
                        <div v-if="formError" class="p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-xs text-danger-theme">{{ formError }}</div>
                        <div class="flex gap-3 pt-2">
                            <button type="submit" :disabled="formLoading" class="flex-1 px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium disabled:opacity-50">{{ formLoading ? 'Saving...' : 'Create Transfer' }}</button>
                            <button type="button" @click="showForm = false" class="flex-1 px-4 py-2 border border-border-input rounded-lg text-sm font-medium text-text-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
