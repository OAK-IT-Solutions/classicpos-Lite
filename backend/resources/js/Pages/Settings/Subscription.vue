<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';
import PayPalButton from '@/Components/PayPalButton.vue';

defineProps<{ embedded?: boolean }>();

interface LandlordSubscription {
    id: string;
    plan: { id: string; name: string; price_monthly: number; price_yearly: number };
    status: string;
    billing_cycle: string;
    starts_at: string | null;
    ends_at: string | null;
    trial_ends_at: string | null;
    amount: number | null;
    original_amount: number | null;
    discount_percent: number | null;
}

interface PlanDetail {
    id: string;
    name: string;
    description: string;
    price_monthly: number;
    price_yearly: number;
    max_branches: number;
    max_users_per_branch: number;
    max_devices_per_branch: number;
    features: { id: string; name: string; slug: string }[];
}

declare global {
    interface Window {
        paypal?: any;
    }
}

const sub = ref<LandlordSubscription | null>(null);
const plans = ref<PlanDetail[]>([]);
const loading = ref(true);
const error = ref('');
const saving = ref(false);
const cancelling = ref(false);
const success = ref('');
const paypalLoaded = ref(false);

const selectedPlanId = ref('');
const selectedBilling = ref('monthly');
const selectedGateway = ref<'paypal' | 'pesapal'>('paypal');

const isActive = computed(() => sub.value && ['active', 'trialing', 'pending'].includes(sub.value.status));
const isTrial = computed(() => sub.value?.status === 'trialing');

const currentPlanName = computed(() => sub.value?.plan?.name || 'N/A');

const statusClass = computed(() => {
    const s = sub.value?.status;
    if (s === 'active') return 'bg-success-light text-success-theme';
    if (s === 'trialing') return 'bg-primary-light text-primary';
    if (s === 'past_due') return 'bg-warning-light text-warning-theme';
    if (s === 'cancelled' || s === 'expired') return 'bg-danger-light text-danger-theme';
    return 'bg-surface-alt text-text-secondary';
});

function fmt(n: number | null | undefined) {
    if (n == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n);
}

async function fetchAll() {
    try {
        const [subRes, plansRes] = await Promise.all([
            api.get('/billing/subscription'),
            api.get('/subscriptions/plans'),
        ]);
        sub.value = subRes.data?.subscription ?? null;
        plans.value = Array.isArray(plansRes.data) ? plansRes.data : (plansRes.data?.plans || []);
        if (sub.value?.plan?.id) {
            selectedPlanId.value = sub.value.plan.id;
            selectedBilling.value = sub.value.billing_cycle || 'monthly';
        } else if (plans.value.length > 0) {
            selectedPlanId.value = plans.value[0].id;
        }
        loadPayPalSDK();
    } catch (err: any) {
        error.value = 'Failed to load subscription.';
    } finally {
        loading.value = false;
    }
}

