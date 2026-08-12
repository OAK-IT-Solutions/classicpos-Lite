import { ref } from 'vue';
import api from './axios';

export interface InventoryItem {
    id: string;
    product_id: string;
    product_name: string | null;
    warehouse_id: string;
    quantity: number;
    batch_number: string | null;
    expiry_date: string | null;
    serial_number: string | null;
}

export interface StockMovement {
    id: string;
    quantity_change: number;
    running_balance: number;
    reference_type: string;
    reference_id: string | null;
    reason: string | null;
    created_at: string;
}

export function useInventory() {
    const items = ref<InventoryItem[]>([]);
    const loading = ref<boolean>(false);
    const error = ref<string | null>(null);

    async function fetchStock(branchId: string, params: Record<string, string> = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get('/inventory/stock', {
                params: { branch_id: branchId, ...params },
            });
            items.value = res.data.data;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to fetch inventory';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function adjustQuantity(
        productId: string,
        warehouseId: string,
        branchId: string,
        delta: number,
    ): Promise<unknown> {
        error.value = null;
        try {
            const res = await api.put('/inventory/update', {
                branch_id: branchId,
                updates: [
                    {
                        product_id: productId,
                        warehouse_id: warehouseId,
                        quantity: delta,
                    },
                ],
            });
            return res.data;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to adjust quantity';
            error.value = message;
            throw err;
        }
    }

    async function fetchMovements(inventoryId: string): Promise<{ data: StockMovement[]; current_quantity: number }> {
        const res = await api.get(`/inventory/${inventoryId}/movements`);
        return res.data;
    }

    return {
        items,
        loading,
        error,
        fetchStock,
        adjustQuantity,
        fetchMovements,
    };
}
