<template>
  <AppLayout>
    <div class="license-page">
      <div class="page-header">
        <h1>Desktop App License</h1>
        <p>Purchase a one-time license for ClassicPOS Desktop</p>
      </div>

      <!-- Step 1: Choose Plan -->
      <div v-if="step === 'plan'" class="plans-grid">
        <div class="plan-card" :class="{ selected: selectedPlan === 'professional' }" @click="selectedPlan = 'professional'">
          <div class="plan-badge">Most Popular</div>
          <h3>Professional</h3>
          <div class="price">$150</div>
          <div class="billing">One-time payment</div>
          <ul>
            <li>Full POS with offline mode</li>
            <li>USB/Serial receipt printing</li>
            <li>Cash drawer control</li>
            <li>Sales & inventory reporting</li>
            <li>Multi-branch support</li>
            <li>1 year of updates</li>
          </ul>
          <button class="btn-select" :class="{ active: selectedPlan === 'professional' }">
            {{ selectedPlan === 'professional' ? 'Selected' : 'Select' }}
          </button>
        </div>

        <div class="plan-card enterprise" :class="{ selected: selectedPlan === 'enterprise' }" @click="selectedPlan = 'enterprise'">
          <div class="plan-badge badge-enterprise">Best Value</div>
          <h3>Enterprise</h3>
          <div class="price">$150</div>
          <div class="billing">One-time payment</div>
          <ul>
            <li>Everything in Professional</li>
            <li>Custom integrations</li>
            <li>Priority support</li>
            <li>Lifetime updates</li>
            <li>Unlimited devices</li>
          </ul>
          <button class="btn-select" :class="{ active: selectedPlan === 'enterprise' }">
            {{ selectedPlan === 'enterprise' ? 'Selected' : 'Select' }}
          </button>
        </div>
      </div>

      <div v-if="step === 'plan'" class="next-step">
        <button @click="step = 'details'" :disabled="!selectedPlan" class="btn-primary">
          Continue to Details
        </button>
      </div>

      <!-- Step 2: Enter Details -->
      <div v-if="step === 'details'" class="details-form">
        <h2>Your Details</h2>
        <div class="form-group">
          <label>Business Name</label>
          <input v-model="businessName" placeholder="Enter your business name" />
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input v-model="email" type="email" placeholder="you@business.com" />
          <p class="help-text">License key will be sent to this email</p>
        </div>
        <div class="form-group">
          <label>Confirm Email</label>
          <input v-model="emailConfirm" type="email" placeholder="Confirm your email" />
        </div>

        <div class="selected-plan-summary">
          <strong>{{ selectedPlan === 'professional' ? 'Professional' : 'Enterprise' }}</strong> — $150
        </div>

        <div class="button-row">
          <button @click="step = 'plan'" class="btn-secondary">Back</button>
          <button @click="step = 'payment'" :disabled="!canProceed" class="btn-primary">Continue to Payment</button>
        </div>
      </div>

      <!-- Step 3: Payment -->
      <div v-if="step === 'payment'" class="payment-section">
        <h2>Payment</h2>
        <p>Choose your payment method:</p>

        <div class="payment-methods">
          <button @click="processPayment('paypal')" class="payment-btn paypal">
            <span class="payment-icon">PayPal</span>
            <span>Pay with PayPal</span>
          </button>
          <button @click="processPayment('pesapal')" class="payment-btn pesapal">
            <span class="payment-icon">PesaPal</span>
            <span>Pay with PesaPal</span>
          </button>
        </div>

        <div v-if="paymentError" class="error-message">{{ paymentError }}</div>
        <div v-if="processing" class="processing">
          <div class="spinner"></div>
          <span>Processing payment...</span>
        </div>

        <button @click="step = 'details'" class="btn-secondary" style="margin-top: 1rem;">Back</button>
      </div>

      <!-- Step 4: Success -->
      <div v-if="step === 'success'" class="success-section">
        <div class="success-icon">&#10003;</div>
        <h2>License Purchased!</h2>
        <p>Your license key has been sent to <strong>{{ email }}</strong></p>
        <p>Check your inbox (and spam folder) for the license key email.</p>

        <div class="license-key-display" v-if="licenseKey">
          <p>Your License Key:</p>
          <code>{{ licenseKey }}</code>
          <button @click="copyKey" class="btn-copy">Copy</button>
        </div>

        <div class="next-steps">
          <h3>Next Steps</h3>
          <ol>
            <li>Download ClassicPOS Desktop from <a href="https://github.com/OAK-IT-Solutions/classicpos-Lite/releases" target="_blank">GitHub Releases</a></li>
            <li>Install and launch the application</li>
            <li>Enter your business name and license key</li>
            <li>Start selling!</li>
          </ol>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const step = ref<'plan' | 'details' | 'payment' | 'success'>('plan');
