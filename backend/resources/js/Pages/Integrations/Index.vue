<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useIntegrations, type Integration, type AvailableIntegration } from '@/composables/useIntegrations';
import EfrisSetup from './EfrisSetup.vue';
import EfrisDashboard from './EfrisDashboard.vue';
import { Plug, Receipt, CheckCircle, XCircle, AlertCircle, Loader2, Settings } from 'lucide-vue-next';

const { integrations, available, loading, error, fetchIntegrations, fetchAvailable, disconnect } = useIntegrations();

const showSetup = ref(false);
const selectedIntegration = ref<Integration | null>(null);
const setupType = ref('');

onMounted(async () => {
    await Promise.all([fetchIntegrations(), fetchAvailable()]);
});

function openSetup(type: string) {
    setupType.value = type;
    showSetup.value = true;
}

function openDashboard(integration: Integration) {
    selectedIntegration.value = integration;
}

function handleSetupComplete() {
    showSetup.value = false;
    setupType.value = '';
    fetchIntegrations();
}

function handleDisconnect(id: string) {
    if (confirm('Are you sure you want to disconnect this integration? This will remove all configuration.')) {
        disconnect(id);
        selectedIntegration.value = null;
    }
}

const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-600',
    error: 'bg-red-100 text-red-700',
    pending: 'bg-yellow-100 text-yellow-700',
};

const integrationIcons: Record<string, any> = {
    efris: Receipt,
};

function getIntegrationByType(type: string): Integration | undefined {
    return integrations.value.find(i => i.type === type);
}
</script>

<template>
    <AppLayout>
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-text-theme">Integrations</h1>
                <p class="text-sm text-text-tertiary mt-1">Connect third-party services and tax systems to your POS</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading && integrations.length === 0" class="text-center py-12">
            <Loader2 class="w-8 h-8 animate-spin mx-auto text-primary" />
            <p class="text-text-tertiary mt-2">Loading integrations...</p>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-red-700 text-sm">{{ error }}</p>
        </div>

        <!-- Content -->
        <template v-else>
            <!-- Connected Integrations -->
            <div v-if="integrations.length > 0" class="mb-8">
                <h2 class="text-lg font-semibold text-text-theme mb-4">Connected</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="integration in integrations"
                        :key="integration.id"
                        class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5 cursor-pointer hover:shadow-md transition-shadow"
                        @click="openDashboard(integration)"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                     :class="integration.status === 'active' ? 'bg-green-100' : integration.status === 'error' ? 'bg-red-100' : 'bg-gray-100'">
                                    <component :is="integrationIcons[integration.type] || Plug" class="w-5 h-5"
                                        :class="integration.status === 'active' ? 'text-green-600' : integration.status === 'error' ? 'text-red-600' : 'text-gray-500'" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-text-theme">{{ integration.name }}</h3>
                                    <p class="text-xs text-text-tertiary capitalize">{{ integration.type }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="statusColors[integration.status]">
                                {{ integration.status }}
                            </span>
                        </div>
                        <div v-if="integration.efris_config" class="text-xs text-text-tertiary space-y-1">
                            <p>TIN: {{ integration.efris_config.tin }}</p>
                            <p>Company: {{ integration.efris_config.company_name || 'N/A' }}</p>
                            <p>Environment: {{ integration.efris_config.weaf_environment }}</p>
                        </div>
                        <div v-if="integration.last_error" class="mt-2 text-xs text-red-600 bg-red-50 rounded p-2">
                            {{ integration.last_error }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Integrations -->
            <div>
                <h2 class="text-lg font-semibold text-text-theme mb-4">Available Integrations</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="item in available"
                        :key="item.type"
                        class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5"
                    >
                        <div class="flex items-start gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                <component :is="integrationIcons[item.type] || Plug" class="w-5 h-5 text-primary" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-text-theme">{{ item.name }}</h3>
                                <span class="text-xs text-text-tertiary capitalize px-1.5 py-0.5 bg-gray-100 rounded">{{ item.category }}</span>
                            </div>
                        </div>
                        <p class="text-sm text-text-secondary mb-4">{{ item.description }}</p>
                        <button
                            v-if="!getIntegrationByType(item.type)"
                            @click="openSetup(item.type)"
                            class="w-full px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors"
                        >
                            Connect
                        </button>
                        <div v-else class="flex items-center gap-2 text-green-600 text-sm">
                            <CheckCircle class="w-4 h-4" />
                            <span>Connected</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- EFRIS Setup Modal -->
        <EfrisSetup
            v-if="showSetup && setupType === 'efris'"
            @close="showSetup = false"
            @complete="handleSetupComplete"
        />

        <!-- EFRIS Dashboard Modal -->
        <EfrisDashboard
            v-if="selectedIntegration && selectedIntegration.type === 'efris'"
            :integration="selectedIntegration"
            @close="selectedIntegration = null"
            @disconnect="handleDisconnect"
        />
    </AppLayout>
</template>
