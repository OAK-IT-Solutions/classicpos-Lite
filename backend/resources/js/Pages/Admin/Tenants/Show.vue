<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTenants } from '@/composables/useAdmin'
import { ArrowLeft, Shield, Building2, CreditCard, Clock } from 'lucide-vue-next'

const props = defineProps<{ id: string }>()
const { fetchTenant, suspendTenant, activateTenant } = useTenants()
const tenant = ref<any>(null)
const loading = ref(true)

onMounted(async () => {
  tenant.value = await fetchTenant(props.id)
  loading.value = false
})

function statusColor(status: string) {
  return { active: 'bg-green-50 text-green-700', trialing: 'bg-blue-50 text-blue-700', suspended: 'bg-yellow-50 text-yellow-700', cancelled: 'bg-gray-100 text-gray-500' }[status] || 'bg-gray-100'
}

async function handleSuspend() {
  if (!confirm('Suspend this tenant?')) return
  tenant.value = await suspendTenant(props.id)
}

async function handleActivate() {
  tenant.value = await activateTenant(props.id)
}
</script>

<template>
  <AdminLayout>
    <Link href="/admin/tenants" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
      <ArrowLeft class="w-4 h-4" /> Back to Tenants
    </Link>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <template v-else-if="tenant">
      <div class="flex items-start justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">{{ tenant.name }}</h1>
          <p class="text-sm text-gray-500 mt-1">{{ tenant.slug }}.classicpos.app</p>
        </div>
        <div class="flex items-center gap-2">
          <span :class="['text-xs font-medium px-3 py-1 rounded-full', statusColor(tenant.status)]">{{ tenant.status }}</span>
          <button v-if="tenant.status === 'active'" @click="handleSuspend" class="px-3 py-1.5 text-sm bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100">Suspend</button>
          <button v-if="tenant.status === 'suspended'" @click="handleActivate" class="px-3 py-1.5 text-sm bg-green-50 text-green-700 rounded-lg hover:bg-green-100">Activate</button>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-xl p-6 border border-gray-100">
            <h3 class="font-semibold text-gray-900 mb-4">Business Information</h3>
            <div class="grid sm:grid-cols-2 gap-4 text-sm">
              <div><span class="text-gray-500">Business Name:</span> <span class="font-medium">{{ tenant.business_name || 'N/A' }}</span></div>
              <div><span class="text-gray-500">Email:</span> <span class="font-medium">{{ tenant.business_email || 'N/A' }}</span></div>
              <div><span class="text-gray-500">Phone:</span> <span class="font-medium">{{ tenant.business_phone || 'N/A' }}</span></div>
              <div><span class="text-gray-500">Created:</span> <span class="font-medium">{{ new Date(tenant.created_at).toLocaleDateString() }}</span></div>
            </div>
          </div>

          <div class="bg-white rounded-xl p-6 border border-gray-100">
            <h3 class="font-semibold text-gray-900 mb-4">Database</h3>
            <div class="grid sm:grid-cols-2 gap-4 text-sm">
              <div><span class="text-gray-500">Database:</span> <span class="font-mono text-xs">{{ tenant.db_name }}</span></div>
              <div><span class="text-gray-500">Host:</span> <span class="font-mono text-xs">{{ tenant.db_host }}:{{ tenant.db_port }}</span></div>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="bg-white rounded-xl p-6 border border-gray-100">
            <h3 class="font-semibold text-gray-900 mb-4">Subscription</h3>
            <div v-if="tenant.subscription" class="text-sm space-y-2">
              <div class="flex items-center gap-2">
                <CreditCard class="w-4 h-4 text-gray-400" />
                <span class="font-medium">{{ tenant.subscription.plan?.name || 'N/A' }}</span>
              </div>
              <div class="flex items-center gap-2">
                <Clock class="w-4 h-4 text-gray-400" />
                <span>{{ tenant.subscription.status }} · {{ tenant.subscription.billing_cycle }}</span>
              </div>
              <div v-if="tenant.subscription.ends_at" class="text-xs text-gray-500">
                Ends: {{ new Date(tenant.subscription.ends_at).toLocaleDateString() }}
              </div>
            </div>
            <p v-else class="text-sm text-gray-400">No subscription</p>
          </div>

          <div class="bg-white rounded-xl p-6 border border-gray-100">
            <h3 class="font-semibold text-gray-900 mb-4">Stats</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Support Tickets</span>
                <span class="font-medium">{{ tenant.support_tickets_count ?? 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Payments</span>
                <span class="font-medium">{{ tenant.payment_transactions_count ?? 0 }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>
