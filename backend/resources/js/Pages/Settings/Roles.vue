<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';

defineProps<{ embedded?: boolean }>();

const roles = ref<any[]>([]);
const permissions = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const saving = ref(false);
const showForm = ref(false);
const editingRole = ref<any>(null);

const form = ref({
    name: '',
    permission_ids: [] as string[],
});

const defaultRoles = ['admin', 'branch_manager', 'cashier', 'inventory_clerk'];

async function fetchData() {
    loading.value = true;
    error.value = '';
    try {
        const [rolesRes, permsRes] = await Promise.all([
            api.get('/roles'),
            api.get('/permissions').catch(() => ({ data: { data: [] } })),
        ]);
        roles.value = rolesRes.data.data || [];
        permissions.value = permsRes.data?.data || [];
    } catch (err: any) {
        error.value = 'Failed to load data.';
    } finally {
        loading.value = false;
    }
}

function addRole() {
    editingRole.value = null;
    form.value = { name: '', permission_ids: [] };
    showForm.value = true;
}

function editRole(role: any) {
    editingRole.value = role;
    form.value = {
        name: role.name,
        permission_ids: (role.permissions || []).map((p: any) => p.id),
    };
    showForm.value = true;
}

async function saveRole() {
    saving.value = true;
    error.value = '';
    try {
        if (editingRole.value) {
            await api.put(`/roles/${editingRole.value.id}`, {
                name: form.value.name,
            });
            await api.put(`/roles/${editingRole.value.id}/permissions`, {
                permission_ids: form.value.permission_ids,
            });
        } else {
            await api.post('/roles', {
                name: form.value.name,
                permission_ids: form.value.permission_ids,
            });
        }
        showForm.value = false;
        editingRole.value = null;
        await fetchData();
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to save role.';
    } finally {
        saving.value = false;
    }
}

async function deleteRole(role: any) {
    if (!confirm(`Are you sure you want to delete the "${role.name}" role?`)) return;
    error.value = '';
    try {
        await api.delete(`/roles/${role.id}`);
        await fetchData();
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to delete role.';
    }
}

function togglePermission(permId: string) {
    const idx = form.value.permission_ids.indexOf(permId);
    if (idx === -1) {
        form.value.permission_ids.push(permId);
    } else {
        form.value.permission_ids.splice(idx, 1);
    }
}

function selectAllPermissions() {
    form.value.permission_ids = permissions.value.map((p: any) => p.id);
}

function deselectAllPermissions() {
    form.value.permission_ids = [];
}

function formatPermName(name: string) {
    return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

onMounted(fetchData);
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-6xl mx-auto'">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">Roles & Permissions</h1>
                    <p class="text-text-tertiary mt-1">Manage roles and customize permissions</p>
                </div>
                <button @click="addRole" class="px-4 py-2.5 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                    Create Custom Role
                </button>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>

            <!-- Role Form -->
            <div v-if="showForm" class="mb-6 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <h2 class="text-lg font-semibold text-text-theme mb-4">{{ editingRole ? 'Edit Role' : 'Create Custom Role' }}</h2>
                <form @submit.prevent="saveRole" class="space-y-6">
                    <div class="max-w-md">
                        <label class="block text-sm font-medium text-text-secondary mb-1">Role Name</label>
                        <input v-model="form.name" type="text" required
                            class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"
                            placeholder="e.g. auditor" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-medium text-text-secondary">Permissions</label>
                            <div class="flex gap-2">
                                <button type="button" @click="selectAllPermissions" class="text-xs text-primary hover:text-primary font-medium">Select All</button>
                                <button type="button" @click="deselectAllPermissions" class="text-xs text-text-tertiary hover:text-text-secondary font-medium">Clear</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            <label v-for="perm in permissions" :key="perm.id"
                                class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors"
                                :class="form.permission_ids.includes(perm.id)
                                    ? 'border-primary bg-primary-light/30'
                                    : 'border-border-input hover:border-border-theme'">
                                <input type="checkbox"
                                    :checked="form.permission_ids.includes(perm.id)"
                                    @change="togglePermission(perm.id)"
                                    class="rounded border-border-input text-primary focus:ring-primary-ring" />
                                <span class="text-sm text-text-secondary">{{ formatPermName(perm.name) }}</span>
                            </label>
                            <div v-if="!permissions.length" class="col-span-full text-sm text-text-tertiary py-4 text-center">
                                No permissions available.
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" @click="showForm = false; editingRole = null"
                            class="px-4 py-2.5 border border-border-input text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving"
                            class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                            {{ saving ? 'Saving...' : (editingRole ? 'Update Role' : 'Create Role') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Roles Table -->
            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <div v-else class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-border-theme bg-surface-alt">
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Role</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Permissions</th>
                            <th class="text-right px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="role in roles" :key="role.id" class="border-b border-gray-100 hover:bg-surface-alt">
                            <td class="px-6 py-4 text-sm font-medium text-text-theme">
                                {{ role.name }}
                                <span v-if="!role.is_editable" class="ml-2 px-1.5 py-0.5 bg-surface-alt text-text-tertiary rounded text-xs">Default</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="perm in role.permissions" :key="perm.id"
                                        class="px-2 py-0.5 bg-primary-light text-primary rounded text-xs">
                                        {{ formatPermName(perm.name) }}
                                    </span>
                                    <span v-if="!role.permissions.length" class="text-text-tertiary text-xs">No permissions</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="editRole(role)" class="text-primary hover:text-primary text-sm font-medium mr-3">Edit</button>
                                <button v-if="role.is_editable" @click="deleteRole(role)" class="text-danger-theme hover:text-danger-theme text-sm font-medium">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!roles.length">
                            <td colspan="3" class="px-6 py-12 text-center text-text-tertiary text-sm">No roles found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </component>
</template>
