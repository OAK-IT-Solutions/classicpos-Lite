<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useRevenue } from '@/composables/useAdmin'
import { DollarSign, TrendingUp, TrendingDown, BarChart3 } from 'lucide-vue-next'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from 'chart.js'
ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const { dashboard, loading, fetchDashboard, fetchMRR, fetchChurn, fetchTrend } = useRevenue()
const mrr = ref<any>(null)
const churn = ref<any>(null)
const trend = ref<any[]>([])

onMounted(async () => {
  await fetchDashboard()
  mrr.value = await fetchMRR()
  churn.value = await fetchChurn()
  trend.value = await fetchTrend(12)
})

function fmt(n: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'KES', minimumFractionDigits: 0 }).format(n)
}

const chartData = computed(() => ({
  labels: trend.value.map((t: any) => new Date(t.month).toLocaleDateString('en', { month: 'short', year: '2-digit' })),
  datasets: [{
    label: 'Revenue',
    data: trend.value.map((t: any) => t.revenue),
    backgroundColor: '#3B82F6',
    borderRadius: 4,
  }]
}))
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { ticks: { callback: (v: any) => '$' + Number(v).toLocaleString() } } },
}
</script>

<template>
  <AdminLayout>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Revenue Analytics</h1>
      <p class="text-sm text-gray-500 mt-1">MRR, ARR, churn, and revenue trends</p>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading...</div>

    <template v-else-if="dashboard">
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <DollarSign class="w-5 h-5 text-green-600 mb-2" />
          <p class="text-2xl font-bold">{{ fmt(dashboard.mrr) }}</p>
          <p class="text-sm text-gray-500">MRR</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <TrendingUp class="w-5 h-5 text-blue-600 mb-2" />
          <p class="text-2xl font-bold">{{ fmt(dashboard.mrr * 12) }}</p>
          <p class="text-sm text-gray-500">ARR</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <BarChart3 class="w-5 h-5 text-purple-600 mb-2" />
          <p class="text-2xl font-bold">{{ fmt(dashboard.revenue_this_year) }}</p>
          <p class="text-sm text-gray-500">Revenue (YTD)</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <TrendingDown class="w-5 h-5 text-orange-600 mb-2" />
          <p class="text-2xl font-bold">{{ churn?.churn_rate || 0 }}%</p>
          <p class="text-sm text-gray-500">Churn Rate</p>
        </div>
      </div>

      <!-- MRR by Plan -->
      <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">MRR by Plan</h3>
          <div class="space-y-3">
            <div v-for="plan in mrr?.by_plan || []" :key="plan.plan_name" class="flex items-center justify-between">
              <span class="text-sm text-gray-600">{{ plan.plan_name }}</span>
              <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400">{{ plan.subscribers }} subscribers</span>
                <span class="text-sm font-semibold">{{ fmt(plan.mrr) }}</span>
              </div>
            </div>
            <div v-if="!mrr?.by_plan?.length" class="text-sm text-gray-400 text-center py-4">No active subscriptions</div>
          </div>
        </div>

        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">Revenue Trend (12 months)</h3>
          <div v-if="trend.length" style="height:280px">
            <Bar :data="chartData" :options="chartOptions" />
          </div>
          <div v-else class="text-sm text-gray-400 text-center py-4">No revenue data yet</div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>
