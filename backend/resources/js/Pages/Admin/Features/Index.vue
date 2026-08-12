<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import { Plus, Edit2, Trash2, Layers } from 'lucide-vue-next'

const features = ref<any[]>([])
const loading = ref(true)
const showForm = ref(false)
const editingFeature = ref<any>(null)
const formLoading = ref(false)
const formError = ref('')
const form = ref({ name: '', slug: '', description: '', icon: '', group_name: '', is_active: true, sort_order: 0 })
const editSlug = ref(false)

const LUCIDE_ICONS = ['ShoppingCart', 'Package', 'BarChart3', 'Wifi', 'ScanLine', 'Gift', 'Percent', 'FileText', 'Truck', 'RefreshCw', 'Users', 'Shield', 'CreditCard', 'Building2', 'MessageSquareCode', 'Database', 'Box', 'LayoutDashboard', 'Settings', 'Bell', 'Calendar', 'Clock', 'DollarSign', 'TrendingUp', 'UserCheck', 'UserPlus', 'Smartphone', 'Monitor', 'Printer', 'Cloud', 'Download', 'Upload', 'MapPin', 'Globe', 'Lock', 'Eye', 'Edit3', 'Trash2']

onMounted(() => fetchFeatures())

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

async function fetchFeatures() {
  loading.value = true
  try {
    features.value = await api.get('/api/v1/admin/features')
  } catch { features.value = [] } finally { loading.value = false }
}

function openCreate() {
  editingFeature.value = null
  form.value = { name: '', slug: '', description: '', icon: '', group_name: '', is_active: true, sort_order: 0 }
  editSlug.value = false; formError.value = ''; showForm.value = true
}

function openEdit(feature: any) {
  editingFeature.value = feature
  form.value = { name: feature.name, slug: feature.slug, description: feature.description || '', icon: feature.icon || '', group_name: feature.group_name || '', is_active: feature.is_active, sort_order: feature.sort_order || 0 }
  editSlug.value = true; formError.value = ''; showForm.value = true
}

async function save() {
  formLoading.value = true; formError.value = ''
  try {
    if (editingFeature.value) {
      await api.put(`/api/v1/admin/features/${editingFeature.value.id}`, form.value)
    } else {
      await api.post('/api/v1/admin/features', form.value)
    }
    showForm.value = false; await fetchFeatures()
  } catch (e: any) {
    formError.value = e?.response?.data?.error || e?.response?.data?.message || 'Failed to save feature'
  } finally { formLoading.value = false }
}

async function handleDelete(id: string) {
  if (!confirm('Delete this feature? It cannot be deleted if assigned to any plans.')) return
  try {
    await api.delete(`/api/v1/admin/features/${id}`)
    await fetchFeatures()
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to delete feature')
  }
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Subscription Features</h1>
        <p class="text-sm text-gray-500 mt-1">Manage feature flags available for subscription plans</p>
      </div>
      <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
        <Plus class="w-4 h-4" /> Add Feature
      </button>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <div v-else class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3">Feature</th>
            <th class="px-4 py-3">Slug</th>
            <th class="px-4 py-3">Group</th>
            <th class="px-4 py-3">Plans</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 w-24"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="f in features" :key="f.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <span v-if="f.icon" class="text-gray-400 text-sm">{{ f.icon }}</span>
                <span class="font-medium text-gray-900">{{ f.name }}</span>
              </div>
              <p v-if="f.description" class="text-xs text-gray-400 mt-0.5">{{ f.description }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ f.slug }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ f.group_name || '—' }}</td>
            <td class="px-4 py-3 text-sm">
              <span class="inline-flex items-center gap-1 text-gray-600">
                <Layers class="w-3.5 h-3.5" /> {{ f.plans_count ?? 0 }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span :class="['text-xs font-medium px-2 py-1 rounded-full', f.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ f.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1">
                <button @click="openEdit(f)" class="p-1.5 hover:bg-gray-100 rounded-lg"><Edit2 class="w-4 h-4 text-gray-400" /></button>
                <button @click="handleDelete(f.id)" class="p-1.5 hover:bg-red-50 rounded-lg"><Trash2 class="w-4 h-4 text-red-400" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="!features.length">
            <td colspan="6" class="px-4 py-12 text-center text-gray-400">No features yet. Click "Add Feature" to create one.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <FormSlideOver :title="editingFeature ? 'Edit Feature' : 'Create Feature'" :visible="showForm" :loading="formLoading" :error="formError" @close="showForm = false" @submit="save">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
          <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Slug
            <button @click="editSlug = !editSlug" type="button" class="text-xs text-blue-600 ml-1">{{ editSlug ? 'auto' : 'edit' }}</button>
          </label>
          <input v-model="form.slug" :disabled="!editSlug" required :class="['w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500', !editSlug && 'bg-gray-50 text-gray-400']" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea v-model="form.description" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" rows="2" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Icon (Lucide)</label>
            <select v-model="form.icon" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">No icon</option>
              <option v-for="icon in LUCIDE_ICONS" :key="icon" :value="icon">{{ icon }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Group</label>
            <input v-model="form.group_name" placeholder="e.g. Reporting, Inventory" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
          <input v-model.number="form.sort_order" type="number" min="0" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <label class="flex items-center gap-2">
          <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
          <span class="text-sm font-medium text-gray-700">Active</span>
        </label>
      </div>
    </FormSlideOver>
  </AdminLayout>
</template>
