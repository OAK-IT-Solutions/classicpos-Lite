<template>
  <div class="min-h-screen bg-gray-50 flex">
    <!-- Sidebar -->
    <aside
      :class="[
        'bg-white border-r border-gray-200 flex flex-col transition-all duration-300',
        collapsed ? 'w-16' : 'w-64',
        'fixed inset-y-0 left-0 z-30 lg:relative lg:translate-x-0',
        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      ]"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center px-4 border-b border-gray-200 flex-shrink-0">
        <div v-if="!collapsed" class="flex items-center space-x-2">
          <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
            <span class="text-white font-bold text-sm">A</span>
          </div>
          <span class="font-semibold text-gray-900">Agent Portal</span>
        </div>
        <div v-else class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center mx-auto">
          <span class="text-white font-bold text-sm">A</span>
        </div>
      </div>

      <!-- Nav Links -->
      <nav class="flex-1 py-4 px-2 space-y-1 overflow-y-auto">
        <Link
          v-for="item in navItems"
          :key="item.route"
          :href="item.route"
          :class="[
            'flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
            isActive(item.route)
              ? 'bg-green-50 text-green-700'
              : 'text-gray-700 hover:bg-gray-100',
          ]"
        >
          <component :is="item.icon" class="w-5 h-5 flex-shrink-0" :class="collapsed ? 'mx-auto' : 'mr-3'" />
          <span v-if="!collapsed">{{ item.label }}</span>
        </Link>
      </nav>

      <!-- Collapse toggle (desktop only) -->
      <div class="hidden lg:block border-t border-gray-200 p-2">
        <button
          @click="collapsed = !collapsed"
          class="w-full flex items-center justify-center p-2 rounded-lg text-gray-500 hover:bg-gray-100"
        >
          <ChevronLeft v-if="!collapsed" class="w-5 h-5" />
          <ChevronRight v-else class="w-5 h-5" />
        </button>
      </div>
    </aside>

    <!-- Mobile overlay -->
    <div
      v-if="mobileOpen"
      class="fixed inset-0 bg-black/50 z-20 lg:hidden"
      @click="mobileOpen = false"
    />

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Top Bar -->
      <header class="h-16 bg-white border-b border-gray-200 flex items-center px-4 lg:px-6 flex-shrink-0">
        <button @click="mobileOpen = !mobileOpen" class="lg:hidden p-2 text-gray-600 hover:text-gray-900">
          <Menu class="w-5 h-5" />
        </button>

        <div class="flex-1" />

        <!-- Agent info -->
        <Link href="/agent/profile" class="flex items-center space-x-3 hover:bg-gray-50 rounded-lg px-2 py-1 transition-colors">
          <div class="text-right hidden sm:block">
            <div class="text-sm font-medium text-gray-900">{{ agentName }}</div>
            <div class="text-xs text-gray-500">{{ agentTier }}</div>
          </div>
          <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
            <User class="w-4 h-4 text-green-600" />
          </div>
        </Link>
      </header>

      <!-- Page Content -->
      <main class="flex-1 overflow-y-auto p-4 lg:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  LayoutDashboard, Users, DollarSign, CreditCard, BookOpen,
  ChevronLeft, ChevronRight, Menu, User, Settings,
} from 'lucide-vue-next'
import { useAgent } from '@/composables/useAgent'

const page = usePage()
const { fetchProfile } = useAgent()

const collapsed = ref(false)
const mobileOpen = ref(false)
const agentName = ref('Agent')
const agentTier = ref('Agent')

const navItems = computed(() => [
  { label: 'Dashboard', route: '/agent', icon: LayoutDashboard },
  { label: 'Referrals', route: '/agent/referrals', icon: Users },
  { label: 'Commissions', route: '/agent/commissions', icon: DollarSign },
  { label: 'Payouts', route: '/agent/payouts', icon: CreditCard },
  { label: 'Profile', route: '/agent/profile', icon: Settings },
  { label: 'Getting Started', route: '/agent/onboarding', icon: BookOpen },
])

function isActive(route: string) {
  const current = page.url
  if (route === '/agent') return current === '/agent' || current === '/agent/'
  return current.startsWith(route)
}

onMounted(async () => {
  try {
    const profile = await fetchProfile()
    agentName.value = profile.name
    agentTier.value = profile.tier_label
  } catch {}
})
</script>
