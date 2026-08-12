<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import { useSubscriptions, usePlans } from '@/composables/useAdmin'
import { ArrowLeft, DollarSign, Calendar, Building2, CreditCard, ArrowUpDown, XCircle } from 'lucide-vue-next'

const props = defineProps<{ id: string }>()
const { fetchSubscription, changePlan, cancelSubscription } = useSubscriptions()
const { plans, fetchPlans } = usePlans()
const sub = ref<any>(null)
const loading = ref(true)
const showChangePlan = ref(false)
const changeForm = ref({ plan_id: '', billing_cycle: 'monthly' })
const changeLoading = ref(false)
const changeError = ref('')
const cancelling = ref(false)

onMounted(async () => {
  sub.value = await fetchSubscription(props.id)
  await fetchPlans()
  loading.value = false
})

function fmt(n: number) { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 }).format(n) }

function statusColor(s: string) {
  return { active: 'bg-green-50 text-green-700', trialing: 'bg-blue-50 text-blue-700', past_due: 'bg-yellow-50 text-yellow-700', cancelled: 'bg-gray-100 text-gray-500', expired: 'bg-red-50 text-red-700' }[s] || 'bg-gray-100'
}

function paymentStatusColor(s: string) {
  return { success: 'bg-green-50 text-green-700', pending: 'bg-yellow-50 text-yellow-700', failed: 'bg-red-50 text-red-500', voided: 'bg-gray-100 text-gray-500' }[s] || 'bg-gray-100'
}

function openChangePlan() {
  changeForm.value = { plan_id: sub.value.plan_id || '', billing_cycle: sub.value.billing_cycle || 'monthly' }
  changeError.value = ''
  showChangePlan.value = true
}

async function handleChangePlan() {
  changeLoading.value = true
  changeError.value = ''
  try {
    sub.value = await changePlan(props.id, changeForm.value)
    showChangePlan.value = false
  } catch (e: any) {
    changeError.value = e?.response?.data?.error || e?.response?.data?.message || 'Failed to change plan'
  } finally {
    changeLoading.value = false
  }
}

async function handleCancel() {
  if (!confirm('Cancel this subscription?')) return
  cancelling.value = true
  try {
    sub.value = await cancelSubscription(props.id)
  } catch (e: any) {
    alert(e?.response?.data?.error || 'Failed to cancel subscription')
  } finally {
    cancelling.value = false
  }
}
</script>

<template>
  <AdminLayout>
    <Link href="/admin/subscriptions" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
      <ArrowLeft class="w-4 h-4" /> Back to Subscriptions
    </Link>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <template v-else-if="sub">
      <div class="flex items-start justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">{{ sub.tenant?.name || 'Unknown' }}</h1>
          <p class="text-sm text-gray-500 mt-1">{{ sub.plan?.name || 'No plan' }} &middot; {{ sub.billing_cycle }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span :class="['text-xs font-medium px-3 py-1 rounded-full', statusColor(sub.status)]">{{ sub.status }}</span>
          <button v-if="sub.status === 'active' || sub.status === 'trialing'" @click="openChangePlan" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
            <ArrowUpDown class="w-3.5 h-3.5" /> Change Plan
          </button>
          <button v-if="sub.status === 'active' || sub.status === 'trialing'" @click="handleCancel" :disabled="cancelling" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50">
            <XCircle class="w-3.5 h-3.5" /> Cancel
          </button>
        </div>
      </div>

      <div class="grid sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <DollarSign class="w-5 h-5 text-green-600 mb-2" />
          <p class="text-2xl font-bold">{{ fmt(sub.amount || sub.plan?.price_monthly || 0) }}</p>
          <p class="text-sm text-gray-500">Monthly Amount</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <Calendar class="w-5 h-5 text-blue-600 mb-2" />
          <p class="text-lg font-bold">{{ sub.starts_at ? new Date(sub.starts_at).toLocaleDateString() : '—' }}</p>
          <p class="text-sm text-gray-500">Started</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <Calendar class="w-5 h-5 text-orange-600 mb-2" />
          <p class="text-lg font-bold">{{ sub.ends_at ? new Date(sub.ends_at).toLocaleDateString() : '—' }}</p>
          <p class="text-sm text-gray-500">Ends</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <Building2 class="w-5 h-5 text-purple-600 mb-2" />
          <p class="text-2xl font-bold">{{ sub.tenant?.slug || '—' }}</p>
          <p class="text-sm text-gray-500">Tenant Slug</p>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">Tenant Info</h3>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Name</span><Link :href="`/admin/tenants/${sub.tenant?.id}`" class="font-medium text-blue-600 hover:text-blue-700">{{ sub.tenant?.name || '—' }}</Link></div>
            <div class="flex justify-between"><span class="text-gray-500">Slug</span><span class="font-medium">{{ sub.tenant?.slug || '—' }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Status</span><span class="font-medium">{{ sub.tenant?.status || '—' }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Created</span><span class="font-medium">{{ sub.tenant?.created_at ? new Date(sub.tenant.created_at).toLocaleDateString() : '—' }}</span></div>
          </div>
        </div>

        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">Plan Details</h3>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Plan</span><span class="font-medium">{{ sub.plan?.name || '—' }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Price</span><span class="font-medium">{{ fmt(sub.plan?.price_monthly || 0) }}/mo</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Billing Cycle</span><span class="font-medium capitalize">{{ sub.billing_cycle }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Branches</span><span class="font-medium">{{ sub.plan?.max_branches || '—' }}</span></div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl p-6 border border-gray-100">
        <h3 class="font-semibold text-gray-900 mb-4">Payment History</h3>
        <div v-if="sub.payment_transactions?.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
              <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Gateway</th>
                <th class="px-4 py-3">Reference</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="txn in sub.payment_transactions" :key="txn.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-500">{{ txn.paid_at ? new Date(txn.paid_at).toLocaleDateString() : new Date(txn.created_at).toLocaleDateString() }}</td>
                <td class="px-4 py-3 font-medium">{{ fmt(txn.amount) }}</td>
                <td class="px-4 py-3"><span :class="['text-xs px-2 py-0.5 rounded-full', paymentStatusColor(txn.status)]">{{ txn.status }}</span></td>
                <td class="px-4 py-3 text-gray-600">{{ txn.gateway || '—' }}</td>
                <td class="px-4 py-3 text-xs font-mono text-gray-500">{{ txn.gateway_ref || txn.order_tracking_id || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-sm text-gray-400 text-center py-6">No payment transactions yet</div>
      </div>
    </template>

    <FormSlideOver title="Change Plan" :visible="showChangePlan" :loading="changeLoading" :error="changeError" @close="showChangePlan = false" @submit="handleChangePlan">
      <div class="space-y-4">
        <div>
          <p class="text-sm text-gray-500 mb-1">Current Plan</p>
          <p class="text-sm font-medium text-gray-900">{{ sub?.plan?.name }} ({{ sub?.billing_cycle }})</p>
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
