<script setup lang="ts">
import { ref, computed } from 'vue';
import type { PosProduct } from '@/composables/useCart';
import type { CategoryInfo } from '@/composables/usePos';

const props = defineProps<{
    products: PosProduct[];
    categories: CategoryInfo[];
    loading: boolean;
}>();

const emit = defineEmits<{
    addToCart: [product: PosProduct];
}>();

const searchQuery = ref('');
const view = ref<'categories' | 'products'>('categories');
const selectedCategoryId = ref<string | null>(null);
const categoryStack = ref<{ id: string | null; name: string }[]>([]);

const tree = computed(() => {
    const map = new Map<string | null, CategoryInfo[]>();
    for (const cat of props.categories) {
        const parentKey = cat.parent_id ?? '__root__';
        if (!map.has(parentKey)) map.set(parentKey, []);
        map.get(parentKey)!.push(cat);
    }
    return map;
});

const currentCategories = computed(() => {
    const key = selectedCategoryId.value ?? '__root__';
    return tree.value.get(key) || [];
});

const currentCategoryName = computed(() => {
    if (!selectedCategoryId.value) return '';
    const cat = props.categories.find(c => c.id === selectedCategoryId.value);
    return cat?.name || '';
});

const categoryCounts = computed(() => {
    const counts: Record<string, number> = {};
    props.products.forEach(p => {
        const cat = p.category || 'Uncategorized';
        counts[cat] = (counts[cat] || 0) + 1;
    });
    return counts;
});

const filteredProducts = computed(() => {
    let result = props.products;
    if (selectedCategoryId.value) {
        const cat = props.categories.find(c => c.id === selectedCategoryId.value);
        if (cat) {
            result = result.filter(p => p.category === cat.name);
        }
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(p =>
            p.name.toLowerCase().includes(q) || p.barcode?.toLowerCase().includes(q)
        );
    }
    return result;
});

function selectCategory(cat: CategoryInfo) {
    const children = tree.value.get(cat.id) || [];
    if (children.length > 0) {
        categoryStack.value.push({ id: selectedCategoryId.value, name: currentCategoryName.value });
        selectedCategoryId.value = cat.id;
        view.value = 'categories';
    } else {
        selectedCategoryId.value = cat.id;
        view.value = 'products';
    }
}

function goBack() {
    if (categoryStack.value.length > 0) {
        const prev = categoryStack.value.pop()!;
        selectedCategoryId.value = prev.id;
        view.value = prev.id ? 'categories' : 'categories';
    } else {
        selectedCategoryId.value = null;
        view.value = 'categories';
    }
    searchQuery.value = '';
}

function formatCurrency(amount: number): string {
    return amount.toFixed(2);
}

function categoryIcon(cat: string): string {
    const icons: Record<string, string> = {
        'Beer': 'M14 2H10L9 5H15L14 2Z M11 6V16M13 6V16M7 16H17L18 22H6L7 16Z',
        'Food': 'M12 2C8 2 4 5 4 9C4 11.5 5.5 13.5 7 15V22H17V15C18.5 13.5 20 11.5 20 9C20 5 16 2 12 2ZM9 17V12L12 14L15 12V17',
        'Soft Drinks': 'M8 2L7 10H17L16 2H8ZM7 13C7 16 9 18 12 18C15 18 17 16 17 13',
        'Snacks': 'M12 2C8 2 4 4 4 7C4 9 6 11 8 12V15H16V12C18 11 20 9 20 7C20 4 16 2 12 2ZM8 17H16V22H8V17Z',
        'Spirits': 'M9 3H15L16 8H8L9 3ZM7 10H17L16 16C16 18 14 20 12 20C10 20 8 18 8 16L7 10Z',
        'Whisky': 'M10 3H14L15 8H9L10 3ZM8 10H16L15 17C15 19 13.5 21 12 21C10.5 21 9 19 9 17L8 10Z',
        'Liqueur': 'M11 3H13L14 7H10L11 3ZM9 9H15L14 16C14 18 13 20 12 20C11 20 10 18 10 16L9 9Z',
        'General': 'M4 6H20M4 12H20M4 18H20',
        'Starter': 'M4 11L12 4L20 11H17V20H7V11H4Z',
    };
    return icons[cat] || 'M4 6H20M4 12H20M4 18H20';
}

function randomColor(seed: string): string {
    const colors = [
        'bg-blue-100 text-blue-700',
        'bg-emerald-100 text-emerald-700',
        'bg-amber-100 text-amber-700',
        'bg-purple-100 text-purple-700',
        'bg-rose-100 text-rose-700',
        'bg-cyan-100 text-cyan-700',
        'bg-orange-100 text-orange-700',
        'bg-teal-100 text-teal-700',
        'bg-indigo-100 text-indigo-700',
        'bg-pink-100 text-pink-700',
    ];
    let hash = 0;
    for (let i = 0; i < seed.length; i++) hash = seed.charCodeAt(i) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
}
</script>

