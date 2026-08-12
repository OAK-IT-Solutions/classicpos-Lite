import { ref } from 'vue';
import api from './axios';

export interface Customer {
    id: string;
    name: string;
    phone: string;
    email: string | null;
    location?: string | null;
    member_level: 'bronze' | 'silver' | 'gold' | 'platinum';
    loyalty_points: number;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export function useCustomers() {
    const customers = ref<Customer[]>([]);
    const loading = ref<boolean>(false);
    const error = ref<string | null>(null);
    const pagination = ref<PaginationMeta>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    async function fetchCustomers(page = 1, params: Record<string, string> = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get('/customers', {
                params: { page, ...params },
            });
            // Laravel paginator: res.data.data holds items, meta at top level
            customers.value = res.data.data as Customer[];
            pagination.value = {
                current_page: res.data.current_page,
                last_page: res.data.last_page,
                per_page: res.data.per_page,
                total: res.data.total,
            };
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to fetch customers';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createCustomer(
        data: Omit<Customer, 'id' | 'member_level' | 'loyalty_points'>
    ): Promise<Customer> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post('/customers', data);
            const newCustomer = res.data.data as Customer;
            customers.value.push(newCustomer);
            return newCustomer;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to create customer';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateCustomer(id: string, data: Partial<Customer>): Promise<Customer> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.put(`/customers/${id}`, data);
            const updated = res.data.data as Customer;
            const index = customers.value.findIndex((c) => c.id === id);
            if (index !== -1) {
                customers.value[index] = updated;
            }
            return updated;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to update customer';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function deleteCustomer(id: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            await api.delete(`/customers/${id}`);
            customers.value = customers.value.filter((c) => c.id !== id);
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to delete customer';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        customers,
        loading,
        error,
        pagination,
        fetchCustomers,
        createCustomer,
        updateCustomer,
        deleteCustomer,
    };
}
