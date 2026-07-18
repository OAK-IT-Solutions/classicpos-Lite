<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useIntegrations, type Integration } from '@/composables/useIntegrations';
import { useEfris, type FiscalLog } from '@/composables/useEfris';
import { X, Loader2, RefreshCw, Search, FileText, CheckCircle, XCircle, Clock, AlertCircle, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    integration: Integration;
}>();

const emit = defineEmits<{
    close: [];
    disconnect: [id: string];
}>();

const { testConnection, syncOffline, loading: integrationLoading } = useIntegrations();
const { fetchFiscalLogs, fiscalLogs, loading: efrisLoading, pagination } = useEfris();

const testResult = ref<{ success: boolean; message: string } | null>(null);
const syncResult = ref<{ success: boolean; processed: number; succeeded: number; failed: number } | null>(null);
const activeTab = ref<'logs' | 'config'>('logs');

const config = props.integration.efris_config;

onMounted(() => {
    fetchFiscalLogs();
});

async function handleTest() {
    testResult.value = null;
    try {
        const result = await testConnection(props.integration.id);
        testResult.value = { success: true, message: result.message || 'Connection successful' };
    } catch (e: any) {
        testResult.value = { success: false, message: e.response?.data?.error?.message || 'Test failed' };
    }
}

async function handleSync() {
    syncResult.value = null;
    try {
        const result = await syncOffline(props.integration.id);
        syncResult.value = result.data;
    } catch (e: any) {
        syncResult.value = { success: false, processed: 0, succeeded: 0, failed: 0 };
    }
}

const statusColors: Record<string, string> = {
    success: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    failed: 'bg-red-100 text-red-700',
    offline_queued: 'bg-blue-100 text-blue-700',
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')"></div>
        <div class="relative bg-surface-raised rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[85vh] flex flex-col overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-border-theme shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                        <FileText class="w-5 h-5 text-green-600" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-text-theme">URA EFRIS Dashboard</h2>
                        <p class="text-xs text-text-tertiary">
                            {{ config?.company_name || 'Connected' }} &middot; TIN: {{ config?.tin }}
                        </p>
                    </div>
                </div>
                <button @click="emit('close')" class="p-1 rounded-lg hover:bg-surface-alt text-text-tertiary">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-border-theme px-6 shrink-0">
                <button @click="activeTab = 'logs'"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
                    :class="activeTab === 'logs' ? 'border-primary text-primary' : 'border-transparent text-text-tertiary hover:text-text-theme'">
                    Fiscal Logs
                </button>
                <button @click="activeTab = 'config'"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
                    :class="activeTab === 'config' ? 'border-primary text-primary' : 'border-transparent text-text-tertiary hover:text-text-theme'">
                    Configuration
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <!-- Logs Tab -->
                <div v-if="activeTab === 'logs'">
                    <!-- Quick Actions -->
                    <div class="flex gap-2 mb-4">
                        <button @click="handleTest" :disabled="integrationLoading"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-border-theme rounded-lg hover:bg-surface-alt transition-colors disabled:opacity-50">
                            <RefreshCw :class="integrationLoading ? 'animate-spin' : ''" class="w-3.5 h-3.5" />
                            Test Connection
                        </button>
                        <button @click="handleSync" :disabled="integrationLoading"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-border-theme rounded-lg hover:bg-surface-alt transition-colors disabled:opacity-50">
                            <RefreshCw :class="integrationLoading ? 'animate-spin' : ''" class="w-3.5 h-3.5" />
                            Process Offline Queue
                        </button>
                    </div>

                    <!-- Test Result -->
                    <div v-if="testResult" class="mb-4 p-3 rounded-lg text-sm"
                         :class="testResult.success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                        {{ testResult.message }}
                    </div>

                    <!-- Sync Result -->
                    <div v-if="syncResult" class="mb-4 p-3 rounded-lg text-sm bg-blue-50 text-blue-700">
                        Processed: {{ syncResult.processed }} | Succeeded: {{ syncResult.succeeded }} | Failed: {{ syncResult.failed }}
                    </div>

                    <!-- Logs Table -->
                    <div v-if="efrisLoading" class="text-center py-8">
                        <Loader2 class="w-6 h-6 animate-spin mx-auto text-primary" />
                    </div>
                    <div v-else-if="fiscalLogs.length === 0" class="text-center py-8 text-text-tertiary text-sm">
                        No fiscal logs yet
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="log in fiscalLogs" :key="log.id"
                            class="flex items-center gap-3 p-3 rounded-lg border border-border-theme hover:bg-surface-alt">
                            <div class="shrink-0">
                                <CheckCircle v-if="log.status === 'success'" class="w-5 h-5 text-green-500" />
                                <XCircle v-else-if="log.status === 'failed'" class="w-5 h-5 text-red-500" />
                                <Clock v-else class="w-5 h-5 text-yellow-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-text-theme truncate">
                                    {{ log.sale?.invoice_number || 'N/A' }}
                                    <span v-if="log.efris_fdn" class="text-text-tertiary">&middot; FDN: {{ log.efris_fdn }}</span>
                                </p>
                                <p v-if="log.error_message" class="text-xs text-red-600 truncate">{{ log.error_message }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full shrink-0" :class="statusColors[log.status]">
                                {{ log.status }}
                            </span>
                            <span class="text-xs text-text-tertiary shrink-0">
                                {{ new Date(log.created_at).toLocaleDateString() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Config Tab -->
                <div v-if="activeTab === 'config'" class="space-y-4">
                    <div class="bg-surface-alt rounded-lg p-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-text-tertiary">Status</span>
                            <span class="font-medium" :class="integration.status === 'active' ? 'text-green-600' : 'text-red-600'">
                                {{ integration.status }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-text-tertiary">TIN</span>
                            <span class="font-medium text-text-theme">{{ config?.tin }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-text-tertiary">Company</span>
                            <span class="font-medium text-text-theme">{{ config?.company_name || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-text-tertiary">Environment</span>
                            <span class="font-medium text-text-theme capitalize">{{ config?.weaf_environment }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-text-tertiary">Email</span>
                            <span class="font-medium text-text-theme">{{ config?.weaf_email }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-text-tertiary">Auto-fiscalize</span>
                            <span class="font-medium" :class="config?.auto_fiscalize ? 'text-green-600' : 'text-text-tertiary'">
                                {{ config?.auto_fiscalize ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-text-tertiary">Receipts Mode</span>
                            <span class="font-medium text-text-theme">{{ config?.fiscalize_receipts ? 'Receipts' : 'Invoices' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-text-tertiary">Token Expires</span>
                            <span class="font-medium text-text-theme">
                                {{ config?.weaf_token_expires_at ? new Date(config.weaf_token_expires_at).toLocaleDateString() : 'N/A' }}
                            </span>
                        </div>
                    </div>

                    <button @click="emit('disconnect', integration.id)"
                        class="flex items-center gap-2 w-full px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                        <Trash2 class="w-4 h-4" />
                        Disconnect Integration
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
