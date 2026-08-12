<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import { Plus, Edit2, Trash2, Percent, DollarSign, Tag } from 'lucide-vue-next'

const discounts = ref<any[]>([])
const plans = ref<any[]>([])
const loading = ref(true)
const showForm = ref(false)
const editingDiscount = ref<any>(null)
const formLoading = ref(false)
const formError = ref('')
const form = ref({
  plan_id: '', name: '', code: '', type: 'percentage', value: 0,
  billing_cycle: '', description: '', is_recurring: false,
  valid_from: '', valid_until: '', max_uses: null as number | null, is_active: true,
})

const api = {
  async get(url: string) {
    const token = localStorage.getItem('admin_token')
    const res = await fetch(url, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch')
    return res.json()
  },
  async post(url: string, data: any) {
    const token = localStorage.getItem('admin_token')
    const res = await fetch(url, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(data),
    })
    const json = await res.json()
    if (!res.ok) throw { response: { data: json } }
    return json
  },
  async put(url: string, data: any) {
    const token = localStorage.getItem('admin_token')
    const res = await fetch(url, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(data),
    })
    const json = await res.json()
    if (!res.ok) throw { response: { data: json } }
    return json
  },
  async delete(url: string) {
    const token = localStorage.getItem('admin_token')
    const res = await fetch(url, { method: 'DELETE', headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })
    if (!res.ok) { const json = await res.json(); throw { response: { data: json } } }
  },
}

onMounted(async () => {
  await Promise.all([fetchDiscounts(), fetchPlans()])
})

async function fetchDiscounts() {
  loading.value = true
  try { discounts.value = await api.get('/api/v1/admin/discounts') } catch { discounts.value = [] } finally { loading.value = false }
}

async function fetchPlans() {
  try { plans.value = await api.get('/api/v1/admin/plans') } catch { plans.value = [] }
}

function openCreate() {
  editingDiscount.value = null
  form.value = { plan_id: '', name: '', code: '', type: 'percentage', value: 0, billing_cycle: '', description: '', is_recurring: false, valid_from: '', valid_until: '', max_uses: null, is_active: true }
  formError.value = ''; showForm.value = true
}

function openEdit(d: any) {
  editingDiscount.value = d
  form.value = {
    plan_id: d.plan_id, name: d.name, code: d.code || '', type: d.type, value: d.value,
    billing_cycle: d.billing_cycle || '', description: d.description || '', is_recurring: d.is_recurring,
    valid_from: d.valid_from ? d.valid_from.slice(0, 16) : '', valid_until: d.valid_until ? d.valid_until.slice(0, 16) : '',
    max_uses: d.max_uses, is_active: d.is_active,
  }
  formError.value = ''; showForm.value = true
}

async function save() {
  formLoading.value = true; formError.value = ''
  const payload = { ...form.value }
  if (!payload.code) payload.code = null as any
  if (!payload.billing_cycle) payload.billing_cycle = null as any
  if (!payload.valid_from) payload.valid_from = null as any
  if (!payload.valid_until) payload.valid_until = null as any
  if (!payload.max_uses) payload.max_uses = null as any
  try {
    if (editingDiscount.value) {
      await api.put(`/api/v1/admin/discounts/${editingDiscount.value.id}`, payload)
    } else {
      await api.post('/api/v1/admin/discounts', payload)
    }
    showForm.value = false; await fetchDiscounts()
  } catch (e: any) {
    formError.value = e?.response?.data?.error || e?.response?.data?.message || 'Failed to save discount'
  } finally { formLoading.value = false }
}

async function handleDelete(id: string) {
  if (!confirm('Delete this discount?')) return
  try {
    await api.delete(`/api/v1/admin/discounts/${id}`)
    await fetchDiscounts()
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to delete discount')
  }
}

function statusColor(s: string) {
  return { active: 'bg-green-50 text-green-700', inactive: 'bg-gray-100 text-gray-500' }[s] || 'bg-gray-100'
}

function fmtDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString() : '—'
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Plan Discounts</h1>
        <p class="text-sm text-gray-500 mt-1">Manage promo codes, seasonal offers, and discounts for subscription plans</p>
      </div>
      <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
        <Plus class="w-4 h-4" /> Add Discount
      </button>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="d in discounts" :key="d.id" class="bg-white rounded-xl p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-2">
            <div :class="['w-10 h-10 rounded-lg flex items-center justify-center', d.type === 'percentage' ? 'bg-green-50' : 'bg-blue-50']">
              <Percent v-if="d.type === 'percentage'" class="w-5 h-5 text-green-600" />
              <DollarSign v-else class="w-5 h-5 text-blue-600" />
            </div>
            <div>
              <h3 class="font-semibold text-gray-900">{{ d.name }}</h3>
              <p class="text-xs text-gray-500">{{ d.code ? `Code: ${d.code}` : 'No code' }}</p>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <button @click="openEdit(d)" class="p-1.5 hover:bg-gray-100 rounded-lg"><Edit2 class="w-4 h-4 text-gray-400" /></button>
            <button @click="handleDelete(d.id)" class="p-1.5 hover:bg-red-50 rounded-lg"><Trash2 class="w-4 h-4 text-red-400" /></button>
          </div>
        </div>

        <div class="mb-3">
          <span class="text-2xl font-bold">
            {{ d.type === 'percentage' ? `${d.value}%` : `$${d.value}` }}
          </span>
          <span v-if="d.is_recurring" class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full ml-2">Recurring</span>
        </div>

        <div class="text-sm space-y-1.5 mb-4">
          <div class="flex justify-between">
            <span class="text-gray-500">Plan</span>
            <span class="font-medium">{{ d.plan?.name || '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Billing Cycle</span>
            <span class="font-medium capitalize">{{ d.billing_cycle || 'Any' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Valid</span>
            <span class="font-medium">{{ fmtDate(d.valid_from) }} — {{ fmtDate(d.valid_until) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Uses</span>
            <span class="font-medium">{{ d.current_uses }}{{ d.max_uses ? ` / ${d.max_uses}` : '' }}</span>
          </div>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
          <span :class="['text-xs font-medium px-2 py-1 rounded-full', statusColor(d.is_active ? 'active' : 'inactive')]">
            {{ d.is_active ? 'Active' : 'Inactive' }}
          </span>
          <span class="text-xs text-gray-400">{{ d.type === 'percentage' ? 'Percentage off' : 'Fixed amount off' }}</span>
        </div>
      </div>

      <div v-if="!discounts.length" class="col-span-full text-center py-20 text-gray-400">
        No discounts yet. Click "Add Discount" to create one.
      </div>
    </div>

    <FormSlideOver :title="editingDiscount ? 'Edit Discount' : 'Create Discount'" :visible="showForm" :loading="formLoading" :error="formError" @close="showForm = false" @submit="save">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
          <select v-model="form.plan_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Select a plan...</option>
            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Discount Name</label>
          <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Promo Code (optional)</label>
          <input v-model="form.code" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase" placeholder="SAVE20" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select v-model="form.type" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="percentage">Percentage</option>
              <option value="fixed">Fixed Amount</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ form.type === 'percentage' ? 'Percentage (%)' : 'Amount ($)' }}</label>
            <input v-model.number="form.value" type="number" min="0" step="0.01" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
          <select v-model="form.billing_cycle" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All cycles</option>
            <option value="monthly">Monthly only</option>
            <option value="yearly">Yearly only</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea v-model="form.description" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" rows="2" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Valid From</label>
            <input v-model="form.valid_from" type="datetime-local" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until</label>
            <input v-model="form.valid_until" type="datetime-local" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Max Uses</label>
          <input v-model.number="form.max_uses" type="number" min="1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Unlimited" />
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2">
            <input v-model="form.is_recurring" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm font-medium text-gray-700">Recurring discount</span>
          </label>
          <label class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm font-medium text-gray-700">Active</span>
          </label>
        </div>
      </div>
    </FormSlideOver>
  </AdminLayout>
</template>
