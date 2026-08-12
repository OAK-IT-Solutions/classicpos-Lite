<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useAdminAuth } from '@/composables/useAdmin'
import api from '@/composables/axios'
import { Save, Lock, User } from 'lucide-vue-next'

const auth = useAdminAuth()
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')

const profile = ref({
  name: '',
  email: '',
  role: '',
  is_active: false,
  last_login_at: '',
  created_at: '',
})

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const showPassword = ref(false)
const passwordSaving = ref(false)
const passwordSaved = ref(false)
const passwordError = ref('')

onMounted(async () => {
  try {
    const token = localStorage.getItem('admin_token')
    const res = await api.get('/admin/auth/profile', { headers: { Authorization: `Bearer ${token}` } })
    Object.assign(profile.value, res.data)
  } catch {
    error.value = 'Failed to load profile.'
  } finally {
    loading.value = false
  }
})

async function saveProfile() {
  saving.value = true
  saved.value = false
  error.value = ''
  try {
    const token = localStorage.getItem('admin_token')
    await api.put('/admin/auth/profile', {
      name: profile.value.name,
      email: profile.value.email,
    }, { headers: { Authorization: `Bearer ${token}` } })
    saved.value = true
    auth.check()
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Failed to save profile.'
  } finally {
    saving.value = false
  }
}

async function savePassword() {
  if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
    passwordError.value = 'Passwords do not match.'
    return
  }
  passwordSaving.value = true
  passwordSaved.value = false
  passwordError.value = ''
  try {
    const token = localStorage.getItem('admin_token')
    await api.put('/admin/auth/change-password', {
      current_password: passwordForm.value.current_password,
      password: passwordForm.value.password,
      password_confirmation: passwordForm.value.password_confirmation,
    }, { headers: { Authorization: `Bearer ${token}` } })
    passwordSaved.value = true
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (err: any) {
    passwordError.value = err?.response?.data?.error || 'Failed to update password.'
  } finally {
    passwordSaving.value = false
  }
}

function roleBadge(role: string) {
  return { super_admin: 'bg-purple-100 text-purple-700', admin: 'bg-blue-100 text-blue-700', support: 'bg-gray-100 text-gray-600' }[role] || 'bg-gray-100'
}
</script>

<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
        <p class="text-sm text-gray-500 mt-1">View and update your account settings</p>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-400">Loading...</div>

      <div v-else class="space-y-6">
        <div v-if="saved" class="p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
          Profile updated successfully.
        </div>
        <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">{{ error }}</div>

        <!-- Account Info -->
        <div class="bg-white rounded-xl border border-gray-100 p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
              <User class="w-5 h-5 text-blue-600" />
            </div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Account Information</h2>
              <p class="text-xs text-gray-500">Update your name and email address</p>
            </div>
          </div>

          <form @submit.prevent="saveProfile" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
              <input v-model="profile.name" type="text" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
              <input v-model="profile.email" type="email" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-500">
              <div>
                <span class="font-medium text-gray-700">Role:</span>
                <span :class="[roleBadge(profile.role), 'ml-2 px-2 py-0.5 rounded-full text-xs font-medium']">
                  {{ profile.role === 'super_admin' ? 'Super Admin' : profile.role === 'admin' ? 'Admin' : 'Support' }}
                </span>
              </div>
              <div v-if="profile.last_login_at">
                <span class="font-medium text-gray-700">Last login:</span>
                <span class="ml-1">{{ new Date(profile.last_login_at).toLocaleDateString() }}</span>
              </div>
            </div>
            <div class="flex justify-end">
              <button type="submit" :disabled="saving" class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors">
                <Save class="w-4 h-4" />
                {{ saving ? 'Saving...' : 'Save Profile' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Password Change -->
        <div class="bg-white rounded-xl border border-gray-100 p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
              <Lock class="w-5 h-5 text-orange-600" />
            </div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Change Password</h2>
              <p class="text-xs text-gray-500">Keep your account secure with a strong password</p>
            </div>
          </div>

          <div v-if="!showPassword">
            <button @click="showPassword = true" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
              Change Password
            </button>
          </div>

          <div v-else>
            <div v-if="passwordSaved" class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
              Password updated successfully.
            </div>
            <div v-if="passwordError" class="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
              {{ passwordError }}
            </div>

            <form @submit.prevent="savePassword" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <input v-model="passwordForm.current_password" type="password" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input v-model="passwordForm.password" type="password" required minlength="6" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <input v-model="passwordForm.password_confirmation" type="password" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div class="flex justify-end gap-2">
                <button type="button" @click="showPassword = false" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                  Cancel
                </button>
                <button type="submit" :disabled="passwordSaving" class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors">
                  <Lock class="w-4 h-4" />
                  {{ passwordSaving ? 'Updating...' : 'Update Password' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
