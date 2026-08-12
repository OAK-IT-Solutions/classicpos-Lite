<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import { useAgents } from '@/composables/useAdmin'
import { Search, Plus, Eye, Edit3, Trash2, ChevronDown, X } from 'lucide-vue-next'

const { agents, loading, fetchAgents, createAgent, updateAgent, deleteAgent } = useAgents()
const search = ref('')
const tierFilter = ref('')
const statusFilter = ref('')
const showForm = ref(false)
const editingAgent = ref<any>(null)
const formLoading = ref(false)
const formError = ref('')
const form = ref({ name: '', email: '', phone: '', password: '', commission_rate: 10, tier: 'standard' })
const deleting = ref<string | null>(null)
const createdCredentials = ref<{ email: string; password: string } | null>(null)

onMounted(() => loadAgents())

function loadAgents() {
  const params: Record<string, any> = {}
  if (search.value) params.search = search.value
  if (tierFilter.value) params.tier = tierFilter.value
  if (statusFilter.value === 'active') params.is_active = true
  if (statusFilter.value === 'inactive') params.is_active = false
  fetchAgents(params)
}

let searchTimer: ReturnType<typeof setTimeout>
function doSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { loadAgents() }, 300)
}
function clearSearch() { search.value = ''; loadAgents() }

function tierColor(tier: string) {
  return { platinum: 'bg-purple-50 text-purple-700', gold: 'bg-yellow-50 text-yellow-700', silver: 'bg-gray-100 text-gray-600', standard: 'bg-blue-50 text-blue-700' }[tier] || 'bg-gray-100'
}

function statusColor(active: boolean) {
  return active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-500'
}

function openCreate() {
  editingAgent.value = null
  form.value = { name: '', email: '', phone: '', password: '', commission_rate: 10, tier: 'standard' }
  formError.value = ''
  createdCredentials.value = null
  showForm.value = true
}

function openEdit(agent: any) {
  editingAgent.value = agent
  form.value = { name: agent.name, email: agent.email, phone: agent.phone || '', commission_rate: agent.commission_rate, tier: agent.tier }
  formError.value = ''
  showForm.value = true
}

async function handleSubmit() {
  formLoading.value = true
  formError.value = ''
  try {
    if (editingAgent.value) {
      await updateAgent(editingAgent.value.id, form.value)
      showForm.value = false
    } else {
      const result = await createAgent(form.value)
      if (result?.credentials) {
        createdCredentials.value = result.credentials
        showForm.value = false
      } else {
        showForm.value = false
      }
    }
    loadAgents()
  } catch (e: any) {
    formError.value = e?.response?.data?.message || e?.response?.data?.error || 'Failed to save agent'
  } finally {
    formLoading.value = false
  }
}

async function confirmDelete(id: string) {
  if (!confirm('Are you sure you want to delete this agent?')) return
  deleting.value = id
  try {
    await deleteAgent(id)
    loadAgents()
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to delete agent')
  } finally {
    deleting.value = null
  }
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Agents</h1>
        <p class="text-sm text-gray-500 mt-1">Manage reseller agents and commissions</p>
      </div>
      <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
        <Plus class="w-4 h-4" /> Create Agent
      </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-100">
      <div class="p-4 border-b border-gray-100 flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 min-w-[200px] max-w-sm">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input v-model="search" @input="doSearch" placeholder="Search agents..." class="w-full pl-10 pr-10 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <button v-if="search" @click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <X class="w-4 h-4" />
          </button>
        </div>
        <select v-model="tierFilter" @change="loadAgents" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All Tiers</option>
          <option value="standard">Standard</option>
          <option value="silver">Silver</option>
          <option value="gold">Gold</option>
          <option value="platinum">Platinum</option>
        </select>
        <select v-model="statusFilter" @change="loadAgents" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div v-if="loading" class="p-8 text-center text-gray-400">Loading...</div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3">Agent</th>
            <th class="px-4 py-3">Code</th>
            <th class="px-4 py-3">Tier</th>
            <th class="px-4 py-3">Commission</th>
            <th class="px-4 py-3">Earnings</th>
            <th class="px-4 py-3">Referrals</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 w-24"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in agents?.data || []" :key="a.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ a.name }}</p>
                <p class="text-xs text-gray-500">{{ a.email }}</p>
              </div>
            </td>
            <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ a.code }}</td>
            <td class="px-4 py-3"><span :class="['text-xs font-medium px-2 py-1 rounded-full', tierColor(a.tier)]">{{ a.tier }}</span></td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ a.commission_rate }}%</td>
            <td class="px-4 py-3 text-sm font-medium text-gray-900">${{ Number(a.total_earnings).toLocaleString() }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ a.referrals_count ?? 0 }}</td>
            <td class="px-4 py-3"><span :class="['text-xs font-medium px-2 py-1 rounded-full', statusColor(a.is_active)]">{{ a.is_active ? 'Active' : 'Inactive' }}</span></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1">
                <Link :href="`/admin/agents/${a.id}`" class="p-1.5 hover:bg-gray-100 rounded-lg inline-flex" title="View">
                  <Eye class="w-4 h-4 text-gray-400" />
                </Link>
                <button @click="openEdit(a)" class="p-1.5 hover:bg-gray-100 rounded-lg inline-flex" title="Edit">
                  <Edit3 class="w-4 h-4 text-gray-400" />
                </button>
                <button @click="confirmDelete(a.id)" :disabled="deleting === a.id" class="p-1.5 hover:bg-red-50 rounded-lg inline-flex" title="Delete">
                  <Trash2 class="w-4 h-4 text-red-400" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!agents?.data?.length">
            <td colspan="8" class="px-4 py-12 text-center text-gray-400">No agents found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <FormSlideOver :title="editingAgent ? 'Edit Agent' : 'Create Agent'" :visible="showForm" :loading="formLoading" :error="formError" @close="showForm = false" @submit="handleSubmit">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
          <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="form.email" type="email" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div v-if="!editingAgent">
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input v-model="form.password" type="password" required minlength="6" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <p class="text-xs text-gray-400 mt-1">Min 6 characters. Agent will use this to log in.</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input v-model="form.phone" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Commission Rate (%)</label>
          <input v-model.number="form.commission_rate" type="number" min="0" max="100" step="0.1" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tier</label>
          <select v-model="form.tier" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="standard">Standard</option>
            <option value="silver">Silver</option>
            <option value="gold">Gold</option>
            <option value="platinum">Platinum</option>
          </select>
        </div>
      </div>
    </FormSlideOver>

    <!-- Credentials Modal -->
    <div v-if="createdCredentials" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
            <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Agent Created Successfully</h3>
            <p class="text-xs text-gray-500">Share these credentials with the agent</p>
          </div>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 space-y-2 mb-4">
          <div>
            <span class="text-xs font-medium text-gray-500">Email:</span>
            <p class="text-sm font-mono text-gray-900">{{ createdCredentials.email }}</p>
          </div>
          <div>
            <span class="text-xs font-medium text-gray-500">Password:</span>
            <p class="text-sm font-mono text-gray-900">{{ createdCredentials.password }}</p>
          </div>
        </div>
        <p class="text-xs text-orange-600 bg-orange-50 rounded-lg p-2 mb-4">Share these credentials securely. The password will not be shown again.</p>
        <button @click="createdCredentials = null" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
          Got it
        </button>
      </div>
    </div>
  </AdminLayout>
</template>
