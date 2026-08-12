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
        <div v-if="state.stage === 'Idle' || state.stage === 'Extracting'" class="status-bar">
          <div class="spinner"></div>
          <span>{{ state.stage === 'Extracting' ? 'Extracting backend files...' : 'Initializing...' }}</span>
        </div>
        <div v-else-if="state.stage === 'StartingPhp'" class="status-bar">
          <div class="spinner"></div>
          <span>Starting server (attempt {{ state.detail?.attempt || 1 }}/3)...</span>
        </div>
        <div v-else-if="state.stage === 'Running'" class="status-bar ready">
          <span class="check">&#10003;</span>
          <span>Ready!</span>
        </div>
        <div v-else-if="state.stage === 'Failed'" class="status-bar error">
          <span class="x-mark">&#10007;</span>
          <span>{{ state.detail?.message || 'Failed to start' }}</span>
        </div>
        <div v-if="state.stage === 'Failed' && logFilePath" class="log-path">
          Log file: {{ logFilePath }}
        </div>
      </div>

      <div class="version">v{{ version }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { isElectron, getStartupState, getAppVersion, getLogFile, onStartupState } from '../services/ElectronBridge';

const state = ref<any>({ stage: 'Idle' });
const version = ref('1.0.0');
const logFilePath = ref('');
let unsubStartup: (() => void) | null = null;

onMounted(async () => {
  if (!isElectron) {
    state.value = { stage: 'Running', detail: { port: 18900 } };
    return;
  }

  try {
    version.value = await getAppVersion();
  } catch {}

  // Get initial state
  try {
    const initial = await getStartupState();
    state.value = initial;
    if (initial.stage === 'Running') {
      const port = initial.detail?.port;
      (window as any).__PHP_BASE__ = `http://127.0.0.1:${port}`;
      window.dispatchEvent(new CustomEvent('classicpos:server-ready'));
      return;
    }
  } catch {}

  // Listen for state changes
  unsubStartup = onStartupState(async (newState: any) => {
    state.value = newState;
    if (newState.stage === 'Running') {
      const port = newState.detail?.port;
      (window as any).__PHP_BASE__ = `http://127.0.0.1:${port}`;
      window.dispatchEvent(new CustomEvent('classicpos:server-ready'));
    } else if (newState.stage === 'Failed') {
      try {
        logFilePath.value = await getLogFile();
      } catch {}
    }
  });
});

onUnmounted(() => {
  unsubStartup?.();
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

.log-path {
  margin-top: 1rem;
  padding: 0.5rem 1rem;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 6px;
  color: #94a3b8;
  font-size: 0.8rem;
  font-family: monospace;
  word-break: break-all;
}

.version {
  color: #475569;
  font-size: 0.85rem;
}
</style>
