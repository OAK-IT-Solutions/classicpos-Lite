<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AgentLayout from '@/Layouts/AgentLayout.vue'
import { useAgent } from '@/composables/useAgent'
import { Save, Lock, User, Phone, Mail } from 'lucide-vue-next'

const { fetchProfile } = useAgent()
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')

const profile = ref({
  user: { id: '', name: '', email: '', created_at: '' },
  agent: { id: '', code: '', name: '', email: '', phone: '', tier: '', commission_rate: 0, is_active: false, total_referrals: 0, converted_referrals: 0, total_earnings: 0, pending_earnings: 0, paid_earnings: 0, created_at: '' },
})

const form = ref({ name: '', email: '', phone: '' })

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
    const data = await fetchProfile()
    profile.value = data
    form.value = { name: data.agent.name, email: data.agent.email, phone: data.agent.phone || '' }
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
    const { data } = await (await import('@/composables/axios')).default.put('/agent/auth/profile', form.value)
    profile.value.user.name = data.user.name
    profile.value.user.email = data.user.email
    profile.value.agent.name = data.agent.name
    profile.value.agent.email = data.agent.email
    profile.value.agent.phone = data.agent.phone
    saved.value = true
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
    const axios = (await import('@/composables/axios')).default
    await axios.put('/agent/auth/change-password', {
      current_password: passwordForm.value.current_password,
      password: passwordForm.value.password,
      password_confirmation: passwordForm.value.password_confirmation,
    })
    passwordSaved.value = true
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (err: any) {
    passwordError.value = err?.response?.data?.error || 'Failed to update password.'
  } finally {
    passwordSaving.value = false
  }
}

function tierBadge(tier: string) {
  return { platinum: 'bg-purple-100 text-purple-700', gold: 'bg-yellow-100 text-yellow-700', silver: 'bg-gray-100 text-gray-600', standard: 'bg-blue-100 text-blue-700' }[tier] || 'bg-gray-100'
}
</script>

<template>
  <AgentLayout>
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

        <!-- Agent Summary -->
        <div class="bg-white rounded-xl border border-gray-100 p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
              <User class="w-6 h-6 text-green-600" />
            </div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900">{{ profile.agent.name }}</h2>
              <div class="flex items-center gap-2 text-sm text-gray-500">
                <span :class="[tierBadge(profile.agent.tier), 'px-2 py-0.5 rounded-full text-xs font-medium']">{{ profile.agent.tier }}</span>
                <span class="font-mono text-gray-400">{{ profile.agent.code }}</span>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-4 text-center">
            <div class="bg-gray-50 rounded-lg p-3">
              <div class="text-lg font-bold text-gray-900">{{ profile.agent.total_referrals }}</div>
              <div class="text-xs text-gray-500">Total Referrals</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <div class="text-lg font-bold text-gray-900">{{ profile.agent.converted_referrals }}</div>
              <div class="text-xs text-gray-500">Converted</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <div class="text-lg font-bold text-green-600">{{ profile.agent.commission_rate }}%</div>
              <div class="text-xs text-gray-500">Commission Rate</div>
            </div>
          </div>
        </div>

        <!-- Contact Info -->
        <div class="bg-white rounded-xl border border-gray-100 p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
              <Mail class="w-5 h-5 text-blue-600" />
            </div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Contact Information</h2>
              <p class="text-xs text-gray-500">Update your name, email, and phone</p>
            </div>
          </div>

          <form @submit.prevent="saveProfile" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
              <input v-model="form.name" type="text" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
              <input v-model="form.email" type="email" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
              <input v-model="form.phone" type="tel" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
            </div>
            <div class="flex justify-end">
              <button type="submit" :disabled="saving" class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 transition-colors">
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
              <p class="text-xs text-gray-500">Keep your account secure</p>
            </div>
          </div>

          <div v-if="!showPassword">
            <button @click="showPassword = true" class="text-sm text-green-600 hover:text-green-700 font-medium">
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
                <input v-model="passwordForm.current_password" type="password" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input v-model="passwordForm.password" type="password" required minlength="8" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <input v-model="passwordForm.password_confirmation" type="password" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
              </div>
              <div class="flex justify-end gap-2">
                <button type="button" @click="showPassword = false" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                  Cancel
                </button>
                <button type="submit" :disabled="passwordSaving" class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 transition-colors">
                  <Lock class="w-4 h-4" />
                  {{ passwordSaving ? 'Updating...' : 'Update Password' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AgentLayout>
</template>
