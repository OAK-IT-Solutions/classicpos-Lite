import { ref, computed, watch } from 'vue';
import { db, PendingSale, PendingPayment, SyncLogEntry } from '@/services/OfflineDB';
import { useNetwork } from '@/composables/useNetwork';
import api from '@/composables/axios';

const pendingSalesCount = ref(0);
const isSyncing = ref(false);
const lastSyncAt = ref<number | null>(null);
const lastSyncError = ref<string | null>(null);
const lastSyncResult = ref<{
    pushed: number;
    failed: number;
    items: { local_id: string; success: boolean; error?: string; server_id?: string }[];
} | null>(null);

let backgroundSyncInterval: ReturnType<typeof setInterval> | null = null;
let networkListenerInitialized = false;
let syncInProgress = false;

export const SYNC_MODE_KEY = 'classicpos_sync_mode';
export const AUTO_SYNC_INTERVAL_MS = 30_000;

export type SyncMode = 'auto' | 'manual';

export function getSyncMode(): SyncMode {
    if (typeof localStorage === 'undefined') return 'auto';
    return (localStorage.getItem(SYNC_MODE_KEY) as SyncMode) || 'auto';
}

export function setSyncMode(mode: SyncMode): void {
    if (typeof localStorage === 'undefined') return;
    localStorage.setItem(SYNC_MODE_KEY, mode);
}

async function refreshPendingCount() {
    try {
        pendingSalesCount.value = await db.getPendingSalesCount();
    } catch (err) {
        console.error('[Sync] Failed to refresh pending count:', err);
    }
}

export async function addPendingSale(sale: Omit<PendingSale, 'id' | 'status' | 'server_id' | 'server_invoice_number' | 'error_message' | 'retry_count' | 'created_at' | 'updated_at'>): Promise<PendingSale> {
    const now = Date.now();
    const pending: PendingSale = {
        id: sale.local_id,
        ...sale,
        status: 'pending_sync',
        server_id: null,
        server_invoice_number: null,
        error_message: null,
        retry_count: 0,
        created_at: now,
        updated_at: now,
    };

    await db.pendingSales.put(pending);
    await refreshPendingCount();

    // If auto-sync is on and we're online, trigger a sync
    if (getSyncMode() === 'auto' && navigator.onLine) {
        syncPendingSales().catch((err) => {
            console.warn('[Sync] Auto-sync after add failed:', err);
        });
    }

    return pending;
}

export async function syncPendingSales(): Promise<typeof lastSyncResult.value> {
    if (syncInProgress) {
        console.log('[Sync] Sync already in progress, skipping');
        return lastSyncResult.value;
    }

    if (!navigator.onLine) {
        console.log('[Sync] Offline, cannot sync');
        return null;
    }

    syncInProgress = true;
    isSyncing.value = true;
    lastSyncError.value = null;

    const startedAt = Date.now();
    const result: NonNullable<typeof lastSyncResult.value> = {
        pushed: 0,
        failed: 0,
        items: [],
    };

    try {
        const pending = await db.getAllPendingSales();

        if (pending.length === 0) {
            lastSyncAt.value = Date.now();
            lastSyncResult.value = result;
            await logSync({
                type: 'sale',
                direction: 'push',
                status: 'success',
                items_count: 0,
                error_message: null,
                started_at: startedAt,
                finished_at: Date.now(),
            });
            return result;
        }

        // Update each to "syncing" state
        await db.pendingSales.bulkPut(pending.map(p => ({ ...p, status: 'syncing', updated_at: Date.now() })));

        // Send to server in batch
        const payload = {
            sales: pending.map(p => ({
                local_id: p.local_id,
                branch_id: p.branch_id,
                customer_id: p.customer_id,
                items: p.items,
                payment_method: p.payment_method,
                gateway: p.gateway,
                promo_code: p.promo_code,
                tax_profile_id: p.tax_profile_id,
                loyalty_points_redeemed: p.loyalty_points_redeemed,
                subtotal: p.subtotal,
                discount: p.discount,
                tax_amount: p.tax_amount,
                total_amount: p.total_amount,
                cash_received: p.cash_received,
                change_due: p.change_due,
                created_offline_at: new Date(p.created_at).toISOString(),
            })),
        };

        try {
            const response = await api.post('/sync/sales', payload, { timeout: 60_000 });
            const syncResponse = response.data;

            // Process results
            if (syncResponse?.results) {
                for (const item of syncResponse.results) {
                    const sale = pending.find(p => p.local_id === item.local_id);
                    if (!sale) continue;

                    if (item.success) {
                        await db.pendingSales.update(sale.id, {
                            status: 'synced',
                            server_id: item.server_id,
                            server_invoice_number: item.invoice_number,
                            updated_at: Date.now(),
                        });
                        result.pushed++;
                        result.items.push({
                            local_id: sale.local_id,
                            success: true,
                            server_id: item.server_id,
                        });
                    } else {
                        await db.pendingSales.update(sale.id, {
                            status: 'failed',
                            error_message: item.error || 'Unknown error',
                            retry_count: (sale.retry_count || 0) + 1,
                            updated_at: Date.now(),
                        });
                        result.failed++;
                        result.items.push({
                            local_id: sale.local_id,
                            success: false,
                            error: item.error,
                        });
                    }
                }
            }

            lastSyncAt.value = Date.now();
            lastSyncResult.value = result;

            await logSync({
                type: 'sale',
                direction: 'push',
                status: result.failed === 0 ? 'success' : 'partial',
                items_count: result.pushed + result.failed,
                error_message: result.failed > 0 ? `${result.failed} items failed` : null,
                started_at: startedAt,
                finished_at: Date.now(),
            });
        } catch (err: any) {
            const errorMsg = err?.response?.data?.error?.message
                || err?.response?.data?.message
                || err?.message
                || 'Sync request failed';

            // Mark all as failed
            await db.pendingSales.bulkPut(pending.map(p => ({
                ...p,
                status: 'failed',
                error_message: errorMsg,
                retry_count: (p.retry_count || 0) + 1,
                updated_at: Date.now(),
            })));

            lastSyncError.value = errorMsg;
            result.failed = pending.length;
            result.items = pending.map(p => ({
                local_id: p.local_id,
                success: false,
                error: errorMsg,
            }));

            await logSync({
                type: 'sale',
                direction: 'push',
                status: 'failed',
                items_count: pending.length,
                error_message: errorMsg,
                started_at: startedAt,
                finished_at: Date.now(),
            });
        }
    } catch (err: any) {
        const errorMsg = err?.message || 'Sync failed';
        lastSyncError.value = errorMsg;
        console.error('[Sync] Sync error:', err);
    } finally {
        isSyncing.value = false;
        syncInProgress = false;
        await refreshPendingCount();
    }

    return result;
}

