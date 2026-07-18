<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import { useCart } from '@/composables/useCart';
import { usePos } from '@/composables/usePos';
import { useNetwork } from '@/composables/useNetwork';
import { useSync } from '@/services/SyncService';
import { openDrawerAndPrintReceipt, type ReceiptData } from '@/services/PrinterService';
import api from '@/composables/axios';
import type { CustomerLookup, HeldSale } from '@/composables/usePos';
import BottomNav from '@/Components/POS/BottomNav.vue';
import ProductGrid from '@/Components/POS/ProductGrid.vue';
import CartPanel from '@/Components/POS/CartPanel.vue';
import CustomerSelect from '@/Components/POS/CustomerSelect.vue';
import PromoCodeInput from '@/Components/POS/PromoCodeInput.vue';
import PaymentForm from '@/Components/POS/PaymentForm.vue';
import GlobalSyncIndicator from '@/Components/GlobalSyncIndicator.vue';
import { Clock, Play, MapPin, ShoppingCart, Timer, Wallet, LogOut, DollarSign, Lock } from 'lucide-vue-next';
import InputText from 'primevue/inputtext';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const auth = useAuth();
const cartManager = useCart();
const pos = usePos();
const toast = useToast();
const { isOnline, isOffline } = useNetwork();
const { pendingSalesCount, isSyncing, lastSyncAt, syncPendingSales } = useSync();

const branchName = computed(() => {
    const u = auth.user.value;
    const ab = (auth as any).activeBranch;
    return u?.branch?.name
        || (ab?.value?.name)
        || u?.assigned_branches?.[0]?.name
        || 'Branch';
});

const now = ref(new Date())
const clockInterval = setInterval(() => { now.value = new Date() }, 1000)

onUnmounted(() => {
    clearInterval(clockInterval)
    document.removeEventListener('keydown', handleKeydown);
})

const openShift = ref<any>(null);
const showOpenShift = ref(false);
const showCloseShift = ref(false);
const shiftOpeningBalance = ref(200);
const shiftActualBalance = ref(0);
const shiftNotes = ref('');
const shiftPassword = ref('');

async function checkShift() {
    try {
        const r = await api.get('/cash-register/status');
        openShift.value = r.data?.data ?? null;
        if (!openShift.value) showOpenShift.value = true;
    } catch { showOpenShift.value = true; }
}

const shiftRequired = computed(() => !openShift.value && !showOpenShift.value)

async function openRegister() {
    if (!shiftPassword.value) { alert('Password is required.'); return; }
    try {
        const r = await api.post(`/cash-register/open?opening_balance=${shiftOpeningBalance.value}&password=${encodeURIComponent(shiftPassword.value)}`);
        openShift.value = r.data.data;
        showOpenShift.value = false;
        shiftPassword.value = '';
    } catch (err: any) { alert(err?.response?.data?.error?.message || 'Failed to open register'); }
}

async function closeRegister() {
    if (!openShift.value) return;
    if (!shiftPassword.value) { alert('Password is required.'); return; }
    try {
        const params = `actual_balance=${shiftActualBalance.value}&password=${encodeURIComponent(shiftPassword.value)}${shiftNotes.value ? `&notes=${encodeURIComponent(shiftNotes.value)}` : ''}`;
        await api.post(`/cash-register/${openShift.value.id}/close?${params}`);
        openShift.value = null;
        showCloseShift.value = false;
        shiftPassword.value = '';
        window.location.href = '/cash-register';
    } catch (err: any) { alert(err?.response?.data?.error?.message || 'Failed to close register'); }
}

function openCloseDialog() {
    if (!openShift.value) return;
    shiftActualBalance.value = openShift.value.expected_balance;
    showCloseShift.value = true;
    console.log('[POS] openCloseDialog called, showCloseShift =', showCloseShift.value);
}

const {
    cart, cartItemCount, cartSubtotal, isEmpty,
    addToCart, updateQuantity, setQuantity, removeFromCart, clearCart,
} = cartManager;

const { products, categories, loading, fetchProducts, completeSale, fetchTaxProfiles } = pos;

const branchId = computed(() => auth.user.value?.branch?.id || auth.user.value?.branch_id);

const showCartMobile = ref(false);
const showCheckout = ref(false);
const checkoutLoading = ref(false);
const checkoutError = ref('');
const saleSuccess = ref(false);
const lastSale = ref<any>(null);
const lastCartItems = ref<{ name: string; quantity: number; price: number }[]>([]);

