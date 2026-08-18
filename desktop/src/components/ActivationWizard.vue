<template>
  <div class="activation-wizard">
    <div class="wizard-card">
      <div class="wizard-header">
        <div class="logo-icon">
          <svg viewBox="0 0 100 100" width="60" height="60">
            <rect x="10" y="10" width="80" height="80" rx="16" fill="#3b82f6"/>
            <text x="50" y="62" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="system-ui">POS</text>
          </svg>
        </div>
        <h1>ClassicPOS</h1>
        <p class="subtitle">Offline Desktop POS System</p>
      </div>

      <!-- Step 1: Welcome -->
      <div v-if="step === 1" class="wizard-step">
        <h2>Welcome to ClassicPOS</h2>
        <p class="description">
          A powerful offline point-of-sale system for your business.
        </p>
        <ul class="features">
          <li>Works completely offline</li>
          <li>Unlimited products and sales</li>
          <li>USB barcode scanner support</li>
          <li>Thermal receipt printing</li>
          <li>Multi-branch support</li>
        </ul>
        <button class="btn btn-primary btn-lg" @click="step = 2">Get Started</button>
      </div>

      <!-- Step 2: Activate -->
      <div v-if="step === 2" class="wizard-step">
        <h2>Activate Your License</h2>
        <p class="description">
          Enter your business name and license key to activate ClassicPOS.
        </p>

        <div class="form-group">
          <input
            v-model="businessName"
            type="text"
            class="form-input form-input-text"
            placeholder="Your Business Name"
          />
        </div>

        <div class="form-group">
          <input
            v-model="licenseKey"
            type="text"
            class="form-input"
            placeholder="CPPOS-XXXX-XXXX-XXXX-XXXX"
            @keyup.enter="activate"
          />
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <button
          class="btn btn-primary btn-lg"
          :disabled="!licenseKey.trim() || !businessName.trim() || loading"
          @click="activate"
        >
          {{ loading ? 'Activating...' : 'Activate' }}
        </button>

        <div class="divider">
          <span>or</span>
        </div>

        <div class="demo-buttons">
          <button
            class="btn btn-outline"
            :disabled="loading || demoLoading"
            @click="activateDemo"
          >
            {{ demoLoading ? 'Generating...' : 'Skip (Demo Mode)' }}
          </button>
        </div>
      </div>

      <!-- Step 3: Activated -->
      <div v-if="step === 3" class="wizard-step">
        <div class="success-icon">&#10003;</div>
        <h2>Activated Successfully!</h2>
        <p class="description">
          Your license has been activated. Starting the application...
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { isElectron, apiRequest } from '../services/ElectronBridge';

const emit = defineEmits<{
  activated: [];
}>();

const step = ref(1);
const businessName = ref('');
const licenseKey = ref('');
const loading = ref(false);
const demoLoading = ref(false);
const error = ref('');

async function activate() {
  if (!licenseKey.value.trim() || !businessName.value.trim()) return;

  loading.value = true;
  error.value = '';

  try {
    const resp = await apiRequest('POST', '/api/v1/desktop/license/activate', JSON.stringify({
      key: licenseKey.value.trim(),
      business_name: businessName.value.trim(),
    }));
    const data = JSON.parse(resp.body);

    if (resp.status === 200 && data.activated) {
      localStorage.setItem('classicpos_license', JSON.stringify(data.license));
      step.value = 3;
      setTimeout(() => emit('activated'), 2000);
    } else {
      error.value = data.error || data.message || 'Invalid license key';
    }
  } catch (e: any) {
    error.value = 'Network error. Please try again.';
  } finally {
    loading.value = false;
  }
}

async function activateDemo() {
  demoLoading.value = true;
  error.value = '';

  const name = businessName.value.trim() || 'Demo Business';

  try {
    const resp = await apiRequest('GET', `/api/v1/desktop/license/generate-demo?business_name=${encodeURIComponent(name)}`);
    const data = JSON.parse(resp.body);

    if (resp.status === 200 && data.key) {
      localStorage.setItem('classicpos_license', JSON.stringify({
        key: data.key,
        business_name: name,
        expires_at: data.expires_at,
        features: data.features,
        activated_at: data.issued,
      }));
      step.value = 3;
      setTimeout(() => emit('activated'), 2000);
    } else {
      error.value = data.error || data.message || 'Failed to generate demo license';
    }
  } catch (e: any) {
    error.value = 'Network error. Please try again.';
  } finally {
    demoLoading.value = false;
  }
}
</script>

<style scoped>
.activation-wizard {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  color: #e2e8f0;
  padding: 2rem;
}

.wizard-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 16px;
  padding: 3rem;
  max-width: 480px;
  width: 100%;
  text-align: center;
}

.wizard-header {
  margin-bottom: 2rem;
}

.wizard-header h1 {
  margin: 1rem 0 0.5rem;
  font-size: 2rem;
  font-weight: 700;
}

.subtitle {
  color: #94a3b8;
}

.wizard-step h2 {
  margin-bottom: 1rem;
  font-size: 1.5rem;
}

.description {
  color: #94a3b8;
  margin-bottom: 1.5rem;
  line-height: 1.6;
}

.features {
  text-align: left;
  list-style: none;
  padding: 0;
  margin: 0 0 2rem;
}

.features li {
  padding: 0.5rem 0;
  color: #cbd5e1;
  font-size: 0.95rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-input {
  width: 100%;
  padding: 0.75rem 1rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 8px;
  color: #e2e8f0;
  font-size: 1rem;
  font-family: monospace;
  text-align: center;
  letter-spacing: 2px;
  outline: none;
  transition: border-color 0.2s;
}

.form-input-text {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  letter-spacing: normal;
  text-align: left;
}

.form-input:focus {
  border-color: #3b82f6;
}

.error {
  color: #ef4444;
  font-size: 0.9rem;
  margin-bottom: 1rem;
}

.btn {
  padding: 0.75rem 2rem;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-outline {
  background: transparent;
  border: 1px solid #475569;
  color: #94a3b8;
  padding: 0.625rem 1.25rem;
  font-size: 0.9rem;
}

.btn-outline:hover:not(:disabled) {
  background: #1e293b;
  border-color: #64748b;
  color: #e2e8f0;
}

.btn-outline:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-lg {
  padding: 1rem 2.5rem;
  font-size: 1.1rem;
}

.divider {
  display: flex;
  align-items: center;
  margin: 1.5rem 0;
  color: #475569;
  font-size: 0.85rem;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid #334155;
}

.divider::before {
  margin-right: 0.75rem;
}

.divider::after {
  margin-left: 0.75rem;
}

.demo-buttons {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
}

.success-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #22c55e;
  color: white;
  font-size: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
}
</style>
