<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';

const auth = useAuth();
const selectedBranch = ref<string | null>(null);
const error = ref('');

const branches = auth.getAssignedBranches();

onMounted(() => {
    if (!auth.isAuthenticated.value) {
        router.visit('/login');
        return;
    }

    if (branches.length <= 1) {
        if (branches.length === 1) {
            auth.setActiveBranch(branches[0]);
        }
        router.visit('/');
    }
});

function selectBranch(branchId: string) {
    const branch = branches.find(b => b.id === branchId);
    if (branch) {
        auth.setActiveBranch(branch);
        router.visit('/');
    }
}
</script>

<template>
    <div class="min-h-screen bg-surface-alt flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-text-theme">Welcome to ClassicPOS</h1>
                <p class="text-text-tertiary mt-2">Select a branch to continue</p>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>

            <div class="space-y-3">
                <button
                    v-for="branch in branches"
                    :key="branch.id"
                    @click="selectBranch(branch.id)"
                    class="w-full p-4 bg-surface-raised rounded-xl border border-border-theme shadow-sm hover:border-primary hover:shadow-md transition-all text-left flex items-center gap-4 group"
                >
                    <div class="w-10 h-10 rounded-full bg-primary-light flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-base font-semibold text-text-theme group-hover:text-primary transition-colors">{{ branch.name }}</p>
                        <p v-if="branch.location" class="text-sm text-text-tertiary">{{ branch.location }}</p>
                    </div>
                    <svg class="w-5 h-5 text-text-tertiary group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
