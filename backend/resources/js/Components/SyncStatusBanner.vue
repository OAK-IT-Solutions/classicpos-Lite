<template>
    <div v-if="show" class="flex items-center gap-2">
        <!-- Offline banner -->
        <div v-if="isOffline"
            class="flex items-center gap-2 px-3 py-2 bg-red-100 border-2 border-red-300 rounded-lg">
            <div class="relative">
                <div class="w-2 h-2 rounded-full bg-red-600" />
                <div class="absolute inset-0 w-2 h-2 rounded-full bg-red-600 animate-ping opacity-75" />
            </div>
            <span class="text-sm font-bold text-red-800">OFFLINE</span>
            <span v-if="pendingCount > 0" class="text-xs px-1.5 py-0.5 rounded bg-red-600 text-white font-bold">
                {{ pendingCount }}
            </span>
        </div>

        <!-- Pending sync banner -->
        <div v-else-if="pendingCount > 0"
            class="flex items-center gap-2 px-3 py-2 bg-amber-100 border-2 border-amber-300 rounded-lg">
            <div class="w-2 h-2 rounded-full bg-amber-600 animate-pulse" />
            <span class="text-sm font-bold text-amber-800">{{ pendingCount }} PENDING</span>
            <button @click="syncNow" :disabled="isSyncing"
                class="text-xs px-2 py-0.5 bg-amber-600 hover:bg-amber-700 text-white rounded font-medium disabled:opacity-50">
                {{ isSyncing ? 'Syncing...' : 'Sync' }}
            </button>
        </div>

        <!-- Synced banner (only show in some contexts) -->
        <div v-else-if="showSynced"
            class="flex items-center gap-2 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-lg">
            <div class="w-2 h-2 rounded-full bg-emerald-500" />
            <span class="text-xs font-medium text-emerald-800">Synced</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useNetwork } from '@/composables/useNetwork';
import { useSync } from '@/services/SyncService';

withDefaults(defineProps<{
    showSynced?: boolean;
}>(), {
    showSynced: false,
});

const { isOffline, isOnline } = useNetwork();
const { pendingSalesCount, isSyncing, syncPendingSales } = useSync();

const pendingCount = computed(() => pendingSalesCount.value);

const show = computed(() => {
    if (isOffline.value) return true;
    if (pendingCount.value > 0) return true;
    return false;
});

async function syncNow() {
    await syncPendingSales();
}
</script>
