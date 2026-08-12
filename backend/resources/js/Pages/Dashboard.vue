<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';
import { useAuth } from '@/composables/useAuth';
import SyncStatusBanner from '@/Components/SyncStatusBanner.vue';

const auth = useAuth();

const loading = ref(true);
const trendDays = ref(7);

const kpis = ref({
    todayRevenue: 0,
    todayOrders: 0,
    totalSales: 0,
    totalRevenue: 0,
    totalProducts: 0,
    activeProducts: 0,
    lowStockCount: 0,
    inventoryValue: 0,
    totalCustomers: 0,
});

const recentSales = ref<any[]>([]);
const topProducts = ref<{ name: string; total_quantity: number; total_revenue: number }[]>([]);
const trend = ref<{ date: string; revenue: number }[]>([]);

function formatCurrency(val: number): string {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
}

function formatNumber(val: number): string {
    return new Intl.NumberFormat('en-US').format(val);
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString();
}

function formatShortDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

const maxTrend = ref(1);

async function fetchAll() {
    loading.value = true;
    try {
        const [sumRes, topRes, recentRes, trendRes] = await Promise.all([
            api.get('/reports/summary').catch(() => ({ data: { data: null } })),
            api.get('/reports/top-products', { params: { limit: 5 } }).catch(() => ({ data: { data: [] } })),
            api.get('/sales', { params: { per_page: 5 } }).catch(() => ({ data: { data: [] } })),
            api.get('/reports/sales-trend', { params: { days: trendDays.value } }).catch(() => ({ data: { data: [] } })),
        ]);

        const s = sumRes.data?.data;
        if (s) {
            kpis.value = {
                todayRevenue: s.sales.today_revenue,
                todayOrders: s.sales.today_sales,
                totalSales: s.sales.total_sales,
                totalRevenue: s.sales.total_revenue,
                totalProducts: s.inventory.total_products,
                activeProducts: s.inventory.active_products,
                lowStockCount: s.inventory.low_stock_count,
                inventoryValue: s.inventory.total_value,
                totalCustomers: s.customers.total,
            };
        }

        topProducts.value = topRes.data?.data ?? [];
        recentSales.value = recentRes.data?.data ?? [];
        trend.value = (trendRes.data?.data ?? []).map((d: any) => ({
            date: d.date,
            revenue: Number(d.revenue) || 0,
        }));
        maxTrend.value = Math.max(...trend.value.map(d => d.revenue), 1);
    } catch {
        //
    } finally {
        loading.value = false;
    }
}

async function reloadTrend() {
    const res = await api.get('/reports/sales-trend', { params: { days: trendDays.value } }).catch(() => ({ data: { data: [] } }));
    trend.value = (res.data?.data ?? []).map((d: any) => ({ date: d.date, revenue: Number(d.revenue) || 0 }));
    maxTrend.value = Math.max(...trend.value.map(d => d.revenue), 1);
}

onMounted(fetchAll);
</script>

