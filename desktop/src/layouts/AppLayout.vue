<template>
  <div class="app-layout">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <svg viewBox="0 0 100 100" width="32" height="32">
          <rect x="10" y="10" width="80" height="80" rx="16" fill="#3b82f6"/>
          <text x="50" y="62" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="system-ui">P</text>
        </svg>
        <span>ClassicPOS</span>
      </div>

      <nav class="sidebar-nav">
        <router-link to="/dashboard" class="nav-item" active-class="active">
          <i class="pi pi-home"></i>
          <span>Dashboard</span>
        </router-link>
        <router-link to="/pos" class="nav-item" active-class="active">
          <i class="pi pi-shopping-cart"></i>
          <span>POS Register</span>
        </router-link>
        <router-link to="/products" class="nav-item" active-class="active">
          <i class="pi pi-box"></i>
          <span>Products</span>
        </router-link>
        <router-link to="/customers" class="nav-item" active-class="active">
          <i class="pi pi-users"></i>
          <span>Customers</span>
        </router-link>
        <router-link to="/sales" class="nav-item" active-class="active">
          <i class="pi pi-dollar"></i>
          <span>Sales</span>
        </router-link>
        <router-link to="/cash-register" class="nav-item" active-class="active">
          <i class="pi pi-wallet"></i>
          <span>Cash Register</span>
        </router-link>
        <router-link to="/settings" class="nav-item" active-class="active">
          <i class="pi pi-cog"></i>
          <span>Settings</span>
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <router-link to="/sync-status" class="nav-item footer-item" active-class="active">
          <i class="pi pi-sync"></i>
          <span>Sync Status</span>
        </router-link>
        <button @click="handleLogout" class="nav-item footer-item logout-btn">
          <i class="pi pi-sign-out"></i>
          <span>Logout</span>
        </button>
      </div>
    </aside>

    <main class="main-content">
      <header class="top-bar">
        <div class="top-bar-left">
          <h2 class="page-title">{{ pageTitle }}</h2>
        </div>
        <div class="top-bar-right">
          <span class="sync-indicator" :class="{ online: isOnline, offline: !isOnline }">
            {{ isOnline ? 'Online' : 'Offline' }}
          </span>
          <span class="user-name">{{ userName }}</span>
        </div>
      </header>

      <div class="page-content">
        <router-view />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();

const isOnline = ref(navigator.onLine);
const userName = ref('');

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
  };
  return titles[route.name as string] || 'ClassicPOS';
});

onMounted(() => {
  // Load user from localStorage
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    userName.value = user.name || 'User';
  } catch {
    userName.value = 'User';
  }

  // Online/offline detection
  window.addEventListener('online', () => { isOnline.value = true; });
  window.addEventListener('offline', () => { isOnline.value = false; });

  // Check auth
  const token = localStorage.getItem('auth_token');
  if (!token) {
    router.replace('/login');
  }
});

const handleLogout = () => {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  router.push('/login');
};
</script>

<style scoped>
.app-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.sidebar {
  width: 240px;
  background: #1e293b;
  color: #e2e8f0;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  user-select: none;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #334155;
  font-weight: 700;
  font-size: 1.1rem;
}

.sidebar-nav {
  flex: 1;
  padding: 0.5rem 0;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 1.25rem;
  color: #94a3b8;
  text-decoration: none;
  transition: all 0.15s;
  font-size: 0.9rem;
  border: none;
  background: none;
  width: 100%;
  cursor: pointer;
  text-align: left;
}

.nav-item:hover {
  background: #334155;
  color: #e2e8f0;
}

.nav-item.active {
  background: #3b82f6;
  color: #ffffff;
}

.sidebar-footer {
  padding: 0.5rem 0;
  border-top: 1px solid #334155;
}

.footer-item {
  border-radius: 0;
}

.logout-btn:hover {
  background: #dc2626;
  color: #ffffff;
}

.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #f8fafc;
}

.top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.5rem;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.top-bar-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 600;
  color: #1e293b;
}

.top-bar-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.sync-indicator {
  font-size: 0.8rem;
  font-weight: 500;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
}

.sync-indicator.online {
  background: #dcfce7;
  color: #166534;
}

.sync-indicator.offline {
  background: #fef2f2;
  color: #991b1b;
}

.user-name {
  font-size: 0.9rem;
  color: #475569;
  font-weight: 500;
}

.page-content {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}
</style>
