<template>
  <div class="tunnel-card">
    <div class="tunnel-header">
      <div class="tunnel-icon">
        <i class="pi pi-globe"></i>
      </div>
      <div>
        <h3>Remote Manager Access</h3>
        <p class="tunnel-desc">Allow managers to view reports remotely via Cloudflare Tunnel</p>
      </div>
    </div>

    <!-- Status -->
    <div v-if="status" class="tunnel-status" :class="{ active: status.running }">
      <div class="status-dot" :class="{ on: status.running }"></div>
      <span>{{ status.running ? 'Tunnel Active' : 'Tunnel Inactive' }}</span>
      <span v-if="status.hostname" class="hostname">{{ status.hostname }}</span>
    </div>

    <!-- Setup Steps -->
    <div v-if="!status?.running && step === 'setup'" class="tunnel-steps">
      <div class="step-item" :class="{ done: setupStep > 1 }">
        <div class="step-num">1</div>
        <div class="step-content">
          <h4>Enter Tunnel Details</h4>
          <p>Get these from your Cloudflare Zero Trust dashboard</p>
        </div>
      </div>

      <div v-if="setupStep >= 1" class="step-form">
        <div class="form-group">
          <label>Tunnel ID</label>
          <input v-model="tunnelId" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
        </div>
        <div class="form-group">
          <label>Hostname</label>
          <input v-model="hostname" placeholder="pos.yourdomain.com" />
        </div>
        <div class="form-group">
          <label>Credentials File Path</label>
          <input v-model="credentialsPath" placeholder="C:\Users\you\.cloudflared\credentials.json" />
        </div>
        <button @click="generateConfig" :disabled="!canGenerate || generating" class="btn-primary">
          {{ generating ? 'Generating...' : 'Generate Config' }}
        </button>
      </div>

      <div class="step-item" :class="{ done: setupStep > 2 }">
        <div class="step-num">2</div>
        <div class="step-content">
          <h4>Install & Start Service</h4>
          <p>Requires administrator privileges</p>
        </div>
      </div>

      <div v-if="setupStep >= 2" class="step-form">
        <button @click="installAndStart" :disabled="installing" class="btn-primary">
          {{ installing ? 'Installing...' : 'Install & Start Tunnel' }}
        </button>
        <p v-if="installError" class="error">{{ installError }}</p>
      </div>
    </div>

    <!-- Controls when running -->
    <div v-if="status?.running" class="tunnel-controls">
      <div class="access-info">
        <p>Managers can access reports at:</p>
        <code class="access-url">https://{{ status.hostname }}</code>
        <p class="access-note">Set up <strong>Cloudflare Access</strong> policies in your Zero Trust dashboard to control who can log in.</p>
      </div>
      <button @click="stopTunnel" class="btn-danger">Stop Tunnel</button>
    </div>

    <!-- Start button when stopped -->
    <div v-if="status && !status.running && !status.installed" class="tunnel-controls">
      <button @click="step = 'setup'" class="btn-primary">Set Up Remote Access</button>
    </div>

    <div v-if="status && !status.running && status.installed" class="tunnel-controls">
      <button @click="startTunnel" class="btn-primary">Start Tunnel</button>
      <button @click="uninstallTunnel" class="btn-secondary">Uninstall</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import {
  isTauri,
  getTunnelStatus,
  tunnelGenerateConfig,
  tunnelInstallService,
  tunnelStart,
  tunnelStop,
  tunnelUninstall,
  getPhpPort,
  type TunnelStatus,
} from '../services/TauriBridge';

const status = ref<TunnelStatus | null>(null);
const step = ref<'idle' | 'setup'>('idle');
const setupStep = ref(1);
const tunnelId = ref('');
const hostname = ref('');
const credentialsPath = ref('');
const generating = ref(false);
const installing = ref(false);
const installError = ref('');
const phpPort = ref(18900);

const canGenerate = computed(() =>
  tunnelId.value.trim().length > 0 &&
  hostname.value.trim().length > 0 &&
  credentialsPath.value.trim().length > 0
);

