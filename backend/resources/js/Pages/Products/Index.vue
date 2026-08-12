<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useProducts, type Product, type Category } from '@/composables/useProducts';
import { useAuth } from '@/composables/useAuth';
import api from '@/composables/axios';
import { Plus, Pencil, ToggleLeft, ToggleRight, AlertCircle, PlusCircle, Barcode } from 'lucide-vue-next';

const { products, categories, loading, error, pagination, fetchProducts, fetchCategories, createCategory, createProduct, updateProduct, uploadProductImage, deleteProductImage } = useProducts();
const _auth = useAuth();

const searchQuery = ref('');
const filterCategoryId = ref('');
const filterIsActive = ref('');
const filterPriceMin = ref('');
const filterPriceMax = ref('');
const showForm = ref(false);
const editingProduct = ref<Product | null>(null);
const newCategoryName = ref('');
const showNewCategoryInput = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const imageFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const imageUploading = ref(false);
const formData = ref({
    name: '',
    barcode: '',
    category_id: '' as string | null,
    price: 0,
    cost: 0,
    stock_uom: 'pcs',
    min_stock: 0,
    is_active: true,
    returnable: null as boolean | null,
});

let debounceTimer: ReturnType<typeof setTimeout>;

watch([searchQuery, filterCategoryId, filterIsActive, filterPriceMin, filterPriceMax], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
});

function buildParams(): Record<string, string> {
    const params: Record<string, string> = {};
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (filterCategoryId.value) params.category_id = filterCategoryId.value;
    if (filterIsActive.value) params.is_active = filterIsActive.value;
    const minPrice = filterPriceMin.value.toString().trim();
    const maxPrice = filterPriceMax.value.toString().trim();
    if (minPrice !== '' && minPrice !== '0') params.price_min = minPrice;
    if (maxPrice !== '' && maxPrice !== '0') params.price_max = maxPrice;
    return params;
}

function applyFilters() {
    fetchProducts(1, buildParams());
}

function changePage(page: number) {
    fetchProducts(page, buildParams());
}

function openAddForm() {
    editingProduct.value = null;
    formData.value = {
        name: '',
        barcode: '',
        category_id: null,
        price: 0,
        cost: 0,
        stock_uom: 'pcs',
        min_stock: 0,
        is_active: true,
        returnable: null,
    };
    imageFile.value = null;
    imagePreview.value = null;
    showForm.value = true;
}

function openEditForm(product: Product) {
    editingProduct.value = product;
    formData.value = {
        name: product.name,
        barcode: product.barcode,
        category_id: product.category_id,
        price: product.price,
        cost: product.cost ?? 0,
        stock_uom: product.stock_uom,
        min_stock: product.min_stock,
        is_active: product.is_active,
        returnable: product.returnable ?? null,
    };
    imageFile.value = null;
    imagePreview.value = product.image || null;
    showForm.value = true;
}

function onFileSelected(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;
    imageFile.value = file;
    if (imagePreview.value && editingProduct.value?.image) {
        URL.revokeObjectURL(imagePreview.value);
    }
    imagePreview.value = URL.createObjectURL(file);
}

function removeImage() {
    imageFile.value = null;
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
    }
    imagePreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

async function handleSubmit() {
    try {
        const payload = { ...formData.value };
        if (!payload.category_id) {
            delete payload.category_id;
        }
        let savedProduct: Product;
        if (editingProduct.value) {
            savedProduct = await updateProduct(editingProduct.value.id, payload);
        } else {
            savedProduct = await createProduct(payload);
        }

        if (imageFile.value) {
            await uploadProductImage(savedProduct.id, imageFile.value);
        } else if (editingProduct.value && editingProduct.value.image && !imagePreview.value) {
            await deleteProductImage(savedProduct.id);
        }

        showForm.value = false;
        await changePage(pagination.value.current_page);
    } catch {
        // error ref is populated by the composable
    }
}

async function handleNewCategory() {
    const name = newCategoryName.value.trim();
    if (!name) return;
    try {
        const category = await createCategory(name);
        formData.value.category_id = category.id;
        newCategoryName.value = '';
        showNewCategoryInput.value = false;
    } catch {
        // error handled by composable
    }
}

