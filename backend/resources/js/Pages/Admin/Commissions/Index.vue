<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useCommissions } from '@/composables/useAdmin'
import { DollarSign, TrendingUp, CheckCircle, Search, X } from 'lucide-vue-next'

const { commissions, loading, fetchCommissions, approveCommission, payCommission, fetchSummary } = useCommissions()
const summary = ref<any>(null)
const search = ref('')
const statusFilter = ref('')
const actionLoading = ref<string | null>(null)

onMounted(async () => {
  await loadCommissions()
  try { summary.value = await fetchSummary() } catch { }
})

async function loadCommissions(page?: string) {
  const params: Record<string, any> = {}
  if (statusFilter.value) params.status = statusFilter.value
  if (page) params.page = page
  fetchCommissions(params)
}

function paginate(url: string | null) {
  if (!url) return
  const p = new URL(url).searchParams
  loadCommissions(p.get('page') || undefined)
}

function fmt(n: number) { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 }).format(n) }

function statusColor(status: string) {
  return { paid: 'bg-green-50 text-green-700', cleared: 'bg-blue-50 text-blue-700', pending: 'bg-yellow-50 text-yellow-700', rejected: 'bg-red-50 text-red-500' }[status] || 'bg-gray-100'
}

async function handleApprove(id: string) {
  if (!confirm('Approve this commission?')) return
  actionLoading.value = id
  try {
    await approveCommission(id)
    await loadCommissions()
    summary.value = await fetchSummary()
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to approve commission')
  } finally {
    actionLoading.value = null
  }
}

async function handlePay(id: string) {
  if (!confirm('Mark this commission as paid?')) return
  actionLoading.value = id
  try {
    await payCommission(id)
    await loadCommissions()
    summary.value = await fetchSummary()
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to pay commission')
  } finally {
    actionLoading.value = null
  }
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Commissions</h1>
        <p class="text-sm text-gray-500 mt-1">Manage agent commissions and payouts</p>
      </div>
    </div>

    <div v-if="summary" class="grid sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl p-5 border border-gray-100">
        <DollarSign class="w-5 h-5 text-yellow-600 mb-2" />
        <p class="text-2xl font-bold">{{ fmt(summary.total_pending) }}</p>
        <p class="text-sm text-gray-500">Pending</p>
      </div>
      <div class="bg-white rounded-xl p-5 border border-gray-100">
        <TrendingUp class="w-5 h-5 text-blue-600 mb-2" />
        <p class="text-2xl font-bold">{{ fmt(summary.total_cleared) }}</p>
        <p class="text-sm text-gray-500">Cleared</p>
      </div>
      <div class="bg-white rounded-xl p-5 border border-gray-100">
        <CheckCircle class="w-5 h-5 text-green-600 mb-2" />
        <p class="text-2xl font-bold">{{ fmt(summary.total_paid) }}</p>
        <p class="text-sm text-gray-500">Paid</p>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100">
      <div class="p-4 border-b border-gray-100 flex items-center gap-3">
        <select v-model="statusFilter" @change="loadCommissions" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="cleared">Cleared</option>
          <option value="paid">Paid</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      <div v-if="loading" class="p-8 text-center text-gray-400">Loading...</div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3">Agent</th>
            <th class="px-4 py-3">Tenant</th>
            <th class="px-4 py-3">Amount</th>
            <th class="px-4 py-3">Type</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Date</th>
            <th class="px-4 py-3 w-32"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="c in commissions?.data || []" :key="c.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <p class="text-sm font-medium text-gray-900">{{ c.agent?.name || 'Unknown' }}</p>
              <p class="text-xs text-gray-500">{{ c.agent?.code || '' }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ c.tenant?.name || '—' }}</td>
            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ fmt(c.amount) }}</td>
            <td class="px-4 py-3 text-sm capitalize text-gray-600">{{ c.type?.replace('_', ' ') }}</td>
            <td class="px-4 py-3"><span :class="['text-xs font-medium px-2 py-1 rounded-full', statusColor(c.status)]">{{ c.status }}</span></td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ new Date(c.created_at).toLocaleDateString() }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1">
                <button v-if="c.status === 'pending'" @click="handleApprove(c.id)" :disabled="actionLoading === c.id" class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded-md hover:bg-blue-100 disabled:opacity-50">
                  Approve
                </button>
                <button v-if="c.status === 'cleared' || c.status === 'pending'" @click="handlePay(c.id)" :disabled="actionLoading === c.id" class="px-2 py-1 text-xs font-medium text-green-700 bg-green-50 rounded-md hover:bg-green-100 disabled:opacity-50">
                  Pay
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!commissions?.data?.length">
            <td colspan="7" class="px-4 py-12 text-center text-gray-400">No commissions found</td>
          </tr>
        </tbody>
      </table>

      <div v-if="commissions?.data?.length" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
        <span class="text-sm text-gray-500">Page {{ commissions.current_page || commissions.meta?.current_page }} of {{ commissions.last_page || commissions.meta?.last_page }}</span>
        <div class="flex items-center gap-2">
          <button @click="paginate(commissions.prev_page_url)" :disabled="!commissions.prev_page_url" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
          <button @click="paginate(commissions.next_page_url)" :disabled="!commissions.next_page_url" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
