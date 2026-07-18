import { ref } from 'vue';
import api from '@/composables/axios';

export interface FiscalLog {
    id: string;
    branch_id: string;
    sale_id: string | null;
    efris_invoice_no: string | null;
    efris_fdn: string | null;
    efris_qr_code: string | null;
    efris_verification_code: string | null;
    status: 'pending' | 'success' | 'failed' | 'offline_queued';
    error_message: string | null;
    retry_count: number;
    created_at: string;
    sale?: { id: string; invoice_number: string; total: number };
}

export function useEfris() {
    const fiscalLogs = ref<FiscalLog[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });

    async function fiscalizeSale(saleId: string) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post(`/efris/fiscalize/${saleId}`);
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Fiscalization failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function queryInvoices(params: {
        invoiceKind: string;
        pageNo: string;
        pageSize: string;
        startDate?: string;
        endDate?: string;
        buyerLegalName?: string;
    }) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post('/efris/invoices/query', params);
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Query failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function getInvoiceDetails(invoiceNo: string) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get(`/efris/invoices/${invoiceNo}`);
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Failed to get invoice details';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function applyCreditNote(data: {
        oriInvoiceNo: string;
        reasonCode: string;
        reason: string;
        sellersReferenceNo: string;
    }) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post('/efris/credit-note', { generalInfo: data });
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Credit note failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function syncProducts(filters?: { goodsCode?: string; goodsName?: string; pageSize?: string; pageNo?: string }) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post('/efris/products/sync', filters || {});
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Product sync failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function registerProducts(products: any[]) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post('/efris/products/register', { products });
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Product registration failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function getRegistrationDetails() {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get('/efris/registration');
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Failed to get registration details';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchFiscalLogs(page = 1) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.get('/efris/logs', { params: { page } });
            fiscalLogs.value = res.data.data;
            pagination.value = {
                current_page: res.data.current_page,
                last_page: res.data.last_page,
                per_page: res.data.per_page,
                total: res.data.total,
            };
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Failed to fetch fiscal logs';
        } finally {
            loading.value = false;
        }
    }

    async function searchTaxpayer(searchTin: string) {
        loading.value = true;
        error.value = null;
        try {
            const res = await api.post('/efris/taxpayer/search', { search_tin: searchTin });
            return res.data;
        } catch (e: any) {
            error.value = e.response?.data?.error?.message || 'Taxpayer search failed';
            throw e;
        } finally {
            loading.value = false;
        }
    }

    return {
        fiscalLogs,
        loading,
        error,
        pagination,
        fiscalizeSale,
        queryInvoices,
        getInvoiceDetails,
        applyCreditNote,
        syncProducts,
        registerProducts,
        getRegistrationDetails,
        fetchFiscalLogs,
        searchTaxpayer,
    };
}
