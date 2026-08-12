<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-text-theme">Support Tickets</h1>
          <p class="text-sm text-text-secondary mt-1">Get help from our support team.</p>
        </div>
        <button @click="showCreate = true" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
          New Ticket
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-surface rounded-xl border border-border-theme p-4">
        <div class="flex flex-col sm:flex-row gap-3">
          <input
            v-model="search"
            type="text"
            placeholder="Search tickets..."
            class="flex-1 px-3 py-2 border border-border-input rounded-lg text-sm focus:ring-2 focus:ring-green-500"
            @input="debouncedLoad"
          />
          <select v-model="statusFilter" class="px-3 py-2 border border-border-input rounded-lg text-sm" @change="loadTickets">
            <option value="">All Status</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="waiting_reply">Waiting for Reply</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
          </select>
          <select v-model="priorityFilter" class="px-3 py-2 border border-border-input rounded-lg text-sm" @change="loadTickets">
            <option value="">All Priority</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </select>
        </div>
      </div>

      <!-- Ticket List -->
      <div class="bg-surface rounded-xl border border-border-theme overflow-hidden">
        <div v-if="loading && tickets.length === 0" class="p-8 text-center text-text-tertiary">Loading tickets...</div>
        <div v-else-if="tickets.length === 0" class="p-8 text-center text-text-tertiary">
          <p>No support tickets yet.</p>
          <button @click="showCreate = true" class="mt-2 text-green-600 hover:text-green-800 text-sm font-medium">Create your first ticket</button>
        </div>
        <div v-else class="divide-y divide-border-light">
          <Link
            v-for="ticket in tickets"
            :key="ticket.id"
            :href="`/tickets/${ticket.id}`"
            class="flex items-center justify-between px-5 py-4 hover:bg-surface-alt transition-colors"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center space-x-3">
                <span class="text-xs font-mono text-text-tertiary">{{ ticket.ticket_number }}</span>
                <span :class="['text-xs px-2 py-0.5 rounded-full', priorityColor(ticket.priority)]">{{ ticket.priority }}</span>
                <span :class="['text-xs px-2 py-0.5 rounded-full', statusColor(ticket.status)]">{{ ticket.status_label || ticket.status }}</span>
              </div>
              <p class="text-sm font-medium text-text-theme mt-1 truncate">{{ ticket.subject }}</p>
              <p class="text-xs text-text-tertiary mt-1">{{ ticket.category?.replace('_', ' ') }} &middot; {{ formatDate(ticket.created_at) }}</p>
            </div>
            <div class="flex items-center space-x-4 ml-4">
              <div class="text-right text-xs text-text-tertiary">
                <div>{{ ticket.message_count }} {{ ticket.message_count === 1 ? 'message' : 'messages' }}</div>
                <div v-if="ticket.unread_count > 0" class="text-green-600 font-medium">{{ ticket.unread_count }} new</div>
              </div>
              <ChevronRight class="w-4 h-4 text-text-tertiary" />
            </div>
          </Link>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-5 py-3 border-t border-border-theme flex items-center justify-between">
          <p class="text-sm text-text-secondary">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</p>
          <div class="flex space-x-1">
            <button
              v-for="p in pagination.last_page"
              :key="p"
              @click="goToPage(p)"
              :class="['px-3 py-1 rounded text-sm', p === pagination.current_page ? 'bg-green-600 text-white' : 'bg-surface-alt text-text-secondary hover:bg-surface-raised']"
            >
              {{ p }}
            </button>
          </div>
        </div>
      </div>

      <!-- Create Ticket Modal -->
      <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-surface rounded-xl shadow-xl max-w-lg w-full p-6">
          <h3 class="text-lg font-semibold text-text-theme mb-4">Create Support Ticket</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-1">Subject</label>
              <input v-model="form.subject" type="text" class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:ring-2 focus:ring-green-500" placeholder="Brief description of your issue" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Category</label>
                <select v-model="form.category" class="w-full px-3 py-2 border border-border-input rounded-lg text-sm">
                  <option value="general">General</option>
                  <option value="technical">Technical</option>
                  <option value="billing">Billing</option>
                  <option value="feature_request">Feature Request</option>
                  <option value="bug_report">Bug Report</option>
                  <option value="account">Account</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Priority</label>
                <select v-model="form.priority" class="w-full px-3 py-2 border border-border-input rounded-lg text-sm">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-1">Description</label>
              <textarea v-model="form.description" rows="4" class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:ring-2 focus:ring-green-500" placeholder="Describe your issue in detail..."></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-1">Attachments (optional)</label>
              <input type="file" multiple @change="onFileSelect" class="w-full text-sm text-text-tertiary file:mr-3 file:py-1.5 file:px-3 file:border file:border-border-input file:rounded-lg file:text-sm file:bg-surface-alt hover:file:bg-surface-raised" />
              <div v-if="form.attachments?.length" class="flex flex-wrap gap-2 mt-2">
                <span v-for="(f, i) in form.attachments" :key="i" class="inline-flex items-center gap-1 px-2 py-1 bg-surface-alt rounded text-xs text-text-secondary">
                  {{ f.name }}
                  <button @click="form.attachments.splice(i, 1)" class="text-text-tertiary hover:text-red-600">&times;</button>
                </span>
              </div>
            </div>
            <p v-if="createError" class="text-sm text-red-600">{{ createError }}</p>
          </div>
          <div class="flex justify-end space-x-3 mt-6">
            <button @click="showCreate = false; createError = ''" class="px-4 py-2 text-text-secondary hover:bg-surface-alt rounded-lg text-sm">Cancel</button>
            <button @click="handleCreate" :disabled="creating" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
              {{ creating ? 'Creating...' : 'Create Ticket' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useTickets } from '@/composables/useTickets'
import { ChevronRight } from 'lucide-vue-next'

const { fetchTickets, createTicket, loading } = useTickets()

const tickets = ref<any[]>([])
const search = ref('')
const statusFilter = ref('')
const priorityFilter = ref('')
const pagination = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 })
const showCreate = ref(false)
const creating = ref(false)
const createError = ref('')

