<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useAuditLogs } from '@/composables/useAdmin'
import api from '@/composables/axios'
import { Search, Download } from 'lucide-vue-next'

const { logs, loading, fetchLogs } = useAuditLogs()
const search = ref('')
const groupFilter = ref('')

onMounted(() => fetchLogs())

let searchTimer: ReturnType<typeof setTimeout>
function doSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    fetchLogs({ search: search.value, action_group: groupFilter.value || undefined })
  }, 300)
}

function paginate(url: string | null) {
  if (!url) return
  const p = new URL(url).searchParams
  fetchLogs({ search: search.value || undefined, action_group: groupFilter.value || undefined, page: p.get('page') || undefined })
}

const expandedLog = ref<string | null>(null)
function toggleExpand(id: string) {
  expandedLog.value = expandedLog.value === id ? null : id
}
function hasValues(obj: any) { return obj && typeof obj === 'object' && Object.keys(obj).length > 0 }

function actionLabel(action: string) {
  return action.split('.').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join(' ')
}

function groupColor(g: string) {
  return { tenant: 'bg-blue-50 text-blue-700', billing: 'bg-green-50 text-green-700', agent: 'bg-purple-50 text-purple-700', system: 'bg-gray-100 text-gray-600', ticket: 'bg-orange-50 text-orange-700' }[g] || 'bg-gray-100'
}
</script>

<template>
  <AdminLayout>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Audit Log</h1>
      <p class="text-sm text-gray-500 mt-1">Track all admin and agent actions</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100">
      <div class="p-4 border-b border-gray-100 flex items-center gap-3">
        <div class="relative flex-1 max-w-sm">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input v-model="search" @input="doSearch" placeholder="Search actions..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <select v-model="groupFilter" @change="doSearch" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
          <option value="">All Groups</option>
          <option value="tenant">Tenant</option>
          <option value="billing">Billing</option>
          <option value="agent">Agent</option>
          <option value="system">System</option>
          <option value="ticket">Ticket</option>
        </select>
        <button @click="api.get('/admin/audit-logs/export', { responseType: 'blob' }).then(r => { const url = window.URL.createObjectURL(new Blob([r.data])); const a = document.createElement('a'); a.href = url; a.download = 'admin-audit-log-' + new Date().toISOString().slice(0,10) + '.csv'; a.click(); window.URL.revokeObjectURL(url) })" class="flex items-center gap-1.5 px-3 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">
          <Download class="w-4 h-4" />
          Export
        </button>
      </div>

      <div v-if="loading" class="p-8 text-center text-gray-400">Loading...</div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3">Action</th>
            <th class="px-4 py-3">User</th>
            <th class="px-4 py-3">Subject</th>
            <th class="px-4 py-3">Time</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <template v-for="log in logs?.data || []" :key="log.id">
            <tr @click="toggleExpand(log.id)" class="hover:bg-gray-50 cursor-pointer">
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', groupColor(log.action_group)]">{{ log.action_group }}</span>
                  <span class="text-sm font-medium">{{ actionLabel(log.action) }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div>
                  <p class="text-sm">{{ log.user_name || 'System' }}</p>
                  <p class="text-xs text-gray-400">{{ log.user_type }}</p>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ log.subject_description || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-500">{{ log.created_at ? new Date(log.created_at).toLocaleString() : '—' }}</td>
            </tr>
            <tr v-if="expandedLog === log.id && (hasValues(log.old_values) || hasValues(log.new_values))">
              <td colspan="4" class="px-4 py-3 bg-gray-50">
                <div class="grid sm:grid-cols-2 gap-4 text-xs">
                  <div v-if="hasValues(log.old_values)">
                    <p class="font-medium text-gray-500 mb-1">Old Values</p>
                    <pre class="bg-white p-2 rounded border border-gray-200 max-h-32 overflow-y-auto">{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                  </div>
                  <div v-if="hasValues(log.new_values)">
                    <p class="font-medium text-gray-500 mb-1">New Values</p>
                    <pre class="bg-white p-2 rounded border border-gray-200 max-h-32 overflow-y-auto">{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                  </div>
                  <div v-if="!hasValues(log.old_values) && !hasValues(log.new_values)" class="col-span-2 text-center text-gray-400 py-4">No value diff available</div>
                </div>
              </td>
            </tr>
          </template>
          <tr v-if="!logs?.data?.length">
            <td colspan="4" class="px-4 py-12 text-center text-gray-400">No audit logs found</td>
          </tr>
        </tbody>
      </table>

      <div v-if="logs?.data?.length" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
        <span class="text-sm text-gray-500">Page {{ logs.current_page || logs.meta?.current_page }} of {{ logs.last_page || logs.meta?.last_page }}</span>
        <div class="flex items-center gap-2">
          <button @click="paginate(logs.prev_page_url)" :disabled="!logs.prev_page_url" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
          <button @click="paginate(logs.next_page_url)" :disabled="!logs.next_page_url" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