async function toggleActive(product: Product) {
    try {
        await updateProduct(product.id, { is_active: !product.is_active });
    } catch {
        // error ref is populated by the composable
    }
}

function marginClass(product: Product): string {
    if (!product.cost) return 'text-text-tertiary';
    const margin = (Number(product.price) - Number(product.cost)) / Number(product.price) * 100;
    if (margin >= 30) return 'text-success-theme font-medium';
    if (margin >= 15) return 'text-warning-theme font-medium';
    return 'text-danger-theme font-medium';
}

const barcodeScanInput = ref('');
const barcodeFeedback = ref('');
const barcodeScanEl = ref<HTMLInputElement | null>(null);

function handleKeydown(e: KeyboardEvent) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        barcodeScanEl.value?.focus();
    }
}

async function handleBarcodeScan() {
    const code = barcodeScanInput.value.trim();
    if (!code) return;
    barcodeScanInput.value = '';
    barcodeFeedback.value = 'Searching...';
    try {
        const res = await api.get(`/products/by-barcode/${encodeURIComponent(code)}`);
        const product = res.data?.data;
        if (product) {
            barcodeFeedback.value = `Scanned: ${product.name}`;
            setTimeout(() => { barcodeFeedback.value = ''; router.visit(`/products/${product.id}`); }, 600);
        } else {
            barcodeFeedback.value = 'Product not found';
        }
    } catch {
        barcodeFeedback.value = 'No product found with this barcode';
    }
    setTimeout(() => { barcodeFeedback.value = ''; }, 3000);
}

function getCategoryName(categoryId: string | null): string {
    if (!categoryId) return '—';
    const cat = categories.value.find(c => c.id === categoryId);
    return cat?.name || '—';
}

onMounted(async () => {
    document.addEventListener('keydown', handleKeydown);
    await Promise.all([fetchProducts(), fetchCategories()]);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
    }
});
</script>

