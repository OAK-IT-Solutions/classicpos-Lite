<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useAxios } from '@/composables/axios'
import AppLayout from '@/layouts/AppLayout.vue'
import { format } from 'date-fns'
import {
  Download,
  Filter,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  AlertCircle,
  CheckCircle,
  XCircle,
  Activity,
  User,
  GitBranch,
  Clock,
  Search,
} from 'lucide-vue-next'

const { user } = useAuth()
const { api } = useAxios()

interface AuditLog {
  id: string
  user_id: string | null
  branch_id: string | null
  auditable_type: string
  auditable_id: string
  event: string
  old_values: Record<string, unknown>
  new_values: Record<string, unknown>
  description: string
  method: string | null
  status_code: number | null
  ip_address: string | null
  created_at: string
  user?: { name: string; email: string } | null
}

const logs = ref<AuditLog[]>([])
const loading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(50)

const filters = ref({
  search: '',
  event: '',
  user_id: '',
  from: '',
  to: '',
})

const eventOptions = [
  { value: '', label: 'All Events' },
  { value: 'created', label: 'Created' },
  { value: 'updated', label: 'Updated' },
  { value: 'deleted', label: 'Deleted' },
  { value: 'voided', label: 'Voided' },
  { value: 'approved', label: 'Approved' },
  { value: 'failed', label: 'Failed' },
  { value: 'low_stock', label: 'Low Stock' },
  { value: 'request', label: 'API Request' },
]

const eventColors: Record<string, string> = {
  created: 'bg-green-100 text-green-800',
  updated: 'bg-blue-100 text-blue-800',
  deleted: 'bg-red-100 text-red-800',
  voided: 'bg-orange-100 text-orange-800',
  approved: 'bg-emerald-100 text-emerald-800',
  failed: 'bg-red-100 text-red-800',
  low_stock: 'bg-yellow-100 text-yellow-800',
  request: 'bg-gray-100 text-gray-800',
}

const eventIcons: Record<string, typeof CheckCircle> = {
  created: CheckCircle,
  updated: Activity,
  deleted: XCircle,
  voided: AlertCircle,
  approved: CheckCircle,
  failed: XCircle,
  low_stock: AlertCircle,
  request: Activity,
}

const modelTypeShort = (type: string) => {
  const parts = type.split('\\')
  return parts[parts.length - 1]
}

const formatDate = (date: string) => format(new Date(date), 'MMM d, yyyy HH:mm:ss')

async function fetchLogs(page = 1) {
  loading.value = true
  try {
    const params: Record<string, string | number> = {
      page,
      per_page: perPage.value,
    }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.event) params.event = filters.value.event
    if (filters.value.user_id) params.user_id = filters.value.user_id
    if (filters.value.from) params.from = filters.value.from
    if (filters.value.to) params.to = filters.value.to

    const { data } = await api.get('/activity-logs', { params })
    logs.value = data.data
    currentPage.value = data.meta.current_page
    lastPage.value = data.meta.last_page
    total.value = data.meta.total
  } catch (e) {
    console.error('Failed to fetch audit logs', e)
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  currentPage.value = 1
  fetchLogs(1)
}

function clearFilters() {
  filters.value = { search: '', event: '', user_id: '', from: '', to: '' }
  applyFilters()
}

function goToPage(page: number) {
  if (page >= 1 && page <= lastPage.value) {
    fetchLogs(page)
  }
}

async function exportCsv() {
  try {
    const params: Record<string, string> = {}
    if (filters.value.event) params.event = filters.value.event
    if (filters.value.user_id) params.user_id = filters.value.user_id
    if (filters.value.from) params.from = filters.value.from
    if (filters.value.to) params.to = filters.value.to

    const { data } = await api.get('/activity-logs/export', {
      params,
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `audit-log-${format(new Date(), 'yyyy-MM-dd')}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    console.error('Export failed', e)
  }
}

onMounted(() => fetchLogs())
</script>

<template>
  <AppLayout title="Audit Log">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Log</h2>
        <button
          @click="exportCsv"
          class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
        >
          <Download class="w-4 h-4 mr-2" />
          Export CSV
        </button>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Filters -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <div class="flex items-center gap-2 mb-4">
              <Filter class="w-5 h-5 text-gray-500" />
              <h3 class="text-lg font-medium text-gray-900">Filters</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
              <!-- Search -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <div class="relative">
                  <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Description..."
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    @keyup.enter="applyFilters"
                  />
                </div>
              </div>

              <!-- Event Type -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Event Type</label>
                <select
                  v-model="filters.event"
                  class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >
                  <option v-for="opt in eventOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>

              <!-- From Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input
                  v-model="filters.from"
                  type="date"
                  class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
              </div>

              <!-- To Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input
                  v-model="filters.to"
                  type="date"
                  class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
              </div>

              <!-- Actions -->
              <div class="flex items-end gap-2">
                <button
                  @click="applyFilters"
                  class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                  Apply
                </button>
                <button
                  @click="clearFilters"
                  class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                  Clear
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Results -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <p class="text-sm text-gray-600">
                {{ total.toLocaleString() }} total entries
              </p>
              <button
                @click="fetchLogs(currentPage)"
                class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
              >
                <RefreshCw class="w-4 h-4 mr-1" :class="{ 'animate-spin': loading }" />
                Refresh
              </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="text-center py-12 text-gray-500">
              Loading...
            </div>

            <!-- Empty -->
            <div v-else-if="logs.length === 0" class="text-center py-12 text-gray-500">
              No audit log entries found.
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Model</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                      <div class="flex items-center gap-1">
                        <Clock class="w-3.5 h-3.5 text-gray-400" />
                        {{ formatDate(log.created_at) }}
                      </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                      <div class="flex items-center gap-1" v-if="log.user">
                        <User class="w-3.5 h-3.5 text-gray-400" />
                        {{ log.user.name }}
                      </div>
                      <span v-else class="text-gray-400">System</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                      <span
                        :class="eventColors[log.event] || 'bg-gray-100 text-gray-800'"
                        class="px-2 py-1 inline-flex items-center gap-1 text-xs font-semibold rounded-full"
                      >
                        <component :is="eventIcons[log.event] || Activity" class="w-3 h-3" />
                        {{ log.event }}
                      </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                      {{ modelTypeShort(log.auditable_type) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">
                      {{ log.description }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                      {{ log.ip_address || '—' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div v-if="lastPage > 1" class="flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
              <button
                @click="goToPage(currentPage - 1)"
                :disabled="currentPage <= 1"
                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <ChevronLeft class="w-4 h-4" />
                Previous
              </button>
              <span class="text-sm text-gray-700">
                Page {{ currentPage }} of {{ lastPage }}
              </span>
              <button
                @click="goToPage(currentPage + 1)"
                :disabled="currentPage >= lastPage"
                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
                <ChevronRight class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
