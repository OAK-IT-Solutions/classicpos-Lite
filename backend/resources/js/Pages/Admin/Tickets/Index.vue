<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTickets } from '@/composables/useAdmin'
import { Search, Eye } from 'lucide-vue-next'

const { tickets, loading, fetchTickets } = useTickets()
const search = ref('')
const statusFilter = ref('')
const priorityFilter = ref('')

onMounted(() => fetchTickets())

let searchTimer: ReturnType<typeof setTimeout>
function doSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    fetchTickets({ search: search.value, status: statusFilter.value || undefined, priority: priorityFilter.value || undefined })
  }, 300)
}

function paginate(url: string | null) {
  if (!url) return
  const p = new URL(url).searchParams
  fetchTickets({ search: search.value || undefined, status: statusFilter.value || undefined, priority: priorityFilter.value || undefined, page: p.get('page') || undefined })
}

function statusColor(s: string) {
  return { open: 'bg-blue-50 text-blue-700', in_progress: 'bg-yellow-50 text-yellow-700', waiting_reply: 'bg-orange-50 text-orange-700', resolved: 'bg-green-50 text-green-700', closed: 'bg-gray-100 text-gray-500' }[s] || 'bg-gray-100'
}

function priorityColor(p: string) {
  return { urgent: 'bg-red-50 text-red-700', high: 'bg-orange-50 text-orange-700', medium: 'bg-yellow-50 text-yellow-700', low: 'bg-gray-100 text-gray-500' }[p] || 'bg-gray-100'
}
</script>

<template>
  <AdminLayout>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-text-theme">Support Tickets</h1>
      <p class="text-sm text-text-tertiary mt-1">Manage customer support requests</p>
    </div>

    <div class="bg-surface rounded-xl border border-border-light">
      <div class="p-4 border-b border-border-light flex flex-wrap items-center gap-3">
        <div class="relative flex-1 max-w-sm">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-tertiary" />
          <input v-model="search" @input="doSearch" placeholder="Search tickets..." class="w-full pl-10 pr-4 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <select v-model="statusFilter" @change="doSearch" class="px-3 py-2 border border-border-input rounded-lg text-sm">
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="waiting_reply">Waiting Reply</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        <select v-model="priorityFilter" @change="doSearch" class="px-3 py-2 border border-border-input rounded-lg text-sm">
          <option value="">All Priorities</option>
          <option value="urgent">Urgent</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
      </div>

      <div v-if="loading" class="p-8 text-center text-text-tertiary">Loading...</div>

      <table v-else class="w-full">
        <thead class="bg-surface-alt text-left text-xs font-medium text-text-tertiary uppercase">
          <tr>
            <th class="px-4 py-3">Ticket</th>
            <th class="px-4 py-3">Tenant</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Priority</th>
            <th class="px-4 py-3">Created</th>
            <th class="px-4 py-3 w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border-light">
          <tr v-for="t in tickets?.data || []" :key="t.id" class="hover:bg-surface-alt">
            <td class="px-4 py-3">
              <div>
                <p class="text-sm font-medium">{{ t.subject }}</p>
                <p class="text-xs text-text-tertiary">{{ t.ticket_number }}</p>
              </div>
            </td>
            <td class="px-4 py-3 text-sm">{{ t.tenant?.name || 'Unknown' }}</td>
            <td class="px-4 py-3"><span :class="['text-xs font-medium px-2 py-1 rounded-full', statusColor(t.status)]">{{ t.status }}</span></td>
            <td class="px-4 py-3"><span :class="['text-xs font-medium px-2 py-1 rounded-full', priorityColor(t.priority)]">{{ t.priority }}</span></td>
            <td class="px-4 py-3 text-sm text-text-tertiary">{{ new Date(t.created_at).toLocaleDateString() }}</td>
            <td class="px-4 py-3">
              <Link :href="`/admin/tickets/${t.id}`" class="p-1.5 hover:bg-surface-alt rounded-lg inline-flex">
                <Eye class="w-4 h-4 text-text-tertiary" />
              </Link>
            </td>
          </tr>
          <tr v-if="!tickets?.data?.length">
            <td colspan="6" class="px-4 py-12 text-center text-text-tertiary">No tickets found</td>
          </tr>
        </tbody>
      </table>

      <div v-if="tickets?.data?.length" class="flex items-center justify-between px-4 py-3 border-t border-border-light">
        <span class="text-sm text-text-tertiary">Page {{ tickets.current_page || tickets.meta?.current_page }} of {{ tickets.last_page || tickets.meta?.last_page }}</span>
        <div class="flex items-center gap-2">
          <button @click="paginate(tickets.prev_page_url)" :disabled="!tickets.prev_page_url" class="px-3 py-1.5 text-sm border border-border-input rounded-lg hover:bg-surface-alt disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
          <button @click="paginate(tickets.next_page_url)" :disabled="!tickets.next_page_url" class="px-3 py-1.5 text-sm border border-border-input rounded-lg hover:bg-surface-alt disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
