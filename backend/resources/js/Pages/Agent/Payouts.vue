<template>
  <AgentLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Payouts</h1>
          <p class="text-sm text-gray-600 mt-1">Request payouts and track payment history.</p>
        </div>
        <button
          @click="showRequest = true"
          :disabled="pendingEarnings < 1"
          class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Request Payout
        </button>
      </div>

      <!-- Pending Balance -->
      <div class="bg-gradient-to-r from-green-600 to-green-700 rounded-xl p-6 text-white">
        <p class="text-sm opacity-80">Available for Payout</p>
        <p class="text-3xl font-bold mt-1">${{ formatNum(pendingEarnings) }}</p>
        <p class="text-sm opacity-70 mt-2">Minimum payout: $1.00</p>
      </div>

      <!-- Payout History -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
          <h3 class="font-semibold text-gray-900">Payout History</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Method</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Reference</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="loading">
                <td colspan="5" class="py-8 text-center text-gray-500 text-sm">Loading...</td>
              </tr>
              <tr v-else-if="payouts.length === 0">
                <td colspan="5" class="py-8 text-center text-gray-500 text-sm">No payouts yet</td>
              </tr>
              <tr v-for="p in payouts" :key="p.id" class="hover:bg-gray-50">
                <td class="px-5 py-3 text-sm font-semibold text-gray-900">${{ formatNum(p.amount) }}</td>
                <td class="px-5 py-3 text-sm text-gray-700 capitalize">{{ p.gateway }}</td>
                <td class="px-5 py-3 text-sm text-gray-500 font-mono">{{ p.gateway_ref }}</td>
                <td class="px-5 py-3">
                  <span :class="['text-xs px-2 py-0.5 rounded-full', payoutStatusColor(p.status)]">{{ p.status }}</span>
                </td>
                <td class="px-5 py-3 text-sm text-gray-500">{{ formatDate(p.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Request Payout Modal -->
      <div v-if="showRequest" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Request Payout</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Amount ($)</label>
              <input
                v-model.number="payoutAmount"
                type="number"
                :max="pendingEarnings"
                min="1"
                step="0.01"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500"
              />
              <p class="text-xs text-gray-500 mt-1">Available: ${{ formatNum(pendingEarnings) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Payout Method</label>
              <select v-model="payoutMethod" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="bank">Bank Transfer</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="pesapal">Pesapal</option>
              </select>
            </div>

            <!-- Bank details -->
            <div v-if="payoutMethod === 'bank'" class="space-y-3">
              <input v-model="accountDetails.bank_name" placeholder="Bank Name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
              <input v-model="accountDetails.account_number" placeholder="Account Number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
            </div>

            <!-- Mobile money details -->
            <div v-if="payoutMethod === 'mobile_money'" class="space-y-3">
              <input v-model="accountDetails.phone" placeholder="Phone Number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
              <select v-model="accountDetails.network" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Select Network</option>
                <option value="mpesa">M-Pesa</option>
                <option value="airtel">Airtel Money</option>
                <option value="mtn">MTN Mobile Money</option>
              </select>
            </div>

            <p v-if="payoutError" class="text-sm text-red-600">{{ payoutError }}</p>
          </div>
          <div class="flex justify-end space-x-3 mt-6">
            <button @click="showRequest = false; payoutError = ''" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg text-sm">Cancel</button>
            <button @click="handleRequest" :disabled="submitting" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
              {{ submitting ? 'Submitting...' : 'Request Payout' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AgentLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AgentLayout from '@/Layouts/AgentLayout.vue'
import { useAgent } from '@/composables/useAgent'

const { fetchPayouts, requestPayout, fetchDashboard, loading } = useAgent()

const payouts = ref<any[]>([])
const pendingEarnings = ref(0)
const showRequest = ref(false)
const payoutAmount = ref(0)
const payoutMethod = ref('bank')
const accountDetails = ref<Record<string, string>>({})
const submitting = ref(false)
const payoutError = ref('')

async function loadPayouts() {
  try {
    const [pData, dash] = await Promise.all([fetchPayouts(), fetchDashboard()])
    payouts.value = pData.data || []
    pendingEarnings.value = dash.earnings?.pending_earnings ?? 0
    payoutAmount.value = Math.min(pendingEarnings.value, 100)
  } catch {
    payouts.value = []
    pendingEarnings.value = 0
  }
}

async function handleRequest() {
  payoutError.value = ''
  if (payoutAmount.value < 1) { payoutError.value = 'Minimum payout is $1.00'; return }
  if (payoutAmount.value > pendingEarnings.value) { payoutError.value = 'Amount exceeds available balance'; return }

  submitting.value = true
  try {
    await requestPayout({
      amount: payoutAmount.value,
      method: payoutMethod.value,
      account_details: accountDetails.value,
    })
    showRequest.value = false
    await loadPayouts()
  } catch (e: any) {
    payoutError.value = e.response?.data?.error || 'Failed to request payout'
  }
  submitting.value = false
}

function formatNum(n: number) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function payoutStatusColor(s: string) {
  return s === 'success' ? 'bg-green-100 text-green-700' : s === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'
}

onMounted(loadPayouts)
</script>
