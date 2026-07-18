<template>
  <AgentLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">My Referrals</h1>
          <p class="text-sm text-gray-600 mt-1">Track your referral links and conversion status.</p>
        </div>
        <button @click="showCreate = true" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
          New Referral Link
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex flex-col sm:flex-row gap-3">
          <input
            v-model="search"
            type="text"
            placeholder="Search referrals..."
            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
            @input="debouncedLoad"
          />
          <select
            v-model="statusFilter"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500"
            @change="loadReferrals"
          >
            <option value="">All Status</option>
            <option value="created">Created</option>
            <option value="clicked">Clicked</option>
            <option value="registered">Registered</option>
            <option value="converted">Converted</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Code</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Tenant</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Commission</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Created</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="loading" class="text-center py-8">
                <td colspan="6" class="py-8 text-gray-500 text-sm">Loading referrals...</td>
              </tr>
              <tr v-else-if="referrals.length === 0">
                <td colspan="6" class="py-8 text-center text-gray-500 text-sm">No referrals found</td>
              </tr>
              <tr v-for="r in referrals" :key="r.id" class="hover:bg-gray-50">
                <td class="px-5 py-3">
                  <span class="font-mono text-sm text-gray-900">{{ r.referral_code }}</span>
                </td>
                <td class="px-5 py-3 text-sm text-gray-700">{{ r.tenant?.name || 'Pending' }}</td>
                <td class="px-5 py-3">
                  <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', referralStatusColor(r.status)]">
                    {{ r.status }}
                  </span>
                </td>
                <td class="px-5 py-3 text-sm font-medium text-green-600">
                  ${{ formatNum(r.commission_earned || 0) }}
                </td>
                <td class="px-5 py-3 text-sm text-gray-500">{{ formatDate(r.created_at) }}</td>
                <td class="px-5 py-3">
                  <Link :href="`/agent/referrals/${r.id}`" class="text-green-600 hover:text-green-800 text-sm font-medium">
                    View
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-5 py-3 border-t border-gray-200 flex items-center justify-between">
          <p class="text-sm text-gray-600">
            Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
          </p>
          <div class="flex space-x-1">
            <button
              v-for="p in pagination.last_page"
              :key="p"
              @click="goToPage(p)"
              :class="['px-3 py-1 rounded text-sm', p === pagination.current_page ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
            >
              {{ p }}
            </button>
          </div>
        </div>
      </div>

      <!-- Create Modal -->
      <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Referral Link</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Landing URL (optional)</label>
              <input
                v-model="createUrl"
                type="url"
                placeholder="https://classicpos.com/pricing"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500"
              />
            </div>
            <div v-if="createError" class="bg-red-50 border border-red-200 rounded-lg p-4">
              <p class="text-sm text-red-800">{{ createError }}</p>
            </div>
            <div v-if="newReferral" class="bg-green-50 border border-green-200 rounded-lg p-4">
              <p class="text-sm text-green-800 font-medium">Referral link created!</p>
              <p class="text-xs text-green-700 mt-1 font-mono">{{ newReferral.referral_code }}</p>
              <button @click="copyCode(newReferral.referral_code)" class="mt-2 text-xs text-green-600 underline">
                {{ copied ? 'Copied!' : 'Copy code' }}
              </button>
            </div>
          </div>
          <div class="flex justify-end space-x-3 mt-6">
            <button @click="showCreate = false; newReferral = null; createError = ''" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg text-sm">Close</button>
            <button @click="handleCreate" :disabled="creating" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
              {{ creating ? 'Creating...' : 'Create' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AgentLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import AgentLayout from '@/Layouts/AgentLayout.vue'
import { useAgent } from '@/composables/useAgent'

const { fetchReferrals, createReferral, loading } = useAgent()

const referrals = ref<any[]>([])
const search = ref('')
const statusFilter = ref('')
const pagination = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 })
const showCreate = ref(false)
const createUrl = ref('')
const creating = ref(false)
const newReferral = ref<any>(null)
const copied = ref(false)
const createError = ref('')

let debounceTimer: ReturnType<typeof setTimeout>

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(loadReferrals, 300)
}

async function loadReferrals(page = 1) {
  try {
    const data = await fetchReferrals({ page, search: search.value, status: statusFilter.value, per_page: 15 })
    referrals.value = data.data
    pagination.value = { current_page: data.current_page, last_page: data.last_page, from: data.from, to: data.to, total: data.total }
  } catch {
    referrals.value = []
  }
}

function goToPage(p: number) { loadReferrals(p) }

async function handleCreate() {
  creating.value = true
  createError.value = ''
  try {
    newReferral.value = await createReferral(createUrl.value || undefined)
    await loadReferrals()
  } catch (e: any) {
    createError.value = e?.response?.data?.error || e?.response?.data?.message || e?.response?.data?.errors?.landing_url?.[0] || 'Failed to create referral link. Check the URL and try again.'
  }
  creating.value = false
}

function copyCode(code: string) {
  navigator.clipboard.writeText(code)
  copied.value = true
  setTimeout(() => copied.value = false, 2000)
}

function formatNum(n: number) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function referralStatusColor(s: string) {
  return s === 'converted' ? 'bg-green-100 text-green-700' : s === 'registered' ? 'bg-blue-100 text-blue-700' : s === 'clicked' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'
}

onMounted(() => loadReferrals())
</script>
