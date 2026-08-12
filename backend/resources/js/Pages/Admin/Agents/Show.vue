<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormSlideOver from '@/Components/FormSlideOver.vue'
import { useAgents } from '@/composables/useAdmin'
import { ArrowLeft, Users, DollarSign, TrendingUp, Edit3, Building2, Activity, Link2 } from 'lucide-vue-next'

const props = defineProps<{ id: string }>()
const { fetchAgent, updateAgent, fetchPerformance } = useAgents()
const agent = ref<any>(null)
const performance = ref<any>(null)
const loading = ref(true)
const showForm = ref(false)
const formLoading = ref(false)
const formError = ref('')
const form = ref({ name: '', email: '', phone: '', commission_rate: 10, tier: 'standard', is_active: true })

onMounted(async () => {
  agent.value = await fetchAgent(props.id)
  try {
    performance.value = await fetchPerformance(props.id)
  } catch { }
  loading.value = false
})

function fmt(n: number) { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(n) }

function tierColor(tier: string) {
  return { platinum: 'bg-purple-50 text-purple-700', gold: 'bg-yellow-50 text-yellow-700', silver: 'bg-gray-100 text-gray-600', standard: 'bg-blue-50 text-blue-700' }[tier] || 'bg-gray-100'
}

function openEdit() {
  form.value = {
    name: agent.value.name,
    email: agent.value.email,
    phone: agent.value.phone || '',
    commission_rate: agent.value.commission_rate,
    tier: agent.value.tier,
    is_active: agent.value.is_active,
  }
  formError.value = ''
  showForm.value = true
}

async function handleSubmit() {
  formLoading.value = true
  formError.value = ''
  try {
    agent.value = await updateAgent(props.id, form.value)
    showForm.value = false
  } catch (e: any) {
    formError.value = e?.response?.data?.message || e?.response?.data?.error || 'Failed to save agent'
  } finally {
    formLoading.value = false
  }
}
</script>

