<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTenants } from '@/composables/useAdmin'
import { Search, Plus, Eye, MoreVertical } from 'lucide-vue-next'

const { tenants, loading, fetchTenants } = useTenants()
const search = ref('')
const showCreate = ref(false)
const form = ref({ name: '', email: '', password: '', business_name: '', plan: 'starter' })

onMounted(() => fetchTenants())

function doSearch() {
  fetchTenants({ search: search.value })
}

function statusColor(status: string) {
  return { active: 'bg-green-50 text-green-700', trialing: 'bg-blue-50 text-blue-700', suspended: 'bg-yellow-50 text-yellow-700', cancelled: 'bg-gray-100 text-gray-500' }[status] || 'bg-gray-100'
}
</script>

<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Tenants</h1>
        <p class="text-sm text-gray-500 mt-1">Manage all tenant accounts</p>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100">
      <div class="p-4 border-b border-gray-100 flex items-center gap-3">
        <div class="relative flex-1 max-w-sm">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input v-model="search" @input="doSearch" placeholder="Search tenants..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <div v-if="loading" class="p-8 text-center text-gray-400">Loading...</div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3">Tenant</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Plan</th>
            <th class="px-4 py-3">Created</th>
            <th class="px-4 py-3 w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="t in tenants?.data || []" :key="t.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ t.name }}</p>
                <p class="text-xs text-gray-500">{{ t.slug }}.classicpos.app</p>
              </div>
            </td>
            <td class="px-4 py-3">
              <span :class="['text-xs font-medium px-2 py-1 rounded-full', statusColor(t.status)]">
                {{ t.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ t.subscription?.plan?.name || 'None' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ new Date(t.created_at).toLocaleDateString() }}</td>
            <td class="px-4 py-3">
              <Link :href="`/admin/tenants/${t.id}`" class="p-1.5 hover:bg-gray-100 rounded-lg inline-flex">
                <Eye class="w-4 h-4 text-gray-400" />
              </Link>
            </td>
          </tr>
          <tr v-if="!tenants?.data?.length">
            <td colspan="5" class="px-4 py-12 text-center text-gray-400">No tenants found</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
