<template>
  <AgentLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Agent Dashboard</h1>
        <p class="text-sm text-gray-600 mt-1">Welcome back, {{ profile.name }}. Here's your performance overview.</p>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">{{ stat.label }}</p>
              <p class="text-2xl font-bold mt-1" :class="stat.color">{{ stat.value }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="stat.bg">
              <component :is="stat.icon" class="w-5 h-5" :class="stat.iconColor" />
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-2">{{ stat.sub }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Commissions -->
        <div class="bg-white rounded-xl border border-gray-200">
          <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Recent Commissions</h3>
          </div>
          <div class="divide-y divide-gray-100">
            <div v-if="dashboard.recent_commissions?.length === 0" class="p-5 text-center text-gray-500 text-sm">
              No commissions yet
            </div>
            <div v-for="c in dashboard.recent_commissions" :key="c.id" class="px-5 py-3 flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ c.tenant_name || 'Unknown' }}</p>
                <p class="text-xs text-gray-500">{{ c.type }} &middot; {{ formatDate(c.created_at) }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-semibold text-green-600">+${{ formatNum(c.amount) }}</p>
                <span :class="['text-xs px-2 py-0.5 rounded-full', statusColor(c.status)]">{{ c.status }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Referrals -->
        <div class="bg-white rounded-xl border border-gray-200">
          <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Recent Referrals</h3>
          </div>
          <div class="divide-y divide-gray-100">
            <div v-if="dashboard.recent_referrals?.length === 0" class="p-5 text-center text-gray-500 text-sm">
              No referrals yet
            </div>
            <div v-for="r in dashboard.recent_referrals" :key="r.id" class="px-5 py-3 flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ r.tenant_name || 'Pending' }}</p>
                <p class="text-xs text-gray-500">{{ r.referral_code }} &middot; {{ formatDate(r.created_at) }}</p>
              </div>
              <div class="text-right">
                <span :class="['text-xs px-2 py-0.5 rounded-full', referralStatusColor(r.status)]">{{ r.status }}</span>
                <p v-if="r.commission_earned > 0" class="text-xs text-green-600 mt-1">+${{ formatNum(r.commission_earned) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Monthly Earnings Chart (simple bar) -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Monthly Earnings</h3>
        <div v-if="dashboard.monthly_earnings?.length === 0" class="text-center text-gray-500 text-sm py-8">
          No earnings data yet
        </div>
        <div v-else class="flex items-end space-x-2 h-40">
          <div
            v-for="m in dashboard.monthly_earnings"
            :key="m.month"
            class="flex-1 flex flex-col items-center"
          >
            <div class="text-xs text-gray-600 mb-1">${{ formatNum(m.total) }}</div>
            <div
              class="w-full bg-green-500 rounded-t"
              :style="{ height: barHeight(m.total) + '%' }"
            />
            <div class="text-xs text-gray-500 mt-1">{{ m.month.slice(5) }}</div>
          </div>
        </div>
      </div>
    </div>
  </AgentLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AgentLayout from '@/Layouts/AgentLayout.vue'
import { useAgent } from '@/composables/useAgent'
import { TrendingUp, Users, DollarSign, Award } from 'lucide-vue-next'

const { fetchDashboard, fetchProfile } = useAgent()

const dashboard = ref<any>({})
const profile = ref<any>({})

const stats = computed(() => [
  {
    label: 'Total Referrals',
    value: dashboard.value.overview?.total_referrals ?? 0,
    sub: `${dashboard.value.overview?.conversion_rate ?? 0}% conversion rate`,
    icon: Users, bg: 'bg-blue-50', iconColor: 'text-blue-600', color: 'text-blue-600',
  },
  {
    label: 'Total Earnings',
    value: `$${formatNum(dashboard.value.earnings?.total_earnings ?? 0)}`,
    sub: `${dashboard.value.overview?.tier_label ?? 'Agent'}`,
    icon: DollarSign, bg: 'bg-green-50', iconColor: 'text-green-600', color: 'text-green-600',
  },
  {
    label: 'Pending Payout',
    value: `$${formatNum(dashboard.value.earnings?.pending_earnings ?? 0)}`,
    sub: 'Awaiting payout',
    icon: TrendingUp, bg: 'bg-yellow-50', iconColor: 'text-yellow-600', color: 'text-yellow-600',
  },
  {
    label: 'Commission Rate',
    value: `${dashboard.value.overview?.commission_rate ?? 0}%`,
    sub: 'Per converted referral',
    icon: Award, bg: 'bg-purple-50', iconColor: 'text-purple-600', color: 'text-purple-600',
  },
])

const maxEarning = computed(() => {
  const earnings = dashboard.value.monthly_earnings?.map((m: any) => m.total) ?? [0]
  return Math.max(...earnings, 1)
})

function barHeight(total: number) {
  return Math.max((total / maxEarning.value) * 100, 4)
}

function formatNum(n: number) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function statusColor(s: string) {
  return s === 'paid' ? 'bg-green-100 text-green-700' : s === 'cleared' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700'
}

function referralStatusColor(s: string) {
  return s === 'converted' ? 'bg-green-100 text-green-700' : s === 'registered' ? 'bg-blue-100 text-blue-700' : s === 'clicked' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'
}

onMounted(async () => {
  try {
    const [d, p] = await Promise.all([fetchDashboard(), fetchProfile()])
    dashboard.value = d
    profile.value = p
  } catch {
    dashboard.value = { overview: {}, earnings: {}, recent_commissions: [], recent_referrals: [], monthly_earnings: [] }
    profile.value = { name: '' }
  }
})
</script>
