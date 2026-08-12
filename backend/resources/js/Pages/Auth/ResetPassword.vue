<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import api from '@/composables/axios'

const page = usePage()
const token = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const error = ref('')
const loading = ref(false)
const success = ref(false)

onMounted(() => {
    const params = new URLSearchParams(window.location.search)
    token.value = params.get('token') || ''
    email.value = params.get('email') || ''
})

async function handleSubmit() {
    if (password.value.length < 8) { error.value = 'Password must be at least 8 characters.'; return }
    if (password.value !== passwordConfirm.value) { error.value = 'Passwords do not match.'; return }
    loading.value = true; error.value = ''
    try {
        const params = new URLSearchParams({
            token: token.value, email: email.value,
            password: password.value, password_confirmation: passwordConfirm.value,
        }).toString()
        await api.post(`/auth/reset-password?${params}`)
        success.value = true
        setTimeout(() => router.visit('/login'), 3000)
    } catch (err: any) {
        error.value = err?.response?.data?.error?.message || err?.response?.data?.message || 'Failed to reset password.'
    } finally { loading.value = false }
}
</script>

<template>
    <AuthLayout>
        <div class="max-w-md mx-auto mt-16 p-6 bg-surface-raised rounded-xl shadow-sm border border-border-theme">
            <div v-if="success" class="text-center space-y-4">
                <div class="text-4xl">✅</div>
                <h1 class="text-2xl font-bold text-text-theme">Password Reset!</h1>
                <p class="text-sm text-text-tertiary">Your password has been updated. Redirecting to login...</p>
                <a href="/login" class="inline-block px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover">Login Now</a>
            </div>

            <div v-else>
                <h1 class="text-2xl font-bold text-text-theme text-center mb-2">Reset Password</h1>
                <p class="text-sm text-text-tertiary text-center mb-6">Enter your new password below</p>

                <div class="mb-4 p-3 bg-surface-alt rounded-lg text-sm">
                    <p class="text-text-tertiary">Resetting for:</p>
                    <p class="font-medium text-text-theme">{{ email }}</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-text-secondary mb-1">New Password</label>
                    <input v-model="password" type="password" required minlength="8" placeholder="Min 8 characters"
                        class="w-full px-3 py-2.5 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring" />
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-text-secondary mb-1">Confirm Password</label>
                    <input v-model="passwordConfirm" type="password" required placeholder="Repeat your new password"
                        class="w-full px-3 py-2.5 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring" />
                </div>
                <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-xs text-danger-theme">{{ error }}</div>

                <button @click="handleSubmit" :disabled="loading || !token || !email"
                    class="w-full px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                    {{ loading ? 'Resetting...' : 'Reset Password' }}
                </button>
                <p class="text-center mt-4"><a href="/login" class="text-sm text-primary hover:underline">Back to Login</a></p>
            </div>
        </div>
    </AuthLayout>
</template>
