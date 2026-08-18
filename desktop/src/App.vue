<template>
  <!-- Phase 1: Startup screen while PHP boots -->
  <StartupScreen v-if="phase === 'startup'" @ready="onServerReady" />

  <!-- Phase 2: License activation -->
  <ActivationWizard v-else-if="phase === 'activation'" @activated="onActivated" />

  <!-- Phase 3: Business onboarding (admin user + business details) -->
  <DesktopOnboarding v-else-if="phase === 'onboarding'" @completed="onOnboardingCompleted" @go-to-login="goToLogin" />

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
  // Always go to activation — user clicks through the full flow
  phase.value = 'activation';
}

// ─── Activation Complete → always go to onboarding ─────────────────────────

function onActivated() {
  console.log('[ClassicPOS] License activated, showing onboarding');
  phase.value = 'onboarding';
}

// ─── Onboarding Complete → ready ───────────────────────────────────────────

function onOnboardingCompleted() {
  console.log('[ClassicPOS] Onboarding completed');
  phase.value = 'ready';
}

// ─── Go to Login (for returning users who already completed onboarding) ────

function goToLogin() {
  console.log('[ClassicPOS] Going to login');
  phase.value = 'ready';
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
    phase.value = 'ready';
    return;
  }
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
