<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useInventory } from '@/composables/useInventory';
import { useAuth } from '@/composables/useAuth';
import api from '@/composables/axios';
import { Package, Eye, AlertCircle } from 'lucide-vue-next';

const { items, loading, error, fetchStock } = useInventory();
const auth = useAuth();

const searchQuery = ref('');
const filterLowStock = ref(false);
const filterWarehouseId = ref('');
const warehouses = ref<{ id: string; name: string }[]>([]);

onMounted(async () => {
    const branchId = auth.user.value?.branch_id || auth.user.value?.branch?.id;
    if (branchId) {
        await Promise.all([
            fetchStock(branchId),
            fetchWarehouses(branchId),
        ]);
    }
});

async function fetchWarehouses(branchId: string) {
    try {
        const res = await api.get('/warehouses', {
            params: { branch_id: branchId, per_page: 50 },
        });
        warehouses.value = (res.data.data || []).map((w: any) => ({
            id: w.id,
            name: w.name,
        }));
    } catch {
        warehouses.value = [];
    }
}

let debounceTimer: ReturnType<typeof setTimeout>;

watch([searchQuery, filterLowStock, filterWarehouseId], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
});

function applyFilters() {
    const branchId = auth.user.value?.branch_id || auth.user.value?.branch?.id;
    if (!branchId) return;
    const params: Record<string, string> = {};
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (filterLowStock.value) params.low_stock = '1';
    if (filterWarehouseId.value) params.warehouse_id = filterWarehouseId.value;
    fetchStock(branchId, params);
}
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <Package class="w-6 h-6 text-primary" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-text-theme">Inventory</h1>
                <p class="text-sm text-text-tertiary">Stock levels across all warehouses — click a row for movement history</p>
            </div>
        </div>

        <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4 mb-6">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-text-secondary mb-1">Search Product</label>
                    <input v-model="searchQuery" type="text" placeholder="Search by product name..."
                        class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                        @keydown.enter="applyFilters" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">Warehouse</label>
                    <select v-model="filterWarehouseId"
                        class="px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                        @change="applyFilters">
                        <option value="">All Warehouses</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">&nbsp;</label>
                    <label class="flex items-center gap-2 px-3 py-2 border border-border-input rounded-lg cursor-pointer hover:bg-surface-alt">
                        <input v-model="filterLowStock" type="checkbox"
                            class="rounded border-border-input text-primary focus:ring-primary-ring"
                            @change="applyFilters" />
                        <span class="text-sm text-text-secondary">Low stock only</span>
                    </label>
                </div>
                <button @click="applyFilters"
                    class="px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                    Filter
                </button>
                <button v-if="searchQuery || filterLowStock"
                    @click="searchQuery = ''; filterLowStock = false; filterWarehouseId = ''; applyFilters()"
                    class="px-3 py-2 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors">
                    Clear
                </button>
            </div>
        </div>

        <div v-if="error" class="mb-4 flex items-start gap-3 rounded-lg border border-danger-theme/20 bg-danger-light p-4 text-danger-theme">
            <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0" />
            <span class="text-sm">{{ error }}</span>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-16 text-text-tertiary">
            <span class="text-sm">Loading inventory…</span>
        </div>

        <div v-else-if="items.length > 0" class="overflow-hidden rounded-xl border border-border-theme bg-surface-raised shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-surface-alt">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-text-tertiary">Product Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-text-tertiary">Warehouse</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-text-tertiary">Quantity</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-text-tertiary">Batch Number</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-text-tertiary">Expiry Date</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-text-tertiary">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in items" :key="`${item.product_id}-${item.warehouse_id}`"
                        class="transition-colors hover:bg-surface-alt cursor-pointer"
                        @click="router.visit(`/inventory/${item.id}`)">
                        <td class="px-6 py-4 text-sm font-medium text-text-theme">{{ item.product_name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-text-secondary">{{ warehouses.find(w => w.id === item.warehouse_id)?.name || item.warehouse_id.substring(0, 8) }}</td>
                        <td class="px-6 py-4 text-sm text-text-theme">{{ item.quantity }}</td>
                        <td class="px-6 py-4 text-sm text-text-secondary">{{ item.batch_number ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-text-secondary">{{ item.expiry_date ?? '—' }}</td>
                        <td class="px-6 py-4 text-right">
                            <button @click.stop="router.visit(`/inventory/${item.id}`)"
                                class="inline-flex items-center gap-1.5 rounded-md border border-border-input bg-surface-raised px-3 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:bg-surface-alt"
                                title="View movements">
                                <Eye class="h-3.5 w-3.5" />
                                Movements
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border-input bg-surface-raised py-16 text-center">
            <Package class="mb-3 h-12 w-12 text-gray-300" />
            <p class="text-sm font-medium text-text-tertiary">No inventory items found</p>
            <p class="mt-1 text-xs text-text-tertiary">Stock will appear here once your branch has inventory.</p>
        </div>
    </AppLayout>
</template>
