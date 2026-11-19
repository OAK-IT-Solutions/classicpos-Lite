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

      <!-- Waiting for server -->
      <div v-if="!serverReady" class="step">
        <div class="loading">
          <div class="spinner"></div>
          <span>Preparing server...</span>
        </div>
        <p class="help-text">The backend server is starting up. Please wait a moment.</p>
      </div>

      <!-- Step 1: Welcome -->
      <div v-else-if="step === 'welcome'" class="step">
        <h2>Welcome to ClassicPOS</h2>
        <p>Activate your license to start using ClassicPOS. If you already have a license key, enter it below.</p>

        <div class="welcome-actions">
          <button @click="step = 'activate'" class="btn-primary btn-block">I Have a License Key</button>
          <button @click="startDemo" :disabled="demoLoading" class="btn-secondary btn-block">
            <span v-if="demoLoading" class="inline-spinner"></span>
            <span v-else>Try Demo (1-Year Trial)</span>
          </button>
          <p v-if="demoError" class="error-text">{{ demoError }}</p>
        </div>

        <p class="help-text">
          No license? Purchase at<br/>
          <a :href="purchaseUrl" target="_blank" class="purchase-link">{{ purchaseUrl }}</a>
          <br/><br/>
          Need help? <a :href="'mailto:' + supportEmail" class="support-link">{{ supportEmail }}</a>
        </p>
      </div>

      <!-- Step 2: Activation -->
      <div v-else-if="step === 'activate'" class="step">
        <h2>Activate Your License</h2>

        <div class="form-group">
          <label>Business Name</label>
          <input v-model="businessName" type="text" placeholder="Enter your business name" />
        </div>

        <div class="form-group">
          <label>License Key</label>
          <input v-model="licenseKey" type="text" placeholder="Paste your full license key here"
                 class="license-input" @keyup.enter="activate" />
        </div>

        <div v-if="error" class="error-message">{{ error }}</div>

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
      <div v-else-if="step === 'success'" class="step success-step">
        <div class="success-icon">&#10003;</div>
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
import { apiRequest, isTauri } from '../services/TauriBridge';

const serverReady = ref(!!(window as any).__PHP_BASE__);

onMounted(async () => {
  // If server isn't ready yet, wait for it
  if (!(window as any).__PHP_BASE__) {
    await new Promise<void>((resolve) => {
      const check = setInterval(() => {
        if ((window as any).__PHP_BASE__) {
          clearInterval(check);
          serverReady.value = true;
          resolve();
        }
      }, 500);
    });
  }
  // Get version info
  try {
    const { getAppVersion } = await import('../services/TauriBridge');
    version.value = await getAppVersion();
  } catch {}
});

const emit = defineEmits<{
  activated: [];
}>();

const PURCHASE_URL = 'https://posapp.oakitsolutionsandsupplies.com/classicpos-Lite/license';
const SUPPORT_EMAIL = 'support@posapp.oakitsolutionsandsupplies.com';

const step = ref<'welcome' | 'activate' | 'success'>('welcome');
const businessName = ref('');
const licenseKey = ref('');
const loading = ref(false);
const error = ref('');
const expiresAt = ref('');
const version = ref('1.0.0');
const demoLoading = ref(false);
const demoError = ref('');

const purchaseUrl = PURCHASE_URL;
const supportEmail = SUPPORT_EMAIL;

const canActivate = computed(() => {
  const name = (businessName.value || '').trim();
  const key = (licenseKey.value || '').trim();
  return name.length > 0 && key.length >= 20;
});

async function proxyRequest(method: 'GET' | 'POST', path: string, body?: Record<string, unknown>): Promise<any> {
  if (isTauri) {
    const resp = await apiRequest(method, path, body ? JSON.stringify(body) : undefined);
    if (resp.status >= 400) {
      let errMsg = `Request failed (HTTP ${resp.status})`;
      try {
        const data = JSON.parse(resp.body);
        errMsg = data.error || data.message || errMsg;
      } catch {
        console.error('[proxyRequest] Non-JSON error body:', resp.body.substring(0, 500));
      }
      throw new Error(errMsg);
    }
    try {
      return JSON.parse(resp.body);
    } catch (e: any) {
      const preview = resp.body.substring(0, 200);
      console.error('[proxyRequest] JSON parse error:', e.message, 'body preview:', preview);
      throw new Error(`Invalid response from server: ${preview}`);
    }
  } else {
    // Web fallback: use fetch directly
    const phpBase = (window as any).__PHP_BASE__ || 'http://127.0.0.1:18900';
    const url = `${phpBase}${path}`;
    const options: RequestInit = { method };
    if (body) {
      (options as any).headers = { 'Content-Type': 'application/json' };
      (options as any).body = JSON.stringify(body);
    }
    const resp = await fetch(url, options);
    if (!resp.ok) {
      const data = await resp.json().catch(() => ({}));
      throw new Error(data.error || data.message || `Request failed (HTTP ${resp.status})`);
    }
    return resp.json();
  }
}

async function startDemo() {
  demoLoading.value = true;
  demoError.value = '';

  try {
    const data = await proxyRequest('GET', '/api/v1/desktop/license/generate-demo');

    if (!data?.key) {
      throw new Error('Demo server returned an invalid response. Check connectivity to the backend.');
    }

    licenseKey.value = data.key;
    businessName.value = 'Demo Business';
    step.value = 'activate';

    await activate();
  } catch (e: any) {
    demoError.value = e.message || 'Failed to generate demo license';
  } finally {
    demoLoading.value = false;
  }
}

async function activate() {
  if (!canActivate.value || loading.value) return;

  loading.value = true;
  error.value = '';

  try {
    const data = await proxyRequest('POST', '/api/v1/desktop/license/activate', {
      key: (licenseKey.value || '').trim(),
      business_name: (businessName.value || '').trim(),
    });

    if (data.activated) {
      localStorage.setItem('classicpos_license', JSON.stringify(data.license));
      expiresAt.value = data.license.expires_at
        ? new Date(data.license.expires_at).toLocaleDateString()
        : 'Never';
      step.value = 'success';
    } else {
      throw new Error(data.error || 'Activation failed');
    }
  } catch (e: any) {
    error.value = e.message || 'Activation failed. Please try again.';
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

.welcome-actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.btn-block {
  width: 100%;
  padding: 0.85rem 1.5rem;
}

.btn-primary {
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
  background: transparent;
  color: #94a3b8;
  border: 1px solid #334155;
  border-radius: 8px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.btn-secondary:hover:not(:disabled) {
  background: #334155;
}

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.inline-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid #475569;
  border-top-color: #94a3b8;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
}

.error-text {
  color: #fca5a5;
  font-size: 0.85rem;
  margin: 0;
  padding: 0.5rem;
  background: rgba(239, 68, 68, 0.1);
  border-radius: 6px;
}

.help-text {
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.6;
  margin-top: 1rem;
}

.purchase-link {
  color: #60a5fa;
  text-decoration: none;
  word-break: break-all;
}

.purchase-link:hover {
  text-decoration: underline;
}

.support-link {
  color: #60a5fa;
  text-decoration: none;
}

.support-link:hover {
  text-decoration: underline;
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
}

.error-message {
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
