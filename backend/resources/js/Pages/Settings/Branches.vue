<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';

defineProps<{ embedded?: boolean }>();

const branches = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const showForm = ref(false);
const editingBranch = ref<any>(null);

const form = ref({
    name: '',
    location: '',
    timezone: 'Africa/Nairobi',
    edge_device_id: '',
});

async function fetchBranches() {
    try {
        const res = await api.get('/branches');
        branches.value = res.data.data || [];
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to load branches.';
    } finally {
        loading.value = false;
    }
}

async function saveBranch() {
    error.value = '';
    try {
        if (editingBranch.value) {
            await api.put(`/branches/${editingBranch.value.id}`, form.value);
        } else {
            await api.post('/branches', form.value);
        }
        showForm.value = false;
        editingBranch.value = null;
        await fetchBranches();
    } catch (err: any) {
        const data = err.response?.data;
        error.value = data?.error?.message || data?.message || 'Failed to save branch.';
    }
}

function editBranch(branch: any) {
    editingBranch.value = branch;
    form.value = {
        name: branch.name,
        location: branch.location || '',
        timezone: branch.timezone || 'Africa/Nairobi',
        edge_device_id: branch.edge_device_id || '',
    };
    showForm.value = true;
}

function addBranch() {
    editingBranch.value = null;
    form.value = { name: '', location: '', timezone: 'Africa/Nairobi', edge_device_id: '' };
    showForm.value = true;
}

onMounted(fetchBranches);
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">Branch Management</h1>
                    <p class="text-text-tertiary mt-1">Manage locations and branch settings</p>
                </div>
                <button @click="addBranch" class="px-4 py-2.5 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                    Add Branch
                </button>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>

            <div v-if="showForm" class="mb-6 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <h2 class="text-lg font-semibold text-text-theme mb-4">{{ editingBranch ? 'Edit Branch' : 'Add Branch' }}</h2>
                <form @submit.prevent="saveBranch" class="space-y-4 max-w-lg">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Branch Name</label>
                        <input v-model="form.name" type="text" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Location</label>
                        <input v-model="form.location" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Timezone</label>
                        <select v-model="form.timezone" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option value="Africa/Nairobi">Africa/Nairobi (EAT)</option>
                            <option value="Africa/Kampala">Africa/Kampala (EAT)</option>
                            <option value="Africa/Dar_es_Salaam">Africa/Dar es Salaam (EAT)</option>
                            <option value="Africa/Lagos">Africa/Lagos (WAT)</option>
                            <option value="Africa/Johannesburg">Africa/Johannesburg (SAST)</option>
                            <option value="UTC">UTC</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Edge Device ID</label>
                        <input v-model="form.edge_device_id" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="showForm = false; editingBranch = null" class="px-4 py-2.5 border border-border-input text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover transition-colors">{{ editingBranch ? 'Update' : 'Create Branch' }}</button>
                    </div>
                </form>
            </div>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <div v-else class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-border-theme bg-surface-alt">
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Name</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Location</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Timezone</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Edge Device</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Sync Status</th>
                            <th class="text-right px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="branch in branches" :key="branch.id" class="border-b border-gray-100 hover:bg-surface-alt">
                            <td class="px-6 py-4 text-sm font-medium text-text-theme">{{ branch.name }}</td>
                            <td class="px-6 py-4 text-sm text-text-secondary">{{ branch.location || '-' }}</td>
                            <td class="px-6 py-4 text-sm text-text-secondary">{{ branch.timezone }}</td>
                            <td class="px-6 py-4 text-sm text-text-secondary">{{ branch.edge_device_id || '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full"
                                    :class="branch.cloud_sync_status === 'synced' ? 'bg-success-light text-success-theme' : branch.cloud_sync_status === 'syncing' ? 'bg-warning-light text-warning-theme' : 'bg-surface-alt text-text-secondary'">
                                    {{ branch.cloud_sync_status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="editBranch(branch)" class="text-primary hover:text-primary text-sm font-medium">Edit</button>
                            </td>
                        </tr>
                        <tr v-if="!branches.length">
                            <td colspan="6" class="px-6 py-12 text-center text-text-tertiary text-sm">No branches found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </component>
</template>