const selectedCustomer = ref<CustomerLookup | null>(null);
const loyaltyPointsToRedeem = ref(0);
const loyaltyDiscount = ref(0);
const promoCode = ref('');
const barcodeInput = ref('');
const barcodeFeedback = ref('');
const barcodeInputEl = ref<HTMLInputElement | null>(null);
const paymentMethod = ref('cash');
const gateway = ref('');
const amountTendered = ref(0);
const selectedTaxProfileId = ref('');
const taxProfiles = ref<any[]>([]);
const rememberLast = ref(false);

const showHeld = ref(false);
const holding = ref(false);
const holdNote = ref('');

const selectedTaxProfile = computed(() => {
    if (!selectedTaxProfileId.value) return null;
    return taxProfiles.value.find(tp => tp.id === selectedTaxProfileId.value) || null;
});

const shiftDiff = computed(() => {
    if (!openShift.value) return 0;
    return shiftActualBalance.value - openShift.value.expected_balance;
});

const cartTax = computed(() => {
    const profile = selectedTaxProfile.value;
    if (!profile) return 0;
    const rate = profile.rate;
    if (profile.type === 'exclusive') {
        return cartSubtotal.value * (rate / 100);
    }
    return cartSubtotal.value - (cartSubtotal.value / (1 + rate / 100));
});
const cartTotal = computed(() => Math.max(0, cartSubtotal.value + cartTax.value - loyaltyDiscount.value));

onMounted(async () => {
    if (!auth.user.value) {
        const isAuthenticated = await auth.check();
        if (!isAuthenticated) {
            window.location.href = '/login';
            return;
        }
    }
    await checkShift();
    if (!branchId.value) return;
    await fetchProducts(branchId.value);
    try {
        taxProfiles.value = await fetchTaxProfiles();
    } catch {
        taxProfiles.value = [];
    }
    document.addEventListener('keydown', handleKeydown);
});

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        if (showCheckout.value) {
            showCheckout.value = false;
            e.preventDefault();
        } else if (saleSuccess.value) {
            resetSale();
            e.preventDefault();
        }
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        barcodeInputEl.value?.focus();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && showCheckout.value) {
        e.preventDefault();
        handleCheckout();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        if (!isEmpty.value) {
            showCheckout.value ? (showCheckout.value = false) : openCheckout();
        }
    }
}

async function handleCheckout() {
    if (!branchId.value || isEmpty.value) return;
    checkoutLoading.value = true;
    checkoutError.value = '';

    try {
        const itemsForSale = cart.value.map(item => ({
            product_id: item.product.id,
            product_name: item.product.name,
            quantity: item.quantity,
            price: item.product.price,
        }));

        const result = await completeSale(
            {
                branch_id: branchId.value,
                customer_id: selectedCustomer.value?.id,
                items: cart.value.map(item => ({
                    product_id: item.product.id,
                    quantity: item.quantity,
                    price: item.product.price,
                })),
                payment_method: paymentMethod.value,
                gateway: gateway.value || undefined,
                promo_code: promoCode.value || undefined,
                tax_profile_id: selectedTaxProfileId.value || undefined,
                loyalty_points_redeemed: loyaltyPointsToRedeem.value || undefined,
            },
            {
                cashReceived: paymentMethod.value === 'cash' ? amountTendered.value : undefined,
                changeDue: paymentMethod.value === 'cash' ? Math.max(0, amountTendered.value - cartTotal.value) : undefined,
                itemDetails: itemsForSale,
            }
        );

        lastSale.value = result;
        lastCartItems.value = cart.value.map(item => ({
            name: item.product.name,
            quantity: item.quantity,
            price: item.product.price,
        }));

        saleSuccess.value = true;
        clearCart();
        showCheckout.value = false;

        // Auto-trigger cash drawer + receipt print for every sale (cash + non-cash)
        // Don't await — fire and forget so UI shows success immediately
        const receiptData: ReceiptData = {
            invoiceNumber: result.invoice_number,
            branchName: auth.user.value?.branch?.name || 'Store',
            items: lastCartItems.value,
            subtotal: result.subtotal,
            discount: result.discount,
            taxAmount: result.tax_amount,
            total: result.total_amount,
            paymentMethod: result.payment_method,
            customerName: selectedCustomer.value?.name,
            amountTendered: paymentMethod.value === 'cash' ? amountTendered.value : undefined,
            changeDue: paymentMethod.value === 'cash' ? Math.max(0, amountTendered.value - cartTotal.value) : undefined,
            offline: (result as any).offline === true,
            date: new Date().toISOString(),
            efrisFdn: (result as any).efris_fdn || undefined,
            efrisVerificationCode: (result as any).efris_verification_code || undefined,
        };
        openDrawerAndPrintReceipt(receiptData).catch((err) => {
            console.warn('[POS] Drawer/print error:', err);
        });
    } catch (err: any) {
        checkoutError.value = err.response?.data?.error?.message
            || err.response?.data?.error
            || 'Failed to complete sale.';
    } finally {
        checkoutLoading.value = false;
    }
}

