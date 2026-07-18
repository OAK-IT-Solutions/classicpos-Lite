<template>
  <!-- Phase 1: Startup screen while PHP boots -->
  <StartupScreen v-if="phase === 'startup'" />

  <!-- Phase 2: License check -->
  <ActivationWizard v-else-if="phase === 'activation'" @activated="onActivated" />

  <!-- Phase 3: Main app -->
  <template v-else>
    <AutoUpdater />
    <router-view />
  </template>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import StartupScreen from './components/StartupScreen.vue';
import ActivationWizard from './components/ActivationWizard.vue';
import AutoUpdater from './components/AutoUpdater.vue';
import { isTauri } from './services/TauriBridge';

type Phase = 'startup' | 'activation' | 'ready';
const phase = ref<Phase>('startup');

onMounted(async () => {
  if (!isTauri) {
    // Web mode — skip startup and activation
    phase.value = 'ready';
    return;
  }

  // Wait for PHP server to be ready
  window.addEventListener('classicpos:server-ready', async () => {
    await checkLicense();
  });

  // Fallback: proceed after 10s
  setTimeout(async () => {
    if (phase.value === 'startup') {
      await checkLicense();
    }
  }, 10000);
});

async function checkLicense() {
  try {
    // Check if license is stored locally
    const stored = localStorage.getItem('classicpos_license');
    if (stored) {
      const license = JSON.parse(stored);

      // Verify license is still valid (check expiry)
      if (license.expires_at) {
        const expiry = new Date(license.expires_at);
        if (expiry < new Date()) {
          // License expired — remove and show activation
          localStorage.removeItem('classicpos_license');
          phase.value = 'activation';
          return;
        }
      }

      // License valid — proceed to app
      phase.value = 'ready';
      return;
    }

    // No license found — show activation wizard
    phase.value = 'activation';
  } catch {
    // Error checking license — show activation
    phase.value = 'activation';
  }
}

function onActivated() {
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
