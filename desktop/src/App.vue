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
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import StartupScreen from './components/StartupScreen.vue';
import ActivationWizard from './components/ActivationWizard.vue';
import DesktopOnboarding from '@/Pages/Onboarding/DesktopOnboarding.vue';
import AutoUpdater from './components/AutoUpdater.vue';
import { isElectron } from './services/ElectronBridge';

const router = useRouter();
type Phase = 'startup' | 'activation' | 'onboarding' | 'ready';
const phase = ref<Phase>('startup');

let wasOffline = false;

function handleOffline() {
  wasOffline = true;
  if (phase.value === 'ready') {
    router.push('/offline');
  }
}

function handleOnline() {
  if (wasOffline && phase.value === 'ready') {
    wasOffline = false;
    router.push('/');
  }
}

onMounted(async () => {
  window.addEventListener('offline', handleOffline);
  window.addEventListener('online', handleOnline);

  if (!isElectron) {
    phase.value = 'ready';
    return;
  }

  // Listen for startup state from main process
  window.electronAPI!.onStartupState((state: any) => {
    if (state.stage === 'Running') {
      const port = state.detail?.port;
      (window as any).__PHP_BASE__ = `http://127.0.0.1:${port}`;
      checkLicense();
    }
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
      try {
        const license = JSON.parse(stored);

        if (license && typeof license === 'object' && license.key) {
          if (license.expires_at) {
            const expiry = new Date(license.expires_at);
            if (expiry < new Date()) {
              console.log('[ClassicPOS] License expired:', license.expires_at);
              localStorage.removeItem('classicpos_license');
              phase.value = 'activation';
              return;
            }
          }

          console.log('[ClassicPOS] License found, proceeding to setup check');
          await checkSetup();
          return;
        }
      } catch (parseErr) {
        console.warn('[ClassicPOS] Failed to parse stored license:', parseErr);
        localStorage.removeItem('classicpos_license');
      }
    }

    phase.value = 'activation';
  } catch (err) {
    console.warn('[ClassicPOS] checkLicense error:', err);
    phase.value = 'activation';
  }
}

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

function onActivated() {
  checkSetup();
}

function onOnboardingCompleted() {
  phase.value = 'ready';
}

onUnmounted(() => {
  window.removeEventListener('offline', handleOffline);
  window.removeEventListener('online', handleOnline);
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
