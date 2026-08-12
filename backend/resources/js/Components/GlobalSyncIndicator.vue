<template>
    <div
        v-if="show"
        class="flex items-center gap-1.5"
        :class="containerClass"
        :title="tooltip"
    >
        <div class="relative">
            <div
                class="w-2 h-2 rounded-full"
                :class="dotClass"
            />
            <div
                v-if="isSyncing"
                class="absolute inset-0 w-2 h-2 rounded-full animate-ping opacity-75"
                :class="dotClass"
            />
        </div>
        <span v-if="label" class="text-xs font-medium" :class="textClass">
            {{ label }}
        </span>
        <span
            v-if="pendingCount > 0"
            class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold bg-amber-500 text-white"
        >
            {{ pendingCount > 99 ? '99+' : pendingCount }}
        </span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useNetwork } from '@/composables/useNetwork';
import { useSync } from '@/services/SyncService';

const props = withDefaults(defineProps<{
    showLabel?: boolean;
    variant?: 'badge' | 'dot' | 'full';
}>(), {
    showLabel: true,
    variant: 'full',
});

const { isOnline } = useNetwork();
const { pendingSalesCount, isSyncing, lastSyncAt } = useSync();

const show = computed(() => true);
const pendingCount = computed(() => pendingSalesCount.value);

const dotClass = computed(() => {
    if (isSyncing.value) return 'bg-blue-500';
    if (!isOnline.value) return 'bg-red-500';
    if (pendingCount.value > 0) return 'bg-amber-500';
    return 'bg-emerald-500';
});

const textClass = computed(() => {
    if (isSyncing.value) return 'text-blue-700';
    if (!isOnline.value) return 'text-red-700';
    if (pendingCount.value > 0) return 'text-amber-700';
    return 'text-emerald-700';
});

const label = computed(() => {
    if (props.variant === 'dot') return '';
    if (isSyncing.value) return 'Syncing...';
    if (!isOnline.value) return 'Offline';
    if (pendingCount.value > 0) return `${pendingCount.value} pending`;
    return 'Synced';
});

const containerClass = computed(() => {
    if (props.variant === 'badge') return 'gap-1.5';
    return 'gap-1.5';
});

const tooltip = computed(() => {
    if (isSyncing.value) return 'Syncing offline data...';
    if (!isOnline.value) return 'You are offline. Data will sync when reconnected.';
    if (pendingCount.value > 0) return `${pendingCount.value} item(s) pending sync`;
    if (lastSyncAt.value) return `Last synced: ${new Date(lastSyncAt.value).toLocaleString()}`;
    return 'All data synced';
});
</script>
