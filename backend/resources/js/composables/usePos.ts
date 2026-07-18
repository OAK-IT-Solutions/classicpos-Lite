import { ref } from 'vue';
import api from '@/composables/axios';
import { db, CachedConfig } from '@/services/OfflineDB';
import { addPendingSale, syncPendingSales } from '@/services/SyncService';
import { useNetwork } from '@/composables/useNetwork';

export interface PosProduct {
    id: string;
    name: string;
    barcode: string;
    price: number;
    category: string;
    stock_uom: string;
    stock: number;
    image?: string | null;
}

export interface CheckoutPayload {
    branch_id: string;
    customer_id?: string;
    items: { product_id: string; quantity: number; price: number }[];
    payment_method: string;
    gateway?: string;
    promo_code?: string;
    tax_profile_id?: string;
    loyalty_points_redeemed?: number;
}

export interface SaleResult {
    sale_id: string;
    invoice_number: string;
    subtotal: number;
    discount: number;
    tax_amount: number;
    total_amount: number;
    loyalty_discount?: number;
    payment_method: string;
    status: string;
}

export interface OfflineSaleResult extends SaleResult {
    offline: true;
    local_id: string;
}

export interface CategoryInfo {
    id: string;
    name: string;
    parent_id: string | null;
}

export interface CustomerLookup {
    id: string;
    name: string;
    phone: string;
    email: string;
    loyalty_points: number;
    member_level: string;
}

export interface HeldSale {
    id: string;
    branch_id: string;
    user_id: string;
    customer: { id: string; name: string } | null;
    cart_data: any;
    promo_code: string | null;
    tax_profile_id: string | null;
    loyalty_points_redeemed: number;
    note: string | null;
    created_at: string;
    offline?: boolean;
}

function generateLocalId(): string {
    return 'offline-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
}

function generateOfflineInvoice(): string {
    const ts = new Date();
    const yy = String(ts.getFullYear()).slice(-2);
    const mm = String(ts.getMonth() + 1).padStart(2, '0');
    const dd = String(ts.getDate()).padStart(2, '0');
    const random = Math.random().toString(36).slice(2, 6).toUpperCase();
    return `OFF-${yy}${mm}${dd}-${random}`;
}

