<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import api from '@/composables/axios';
import { Plug, Receipt, CheckCircle, XCircle, ExternalLink, Loader2 } from 'lucide-vue-next';

defineProps<{ embedded?: boolean }>();

const integrations = ref<any[]>([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const res = await api.get('/integrations');
        integrations.value = res.data.data;
    } catch { /* ignore */ } finally {
        loading.value = false;
    }
});

function goToIntegrations() {
    router.visit('/integrations');
}

const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-600',
    error: 'bg-red-100 text-red-700',
    pending: 'bg-yellow-100 text-yellow-700',
};
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-4xl mx-auto'">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-text-theme">Integrations</h2>
                <button @click="goToIntegrations"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-primary hover:bg-primary/10 rounded-lg transition-colors">
                    Manage <ExternalLink class="w-3.5 h-3.5" />
                </button>
            </div>

            <div v-if="loading" class="text-center py-8">
                <Loader2 class="w-6 h-6 animate-spin mx-auto text-primary" />
            </div>

            <div v-else-if="integrations.length === 0" class="text-center py-8 bg-surface-raised rounded-xl border border-border-theme">
                <Plug class="w-10 h-10 mx-auto text-text-tertiary mb-2" />
                <p class="text-sm text-text-tertiary">No integrations connected</p>
                <button @click="goToIntegrations" class="mt-2 text-sm text-primary hover:underline">Set up your first integration</button>
            </div>

            <div v-else class="space-y-2">
                <div v-for="integration in integrations" :key="integration.id"
                    class="flex items-center gap-3 p-3 bg-surface-raised rounded-xl border border-border-theme">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                         :class="integration.status === 'active' ? 'bg-green-100' : 'bg-gray-100'">
                        <Receipt v-if="integration.type === 'efris'" class="w-4 h-4"
                            :class="integration.status === 'active' ? 'text-green-600' : 'text-gray-500'" />
                        <Plug v-else class="w-4 h-4 text-gray-500" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-text-theme">{{ integration.name }}</p>
                        <p class="text-xs text-text-tertiary capitalize">{{ integration.type }}</p>
                    </div>
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="statusColors[integration.status]">
                        {{ integration.status }}
                    </span>
                </div>
            </div>
        </div>
    </component>
</template>
