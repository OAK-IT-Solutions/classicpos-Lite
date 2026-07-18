import { ref } from 'vue';
import api from './axios';

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface CrudOptions {
    searchFields?: string[];
    defaultPerPage?: number;
}

export function useCrud<T extends Record<string, unknown>>(basePath: string, options: CrudOptions = {}) {
    const items = ref<T[]>([]);
    const item = ref<T | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const pagination = ref<PaginationMeta>({
        current_page: 1,
        last_page: 1,
        per_page: options.defaultPerPage ?? 15,
        total: 0,
    });

    async function fetchAll(page = 1, params: Record<string, string> = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get(basePath, {
                params: { page, per_page: pagination.value.per_page, ...params },
            });
            items.value = res.data.data ?? res.data;
            pagination.value = {
                current_page: res.data.current_page ?? 1,
                last_page: res.data.last_page ?? 1,
                per_page: res.data.per_page ?? pagination.value.per_page,
                total: res.data.total ?? (Array.isArray(res.data) ? res.data.length : 0),
            };
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'An error occurred';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchOne(id: string): Promise<T> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get(`${basePath}/${id}`);
            const data: T = res.data.data ?? res.data;
            item.value = data;
            return data;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'An error occurred';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function create(data: Partial<T>): Promise<T> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post(basePath, data);
            const created: T = res.data.data ?? res.data;
            items.value = [...items.value, created];
            return created;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to create';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateRecord(id: string, data: Partial<T>): Promise<T> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.put(`${basePath}/${id}`, data);
            const updated: T = res.data.data ?? res.data;
            items.value = items.value.map((item) =>
                (item as Record<string, unknown>).id === id ? updated : item
            ) as T[];
            return updated;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to update';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function destroy(id: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            await api.delete(`${basePath}/${id}`);
            items.value = items.value.filter(
                (item) => (item as Record<string, unknown>).id !== id
            );
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to delete';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        items,
        item,
        loading,
        error,
        pagination,
        fetchAll,
        fetchOne,
        create,
        update: updateRecord,
        destroy,
    };
}