<template>
    <AppLayout>
        <!-- Page header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-text-theme">Products</h1>
            <button
                @click="openAddForm"
                class="flex items-center gap-2 px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors"
            >
                <Plus class="w-4 h-4" />
                Add Product
            </button>
        </div>

        <!-- Search and filter bar -->
        <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4 mb-6">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-text-secondary mb-1">Search</label>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Name or barcode..."
                        class="w-full px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                        @keydown.enter="applyFilters"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">Category</label>
                    <select
                        v-model="filterCategoryId"
                        class="px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                        @change="applyFilters"
                    >
                        <option value="">All Categories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">Status</label>
                    <select
                        v-model="filterIsActive"
                        class="px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                        @change="applyFilters"
                    >
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">Min Price</label>
                    <input
                        v-model="filterPriceMin"
                        type="text"
                        inputmode="decimal"
                        placeholder="Min"
                        class="w-24 px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">Max Price</label>
                    <input
                        v-model="filterPriceMax"
                        type="text"
                        inputmode="decimal"
                        placeholder="Max"
                        class="w-24 px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                    />
                </div>
                <button
                    @click="applyFilters"
                    class="px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors"
                >
                    Filter
                </button>
                <button
                    v-if="searchQuery || filterCategoryId || filterIsActive || filterPriceMin || filterPriceMax"
                    @click="searchQuery = ''; filterCategoryId = ''; filterIsActive = ''; filterPriceMin = ''; filterPriceMax = ''; fetchProducts(1)"
                    class="px-3 py-2 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- Barcode scanner bar -->
        <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-3 mb-6 flex items-center gap-3">
            <Barcode class="w-5 h-5 text-text-tertiary flex-shrink-0" />
            <input
                ref="barcodeScanEl"
                v-model="barcodeScanInput"
                @keydown.enter.prevent="handleBarcodeScan"
                type="text"
                placeholder="Scan barcode... (Ctrl+B)"
                class="flex-1 px-3 py-2 text-sm border border-border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
            />
            <button @click="handleBarcodeScan"
                class="px-4 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors flex-shrink-0">
                Scan
            </button>
            <span v-if="barcodeFeedback" class="text-xs font-medium flex-shrink-0"
                :class="barcodeFeedback.startsWith('Scanned') ? 'text-success-theme' : barcodeFeedback === 'Searching...' ? 'text-text-tertiary' : 'text-danger-theme'">
                {{ barcodeFeedback }}
            </span>
        </div>

        <!-- Error alert -->
        <div v-if="error" class="flex items-center gap-3 mb-6 px-4 py-3 bg-danger-light border border-danger-theme/20 rounded-lg text-danger-theme">
            <AlertCircle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ error }}</span>
        </div>

        <!-- Table -->
        <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
            <div v-if="loading" class="flex items-center justify-center py-16 text-text-tertiary text-sm">
                Loading products…
            </div>
            <div v-else-if="products.length === 0" class="flex items-center justify-center py-16 text-text-tertiary text-sm">
                No products found. Add one to get started.
            </div>
            <table v-else class="min-w-full divide-y divide-gray-200">
                <thead class="bg-surface-alt">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Barcode</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Price</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Cost</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Margin</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">UOM</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Min Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-text-tertiary uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="product in products" :key="product.id" @click="router.visit(`/products/${product.id}`)" class="hover:bg-surface-alt cursor-pointer transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-primary hover:underline">{{ product.name }}</td>
                        <td class="px-6 py-4 text-sm text-text-tertiary">{{ product.barcode || '—' }}</td>
                        <td class="px-6 py-4 text-sm text-text-tertiary">{{ getCategoryName(product.category_id) }}</td>
                        <td class="px-6 py-4 text-sm text-text-theme">{{ Number(product.price).toFixed(2) }}</td>
                        <td class="px-6 py-4 text-sm text-text-tertiary">{{ product.cost ? Number(product.cost).toFixed(2) : '—' }}</td>
                        <td class="px-6 py-4 text-sm" :class="marginClass(product)">
                            {{ product.cost ? ((Number(product.price) - Number(product.cost)) / Number(product.price) * 100).toFixed(1) + '%' : '—' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-text-tertiary">{{ product.stock_uom }}</td>
                        <td class="px-6 py-4 text-sm text-text-tertiary">{{ product.min_stock }}</td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="product.is_active
                                    ? 'bg-success-light text-success-theme'
                                    : 'bg-surface-alt text-text-tertiary'"
                            >
                                {{ product.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <button
                                    @click="openEditForm(product)"
                                    class="p-1.5 text-text-tertiary hover:text-primary hover:bg-primary-light rounded-lg transition-colors"
                                    title="Edit product"
                                >
                                    <Pencil class="w-4 h-4" />
                                </button>
                                <button
                                    @click="toggleActive(product)"
                                    class="p-1.5 rounded-lg transition-colors"
                                    :class="product.is_active
                                        ? 'text-green-500 hover:text-text-tertiary hover:bg-surface-alt'
                                        : 'text-text-tertiary hover:text-green-500 hover:bg-success-light'"
                                    :title="product.is_active ? 'Deactivate' : 'Activate'"
                                >
                                    <ToggleRight v-if="product.is_active" class="w-5 h-5" />
                                    <ToggleLeft v-else class="w-5 h-5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between mt-4">
            <p class="text-sm text-text-tertiary">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
                &mdash; {{ pagination.total }} total
            </p>
            <div class="flex items-center gap-2">
                <button
                    @click="changePage(pagination.current_page - 1)"
                    :disabled="pagination.current_page <= 1"
                    class="px-3 py-1.5 text-sm font-medium rounded-lg border border-border-theme text-text-secondary hover:bg-surface-alt disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    Previous
                </button>
                <button
                    @click="changePage(pagination.current_page + 1)"
                    :disabled="pagination.current_page >= pagination.last_page"
                    class="px-3 py-1.5 text-sm font-medium rounded-lg border border-border-theme text-text-secondary hover:bg-surface-alt disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    Next
                </button>
            </div>
        </div>

        <!-- Slide-over panel -->
        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-50 flex justify-end">
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/40"
                    @click="showForm = false"
                />
                <!-- Panel -->
                <div class="relative w-full max-w-md bg-surface-raised shadow-xl flex flex-col h-full">
                    <!-- Panel header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-border-theme">
                        <h2 class="text-lg font-semibold text-text-theme">
                            {{ editingProduct ? 'Edit Product' : 'Add Product' }}
                        </h2>
                        <button
                            @click="showForm = false"
                            class="p-2 text-text-tertiary hover:text-text-secondary hover:bg-surface-alt rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="handleSubmit" class="flex-1 overflow-y-auto px-6 py-6 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.name"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                placeholder="Product name"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Barcode</label>
                            <input
                                v-model="formData.barcode"
                                type="text"
                                class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                placeholder="e.g. 1234567890"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Category</label>
                            <div class="relative">
                                <select
                                    v-model="formData.category_id"
                                    class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent appearance-none pr-10"
                                >
                                    <option value="">Select category</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }}
                                    </option>
                                    <option value="__new__">+ Add new category…</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                    <svg class="w-4 h-4 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                            <div v-if="showNewCategoryInput" class="mt-2 flex gap-2">
                                <input
                                    v-model="newCategoryName"
                                    type="text"
                                    placeholder="New category name"
                                    class="flex-1 px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                    @keydown.enter="handleNewCategory"
                                    autofocus
                                />
                                <button
                                    type="button"
                                    @click="handleNewCategory"
                                    class="px-3 py-2 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors"
                                >
                                    Add
                                </button>
                                <button
                                    type="button"
                                    @click="showNewCategoryInput = false; newCategoryName = ''"
                                    class="px-3 py-2 text-text-secondary text-sm font-medium rounded-lg hover:bg-surface-alt transition-colors"
                                >
                                    Cancel
                                </button>
                            </div>
                            <button
                                v-else
                                type="button"
                                @click="showNewCategoryInput = true"
                                class="mt-2 text-sm text-primary hover:text-primary flex items-center gap-1"
                            >
                                <PlusCircle class="w-4 h-4" />
                                Add new category
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Price</label>
                            <input
                                v-model.number="formData.price"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                placeholder="0.00"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Cost</label>
                            <input
                                v-model.number="formData.cost"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                placeholder="0.00"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Stock UOM</label>
                            <input
                                v-model="formData.stock_uom"
                                type="text"
                                class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                placeholder="pcs"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Min Stock</label>
                            <input
                                v-model.number="formData.min_stock"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 border border-border-input rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-ring focus:border-transparent"
                                placeholder="0"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Returnable</label>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="formData.returnable" :value="true" class="text-primary focus:ring-primary-ring" />
                                    <span class="text-sm text-text-theme">Yes</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="formData.returnable" :value="false" class="text-primary focus:ring-primary-ring" />
                                    <span class="text-sm text-text-theme">No</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="formData.returnable" :value="null" class="text-primary focus:ring-primary-ring" />
                                    <span class="text-sm text-text-tertiary">Use category default</span>
                                </label>
                            </div>
                            <p class="text-xs text-text-tertiary mt-1">By default, products are not returnable. Enable per product or by category.</p>
                        </div>

                        <!-- Image Upload -->
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Product Image</label>
                            <div class="flex items-start gap-4">
                                <div
                                    class="relative w-32 h-32 rounded-lg border-2 border-dashed border-border-input flex items-center justify-center overflow-hidden bg-surface-alt cursor-pointer hover:border-blue-400 transition-colors shrink-0"
                                    @click="fileInput?.click()"
                                >
                                    <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
                                    <div v-else class="text-center px-2">
                                        <svg class="w-8 h-8 mx-auto text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-xs text-text-tertiary mt-1 block">Click to upload</span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                        class="hidden"
                                        @change="onFileSelected"
                                    />
                                    <button
                                        type="button"
                                        @click="fileInput?.click()"
                                        class="px-3 py-1.5 text-sm font-medium text-primary bg-primary-light border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors"
                                    >
                                        Choose File
                                    </button>
                                    <button
                                        v-if="imagePreview"
                                        type="button"
                                        @click="removeImage"
                                        class="px-3 py-1.5 text-sm font-medium text-danger-theme bg-danger-light border border-danger-theme/20 rounded-lg hover:bg-danger-light transition-colors"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Panel footer -->
                    <div class="flex items-center gap-3 px-6 py-4 border-t border-border-theme bg-surface-alt">
                        <button
                            type="button"
                            @click="showForm = false"
                            class="flex-1 px-4 py-2 text-sm font-medium text-text-secondary bg-surface-raised border border-border-input rounded-lg hover:bg-surface-alt transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            @click="handleSubmit"
                            :disabled="loading"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-btn-primary rounded-lg hover:bg-btn-primary-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ editingProduct ? 'Save Changes' : 'Add Product' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