function loadPayPalSDK() {
    if (typeof window.paypal !== 'undefined') { paypalLoaded.value = true; return; }
    const clientId = (usePage().props as any).paypal_client_id || '';
    if (!clientId) return;
    const script = document.createElement('script');
    script.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&currency=USD`;
    script.async = true;
    script.onload = () => { paypalLoaded.value = true; };
    document.head.appendChild(script);
}

async function pesapalCheckout() {
    error.value = '';
    success.value = '';
    saving.value = true;
    try {
        const res = await api.post('/billing/checkout', {
            plan_id: selectedPlanId.value,
            billing_cycle: selectedBilling.value,
        });
        success.value = 'Redirecting to payment...';
        window.location.href = res.data.checkout_url;
    } catch (err: any) {
        error.value = err.response?.data?.error || err.response?.data?.message || 'Failed to start checkout.';
    } finally {
        saving.value = false;
    }
}

let currentOrderId = '';

async function createPayPalOrder() {
    try {
        const res = await api.post('/billing/paypal/create-order', {
            plan_id: selectedPlanId.value,
            billing_cycle: selectedBilling.value,
        });
        currentOrderId = res.data.order_id;
        return res.data.order_id;
    } catch (err: any) {
        error.value = err.response?.data?.error || 'Failed to create PayPal order.';
        throw new Error(error.value);
    }
}

async function onPayPalApprove(data: any) {
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        const res = await api.post(`/billing/paypal/capture/${data.orderID}`);
        if (res.data.status === 'success') {
            success.value = 'Payment successful! Your subscription is now active.';
            await fetchAll();
        } else {
            error.value = 'Payment was not completed. Please try again.';
        }
    } catch (err: any) {
        error.value = err.response?.data?.error || 'Failed to capture payment.';
    } finally {
        saving.value = false;
    }
}

function onPayPalCancel() {
    error.value = 'Payment cancelled.';
}

async function cancelSubscription() {
    if (!confirm('Cancel your subscription? Your access will continue until the end of the billing period.')) return;
    cancelling.value = true;
    error.value = '';
    success.value = '';
    try {
        await api.post('/subscriptions/cancel');
        success.value = 'Subscription cancelled.';
        await fetchAll();
    } catch (err: any) {
        error.value = err.response?.data?.error || 'Failed to cancel.';
    } finally {
        cancelling.value = false;
    }
}

function planLimit(val: number) {
    return val >= 999999 ? 'Unlimited' : String(val);
}

onMounted(fetchAll);
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-4xl mx-auto'">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-text-theme">Subscription & Billing</h1>
                <p class="text-text-tertiary mt-1">Manage your plan and billing settings</p>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>
            <div v-if="success" class="mb-4 p-3 bg-success-light border border-success-theme/20 rounded-lg text-sm text-success-theme">{{ success }}</div>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <template v-else>
                <!-- Current Plan -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-text-theme">Current Plan</h2>
                        <span v-if="sub" class="px-3 py-1 text-xs font-medium rounded-full capitalize" :class="statusClass">
                            {{ sub.status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div class="bg-surface-alt rounded-lg p-4">
                            <p class="text-sm text-text-tertiary mb-1">Plan</p>
                            <p class="text-lg font-semibold text-text-theme">{{ currentPlanName }}</p>
                        </div>
                        <div class="bg-surface-alt rounded-lg p-4">
                            <p class="text-sm text-text-tertiary mb-1">Billing</p>
                            <p class="text-lg font-semibold text-text-theme capitalize">{{ sub?.billing_cycle || '—' }}</p>
                        </div>
                        <div class="bg-surface-alt rounded-lg p-4">
                            <p class="text-sm text-text-tertiary mb-1">Amount</p>
                            <p class="text-lg font-semibold text-text-theme">{{ sub?.amount ? fmt(sub.amount) : '—' }}</p>
                        </div>
                        <div class="bg-surface-alt rounded-lg p-4">
                            <p class="text-sm text-text-tertiary mb-1">Status</p>
                            <p class="text-lg font-semibold text-text-theme capitalize">{{ sub?.status || 'No subscription' }}</p>
                        </div>
                    </div>

                    <div v-if="sub?.discount_percent" class="text-sm text-success-theme bg-success-light px-3 py-1.5 rounded-lg inline-block mb-4">
                        {{ sub.discount_percent }}% discount applied
                    </div>

                    <div v-if="sub?.trial_ends_at" class="text-sm text-text-tertiary">
                        Trial ends: {{ new Date(sub.trial_ends_at).toLocaleDateString() }}
                    </div>
                    <div v-if="sub?.ends_at && sub.status !== 'trialing'" class="text-sm text-text-tertiary">
                        {{ sub.status === 'cancelled' ? 'Access until' : 'Renews' }}: {{ new Date(sub.ends_at).toLocaleDateString() }}
                    </div>
                </div>

                <!-- Available Plans -->
                <div v-if="plans.length" class="grid md:grid-cols-3 gap-4 mb-6">
                    <div v-for="plan in plans" :key="plan.id"
                        @click="selectedPlanId = plan.id; selectedBilling = 'monthly'"
                        :class="['bg-surface-raised rounded-xl border-2 p-5 cursor-pointer transition-all hover:shadow-md', selectedPlanId === plan.id ? 'border-primary' : 'border-border-theme']"
                    >
                        <h3 class="font-semibold text-text-theme mb-1">{{ plan.name }}</h3>
                        <p class="text-xs text-text-tertiary mb-2">{{ plan.description }}</p>
                        <p class="text-xl font-bold text-text-theme">{{ fmt(plan.price_monthly) }}<span class="text-sm font-normal text-text-tertiary">/mo</span></p>
                        <p v-if="plan.price_yearly > 0" class="text-xs text-primary mt-1">{{ fmt(plan.price_yearly) }}/yr</p>
                        <div class="flex flex-wrap gap-1 mt-3">
                            <span v-for="f in plan.features?.slice(0, 3)" :key="f.id"
                                class="text-xs bg-primary-light text-primary px-2 py-0.5 rounded-full">{{ f.name }}</span>
                            <span v-if="plan.features?.length > 3" class="text-xs text-text-tertiary">+{{ plan.features.length - 3 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Billing Actions -->
                <div v-if="isActive || isTrial" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6 mb-6">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">{{ sub?.status === 'trialing' ? 'Start Paid Plan' : 'Change Plan' }}</h2>
                    <div class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Plan</label>
                            <select v-model="selectedPlanId" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm bg-surface-raised">
                                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }} - {{ fmt(p.price_monthly) }}/mo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Billing Cycle</label>
                            <select v-model="selectedBilling" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm bg-surface-raised">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly (Save up to 17%)</option>
                            </select>
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-2">Payment Method</label>
                            <div class="flex gap-3">
                                <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-surface-alt"
                                    :class="selectedGateway === 'paypal' ? 'border-primary bg-primary-light' : 'border-border-input'">
                                    <input type="radio" v-model="selectedGateway" value="paypal" class="accent-primary" />
                                    <span class="text-sm font-medium text-text-theme">PayPal</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-surface-alt"
                                    :class="selectedGateway === 'pesapal' ? 'border-primary bg-primary-light' : 'border-border-input'">
                                    <input type="radio" v-model="selectedGateway" value="pesapal" class="accent-primary" />
                                    <span class="text-sm font-medium text-text-theme">Pesapal</span>
                                </label>
                            </div>
                        </div>

                        <!-- PayPal Button -->
                        <div v-if="selectedGateway === 'paypal'" id="paypal-button-container" class="pt-2">
                            <div v-if="!paypalLoaded" class="text-sm text-text-tertiary">Loading PayPal...</div>
                            <PayPalButton v-else :create-order="createPayPalOrder" :on-approve="onPayPalApprove" :on-cancel="onPayPalCancel" :disabled="saving" />
                        </div>

                        <!-- Pesapal Button -->
                        <button v-if="selectedGateway === 'pesapal'" @click="pesapalCheckout" :disabled="saving"
                            class="w-full px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50">
                            {{ saving ? 'Processing...' : sub?.status === 'trialing' ? 'Start Subscription' : 'Change Plan & Pay' }}
                        </button>
                    </div>
                </div>

                <!-- Cancel -->
                <div v-if="isActive" class="bg-surface-raised rounded-xl border border-danger-theme/20 p-6">
                    <h2 class="text-lg font-semibold text-danger-theme mb-2">Cancel Subscription</h2>
                    <p class="text-sm text-text-secondary mb-4">Your access continues until the end of the current billing period.</p>
                    <button @click="cancelSubscription" :disabled="cancelling"
                        class="px-4 py-2.5 bg-danger-theme text-white rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-50">
                        {{ cancelling ? 'Cancelling...' : 'Cancel Subscription' }}
                    </button>
                </div>
            </template>
        </div>
    </component>
</template>
