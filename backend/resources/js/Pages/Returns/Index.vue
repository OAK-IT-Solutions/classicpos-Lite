<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useCrud } from '@/composables/useCrud'
import api from '@/composables/axios'
import AppLayout from '@/Layouts/AppLayout.vue'

interface OrderReturn { id: string; sale_id: string; branch_id: string; reason: string | null; status: string; refund_amount: number; created_at: string; sale?: { invoice_number?: string }; items?: { id: string; product_id: string; quantity: number; reason: string | null; condition: string; product?: { name: string } }[] }

const returnCrud = useCrud<OrderReturn>('/returns')
const { items, loading, error, fetchAll } = returnCrud
const selectedReturn = ref<OrderReturn | null>(null)

function formatDate(d: string): string { return new Date(d).toLocaleDateString() }
function formatCurrency(v: number): string { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v) }
function statusClass(s: string): string { const m: Record<string, string> = { pending: 'bg-yellow-100 text-yellow-800', approved: 'bg-success-light text-green-800', rejected: 'bg-danger-light text-red-800' }; return m[s] || 'bg-surface-alt text-gray-800' }

async function approveReturn(id: string) {
    if (!confirm('Approve this return?')) return
    try { await api.post(`/returns/${id}/approve`); await fetchAll() }
    catch (err: any) { alert(err?.response?.data?.message || 'Failed to approve return') }
}

onMounted(async () => { await fetchAll() })
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <div class="w-72 flex-shrink-0 space-y-3">
                <h1 class="text-lg font-bold text-text-theme">Returns</h1>
                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 120px);">
                    <button v-for="r in items" :key="r.id" @click="selectedReturn = r"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedReturn?.id === r.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ r.sale?.invoice_number || r.sale_id.slice(0, 8) }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize" :class="statusClass(r.status)">{{ r.status }}</span>
                            <span class="text-xs opacity-80">{{ formatDate(r.created_at) }}</span>
                        </div>
                    </button>
                    <p v-if="!items.length" class="text-center py-8 text-text-tertiary text-sm">No returns.</p>
                </div>
            </div>

            <div v-if="selectedReturn" class="flex-1 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-text-theme">{{ selectedReturn.sale?.invoice_number || 'Return' }}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1 capitalize" :class="statusClass(selectedReturn.status)">{{ selectedReturn.status }}</span>
                    </div>
                    <p class="text-sm text-text-tertiary">{{ formatDate(selectedReturn.created_at) }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Refund Amount</p><p class="text-lg font-bold text-text-theme mt-1">{{ formatCurrency(selectedReturn.refund_amount) || '—' }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Reason</p><p class="text-sm font-semibold text-text-theme mt-1">{{ selectedReturn.reason || '—' }}</p></div>
                </div>

                <div><h3 class="text-sm font-semibold text-text-secondary mb-2">Items</h3>
                    <div class="space-y-1">
                        <div v-for="item in selectedReturn.items" :key="item.id" class="flex items-center gap-2 p-2 bg-surface-alt rounded text-sm">
                            <span class="flex-1">{{ item.product?.name || item.product_id.slice(0, 8) }}</span>
                            <span>Qty: {{ item.quantity }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="item.condition === 'returnable' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">{{ item.condition }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button v-if="selectedReturn.status === 'pending'" @click="approveReturn(selectedReturn.id)" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">Approve</button>
                    <button @click="router.visit(`/sales/${selectedReturn.sale_id}`)" class="px-4 py-2 border border-border-input rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt transition-colors">View Sale</button>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">Select a return to view details.</div>
        </div>
    </AppLayout>
</template>
