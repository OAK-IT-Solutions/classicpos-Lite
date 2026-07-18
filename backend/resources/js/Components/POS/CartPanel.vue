<script setup lang="ts">
import { ref } from 'vue';
import { ShoppingCart, Trash2 } from 'lucide-vue-next';
import type { CartItem } from '@/composables/useCart';

defineProps<{
    cart: CartItem[];
    cartItemCount: number;
    subtotal: number;
    tax: number;
    total: number;
    isEmpty: boolean;
}>();

const emit = defineEmits<{
    updateQuantity: [productId: string, delta: number];
    setQuantity: [productId: string, quantity: number];
    remove: [productId: string];
    clear: [];
    checkout: [];
}>();

function formatCurrency(amount: number): string {
    return amount.toFixed(2);
}

const editingQty = ref<Record<string, string>>({});

function startEdit(productId: string, currentQty: number) {
    editingQty.value[productId] = String(currentQty);
}

function commitEdit(productId: string) {
    const val = editingQty.value[productId];
    if (val !== undefined) {
        const qty = parseInt(val, 10);
        if (!isNaN(qty) && qty > 0) {
            emit('setQuantity', productId, qty);
        }
    }
    delete editingQty.value[productId];
}

function cancelEdit(productId: string) {
    delete editingQty.value[productId];
}
</script>

<template>
    <div class="w-96 flex flex-col bg-surface-raised flex-shrink-0 border-l border-border-theme">
        <div class="px-4 py-3 border-b border-border-theme flex items-center justify-between">
            <h2 class="text-sm font-bold text-text-theme">Cart ({{ cartItemCount }} items)</h2>
            <button
                v-if="!isEmpty"
                @click="emit('clear')"
                class="text-xs text-danger-theme hover:text-danger-theme font-medium"
            >
                Clear All
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-2 space-y-1.5">
            <div v-if="isEmpty" class="flex items-center justify-center h-full text-text-tertiary text-sm">
                <div class="text-center">
                    <svg class="w-12 h-12 mx-auto mb-2 text-border-input" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                    <p>Tap a product to start</p>
                </div>
            </div>
            <div
                v-for="item in cart"
                :key="item.product.id"
                class="flex items-center gap-2 p-2 rounded-lg hover:bg-primary/5 hover:border hover:border-primary/10 group transition-all"
            >
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-text-theme truncate">{{ item.product.name }}</p>
                    <p class="text-xs text-text-tertiary">{{ formatCurrency(item.product.price) }} each</p>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        @click="emit('updateQuantity', item.product.id, -1)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-danger/10 hover:bg-danger/20 text-danger text-sm font-bold transition-colors"
                    >
                        -
                    </button>
                    <input
                        v-if="editingQty[item.product.id] !== undefined"
                        v-model="editingQty[item.product.id]"
                        @blur="commitEdit(item.product.id)"
                        @keydown.enter.prevent="commitEdit(item.product.id)"
                        @keydown.escape.prevent="cancelEdit(item.product.id)"
                        type="text"
                        inputmode="numeric"
                        class="w-10 text-center text-sm font-bold text-text-theme border border-blue-300 rounded outline-none focus:ring-1 focus:ring-primary-ring"
                        autofocus
                    />
                    <span
                        v-else
                        @dblclick="startEdit(item.product.id, item.quantity)"
                        class="w-8 text-center text-sm font-bold text-text-theme cursor-pointer hover:bg-surface-alt rounded"
                    >
                        {{ item.quantity }}
                    </span>
                    <button
                        @click="emit('updateQuantity', item.product.id, 1)"
                        :disabled="item.quantity >= item.product.stock"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-success/10 hover:bg-success/20 text-success text-sm font-bold transition-colors disabled:opacity-30"
                    >
                        +
                    </button>
                </div>
                <div class="text-right min-w-[60px]">
                    <p class="text-sm font-bold text-text-theme">{{ formatCurrency(item.product.price * item.quantity) }}</p>
                </div>
                <button
                    @click="emit('remove', item.product.id)"
                    class="opacity-0 group-hover:opacity-100 transition-all p-1.5 rounded-lg bg-danger/10 hover:bg-danger/20 text-danger"
                >
                    <Trash2 class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>

            <div v-if="!isEmpty" class="border-t-2 border-primary/20 px-4 py-3 space-y-2 bg-gradient-to-t from-primary/5 to-transparent">
                <div class="flex justify-between text-sm">
                    <span class="text-text-tertiary font-medium">Subtotal</span>
                    <span class="text-text-theme font-semibold">{{ formatCurrency(subtotal) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-text-tertiary font-medium">Tax</span>
                    <span class="text-text-theme font-semibold">{{ formatCurrency(tax) }}</span>
                </div>
                <div class="flex justify-between items-center pt-1">
                    <span class="text-base font-bold text-text-theme">Total</span>
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-gradient-to-r from-primary/20 to-primary/10 text-primary text-lg font-bold shadow-sm">{{ formatCurrency(total) }}</span>
                </div>
                <button
                    @click="emit('checkout')"
                    class="w-full mt-2 bg-gradient-to-r from-primary to-primary/80 text-white py-2.5 rounded-lg font-bold hover:from-primary-hover hover:to-primary transition-all shadow-md hover:shadow-lg active:scale-[0.98] text-sm flex items-center justify-center gap-2"
                >
                    <ShoppingCart class="w-4 h-4" />
                    Checkout ({{ cartItemCount }} items)
                </button>
            </div>
    </div>
</template>