const form = ref<{ subject: string; description: string; category: string; priority: string; attachments: File[] }>({ subject: '', description: '', category: 'general', priority: 'medium', attachments: [] })

function onFileSelect(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (files) form.value.attachments = [...files]
}

let debounceTimer: ReturnType<typeof setTimeout>

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(loadTickets, 300)
}

async function loadTickets(page = 1) {
  try {
    const data = await fetchTickets({ page, search: search.value, status: statusFilter.value, priority: priorityFilter.value, per_page: 15 })
    tickets.value = data.data
    pagination.value = { current_page: data.current_page, last_page: data.last_page, from: data.from, to: data.to, total: data.total }
  } catch {}
}

function goToPage(p: number) { loadTickets(p) }

async function handleCreate() {
  creating.value = true; createError.value = ''
  try {
    await createTicket({ ...form.value, attachments: form.value.attachments })
    showCreate.value = false
    form.value = { subject: '', description: '', category: 'general', priority: 'medium', attachments: [] }
    await loadTickets()
  } catch (e: any) {
    createError.value = e.response?.data?.error || 'Failed to create ticket'
  }
  creating.value = false
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusColor(s: string) {
  return s === 'open' ? 'bg-blue-100 text-blue-700'
    : s === 'in_progress' ? 'bg-yellow-100 text-yellow-700'
    : s === 'waiting_reply' ? 'bg-orange-100 text-orange-700'
    : s === 'resolved' ? 'bg-green-100 text-green-700'
    : 'bg-gray-100 text-gray-700'
}

function priorityColor(p: string) {
  return p === 'urgent' ? 'bg-red-100 text-red-700'
    : p === 'high' ? 'bg-orange-100 text-orange-700'
    : p === 'medium' ? 'bg-yellow-100 text-yellow-700'
    : 'bg-gray-100 text-gray-700'
}

onMounted(() => loadTickets())
</script>
