<template>
  <AgentLayout>
    <div class="space-y-6">
      <!-- Back -->
      <Link href="/agent/referrals" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
        <ArrowLeft class="w-4 h-4 mr-1" /> Back to Referrals
      </Link>

      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div v-if="loading" class="text-center py-8 text-gray-500">Loading referral details...</div>
        <div v-else-if="error" class="text-center py-8 text-red-500">{{ error }}</div>
        <div v-else>
          <div class="flex items-start justify-between mb-6">
            <div>
              <h2 class="text-xl font-bold text-gray-900">{{ referral.referral_code }}</h2>
              <p class="text-sm text-gray-600 mt-1">Created {{ formatDate(referral.created_at) }}</p>
            </div>
            <span :class="['text-sm px-3 py-1 rounded-full font-medium', statusColor()]">
              {{ referral.status }}
            </span>
          </div>

          <!-- Timeline -->
          <div class="space-y-4">
            <h3 class="font-semibold text-gray-900">Conversion Timeline</h3>
            <div class="relative pl-6 border-l-2 border-gray-200 space-y-4">
              <TimelineStep label="Link Clicked" :date="referral.clicked_at" color="blue" />
              <TimelineStep label="Account Registered" :date="referral.registered_at" color="indigo" />
              <TimelineStep label="Trial Started" :date="referral.trial_started_at" color="purple" />
              <TimelineStep label="Converted (Paid)" :date="referral.converted_at" color="green" />
              <TimelineStep label="First Payment" :date="referral.first_payment_at" color="emerald" />
            </div>
          </div>

          <!-- Commission -->
          <div v-if="referral.commission_earned > 0" class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
            <p class="text-sm text-green-800">
              <span class="font-semibold">${{ formatNum(referral.commission_earned) }}</span> commission earned from this referral
            </p>
          </div>

          <!-- Tenant Info -->
          <div v-if="referral.tenant_name" class="mt-6 pt-6 border-t border-gray-200">
            <h3 class="font-semibold text-gray-900 mb-2">Referred Business</h3>
            <p class="text-sm text-gray-700">{{ referral.tenant_name }} ({{ referral.tenant_slug }})</p>
          </div>
        </div>
      </div>
    </div>
  </AgentLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, h } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AgentLayout from '@/Layouts/AgentLayout.vue'
import { useAgent } from '@/composables/useAgent'
import { ArrowLeft, Check, Circle } from 'lucide-vue-next'

const page = usePage()
const { fetchReferral } = useAgent()

const referral = ref<any>({})
const loading = ref(true)
const error = ref('')

const id = (page.props as any).id as string

function formatDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Not yet'
}

function statusColor() {
  return referral.value.status === 'converted' ? 'bg-green-100 text-green-700'
    : referral.value.status === 'registered' ? 'bg-blue-100 text-blue-700'
    : referral.value.status === 'clicked' ? 'bg-yellow-100 text-yellow-700'
    : 'bg-gray-100 text-gray-700'
}

function formatNum(n: number) {
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// Timeline step component
const TimelineStep = {
  props: { label: String, date: String, color: String },
  setup(props: any) {
    return () => {
      const hasDate = !!props.date
      const colorClass = hasDate ? `bg-${props.color}-500` : 'bg-gray-300'
      return h('div', { class: 'relative' }, [
        h('div', { class: `absolute -left-[25px] w-3 h-3 rounded-full border-2 border-white ${colorClass}` }),
        h('div', [
          h('p', { class: `text-sm font-medium ${hasDate ? 'text-gray-900' : 'text-gray-400'}` }, props.label),
          h('p', { class: 'text-xs text-gray-500' }, hasDate ? formatDate(props.date) : 'Pending'),
        ]),
      ])
    }
  },
}

onMounted(async () => {
  try {
    referral.value = await fetchReferral(id)
  } catch (e: any) {
    error.value = e.response?.data?.error || 'Failed to load referral'
  }
  loading.value = false
})
</script>
