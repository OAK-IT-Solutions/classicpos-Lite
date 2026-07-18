import { ref } from 'vue';
import api from './axios';

export interface Sale {
    id: string;
    invoice_number: string;
    total_amount: number;
    payment_method: string;
    status: string;
    created_at: string;
}

export interface SaleDetail extends Sale {
    branch_id: string;
    customer: { id: string; name: string } | null;
    items: Array<{ product_id: string; name: string; qty: number; price: number }>;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export function useSales() {
    const sales = ref<Sale[]>([]);
    const saleDetail = ref<SaleDetail | null>(null);
    const loading = ref<boolean>(false);
    const error = ref<string | null>(null);
    const pagination = ref<PaginationMeta>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    async function fetchSales(page = 1, params: Record<string, string> = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get('/sales', {
                params: { page, ...params },
            });
            sales.value = res.data.data;
            pagination.value = {
                current_page: res.data.current_page,
                last_page: res.data.last_page,
                per_page: res.data.per_page,
                total: res.data.total,
            };
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'An error occurred';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchSaleDetail(id: string): Promise<SaleDetail> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get(`/sales/${id}`);
            saleDetail.value = res.data;
            return res.data;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'An error occurred';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function voidSale(id: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            await api.post(`/sales/${id}/void`);
            if (saleDetail.value) {
                saleDetail.value.status = 'voided';
            }
            const sale = sales.value.find(s => s.id === id);
            if (sale) {
                sale.status = 'voided';
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to void sale';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        sales,
        saleDetail,
        loading,
        error,
        pagination,
        fetchSales,
        fetchSaleDetail,
        voidSale,
    };
}
