<script setup lang="ts">
import { onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useRevenue } from '@/composables/useAdmin'
import {
  Building2, CreditCard, DollarSign, Users, HeadphonesIcon,
  TrendingUp, TrendingDown, ArrowUpRight,
} from 'lucide-vue-next'

const { dashboard, loading, fetchDashboard } = useRevenue()

onMounted(() => fetchDashboard())

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'KES', minimumFractionDigits: 0 }).format(amount)
}
</script>

<template>
  <AdminLayout>
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
      <p class="text-sm text-gray-500 mt-1">Platform overview and key metrics</p>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">Loading dashboard...</div>

    <template v-else-if="dashboard">
      <!-- KPI Cards -->
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
              <Building2 class="w-5 h-5 text-blue-600" />
            </div>
            <span class="text-sm font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
              {{ dashboard.tenants.active }} active
            </span>
          </div>
          <p class="text-2xl font-bold">{{ dashboard.tenants.total }}</p>
          <p class="text-sm text-gray-500 mt-1">Total Tenants</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
              <DollarSign class="w-5 h-5 text-green-600" />
            </div>
            <span
              :class="[
                'text-sm font-medium px-2 py-0.5 rounded-full',
                dashboard.mrr_growth >= 0 ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50',
              ]"
            >
              <span v-if="dashboard.mrr_growth >= 0">+</span>{{ dashboard.mrr_growth }}%
            </span>
          </div>
          <p class="text-2xl font-bold">{{ formatCurrency(dashboard.mrr) }}</p>
          <p class="text-sm text-gray-500 mt-1">Monthly Recurring Revenue</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center">
              <CreditCard class="w-5 h-5 text-purple-600" />
            </div>
            <span class="text-sm font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
              {{ dashboard.active_subscriptions }} active
            </span>
          </div>
          <p class="text-2xl font-bold">{{ formatCurrency(dashboard.revenue_this_month) }}</p>
          <p class="text-sm text-gray-500 mt-1">Revenue This Month</p>
        </div>

        <div class="bg-white rounded-xl p-5 border border-gray-100">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center">
              <HeadphonesIcon class="w-5 h-5 text-orange-600" />
            </div>
            <span
              :class="[
                'text-sm font-medium px-2 py-0.5 rounded-full',
                dashboard.open_tickets > 0 ? 'text-orange-600 bg-orange-50' : 'text-green-600 bg-green-50',
              ]"
            >
              {{ dashboard.open_tickets }} open
            </span>
          </div>
          <p class="text-2xl font-bold">{{ dashboard.pending_commissions > 0 ? formatCurrency(dashboard.pending_commissions) : '0' }}</p>
          <p class="text-sm text-gray-500 mt-1">Pending Commissions</p>
        </div>
      </div>

      <!-- Tenant Status Breakdown -->
      <div class="grid lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">Tenant Status</h3>
          <div class="space-y-3">
            <div v-for="(count, status) in { active: dashboard.tenants.active, trialing: dashboard.tenants.trialing, suspended: dashboard.tenants.suspended, cancelled: dashboard.tenants.cancelled }" :key="status" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div :class="[
                  'w-2.5 h-2.5 rounded-full',
                  status === 'active' ? 'bg-green-500' :
                  status === 'trialing' ? 'bg-blue-500' :
                  status === 'suspended' ? 'bg-yellow-500' : 'bg-gray-400',
                ]" />
                <span class="text-sm text-gray-600 capitalize">{{ status }}</span>
              </div>
              <span class="text-sm font-semibold">{{ count }}</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl p-6 border border-gray-100">
          <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
          <div class="space-y-2">
            <a href="/admin/tenants" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
              <Building2 class="w-4 h-4 text-gray-400" />
              Manage Tenants
            </a>
            <a href="/admin/plans" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
              <CreditCard class="w-4 h-4 text-gray-400" />
              Manage Plans
            </a>
            <a href="/admin/tickets" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
              <HeadphonesIcon class="w-4 h-4 text-gray-400" />
              Support Tickets
            </a>
            <a href="/admin/agents" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
              <Users class="w-4 h-4 text-gray-400" />
              Manage Agents
            </a>
            <a href="/admin/health" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-sm text-gray-700">
              <TrendingUp class="w-4 h-4 text-gray-400" />
              System Health
            </a>
          </div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>
