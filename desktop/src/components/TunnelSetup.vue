<template>
  <div class="tunnel-setup">
    <div class="tunnel-card">
      <h2>Cloudflare Tunnel Setup</h2>
      <p class="description">
        Expose your POS system to the internet via a secure Cloudflare tunnel.
      </p>

      <!-- Status -->
      <div v-if="status" class="status-section">
        <div class="status-item">
          <span class="label">Status:</span>
          <span :class="['value', status.running ? 'running' : 'stopped']">
            {{ status.running ? 'Running' : 'Stopped' }}
          </span>
        </div>
        <div v-if="status.hostname" class="status-item">
          <span class="label">Hostname:</span>
          <span class="value">{{ status.hostname }}</span>
        </div>
      </div>

      <!-- Setup Form (if not configured) -->
      <div v-if="!status || !status.hostname" class="setup-form">
        <div class="form-group">
          <label>Tunnel ID</label>
          <input v-model="form.tunnelId" type="text" class="form-input" placeholder="your-tunnel-id" />
        </div>
        <div class="form-group">
          <label>Hostname</label>
          <input v-model="form.hostname" type="text" class="form-input" placeholder="pos.yourdomain.com" />
        </div>
        <div class="form-group">
          <label>Credentials Path</label>
          <input v-model="form.credentialsPath" type="text" class="form-input" placeholder="%USERPROFILE%\.cloudflared\..." />
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <button class="btn btn-primary" @click="setup" :disabled="loading">
          {{ loading ? 'Setting up...' : 'Setup & Start' }}
        </button>
      </div>

      <!-- Controls (if configured) -->
      <div v-else class="controls">
        <button class="btn btn-success" @click="startTunnel" :disabled="status.running">
          Start
        </button>
        <button class="btn btn-danger" @click="stopTunnel" :disabled="!status.running">
          Stop
        </button>
        <button class="btn btn-secondary" @click="uninstallService">
          Uninstall Service
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
  isElectron,
  getTunnelStatus,
  tunnelGenerateConfig,
  tunnelInstallService,
  tunnelStart,
  tunnelStop,
  tunnelUninstall,
} from '../services/ElectronBridge';
import type { TunnelStatus } from '../services/ElectronBridge';

const status = ref<TunnelStatus | null>(null);
const loading = ref(false);
const error = ref('');
const form = ref({
  tunnelId: '',
  hostname: '',
  credentialsPath: '',
});

onMounted(async () => {
  if (!isElectron) return;
  await refreshStatus();
});

async function refreshStatus() {
  try {
    status.value = await getTunnelStatus();
  } catch (e) {
    console.warn('Failed to get tunnel status:', e);
  }
}

async function setup() {
  if (!form.value.tunnelId || !form.value.hostname) {
    error.value = 'Tunnel ID and hostname are required';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const port = (window as any).__PHP_PORT__ || 18900;
    const configPath = await tunnelGenerateConfig(
      form.value.tunnelId,
      form.value.credentialsPath,
      form.value.hostname,
      port
    );
    await tunnelInstallService(configPath);
    await tunnelStart();
    await refreshStatus();
  } catch (e: any) {
    error.value = e.message || 'Failed to setup tunnel';
  } finally {
    loading.value = false;
  }
}

async function startTunnel() {
  try {
    await tunnelStart();
    await refreshStatus();
  } catch (e: any) {
    error.value = e.message || 'Failed to start tunnel';
  }
}

async function stopTunnel() {
  try {
    await tunnelStop();
    await refreshStatus();
  } catch (e: any) {
    error.value = e.message || 'Failed to stop tunnel';
  }
}

async function uninstallService() {
  if (!confirm('Uninstall the Cloudflare tunnel service?')) return;
  try {
    await tunnelUninstall();
    await refreshStatus();
  } catch (e: any) {
    error.value = e.message || 'Failed to uninstall service';
  }
}
</script>

<style scoped>
.tunnel-setup {
  padding: 1.5rem;
}

.tunnel-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 12px;
  padding: 2rem;
  max-width: 500px;
}

h2 {
  margin-bottom: 0.5rem;
  font-size: 1.5rem;
  color: #e2e8f0;
}

.description {
  color: #94a3b8;
  margin-bottom: 1.5rem;
  line-height: 1.5;
}

.status-section {
  background: #0f172a;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1.5rem;
}

.status-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
}

.label {
  color: #94a3b8;
}

.value {
  color: #e2e8f0;
  font-weight: 500;
}

.value.running { color: #22c55e; }
.value.stopped { color: #ef4444; }

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #94a3b8;
  font-size: 0.9rem;
}

.form-input {
  width: 100%;
  padding: 0.75rem;
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 6px;
  color: #e2e8f0;
  font-size: 0.95rem;
  outline: none;
}

.form-input:focus {
  border-color: #3b82f6;
}

.error {
  color: #ef4444;
  font-size: 0.9rem;
  margin-bottom: 1rem;
}

.controls {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary { background: #3b82f6; color: white; }
.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-success { background: #22c55e; color: white; }
.btn-success:hover:not(:disabled) { background: #16a34a; }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover:not(:disabled) { background: #dc2626; }
.btn-secondary { background: #475569; color: white; }
.btn-secondary:hover:not(:disabled) { background: #334155; }
</style>