<template>
    <AppLayout>
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-text-theme">Dashboard</h1>
                <p class="text-text-secondary mt-1">Welcome back, {{ auth.user?.name }}</p>
            </div>
            <SyncStatusBanner />
        </div>

        <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading dashboard...</div>

        <template v-else>
            <!-- Row 1: Critical KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium uppercase tracking-wide">Today's Revenue</p>
                    <p class="text-2xl font-bold text-text-theme mt-1">{{ formatCurrency(kpis.todayRevenue) }}</p>
                    <p class="text-sm text-text-tertiary mt-0.5">{{ kpis.todayOrders }} orders today</p>
                </div>

                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium uppercase tracking-wide">Total Sales</p>
                    <p class="text-2xl font-bold text-text-theme mt-1">{{ formatNumber(kpis.totalSales) }}</p>
                    <p class="text-sm text-success-theme mt-0.5">{{ formatCurrency(kpis.totalRevenue) }} total revenue</p>
                </div>

                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium uppercase tracking-wide">Low Stock Items</p>
                    <p class="text-2xl font-bold" :class="kpis.lowStockCount > 0 ? 'text-red-600' : 'text-text-theme'">{{ kpis.lowStockCount }}</p>
                    <p class="text-sm text-text-tertiary mt-0.5">{{ kpis.activeProducts }} active products</p>
                </div>

                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <p class="text-xs text-text-tertiary font-medium uppercase tracking-wide">Inventory Value</p>
                    <p class="text-2xl font-bold text-text-theme mt-1">{{ formatCurrency(kpis.inventoryValue) }}</p>
                    <p class="text-sm text-text-tertiary mt-0.5">{{ formatNumber(kpis.totalCustomers) }} customers</p>
                </div>
            </div>

            <!-- Row 2: Sales Trend + Top Products -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-2 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-text-theme">Sales Trend</h2>
                        <div class="flex items-center gap-1">
                            <button v-for="d in [7, 30, 90]" :key="d"
                                @click="trendDays = d; reloadTrend()"
                                class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors"
                                :class="trendDays === d ? 'bg-primary text-white' : 'bg-surface-alt text-text-secondary hover:bg-border-light'">
                                {{ d }}d
                            </button>
                        </div>
                    </div>
                    <div v-if="trend.length" class="flex items-end gap-1" style="height: 140px;">
                        <div v-for="t in trend" :key="t.date" class="flex-1 flex flex-col items-center justify-end h-full">
                            <div class="w-full bg-primary rounded-t transition-all group relative cursor-pointer"
                                :style="{ height: Math.max((t.revenue / maxTrend) * 120, 3) + 'px' }">
                                <div class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs rounded px-2 py-0.5 opacity-0 group-hover:opacity-100 whitespace-nowrap pointer-events-none">
                                    {{ formatCurrency(t.revenue) }}
                                </div>
                            </div>
                            <span class="text-[10px] text-text-tertiary mt-1 truncate w-full text-center">{{ formatShortDate(t.date) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-text-tertiary py-8 text-center">No sales data yet.</p>
                </div>

                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Top Products</h2>
                    <div v-if="topProducts.length">
                        <div v-for="(p, i) in topProducts" :key="p.name"
                            class="flex items-center justify-between py-2 border-b border-border-light last:border-0">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-xs font-medium text-text-tertiary w-4 flex-shrink-0">#{{ i + 1 }}</span>
                                <span class="text-sm text-text-theme truncate">{{ p.name }}</span>
                            </div>
                            <div class="text-right flex-shrink-0 ml-2">
                                <p class="text-sm font-medium text-text-theme">{{ formatNumber(p.total_quantity) }}</p>
                                <p class="text-xs text-text-tertiary">{{ formatCurrency(p.total_revenue) }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-text-tertiary py-8 text-center">No product data yet.</p>
                </div>
            </div>

            <!-- Row 3: Recent Sales + Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Recent Sales</h2>
                    <div v-if="recentSales.length === 0" class="text-sm text-text-tertiary py-4 text-center">No sales yet.</div>
                    <div v-else class="space-y-2">
                        <div v-for="sale in recentSales" :key="sale.id"
                            @click="router.visit(`/sales/${sale.id}`)"
                            class="flex items-center justify-between py-2.5 px-3 -mx-3 rounded-lg cursor-pointer hover:bg-surface-alt transition-colors border-b border-border-light last:border-0">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-text-theme truncate">{{ sale.invoice_number }}</p>
                                <p class="text-xs text-text-tertiary">{{ formatDate(sale.created_at) }}</p>
                            </div>
                            <p class="text-sm font-semibold text-text-theme flex-shrink-0 ml-2">{{ formatCurrency(sale.total_amount) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Business Overview</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-surface-alt rounded-xl p-4">
                            <p class="text-xs text-text-tertiary font-medium">Active Products</p>
                            <p class="text-xl font-bold text-text-theme mt-1">{{ formatNumber(kpis.activeProducts) }}</p>
                            <p class="text-xs text-text-tertiary">{{ formatNumber(kpis.totalProducts) }} total</p>
                        </div>
                        <div class="bg-surface-alt rounded-xl p-4">
                            <p class="text-xs text-text-tertiary font-medium">Total Customers</p>
                            <p class="text-xl font-bold text-text-theme mt-1">{{ formatNumber(kpis.totalCustomers) }}</p>
                            <p class="text-xs text-text-tertiary">registered</p>
                        </div>
                        <div class="bg-surface-alt rounded-xl p-4">
                            <p class="text-xs text-text-tertiary font-medium">Total Revenue</p>
                            <p class="text-xl font-bold text-text-theme mt-1">{{ formatCurrency(kpis.totalRevenue) }}</p>
                            <p class="text-xs text-text-tertiary">{{ formatNumber(kpis.totalSales) }} transactions</p>
                        </div>
                        <div class="bg-surface-alt rounded-xl p-4">
                            <p class="text-xs text-text-tertiary font-medium">Inventory Value</p>
                            <p class="text-xl font-bold text-text-theme mt-1">{{ formatCurrency(kpis.inventoryValue) }}</p>
                            <p class="text-xs text-text-tertiary">stock on hand</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 4: Quick Actions -->
            <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                <h2 class="text-lg font-semibold text-text-theme mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <button @click="router.visit('/pos')" class="flex flex-col items-center gap-2 p-4 bg-primary-light rounded-xl hover:opacity-80 transition-colors">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        <span class="text-xs font-medium text-primary">New Sale</span>
                    </button>
                    <button @click="router.visit('/products')" class="flex flex-col items-center gap-2 p-4 bg-success-light rounded-xl hover:opacity-80 transition-colors">
                        <svg class="w-6 h-6 text-success-theme" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span class="text-xs font-medium text-success-theme">Products</span>
                    </button>
                    <button @click="router.visit('/customers')" class="flex flex-col items-center gap-2 p-4 bg-blue-100 rounded-xl hover:opacity-80 transition-colors">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-xs font-medium text-blue-600">Customers</span>
                    </button>
                    <button @click="router.visit('/reports')" class="flex flex-col items-center gap-2 p-4 bg-warning-light rounded-xl hover:opacity-80 transition-colors">
                        <svg class="w-6 h-6 text-warning-theme" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="text-xs font-medium text-warning-theme">Reports</span>
                    </button>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
