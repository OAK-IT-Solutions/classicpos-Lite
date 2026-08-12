<template>
  <AppLayout>
    <div class="downloads-page">
      <div class="page-header">
        <h1>Desktop App Downloads</h1>
        <p>Download the native ClassicPOS desktop app for offline use.</p>
      </div>

      <!-- Not subscribed -->
      <div v-if="!hasAccess" class="upgrade-banner">
        <div class="upgrade-content">
          <i class="pi pi-arrow-circle-up"></i>
          <div>
            <h2>Upgrade to Professional</h2>
            <p>Desktop app download requires a Professional or Enterprise subscription.</p>
          </div>
          <a href="/settings/subscription" class="btn-upgrade">Upgrade Now</a>
        </div>
      </div>

      <!-- Subscribed — show downloads -->
      <div v-else>
        <div v-if="deviceLimitReached" class="warning-banner">
          <i class="pi pi-exclamation-triangle"></i>
          <span>Device limit reached. Manage devices in Settings > Devices.</span>
        </div>

        <div class="platform-grid">
          <div v-for="(platform, key) in platforms" :key="key" class="platform-card">
            <div class="platform-icon">
              <i :class="getIcon(key)"></i>
            </div>
            <h3>{{ platform.name }}</h3>
            <p class="format">{{ platform.format }}</p>
            <p class="requirements">{{ platform.requirements }}</p>
            <p class="size" v-if="platform.size">{{ formatSize(platform.size) }}</p>
            <a :href="platform.url" @click="trackDownload(key)" class="btn-download">
              Download for {{ platform.name }}
            </a>
            <p v-if="key === 'macos'" class="alt-download">
              <a :href="platform.url_arm">Apple Silicon (M1+) version</a>
            </p>
          </div>
        </div>

        <div class="features-section">
          <h2>What's Included</h2>
          <ul>
            <li><i class="pi pi-check"></i> Full POS with offline mode — sell without internet</li>
            <li><i class="pi pi-check"></i> Native USB thermal receipt printing (ESC/POS)</li>
            <li><i class="pi pi-check"></i> Cash drawer control</li>
            <li><i class="pi pi-check"></i> Automatic sync when reconnected</li>
            <li><i class="pi pi-check"></i> Auto-updates — always the latest version</li>
            <li><i class="pi pi-check"></i> Remote manager access via Cloudflare Tunnel</li>
            <li><i class="pi pi-check"></i> Barcode scanning via USB</li>
          </ul>
        </div>

        <div class="system-requirements">
          <h2>System Requirements</h2>
          <table>
            <tr><td>Windows</td><td>Windows 10+ (x64), 4GB RAM, 200MB disk</td></tr>
            <tr><td>macOS</td><td>macOS 12+ (Intel & Apple Silicon), 4GB RAM</td></tr>
            <tr><td>Linux</td><td>Ubuntu 20.04+ / Fedora 36+ / Any modern distro</td></tr>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const hasAccess = ref(false);
const deviceLimitReached = ref(false);
const platforms = ref<Record<string, any>>({});
const latestVersion = ref('');

onMounted(async () => {
  try {
    const response = await axios.get('/api/v1/desktop/downloads');
    hasAccess.value = true;
    platforms.value = response.data.platforms;
    latestVersion.value = response.data.latest_version;
  } catch (e: any) {
    if (e.response?.status === 403) {
      hasAccess.value = false;
    }
  }
});

function getIcon(platform: string): string {
  const icons: Record<string, string> = {
    windows: 'pi pi-windows',
    macos: 'pi pi-apple',
    macos_arm: 'pi pi-apple',
    linux: 'pi pi-globe',
  };
  return icons[platform] || 'pi pi-download';
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

async function trackDownload(platform: string) {
  try {
    await axios.post(`/api/v1/desktop/downloads/track/${platform}`);
  } catch {}
}
</script>

<style scoped>
.downloads-page {
  max-width: 900px;
}

.page-header {
  margin-bottom: 2rem;
}

.page-header h1 {
  margin: 0;
  font-size: 1.5rem;
  color: #1e293b;
}

.page-header p {
  margin: 0.5rem 0 0;
  color: #64748b;
}

.upgrade-banner {
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: 2rem;
  text-align: center;
}

.upgrade-content {
  display: flex;
  align-items: center;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

.upgrade-content i {
  font-size: 2rem;
  color: #3b82f6;
}

.upgrade-content h2 {
  margin: 0;
  color: #1e40af;
}

.upgrade-content p {
  margin: 0.25rem 0 0;
  color: #3b82f6;
}

.btn-upgrade {
  padding: 0.75rem 1.5rem;
  background: #3b82f6;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 600;
}

.warning-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  color: #92400e;
  margin-bottom: 1.5rem;
}

.platform-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.platform-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  text-align: center;
}

.platform-icon {
  font-size: 2rem;
  color: #3b82f6;
  margin-bottom: 0.75rem;
}

.platform-card h3 {
  margin: 0;
  color: #1e293b;
}

.format {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0.25rem 0;
}

.requirements {
  color: #94a3b8;
  font-size: 0.8rem;
  margin: 0.25rem 0;
}

.size {
  color: #64748b;
  font-size: 0.85rem;
  margin: 0.5rem 0;
}

.btn-download {
  display: inline-block;
  margin-top: 0.75rem;
  padding: 0.625rem 1.5rem;
  background: #3b82f6;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
}

.btn-download:hover {
  background: #2563eb;
}

.alt-download {
  margin-top: 0.5rem;
  font-size: 0.8rem;
}

.alt-download a {
  color: #64748b;
}

.features-section, .system-requirements {
  margin-bottom: 2rem;
}

.features-section h2, .system-requirements h2 {
  font-size: 1.1rem;
  color: #1e293b;
  margin-bottom: 1rem;
}

.features-section ul {
  list-style: none;
  padding: 0;
}

.features-section li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  color: #475569;
}

.features-section li i {
  color: #22c55e;
}

.system-requirements table {
  width: 100%;
  border-collapse: collapse;
}

.system-requirements td {
  padding: 0.5rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.9rem;
}

.system-requirements td:first-child {
  font-weight: 500;
  color: #1e293b;
  width: 120px;
}
</style>
