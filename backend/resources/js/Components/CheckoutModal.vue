<template>
  <div>
    <button
      @click="open = true"
      :class="[
        'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
        variant === 'primary' ? 'bg-green-600 text-white hover:bg-green-700' : 'border border-gray-300 text-gray-700 hover:bg-gray-50',
      ]"
    >
      <slot>Upgrade Plan</slot>
    </button>

    <!-- Modal -->
    <div v-if="open" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Choose Billing Cycle</h3>

        <div class="space-y-3">
          <button
            v-for="cycle in cycles"
            :key="cycle.value"
            @click="selectedCycle = cycle.value"
            :class="[
              'w-full p-4 rounded-lg border-2 text-left transition-colors',
              selectedCycle === cycle.value ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300',
            ]"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="font-medium text-gray-900">{{ cycle.label }}</p>
                <p class="text-sm text-gray-600">{{ cycle.description }}</p>
              </div>
              <div class="text-right">
                <p class="text-lg font-bold text-green-600">${{ formatNum(cycle.price) }}</p>
                <p class="text-xs text-gray-500">{{ cycle.per }}</p>
              </div>
            </div>
            <p v-if="cycle.savings" class="text-xs text-green-600 mt-2 font-medium">Save {{ cycle.savings }}</p>
          </button>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
          <button @click="open = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg text-sm">Cancel</button>
          <button
            @click="proceed"
            class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700"
          >
            Continue to Checkout
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  planId: string
  priceMonthly: number
  priceYearly: number
  variant?: 'primary' | 'secondary'
}>()

const open = ref(false)
const selectedCycle = ref('monthly')

const cycles = computed(() => [
  {
    value: 'monthly',
    label: 'Monthly',
    description: 'Billed every month',
    price: props.priceMonthly,
    per: '/month',
    savings: null,
  },
  {
    value: 'yearly',
    label: 'Yearly',
    description: 'Billed annually',
    price: props.priceYearly,
    per: '/year',
    savings: `${Math.round((1 - props.priceYearly / (props.priceMonthly * 12)) * 100)}% vs monthly`,
  },
])

function proceed() {
  open.value = false
  router.visit(`/billing/checkout?plan=${props.planId}&cycle=${selectedCycle.value}`)
}

function formatNum(n: number) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