const selectedPlan = ref('');
const businessName = ref('');
const email = ref('');
const emailConfirm = ref('');
const processing = ref(false);
const paymentError = ref('');
const licenseKey = ref('');

const canProceed = computed(() =>
  businessName.value.trim().length > 0 &&
  email.value.includes('@') &&
  email.value === emailConfirm.value
);

async function processPayment(method: string) {
  processing.value = true;
  paymentError.value = '';

  try {
    // Create purchase
    const purchase = await axios.post('/api/v1/desktop/license/purchase', {
      business_name: businessName.value,
      email: email.value,
      plan: selectedPlan.value,
      payment_method: method,
    });

    // For demo/testing: complete immediately
    // In production, this would redirect to Pesapal/PayPal
    const complete = await axios.post('/api/v1/desktop/license/complete', {
      payment_id: purchase.data.payment_id,
      transaction_id: 'demo_' + Date.now(),
    });

    licenseKey.value = complete.data.license_key;
    step.value = 'success';
  } catch (e: any) {
    paymentError.value = e.response?.data?.message || 'Payment failed. Please try again.';
  } finally {
    processing.value = false;
  }
}

function copyKey() {
  navigator.clipboard.writeText(licenseKey.value);
}
</script>

<style scoped>
.license-page { max-width: 800px; margin: 0 auto; }
.page-header { margin-bottom: 2rem; }
.page-header h1 { font-size: 1.5rem; color: #1e293b; margin: 0; }
.page-header p { color: #64748b; margin: 0.5rem 0 0; }

.plans-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.plan-card { background: white; border: 2px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; cursor: pointer; transition: all 0.2s; position: relative; }
.plan-card:hover { border-color: #3b82f6; }
.plan-card.selected { border-color: #3b82f6; background: #eff6ff; }
.plan-card.enterprise { border-color: #7c3aed; }
.plan-card.enterprise.selected { background: #f5f3ff; }
.plan-badge { position: absolute; top: -10px; right: 16px; background: #3b82f6; color: white; padding: 2px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.badge-enterprise { background: #7c3aed; }
.plan-card h3 { margin: 0 0 0.5rem; font-size: 1.25rem; }
.price { font-size: 2rem; font-weight: 700; color: #1e293b; }
.billing { color: #64748b; font-size: 0.85rem; margin-bottom: 1rem; }
.plan-card ul { list-style: none; padding: 0; margin: 1rem 0; }
.plan-card li { padding: 0.375rem 0; font-size: 0.9rem; color: #475569; }
.plan-card li::before { content: "✓ "; color: #22c55e; font-weight: bold; }
.btn-select { width: 100%; padding: 0.625rem; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-size: 0.9rem; }
.btn-select.active { background: #3b82f6; color: white; border-color: #3b82f6; }
.next-step { text-align: center; }

.details-form { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; }
.details-form h2 { margin: 0 0 1rem; font-size: 1.1rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 0.25rem; }
.form-group input { width: 100%; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; }
.help-text { font-size: 0.8rem; color: #94a3b8; margin: 0.25rem 0 0; }
.selected-plan-summary { background: #f1f5f9; padding: 0.75rem 1rem; border-radius: 6px; margin: 1rem 0; font-size: 0.95rem; }
.button-row { display: flex; gap: 0.75rem; justify-content: flex-end; }

.payment-section { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; }
.payment-methods { display: flex; gap: 1rem; margin: 1rem 0; }
.payment-btn { flex: 1; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: white; text-align: center; transition: all 0.2s; }
.payment-btn:hover { border-color: #3b82f6; }
.payment-icon { display: block; font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
.paypal .payment-icon { color: #003087; }
.pesapal .payment-icon { color: #1a73e8; }

.success-section { text-align: center; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; }
.success-icon { font-size: 3rem; color: #22c55e; margin-bottom: 1rem; }
.license-key-display { background: #f1f5f9; border-radius: 8px; padding: 1rem; margin: 1.5rem 0; }
.license-key-display code { font-family: monospace; font-size: 1.1rem; display: block; margin: 0.5rem 0; word-break: break-all; }
.btn-copy { padding: 0.375rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
.next-steps { text-align: left; margin-top: 1.5rem; }
.next-steps h3 { font-size: 1rem; margin-bottom: 0.5rem; }
.next-steps ol { padding-left: 1.25rem; }
.next-steps li { margin-bottom: 0.5rem; color: #475569; }

.error-message { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin: 1rem 0; }
.processing { display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin: 1rem 0; }
.spinner { width: 20px; height: 20px; border: 2px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.btn-primary { padding: 0.75rem 1.5rem; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.95rem; }
.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { padding: 0.75rem 1.5rem; background: white; color: #475569; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 0.95rem; }
</style>