async function logSync(entry: Omit<SyncLogEntry, 'id'>) {
    try {
        await db.syncLog.add(entry);
        // Keep only last 100 log entries
        const count = await db.syncLog.count();
        if (count > 100) {
            const oldest = await db.syncLog.orderBy('started_at').limit(count - 100).primaryKeys();
            await db.syncLog.bulkDelete(oldest);
        }
    } catch (err) {
        console.warn('[Sync] Failed to log sync event:', err);
    }
}

export async function getSyncLog(limit = 20): Promise<SyncLogEntry[]> {
    try {
        return await db.syncLog.orderBy('started_at').reverse().limit(limit).toArray();
    } catch {
        return [];
    }
}

export async function getPendingSales(): Promise<PendingSale[]> {
    return await db.getAllPendingSales();
}

export async function clearSyncedSales(): Promise<number> {
    return await db.pendingSales.where('status').equals('synced').delete();
}

export async function deletePendingSale(localId: string): Promise<void> {
    await db.pendingSales.delete(localId);
    await refreshPendingCount();
}

export function initNetworkSyncListener() {
    if (networkListenerInitialized || typeof window === 'undefined') return;
    networkListenerInitialized = true;

    window.addEventListener('online', async () => {
        console.log('[Sync] Network back online, triggering sync');
        if (getSyncMode() === 'auto') {
            await syncPendingSales();
        }
    });
}

export function startBackgroundSync() {
    if (typeof window === 'undefined') return;

    initNetworkSyncListener();
    refreshPendingCount();

    if (backgroundSyncInterval) {
        clearInterval(backgroundSyncInterval);
    }

    backgroundSyncInterval = setInterval(async () => {
        if (getSyncMode() !== 'auto') return;
        if (!navigator.onLine) return;
        if (syncInProgress) return;

        const count = await db.getPendingSalesCount();
        if (count > 0) {
            console.log(`[Sync] Auto-syncing ${count} pending sales`);
            await syncPendingSales();
        }
    }, AUTO_SYNC_INTERVAL_MS);
}

export function stopBackgroundSync() {
    if (backgroundSyncInterval) {
        clearInterval(backgroundSyncInterval);
        backgroundSyncInterval = null;
    }
}

export function useSync() {
    return {
        pendingSalesCount,
        isSyncing,
        lastSyncAt,
        lastSyncError,
        lastSyncResult,
        syncMode: computed(() => getSyncMode()),
        syncPendingSales,
        getPendingSales,
        getSyncLog,
        clearSyncedSales,
        deletePendingSale,
        refreshPendingCount,
        setSyncMode,
    };
}
