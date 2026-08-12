<template>
  <!-- Auto-updater: checks on mount, shows toast if update available -->
  <div v-if="updateAvailable" class="update-toast">
    <div class="update-content">
      <p>Update available: v{{ updateInfo.version }}</p>
      <p v-if="updateInfo.body" class="update-notes">{{ updateInfo.body }}</p>
      <div class="update-actions">
        <button class="btn btn-sm" @click="downloadUpdate" :disabled="downloading">
          {{ downloading ? `Downloading ${progress}%...` : 'Update Now' }}
        </button>
        <button class="btn btn-sm btn-ghost" @click="dismiss">Later</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { isElectron } from '../services/ElectronBridge';

const updateAvailable = ref(false);
const updateInfo = ref({ version: '', date: '', body: '' });
const downloading = ref(false);
const progress = ref(0);

onMounted(async () => {
  if (!isElectron) return;

  try {
    const { autoUpdater } = await import('electron-updater');
    
    // Check for updates silently
    autoUpdater.checkForUpdates().catch(() => {
      // Silent fail — updates are optional
    });

    // When update is available
    autoUpdater.on('update-available', (info) => {
      updateInfo.value = {
        version: info.version,
        date: info.releaseDate || '',
        body: info.releaseNotes || '',
      };
      updateAvailable.value = true;
    });

    // Download progress
    autoUpdater.on('download-progress', (progressInfo) => {
      progress.value = Math.round(progressInfo.percent);
    });

    // When download is complete
    autoUpdater.on('update-downloaded', () => {
      downloading.value = false;
      // Auto-install after short delay
      setTimeout(() => {
        autoUpdater.quitAndInstall();
      }, 3000);
    });

    // Errors
    autoUpdater.on('error', () => {
      downloading.value = false;
    });
  } catch (e) {
    // Silent fail — updates are optional
  }
});

function dismiss() {
  updateAvailable.value = false;
}

async function downloadUpdate() {
  downloading.value = true;
  try {
    const { autoUpdater } = await import('electron-updater');
    await autoUpdater.downloadUpdate();
  } catch (e) {
    downloading.value = false;
    dismiss();
  }
}
</script>

<style scoped>
.update-toast {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 9999;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 8px;
  padding: 1rem;
  min-width: 300px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.update-content p {
  margin: 0 0 0.5rem;
  color: #e2e8f0;
  font-size: 0.9rem;
}

.update-notes {
  font-size: 0.8rem;
  color: #94a3b8;
  max-height: 60px;
  overflow-y: auto;
}

.update-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  background: #3b82f6;
  color: white;
  transition: background 0.2s;
}

.btn:hover:not(:disabled) {
  background: #2563eb;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-ghost {
  background: transparent;
  color: #94a3b8;
}

.btn-ghost:hover {
  background: #334155;
}
</style>
