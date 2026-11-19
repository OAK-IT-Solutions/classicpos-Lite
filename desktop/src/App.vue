<template>
  <!-- Phase 1: Startup screen while PHP boots -->
  <StartupScreen v-if="phase === 'startup'" />

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
import { ref, onMounted } from 'vue';
import StartupScreen from './components/StartupScreen.vue';
import ActivationWizard from './components/ActivationWizard.vue';
import DesktopOnboarding from '@/Pages/Onboarding/DesktopOnboarding.vue';
import AutoUpdater from './components/AutoUpdater.vue';
import { isTauri } from './services/TauriBridge';

type Phase = 'startup' | 'activation' | 'onboarding' | 'ready';
const phase = ref<Phase>('startup');

onMounted(async () => {
  if (!isTauri) {
    phase.value = 'ready';
    return;
  }

  // Single event: when StartupScreen detects the server is running
  window.addEventListener('classicpos:server-ready', async () => {
    await checkLicense();
  });

  // Fallback: poll __PHP_BASE__ in case the event was missed
  scheduleFallbackCheck();
});

function scheduleFallbackCheck() {
  setTimeout(async () => {
    if (phase.value !== 'startup') return;
    if ((window as any).__PHP_BASE__) {
      await checkLicense();
    } else {
      scheduleFallbackCheck();
    }
  }, 3000);
}

async function checkLicense() {
  try {
    const stored = localStorage.getItem('classicpos_license');
    if (stored) {
      const license = JSON.parse(stored);

      if (license.expires_at) {
        const expiry = new Date(license.expires_at);
        if (expiry < new Date()) {
          localStorage.removeItem('classicpos_license');
          phase.value = 'activation';
          return;
        }
      }

      await checkSetup();
      return;
    }

    phase.value = 'activation';
  } catch {
    phase.value = 'activation';
  }
}

async function checkSetup(retries = 3, delayMs = 1000) {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      if (isTauri) {
        const { apiRequest } = await import('./services/TauriBridge');
        const resp = await apiRequest('GET', '/api/v1/desktop/setup/check');
        const data = JSON.parse(resp.body);
        console.log('[ClassicPOS] checkSetup response:', data);
        phase.value = data.setup_required ? 'onboarding' : 'ready';
        return;
      } else {
        const phpBase = (window as any).__PHP_BASE__;
        const response = await fetch(`${phpBase}/api/v1/desktop/setup/check`);
        const data = await response.json();
        phase.value = data.setup_required ? 'onboarding' : 'ready';
        return;
      }
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

function onActivated() {
  checkSetup();
}

function onOnboardingCompleted() {
  phase.value = 'ready';
}
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