<template>
    <div class="flex flex-col overflow-hidden h-full">
        <div class="bg-gradient-to-r from-primary/5 via-surface-raised to-primary/5 px-4 pt-3 pb-2 border-b-2 border-primary/20 flex-shrink-0 space-y-2 shadow-sm">
            <div class="flex items-center gap-2">
                <button
                    v-if="selectedCategoryId"
                    @click="goBack"
                    class="flex items-center gap-1 text-sm font-medium text-text-secondary hover:text-text-theme transition-colors flex-shrink-0"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span class="hidden sm:inline">Back</span>
                </button>
                <h2 class="text-base font-bold text-text-theme truncate">
                    <template v-if="selectedCategoryId && view === 'products'">{{ currentCategoryName }}</template>
                    <template v-else-if="selectedCategoryId && view === 'categories'">{{ currentCategoryName }}</template>
                    <template v-else>Categories</template>
                </h2>
            </div>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="view === 'categories' ? 'Search categories or products...' : 'Search in ' + currentCategoryName + '...'"
                    class="w-full pl-10 pr-4 py-2.5 border border-border-input rounded-lg text-sm bg-input-bg text-text-theme focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none"
                />
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-3">
            <div v-if="loading" class="flex items-center justify-center h-full text-text-tertiary text-sm">
                Loading...
            </div>

            <div v-else-if="view === 'categories'">
                <div v-if="searchQuery && filteredProducts.length > 0" class="space-y-2">
                    <p class="text-xs font-medium text-text-tertiary uppercase tracking-wider">Search Results</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                        <button
                            v-for="product in filteredProducts"
                            :key="product.id"
                            @click="emit('addToCart', product)"
                            :disabled="product.stock <= 0"
                            class="bg-surface-raised rounded-lg border border-border-theme p-2 text-left hover:shadow-md hover:border-primary/40 hover:bg-primary/[0.02] transition-all disabled:opacity-50 disabled:cursor-not-allowed active:scale-95"
                        >
                            <p class="text-xs font-semibold text-text-theme truncate">{{ product.name }}</p>
                            <p class="text-sm font-bold text-primary">{{ formatCurrency(product.price) }}</p>
                            <span class="text-[10px] text-text-tertiary">{{ product.category }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="!searchQuery || filteredProducts.length === 0">
                    <div v-if="currentCategories.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                        <button
                            v-for="cat in currentCategories"
                            :key="cat.id"
                            @click="selectCategory(cat)"
                            class="bg-surface-raised rounded-2xl border-2 border-border-theme p-4 text-center hover:shadow-lg hover:border-primary/40 transition-all active:scale-95 flex flex-col items-center gap-2"
                        >
                            <div class="w-16 h-16 rounded-2xl flex items-center justify-center" :class="randomColor(cat.name)">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="categoryIcon(cat.name)" />
                                </svg>
                            </div>
                            <span class="text-sm font-bold text-text-theme">{{ cat.name }}</span>
                            <span class="text-xs text-text-tertiary">{{ categoryCounts[cat.name] || 0 }} items</span>
                        </button>
                    </div>
                    <div v-if="currentCategories.length === 0 && !searchQuery" class="flex items-center justify-center h-full text-text-tertiary text-sm">
                        No categories available
                    </div>
                </div>
            </div>

            <div v-else-if="view === 'products'">
                <div v-if="filteredProducts.length === 0" class="flex items-center justify-center h-full text-text-tertiary text-sm">
                    <div class="text-center">
                        <svg class="w-12 h-12 mx-auto mb-2 text-border-input" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p v-if="searchQuery">No products match "{{ searchQuery }}"</p>
                        <p v-else>No products in {{ currentCategoryName }}</p>
                    </div>
                </div>
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="emit('addToCart', product)"
                        :disabled="product.stock <= 0"
                        class="bg-surface-raised rounded-xl border border-border-theme overflow-hidden text-left hover:shadow-lg hover:border-primary/40 hover:bg-primary/[0.02] transition-all disabled:opacity-50 disabled:cursor-not-allowed active:scale-95"
                    >
                        <div class="h-1.5 bg-gradient-to-r from-primary via-primary/60 to-primary/20"></div>
                        <div class="aspect-square bg-surface-alt flex items-center justify-center relative">
                            <img
                                v-if="product.image"
                                :src="product.image"
                                :alt="product.name"
                                class="w-full h-full object-cover"
                            />
                            <svg v-else class="w-10 h-10 text-border-input" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span
                                class="absolute top-1.5 right-1.5 text-[9px] font-bold px-1.5 py-0.5 rounded"
                                :class="product.stock <= 0 ? 'bg-red-500 text-white' : product.stock <= 10 ? 'bg-amber-400 text-white' : 'bg-green-500 text-white'"
                            >
                                {{ product.stock <= 0 ? 'OUT' : product.stock }}
                            </span>
                        </div>
                        <div class="p-2">
                            <p class="text-[11px] font-semibold text-text-theme truncate leading-tight">{{ product.name }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-primary/10 text-primary text-sm font-bold">{{ formatCurrency(product.price) }}</span>
                                <span class="text-[9px] text-text-tertiary">{{ product.stock_uom }}</span>
                            </div>
                            <p v-if="product.barcode" class="text-[8px] text-text-tertiary mt-1 font-mono truncate">{{ product.barcode }}</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
