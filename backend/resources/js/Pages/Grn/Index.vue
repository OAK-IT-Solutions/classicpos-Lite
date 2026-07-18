<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCrud } from '@/composables/useCrud';
import { useAuth } from '@/composables/useAuth';
import api from '@/composables/axios';

interface Grn { id: string; purchase_order_id: string; received_by: string; notes: string | null; created_at: string; purchase_order?: { id: string; po_number: string }; grn_items?: { id: string; product_id: string; quantity: number; unit_cost: number; batch_number: string | null; expiry_date: string | null; product?: { name: string } }[] }

const { items, loading, error, fetchAll, create } = useCrud<Grn>('/grn');
const auth = useAuth();
const selectedGRN = ref<Grn | null>(null);
const purchaseOrders = ref<{ id: string; po_number: string }[]>([]);
const products = ref<{ id: string; name: string }[]>([]);
const showForm = ref(false); const formLoading = ref(false); const formError = ref('');
const form = ref({ purchase_order_id: '', notes: '', items: [] as { product_id: string; quantity: number; unit_cost: number; batch_number: string; expiry_date: string }[] });
const newItem = ref({ product_id: '', quantity: 1, unit_cost: 0, batch_number: '', expiry_date: '' });

function formatDate(d: string | null): string { return d ? new Date(d).toLocaleDateString() : '—' }
function addItem() { if (!newItem.value.product_id) return; form.value.items.push({ ...newItem.value }); newItem.value = { product_id: '', quantity: 1, unit_cost: 0, batch_number: '', expiry_date: '' } }
function removeItem(i: number) { form.value.items.splice(i, 1) }
function resetForm() { form.value = { purchase_order_id: '', notes: '', items: [] }; formError.value = '' }

async function handleSubmit() {
    formLoading.value = true; formError.value = ''
    try {
        await create({ purchase_order_id: form.value.purchase_order_id, received_by: auth.user.value?.id, notes: form.value.notes || undefined, items: form.value.items.length > 0 ? form.value.items : undefined });
        showForm.value = false; resetForm(); await fetchAll();
    } catch (err: any) { const d = err?.response?.data; formError.value = d?.errors ? Object.values(d.errors).flat().join('; ') : d?.message || err?.message || 'Failed'; }
    finally { formLoading.value = false }
}

onMounted(async () => {
    await Promise.all([fetchAll(), api.get('/purchase-orders?per_page=200').then(r => purchaseOrders.value = r.data?.data ?? []).catch(() => {}), api.get('/products?per_page=200').then(r => products.value = r.data?.data ?? []).catch(() => {})])
})
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <div class="w-72 flex-shrink-0 space-y-3">
                <div class="flex items-center justify-between"><h1 class="text-lg font-bold text-text-theme">Goods Received</h1><button @click="showForm = true; resetForm()" class="p-1.5 bg-btn-primary text-white rounded-lg hover:bg-btn-primary-hover transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></button></div>
                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 160px);">
                    <button v-for="g in items" :key="g.id" @click="selectedGRN = g as unknown as Grn"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedGRN?.id === g.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ (g as any).purchase_order?.po_number || (g as any).purchase_order_id?.slice(0, 8) }}</p>
                        <p class="text-xs opacity-80 mt-0.5">{{ formatDate((g as any).created_at) }}</p>
                    </button>
                    <p v-if="!items.length" class="text-center py-8 text-text-tertiary text-sm">No goods received.</p>
                </div>
            </div>

            <div v-if="selectedGRN" class="flex-1 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div><h2 class="text-xl font-bold text-text-theme">{{ selectedGRN.purchase_order?.po_number || 'GRN' }}</h2><p class="text-sm text-text-tertiary mt-0.5">{{ formatDate(selectedGRN.created_at) }}</p></div>
                    <button @click="router.visit(`/purchase-orders`)" class="text-sm text-primary hover:underline">View PO</button>
                </div>
                <div v-if="selectedGRN.notes" class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Notes</p><p class="text-sm text-text-theme mt-1">{{ selectedGRN.notes }}</p></div>
                <div><h3 class="text-sm font-semibold text-text-secondary mb-2">Items</h3>
                    <div class="space-y-1">
                        <div v-for="item in selectedGRN.grn_items" :key="item.id" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm">
                            <span class="flex-1">{{ item.product?.name || item.product_id.slice(0, 8) }}</span>
                            <span>Qty: {{ item.quantity }}</span>
                            <span class="text-text-tertiary">Batch: {{ item.batch_number || '—' }}</span>
                            <span class="text-text-tertiary">Exp: {{ item.expiry_date || '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">Select a GRN.</div>

            <div v-if="showForm" class="fixed inset-0 z-40 flex justify-end">
                <div class="absolute inset-0 bg-black/30" @click="showForm = false" />
                <div class="relative z-50 w-full max-w-md bg-surface-raised shadow-xl flex flex-col overflow-y-auto p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-text-theme">New Goods Received Note</h2>
                    <form @submit.prevent="handleSubmit" class="space-y-4">
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Purchase Order</label><select v-model="form.purchase_order_id" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg"><option value="" disabled>Select PO</option><option v-for="po in purchaseOrders" :key="po.id" :value="po.id">{{ po.po_number }}</option></select></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Notes</label><textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div class="border-t pt-4"><h3 class="text-sm font-semibold text-text-theme mb-3">Line Items</h3>
                            <div v-for="(item, idx) in form.items" :key="idx" class="flex items-center gap-2 mb-2 p-2 bg-surface-alt rounded text-sm"><span class="flex-1">{{ products.find(p => p.id === item.product_id)?.name || item.product_id.slice(0, 8) }}</span><span>Qty: {{ item.quantity }}</span><span>@ {{ item.unit_cost }}</span><button @click="removeItem(idx)" class="text-red-500">&times;</button></div>
                            <div class="grid grid-cols-2 gap-2"><select v-model="newItem.product_id" class="px-3 py-2 text-sm border border-border-input rounded-lg"><option value="" disabled>Product</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                                <input v-model.number="newItem.quantity" type="number" min="0.001" step="any" placeholder="Qty" class="px-3 py-2 text-sm border border-border-input rounded-lg" />
                                <input v-model.number="newItem.unit_cost" type="number" min="0" step="0.01" placeholder="Cost" class="px-3 py-2 text-sm border border-border-input rounded-lg" />
                                <input v-model="newItem.batch_number" type="text" placeholder="Batch #" class="px-3 py-2 text-sm border border-border-input rounded-lg" />
                                <div class="col-span-2"><input v-model="newItem.expiry_date" type="date" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                            </div>
                            <button @click="addItem" type="button" class="mt-2 px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium w-full">+ Add Item</button>
                            <p v-if="!form.items.length" class="text-xs text-text-tertiary mt-2 text-center">No items.</p>
                        </div>
                        <div v-if="formError" class="p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-xs text-danger-theme">{{ formError }}</div>
                        <div class="flex gap-3">
                            <button type="submit" :disabled="formLoading" class="flex-1 px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium disabled:opacity-50">{{ formLoading ? 'Saving...' : 'Create GRN' }}</button>
                            <button type="button" @click="showForm = false" class="flex-1 px-4 py-2 border border-border-input rounded-lg text-sm font-medium text-text-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
