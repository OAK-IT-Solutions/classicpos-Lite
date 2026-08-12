<script setup lang="ts">
import { ShoppingCart, Clock, List, LayoutDashboard, User } from 'lucide-vue-next';

defineProps<{
    cartItemCount: number;
}>();

defineEmits<{
    cart: [];
    held: [];
    categories: [];
    dashboard: [];
}>();
</script>

<template>
    <nav class="fixed bottom-0 left-0 right-0 z-40 bg-surface-raised border-t border-border-theme flex items-center justify-around h-16 px-2 safe-area-bottom md:hidden">
        <button @click="$emit('categories')" class="flex flex-col items-center gap-0.5 text-text-tertiary hover:text-text-theme transition-colors py-1 px-3">
            <LayoutDashboard class="w-5 h-5" />
            <span class="text-[10px] font-medium">Products</span>
        </button>
        <button @click="$emit('cart')" class="flex flex-col items-center gap-0.5 text-text-tertiary hover:text-text-theme transition-colors py-1 px-3 relative">
            <ShoppingCart class="w-5 h-5" />
            <span class="text-[10px] font-medium">Cart</span>
            <span v-if="cartItemCount > 0" class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-primary text-[10px] font-bold text-white flex items-center justify-center">
                {{ cartItemCount }}
            </span>
        </button>
        <button @click="$emit('held')" class="flex flex-col items-center gap-0.5 text-text-tertiary hover:text-text-theme transition-colors py-1 px-3">
            <Clock class="w-5 h-5" />
            <span class="text-[10px] font-medium">Held</span>
        </button>
        <button @click="$emit('dashboard')" class="flex flex-col items-center gap-0.5 text-text-tertiary hover:text-text-theme transition-colors py-1 px-3">
            <User class="w-5 h-5" />
            <span class="text-[10px] font-medium">Account</span>
        </button>
    </nav>
</template>

<style scoped>
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
