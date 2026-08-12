<template>
  <AgentLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Commissions</h1>
        <p class="text-sm text-gray-600 mt-1">View all commissions earned from your referrals.</p>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-sm text-gray-600">Total Earned</p>
          <p class="text-xl font-bold text-green-600 mt-1">${{ formatNum(summary.total_earned || 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-sm text-gray-600">Pending</p>
          <p class="text-xl font-bold text-yellow-600 mt-1">${{ formatNum(summary.pending || 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-sm text-gray-600">This Month</p>
          <p class="text-xl font-bold text-blue-600 mt-1">${{ formatNum(summary.this_month || 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-sm text-gray-600">Last Month</p>
          <p class="text-xl font-bold text-gray-600 mt-1">${{ formatNum(summary.last_month || 0) }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex flex-col sm:flex-row gap-3">
          <input
            v-model="search"
            type="text"
            placeholder="Search by tenant..."
            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500"
            @input="debouncedLoad"
          />
          <select v-model="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" @change="loadCommissions">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="cleared">Cleared</option>
            <option value="paid">Paid</option>
          </select>
          <select v-model="typeFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" @change="loadCommissions">
            <option value="">All Types</option>
            <option value="referral">Referral</option>
            <option value="bonus">Bonus</option>
            <option value="tier_upgrade">Tier Upgrade</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Rate</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Tenant</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="loading">
                <td colspan="6" class="py-8 text-center text-gray-500 text-sm">Loading...</td>
              </tr>
              <tr v-else-if="commissions.length === 0">
                <td colspan="6" class="py-8 text-center text-gray-500 text-sm">No commissions found</td>
              </tr>
              <tr v-for="c in commissions" :key="c.id" class="hover:bg-gray-50">
                <td class="px-5 py-3 text-sm font-semibold text-green-600">+${{ formatNum(c.amount) }}</td>
                <td class="px-5 py-3 text-sm text-gray-700 capitalize">{{ c.type }}</td>
                <td class="px-5 py-3 text-sm text-gray-500">{{ c.rate }}%</td>
                <td class="px-5 py-3 text-sm text-gray-700">{{ c.tenant?.name || '-' }}</td>
                <td class="px-5 py-3">
                  <span :class="['text-xs px-2 py-0.5 rounded-full', statusColor(c.status)]">{{ c.status }}</span>
                </td>
                <td class="px-5 py-3 text-sm text-gray-500">{{ formatDate(c.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination.last_page > 1" class="px-5 py-3 border-t border-gray-200 flex items-center justify-between">
          <p class="text-sm text-gray-600">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</p>
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
    </div>
  </AgentLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AgentLayout from '@/Layouts/AgentLayout.vue'
import { useAgent } from '@/composables/useAgent'

const { fetchCommissions, fetchCommissionSummary, loading } = useAgent()

const commissions = ref<any[]>([])
const summary = ref<any>({})
const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const pagination = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 })

let debounceTimer: ReturnType<typeof setTimeout>

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(loadCommissions, 300)
}

async function loadCommissions(page = 1) {
  try {
    const [data, s] = await Promise.all([
      fetchCommissions({ page, search: search.value, status: statusFilter.value, type: typeFilter.value, per_page: 15 }),
      page === 1 ? fetchCommissionSummary() : Promise.resolve(summary.value),
    ])
    commissions.value = data.data
    pagination.value = { current_page: data.current_page, last_page: data.last_page, from: data.from, to: data.to, total: data.total }
    if (page === 1) summary.value = s
  } catch {
    commissions.value = []
  }
}

function goToPage(p: number) { loadCommissions(p) }

function formatNum(n: number) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusColor(s: string) {
  return s === 'paid' ? 'bg-green-100 text-green-700' : s === 'cleared' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700'
}

onMounted(() => loadCommissions())
</script>
