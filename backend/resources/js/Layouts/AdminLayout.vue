<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useAdminAuth } from '@/composables/useAdmin'
import NotificationBell from '@/Components/NotificationBell.vue'
import {
  LayoutDashboard, Building2, CreditCard, DollarSign, Users,
  HeadphonesIcon, Settings, Shield, Activity, ChevronLeft,
  ChevronRight, LogOut, Menu, X, BarChart3, UserCog, Layers, Tag,
} from 'lucide-vue-next'

const auth = useAdminAuth()
const page = usePage()
const sidebarOpen = ref(true)
const mobileOpen = ref(false)
const checking = ref(true)

const adminRole = computed(() => (auth.user.value as any)?.role || '')

const isAdmin = computed(() => {
  return ['super_admin', 'admin', 'support'].includes(adminRole.value)
})

onMounted(async () => {
  const token = localStorage.getItem('admin_token')
  if (!token) {
    window.location.href = '/admin/login'
    return
  }
  const user = await auth.check()
  checking.value = false
  if (!user || !isAdmin.value) {
    window.location.href = '/admin/login'
  }
})

const navItems = computed(() => {
  const role = adminRole.value
  const items = [
    { label: 'Dashboard', href: '/admin', icon: LayoutDashboard },
    { label: 'Tenants', href: '/admin/tenants', icon: Building2, section: 'tenants' },
    { label: 'Plans', href: '/admin/plans', icon: CreditCard, section: 'plans' },
    { label: 'Features', href: '/admin/features', icon: Layers, section: 'features' },
    { label: 'Discounts', href: '/admin/discounts', icon: Tag, section: 'discounts' },
    { label: 'Subscriptions', href: '/admin/subscriptions', icon: BarChart3, section: 'subscriptions' },
    { label: 'Revenue', href: '/admin/revenue', icon: DollarSign, section: 'revenue' },
    { label: 'Agents', href: '/admin/agents', icon: Users, section: 'agents' },
    { label: 'Commissions', href: '/admin/commissions', icon: DollarSign, section: 'commissions' },
    { label: 'Tickets', href: '/admin/tickets', icon: HeadphonesIcon, section: 'tickets' },
    { divider: true },
    { label: 'Admin Users', href: '/admin/admin-users', icon: UserCog, section: 'admin_users' },
    { label: 'Audit Log', href: '/admin/audit-log', icon: Shield, section: 'audit_log' },
    { label: 'Health', href: '/admin/health', icon: Activity, section: 'health' },
    { label: 'Settings', href: '/admin/settings', icon: Settings, section: 'settings' },
  ]

  if (role === 'support') {
    return items.filter(i => i.divider || ['Dashboard', 'Tickets'].includes(i.label))
  }
  if (role === 'admin') {
    return items.filter(i => !i.divider || false) // show all except divider
  }
  return items // super_admin sees all
})

function isActive(href: string): boolean {
  if (href === '/admin') return page.url === '/admin' || page.url === '/admin/'
  return page.url.startsWith(href)
}

async function handleLogout() {
  await auth.logout()
  router.visit('/admin/login', { replace: true })
}
</script>

<template>
  <div v-if="checking" class="min-h-screen bg-gray-50 flex items-center justify-center">
    <p class="text-gray-400 text-sm">Checking authentication...</p>
  </div>

  <div v-else class="min-h-screen bg-gray-50">
    <!-- Mobile overlay -->
    <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="mobileOpen = false" />

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed top-0 left-0 z-50 h-full bg-white border-r border-gray-200 transition-all duration-200',
        sidebarOpen ? 'w-64' : 'w-16',
        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      ]"
    >
      <div class="flex items-center h-16 px-4 border-b border-gray-100">
        <Link href="/admin" class="flex items-center gap-2 overflow-hidden">
          <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center shrink-0">
            <span class="text-white font-bold text-sm">C</span>
          </div>
          <span v-if="sidebarOpen" class="font-bold text-lg whitespace-nowrap">ClassicPOS</span>
        </Link>
      </div>

      <nav class="p-3 space-y-1">
        <template v-for="item in navItems" :key="item.label">
          <div v-if="item.divider" class="my-3 border-t border-gray-100" />
          <Link
            v-else
            :href="item.href"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
              isActive(item.href)
                ? 'bg-blue-50 text-blue-700'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
            ]"
            @click="mobileOpen = false"
          >
            <component :is="item.icon" class="w-5 h-5 shrink-0" />
            <span v-if="sidebarOpen" class="whitespace-nowrap">{{ item.label }}</span>
          </Link>
        </template>
      </nav>

      <!-- Collapse toggle (desktop only) -->
      <button
        @click="sidebarOpen = !sidebarOpen"
        class="hidden lg:flex absolute -right-3 top-20 w-6 h-6 bg-white border border-gray-200 rounded-full items-center justify-center hover:bg-gray-50"
      >
        <ChevronLeft v-if="sidebarOpen" class="w-3 h-3" />
        <ChevronRight v-else class="w-3 h-3" />
      </button>
    </aside>

    <!-- Main content -->
    <div :class="['transition-all duration-200', sidebarOpen ? 'lg:ml-64' : 'lg:ml-16']">
      <!-- Top bar -->
      <header class="sticky top-0 z-30 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
        <button @click="mobileOpen = true" class="lg:hidden p-2 rounded-lg hover:bg-gray-100">
          <Menu class="w-5 h-5" />
        </button>

        <div class="flex items-center gap-4 ml-auto">
          <span :class="['text-xs font-medium px-2 py-1 rounded-full', adminRole === 'super_admin' ? 'text-purple-700 bg-purple-50' : adminRole === 'admin' ? 'text-blue-700 bg-blue-50' : 'text-gray-700 bg-gray-100']">
            {{ adminRole === 'super_admin' ? 'Super Admin' : adminRole === 'admin' ? 'Admin' : 'Support' }}
          </span>
          <NotificationBell />
          <Link href="/admin/profile" class="flex items-center gap-2 hover:bg-gray-50 rounded-lg px-2 py-1 transition-colors">
            <span class="text-sm font-medium text-gray-700">{{ auth.user.value?.name }}</span>
          </Link>
          <button @click="handleLogout" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </header>

      <!-- Page content -->
      <main class="p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
