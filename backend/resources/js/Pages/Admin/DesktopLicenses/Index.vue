<template>
  <AdminLayout>
    <div class="admin-licenses">
      <div class="page-header">
        <h1>Desktop App Licenses</h1>
        <p>Manage ClassicPOS Desktop license sales</p>
      </div>

      <!-- Stats -->
      <div class="stats-grid" v-if="stats">
        <div class="stat-card">
          <div class="stat-value">${{ stats.total_revenue.toLocaleString() }}</div>
          <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.total_licenses }}</div>
          <div class="stat-label">Total Licenses</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.active_licenses }}</div>
          <div class="stat-label">Active</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ stats.pending_licenses }}</div>
          <div class="stat-label">Pending</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters">
        <input v-model="search" placeholder="Search by name, email, or key..." @input="fetchLicenses" />
        <select v-model="statusFilter" @change="fetchLicenses">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="expired">Expired</option>
          <option value="voided">Voided</option>
        </select>
      </div>

      <!-- Licenses Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Business</th>
              <th>Email</th>
              <th>Plan</th>
              <th>License Key</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Activated</th>
              <th>Expires</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="license in licenses" :key="license.id">
              <td>{{ license.business_name }}</td>
              <td>{{ license.email }}</td>
              <td><span class="plan-badge" :class="license.plan">{{ license.plan }}</span></td>
              <td class="key-cell">{{ license.license_key }}</td>
              <td>${{ license.amount }}</td>
              <td>
                <span class="status-badge" :class="license.status">{{ license.status }}</span>
              </td>
              <td>{{ license.activated_at ? new Date(license.activated_at).toLocaleDateString() : '-' }}</td>
              <td>{{ license.expires_at ? new Date(license.expires_at).toLocaleDateString() : 'Lifetime' }}</td>
            </tr>
            <tr v-if="licenses.length === 0">
              <td colspan="8" class="empty-row">No licenses found</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="totalPages > 1">
        <button @click="page--" :disabled="page <= 1">Previous</button>
        <span>Page {{ page }} of {{ totalPages }}</span>
        <button @click="page++" :disabled="page >= totalPages">Next</button>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const licenses = ref<any[]>([]);
const stats = ref<any>(null);
const search = ref('');
const statusFilter = ref('');
const page = ref(1);
const totalPages = ref(1);

onMounted(() => {
  fetchStats();
  fetchLicenses();
});

async function fetchStats() {
  try {
    const response = await axios.get('/api/v1/desktop-licenses/stats');
    stats.value = response.data;
  } catch (e) {
    console.error('Failed to load stats:', e);
  }
}

async function fetchLicenses() {
  try {
    const params: any = { page: page.value };
    if (search.value) params.search = search.value;
    if (statusFilter.value) params.status = statusFilter.value;

    const response = await axios.get('/api/v1/desktop-licenses', { params });
    licenses.value = response.data.data;
    totalPages.value = response.data.last_page;
  } catch (e) {
    console.error('Failed to load licenses:', e);
  }
}

watch(page, () => fetchLicenses());
</script>

<style scoped>
.admin-licenses { max-width: 1200px; }
.page-header { margin-bottom: 1.5rem; }
.page-header h1 { font-size: 1.5rem; color: #1e293b; margin: 0; }
.page-header p { color: #64748b; margin: 0.25rem 0 0; }

.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; text-align: center; }
.stat-value { font-size: 1.75rem; font-weight: 700; color: #1e293b; }
.stat-label { font-size: 0.85rem; color: #64748b; margin-top: 0.25rem; }

.filters { display: flex; gap: 1rem; margin-bottom: 1rem; }
.filters input, .filters select { padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; }
.filters input { flex: 1; }

.table-container { background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
.data-table th { background: #f8fafc; font-weight: 600; color: #475569; }
.key-cell { font-family: monospace; font-size: 0.8rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
.empty-row { text-align: center; color: #94a3b8; padding: 2rem; }

.plan-badge { padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.plan-badge.professional { background: #dbeafe; color: #1d4ed8; }
.plan-badge.enterprise { background: #f3e8ff; color: #7c3aed; }

.status-badge { padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.status-badge.active { background: #dcfce7; color: #166534; }
.status-badge.pending { background: #fef3c7; color: #92400e; }
.status-badge.expired { background: #fee2e2; color: #991b1b; }
.status-badge.voided { background: #f1f5f9; color: #64748b; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1rem; }
.pagination button { padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
