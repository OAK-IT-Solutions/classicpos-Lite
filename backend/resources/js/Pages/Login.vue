<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useAuth } from '@/composables/useAuth';

const auth = useAuth();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function handleSubmit() {
    error.value = '';
    loading.value = true;

    try {
        await auth.login(email.value, password.value);

        if (auth.needsBranchSelection()) {
            router.visit('/branch-select');
        } else {
            router.visit('/');
        }
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Invalid email or password.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AuthLayout>
        <h2 class="text-2xl font-semibold mb-6 text-text-theme">Welcome Back</h2>

        <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">
            {{ error }}
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Email</label>
                <input
                    v-model="email"
                    type="email"
                    required
                    class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-input-bg text-text-theme transition-shadow"
                    placeholder="your@email.com"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Password</label>
                <input
                    v-model="password"
                    type="password"
                    required
                    class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-input-bg text-text-theme transition-shadow"
                    placeholder="Enter your password"
                />
            </div>
            <button
                type="submit"
                :disabled="loading"
                class="w-full bg-btn-primary text-btn-primary-text rounded-lg py-2.5 font-medium hover:bg-btn-primary-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                {{ loading ? 'Signing in...' : 'Sign In' }}
            </button>
        </form>

        <p class="mt-4 text-center text-sm">
            <a href="/forgot-password" class="text-text-tertiary hover:text-primary transition-colors">Forgot Password?</a>
        </p>

        <p class="mt-6 text-center text-sm text-text-tertiary">
            Don't have an account?
            <a href="/register" class="text-primary hover:opacity-80 font-medium">Get Started</a>
        </p>
    </AuthLayout>
</template>
