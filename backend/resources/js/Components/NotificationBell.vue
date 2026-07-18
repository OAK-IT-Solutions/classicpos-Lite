<template>
  <div class="relative">
    <button @click="toggle" class="relative p-2 rounded-lg hover:bg-gray-100 transition-colors">
      <Bell class="w-5 h-5 text-gray-600" />
      <span v-if="count > 0" class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center w-4.5 h-4.5 text-[10px] font-bold text-white bg-red-500 rounded-full min-w-[18px] min-h-[18px]">
        {{ count > 99 ? '99+' : count }}
      </span>
    </button>

    <div v-if="open" class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50 max-h-96 overflow-hidden flex flex-col">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
        <button v-if="count > 0" @click="markAllRead" class="text-xs text-blue-600 hover:text-blue-800">Mark all as read</button>
      </div>
      <div class="overflow-y-auto flex-1">
        <div v-if="loading" class="p-4 text-center text-sm text-gray-500">Loading...</div>
        <div v-else-if="!list.length" class="p-8 text-center text-sm text-gray-400">No notifications</div>
        <template v-else>
          <a
            v-for="n in list" :key="n.id"
            :href="notificationLink(n)"
            class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-50 last:border-0"
            :class="n.read_at ? '' : 'bg-blue-50/50'"
            @click="n.read_at || markRead(n.id)"
          >
            <p class="text-sm text-gray-900">{{ n.data.message }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ timeAgo(n.created_at) }}</p>
          </a>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Bell } from 'lucide-vue-next'
import api from '@/composables/axios'

const open = ref(false)
const list = ref<any[]>([])
const count = ref(0)
const loading = ref(false)
let pollTimer: ReturnType<typeof setTimeout>

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/notifications?per_page=20')
    list.value = data.data || []
    count.value = data.total || list.value.filter((n: any) => !n.read_at).length
  } catch {}
  loading.value = false
}

async function fetchCount() {
  try {
    const { data } = await api.get('/notifications/unread-count')
    count.value = data.count
  } catch {}
}

function toggle() {
  open.value = !open.value
  if (open.value) load()
}

function timeAgo(date: string) {
  const s = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
  if (s < 60) return 'just now'
  if (s < 3600) return `${Math.floor(s / 60)}m ago`
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`
  return new Date(date).toLocaleDateString()
}

function notificationLink(n: any) {
  if (n.data?.ticket_id) return `/admin/tickets/${n.data.ticket_id}`
  return '#'
}

async function markRead(id: string) {
  try { await api.post(`/notifications/${id}/read`) } catch {}
}

async function markAllRead() {
  try {
    await api.post('/notifications/read-all')
    count.value = 0
    list.value.forEach((n: any) => (n.read_at = new Date().toISOString()))
  } catch {}
}

function handleClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.relative')) open.value = false
}

onMounted(() => {
  fetchCount()
  pollTimer = setInterval(fetchCount, 30000)
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  clearInterval(pollTimer)
  document.removeEventListener('click', handleClickOutside)
})
</script>
