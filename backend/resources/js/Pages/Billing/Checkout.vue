<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-text-theme">Checkout</h1>
        <p class="text-sm text-text-secondary mt-1">Complete your subscription payment.</p>
      </div>

      <!-- Plan Summary -->
      <div class="bg-surface rounded-xl border border-border-theme p-6">
        <h3 class="font-semibold text-text-theme mb-4">Order Summary</h3>
        <div v-if="plan" class="space-y-3">
          <div class="flex justify-between text-sm">
            <span class="text-text-secondary">Plan</span>
            <span class="font-medium text-text-theme">{{ plan.name }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-text-secondary">Billing Cycle</span>
            <span class="font-medium text-text-theme capitalize">{{ billingCycle }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Amount</span>
            <span class="font-bold text-green-600 text-lg">${{ formatNum(amount) }}</span>
          </div>
        </div>
        <div v-else class="text-center py-4 text-text-tertiary text-sm">Loading plan details...</div>
      </div>

      <!-- Status -->
      <div v-if="status === 'processing'" class="bg-blue-50 border border-blue-200 rounded-xl p-6 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-3"></div>
        <p class="text-sm text-blue-800">Processing your payment...</p>
      </div>

      <div v-else-if="status === 'success'" class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
        <CheckCircle class="w-12 h-12 text-green-600 mx-auto mb-3" />
        <h3 class="font-semibold text-green-800 text-lg">Payment Successful!</h3>
        <p class="text-sm text-green-700 mt-1">Your subscription has been activated.</p>
        <Link href="/settings/subscription" class="mt-4 inline-block px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
          View Subscription
        </Link>
      </div>

      <div v-else-if="status === 'failed'" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
        <XCircle class="w-12 h-12 text-red-600 mx-auto mb-3" />
        <h3 class="font-semibold text-red-800 text-lg">Payment Failed</h3>
        <p class="text-sm text-red-700 mt-1">{{ errorMessage || 'Payment could not be processed.' }}</p>
        <button @click="initCheckout" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">
          Try Again
        </button>
      </div>

      <div v-else class="bg-surface rounded-xl border border-border-theme p-6">
        <p class="text-sm text-text-secondary mb-4">Click below to proceed to Pesapal secure payment gateway.</p>
        <button
          @click="initCheckout"
          :disabled="!plan || processing"
          class="w-full py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50"
        >
          {{ processing ? 'Redirecting...' : 'Pay with Pesapal' }}
        </button>
      </div>

      <!-- Pesapal Iframe -->
      <div v-if="checkoutUrl" class="bg-surface rounded-xl border border-border-theme overflow-hidden">
        <iframe :src="checkoutUrl" class="w-full h-[600px] border-0" @load="onIframeLoad" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { CheckCircle, XCircle } from 'lucide-vue-next'

const page = usePage()

const planId = (page.props as any).planId as string
const billingCycle = ref((page.props as any).billingCycle || 'monthly')

const plan = ref<any>(null)
const amount = ref(0)
const checkoutUrl = ref('')
const processing = ref(false)
const status = ref<'idle' | 'processing' | 'success' | 'failed'>('idle')
const errorMessage = ref('')
const orderId = ref('')

async function loadPlan() {
  try {
    const { data } = await axios.get('/api/v1/subscriptions/plans')
    const plans = data.plans || data
    plan.value = Array.isArray(plans) ? plans.find((p: any) => p.id === planId) : null
    if (plan.value) {
      amount.value = billingCycle.value === 'yearly' ? plan.value.price_yearly : plan.value.price_monthly
    }
  } catch {}
}

async function initCheckout() {
  processing.value = true
  try {
    const { data } = await axios.post('/api/v1/billing/checkout', {
      plan_id: planId,
      billing_cycle: billingCycle.value,
    })
    checkoutUrl.value = data.checkout_url
    orderId.value = data.order_id
    status.value = 'processing'
  } catch (e: any) {
    status.value = 'failed'
    errorMessage.value = e.response?.data?.error || 'Failed to initiate checkout'
  }
  processing.value = false
}

function onIframeLoad() {
  // When Pesapal redirects back, the iframe reloads
  if (orderId.value) {
    pollStatus()
  }
}

async function pollStatus() {
  let attempts = 0
  const maxAttempts = 30
  while (attempts < maxAttempts) {
    try {
      const { data } = await axios.get(`/api/v1/billing/status/${orderId.value}`)
      if (data.status === 'success') {
        status.value = 'success'
        return
      }
      if (data.status === 'failed' || data.status === 'cancelled') {
        status.value = 'failed'
        return
      }
    } catch {}
    await new Promise(r => setTimeout(r, 2000))
    attempts++
  }
}

function formatNum(n: number) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(loadPlan)
</script>
