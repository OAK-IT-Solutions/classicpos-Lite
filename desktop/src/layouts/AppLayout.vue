<template>
  <div class="app-layout">
    <!-- Mobile overlay -->
    <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
      <!-- Brand -->
      <div class="sidebar-brand">
        <div class="brand-icon">
          <svg viewBox="0 0 100 100" width="28" height="28">
            <rect x="10" y="10" width="80" height="80" rx="16" fill="#3b82f6"/>
            <text x="50" y="62" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="system-ui">P</text>
          </svg>
        </div>
        <div class="brand-text">
          <span class="brand-name">ClassicPOS</span>
          <span class="brand-business">{{ businessName }}</span>
        </div>
        <button class="sidebar-close" @click="sidebarOpen = false">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Nav -->
      <nav class="sidebar-nav">
        <!-- Top-level items -->
        <a v-for="item in topNavItems" :key="item.href" :href="item.href"
           class="nav-item" :class="{ active: isActive(item.href) }">
          <component :is="item.icon" :size="20" :stroke-width="1.8" />
          <span>{{ item.label }}</span>
        </a>

        <!-- Collapsible sections -->
        <div v-for="section in sections" :key="section.id" class="nav-section">
          <button class="nav-section-header" @click="toggleSection(section.id)">
            <component :is="section.icon" :size="20" :stroke-width="1.8" />
            <span>{{ section.label }}</span>
            <svg class="section-chevron" :class="{ expanded: isExpanded(section.id) }" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div class="nav-section-links" :class="{ expanded: isExpanded(section.id) }">
            <a v-for="link in section.links" :key="link.href" :href="link.href"
               class="nav-link" :class="{ active: isActive(link.href) }">
              {{ link.label }}
            </a>
          </div>
        </div>
      </nav>

      <!-- User profile -->
      <div class="sidebar-footer">
        <div class="user-profile">
          <div class="user-avatar">{{ userInitial }}</div>
          <div class="user-info">
            <span class="user-name">{{ userName }}</span>
            <span class="user-email">{{ userEmail }}</span>
          </div>
          <button class="logout-btn" @click="handleLogout" title="Sign out">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <div class="main-area">
      <!-- Top bar -->
      <header class="top-bar">
        <div class="top-bar-left">
          <button class="hamburger" @click="sidebarOpen = true">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>
          <h1 class="page-title">{{ pageTitle }}</h1>
        </div>
        <div class="top-bar-right">
          <div class="sync-badge" :class="{ online: isOnline, offline: !isOnline }">
            <span class="sync-dot"></span>
            {{ isOnline ? 'Online' : 'Offline' }}
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="page-content">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, markRaw } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import {
  LayoutDashboard, ShoppingCart, Package, Users, Receipt,
  Wallet, BarChart3, Settings, Plug, Headphones, ChevronDown,
} from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();

const sidebarOpen = ref(false);
const isOnline = ref(navigator.onLine);
const userName = ref('');
const userEmail = ref('');
const businessName = ref('ClassicPOS');
const expandedSections = ref<Record<string, boolean>>({});

const userInitial = computed(() => {
  return userName.value ? userName.value.charAt(0).toUpperCase() : 'U';
});

const pageTitle = computed(() => {
  const titles: Record<string, string> = {
    'dashboard': 'Dashboard',
    'pos': 'POS Register',
    'products': 'Products',
    'products.show': 'Product Details',
    'customers': 'Customers',
    'customers.show': 'Customer Details',
    'sales': 'Sales',
    'sales.show': 'Sale Details',
    'cash-register': 'Cash Register',
    'sync-status': 'Sync Status',
    'reports': 'Reports',
    'settings': 'Settings',
  };
  return titles[route.name as string] || 'ClassicPOS';
});

const topNavItems = [
  { href: '/dashboard', label: 'Dashboard', icon: markRaw(LayoutDashboard) },
  { href: '/pos', label: 'POS Register', icon: markRaw(ShoppingCart) },
];

const sections = [
  {
    id: 'sales',
    label: 'Sales & Customers',
    icon: markRaw(Receipt),
    links: [
      { href: '/sales', label: 'Sales' },
      { href: '/customers', label: 'Customers' },
    ],
  },
  {
    id: 'products',
    label: 'Products & Inventory',
    icon: markRaw(Package),
    links: [
      { href: '/products', label: 'Products' },
    ],
  },
  {
    id: 'operations',
    label: 'Operations',
    icon: markRaw(BarChart3),
    links: [
      { href: '/cash-register', label: 'Cash Register' },
      { href: '/reports', label: 'Reports' },
      { href: '/sync-status', label: 'Sync Status' },
    ],
  },
  {
    id: 'settings',
    label: 'Settings',
    icon: markRaw(Settings),
    links: [
      { href: '/settings', label: 'Business Settings' },
    ],
  },
];

function isActive(href: string): boolean {
  if (href === '/dashboard') return route.path === '/dashboard' || route.path === '/';
  if (href === '/pos') return route.path === '/pos';
  return route.path.startsWith(href);
}

function toggleSection(id: string) {
  expandedSections.value[id] = !expandedSections.value[id];
}

