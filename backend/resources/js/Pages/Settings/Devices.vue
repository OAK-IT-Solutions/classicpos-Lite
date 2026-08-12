<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';

defineProps<{ embedded?: boolean }>();

const devices = ref<any[]>([]);
const branches = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const showForm = ref(false);
const editingDevice = ref<any>(null);

const form = ref({
    branch_id: '',
    name: '',
    device_id: '',
    type: 'edge_node',
    description: '',
    os: '',
    ip_address: '',
    mac_address: '',
});

const deviceTypes = [
    { value: 'edge_node', label: 'Edge Node' },
    { value: 'pos_terminal', label: 'POS Terminal' },
    { value: 'tablet', label: 'Tablet' },
    { value: 'phone', label: 'Phone' },
];

async function fetchData() {
    try {
        const [devicesRes, branchesRes] = await Promise.all([
            api.get('/devices'),
            api.get('/branches'),
        ]);
        devices.value = devicesRes.data.data || [];
        branches.value = branchesRes.data.data || [];
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to load data.';
    } finally {
        loading.value = false;
    }
}

async function saveDevice() {
    error.value = '';
    try {
        if (editingDevice.value) {
            await api.put(`/devices/${editingDevice.value.id}`, form.value);
        } else {
            await api.post('/devices', form.value);
        }
        showForm.value = false;
        editingDevice.value = null;
        await fetchData();
    } catch (err: any) {
        const data = err.response?.data;
        error.value = data?.error?.message || data?.message || 'Failed to save device.';
    }
}

function editDevice(device: any) {
    editingDevice.value = device;
    form.value = {
        branch_id: device.branch_id,
        name: device.name,
        device_id: device.device_id,
        type: device.type || 'edge_node',
        description: device.description || '',
        os: device.os || '',
        ip_address: device.ip_address || '',
        mac_address: device.mac_address || '',
    };
    showForm.value = true;
}

function addDevice() {
    editingDevice.value = null;
    form.value = { branch_id: '', name: '', device_id: '', type: 'edge_node', description: '', os: '', ip_address: '', mac_address: '' };
    showForm.value = true;
}

function statusClass(status: string) {
    switch (status) {
        case 'active': return 'bg-success-light text-success-theme';
        case 'inactive': return 'bg-surface-alt text-text-secondary';
        case 'pending': return 'bg-warning-light text-warning-theme';
        case 'decommissioned': return 'bg-danger-light text-danger-theme';
        default: return 'bg-surface-alt text-text-secondary';
    }
}

onMounted(fetchData);
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-6xl mx-auto'">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">Device Management</h1>
                    <p class="text-text-tertiary mt-1">Register and manage edge nodes, POS terminals, and tablets</p>
                </div>
                <button @click="addDevice" class="px-4 py-2.5 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                    Register Device
                </button>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>

            <div v-if="showForm" class="mb-6 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <h2 class="text-lg font-semibold text-text-theme mb-4">{{ editingDevice ? 'Edit Device' : 'Register Device' }}</h2>
                <form @submit.prevent="saveDevice" class="space-y-4 max-w-lg">
                    <div v-if="!editingDevice">
                        <label class="block text-sm font-medium text-text-secondary mb-1">Branch</label>
                        <select v-model="form.branch_id" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option value="" disabled>Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Device Name</label>
                        <input v-model="form.name" type="text" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div v-if="!editingDevice">
                        <label class="block text-sm font-medium text-text-secondary mb-1">Device ID (unique identifier)</label>
                        <input v-model="form.device_id" type="text" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Device Type</label>
                        <select v-model="form.type" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option v-for="dt in deviceTypes" :key="dt.value" :value="dt.value">{{ dt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Description</label>
                        <input v-model="form.description" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Operating System</label>
                        <input v-model="form.os" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">IP Address</label>
                            <input v-model="form.ip_address" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">MAC Address</label>
                            <input v-model="form.mac_address" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="showForm = false; editingDevice = null" class="px-4 py-2.5 border border-border-input text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover transition-colors">{{ editingDevice ? 'Update' : 'Register' }}</button>
                    </div>
                </form>
            </div>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <div v-else class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-border-theme bg-surface-alt">
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Name</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Type</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Branch</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Status</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Last Seen</th>
                            <th class="text-right px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="device in devices" :key="device.id" class="border-b border-gray-100 hover:bg-surface-alt">
                            <td class="px-6 py-4 text-sm font-medium text-text-theme">{{ device.name }}</td>
                            <td class="px-6 py-4 text-sm text-text-secondary">{{ device.type }}</td>
                            <td class="px-6 py-4 text-sm text-text-secondary">{{ device.branch?.name || device.branch_id }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="statusClass(device.status)">
                                    {{ device.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-text-secondary">{{ device.last_seen_at ? new Date(device.last_seen_at).toLocaleString() : 'Never' }}</td>
                            <td class="px-6 py-4 text-right">
                                <button @click="editDevice(device)" class="text-primary hover:text-primary text-sm font-medium">Edit</button>
                            </td>
                        </tr>
                        <tr v-if="!devices.length">
                            <td colspan="6" class="px-6 py-12 text-center text-text-tertiary text-sm">No devices registered.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </component>
</template>