onMounted(async () => {
  if (!isTauri) return;
  status.value = await getTunnelStatus();
  phpPort.value = await getPhpPort();
});

async function generateConfig() {
  if (!canGenerate.value) return;
  generating.value = true;
  try {
    await tunnelGenerateConfig(
      tunnelId.value.trim(),
      credentialsPath.value.trim(),
      hostname.value.trim(),
      phpPort.value,
    );
    setupStep.value = 2;
  } catch (e: any) {
    alert('Failed to generate config: ' + e);
  } finally {
    generating.value = false;
  }
}

async function installAndStart() {
  installing.value = true;
  installError.value = '';
  try {
    // Read the config path that was just generated
    const configPath = getConfigPath();
    await tunnelInstallService(configPath);
    await tunnelStart();
    status.value = await getTunnelStatus();
  } catch (e: any) {
    installError.value = e;
  } finally {
    installing.value = false;
  }
}

async function startTunnel() {
  try {
    await tunnelStart();
    status.value = await getTunnelStatus();
  } catch (e: any) {
    alert('Failed to start: ' + e);
  }
}

async function stopTunnel() {
  try {
    await tunnelStop();
    status.value = await getTunnelStatus();
  } catch (e: any) {
    alert('Failed to stop: ' + e);
  }
}

async function uninstallTunnel() {
  if (!confirm('Remove the Cloudflare Tunnel service?')) return;
  try {
    await tunnelUninstall();
    status.value = await getTunnelStatus();
  } catch (e: any) {
    alert('Failed to uninstall: ' + e);
  }
}

function getConfigPath(): string {
  if (navigator.platform.includes('Win')) {
    return 'C:\\ProgramData\\classicpos\\cloudflared\\config.yml';
  }
  return '/etc/classicpos/cloudflared/config.yml';
}
</script>

<style scoped>
.tunnel-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
}

.tunnel-header {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.tunnel-icon {
  width: 48px;
  height: 48px;
  background: #eff6ff;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: #3b82f6;
  flex-shrink: 0;
}

.tunnel-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: #1e293b;
}

.tunnel-desc {
  margin: 0.25rem 0 0;
  color: #64748b;
  font-size: 0.9rem;
}

.tunnel-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: #f8fafc;
  border-radius: 8px;
  margin-bottom: 1.25rem;
  font-size: 0.9rem;
}

.tunnel-status.active {
  background: #f0fdf4;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #94a3b8;
}

.status-dot.on {
  background: #22c55e;
}

.hostname {
  margin-left: auto;
  font-family: monospace;
  color: #3b82f6;
  font-size: 0.85rem;
}

.tunnel-steps {
  margin-bottom: 1.25rem;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.step-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  font-weight: 600;
  flex-shrink: 0;
}

.step-item.done .step-num {
  background: #22c55e;
  color: white;
}

.step-content h4 {
  margin: 0;
  font-size: 0.95rem;
  color: #1e293b;
}

.step-content p {
  margin: 0.125rem 0 0;
  font-size: 0.8rem;
  color: #94a3b8;
}

.step-form {
  margin: 0.5rem 0 1.25rem 2.75rem;
}

.form-group {
  margin-bottom: 0.75rem;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 500;
  color: #475569;
  margin-bottom: 0.25rem;
}

.form-group input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
}

.form-group input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}

.tunnel-controls {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.access-info {
  flex: 1;
  min-width: 300px;
}

.access-info p {
  margin: 0;
  font-size: 0.9rem;
  color: #475569;
}

.access-url {
  display: inline-block;
  margin: 0.5rem 0;
  padding: 0.5rem 1rem;
  background: #f1f5f9;
  border-radius: 6px;
  font-family: monospace;
  font-size: 0.95rem;
  color: #1e293b;
}

.access-note {
  font-size: 0.8rem !important;
  color: #94a3b8 !important;
}

.error {
  color: #dc2626;
  font-size: 0.85rem;
  margin-top: 0.5rem;
}

.btn-primary {
  padding: 0.625rem 1.25rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-secondary {
  padding: 0.625rem 1.25rem;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-danger {
  padding: 0.625rem 1.25rem;
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.9rem;
}
</style>
