import Dexie, { Table } from 'dexie';

export interface CachedProduct {
    id: string;
    branch_id: string;
    name: string;
    barcode: string | null;
    price: number;
    category: string;
    category_name?: string;
    stock_uom: string;
    stock: number;
    image?: string | null;
    synced_at: number;
}

export interface CachedCategory {
    id: string;
    name: string;
    parent_id: string | null;
    synced_at: number;
}

export interface CachedCustomer {
    id: string;
    name: string;
    phone: string;
    email: string;
    loyalty_points: number;
    member_level: string;
    synced_at: number;
}

export interface PendingSale {
    id: string;
    local_id: string;
    branch_id: string;
    customer_id: string | null;
    items: {
        product_id: string;
        product_name: string;
        quantity: number;
        price: number;
    }[];
    payment_method: string;
    gateway?: string;
    promo_code?: string;
    tax_profile_id?: string;
    loyalty_points_redeemed?: number;
    subtotal: number;
    discount: number;
    tax_amount: number;
    total_amount: number;
    cash_received: number | null;
    change_due: number | null;
    status: 'pending_sync' | 'syncing' | 'synced' | 'failed';
    server_id: string | null;
    server_invoice_number: string | null;
    error_message: string | null;
    retry_count: number;
    created_at: number;
    updated_at: number;
}

export interface PendingPayment {
    id: string;
    local_id: string;
    sale_local_id: string;
    amount: number;
    method: string;
    gateway?: string;
    txn_id?: string;
    status: 'pending_sync' | 'syncing' | 'synced' | 'failed';
    error_message: string | null;
    retry_count: number;
    created_at: number;
    updated_at: number;
}

export interface CachedConfig {
    key: string;
    value: any;
    updated_at: number;
}

export interface SyncLogEntry {
    id?: number;
    type: 'sale' | 'payment' | 'config' | 'manual';
    direction: 'push' | 'pull';
    status: 'success' | 'failed' | 'partial';
    items_count: number;
    error_message: string | null;
    started_at: number;
    finished_at: number | null;
}

export interface CartSnapshot {
    id: string;
    cart: any[];
    customer: any | null;
    promo_code: string;
    tax_profile_id: string;
    loyalty_points_to_redeem: number;
    payment_method: string;
    gateway: string;
    updated_at: number;
}

class ClassicPOSDatabase extends Dexie {
    products!: Table<CachedProduct, string>;
    categories!: Table<CachedCategory, string>;
    customers!: Table<CachedCustomer, string>;
    pendingSales!: Table<PendingSale, string>;
    pendingPayments!: Table<PendingPayment, string>;
    config!: Table<CachedConfig, string>;
    syncLog!: Table<SyncLogEntry, number>;
    cartSnapshots!: Table<CartSnapshot, string>;

    constructor() {
        super('ClassicPOS_Offline_DB');

        this.version(1).stores({
            products: 'id, branch_id, category, barcode, name, synced_at',
            categories: 'id, parent_id, name, synced_at',
            customers: 'id, name, phone, email, synced_at',
            pendingSales: 'id, local_id, branch_id, status, created_at, server_id',
            pendingPayments: 'id, local_id, sale_local_id, status, created_at',
            config: 'key, updated_at',
            syncLog: '++id, type, direction, status, started_at',
            cartSnapshots: 'id, updated_at',
        });
    }

    async clearAll(): Promise<void> {
        await Promise.all([
            this.products.clear(),
            this.categories.clear(),
            this.customers.clear(),
            this.pendingSales.clear(),
            this.pendingPayments.clear(),
            this.config.clear(),
            this.syncLog.clear(),
            this.cartSnapshots.clear(),
        ]);
    }

    async getPendingSalesCount(): Promise<number> {
        return await this.pendingSales
            .where('status')
            .anyOf('pending_sync', 'failed')
            .count();
    }

    async getAllPendingSales(): Promise<PendingSale[]> {
        return await this.pendingSales
            .where('status')
            .anyOf('pending_sync', 'failed')
            .toArray();
    }
}

let dbInstance: ClassicPOSDatabase | null = null;

export function getDB(): ClassicPOSDatabase {
    if (!dbInstance) {
        dbInstance = new ClassicPOSDatabase();
    }
    return dbInstance;
}

export const db = getDB();
export type { ClassicPOSDatabase };
