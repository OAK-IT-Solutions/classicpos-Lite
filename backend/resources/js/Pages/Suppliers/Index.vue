<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCrud } from '@/composables/useCrud';
import api from '@/composables/axios';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';

interface Supplier { id: string; name: string; contact_person: string | null; phone: string; email: string | null; address: string | null; is_active: boolean }

const { items, loading, error, pagination, fetchAll, create, update, destroy } = useCrud<Supplier>('/suppliers');
const selectedSupplier = ref<Supplier | null>(null);
const searchQuery = ref('');
const showForm = ref(false);
const editingItem = ref<Supplier | null>(null);
const form = ref({ name: '', contact_person: '', phone: '', email: '', address: '' });
const poCounts = ref<Record<string, number>>({});

function applySearch() { const p: Record<string, string> = {}; if (searchQuery.value.trim()) p.search = searchQuery.value.trim(); fetchAll(1, p) }
function openAdd() { editingItem.value = null; form.value = { name: '', contact_person: '', phone: '', email: '', address: '' }; showForm.value = true }
function openEdit() { if (!selectedSupplier.value) return; const s = selectedSupplier.value; editingItem.value = s; form.value = { name: s.name, contact_person: s.contact_person || '', phone: s.phone, email: s.email || '', address: s.address || '' }; showForm.value = true }
async function submit() {
    if (editingItem.value) { await update(editingItem.value.id, form.value) } else { await create(form.value) }
    showForm.value = false; await applySearch()
}
async function handleDelete(id: string) { if (!confirm('Delete this supplier?')) return; await destroy(id); if (selectedSupplier.value?.id === id) selectedSupplier.value = null }

onMounted(async () => {
    await fetchAll();
    try { const r = await api.get('/purchase-orders'); (r.data?.data ?? []).forEach((po: any) => { if (po.supplier_id) poCounts.value[po.supplier_id] = (poCounts.value[po.supplier_id] || 0) + 1 }) } catch {}
})
</script>

<template>
    <AppLayout>
        <div class="flex gap-4">
            <div class="w-72 flex-shrink-0 space-y-3">
                <div class="flex items-center justify-between"><h1 class="text-lg font-bold text-text-theme">Suppliers</h1><button @click="openAdd" class="p-1.5 bg-btn-primary text-white rounded-lg hover:bg-btn-primary-hover transition-colors"><Plus class="w-4 h-4" /></button></div>
                <input v-model="searchQuery" type="text" placeholder="Search..." @input="applySearch()" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" />
                <div v-if="loading" class="text-center py-8 text-text-tertiary text-sm">Loading...</div>
                <div v-else class="space-y-1 overflow-y-auto" style="max-height: calc(100vh - 200px);">
                    <button v-for="s in items" :key="s.id" @click="selectedSupplier = s as unknown as Supplier"
                        class="w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors"
                        :class="selectedSupplier?.id === s.id ? 'bg-primary text-white' : 'hover:bg-surface-alt text-text-theme'">
                        <p class="font-medium truncate">{{ (s as any).name }}</p>
                        <p class="text-xs opacity-80 mt-0.5">{{ (s as any).phone }} · {{ (s as any).is_active ? 'Active' : 'Inactive' }}</p>
                    </button>
                    <p v-if="!items.length" class="text-center py-8 text-text-tertiary text-sm">No suppliers.</p>
                </div>
            </div>

            <div v-if="selectedSupplier" class="flex-1 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div><h2 class="text-xl font-bold text-text-theme">{{ selectedSupplier.name }}</h2><span class="text-xs px-2 py-0.5 rounded-full font-medium mt-1 inline-block" :class="selectedSupplier.is_active ? 'bg-success-light text-success-theme' : 'bg-surface-alt text-text-tertiary'">{{ selectedSupplier.is_active ? 'Active' : 'Inactive' }}</span></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Contact</p><p class="text-sm font-semibold text-text-theme mt-1">{{ selectedSupplier.contact_person || '—' }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Phone</p><p class="text-sm font-semibold text-text-theme mt-1">{{ selectedSupplier.phone }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Email</p><p class="text-sm font-semibold text-text-theme mt-1">{{ selectedSupplier.email || '—' }}</p></div>
                    <div class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Purchase Orders</p><p class="text-sm font-semibold text-text-theme mt-1">{{ poCounts[selectedSupplier.id] || 0 }}</p></div>
                </div>
                <div v-if="selectedSupplier.address" class="bg-surface-alt rounded-xl p-4"><p class="text-xs text-text-tertiary">Address</p><p class="text-sm text-text-theme mt-1">{{ selectedSupplier.address }}</p></div>
                <div class="flex gap-2 pt-2">
                    <button @click="openEdit" class="px-4 py-2 border border-border-input rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt"><Pencil class="w-4 h-4 inline mr-1" />Edit</button>
                    <button @click="handleDelete(selectedSupplier.id)" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700"><Trash2 class="w-4 h-4 inline mr-1" />Delete</button>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-text-tertiary text-sm bg-surface-raised rounded-xl border border-border-theme">Select a supplier.</div>

            <div v-if="showForm" class="fixed inset-0 z-40 flex justify-end">
                <div class="absolute inset-0 bg-black/30" @click="showForm = false" />
                <div class="relative z-50 w-full max-w-md bg-surface-raised shadow-xl flex flex-col overflow-y-auto p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-text-theme">{{ editingItem ? 'Edit' : 'Add' }} Supplier</h2>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Name *</label><input v-model="form.name" type="text" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Contact Person</label><input v-model="form.contact_person" type="text" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Phone *</label><input v-model="form.phone" type="tel" required class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Email</label><input v-model="form.email" type="email" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div><label class="block text-sm font-medium text-text-secondary mb-1">Address</label><textarea v-model="form.address" rows="3" class="w-full px-3 py-2 text-sm border border-border-input rounded-lg" /></div>
                        <div class="flex gap-3 pt-2">
                            <button type="submit" class="flex-1 px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium">{{ editingItem ? 'Save' : 'Add Supplier' }}</button>
                            <button type="button" @click="showForm = false" class="flex-1 px-4 py-2 border border-border-input rounded-lg text-sm font-medium text-text-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
