<script setup lang="ts">
import { onMounted, ref, watchEffect } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTickets } from '@/composables/useAdmin'
import { ArrowLeft, Send } from 'lucide-vue-next'
import api from '@/composables/axios'

const props = defineProps<{ id: string }>()
const { fetchTicket, replyToTicket, updateTicketStatus, assignTicket, reopenTicket } = useTickets()
const ticket = ref<any>(null)
const loading = ref(true)
const newMessage = ref('')
const adminAttachments = ref<File[]>([])
const agentsList = ref<any[]>([])
const assigneeId = ref('')
const assignLoading = ref(false)

function onAdminFileSelect(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (files) adminAttachments.value = [...files]
}

onMounted(async () => {
  ticket.value = await fetchTicket(props.id)
  assigneeId.value = ticket.value.assigned_to || ''
  loading.value = false
  try {
    const res = await api.get('/admin/agents?per_page=100')
    agentsList.value = res.data?.data || []
  } catch {}
})

watchEffect(() => {
  if (ticket.value) assigneeId.value = ticket.value.assigned_to || ''
})

async function handleAssign() {
  assignLoading.value = true
  try {
    await assignTicket(props.id, assigneeId.value)
  } catch {
    assigneeId.value = ticket.value?.assigned_to || ''
  } finally {
    assignLoading.value = false
  }
}

async function sendReply() {
  if (!newMessage.value.trim()) return
  await replyToTicket(props.id, newMessage.value, adminAttachments.value.length ? adminAttachments.value : undefined)
  newMessage.value = ''
  adminAttachments.value = []
  ticket.value = await fetchTicket(props.id)
}

async function changeStatus(status: string) {
  await updateTicketStatus(props.id, status)
  ticket.value = await fetchTicket(props.id)
}

async function handleReopen() {
  await reopenTicket(props.id)
  ticket.value = await fetchTicket(props.id)
}

function statusColor(s: string) {
  return { open: 'bg-blue-50 text-blue-700', in_progress: 'bg-yellow-50 text-yellow-700', waiting_reply: 'bg-orange-50 text-orange-700', resolved: 'bg-green-50 text-green-700', closed: 'bg-gray-100 text-gray-500' }[s] || 'bg-gray-100'
}
</script>

<template>
  <AdminLayout>
    <Link href="/admin/tickets" class="inline-flex items-center gap-1 text-sm text-text-tertiary hover:text-text-secondary mb-4">
      <ArrowLeft class="w-4 h-4" /> Back to Tickets
    </Link>

    <div v-if="loading" class="text-center py-20 text-text-tertiary">Loading...</div>

    <template v-else-if="ticket">
      <div class="flex items-start justify-between mb-6">
        <div>
          <h1 class="text-xl font-bold text-text-theme">{{ ticket.subject }}</h1>
          <p class="text-sm text-text-tertiary mt-1">{{ ticket.ticket_number }} · {{ ticket.tenant?.name }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span :class="['text-xs font-medium px-3 py-1 rounded-full', statusColor(ticket.status)]">{{ ticket.status }}</span>
          <select @change="(e: any) => changeStatus(e.target.value)" class="px-3 py-1.5 border border-border-input rounded-lg text-sm">
            <option value="" disabled selected>Change Status</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="waiting_reply">Waiting Reply</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
          </select>
          <button v-if="ticket.status === 'closed'" @click="handleReopen" class="px-3 py-1.5 text-xs font-medium border border-green-300 text-green-700 rounded-lg hover:bg-green-50">Reopen</button>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <!-- Messages -->
        <div class="lg:col-span-2 space-y-4">
          <div v-for="msg in ticket.messages || []" :key="msg.id" :class="['rounded-xl p-4', msg.sender_type === 'admin' ? 'bg-blue-50 ml-8' : msg.sender_type === 'system' ? 'bg-surface-alt mx-8 text-center' : 'bg-surface border border-border-light mr-8']">
            <div v-if="msg.sender_type === 'system'" class="text-xs text-text-tertiary italic">{{ msg.message }}</div>
            <template v-else>
              <div class="flex items-center gap-2 mb-2">
                <span class="text-sm font-medium">{{ msg.sender_name || 'Unknown' }}</span>
                <span class="text-xs text-text-tertiary">{{ new Date(msg.created_at).toLocaleString() }}</span>
              </div>
              <p class="text-sm text-text-secondary whitespace-pre-wrap">{{ msg.message }}</p>
              <div v-if="msg.attachments?.length" class="flex flex-wrap gap-2 mt-2">
                <a v-for="(att, ai) in msg.attachments" :key="ai" :href="att.path" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-surface-alt border border-border-theme rounded-lg text-xs text-text-secondary hover:bg-surface-raised hover:text-text-theme">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  {{ att.name }}
                </a>
              </div>
            </template>
          </div>

          <!-- Reply form -->
          <div class="bg-surface rounded-xl p-4 border border-border-light">
            <textarea v-model="newMessage" placeholder="Type your reply..." class="w-full px-3 py-2 border border-border-input rounded-lg text-sm resize-none" rows="3" />
            <div class="flex items-center gap-3 mt-2">
              <label class="cursor-pointer text-sm text-text-tertiary hover:text-text-secondary flex items-center gap-1">
                <input type="file" multiple @change="onAdminFileSelect" class="hidden" />
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                Attach files
              </label>
              <div v-if="adminAttachments.length" class="flex flex-wrap gap-1">
                <span v-for="(f, i) in adminAttachments" :key="i" class="inline-flex items-center gap-1 px-2 py-0.5 bg-surface-alt rounded text-xs text-text-secondary">
                  {{ f.name }}
                  <button @click="adminAttachments.splice(i, 1)" class="text-text-tertiary hover:text-red-600">&times;</button>
                </span>
              </div>
            </div>
            <div class="flex justify-end mt-3">
              <button @click="sendReply" class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                <Send class="w-4 h-4" /> Reply
              </button>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4">
          <div class="bg-surface rounded-xl p-5 border border-border-light">
            <h4 class="font-semibold text-sm mb-3">Details</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-text-tertiary">Category</span><span class="font-medium">{{ ticket.category }}</span></div>
              <div class="flex justify-between"><span class="text-text-tertiary">Priority</span><span class="font-medium">{{ ticket.priority }}</span></div>
              <div class="flex justify-between"><span class="text-text-tertiary">Created</span><span class="font-medium">{{ new Date(ticket.created_at).toLocaleDateString() }}</span></div>
              <div v-if="ticket.first_response_at" class="flex justify-between"><span class="text-text-tertiary">First Response</span><span class="font-medium">{{ ticket.sla_response_hours }}h</span></div>
              <div class="flex justify-between items-center">
                <span class="text-text-tertiary">Assigned To</span>
                <select v-model="assigneeId" @change="handleAssign" :disabled="assignLoading" class="text-sm border border-border-input rounded px-2 py-1 max-w-[140px]">
                  <option value="">Unassigned</option>
                  <option v-for="a in agentsList" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>
