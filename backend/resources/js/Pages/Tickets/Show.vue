<template>
  <AppLayout>
    <div class="space-y-6 max-w-4xl">
      <!-- Back -->
      <Link href="/tickets" class="inline-flex items-center text-sm text-text-secondary hover:text-text-theme">
        <ArrowLeft class="w-4 h-4 mr-1" /> Back to Tickets
      </Link>

      <div v-if="loading" class="bg-surface rounded-xl border border-border-theme p-8 text-center text-text-tertiary">Loading ticket...</div>
      <div v-else-if="error" class="bg-surface rounded-xl border border-border-theme p-8 text-center text-red-500">{{ error }}</div>
      <div v-else class="space-y-6">
        <!-- Header -->
        <div class="bg-surface rounded-xl border border-border-theme p-6">
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center space-x-3">
                <span class="text-sm font-mono text-text-tertiary">{{ ticket.ticket_number }}</span>
                <span :class="['text-xs px-2 py-0.5 rounded-full', priorityColor(ticket.priority)]">{{ ticket.priority_label }}</span>
                <span :class="['text-xs px-2 py-0.5 rounded-full', statusColor(ticket.status)]">{{ ticket.status_label }}</span>
              </div>
              <h1 class="text-xl font-bold text-text-theme mt-2">{{ ticket.subject }}</h1>
              <p class="text-sm text-text-tertiary mt-1">
                {{ ticket.category?.replace('_', ' ') }} &middot; Created {{ formatDate(ticket.created_at) }}
                <span v-if="ticket.first_response_at"> &middot; First response in {{ ticket.sla_response_hours }}h</span>
              </p>
            </div>
            <div class="flex space-x-2">
              <button
                v-if="ticket.status === 'closed'"
                @click="handleReopen"
                :disabled="reopening"
                class="px-3 py-1.5 text-sm border border-green-300 text-green-700 rounded-lg hover:bg-green-50 disabled:opacity-50"
              >
                {{ reopening ? 'Reopening...' : 'Reopen Ticket' }}
              </button>
              <button
                v-if="ticket.status !== 'closed'"
                @click="handleClose"
                :disabled="closing"
                class="px-3 py-1.5 text-sm border border-border-input rounded-lg hover:bg-surface-alt disabled:opacity-50"
              >
                {{ closing ? 'Closing...' : 'Close Ticket' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Messages -->
        <div class="bg-surface rounded-xl border border-border-theme overflow-hidden">
          <div class="divide-y divide-border-light">
            <div v-for="msg in ticket.messages" :key="msg.id" :class="['px-6 py-4', msg.sender_type === 'system' ? 'bg-surface-alt' : '']">
              <!-- System message -->
              <div v-if="msg.sender_type === 'system'" class="text-center">
                <p class="text-xs text-text-tertiary italic">{{ msg.message }}</p>
              </div>
              <!-- Regular message -->
              <div v-else>
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium', msg.sender_type === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700']">
                      {{ msg.sender_name?.charAt(0)?.toUpperCase() }}
                    </div>
                    <div>
                      <p class="text-sm font-medium text-text-theme">
                        {{ msg.sender_name }}
                        <span v-if="msg.sender_type === 'admin'" class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded ml-1">Support</span>
                      </p>
                    </div>
                  </div>
                  <p class="text-xs text-text-tertiary">{{ formatDate(msg.created_at) }}</p>
                </div>
                <p class="text-sm text-text-secondary mt-2 whitespace-pre-wrap">{{ msg.message }}</p>
                <div v-if="msg.attachments?.length" class="flex flex-wrap gap-2 mt-2">
                  <a v-for="(att, ai) in msg.attachments" :key="ai" :href="att.path" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-surface-alt border border-border-theme rounded-lg text-xs text-text-secondary hover:bg-surface-raised hover:text-text-theme">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ att.name }}
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Reply Box -->
        <div v-if="ticket.status !== 'closed'" class="bg-surface rounded-xl border border-border-theme p-5">
          <h3 class="font-semibold text-text-theme mb-3">Reply</h3>
          <textarea
            v-model="replyMessage"
            rows="3"
            class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:ring-2 focus:ring-green-500"
            placeholder="Type your reply..."
          />
          <div class="flex items-center gap-3 mt-3">
            <label class="cursor-pointer text-sm text-text-tertiary hover:text-text-secondary flex items-center gap-1">
              <input type="file" multiple @change="onReplyFileSelect" class="hidden" />
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
              Attach files
            </label>
            <div v-if="replyAttachments.length" class="flex flex-wrap gap-1">
              <span v-for="(f, i) in replyAttachments" :key="i" class="inline-flex items-center gap-1 px-2 py-0.5 bg-surface-alt rounded text-xs text-text-secondary">
                {{ f.name }}
                <button @click="replyAttachments.splice(i, 1)" class="text-text-tertiary hover:text-red-600">&times;</button>
              </span>
            </div>
          </div>
          <div class="flex justify-between items-center mt-3">
            <p v-if="replyError" class="text-sm text-red-600">{{ replyError }}</p>
            <div v-else />
            <button
              @click="handleReply"
              :disabled="!replyMessage.trim() || replying"
              class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50"
            >
              {{ replying ? 'Sending...' : 'Send Reply' }}
            </button>
          </div>
        </div>
        <div v-else class="bg-surface-alt rounded-xl border border-border-theme p-5 text-center">
          <p class="text-sm text-text-secondary">This ticket is closed.</p>
          <button @click="handleReopen" :disabled="reopening" class="mt-2 px-4 py-2 text-sm border border-green-300 text-green-700 rounded-lg hover:bg-green-50 disabled:opacity-50">
            {{ reopening ? 'Reopening...' : 'Reopen Ticket' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useTickets } from '@/composables/useTickets'
import { ArrowLeft } from 'lucide-vue-next'

const page = usePage()
const { fetchTicket, replyToTicket, closeTicket, reopenTicket } = useTickets()

const ticket = ref<any>({})
const loading = ref(true)
const error = ref('')
const replyMessage = ref('')
const replyAttachments = ref<File[]>([])
const replying = ref(false)
const replyError = ref('')
const closing = ref(false)
const reopening = ref(false)

function onReplyFileSelect(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (files) replyAttachments.value = [...files]
}

const id = (page.props as any).id as string

async function loadTicket() {
  try {
    ticket.value = await fetchTicket(id)
  } catch (e: any) {
    error.value = e.response?.data?.error || 'Failed to load ticket'
  }
  loading.value = false
}

async function handleReply() {
  if (!replyMessage.value.trim()) return
  replying.value = true; replyError.value = ''
  try {
    await replyToTicket(id, replyMessage.value, replyAttachments.value.length ? replyAttachments.value : undefined)
    replyMessage.value = ''
    replyAttachments.value = []
    await loadTicket()
  } catch (e: any) {
    replyError.value = e.response?.data?.error || 'Failed to send reply'
  }
  replying.value = false
}

async function handleClose() {
  closing.value = true
  try {
    await closeTicket(id)
    await loadTicket()
  } catch {}
  closing.value = false
}

async function handleReopen() {
  reopening.value = true
  try {
    await reopenTicket(id)
    await loadTicket()
  } catch {}
  reopening.value = false
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
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

onMounted(loadTicket)
</script>
