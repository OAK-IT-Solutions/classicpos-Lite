import { ref } from 'vue';
import api from './axios';

export interface Category {
    id: string;
    name: string;
}

export interface Product {
    id: string;
    name: string;
    barcode: string;
    category: string;
    category_id: string | null;
    price: number;
    cost?: number;
    stock_uom: string;
    min_stock: number;
    is_active: boolean;
    image?: string | null;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export function useProducts() {
    const products = ref<Product[]>([]);
    const categories = ref<Category[]>([]);
    const loading = ref<boolean>(false);
    const error = ref<string | null>(null);
    const pagination = ref<PaginationMeta>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    async function fetchProducts(page = 1, params: Record<string, string> = {}): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get('/products', {
                params: { page, per_page: 15, ...params },
            });
            products.value = res.data.data;
            pagination.value = {
                current_page: res.data.current_page,
                last_page: res.data.last_page,
                per_page: res.data.per_page,
                total: res.data.total,
            };
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to fetch products';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchCategories(): Promise<void> {
        try {
            const res = await api.get('/categories');
            categories.value = res.data.data;
        } catch {
            // Silently fail - categories are optional
        }
    }

    async function createCategory(name: string): Promise<Category> {
        const res = await api.post('/categories', { name });
        const newCategory = res.data.data;
        categories.value.push(newCategory);
        return newCategory;
    }

    async function createProduct(data: Omit<Product, 'id'>): Promise<Product> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post('/products', data);
            const newProduct: Product = res.data.data ?? res.data;
            products.value = [...products.value, newProduct];
            return newProduct;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to create product';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateProduct(id: string, data: Partial<Product>): Promise<Product> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.put(`/products/${id}`, data);
            const updated: Product = res.data.data ?? res.data;
            products.value = products.value.map((p) => (p.id === id ? updated : p));
            return updated;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to update product';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function uploadProductImage(id: string, file: File): Promise<Product> {
        loading.value = true;
        error.value = null;
        try {
            const formData = new FormData();
            formData.append('image', file);
            const res = await api.post(`/products/${id}/upload-image`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            const updated: Product = res.data.data ?? res.data;
            products.value = products.value.map((p) => (p.id === id ? updated : p));
            return updated;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to upload image';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function deleteProductImage(id: string): Promise<Product> {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.delete(`/products/${id}/image`);
            const updated: Product = res.data.data ?? res.data;
            products.value = products.value.map((p) => (p.id === id ? updated : p));
            return updated;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to delete image';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        products,
        categories,
        loading,
        error,
        pagination,
        fetchProducts,
        fetchCategories,
        createCategory,
        createProduct,
        updateProduct,
        uploadProductImage,
        deleteProductImage,
    };
}