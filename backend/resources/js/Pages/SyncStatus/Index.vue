<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import api from '@/composables/axios'
import { useNetwork } from '@/composables/useNetwork'
import { useSyncSettings } from '@/composables/useSyncSettings'
import { useSync, getPendingSales, getSyncLog, clearSyncedSales, deletePendingSale } from '@/services/SyncService'
import type { PendingSale } from '@/services/OfflineDB'
import GlobalSyncIndicator from '@/Components/GlobalSyncIndicator.vue'
import { RefreshCw, Wifi, WifiOff, Play, Trash2, X, CheckCircle2, AlertCircle, Clock, Settings as SettingsIcon, Database, Activity, Zap, ZapOff } from 'lucide-vue-next'

interface SyncStatus {
    online: boolean;
    last_sync: string | null;
    next_sync: string | null;
    latency_ms: number;
    bandwidth_mbps: number;
    sync_mode: string;
    sync_queue: number;
    payment_queue: number;
    pending_offline_sales: number;
    tables: Record<string, { pending: number; synced: number; failed: number }>;
}

const { isOnline, isOffline } = useNetwork()
const { pendingSalesCount, isSyncing, lastSyncAt, syncPendingSales, lastSyncResult } = useSync()
const { syncMode, isAuto, isManual, loadServerSyncMode, updateSyncMode, isUpdating } = useSyncSettings()

const status = ref<SyncStatus | null>(null)
const loading = ref(true)
const error = ref('')
const pendingSales = ref<PendingSale[]>([])
const syncLog = ref<any[]>([])
const showSyncLog = ref(false)

let interval: ReturnType<typeof setInterval> | null = null

async function loadStatus() {
    loading.value = true
    error.value = ''
    try {
        const res = await api.get('/sync/status')
        status.value = res.data?.data ?? null
    } catch (err: any) {
        error.value = err?.response?.data?.error?.message || err?.message || 'Failed to load sync status'
    } finally {
        loading.value = false
    }
}

async function loadPendingSales() {
    try {
        pendingSales.value = await getPendingSales()
    } catch (err) {
        console.error('Failed to load pending sales:', err)
        pendingSales.value = []
    }
}

async function loadSyncLog() {
    try {
        syncLog.value = await getSyncLog(20)
    } catch (err) {
        console.error('Failed to load sync log:', err)
    }
}

async function startSync() {
    await syncPendingSales()
    await Promise.all([loadStatus(), loadPendingSales()])
}

async function changeSyncMode(mode: 'auto' | 'manual') {
    try {
        await updateSyncMode(mode)
        await loadStatus()
    } catch (err) {
        console.error('Failed to change sync mode:', err)
        alert('Failed to change sync mode. See console for details.')
    }
}

async function clearSynced() {
    const count = await clearSyncedSales()
    await loadPendingSales()
    alert(`Cleared ${count} synced records.`)
}

async function deleteSale(localId: string) {
    if (!confirm('Delete this pending sale? This cannot be undone.')) return
    await deletePendingSale(localId)
    await loadPendingSales()
}

function formatDate(ts: number | string | null): string {
    if (!ts) return 'Never'
    return new Date(ts).toLocaleString()
}

