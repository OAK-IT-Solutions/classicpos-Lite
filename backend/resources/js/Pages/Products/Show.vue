<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';
import { Package, ArrowLeft, DollarSign, ShoppingCart, Building2, Barcode } from 'lucide-vue-next';

const page = usePage();

const productId = computed(() => {
    const parts = page.url.split('/');
    return parts[parts.length - 1];
});

const product = ref<any>(null);
const loading = ref(true);

const margin = computed(() => {
    if (!product.value) return 0;
    const p = product.value;
    if (p.cost && p.cost > 0) return ((p.price - p.cost) / p.cost * 100);
    return 0;
});

const totalStock = computed(() => {
    if (!product.value?.inventory) return 0;
    return product.value.inventory.reduce((sum: number, i: any) => sum + i.quantity, 0);
});

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString();
}

function printBarcode() {
    if (!product.value?.barcode) return
    const p = product.value
    const JsBarcode = (window as any).JsBarcode
    const win = window.open('', '_blank', 'width=400,height=300')
    if (!win) return
    win.document.write(`<!DOCTYPE html><html><head><title>Barcode - ${p.name}</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3/dist/JsBarcode.all.min.js"><\/script>
    <style>body{text-align:center;padding:20px;font-family:Arial}.barcode{margin:20px auto}.label{font-size:12px;margin-top:4px;color:#333}.price{font-size:14px;font-weight:bold;margin-top:2px}</style></head>
    <body><svg id="barcode"></svg>
    <p class="label">${p.name}</p>
    <p class="price">$${Number(p.price).toFixed(2)}</p>
    <script>
    JsBarcode("#barcode", "${p.barcode}", {format:"CODE128",width:2,height:60,displayValue:true,fontSize:14});
    setTimeout(() => { window.print(); window.close(); }, 500);
    <\/script></body></html>`)
    win.document.close()
}

onMounted(async () => {
    if (productId.value) {
        try {
            const res = await api.get(`/products/${productId.value}`);
            product.value = res.data.data;
        } catch {
            // handle error
        } finally {
            loading.value = false;
        }
    }
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="router.visit('/products')"
                    class="p-2 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-lg transition-colors">
                    <ArrowLeft class="w-5 h-5" />
                </button>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <Package class="w-5 h-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">{{ product?.name || 'Product Details' }}</h1>
                    <p class="text-text-tertiary text-sm mt-0.5" v-if="product?.barcode">Barcode: {{ product.barcode }}</p>
                </div>
                <button v-if="product?.barcode" @click="printBarcode"
                    class="px-4 py-2 bg-surface-raised border border-border-input rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt transition-colors flex items-center gap-2">
                    <Barcode class="w-4 h-4" />
                    Print Barcode
                </button>
            </div>
        </div>

        <div v-if="loading" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-12 text-center">
            <p class="text-text-tertiary text-sm">Loading product details…</p>
        </div>

        <div v-else-if="product" class="space-y-6">
            <!-- Summary cards -->
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <DollarSign class="w-4 h-4 text-text-tertiary" />
                        <p class="text-xs text-text-tertiary font-medium">Price</p>
                    </div>
                    <p class="text-lg font-bold text-text-theme">{{ Number(product.price).toFixed(2) }}</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <DollarSign class="w-4 h-4 text-text-tertiary" />
                        <p class="text-xs text-text-tertiary font-medium">Cost</p>
                    </div>
                    <p class="text-lg font-bold text-text-theme">{{ Number(product.cost || 0).toFixed(2) }}</p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <ShoppingCart class="w-4 h-4 text-text-tertiary" />
                        <p class="text-xs text-text-tertiary font-medium">Margin</p>
                    </div>
                    <p class="text-lg font-bold" :class="margin >= 30 ? 'text-green-600' : margin >= 15 ? 'text-yellow-600' : 'text-red-600'">
                        {{ margin.toFixed(1) }}%
                    </p>
                </div>
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <Building2 class="w-4 h-4 text-text-tertiary" />
                        <p class="text-xs text-text-tertiary font-medium">Total Stock</p>
                    </div>
                    <p class="text-lg font-bold text-text-theme">{{ totalStock }}</p>
                </div>
            </div>

            <!-- Product info -->
            <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <h2 class="text-lg font-semibold text-text-theme mb-4">Product Information</h2>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs text-text-tertiary font-medium mb-1">Category</p>
                        <p class="text-sm font-semibold text-text-theme">{{ product.category?.name || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-text-tertiary font-medium mb-1">UOM</p>
                        <p class="text-sm font-semibold text-text-theme">{{ product.stock_uom || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-text-tertiary font-medium mb-1">Min Stock Level</p>
                        <p class="text-sm font-semibold text-text-theme">{{ product.min_stock ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-text-tertiary font-medium mb-1">Status</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="product.is_active ? 'bg-success-light text-success-theme' : 'bg-danger-light text-danger-theme'">
                            {{ product.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Stock by warehouse -->
            <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                <div class="px-6 py-4 border-b border-border-theme">
                    <h2 class="text-lg font-semibold text-text-theme">Stock by Warehouse</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-alt border-b border-border-theme">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wide">Warehouse</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Quantity</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Batch</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-text-tertiary uppercase tracking-wide">Expiry</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="inv in product.inventory" :key="inv.id">
                                <td class="px-6 py-4 text-text-theme">{{ inv.warehouse?.name || inv.warehouse_id?.slice(0, 8) }}</td>
                                <td class="px-6 py-4 text-right text-text-secondary">{{ inv.quantity }}</td>
                                <td class="px-6 py-4 text-right text-text-tertiary">{{ inv.batch_number || '—' }}</td>
                                <td class="px-6 py-4 text-right text-text-tertiary">{{ inv.expiry_date || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
