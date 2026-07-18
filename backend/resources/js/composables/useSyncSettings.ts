import { ref, computed } from 'vue';
import { getSyncMode, setSyncMode as setSyncModeStorage, SyncMode } from '@/services/SyncService';
import { useAuth } from '@/composables/useAuth';
import api from '@/composables/axios';
import type { PrinterConfig } from '@/services/PrinterService';

const syncMode = ref<SyncMode>(getSyncMode());
const serverSyncMode = ref<SyncMode | null>(null);
const serverPrinterConfig = ref<PrinterConfig | null>(null);
const isLoadingServer = ref(false);
const isUpdating = ref(false);
const lastError = ref<string | null>(null);

export function useSyncSettings() {
    const auth = useAuth();

    async function loadServerSyncMode() {
        if (!auth.user.value) return;
        isLoadingServer.value = true;
        lastError.value = null;
        try {
            const res = await api.get('/sync/settings');
            const data = res.data?.data;
            const mode = (data?.sync_mode as SyncMode) || 'auto';
            serverSyncMode.value = mode;
            serverPrinterConfig.value = data?.printer_config || null;
            // Sync local storage with server
            if (mode !== syncMode.value) {
                setSyncModeStorage(mode);
                syncMode.value = mode;
            }
        } catch (err: any) {
            lastError.value = err?.response?.data?.error?.message || err?.message || 'Failed to load sync settings';
            serverSyncMode.value = null;
            serverPrinterConfig.value = null;
        } finally {
            isLoadingServer.value = false;
        }
    }

    async function updateSyncMode(mode: SyncMode) {
        isUpdating.value = true;
        lastError.value = null;
        try {
            await api.put('/sync/settings', { sync_mode: mode });
            setSyncModeStorage(mode);
            syncMode.value = mode;
            serverSyncMode.value = mode;
        } catch (err: any) {
            lastError.value = err?.response?.data?.error?.message || err?.message || 'Failed to update sync mode';
            throw err;
        } finally {
            isUpdating.value = false;
        }
    }

    async function updatePrinterConfig(config: PrinterConfig) {
        isUpdating.value = true;
        lastError.value = null;
        try {
            const res = await api.put('/sync/settings', { printer_config: config });
            serverPrinterConfig.value = res.data?.data?.printer_config || null;
        } catch (err: any) {
            lastError.value = err?.response?.data?.error?.message || err?.message || 'Failed to update printer config';
            throw err;
        } finally {
            isUpdating.value = false;
        }
    }

    async function updateSettings(payload: { sync_mode?: SyncMode; printer_config?: PrinterConfig; auto_sync_interval_seconds?: number }) {
        isUpdating.value = true;
        lastError.value = null;
        try {
            const res = await api.put('/sync/settings', payload);
            const data = res.data?.data;
            if (payload.sync_mode) {
                setSyncModeStorage(payload.sync_mode);
                syncMode.value = payload.sync_mode;
                serverSyncMode.value = payload.sync_mode;
            }
            if (data?.printer_config) {
                serverPrinterConfig.value = data.printer_config;
            }
            return res.data?.data;
        } catch (err: any) {
            lastError.value = err?.response?.data?.error?.message || err?.message || 'Failed to update settings';
            throw err;
        } finally {
            isUpdating.value = false;
        }
    }

    function setLocalMode(mode: SyncMode) {
        setSyncModeStorage(mode);
        syncMode.value = mode;
    }

    return {
        syncMode,
        serverSyncMode,
        serverPrinterConfig,
        isLoadingServer,
        isUpdating,
        lastError,
        isAuto: computed(() => syncMode.value === 'auto'),
        isManual: computed(() => syncMode.value === 'manual'),
        loadServerSyncMode,
        updateSyncMode,
        updatePrinterConfig,
        updateSettings,
        setLocalMode,
    };
}
