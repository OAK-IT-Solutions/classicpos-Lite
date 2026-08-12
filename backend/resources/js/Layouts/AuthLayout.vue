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
    <div class="min-h-screen flex items-center justify-center relative overflow-hidden bg-gradient-to-br from-[#0f172a] via-[#1e293b] to-[#0f172a]">
        <div class="absolute inset-0 opacity-30">
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-primary/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-primary/10 rounded-full blur-3xl"></div>
        </div>
        <div class="w-full px-4 relative z-10" :class="wide ? 'max-w-2xl' : 'max-w-md'">
            <div class="text-center mb-10">
                <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="h-14 w-auto mx-auto mb-4" />
                <div v-else class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-br from-primary to-primary-hover rounded-2xl mb-4 shadow-lg shadow-primary/30">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">ClassicPOS</h1>
                <p class="text-slate-400 mt-1 text-sm">Offline Desktop Point of Sale</p>
            </div>
            <div class="bg-white/[0.03] backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl p-8">
                <slot />
            </div>
            <p class="text-center text-slate-500 text-xs mt-6">&copy; {{ new Date().getFullYear() }} ClassicPOS. All rights reserved.</p>
        </div>
    </div>
</template>
