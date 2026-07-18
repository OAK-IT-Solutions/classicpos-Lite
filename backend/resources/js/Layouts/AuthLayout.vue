<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '@/composables/axios';

withDefaults(defineProps<{
    wide?: boolean;
}>(), {
    wide: false,
});

const logoUrl = ref<string | null>(null);

onMounted(async () => {
    const token = localStorage.getItem('admin_token') || localStorage.getItem('auth_token');
    if (!token) return;
    try {
        const r = await api.get('/onboarding/status');
        logoUrl.value = r.data?.profile?.logo_url ?? null;
    } catch { /* ignore */ }
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-login-from via-login-via to-login-to">
        <div class="w-full px-4" :class="wide ? 'max-w-2xl' : 'max-w-md'">
            <div class="text-center mb-8">
                <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="h-16 w-auto mx-auto mb-4" />
                <div v-else class="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white">ClassicPOS</h1>
                <p class="text-white/60 mt-2">Point of Sale System</p>
            </div>
            <div class="bg-surface-raised rounded-xl shadow-2xl p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
