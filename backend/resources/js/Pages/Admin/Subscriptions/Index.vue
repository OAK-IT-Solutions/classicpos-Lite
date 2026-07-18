<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import { useSubscriptions, usePlans } from '@/composables/useAdmin'
import { Eye, ArrowUpDown, XCircle } from 'lucide-vue-next'

const { subscriptions, loading, fetchSubscriptions, changePlan, cancelSubscription } = useSubscriptions()
const { plans, fetchPlans } = usePlans()
const statusFilter = ref('')
const planFilter = ref('')
const showChangePlan = ref(false)
const changingSub = ref<any>(null)
const changeForm = ref({ plan_id: '', billing_cycle: 'monthly' })
const changeLoading = ref(false)
const changeError = ref('')
const cancelling = ref<string | null>(null)

onMounted(() => { fetchSubs(); fetchPlans() })

async function fetchSubs() {
  const params: Record<string, any> = {}
  if (statusFilter.value) params.status = statusFilter.value
  if (planFilter.value) params.plan_id = planFilter.value
  fetchSubscriptions(params)
}

function statusColor(s: string) {
  return { active: 'bg-green-50 text-green-700', trialing: 'bg-blue-50 text-blue-700', past_due: 'bg-yellow-50 text-yellow-700', cancelled: 'bg-gray-100 text-gray-500', expired: 'bg-red-50 text-red-700' }[s] || 'bg-gray-100'
}

function openChangePlan(sub: any) {
  changingSub.value = sub
  changeForm.value = { plan_id: sub.plan_id || '', billing_cycle: sub.billing_cycle || 'monthly' }
  changeError.value = ''
  showChangePlan.value = true
}

async function handleChangePlan() {
  if (!changingSub.value) return
  changeLoading.value = true
  changeError.value = ''
  try {
    await changePlan(changingSub.value.id, changeForm.value)
    showChangePlan.value = false
    fetchSubs()
  } catch (e: any) {
    changeError.value = e?.response?.data?.error || e?.response?.data?.message || 'Failed to change plan'
  } finally {
    changeLoading.value = false
  }
}

async function handleCancel(id: string) {
  if (!confirm('Cancel this subscription? The tenant will lose access at the end of the billing period.')) return
  cancelling.value = id
  try {
    await cancelSubscription(id)
    fetchSubs()
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to cancel subscription')
  } finally {
    cancelling.value = null
  }
}
</script>

<template>
  <AdminLayout>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Subscriptions</h1>
      <p class="text-sm text-gray-500 mt-1">All subscriptions across tenants</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100">
      <div class="p-4 border-b border-gray-100 flex items-center gap-3 flex-wrap">
        <select v-model="statusFilter" @change="fetchSubs" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All Statuses</option>
          <option v-for="s in ['active','trialing','past_due','cancelled','expired']" :key="s" :value="s">{{ s }}</option>
        </select>
        <select v-model="planFilter" @change="fetchSubs" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">All Plans</option>
          <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div v-if="loading" class="p-8 text-center text-gray-400">Loading...</div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3">Tenant</th>
            <th class="px-4 py-3">Plan</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Billing</th>
            <th class="px-4 py-3">Starts</th>
            <th class="px-4 py-3">Ends</th>
            <th class="px-4 py-3 w-32"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="sub in subscriptions?.data || []" :key="sub.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ sub.tenant?.name || 'Unknown' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ sub.plan?.name || 'N/A' }}</td>
            <td class="px-4 py-3"><span :class="['text-xs font-medium px-2 py-1 rounded-full', statusColor(sub.status)]">{{ sub.status }}</span></td>
            <td class="px-4 py-3 text-sm capitalize text-gray-600">{{ sub.billing_cycle }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ sub.starts_at ? new Date(sub.starts_at).toLocaleDateString() : '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ sub.ends_at ? new Date(sub.ends_at).toLocaleDateString() : '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1">
                <Link :href="`/admin/subscriptions/${sub.id}`" class="p-1.5 hover:bg-gray-100 rounded-lg inline-flex" title="View">
                  <Eye class="w-4 h-4 text-gray-400" />
                </Link>
                <button v-if="sub.status === 'active' || sub.status === 'trialing'" @click="openChangePlan(sub)" class="p-1.5 hover:bg-gray-100 rounded-lg inline-flex" title="Change Plan">
                  <ArrowUpDown class="w-4 h-4 text-blue-400" />
                </button>
                <button v-if="sub.status === 'active' || sub.status === 'trialing'" @click="handleCancel(sub.id)" :disabled="cancelling === sub.id" class="p-1.5 hover:bg-red-50 rounded-lg inline-flex" title="Cancel">
                  <XCircle class="w-4 h-4 text-red-400" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!subscriptions?.data?.length">
            <td colspan="7" class="px-4 py-12 text-center text-gray-400">No subscriptions found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <FormSlideOver title="Change Plan" :visible="showChangePlan" :loading="changeLoading" :error="changeError" @close="showChangePlan = false" @submit="handleChangePlan">
      <div class="space-y-4">
        <div v-if="changingSub">
          <p class="text-sm text-gray-500 mb-1">Current Plan</p>
          <p class="text-sm font-medium text-gray-900">{{ changingSub.plan?.name }} ({{ changingSub.billing_cycle }})</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">New Plan</label>
          <select v-model="changeForm.plan_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Select a plan...</option>
            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }} (${{ p.price_monthly }}/mo)</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
          <select v-model="changeForm.billing_cycle" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
        </div>
      </div>
    </FormSlideOver>
  </AdminLayout>
</template>