function resetSale() {
    saleSuccess.value = false;
    lastSale.value = null;
    lastCartItems.value = [];
    if (!rememberLast.value) {
        selectedCustomer.value = null;
        promoCode.value = '';
        paymentMethod.value = 'cash';
        gateway.value = '';
        selectedTaxProfileId.value = '';
    }
    amountTendered.value = 0;
    loyaltyPointsToRedeem.value = 0;
    loyaltyDiscount.value = 0;
    checkoutError.value = '';
}

async function handleBarcode() {
    const code = barcodeInput.value.trim();
    if (!code) return;
    // Try local products first
    let product = products.value.find(p => p.barcode?.toLowerCase() === code.toLowerCase());
    // Fallback: call API if not found locally
    if (!product) {
        try {
            const res = await api.get(`/products/by-barcode/${encodeURIComponent(code)}`);
            if (res.data?.data) {
                product = res.data.data;
                // Add stock field to match PosProduct type if missing
                if (product.stock === undefined) product.stock = 0;
            }
        } catch { /* not found */ }
    }
    if (product) {
        addToCart(product);
        barcodeFeedback.value = `Scanned: ${product.name}`;
    } else {
        barcodeFeedback.value = 'Product not found';
    }
    barcodeInput.value = '';
    setTimeout(() => { barcodeFeedback.value = ''; }, 2000);
}

function openCheckout() {
    checkoutError.value = '';
    showCheckout.value = true;
}

async function handleHold() {
    if (!branchId.value || isEmpty.value) return;
    holding.value = true;
    try {
        await pos.holdSale({
            branch_id: branchId.value,
            cart_data: cart.value.map(item => ({
                product: item.product,
                quantity: item.quantity,
            })),
            customer_id: selectedCustomer.value?.id,
            promo_code: promoCode.value || undefined,
            tax_profile_id: selectedTaxProfileId.value || undefined,
            loyalty_points_redeemed: loyaltyPointsToRedeem.value || undefined,
            note: holdNote.value || undefined,
        });
        clearCart();
        holdNote.value = '';
    } catch {
        // error handled silently
    } finally {
        holding.value = false;
    }
}

async function openHeld() {
    if (!branchId.value) return;
    showHeld.value = true;
    await pos.fetchHeldSales(branchId.value);
}

async function handleResume(sale: HeldSale) {
    try {
        clearCart();
        for (const item of sale.cart_data || []) {
            const product = item.product;
            if (product) {
                addToCart(product);
                if (item.quantity > 1) {
                    setQuantity(product.id, item.quantity);
                }
            }
        }
        if (sale.customer) {
            selectedCustomer.value = sale.customer;
        }
        if (sale.promo_code) {
            promoCode.value = sale.promo_code;
        }
        if (sale.tax_profile_id) {
            selectedTaxProfileId.value = sale.tax_profile_id;
        }
        if (sale.loyalty_points_redeemed) {
            loyaltyPointsToRedeem.value = sale.loyalty_points_redeemed;
            if (selectedCustomer.value) {
                await redeemLoyalty();
            }
        }
        showHeld.value = false;
    } catch {
        // error handled silently
    }
}

function canComplete(): boolean {
    if (checkoutLoading.value || isEmpty.value) return false;
    if (paymentMethod.value === 'cash' && amountTendered.value < cartTotal.value) return false;
    return true;
}

function formatCurrency(amount: number | string | null | undefined): string {
    return Number(amount ?? 0).toFixed(2);
}

