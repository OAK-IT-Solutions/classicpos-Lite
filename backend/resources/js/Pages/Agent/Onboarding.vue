<template>
  <AgentLayout>
    <div class="space-y-6 max-w-3xl">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Getting Started</h1>
        <p class="text-sm text-gray-600 mt-1">Everything you need to know to start earning commissions.</p>
      </div>

      <!-- Steps -->
      <div class="space-y-4">
        <div v-for="(step, i) in steps" :key="i" class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-start space-x-4">
            <div :class="['w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold', step.done ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
              {{ i + 1 }}
            </div>
            <div class="flex-1">
              <h3 class="font-semibold text-gray-900">{{ step.title }}</h3>
              <p class="text-sm text-gray-600 mt-1">{{ step.description }}</p>
              <div v-if="step.action" class="mt-3">
                <component :is="step.action" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- FAQ -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Frequently Asked Questions</h3>
        <div class="space-y-4">
          <div v-for="(faq, i) in faqs" :key="i">
            <h4 class="text-sm font-medium text-gray-900">{{ faq.q }}</h4>
            <p class="text-sm text-gray-600 mt-1">{{ faq.a }}</p>
          </div>
        </div>
      </div>

      <!-- Commission Tiers -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Commission Tiers</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="tier in tiers" :key="tier.name" :class="['rounded-lg p-4 border-2', tier.current ? 'border-green-500 bg-green-50' : 'border-gray-200']">
            <div class="flex items-center justify-between">
              <h4 class="font-semibold text-gray-900">{{ tier.name }}</h4>
              <span v-if="tier.current" class="text-xs bg-green-600 text-white px-2 py-0.5 rounded-full">Current</span>
            </div>
            <p class="text-2xl font-bold text-green-600 mt-2">{{ tier.rate }}%</p>
            <p class="text-xs text-gray-500 mt-1">{{ tier.requirement }}</p>
          </div>
        </div>
      </div>
    </div>
  </AgentLayout>
</template>

<script setup lang="ts">
import { h } from 'vue'
import AgentLayout from '@/Layouts/AgentLayout.vue'
import { Link } from '@inertiajs/vue3'

const steps = [
  {
    title: 'Share Your Referral Link',
    description: 'Generate a unique referral link and share it with businesses that could benefit from ClassicPOS. Each link is tracked to ensure you get credit.',
    done: true,
    action: () => h(Link, { href: '/agent/referrals', class: 'inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700' }, { default: () => 'Create Referral Link' }),
  },
  {
    title: 'Business Signs Up',
    description: 'When a business uses your link to visit ClassicPOS and creates an account, they are automatically associated with you as their referring agent.',
    done: false,
  },
  {
    title: 'Business Subscribes',
    description: 'Once the business picks a plan and makes their first payment, you earn a commission based on your tier rate.',
    done: false,
  },
  {
    title: 'Earn Commissions',
    description: 'Commissions are credited to your account. You can request a payout once your pending balance reaches $1.00 or more.',
    done: false,
  },
]

const faqs = [
  { q: 'How much can I earn?', a: 'Commission rates range from 15% to 25% depending on your tier. Higher tiers unlock higher rates as you refer more paying customers.' },
  { q: 'When do I get paid?', a: 'Payouts are processed within 3-5 business days after you request one. You need a minimum of $1.00 in pending earnings.' },
  { q: 'What payout methods are available?', a: 'Bank transfer, mobile money (M-Pesa, Airtel, MTN), and Pesapal are available depending on your region.' },
  { q: 'How do I level up?', a: 'Your tier is based on total converted referrals. Hit the thresholds and your rate increases automatically.' },
]

const tiers = [
  { name: 'Standard', rate: 15, requirement: '0-4 conversions', current: false },
  { name: 'Silver', rate: 18, requirement: '5-14 conversions', current: true },
  { name: 'Gold', rate: 22, requirement: '15-29 conversions', current: false },
  { name: 'Platinum', rate: 25, requirement: '30+ conversions', current: false },
]
</script>
