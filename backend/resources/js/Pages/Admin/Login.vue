<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { useAdminAuth } from '@/composables/useAdmin'
import { Shield } from 'lucide-vue-next'

const auth = useAdminAuth()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

onMounted(() => {
  const token = localStorage.getItem('admin_token')
  if (token) {
    auth.check().then(user => {
      if (user && ['super_admin', 'admin', 'support'].includes(user.role)) {
        window.location.href = '/admin'
      }
    })
  }
})

async function handleSubmit() {
  error.value = ''
  loading.value = true

  try {
    const data = await auth.login(email.value, password.value)
    if (!data || !['super_admin', 'admin', 'support'].includes(data.role)) {
      localStorage.removeItem('admin_token')
      error.value = 'Admin access required. Please use an admin account.'
      return
    }
    window.location.href = '/admin'
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Invalid email or password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <div class="text-center mb-6">
      <div class="inline-flex items-center justify-center w-12 h-12 bg-purple-100 rounded-xl mb-3">
        <Shield class="w-6 h-6 text-purple-600" />
      </div>
      <h2 class="text-xl font-semibold text-gray-900">Admin Login</h2>
      <p class="text-sm text-gray-500 mt-1">Sign in to the SaaS admin panel</p>
    </div>

    <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
      {{ error }}
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input
          v-model="email"
          type="email"
          required
          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none"
          placeholder="your@email.com"
        />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input
          v-model="password"
          type="password"
          required
          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none"
          placeholder="Enter your password"
        />
      </div>
      <button
        type="submit"
        :disabled="loading"
        class="w-full bg-purple-600 text-white rounded-lg py-2.5 font-medium hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {{ loading ? 'Signing in...' : 'Sign In' }}
      </button>
    </form>

    <p class="mt-6 text-center text-xs text-gray-400">
      This area is for platform administrators only.
    </p>
  </AuthLayout>
</template>