<template>
  <AdminLayout>
    <Link href="/admin/agents" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
      <ArrowLeft class="w-4 h-4" /> Back to Agents
    </Link>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <template v-else-if="agent">
      <div class="flex items-start justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">{{ agent.name }}</h1>
          <p class="text-sm text-gray-500 mt-1">{{ agent.email }} · {{ agent.code }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span :class="['text-xs font-medium px-3 py-1 rounded-full', agent.is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-500']">{{ agent.is_active ? 'Active' : 'Inactive' }}</span>
          <span :class="['text-xs font-medium px-3 py-1 rounded-full', tierColor(agent.tier)]">{{ agent.tier }}</span>
          <button @click="openEdit" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            <Edit3 class="w-3.5 h-3.5" /> Edit
          </button>
        </div>
      </div>

      <div class="grid sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <DollarSign class="w-5 h-5 text-green-600 mb-2" />
          <p class="text-2xl font-bold">{{ fmt(agent.total_earnings) }}</p>
          <p class="text-sm text-gray-500">Total Earnings</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <TrendingUp class="w-5 h-5 text-blue-600 mb-2" />
          <p class="text-2xl font-bold">{{ fmt(agent.pending_earnings) }}</p>
          <p class="text-sm text-gray-500">Pending</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <Users class="w-5 h-5 text-purple-600 mb-2" />
          <p class="text-2xl font-bold">{{ agent.conversion_rate || agent.conversionRate?.() || 0 }}%</p>
          <p class="text-sm text-gray-500">Conversion Rate</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <Building2 class="w-5 h-5 text-orange-600 mb-2" />
          <p class="text-2xl font-bold">{{ agent.total_referrals }}</p>
          <p class="text-sm text-gray-500">Total Referrals</p>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">Agent Details</h3>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Commission Rate</span><span class="font-medium">{{ agent.commission_rate }}%</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Total Referrals</span><span class="font-medium">{{ agent.total_referrals }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Converted</span><span class="font-medium">{{ agent.converted_referrals }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Conversion Rate</span><span class="font-medium">{{ agent.conversion_rate || 0 }}%</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Paid Earnings</span><span class="font-medium">{{ fmt(agent.paid_earnings) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Phone</span><span class="font-medium">{{ agent.phone || '—' }}</span></div>
          </div>
        </div>

        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">Recent Commissions</h3>
          <div class="space-y-2">
            <div v-for="c in agent.commissions || []" :key="c.id" class="flex items-center justify-between text-sm py-1.5">
              <span class="text-gray-600 capitalize">{{ c.type?.replace('_', ' ') }}</span>
              <div class="flex items-center gap-2">
                <span :class="['text-xs px-2 py-0.5 rounded-full', c.status === 'paid' ? 'bg-green-50 text-green-700' : c.status === 'cleared' ? 'bg-blue-50 text-blue-700' : c.status === 'pending' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-500']">{{ c.status }}</span>
                <span class="font-medium">{{ fmt(c.amount) }}</span>
              </div>
            </div>
            <div v-if="!agent.commissions?.length" class="text-sm text-gray-400 text-center py-4">No commissions yet</div>
          </div>
        </div>
      </div>

      <div v-if="agent.referrals?.length" class="bg-white rounded-xl p-6 border border-gray-100 mb-6">
        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Link2 class="w-4 h-4 text-gray-400" /> Referral Codes
        </h3>
        <div class="space-y-2">
          <div v-for="r in agent.referrals" :key="r.id" class="flex items-center justify-between text-sm py-1.5">
            <span class="font-mono text-gray-900">{{ r.referral_code }}</span>
            <span :class="['text-xs px-2 py-0.5 rounded-full', r.converted_at ? 'bg-green-100 text-green-700' : r.registered_at ? 'bg-blue-100 text-blue-700' : r.clicked_at ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700']">
              {{ r.converted_at ? 'converted' : r.registered_at ? 'registered' : r.clicked_at ? 'clicked' : 'created' }}
            </span>
          </div>
        </div>
      </div>

      <div v-if="performance" class="bg-white rounded-xl p-6 border border-gray-100">
        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Activity class="w-4 h-4 text-gray-400" /> Performance Overview
        </h3>
        <div class="grid sm:grid-cols-3 gap-4">
          <div class="p-4 bg-gray-50 rounded-lg">
            <p class="text-xs text-gray-500 mb-1">Total Earnings</p>
            <p class="text-lg font-bold">{{ fmt(performance.total_earnings) }}</p>
          </div>
          <div class="p-4 bg-gray-50 rounded-lg">
            <p class="text-xs text-gray-500 mb-1">Pending Earnings</p>
            <p class="text-lg font-bold">{{ fmt(performance.pending_earnings) }}</p>
          </div>
          <div class="p-4 bg-gray-50 rounded-lg">
            <p class="text-xs text-gray-500 mb-1">Paid Earnings</p>
            <p class="text-lg font-bold">{{ fmt(performance.paid_earnings) }}</p>
          </div>
        </div>
        <div v-if="performance.referrals?.length" class="mt-4">
          <p class="text-xs text-gray-500 mb-2">Monthly Referrals</p>
          <div class="flex gap-2 flex-wrap">
            <div v-for="r in performance.referrals" :key="r.month" class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded">
              {{ r.month?.substring(0, 7) }}: {{ r.total }} ({{ r.converted }} converted)
            </div>
          </div>
        </div>
      </div>
    </template>

    <FormSlideOver title="Edit Agent" :visible="showForm" :loading="formLoading" :error="formError" @close="showForm = false" @submit="handleSubmit">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
          <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="form.email" type="email" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input v-model="form.phone" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Commission Rate (%)</label>
          <input v-model.number="form.commission_rate" type="number" min="0" max="100" step="0.1" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tier</label>
          <select v-model="form.tier" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="standard">Standard</option>
            <option value="silver">Silver</option>
            <option value="gold">Gold</option>
            <option value="platinum">Platinum</option>
          </select>
        </div>
        <div>
          <label class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm font-medium text-gray-700">Active</span>
          </label>
        </div>
      </div>
    </FormSlideOver>
  </AdminLayout>
</template>
