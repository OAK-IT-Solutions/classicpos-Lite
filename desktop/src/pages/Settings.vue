<template>
  <div class="settings-page">
    <h1>Settings</h1>

    <!-- Printer Settings -->
    <section class="settings-section">
      <h2>Printer Configuration</h2>
      <div class="printer-status">
        <div class="status-dot" :class="{ on: printers.length > 0 }"></div>
        <span>{{ printers.length }} printer(s) detected</span>
        <button @click="refreshPrinters" class="btn-link">Refresh</button>
      </div>

      <div v-if="printers.length > 0" class="printer-list">
        <div v-for="printer in printers" :key="printer.name" class="printer-item">
          <div class="printer-info">
            <span class="printer-type">{{ printer.port_type.toUpperCase() }}</span>
            <span class="printer-name">{{ printer.name }}</span>
          </div>
          <button @click="testPrint(printer)" class="btn-sm">Test Print</button>
        </div>
      </div>

      <div class="form-group">
        <label>Default Printer</label>
        <select v-model="selectedPrinter">
          <option value="">Select printer...</option>
          <option v-for="p in printers" :key="p.name" :value="p.name">{{ p.name }} ({{ p.port_type }})</option>
        </select>
      </div>

      <div class="form-group">
        <label>Network Printer IP (if using network)</label>
        <input v-model="networkIp" placeholder="192.168.1.100" />
      </div>

      <div class="form-group">
        <label>Network Printer Port</label>
        <input v-model.number="networkPort" type="number" placeholder="9100" />
      </div>
    </section>

    <!-- Remote Access -->
    <section class="settings-section">
      <TunnelSetup />
    </section>

    <!-- App Info -->
    <section class="settings-section">
      <h2>Application</h2>
      <div class="info-row">
        <span>Version</span>
        <span>{{ version }}</span>
      </div>
      <div class="info-row">
        <span>License Status</span>
        <span :class="licenseStatus ? 'text-green' : 'text-red'">
          {{ licenseStatus ? 'Active' : 'Inactive' }}
        </span>
      </div>
      <template v-if="licenseData">
        <div class="info-row">
          <span>Business</span>
          <span>{{ licenseData.business_name || '—' }}</span>
        </div>
        <div class="info-row">
          <span>License Key</span>
          <span class="font-mono text-xs">{{ truncateKey(licenseData.key) }}</span>
        </div>
        <div v-if="licenseData.features" class="info-row">
          <span>Features</span>
          <span>{{ formatFeatures(licenseData.features) }}</span>
        </div>
        <div class="info-row">
          <span>Expires</span>
          <span>{{ licenseData.expires_at ? formatDate(licenseData.expires_at) : 'Never (Lifetime)' }}</span>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import TunnelSetup from '../components/TunnelSetup.vue';
import {
  isElectron,
  getAppVersion,
  listPrinters,
  printReceipt,
  type PrinterInfo,
} from '../services/ElectronBridge';

const version = ref('1.0.0');
const printers = ref<PrinterInfo[]>([]);
const selectedPrinter = ref('');
const networkIp = ref('');
const networkPort = ref(9100);
const licenseStatus = ref(false);
const licenseData = ref<any>(null);

onMounted(async () => {
  version.value = await getAppVersion();
  licenseStatus.value = !!localStorage.getItem('classicpos_license');

  try {
    const stored = localStorage.getItem('classicpos_license');
    if (stored) {
      licenseData.value = JSON.parse(stored);
    }
  } catch {}

  if (isElectron) {
    printers.value = await listPrinters();
  }
});

function truncateKey(key: string) {
  if (!key) return '—';
  if (key.length <= 25) return key;
  return key.substring(0, 25) + '...';
}

function formatFeatures(features: string[]) {
  if (!features || !features.length) return '—';
  return features.map(f => f.replace(/_/g, ' ')).join(', ');
}

function formatDate(date: string) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

async function refreshPrinters() {
  if (!isElectron) return;
  printers.value = await listPrinters();
}

async function testPrint(printer: PrinterInfo) {
  // Send a test ESC/POS receipt
  const ESC = 0x1B;
  const LF = 0x0A;
  const testReceipt = [
    ESC, 0x40, // Initialize
    ESC, 0x61, 0x01, // Center align
    0x12, 0x54, // Double height
    ...TextEncoder.encode('ClassicPOS'),
    0x12, 0x14, // Normal
    LF,
    ...TextEncoder.encode('Test Print Successful'),
    LF, LF,
    ESC, 0x61, 0x00, // Left align
    ...TextEncoder.encode(`Date: ${new Date().toLocaleString()}`),
    LF,
    ...TextEncoder.encode(`Printer: ${printer.name}`),
    LF, LF, LF,
    0x1D, 0x56, 0x00, // Cut paper
  ];

  try {
    if (printer.port_type === 'network') {
      const { printToPort } = await import('../services/ElectronBridge');
      await printToPort('network', '', networkIp.value, networkPort.value, testReceipt);
    } else {
      await printReceipt(testReceipt);
    }
    alert('Test print sent!');
  } catch (e: any) {
    alert('Print failed: ' + e);
  }
}
</script>

<style scoped>
.settings-page {
  max-width: 800px;
}

.settings-page h1 {
  margin: 0 0 1.5rem;
  font-size: 1.5rem;
  color: #1e293b;
}

.settings-section {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.settings-section h2 {
  margin: 0 0 1rem;
  font-size: 1.1rem;
  color: #1e293b;
}

.printer-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
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

.printer-list {
  margin-bottom: 1rem;
}

.printer-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.625rem 0.75rem;
  background: #f8fafc;
  border-radius: 6px;
  margin-bottom: 0.5rem;
}

.printer-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.printer-type {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  background: #e0e7ff;
  color: #3730a3;
  border-radius: 4px;
}

.printer-name {
  font-size: 0.9rem;
  color: #1e293b;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 500;
  color: #475569;
  margin-bottom: 0.25rem;
}

.form-group input, .form-group select {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.9rem;
}

.text-green { color: #16a34a; font-weight: 500; }
.text-red { color: #dc2626; font-weight: 500; }

.btn-link {
  background: none;
  border: none;
  color: #3b82f6;
  cursor: pointer;
  font-size: 0.85rem;
  padding: 0;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.8rem;
}

.font-mono {
  font-family: monospace;
}
</style>
