import { ref, computed, watch } from 'vue';
import { db } from '@/services/OfflineDB';

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

export interface CartItem {
    product: PosProduct;
    quantity: number;
}

const cart = ref<CartItem[]>([]);
let cartLoaded = false;
let persistEnabled = true;
let persistTimeout: ReturnType<typeof setTimeout> | null = null;

async function loadCart() {
    if (cartLoaded) return;
    cartLoaded = true;
    try {
        const snapshot = await db.cartSnapshots.get('current');
        if (snapshot?.cart) {
            cart.value = snapshot.cart;
        }
    } catch (err) {
        console.warn('[Cart] Failed to load from IndexedDB:', err);
    }
}

function persistCart() {
    if (!persistEnabled) return;
    // Debounce to avoid excessive writes
    if (persistTimeout) {
        clearTimeout(persistTimeout);
    }
    persistTimeout = setTimeout(async () => {
        try {
            if (cart.value.length === 0) {
                await db.cartSnapshots.delete('current');
            } else {
                await db.cartSnapshots.put({
                    id: 'current',
                    cart: cart.value,
                    customer: null,
                    promo_code: '',
                    tax_profile_id: '',
                    loyalty_points_to_redeem: 0,
                    payment_method: '',
                    gateway: '',
                    updated_at: Date.now(),
                });
            }
        } catch (err) {
            console.warn('[Cart] Failed to persist to IndexedDB:', err);
        }
    }, 300);
}

export function useCart() {
    const cartItemCount = computed(() =>
        cart.value.reduce((sum, item) => sum + item.quantity, 0)
    );

    const cartSubtotal = computed(() =>
        cart.value.reduce((sum, item) => sum + item.product.price * item.quantity, 0)
    );

    const isEmpty = computed(() => cart.value.length === 0);

    function addToCart(product: PosProduct) {
        if (product.stock <= 0) return;
        const existing = cart.value.find(item => item.product.id === product.id);
        if (existing) {
            if (existing.quantity < product.stock) {
                existing.quantity++;
                persistCart();
            }
        } else {
            cart.value.push({ product, quantity: 1 });
            persistCart();
        }
    }

    function updateQuantity(productId: string, delta: number) {
        const item = cart.value.find(i => i.product.id === productId);
        if (!item) return;
        const newQty = item.quantity + delta;
        if (newQty <= 0) {
            cart.value = cart.value.filter(i => i.product.id !== productId);
        } else if (newQty <= item.product.stock) {
            item.quantity = newQty;
        }
        persistCart();
    }

    function setQuantity(productId: string, qty: number) {
        const item = cart.value.find(i => i.product.id === productId);
        if (!item) return;
        if (qty <= 0) {
            cart.value = cart.value.filter(i => i.product.id !== productId);
        } else {
            item.quantity = Math.min(qty, item.product.stock);
        }
        persistCart();
    }

    function removeFromCart(productId: string) {
        cart.value = cart.value.filter(i => i.product.id !== productId);
        persistCart();
    }

    function clearCart() {
        cart.value = [];
        // Immediately delete from IndexedDB
        db.cartSnapshots.delete('current').catch(() => {});
    }

    // Load cart on first use
    loadCart();

    return {
        cart,
        cartItemCount,
        cartSubtotal,
        isEmpty,
        addToCart,
        updateQuantity,
        setQuantity,
        removeFromCart,
        clearCart,
        loadCart,
    };
}