function formatTimeAgo(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ${mins % 60}m ago`;
    const days = Math.floor(hrs / 24);
    return `${days}d ago`;
}

async function redeemLoyalty() {
    if (!selectedCustomer.value || !loyaltyPointsToRedeem.value || loyaltyPointsToRedeem.value < 1) return;
    try {
        const res = await api.post('/loyalty/redeem', {
            customer_id: selectedCustomer.value.id,
            points: loyaltyPointsToRedeem.value,
        });
        loyaltyDiscount.value = res.data.data?.reward_value || 0;
        selectedCustomer.value.loyalty_points = res.data.data?.remaining_points || 0;
    } catch {
        loyaltyPointsToRedeem.value = 0;
    }
}

function removeLoyaltyRedemption() {
    loyaltyPointsToRedeem.value = 0;
    loyaltyDiscount.value = 0;
}

async function printReceipt() {
    if (!lastSale.value) return;
    const sale = lastSale.value;

    const receiptData: ReceiptData = {
        invoiceNumber: sale.invoice_number,
        branchName: auth.user.value?.branch?.name || 'Store',
        items: lastCartItems.value,
        subtotal: sale.subtotal,
        discount: sale.discount,
        taxAmount: sale.tax_amount,
        total: sale.total_amount,
        paymentMethod: sale.payment_method,
        customerName: selectedCustomer.value?.name,
        amountTendered: paymentMethod.value === 'cash' ? amountTendered.value : undefined,
        changeDue: paymentMethod.value === 'cash' ? Math.max(0, amountTendered.value - cartTotal.value) : undefined,
        offline: (sale as any).offline === true,
        date: new Date().toISOString(),
        efrisFdn: (sale as any).efris_fdn || undefined,
        efrisVerificationCode: (sale as any).efris_verification_code || undefined,
    };

    try {
        const result = await openDrawerAndPrintReceipt(receiptData);
        if (!result.printed) {
            console.warn('[POS] Print failed; receipt was not sent to printer');
        }
    } catch (err) {
        console.error('[POS] Print error:', err);
    }
}
</script>

<template>
    <div class="h-screen flex flex-col bg-surface-alt overflow-hidden pos-page">
        <header class="bg-gradient-to-r from-header-bg via-header-bg to-primary/5 border-b-2 border-primary/50 px-4 py-2 flex items-center justify-between flex-shrink-0 shadow-md">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-primary rounded flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-text-theme">POS Register</span>
                </div>
                <span class="flex items-center gap-1 text-sm text-primary font-semibold">
                    <MapPin class="w-3.5 h-3.5" />
                    {{ branchName }}
                </span>
                <div v-if="openShift" class="flex items-center gap-2 ml-1">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-surface-alt border border-success/40 text-text-theme text-xs font-bold">
                        <span class="w-1.5 h-1.5 rounded-full bg-success inline-block animate-pulse"></span>
                        Shift: ${{ openShift.opening_balance }}
                    </span>
                    <span class="text-xs text-text-tertiary">· {{ formatTimeAgo(openShift.opened_at) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-xs text-text-theme text-right leading-tight">
                    <div class="font-semibold">{{ now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) }}</div>
                    <div class="font-medium">{{ now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}</div>
                </div>
                <button @click="router.visit('/dashboard')" class="text-sm font-medium text-text-theme hover:text-primary transition-colors">Dashboard</button>
                <div v-if="isOffline" class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-red-100 border border-red-300 text-red-800 text-xs font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-600 inline-block"></span>
                    OFFLINE MODE
                </div>
                <div v-else-if="pendingSalesCount > 0" class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-100 border border-amber-300 text-amber-800 text-xs font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600 inline-block animate-pulse"></span>
                    {{ pendingSalesCount }} PENDING SYNC
                </div>
                <button v-if="pendingSalesCount > 0 && isOnline && !isSyncing"
                    @click="syncPendingSales()"
                    class="h-7 px-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition-colors flex items-center gap-1"
                    title="Sync pending offline sales to server">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Sync Now
                </button>
                <GlobalSyncIndicator :show-label="false" variant="dot" />
                <div class="flex items-center gap-2">
                    <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="" class="w-7 h-7 rounded-full object-cover" />
                    <div v-else class="w-7 h-7 bg-primary rounded-full flex items-center justify-center text-white text-xs font-semibold">
                        {{ auth.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                    <span class="text-sm font-medium text-text-theme">{{ auth.user?.name }}</span>
                </div>
                <button v-if="openShift" type="button" @click.stop.prevent="openCloseDialog()"
                    id="close-register-btn"
                    class="h-9 px-3 bg-surface-alt text-text-theme border border-warning/30 rounded-lg text-xs font-bold hover:bg-surface transition-colors whitespace-nowrap">
                    Close Register
                </button>
            </div>
        </header>

        <div class="bg-gradient-to-r from-primary/5 via-surface-alt to-primary/5 border-b border-primary/20 px-4 py-2 flex items-center gap-3 shadow-inner">
            <div class="relative flex-1 max-w-xs">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <InputText
                    ref="barcodeInputEl"
                    v-model="barcodeInput"
                    @keydown.enter.prevent="handleBarcode"
                    placeholder="Scan barcode... (Ctrl+B)"
                    class="w-full pl-8 pr-3 py-1.5 text-sm bg-input-bg text-text-theme placeholder:text-text-tertiary border-primary/20 focus:border-primary"
                />
            </div>
            <span v-if="barcodeFeedback" class="text-xs font-medium" :class="barcodeFeedback.startsWith('Scanned') ? 'text-success-theme' : 'text-danger-theme'">
                {{ barcodeFeedback }}
            </span>
        </div>

        <div class="flex flex-1 overflow-hidden">
            <div class="flex-1 flex flex-col overflow-hidden border-r border-border-theme">
                <ProductGrid
                    :products="products"
                    :categories="categories"
                    :loading="loading"
                    @add-to-cart="addToCart"
                />
            </div>

            <div class="w-96 flex flex-col flex-shrink-0 border-l-2 border-primary/20 shadow-lg">
                <div v-if="!isEmpty" class="border-b border-border-theme px-4 py-2 flex gap-2">
                    <button type="button" @click="handleHold" :disabled="holding"
                        class="flex-1 h-9 px-3 bg-surface-alt text-text-theme border border-warning/30 rounded-lg text-xs font-bold hover:bg-surface transition-colors disabled:opacity-50">
                        {{ holding ? 'Holding...' : 'Hold Sale' }}
                    </button>
                    <button type="button" @click="openHeld"
                        class="h-9 px-3 bg-surface-alt border border-border-theme text-text-theme rounded-lg text-xs font-medium hover:bg-surface transition-colors flex items-center gap-1.5">
                        <Clock class="w-3.5 h-3.5" />
                        Held
                    </button>
                </div>
                <div v-else class="border-b border-border-theme px-4 py-2 flex">
                    <button type="button" @click="openHeld"
                        class="h-9 px-3 bg-surface-alt border border-border-theme text-text-theme rounded-lg text-xs font-medium hover:bg-surface transition-colors flex items-center gap-1.5">
                        <Clock class="w-3.5 h-3.5" />
                        Held Sales
                    </button>
                </div>
                <div class="flex-1 flex flex-col min-h-0">
                    <CartPanel
                        :cart="cart"
                        :cart-item-count="cartItemCount"
                        :subtotal="cartSubtotal"
                        :tax="cartTax"
                        :total="cartTotal"
                        :is-empty="isEmpty"
                        @update-quantity="updateQuantity"
                        @set-quantity="setQuantity"
                        @remove="removeFromCart"
                        @clear="clearCart"
                        @checkout="openCheckout"
                    />
                </div>
            </div>
        </div>

        <div v-if="showCheckout" class="fixed inset-0 bg-surface-overlay flex items-center justify-center z-50 animate-fade-in">
            <div class="bg-gradient-to-br from-surface-raised to-primary/5 rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 max-h-[90vh] overflow-y-auto border border-primary/20 animate-scale-in">
                <h3 class="text-lg font-bold text-text-theme mb-4 flex items-center gap-2">
                    <ShoppingCart class="w-5 h-5 text-primary" />
                    Complete Sale
                </h3>

                <div v-if="checkoutError" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">
                    {{ checkoutError }}
                </div>

                <div class="space-y-4">
                    <CustomerSelect v-model="selectedCustomer" />

                    <div v-if="selectedCustomer && selectedCustomer.loyalty_points > 0" class="border border-warning-theme/30 rounded-lg p-3 bg-warning-light">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-warning-theme">Loyalty Points</span>
                            <span class="text-sm text-warning-theme/80">{{ selectedCustomer.loyalty_points }} available</span>
                        </div>
                        <div v-if="loyaltyDiscount === 0" class="flex gap-2">
                            <input
                                v-model.number="loyaltyPointsToRedeem"
                                type="number"
                                min="0"
                                :max="selectedCustomer.loyalty_points"
                                placeholder="Points to redeem"
                                class="flex-1 border border-border-input rounded px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme"
                            />
                            <button
                                @click="redeemLoyalty"
                                :disabled="!loyaltyPointsToRedeem || loyaltyPointsToRedeem < 1"
                                class="px-3 py-1.5 bg-btn-primary text-btn-primary-text rounded text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50"
                                type="button"
                            >
                                Apply
                            </button>
                        </div>
                        <div v-else class="flex items-center justify-between text-sm">
                            <span class="text-success-theme font-medium">Loyalty discount: -{{ formatCurrency(loyaltyDiscount) }}</span>
                            <button @click="removeLoyaltyRedemption" class="text-xs text-danger-theme hover:underline" type="button">Remove</button>
                        </div>
                    </div>

                    <PromoCodeInput v-model="promoCode" />

                    <div v-if="taxProfiles.length > 0">
                        <label class="block text-sm font-medium text-text-secondary mb-1">Tax Profile</label>
                        <select
                            v-model="selectedTaxProfileId"
                            class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme"
                        >
                            <option value="">Default</option>
                            <option
                                v-for="tp in taxProfiles"
                                :key="tp.id"
                                :value="tp.id"
                            >
                                {{ tp.name }} ({{ tp.rate }}% - {{ tp.type }})
                            </option>
                        </select>
                    </div>

                    <PaymentForm
                        v-model="paymentMethod"
                        v-model:amount-tendered="amountTendered"
                        v-model:gateway="gateway"
                        :total="cartTotal"
                    />

                    <label class="flex items-center gap-2 text-sm text-text-secondary cursor-pointer">
                        <input type="checkbox" v-model="rememberLast" class="rounded border-border-input text-primary focus:ring-primary-ring" />
                        Remember selections for next sale
                    </label>

                    <div class="flex gap-3">
                        <button
                            @click="showCheckout = false"
                            class="flex-1 px-4 py-2.5 border border-border-theme rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="handleCheckout"
                            :disabled="!canComplete()"
                            class="flex-1 px-4 py-2.5 bg-gradient-to-r from-primary to-primary/80 text-white rounded-lg text-sm font-bold hover:from-primary-hover hover:to-primary disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2"
                        >
                            <ShoppingCart class="w-4 h-4" />
                            {{ checkoutLoading ? 'Processing...' : 'Complete Sale' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showHeld" class="fixed inset-0 bg-surface-overlay flex items-center justify-center z-50 animate-fade-in">
            <div class="bg-gradient-to-br from-surface-raised to-warning/5 rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 max-h-[80vh] flex flex-col border border-warning/20 animate-scale-in">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-text-theme flex items-center gap-2">
                        <Clock class="w-5 h-5 text-warning" />
                        Held Sales
                    </h3>
                    <button @click="showHeld = false" class="text-text-tertiary hover:text-text-secondary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div v-if="pos.heldSales.value.length === 0" class="flex-1 flex items-center justify-center text-text-tertiary text-sm py-12">
                    No held sales found.
                </div>
                <div v-else class="flex-1 overflow-y-auto space-y-2">
                    <div
                        v-for="sale in pos.heldSales.value"
                        :key="sale.id"
                        class="flex items-center justify-between p-3 rounded-lg border border-border-theme hover:bg-surface-alt"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <Clock class="w-4 h-4 text-warning-theme flex-shrink-0" />
                                <span class="text-sm font-medium text-text-theme truncate">
                                    {{ sale.customer?.name || 'Walk-in' }}
                                </span>
                            </div>
                            <p class="text-xs text-text-tertiary mt-0.5">
                                {{ sale.cart_data?.length || 0 }} items &middot; {{ new Date(sale.created_at).toLocaleString() }}
                            </p>
                            <p v-if="sale.note" class="text-xs text-text-tertiary mt-0.5 truncate">{{ sale.note }}</p>
                        </div>
                        <button
                            @click="handleResume(sale)"
                            class="flex items-center gap-1 px-3 py-1.5 bg-btn-primary text-btn-primary-text text-xs font-medium rounded-lg hover:bg-btn-primary-hover transition-colors flex-shrink-0 ml-3"
                        >
                            <Play class="w-3.5 h-3.5" />
                            Resume
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="saleSuccess" class="fixed inset-0 bg-surface-overlay flex items-center justify-center z-50 animate-fade-in">
            <div class="bg-gradient-to-br from-surface-raised to-primary/5 rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6 text-center border border-primary/20 animate-scale-in">
                <div v-if="lastSale && (lastSale as any).offline"
                    class="w-16 h-16 bg-gradient-to-br from-amber-500 to-amber-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-amber-500/20">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div v-else
                    class="w-16 h-16 bg-gradient-to-br from-success to-success/70 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-success/20">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-text-theme mb-2">
                    {{ (lastSale as any)?.offline ? 'Sale Saved Offline' : 'Sale Complete!' }}
                </h3>
                <p class="text-sm text-text-secondary mb-1">Invoice: {{ lastSale?.invoice_number }}</p>
                <p class="text-2xl font-bold text-text-theme mb-4">{{ formatCurrency(lastSale?.total_amount || 0) }}</p>

                <div v-if="(lastSale as any)?.offline"
                    class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-left">
                    <p class="text-xs text-amber-900">
                        <strong>Offline mode:</strong> This sale was saved locally and will
                        automatically sync to the server when you're back online.
                    </p>
                </div>

                <div v-if="lastSale?.discount > 0" class="text-xs text-success-theme mb-3">
                    Discount applied: -{{ formatCurrency(lastSale?.discount) }}
                </div>

                <div v-if="selectedCustomer" class="text-xs text-warning-theme mb-3">
                    Customer: {{ selectedCustomer.name }}
                </div>

                <div class="flex gap-3">
                    <button
                        @click="printReceipt"
                        class="flex-1 px-4 py-2.5 border border-border-theme rounded-lg text-sm font-medium text-text-secondary hover:bg-surface-alt transition-colors"
                    >
                        Print Receipt
                    </button>
                    <button type="button"
                        @click="resetSale"
                        class="flex-1 px-4 py-2.5 bg-gradient-to-r from-primary to-primary/80 text-white rounded-lg text-sm font-bold hover:from-primary-hover hover:to-primary transition-all shadow-md hover:shadow-lg"
                    >
                        New Sale
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast notifications -->
        <Toast position="bottom-right" />

        <!-- Open Shift Modal -->
        <div v-if="showOpenShift" class="fixed inset-0 z-50 flex items-center justify-center bg-surface-overlay animate-fade-in" @click.self="() => {}">
            <div class="bg-surface-raised rounded-xl shadow-2xl w-full max-w-md mx-4 border border-border-theme overflow-hidden animate-scale-in">
                <div class="px-6 py-4 bg-primary/10 border-b border-primary/20 flex items-center gap-2.5">
                    <Wallet class="w-5 h-5 text-primary" />
                    <h3 class="text-lg font-bold text-primary">Open Cash Register</h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-text-secondary">You must open the register before you can start selling. Enter the change fund amount to begin.</p>
                    <div class="bg-primary/5 border border-primary/10 rounded-lg p-4 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-text-theme mb-1.5">Opening Balance (Change Fund)</label>
                            <div class="relative">
                                <DollarSign class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-tertiary" />
                                <InputText v-model.number="shiftOpeningBalance" type="number" min="0" step="0.01" class="w-full text-lg font-bold pl-9 bg-input-bg text-text-theme" placeholder="0.00" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-text-theme mb-1.5">Confirm Password</label>
                            <InputText v-model="shiftPassword" type="password" class="w-full bg-input-bg text-text-theme" placeholder="Enter your password" @keydown.enter="openRegister" />
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-surface-alt border-t border-border-theme flex gap-3">
                    <button type="button" @click="openRegister" class="flex-1 h-11 bg-gradient-to-r from-primary to-primary/80 text-white rounded-lg font-bold hover:from-primary-hover hover:to-primary transition-all shadow-md hover:shadow-lg text-sm flex items-center justify-center gap-2">
                        Open Register
                    </button>
                    <button type="button" @click="router.visit('/dashboard')" class="flex-1 h-11 bg-btn-secondary-bg text-btn-secondary-text border border-btn-secondary-border rounded-lg font-medium hover:bg-btn-secondary-hover transition-all text-sm">
                        Go to Dashboard
                    </button>
                </div>
            </div>
        </div>

        <!-- Close Shift Modal -->
        <div v-if="showCloseShift" class="fixed inset-0 z-50 flex items-center justify-center bg-surface-overlay animate-fade-in" @click.self="showCloseShift = false">
            <div class="bg-surface-raised rounded-xl shadow-2xl w-full max-w-md mx-4 border border-border-theme overflow-hidden animate-scale-in">
                <div class="px-6 py-4 bg-warning/10 border-b border-warning/20 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <LogOut class="w-5 h-5 text-warning" />
                        <h3 class="text-lg font-bold text-warning">Close Register</h3>
                    </div>
                    <button @click="showCloseShift = false" class="p-1.5 rounded-lg hover:bg-surface-alt text-text-tertiary hover:text-text-theme transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-text-secondary">Count the cash in your drawer and enter the total below.</p>

                    <!-- Expected vs Actual summary -->
                    <div v-if="openShift" class="grid grid-cols-2 gap-3">
                        <div class="bg-surface-alt rounded-lg p-3 border border-border-theme">
                            <p class="text-xs text-text-tertiary mb-0.5">Expected</p>
                            <p class="text-lg font-bold text-text-theme">{{ formatCurrency(openShift.expected_balance) }}</p>
                        </div>
                        <div class="bg-primary/5 rounded-lg p-3 border border-primary/10">
                            <p class="text-xs text-text-tertiary mb-0.5">Difference</p>
                            <p class="text-lg font-bold" :class="shiftDiff >= 0 ? 'text-success' : 'text-danger'">
                                {{ shiftDiff >= 0 ? '+' : '' }}{{ formatCurrency(shiftDiff) }}
                            </p>
                        </div>
                    </div>

                    <div class="bg-warning/5 border border-warning/10 rounded-lg p-4 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-text-theme mb-1.5">Actual Cash Count</label>
                            <div class="relative">
                                <DollarSign class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-tertiary" />
                                <InputText v-model.number="shiftActualBalance" type="number" min="0" step="0.01" class="w-full text-lg font-bold pl-9 bg-input-bg text-text-theme" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-text-theme mb-1.5">Notes</label>
                            <textarea v-model="shiftNotes" rows="2" class="w-full px-3 py-2.5 text-sm border border-border-input rounded-lg bg-input-bg text-text-theme outline-none focus:ring-2 focus:ring-primary-ring resize-none" placeholder="Optional notes..." />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-text-theme mb-1.5">Confirm Password</label>
                        <InputText v-model="shiftPassword" type="password" class="w-full bg-input-bg text-text-theme" placeholder="Enter your password to close" @keydown.enter="closeRegister" />
                    </div>
                </div>
                <div class="px-6 py-4 bg-surface-alt border-t border-border-theme flex gap-3">
                    <button type="button" @click="closeRegister" class="flex-1 h-11 bg-gradient-to-r from-warning to-warning/80 text-white rounded-lg font-bold hover:brightness-110 transition-all shadow-md hover:shadow-lg text-sm flex items-center justify-center gap-2">
                        Close Register
                    </button>
                    <button type="button" @click="showCloseShift = false" class="flex-1 h-11 bg-btn-secondary-bg text-btn-secondary-text border border-btn-secondary-border rounded-lg font-medium hover:bg-btn-secondary-hover transition-all text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile cart drawer -->
        <div v-if="showCartMobile" class="fixed inset-0 z-30 flex flex-col md:hidden" @click.self="showCartMobile = false">
            <div class="flex-1 bg-surface-overlay" @click="showCartMobile = false"></div>
            <div class="bg-surface-raised rounded-t-2xl max-h-[70vh] flex flex-col overflow-hidden shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-border-theme">
                    <h2 class="text-sm font-bold text-text-theme">Cart ({{ cartItemCount }} items)</h2>
                    <div class="flex gap-2">
                        <button v-if="!isEmpty" type="button" @click="clearCart; showCartMobile = false"
                            class="h-8 px-2.5 bg-surface-alt border border-border-theme text-text-theme rounded text-xs font-medium hover:bg-surface transition-colors">
                            Clear
                        </button>
                        <button @click="showCartMobile = false" class="p-1 rounded hover:bg-surface-alt">
                            <svg class="w-5 h-5 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-2 space-y-1.5">
                    <div v-if="isEmpty" class="flex items-center justify-center h-full text-text-tertiary text-sm py-8">
                        Tap a product to start
                    </div>
                    <div v-for="item in cart" :key="item.product.id" class="flex items-center gap-2 p-2 rounded-lg hover:bg-surface-alt group">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-text-theme truncate">{{ item.product.name }}</p>
                            <p class="text-xs text-text-tertiary">{{ Number(item.product.price).toFixed(2) }} each</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="updateQuantity(item.product.id, -1)" class="w-7 h-7 flex items-center justify-center rounded bg-surface-alt text-text-secondary text-sm font-bold">-</button>
                            <span class="w-8 text-center text-sm font-bold text-text-theme">{{ item.quantity }}</span>
                            <button @click="updateQuantity(item.product.id, 1)" :disabled="item.quantity >= item.product.stock" class="w-7 h-7 flex items-center justify-center rounded bg-surface-alt text-text-secondary text-sm font-bold disabled:opacity-30">+</button>
                        </div>
                        <div class="text-right min-w-[60px]">
                            <p class="text-sm font-bold text-text-theme">{{ (item.product.price * item.quantity).toFixed(2) }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="!isEmpty" class="border-t border-border-theme px-4 py-3 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-text-tertiary">Subtotal</span>
                        <span class="text-text-theme font-medium">{{ cartSubtotal.toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-text-tertiary">Tax</span>
                        <span class="text-text-theme font-medium">{{ cartTax.toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold">
                        <span class="text-text-theme">Total</span>
                        <span class="text-primary">{{ cartTotal.toFixed(2) }}</span>
                    </div>
                    <button @click="showCartMobile = false; openCheckout()"
                        class="w-full h-11 text-sm font-bold mt-1 bg-gradient-to-r from-primary to-primary/80 text-white rounded-lg hover:from-primary-hover hover:to-primary transition-all shadow-md">
                        Checkout
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile bottom navigation -->
        <BottomNav
            :cart-item-count="cartItemCount"
            @cart="showCartMobile = !showCartMobile"
            @held="openHeld"
            @categories="showCartMobile = false"
            @dashboard="router.visit('/dashboard')"
        />
    </div>
</template>