export function usePos() {
    const products = ref<PosProduct[]>([]);
    const categories = ref<CategoryInfo[]>([]);
    const loading = ref(false);
    const heldSales = ref<HeldSale[]>([]);

    async function fetchProducts(branchId: string, params?: { category?: string; search?: string }) {
        loading.value = true;
        try {
            const res = await api.get('/pos/products', {
                params: { branch_id: branchId, ...params },
            });
            const fetchedProducts: PosProduct[] = res.data.data || [];
            const fetchedCategories: CategoryInfo[] = res.data.categories || [];

            products.value = fetchedProducts;
            categories.value = fetchedCategories;

            // Cache for offline use
            try {
                const now = Date.now();
                await db.products.bulkPut(fetchedProducts.map(p => ({
                    id: p.id,
                    branch_id: branchId,
                    name: p.name,
                    barcode: p.barcode,
                    price: p.price,
                    category: p.category,
                    stock_uom: p.stock_uom,
                    stock: p.stock,
                    image: p.image,
                    synced_at: now,
                })));
                await db.categories.bulkPut(fetchedCategories.map(c => ({
                    id: c.id,
                    name: c.name,
                    parent_id: c.parent_id,
                    synced_at: now,
                })));
            } catch (cacheErr) {
                console.warn('[POS] Failed to cache products for offline:', cacheErr);
            }
        } catch (err) {
            console.error('fetchProducts failed:', err);
            // Try to load from offline cache
            try {
                const cachedProducts = await db.products.where('branch_id').equals(branchId).toArray();
                const cachedCategories = await db.categories.toArray();
                if (cachedProducts.length > 0) {
                    products.value = cachedProducts.map(p => ({
                        id: p.id,
                        name: p.name,
                        barcode: p.barcode || '',
                        price: p.price,
                        category: p.category,
                        stock_uom: p.stock_uom,
                        stock: p.stock,
                        image: p.image,
                    }));
                    categories.value = cachedCategories.map(c => ({
                        id: c.id,
                        name: c.name,
                        parent_id: c.parent_id,
                    }));
                    console.log('[POS] Loaded products from offline cache');
                } else {
                    products.value = [];
                    categories.value = [];
                }
            } catch {
                products.value = [];
                categories.value = [];
            }
        } finally {
            loading.value = false;
        }
    }

    async function completeSale(payload: CheckoutPayload, options?: {
        cashReceived?: number;
        changeDue?: number;
        itemDetails?: { product_id: string; product_name: string; quantity: number; price: number }[];
    }): Promise<SaleResult | OfflineSaleResult> {
        const { isOnline } = useNetwork();

        // If offline, save to IndexedDB and return synthetic result
        if (!isOnline.value) {
            return await saveSaleOffline(payload, options);
        }

        try {
            const res = await api.post('/sales', payload);
            return res.data;
        } catch (err: any) {
            // If network error (offline), fall back to offline storage
            const isNetworkError = !err.response || err.code === 'ERR_NETWORK' || err.message?.includes('Network Error');
            if (isNetworkError) {
                console.warn('[POS] Online check failed, saving offline:', err.message);
                return await saveSaleOffline(payload, options);
            }
            throw err;
        }
    }

    async function saveSaleOffline(
        payload: CheckoutPayload,
        options?: {
            cashReceived?: number;
            changeDue?: number;
            itemDetails?: { product_id: string; product_name: string; quantity: number; price: number }[];
        }
    ): Promise<OfflineSaleResult> {
        const localId = generateLocalId();
        const invoice = generateOfflineInvoice();

        const subtotal = payload.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
        const total = subtotal; // server will recalculate; this is just a placeholder for receipt

        const enrichedItems = (options?.itemDetails || payload.items).map((item: any) => ({
            product_id: item.product_id,
            product_name: item.product_name || item.name || '',
            quantity: item.quantity,
            price: item.price,
        }));

        const pending = await addPendingSale({
            local_id: localId,
            branch_id: payload.branch_id,
            customer_id: payload.customer_id || null,
            items: enrichedItems,
            payment_method: payload.payment_method,
            gateway: payload.gateway,
            promo_code: payload.promo_code,
            tax_profile_id: payload.tax_profile_id,
            loyalty_points_redeemed: payload.loyalty_points_redeemed,
            subtotal: subtotal,
            discount: 0,
            tax_amount: 0,
            total_amount: total,
            cash_received: options?.cashReceived ?? null,
            change_due: options?.changeDue ?? null,
        });

        return {
            sale_id: localId,
            invoice_number: invoice,
            subtotal: subtotal,
            discount: 0,
            tax_amount: 0,
            total_amount: total,
            payment_method: payload.payment_method,
            status: 'pending_sync',
            offline: true,
            local_id: localId,
        };
    }

    async function validatePromo(code: string): Promise<any> {
        try {
            const res = await api.get('/promotions', {
                params: { search: code, per_page: 1 },
            });
            const items = res.data.data || [];
            return items.find((p: any) => p.code === code && p.is_active) || null;
        } catch (err) {
            console.warn('[POS] Promo validation failed (offline?):', err);
            return null;
        }
    }

    async function fetchTaxProfiles(): Promise<any[]> {
        try {
            const res = await api.get('/tax-profiles', { params: { per_page: 50 } });
            return res.data.data || [];
        } catch (err) {
            console.warn('[POS] Tax profiles fetch failed (offline?):', err);
            return [];
        }
    }

    async function searchCustomers(query: string): Promise<CustomerLookup[]> {
        const params: Record<string, string | number> = { per_page: 20 };
        if (query && query.length >= 1) params.search = query;
        try {
            const res = await api.get('/customers', { params });
            const list = (res.data.data || []).map((c: any) => ({
                id: c.id,
                name: c.name,
                phone: c.phone,
                email: c.email,
                loyalty_points: c.loyalty_points || 0,
                member_level: c.member_level || 'bronze',
            }));
            // Cache for offline
            try {
                const now = Date.now();
                await db.customers.bulkPut(list.map((c: any) => ({ ...c, synced_at: now })));
            } catch {}
            return list;
        } catch (err) {
            // Try offline cache
            try {
                const cached = await db.customers.toArray();
                if (query) {
                    const q = query.toLowerCase();
                    return cached
                        .filter(c =>
                            c.name.toLowerCase().includes(q) ||
                            c.phone?.includes(query) ||
                            c.email?.toLowerCase().includes(q)
                        )
                        .slice(0, 20);
                }
                return cached.slice(0, 20);
            } catch {
                return [];
            }
        }
    }

    async function holdSale(payload: {
        branch_id: string;
        cart_data: any;
        customer_id?: string;
        promo_code?: string;
        tax_profile_id?: string;
        loyalty_points_redeemed?: number;
        note?: string;
    }): Promise<{ id: string; offline?: boolean }> {
        if (!navigator.onLine) {
            // Save hold sale locally with a synthetic ID
            const localHoldId = 'hold-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
            try {
                await db.config.put({
                    key: 'hold:' + localHoldId,
                    value: payload,
                    updated_at: Date.now(),
                });
            } catch (err) {
                console.warn('[POS] Failed to save held sale locally:', err);
                throw new Error('Cannot hold sale while offline');
            }
            return { id: localHoldId, offline: true };
        }
        const res = await api.post('/pos/hold', payload);
        return { id: res.data.data?.id || res.data.id, offline: false };
    }

    async function fetchHeldSales(branchId: string): Promise<void> {
        try {
            const res = await api.get('/pos/held', {
                params: { branch_id: branchId },
            });
            heldSales.value = res.data.data || [];
        } catch {
            // Try to load offline holds
            try {
                const offlineHolds = await db.config
                    .where('key').startsWith('hold:')
                    .toArray();
                heldSales.value = offlineHolds.map((h: CachedConfig) => ({
                    id: h.key.replace('hold:', ''),
                    branch_id: h.value.branch_id,
                    user_id: '',
                    customer: null,
                    cart_data: h.value.cart_data,
                    promo_code: h.value.promo_code,
                    tax_profile_id: h.value.tax_profile_id,
                    loyalty_points_redeemed: h.value.loyalty_points_redeemed || 0,
                    note: h.value.note,
                    created_at: new Date(h.updated_at).toISOString(),
                    offline: true,
                }));
            } catch {
                heldSales.value = [];
            }
        }
    }

    async function resumeHeldSale(id: string): Promise<any> {
        // Check if this is an offline hold
        if (typeof id === 'string' && id.startsWith('hold-')) {
            try {
                const stored = await db.config.get('hold:' + id);
                if (stored?.value) {
                    return stored.value;
                }
            } catch (err) {
                console.warn('[POS] Failed to load offline hold:', err);
            }
            throw new Error('Offline hold sale not found');
        }
        const res = await api.post(`/pos/resume/${id}`);
        return res.data.data;
    }

    return {
        products,
        categories,
        loading,
        heldSales,
        fetchProducts,
        completeSale,
        validatePromo,
        fetchTaxProfiles,
        searchCustomers,
        holdSale,
        fetchHeldSales,
        resumeHeldSale,
        syncPendingSales,
    };
}
