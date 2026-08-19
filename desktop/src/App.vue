<template>
  <!-- Phase 1: Startup screen while PHP boots -->
  <StartupScreen v-if="phase === 'startup'" @ready="onServerReady" />

  <!-- Phase 2: License activation -->
  <ActivationWizard v-else-if="phase === 'activation'" @activated="onActivated" />

  <!-- Phase 3: Business onboarding (admin user + business details) -->
  <DesktopOnboarding v-else-if="phase === 'onboarding'" @completed="onOnboardingCompleted" @go-to-login="goToLogin" />

  <!-- Phase 4: Main app -->
  <template v-else>
    <router-view />
  </template>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import StartupScreen from './components/StartupScreen.vue';
import ActivationWizard from './components/ActivationWizard.vue';
import DesktopOnboarding from '@/Pages/Onboarding/DesktopOnboarding.vue';
import { isElectron } from './services/ElectronBridge';

const router = useRouter();
type Phase = 'startup' | 'activation' | 'onboarding' | 'ready';
const phase = ref<Phase>('startup');

// ─── Server Ready Handler (called when StartupScreen emits 'ready') ────────

function onServerReady(port: number) {
  console.log('[ClassicPOS] Server ready on port', port);
  (window as any).__PHP_BASE__ = `http://127.0.0.1:${port}`;

  // Check if user already completed onboarding
  const setupComplete = localStorage.getItem('classicpos_setup_complete');
  if (setupComplete === 'true') {
    console.log('[ClassicPOS] Setup already complete, going to POS');
    phase.value = 'ready';
    router.push('/pos');
    return;
  }

  // First time — show activation
  phase.value = 'activation';
}

// ─── Activation Complete → check if onboarding needed ──────────────────────

function onActivated() {
  const setupComplete = localStorage.getItem('classicpos_setup_complete');
  if (setupComplete === 'true') {
    console.log('[ClassicPOS] Setup already complete, skipping onboarding');
    phase.value = 'ready';
    router.push('/pos');
    return;
  }
  console.log('[ClassicPOS] License activated, showing onboarding');
  phase.value = 'onboarding';
}

// ─── Onboarding Complete → ready ───────────────────────────────────────────

function onOnboardingCompleted() {
  console.log('[ClassicPOS] Onboarding completed');
  phase.value = 'ready';
  router.push('/pos');
}

// ─── Go to Login (for returning users who already completed onboarding) ────

function goToLogin() {
  console.log('[ClassicPOS] Going to login');
  phase.value = 'ready';
  router.push('/login');
}

// ─── Lifecycle ─────────────────────────────────────────────────────────────

onMounted(() => {
  if (!isElectron) {
    phase.value = 'ready';
    return;
  }
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
