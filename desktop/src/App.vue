<template>
  <!-- Phase 1: Startup screen while PHP boots -->
  <StartupScreen v-if="phase === 'startup'" @ready="onServerReady" />

  <!-- Phase 2: License check -->
  <ActivationWizard v-else-if="phase === 'activation'" @activated="onActivated" />

  <!-- Phase 3: First-run onboarding (create admin user + business) -->
  <DesktopOnboarding v-else-if="phase === 'onboarding'" @completed="onOnboardingCompleted" />

  <!-- Phase 4: Main app -->
  <template v-else>
    <AutoUpdater />
    <router-view />
  </template>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import StartupScreen from './components/StartupScreen.vue';
import ActivationWizard from './components/ActivationWizard.vue';
import DesktopOnboarding from '@/Pages/Onboarding/DesktopOnboarding.vue';
import AutoUpdater from './components/AutoUpdater.vue';
import { isElectron, getStartupState } from './services/ElectronBridge';

type Phase = 'startup' | 'activation' | 'onboarding' | 'ready';
const phase = ref<Phase>('startup');

let fallbackTimer: ReturnType<typeof setTimeout> | null = null;

// ─── Server Ready Handler (called when StartupScreen emits 'ready') ────────

function onServerReady(port: number) {
  console.log('[ClassicPOS] Server ready on port', port);
  (window as any).__PHP_BASE__ = `http://127.0.0.1:${port}`;

  // Check if user already completed the full setup flow
  const setupComplete = localStorage.getItem('classicpos_setup_complete');
  if (setupComplete === 'true') {
    console.log('[ClassicPOS] Setup already complete, going to login');
    phase.value = 'ready';
    return;
  }

  // Fresh install or incomplete setup — go through activation
  console.log('[ClassicPOS] Setup not complete, showing activation');
  phase.value = 'activation';
}

// ─── Activation Complete Handler ───────────────────────────────────────────

function onActivated() {
  console.log('[ClassicPOS] License activated, checking setup status');
  checkSetup();
}

// ─── Onboarding Complete Handler ───────────────────────────────────────────

function onOnboardingCompleted() {
  console.log('[ClassicPOS] Onboarding completed');
  localStorage.setItem('classicpos_setup_complete', 'true');
  phase.value = 'ready';
}

// ─── Setup Check (calls backend to see if business setup is needed) ────────

async function checkSetup(retries = 3, delayMs = 1000) {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      const { apiRequest } = await import('./services/ElectronBridge');
      const resp = await apiRequest('GET', '/api/v1/desktop/setup/check');
      const data = JSON.parse(resp.body);
      console.log('[ClassicPOS] checkSetup response:', data);
      phase.value = data.setup_required ? 'onboarding' : 'ready';
      return;
    } catch (e) {
      console.warn(`[ClassicPOS] checkSetup attempt ${attempt}/${retries} failed:`, e);
      if (attempt < retries) {
        await new Promise(r => setTimeout(r, delayMs * attempt));
      }
    }
  }
  console.warn('[ClassicPOS] checkSetup all retries failed, defaulting to onboarding');
  phase.value = 'onboarding';
}

// ─── Fallback: Poll startup state via IPC if events were missed ────────────

function startFallbackCheck() {
  if (!isElectron) return;

  fallbackTimer = setTimeout(async () => {
    if (phase.value !== 'startup') return;
    try {
      const state = await getStartupState();
      if (state.stage === 'Running' && state.detail?.port) {
        console.log('[ClassicPOS] Fallback: server is running on port', state.detail.port);
        onServerReady(state.detail.port);
        return;
      }
    } catch {}
    startFallbackCheck();
  }, 3000);
}

// ─── Lifecycle ─────────────────────────────────────────────────────────────

onMounted(() => {
  if (!isElectron) {
    // Web dev mode — skip startup, go straight to ready
    phase.value = 'ready';
    return;
  }
  // Start fallback polling in case StartupScreen events are missed
  startFallbackCheck();
});

onUnmounted(() => {
  if (fallbackTimer) clearTimeout(fallbackTimer);
});
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
</style>
