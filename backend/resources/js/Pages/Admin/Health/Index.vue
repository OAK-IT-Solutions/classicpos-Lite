<script setup lang="ts">
import { onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useHealth } from '@/composables/useAdmin'
import { Database, Server, HardDrive, Cpu, Activity, RefreshCw, Circle } from 'lucide-vue-next'

const { health, loading, fetchHealth } = useHealth()

onMounted(() => fetchHealth())

function statusDot(status: string) {
  return status === 'connected' || status === 'healthy' ? 'bg-green-500' : 'bg-red-500'
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">System Health</h1>
        <p class="text-sm text-gray-500 mt-1">Infrastructure status and diagnostics</p>
      </div>
      <div class="flex items-center gap-2">
        <span :class="['px-3 py-1.5 text-xs font-medium rounded-full', health?.mode === 'SaaS' ? 'bg-purple-50 text-purple-700' : 'bg-green-50 text-green-700']">{{ health?.mode || 'Self-Hosted' }}</span>
        <button @click="fetchHealth" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50">
          <RefreshCw class="w-4 h-4" /> Refresh
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <template v-else-if="health">
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <div class="flex items-center gap-2 mb-2">
            <div :class="['w-2.5 h-2.5 rounded-full', statusDot(health.landlord_database?.status)]" />
            <span class="text-sm font-medium">Landlord DB</span>
          </div>
          <p class="text-sm text-gray-500">{{ health.landlord_database?.database }}</p>
          <p class="text-xs text-gray-400 mt-1">{{ health.landlord_database?.latency_ms || '—' }}ms latency</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-2.5 h-2.5 rounded-full bg-green-500" />
            <span class="text-sm font-medium">Tenants</span>
          </div>
          <p class="text-2xl font-bold">{{ health.tenant_count }}</p>
          <p class="text-xs text-gray-400 mt-1">Active tenant databases</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <div class="flex items-center gap-2 mb-2">
            <div :class="['w-2.5 h-2.5 rounded-full', health.redis?.status === 'available' ? 'bg-green-500' : 'bg-red-500']" />
            <span class="text-sm font-medium">Redis</span>
          </div>
          <p class="text-sm text-gray-500">{{ health.redis?.status === 'available' ? 'Connected' : health.redis?.status === 'unavailable' ? 'Disconnected' : 'Unknown' }}</p>
          <p v-if="health.redis?.latency_ms" class="text-xs text-gray-400 mt-1">{{ health.redis.latency_ms }}ms latency</p>
          <p v-else class="text-xs text-gray-400 mt-1">{{ health.queue?.pending_jobs || 0 }} pending jobs</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <div class="flex items-center gap-2 mb-2">
            <HardDrive class="w-4 h-4 text-gray-400" />
            <span class="text-sm font-medium">Disk</span>
          </div>
          <p class="text-2xl font-bold">{{ health.disk?.used }}GB</p>
          <p class="text-xs text-gray-400 mt-1">{{ health.disk?.percent }}% used of {{ health.disk?.total }}GB</p>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">PHP Environment</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">PHP Version</span><span class="font-medium">{{ health.php?.version }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Laravel Version</span><span class="font-medium">{{ health.laravel?.version }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Environment</span><span class="font-medium">{{ health.laravel?.environment }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Queue Pending</span><span class="font-medium">{{ health.queue?.pending_jobs }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Uptime</span><span class="font-medium">{{ health.uptime || '—' }}</span></div>
          </div>
        </div>

        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">Extensions</h3>
          <div class="flex flex-wrap gap-2">
            <span v-for="ext in health.php?.extensions || []" :key="ext" class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded-full">{{ ext }}</span>
          </div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>
