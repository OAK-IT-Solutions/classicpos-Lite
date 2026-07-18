<template>
  <div class="startup-screen">
    <div class="startup-content">
      <div class="logo">
        <div class="logo-icon">
          <svg viewBox="0 0 100 100" width="80" height="80">
            <rect x="10" y="10" width="80" height="80" rx="16" fill="#3b82f6"/>
            <text x="50" y="62" text-anchor="middle" fill="white" font-size="28" font-weight="bold" font-family="system-ui">POS</text>
          </svg>
        </div>
        <h1>ClassicPOS</h1>
        <p>Offline Desktop POS System</p>
      </div>

      <div class="status">
        <div v-if="status === 'starting'" class="status-bar">
          <div class="spinner"></div>
          <span>Starting server...</span>
        </div>
        <div v-else-if="status === 'migrating'" class="status-bar">
          <div class="spinner"></div>
          <span>Setting up database...</span>
        </div>
        <div v-else-if="status === 'ready'" class="status-bar ready">
          <span class="check">&#10003;</span>
          <span>Ready!</span>
        </div>
        <div v-else-if="status === 'error'" class="status-bar error">
          <span class="x-mark">&#10007;</span>
          <span>{{ errorMessage }}</span>
        </div>
      </div>

      <div class="version">v{{ version }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { isTauri, startPhpServer, getAppVersion, getPhpStatus } from '../services/TauriBridge';

const status = ref<'starting' | 'migrating' | 'ready' | 'error'>('starting');
const errorMessage = ref('');
const version = ref('1.0.0');

onMounted(async () => {
  if (!isTauri) {
    // Web mode — skip startup screen
    status.value = 'ready';
    return;
  }

  try {
    // Get app version
    version.value = await getAppVersion();

    // Check if PHP is already running
    const phpStatus = await getPhpStatus();
    if (phpStatus.running) {
      status.value = 'ready';
      return;
    }

    // Start PHP server
    status.value = 'starting';
    await startPhpServer();

    status.value = 'ready';

    // Emit event to notify the app
    window.dispatchEvent(new CustomEvent('classicpos:server-ready'));
  } catch (e: any) {
    status.value = 'error';
    errorMessage.value = e.message || 'Failed to start server';
  }
});
</script>

<style scoped>
.startup-screen {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  color: #e2e8f0;
}

.startup-content {
  text-align: center;
}

.logo {
  margin-bottom: 3rem;
}

.logo-icon {
  margin-bottom: 1rem;
}

.logo h1 {
  margin: 0;
  font-size: 2.5rem;
  font-weight: 700;
  letter-spacing: -0.025em;
}

.logo p {
  margin: 0.5rem 0 0;
  color: #94a3b8;
  font-size: 1.1rem;
}

.status {
  margin-bottom: 2rem;
}

.status-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 8px;
  font-size: 0.95rem;
}

.status-bar.ready {
  border-color: #22c55e;
  color: #22c55e;
}

.status-bar.error {
  border-color: #ef4444;
  color: #ef4444;
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

.check {
  font-size: 1.2rem;
  font-weight: bold;
}

.x-mark {
  font-size: 1.2rem;
  font-weight: bold;
}

.version {
  color: #475569;
  font-size: 0.85rem;
}
</style>