function formatRelativeTime(ts: number | string | null): string {
    if (!ts) return ''
    const date = typeof ts === 'number' ? new Date(ts) : new Date(ts)
    const diff = Date.now() - date.getTime()
    const secs = Math.floor(diff / 1000)
    if (secs < 60) return `${secs}s ago`
    const mins = Math.floor(secs / 60)
    if (mins < 60) return `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24) return `${hrs}h ago`
    const days = Math.floor(hrs / 24)
    return `${days}d ago`
}

onMounted(async () => {
    await Promise.all([loadStatus(), loadServerSyncMode(), loadPendingSales()])
    interval = setInterval(() => {
        loadStatus()
        loadPendingSales()
    }, 15000)
})

onUnmounted(() => {
    if (interval) clearInterval(interval)
})
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">Sync & Offline Status</h1>
                    <p class="text-text-tertiary mt-1">Monitor connectivity, manage offline sales, and configure sync preferences</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="loadStatus" class="px-3 py-2 bg-surface-raised border border-border-input text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors flex items-center gap-1.5">
                        <RefreshCw class="w-3.5 h-3.5" />
                        Refresh
                    </button>
                    <button @click="startSync" :disabled="isSyncing || isOffline"
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-btn-primary-hover text-sm font-medium transition-colors disabled:opacity-50 flex items-center gap-1.5">
                        <Play v-if="!isSyncing" class="w-3.5 h-3.5" />
                        <RefreshCw v-else class="w-3.5 h-3.5 animate-spin" />
                        {{ isSyncing ? 'Syncing...' : 'Sync Now' }}
                    </button>
                </div>
            </div>

            <!-- Connection status card -->
            <div class="bg-gradient-to-br rounded-xl shadow-sm border-2 p-6"
                :class="isOffline ? 'from-red-50 to-red-100/50 border-red-300' : 'from-emerald-50 to-emerald-100/50 border-emerald-300'">
                <div class="flex items-start justify-between">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center"
                            :class="isOffline ? 'bg-red-200' : 'bg-emerald-200'">
                            <WifiOff v-if="isOffline" class="w-6 h-6 text-red-700" />
                            <Wifi v-else class="w-6 h-6 text-emerald-700" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-bold" :class="isOffline ? 'text-red-900' : 'text-emerald-900'">
                                    {{ isOffline ? 'Offline Mode' : 'Online' }}
                                </h2>
                                <GlobalSyncIndicator :show-label="false" variant="dot" />
                            </div>
                            <p class="text-sm mt-1" :class="isOffline ? 'text-red-700' : 'text-emerald-700'">
                                {{ isOffline
                                    ? 'Working offline. Sales are saved locally and will sync when reconnected.'
                                    : 'Connected. All data syncing normally.' }}
                            </p>
                            <div v-if="status && isOnline" class="flex items-center gap-4 mt-3 text-xs text-emerald-800">
                                <span>Latency: <strong>{{ status.latency_ms ?? '-' }}ms</strong></span>
                                <span>Bandwidth: <strong>{{ status.bandwidth_mbps ?? '-' }} Mbps</strong></span>
                                <span>Last sync: <strong>{{ formatRelativeTime(status.last_sync) }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <Database class="w-4 h-4 text-text-tertiary" />
                        <p class="text-xs text-text-tertiary font-medium uppercase">Pending Offline</p>
                    </div>
                    <p class="text-3xl font-bold" :class="pendingSalesCount > 0 ? 'text-amber-600' : 'text-text-theme'">
                        {{ pendingSalesCount }}
                    </p>
                    <p class="text-xs text-text-tertiary mt-1">sales waiting to sync</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <Activity class="w-4 h-4 text-text-tertiary" />
                        <p class="text-xs text-text-tertiary font-medium uppercase">Server Queue</p>
                    </div>
                    <p class="text-3xl font-bold text-text-theme">
                        {{ (status?.sync_queue || 0) + (status?.payment_queue || 0) }}
                    </p>
                    <p class="text-xs text-text-tertiary mt-1">items in server queue</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <Zap v-if="isAuto" class="w-4 h-4 text-emerald-600" />
                        <ZapOff v-else class="w-4 h-4 text-text-tertiary" />
                        <p class="text-xs text-text-tertiary font-medium uppercase">Sync Mode</p>
                    </div>
                    <p class="text-lg font-bold" :class="isAuto ? 'text-emerald-600' : 'text-text-secondary'">
                        {{ isAuto ? 'Automatic' : 'Manual' }}
                    </p>
                    <p class="text-xs text-text-tertiary mt-1">{{ isAuto ? 'Syncs every 30s' : 'Click Sync Now' }}</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <CheckCircle2 class="w-4 h-4 text-text-tertiary" />
                        <p class="text-xs text-text-tertiary font-medium uppercase">Last Sync</p>
                    </div>
                    <p class="text-sm font-semibold text-text-theme">
                        {{ lastSyncAt ? formatRelativeTime(lastSyncAt) : 'Never' }}
                    </p>
                    <p v-if="lastSyncResult" class="text-xs mt-1"
                        :class="lastSyncResult.failed > 0 ? 'text-amber-600' : 'text-emerald-600'">
                        {{ lastSyncResult.pushed }} synced, {{ lastSyncResult.failed }} failed
                    </p>
                    <p v-else class="text-xs text-text-tertiary mt-1">No syncs yet</p>
                </div>
            </div>

            <!-- Sync Mode Toggle -->
            <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <div class="flex items-center gap-2 mb-3">
                    <SettingsIcon class="w-5 h-5 text-text-tertiary" />
                    <h2 class="text-lg font-semibold text-text-theme">Sync Mode</h2>
                </div>
                <p class="text-sm text-text-tertiary mb-4">Choose how offline sales are synced to the server.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-w-2xl">
                    <button @click="changeSyncMode('auto')" :disabled="isUpdating"
                        class="text-left p-4 border-2 rounded-lg transition-colors"
                        :class="isAuto ? 'border-emerald-500 bg-emerald-50' : 'border-border-theme hover:border-emerald-300 hover:bg-surface-alt'">
                        <div class="flex items-start gap-3">
                            <Zap class="w-5 h-5 mt-0.5" :class="isAuto ? 'text-emerald-600' : 'text-text-tertiary'" />
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm" :class="isAuto ? 'text-emerald-900' : 'text-text-theme'">Automatic (Recommended)</span>
                                    <span v-if="isAuto" class="text-xs px-1.5 py-0.5 rounded bg-emerald-600 text-white">ACTIVE</span>
                                </div>
                                <p class="text-xs text-text-tertiary mt-1">Sales sync automatically every 30 seconds when online. Also syncs immediately after creation when reconnected.</p>
                            </div>
                        </div>
                    </button>
                    <button @click="changeSyncMode('manual')" :disabled="isUpdating"
                        class="text-left p-4 border-2 rounded-lg transition-colors"
                        :class="isManual ? 'border-blue-500 bg-blue-50' : 'border-border-theme hover:border-blue-300 hover:bg-surface-alt'">
                        <div class="flex items-start gap-3">
                            <ZapOff class="w-5 h-5 mt-0.5" :class="isManual ? 'text-blue-600' : 'text-text-tertiary'" />
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm" :class="isManual ? 'text-blue-900' : 'text-text-theme'">Manual</span>
                                    <span v-if="isManual" class="text-xs px-1.5 py-0.5 rounded bg-blue-600 text-white">ACTIVE</span>
                                </div>
                                <p class="text-xs text-text-tertiary mt-1">Sales are queued offline. Click "Sync Now" to push them to the server. Use this on metered connections or for batched syncing.</p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Pending offline sales -->
            <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-text-theme">Pending Offline Sales</h2>
                        <p class="text-xs text-text-tertiary mt-1">Sales created while offline or while sync was disabled</p>
                    </div>
                    <button v-if="pendingSales.length > 0" @click="clearSynced" class="text-xs px-3 py-1.5 text-text-secondary hover:text-danger-theme border border-border-input rounded-md transition-colors flex items-center gap-1">
                        <Trash2 class="w-3 h-3" />
                        Clear Synced
                    </button>
                </div>

                <div v-if="loading" class="text-center py-8 text-text-tertiary">Loading...</div>

                <div v-else-if="pendingSales.length === 0" class="text-center py-12">
                    <CheckCircle2 class="w-12 h-12 text-emerald-500 mx-auto mb-3" />
                    <h3 class="text-sm font-semibold text-text-theme">All Caught Up</h3>
                    <p class="text-xs text-text-tertiary mt-1">No pending offline sales. All your data is synced to the server.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-border-theme bg-surface-alt">
                                <th class="text-left px-4 py-2 text-xs font-medium text-text-tertiary uppercase">Local ID</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-text-tertiary uppercase">Created</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-text-tertiary uppercase">Items</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-text-tertiary uppercase">Total</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-text-tertiary uppercase">Status</th>
                                <th class="text-right px-4 py-2 text-xs font-medium text-text-tertiary uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sale in pendingSales" :key="sale.id" class="border-b border-gray-100">
                                <td class="px-4 py-3 text-xs font-mono text-text-secondary">
                                    {{ sale.local_id.slice(0, 20) }}...
                                </td>
                                <td class="px-4 py-3 text-xs text-text-secondary">
                                    {{ formatDate(sale.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-text-secondary">
                                    {{ sale.items.length }} items
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-text-theme">
                                    ${{ sale.total_amount.toFixed(2) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="sale.status === 'synced'" class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Synced</span>
                                    <span v-else-if="sale.status === 'syncing'" class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">Syncing</span>
                                    <span v-else-if="sale.status === 'failed'" class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-800">Failed</span>
                                    <span v-else class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending</span>
                                    <p v-if="sale.server_invoice_number" class="text-xs text-text-tertiary mt-1">
                                        Invoice: {{ sale.server_invoice_number }}
                                    </p>
                                    <p v-if="sale.error_message" class="text-xs text-red-600 mt-1 max-w-xs truncate" :title="sale.error_message">
                                        {{ sale.error_message }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="deleteSale(sale.id)" class="text-xs text-red-600 hover:text-red-700 flex items-center gap-1 ml-auto">
                                        <X class="w-3 h-3" />
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Server-side table breakdown -->
            <div v-if="status?.tables" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <h2 class="text-lg font-semibold text-text-theme mb-4">Server-Side Table Sync</h2>
                <div class="space-y-2">
                    <div v-for="(info, table) in status.tables" :key="table"
                        class="flex items-center justify-between py-3 px-4 bg-surface-alt rounded-lg">
                        <span class="text-sm font-medium text-text-secondary capitalize">{{ String(table).replace(/_/g, ' ') }}</span>
                        <div class="flex gap-4 text-xs">
                            <span class="text-amber-700 font-medium">{{ info.pending }} pending</span>
                            <span class="text-emerald-700 font-medium">{{ info.synced }} synced</span>
                            <span v-if="info.failed" class="text-red-700 font-medium">{{ info.failed }} failed</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Last sync result -->
            <div v-if="lastSyncResult && (lastSyncResult.pushed > 0 || lastSyncResult.failed > 0)"
                class="rounded-xl border p-4"
                :class="lastSyncResult.failed === 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'">
                <h3 class="text-sm font-semibold mb-2" :class="lastSyncResult.failed === 0 ? 'text-emerald-900' : 'text-amber-900'">
                    Last Sync Result
                </h3>
                <div class="text-xs space-y-1" :class="lastSyncResult.failed === 0 ? 'text-emerald-800' : 'text-amber-800'">
                    <p>✓ {{ lastSyncResult.pushed }} synced successfully</p>
                    <p v-if="lastSyncResult.failed > 0">⚠ {{ lastSyncResult.failed }} failed</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
