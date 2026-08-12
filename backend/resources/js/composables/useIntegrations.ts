import { ref } from 'vue';
import api from '@/composables/axios';

export interface Integration {
    id: string;
    branch_id: string;
    type: string;
    name: string;
    status: 'active' | 'inactive' | 'error' | 'pending';
    config: Record<string, any> | null;
    last_sync_at: string | null;
    last_error: string | null;
    efris_config?: EfrisConfig | null;
    created_at: string;
    updated_at: string;
}

export interface EfrisConfig {
    id: string;
    integration_id: string;
    tin: string;
    weaf_email: string;
    weaf_environment: 'sandbox' | 'production';
    company_name: string | null;
    auto_fiscalize: boolean;
    fiscalize_receipts: boolean;
    weaf_token_expires_at: string | null;
}

export interface AvailableIntegration {
    type: string;
    name: string;
    description: string;
    icon: string;
    category: string;
    requires_config: string[];
}

export function useIntegrations() {
    const integrations = ref<Integration[]>([]);
    const available = ref<AvailableIntegration[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function fetchIntegrations() {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get('/integrations');
            integrations.value = res.data.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Failed to fetch integrations';
        } finally {
            loading.value = false;
        }
    }

    async function fetchAvailable() {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get('/integrations/available');
            available.value = res.data.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Failed to fetch available integrations';
        } finally {
            loading.value = false;
        }
    }

    async function connect(type: string, config: Record<string, any>) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post('/integrations', { type, name: config.name || type, ...config });
            integrations.value.push(res.data.data);
            return res.data.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Failed to connect integration';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function disconnect(id: string) {
        loading.value = true;
        error.value = null;
        try {
            await api.delete(`/integrations/${id}`);
            integrations.value = integrations.value.filter(i => i.id !== id);
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Failed to disconnect integration';
        } finally {
            loading.value = false;
        }
    }

    async function testConnection(id: string) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post(`/integrations/${id}/test`);
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Connection test failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function syncOffline(id: string) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post(`/integrations/${id}/sync`);
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Sync failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchLogs(id: string, page = 1) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get(`/integrations/${id}/logs`, { params: { page } });
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Failed to fetch logs';
        } finally {
            loading.value = false;
        }
    }

    function getIntegration(type: string): Integration | undefined {
        return integrations.value.find(i => i.type === type);
    }

    function isConnected(type: string): boolean {
        const integration = getIntegration(type);
        return !!integration && integration.status === 'active';
    }

    return {
        integrations,
        available,
        loading,
        error,
        fetchIntegrations,
        fetchAvailable,
        connect,
        disconnect,
        testConnection,
        syncOffline,
        fetchLogs,
        getIntegration,
        isConnected,
    };
}
