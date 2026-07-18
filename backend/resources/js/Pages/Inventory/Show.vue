<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useInventory, type StockMovement } from '@/composables/useInventory';
import { Package, ArrowLeft, TrendingUp, TrendingDown, Minus } from 'lucide-vue-next';

const { fetchMovements } = useInventory();
const page = usePage();

const inventoryId = computed(() => {
    const parts = page.url.split('/');
    return parts[parts.length - 1];
});

const movements = ref<StockMovement[]>([]);
const currentQuantity = ref(0);
const loading = ref(true);

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString();
}

function movementLabel(type: string): string {
    const labels: Record<string, string> = {
        grn: 'Goods Received',
        sale: 'Sale',
        sale_void: 'Sale Voided',
        transfer_out: 'Transferred Out',
        transfer_in: 'Transferred In',
        return: 'Return Approved',
        rollback: 'Payment Rollback',
        adjustment: 'Manual Adjustment',
        reservation: 'Reserved',
    };
    return labels[type] || type;
}

function movementIcon(type: string) {
    if (type === 'sale' || type === 'transfer_out') return TrendingDown;
    if (type === 'grn' || type === 'transfer_in' || type === 'return' || type === 'rollback' || type === 'sale_void') return TrendingUp;
    return Minus;
}

function movementColor(type: string): string {
    if (type === 'sale' || type === 'transfer_out') return 'text-red-500';
    if (type === 'grn' || type === 'transfer_in' || type === 'return' || type === 'rollback' || type === 'sale_void') return 'text-green-500';
    return 'text-text-secondary';
}

function movementRoute(m: StockMovement): string | null {
    switch (m.reference_type) {
        case 'sale':
        case 'sale_void':
        case 'rollback':
            return `/sales/${m.reference_id}`;
        case 'grn':
            return `/grn`;
        case 'transfer_out':
        case 'transfer_in':
            return `/stock-transfers`;
        case 'return':
            return `/returns`;
        default:
            return null;
    }
}

onMounted(async () => {
    if (inventoryId.value) {
        try {
            const result = await fetchMovements(inventoryId.value);
            movements.value = result.data;
            currentQuantity.value = result.current_quantity;
        } catch {
            // error handled
        } finally {
            loading.value = false;
        }
    }
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="router.visit('/inventory')"
                    class="p-2 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-lg transition-colors">
                    <ArrowLeft class="w-5 h-5" />
                </button>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <Package class="w-5 h-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">Stock Movement History</h1>
                    <p class="text-text-tertiary text-sm mt-0.5">
                        Current stock: <span class="font-semibold text-text-theme">{{ currentQuantity }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div v-if="loading" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-12 text-center">
            <p class="text-text-tertiary text-sm">Loading movement history…</p>
        </div>

        <div v-else-if="movements.length === 0" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-12 text-center">
            <p class="text-text-tertiary text-sm">No stock movements recorded yet.</p>
        </div>

        <div v-else class="space-y-3">
            <div v-for="m in movements" :key="m.id"
                @click="movementRoute(m) ? router.visit(movementRoute(m)!) : null"
                :class="['bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4 flex items-center gap-4', movementRoute(m) ? 'cursor-pointer hover:bg-surface-alt transition-colors' : '']">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-surface-alt flex items-center justify-center">
                    <component :is="movementIcon(m.reference_type)" :class="['w-5 h-5', movementColor(m.reference_type)]" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-text-theme">{{ movementLabel(m.reference_type) }}</p>
                    <p class="text-xs text-text-tertiary mt-0.5">{{ formatDate(m.created_at) }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p :class="['text-sm font-bold', m.quantity_change > 0 ? 'text-green-600' : 'text-red-600']">
                        {{ m.quantity_change > 0 ? '+' : '' }}{{ m.quantity_change }}
                    </p>
                    <p class="text-xs text-text-tertiary">Balance: {{ m.running_balance }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
