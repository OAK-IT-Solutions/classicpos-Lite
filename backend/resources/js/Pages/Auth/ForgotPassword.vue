<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import api from '@/composables/axios'

const step = ref<'email' | 'choose' | 'email-sent' | 'secret'>('email')
const error = ref('')
const loading = ref(false)

const email = ref('')
const secretQuestion = ref('')
const secretAnswer = ref('')
const resetToken = ref('')

const questions = [
    'What city were you born in?',
    'What is your mother\'s maiden name?',
    'What was your first pet\'s name?',
    'What was the name of your first school?',
    'What is your favorite book?',
    'What is the name of your childhood best friend?',
]

async function postForm(url: string, data: Record<string, string>) {
    const params = new URLSearchParams(data).toString()
    return api.post(`${url}?${params}`)
}

async function lookupEmail() {
    loading.value = true; error.value = ''
    try {
        const r = await postForm('/auth/forgot-password/secret-question', { email: email.value })
        secretQuestion.value = r.data.data.question
        step.value = 'choose'
    } catch {
        step.value = 'choose'
        secretQuestion.value = ''
    } finally { loading.value = false }
}

async function sendEmailReset() {
    loading.value = true; error.value = ''
    try {
        await postForm('/auth/forgot-password', { email: email.value })
        step.value = 'email-sent'
    } catch (err: any) {
        error.value = err?.response?.data?.error?.message || 'Failed to send reset email.'
    } finally { loading.value = false }
}

async function verifySecret() {
    if (!secretAnswer.value) { error.value = 'Please enter your answer.'; return }
    loading.value = true; error.value = ''
    try {
        const r = await postForm('/auth/forgot-password/verify-secret', { email: email.value, answer: secretAnswer.value })
        resetToken.value = r.data.data.token
        router.visit(`/reset-password?token=${resetToken.value}&email=${encodeURIComponent(email.value)}`)
    } catch (err: any) {
        error.value = err?.response?.data?.error?.message || 'Incorrect answer. Try again.'
    } finally { loading.value = false }
}
</script>

<template>
    <AuthLayout>
        <div class="max-w-md mx-auto mt-16 p-6 bg-surface-raised rounded-xl shadow-sm border border-border-theme">
            <h1 class="text-2xl font-bold text-text-theme text-center mb-2">Forgot Password</h1>
            <p class="text-sm text-text-tertiary text-center mb-6">Reset your password via email or security question</p>

            <!-- Step 1: Enter Email -->
            <div v-if="step === 'email'">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-text-secondary mb-1">Email Address</label>
                    <input v-model="email" type="email" required placeholder="your@email.com"
                        class="w-full px-3 py-2.5 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring"
                        @keydown.enter="lookupEmail" />
                </div>
                <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-xs text-danger-theme">{{ error }}</div>
                <button @click="lookupEmail" :disabled="loading || !email"
                    class="w-full px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                    {{ loading ? 'Checking...' : 'Continue' }}
                </button>
                <p class="text-center mt-4"><a href="/login" class="text-sm text-primary hover:underline">Back to Login</a></p>
            </div>

            <!-- Step 2: Choose Method -->
            <div v-if="step === 'choose'">
                <div class="mb-4 p-4 bg-surface-alt rounded-lg text-sm">
                    <p class="text-text-tertiary">Email:</p>
                    <p class="font-medium text-text-theme">{{ email }}</p>
                </div>

                <button @click="sendEmailReset" :disabled="loading"
                    class="w-full mb-3 px-4 py-3 bg-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors text-left flex items-center gap-3">
                    <span class="text-lg">📧</span>
                    <div><p class="font-medium">Email me a reset link</p><p class="text-xs opacity-80">A link will be sent to your email</p></div>
                </button>

                <div v-if="secretQuestion">
                    <div class="border-t border-border-theme pt-3 mb-3">
                        <p class="text-xs text-text-tertiary mb-2">Or answer your security question:</p>
                        <p class="text-sm font-semibold text-text-theme mb-2">{{ secretQuestion }}</p>
                        <input v-model="secretAnswer" type="text" placeholder="Your answer"
                            class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring"
                            @keydown.enter="verifySecret" />
                    </div>
                    <div v-if="error" class="mb-3 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-xs text-danger-theme">{{ error }}</div>
                    <button @click="verifySecret" :disabled="loading"
                        class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 transition-colors">
                        {{ loading ? 'Verifying...' : 'Verify & Reset' }}
                    </button>
                </div>

                <p class="text-center mt-4"><a href="/login" class="text-sm text-primary hover:underline">Back to Login</a></p>
            </div>

            <!-- Step 3: Email Sent -->
            <div v-if="step === 'email-sent'" class="text-center space-y-4">
                <div class="text-4xl">📨</div>
                <p class="text-text-theme font-medium">Check your email</p>
                <p class="text-sm text-text-tertiary">We've sent a password reset link to <strong>{{ email }}</strong>. It expires in 60 minutes.</p>
                <button @click="sendEmailReset" :disabled="loading"
                    class="px-4 py-2 text-sm text-primary hover:underline">
                    Resend email
                </button>
                <div><a href="/login" class="text-sm text-primary hover:underline">Back to Login</a></div>
            </div>
        </div>
    </AuthLayout>
</template>
