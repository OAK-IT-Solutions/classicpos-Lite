import { createRouter, createWebHistory } from 'vue-router';

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../wrappers/Auth/Login.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/offline',
    name: 'offline',
    component: () => import('../wrappers/Offline.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/',
    component: () => import('../layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('../wrappers/Dashboard.vue'),
      },
      {
        path: 'pos',
        name: 'pos',
        component: () => import('../wrappers/POS/Register.vue'),
      },
      {
        path: 'products',
        name: 'products',
        component: () => import('../wrappers/Products/Index.vue'),
      },
      {
        path: 'products/:id',
        name: 'products.show',
        component: () => import('../wrappers/Products/Show.vue'),
        props: true,
      },
      {
        path: 'customers',
        name: 'customers',
        component: () => import('../wrappers/Customers/Index.vue'),
      },
      {
        path: 'customers/:id',
        name: 'customers.show',
        component: () => import('../wrappers/Customers/Show.vue'),
        props: true,
      },
      {
        path: 'sales',
        name: 'sales',
        component: () => import('../wrappers/Sales/Index.vue'),
      },
      {
        path: 'sales/:id',
        name: 'sales.show',
        component: () => import('../wrappers/Sales/Show.vue'),
        props: true,
      },
      {
        path: 'cash-register',
        name: 'cash-register',
        component: () => import('../wrappers/CashRegister/Index.vue'),
      },
      {
        path: 'sync-status',
        name: 'sync-status',
        component: () => import('../wrappers/SyncStatus.vue'),
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('../pages/Settings.vue'),
      },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Auth guard
router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('auth_token');
  if (to.meta.requiresAuth !== false && !token) {
    next('/login');
  } else {
    next();
  }
});

export default router;
