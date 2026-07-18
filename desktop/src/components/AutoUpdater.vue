<template>
  <div v-if="updateInfo" class="update-banner">
    <div class="update-content">
      <i class="pi pi-arrow-circle-up"></i>
      <div class="update-text">
        <strong>Update available: v{{ updateInfo.version }}</strong>
        <span v-if="updateInfo.body" class="update-notes">{{ updateInfo.body }}</span>
      </div>
      <div class="update-actions">
        <button v-if="!downloading" @click="installUpdate" class="btn-install">
          Install & Restart
        </button>
        <span v-else class="download-status">
          {{ downloadStatus }}
        </span>
        <button @click="dismiss" class="btn-dismiss">Later</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
  isTauri,
  checkForUpdates,
  installUpdate,
  type UpdateInfo,
  type DownloadProgress,
} from '../services/TauriBridge';

const updateInfo = ref<UpdateInfo | null>(null);
const downloading = ref(false);
const downloadStatus = ref('');

onMounted(async () => {
  if (!isTauri) return;

  try {
    const update = await checkForUpdates();
    if (update) {
      updateInfo.value = update;
    }
  } catch (e) {
    console.warn('[AutoUpdater] Check failed:', e);
  }
});

async function installUpdate() {
  downloading.value = true;
  downloadStatus.value = 'Downloading...';

  try {
    await installUpdate((progress: DownloadProgress) => {
      switch (progress.event) {
        case 'started':
          downloadStatus.value = `Downloading ${progress.data.contentLength ? Math.round(progress.data.contentLength / 1024) + 'KB' : ''}...`;
          break;
        case 'progress':
          downloadStatus.value = 'Installing...';
          break;
        case 'finished':
          downloadStatus.value = 'Restarting...';
          break;
      }
    });
  } catch (e) {
    downloading.value = false;
    downloadStatus.value = '';
    console.error('[AutoUpdater] Install failed:', e);
  }
}

function dismiss() {
  updateInfo.value = null;
}
</script>

<style scoped>
.update-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 9999;
  background: #1e40af;
  color: white;
  padding: 0.75rem 1.5rem;
}

.update-content {
  display: flex;
  align-items: center;
  gap: 1rem;
  max-width: 1200px;
  margin: 0 auto;
}

.update-content i {
  font-size: 1.5rem;
}

.update-text {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.update-notes {
  font-size: 0.85rem;
  opacity: 0.8;
}

.update-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.btn-install {
  background: white;
  color: #1e40af;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-install:hover {
  background: #eff6ff;
}

.btn-dismiss {
  background: transparent;
  color: white;
  border: 1px solid rgba(255,255,255,0.3);
  padding: 0.5rem 1rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-dismiss:hover {
  background: rgba(255,255,255,0.1);
}

.download-status {
  font-size: 0.9rem;
  font-weight: 500;
}
</style>
