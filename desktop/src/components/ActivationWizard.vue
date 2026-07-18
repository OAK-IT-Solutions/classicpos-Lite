<template>
  <div class="activation-screen">
    <div class="activation-card">
      <div class="logo">
        <svg viewBox="0 0 100 100" width="64" height="64">
          <rect x="10" y="10" width="80" height="80" rx="16" fill="#3b82f6"/>
          <text x="50" y="62" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="system-ui">POS</text>
        </svg>
        <h1>ClassicPOS</h1>
        <p class="subtitle">Offline Desktop POS System</p>
      </div>

      <!-- Step 1: Welcome -->
      <div v-if="step === 'welcome'" class="step">
        <h2>Welcome to ClassicPOS</h2>
        <p>Enter your license key to activate the desktop app. You can find your license key in your purchase confirmation email.</p>
        <button @click="step = 'activate'" class="btn-primary">Enter License Key</button>
      </div>

      <!-- Step 2: Activation -->
      <div v-if="step === 'activate'" class="step">
        <h2>Activate Your License</h2>

        <div class="form-group">
          <label>Business Name</label>
          <input v-model="businessName" type="text" placeholder="Enter your business name" />
        </div>

        <div class="form-group">
          <label>License Key</label>
          <input v-model="licenseKey" type="text" placeholder="CPPOS-XXXX-XXXX-XXXX-XXXX"
                 class="license-input" maxlength="48" @keyup.enter="activate" />
        </div>

        <div v-if="error" class="error-message">
          <i class="pi pi-exclamation-circle"></i>
          {{ error }}
        </div>

        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <span>Verifying license...</span>
        </div>

        <div class="button-row">
          <button @click="step = 'welcome'" class="btn-secondary">Back</button>
          <button @click="activate" :disabled="!canActivate || loading" class="btn-primary">
            Activate
          </button>
        </div>
      </div>

      <!-- Step 3: Success -->
      <div v-if="step === 'success'" class="step success-step">
        <div class="success-icon">
          <i class="pi pi-check-circle"></i>
        </div>
        <h2>License Activated!</h2>
        <p>ClassicPOS is now ready to use. Your license is valid until <strong>{{ expiresAt }}</strong>.</p>
        <button @click="$emit('activated')" class="btn-primary">Get Started</button>
      </div>

      <div class="version">v{{ version }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const emit = defineEmits<{
  activated: [];
}>();

const step = ref<'welcome' | 'activate' | 'success'>('welcome');
const businessName = ref('');
const licenseKey = ref('');
const loading = ref(false);
const error = ref('');
const expiresAt = ref('');
const version = ref('1.0.0');

const canActivate = computed(() =>
  businessName.value.trim().length > 0 &&
  licenseKey.value.trim().length >= 20
);

onMounted(async () => {
  try {
    const { getAppVersion } = await import('../services/TauriBridge');
    version.value = await getAppVersion();
  } catch {}
});

async function activate() {
  if (!canActivate.value || loading.value) return;

  loading.value = true;
  error.value = '';

  try {
    const response = await axios.post('/api/v1/desktop/license/activate', {
      key: licenseKey.value.trim(),
      business_name: businessName.value.trim(),
    });

    if (response.data.activated) {
      // Store license locally
      localStorage.setItem('classicpos_license', JSON.stringify(response.data.license));
      expiresAt.value = response.data.license.expires_at
        ? new Date(response.data.license.expires_at).toLocaleDateString()
        : 'Never';
      step.value = 'success';
    }
  } catch (e: any) {
    error.value = e.response?.data?.error || e.message || 'Activation failed';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.activation-screen {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  color: #e2e8f0;
}

.activation-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 16px;
  padding: 2.5rem;
  width: 480px;
  text-align: center;
}

.logo {
  margin-bottom: 2rem;
}

.logo h1 {
  margin: 1rem 0 0.25rem;
  font-size: 1.75rem;
  font-weight: 700;
}

.subtitle {
  color: #94a3b8;
  font-size: 0.95rem;
}

.step h2 {
  margin-bottom: 1rem;
  font-size: 1.25rem;
}

.step p {
  color: #94a3b8;
  margin-bottom: 1.5rem;
  line-height: 1.6;
}

.form-group {
  text-align: left;
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.375rem;
  font-weight: 500;
  font-size: 0.9rem;
  color: #cbd5e1;
}

.form-group input {
  width: 100%;
  padding: 0.625rem 0.75rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 8px;
  color: #e2e8f0;
  font-size: 1rem;
  font-family: monospace;
}

.form-group input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.license-input {
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.error-message {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 8px;
  color: #fca5a5;
  font-size: 0.9rem;
  margin-bottom: 1rem;
}

.loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
  color: #94a3b8;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 2px solid #334155;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.button-row {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
}

.btn-primary {
  padding: 0.75rem 1.5rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-secondary {
  padding: 0.75rem 1.5rem;
  background: transparent;
  color: #94a3b8;
  border: 1px solid #334155;
  border-radius: 8px;
  font-size: 1rem;
  cursor: pointer;
}

.btn-secondary:hover {
  background: #334155;
}

.success-step {
  padding: 1rem 0;
}

.success-icon {
  font-size: 3rem;
  color: #22c55e;
  margin-bottom: 1rem;
}

.success-step p {
  color: #94a3b8;
}

.version {
  margin-top: 2rem;
  color: #475569;
  font-size: 0.8rem;
}
</style>