function isExpanded(id: string): boolean {
  return expandedSections.value[id] ?? false;
}

onMounted(() => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    userName.value = user.name || 'User';
    userEmail.value = user.email || '';
  } catch {
    userName.value = 'User';
  }

  try {
    const business = JSON.parse(localStorage.getItem('classicpos_business') || '{}');
    businessName.value = business.name || 'ClassicPOS';
  } catch {}

  // Auto-expand active section
  for (const section of sections) {
    if (section.links.some(l => isActive(l.href))) {
      expandedSections.value[section.id] = true;
    }
  }

  window.addEventListener('online', () => { isOnline.value = true; });
  window.addEventListener('offline', () => { isOnline.value = false; });

  const token = localStorage.getItem('auth_token');
  if (!token) {
    router.replace('/login');
  }
});

function handleLogout() {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  router.push('/login');
}
</script>

<style scoped>
.app-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: #f8fafc;
}

/* ─── Sidebar Overlay ─── */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 40;
  display: none;
}
@media (max-width: 1023px) {
  .sidebar-overlay { display: block; }
}

/* ─── Sidebar ─── */
.sidebar {
  width: 260px;
  background: #0f172a;
  color: #e2e8f0;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  z-index: 50;
  transition: transform 0.2s ease;
}
@media (max-width: 1023px) {
  .sidebar {
    position: fixed;
    inset-y: 0;
    left: 0;
    transform: translateX(-100%);
  }
  .sidebar.sidebar-open {
    transform: translateX(0);
  }
}

/* ─── Brand ─── */
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #1e293b;
  min-height: 64px;
}
.brand-icon {
  width: 36px;
  height: 36px;
  background: #1e293b;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.brand-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}
.brand-name {
  font-weight: 700;
  font-size: 0.95rem;
  color: #f1f5f9;
  line-height: 1.2;
}
.brand-business {
  font-size: 0.75rem;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sidebar-close {
  display: none;
  padding: 4px;
  color: #64748b;
  background: none;
  border: none;
  cursor: pointer;
  border-radius: 6px;
}
.sidebar-close:hover { color: #e2e8f0; background: #1e293b; }
@media (max-width: 1023px) {
  .sidebar-close { display: flex; }
}

/* ─── Nav ─── */
.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 0.75rem 0;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.25rem;
  margin: 0.125rem 0.5rem;
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  border-radius: 8px;
  transition: all 0.15s;
  min-height: 44px;
}
.nav-item:hover {
  background: #1e293b;
  color: #e2e8f0;
}
.nav-item.active {
  background: #3b82f6;
  color: #ffffff;
}

/* ─── Sections ─── */
.nav-section { margin: 0.25rem 0; }
.nav-section-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.25rem;
  margin: 0.125rem 0.5rem;
  color: #94a3b8;
  font-size: 0.9rem;
  font-weight: 500;
  border-radius: 8px;
  transition: all 0.15s;
  width: 100%;
  text-align: left;
  background: none;
  border: none;
  cursor: pointer;
  min-height: 44px;
}
.nav-section-header:hover {
  background: #1e293b;
  color: #e2e8f0;
}
.section-chevron {
  margin-left: auto;
  transition: transform 0.2s;
  flex-shrink: 0;
}
.section-chevron.expanded {
  transform: rotate(180deg);
}

.nav-section-links {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.25s ease;
}
.nav-section-links.expanded {
  max-height: 500px;
}
.nav-link {
  display: block;
  padding: 0.625rem 1.25rem 0.625rem 3.5rem;
  color: #64748b;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  border-radius: 6px;
  transition: all 0.15s;
  min-height: 40px;
  display: flex;
  align-items: center;
}
.nav-link:hover {
  background: #1e293b;
  color: #e2e8f0;
}
.nav-link.active {
  background: rgba(59, 130, 246, 0.15);
  color: #3b82f6;
}

/* ─── Footer / User Profile ─── */
.sidebar-footer {
  border-top: 1px solid #1e293b;
  padding: 0.75rem;
}
.user-profile {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  border-radius: 10px;
  background: #1e293b;
}
.user-avatar {
  width: 36px;
  height: 36px;
  background: #3b82f6;
  color: white;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.875rem;
  flex-shrink: 0;
}
.user-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.user-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: #e2e8f0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.user-email {
  font-size: 0.7rem;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.logout-btn {
  padding: 8px;
  color: #64748b;
  background: none;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
  flex-shrink: 0;
}
.logout-btn:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
}

/* ─── Main Area ─── */
.main-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-width: 0;
}

/* ─── Top Bar ─── */
.top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.5rem;
  height: 60px;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.top-bar-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.hamburger {
  display: none;
  padding: 8px;
  color: #475569;
  background: none;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}
.hamburger:hover { background: #f1f5f9; }
@media (max-width: 1023px) {
  .hamburger { display: flex; }
}
.page-title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.01em;
}
.top-bar-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

/* ─── Sync Badge ─── */
.sync-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
}
.sync-badge.online {
  background: #dcfce7;
  color: #166534;
}
.sync-badge.offline {
  background: #fef2f2;
  color: #991b1b;
}
.sync-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
}

/* ─── Page Content ─── */
.page-content {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}
</style>
