<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';
import { useAuth } from '@/composables/useAuth';

defineProps<{ embedded?: boolean }>();

const auth = useAuth();

const users = ref<any[]>([]);
const branches = ref<any[]>([]);
const roles = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const showForm = ref(false);
const editingUser = ref<any>(null);
const showRolePanel = ref(false);
const roleUser = ref<any>(null);

const form = ref({
    name: '',
    email: '',
    password: '',
    branch_ids: [] as string[],
    role_id: '',
    is_active: true,
});

const roleForm = ref({
    user_id: '',
    role_id: '',
    branch_ids: [] as string[],
});

const showPasswordModal = ref(false);
const activatingUser = ref<any>(null);
const activationPassword = ref('');

async function fetchData() {
    try {
        const [usersRes, branchesRes, rolesRes] = await Promise.all([
            api.get('/users', { params: { per_page: 50 } }),
            api.get('/branches'),
            api.get('/roles').catch(() => ({ data: [] })),
        ]);
        users.value = usersRes.data.data || [];
        branches.value = branchesRes.data.data || [];
        roles.value = rolesRes.data?.data || rolesRes.data || [];
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to load data.';
    } finally {
        loading.value = false;
    }
}

async function saveUser() {
    error.value = '';
    try {
        if (editingUser.value) {
            const payload: any = { name: form.value.name, email: form.value.email, is_active: form.value.is_active };
            if (form.value.password) payload.password = form.value.password;
            payload.branch_ids = form.value.branch_ids;
            await api.put(`/users/${editingUser.value.id}`, payload);
        } else {
            await api.post('/users', form.value);
        }
        showForm.value = false;
        editingUser.value = null;
        resetForm();
        await fetchData();
    } catch (err: any) {
        const data = err.response?.data;
        error.value = data?.error?.message || data?.message || 'Failed to save user.';
    }
}

function editUser(user: any) {
    editingUser.value = user;
    form.value = {
        name: user.name,
        email: user.email,
        password: '',
        branch_ids: (user.branches || []).map((b: any) => b.id),
        role_id: '',
        is_active: user.is_active,
    };
    showForm.value = true;
}

function addUser() {
    editingUser.value = null;
    resetForm();
    showForm.value = true;
}

function resetForm() {
    form.value = { name: '', email: '', password: '', branch_ids: [], role_id: '', is_active: true };
}

function toggleBranch(branchId: string) {
    const idx = form.value.branch_ids.indexOf(branchId);
    if (idx === -1) {
        form.value.branch_ids.push(branchId);
    } else {
        form.value.branch_ids.splice(idx, 1);
    }
}

function toggleRoleBranch(branchId: string) {
    const idx = roleForm.value.branch_ids.indexOf(branchId);
    if (idx === -1) {
        roleForm.value.branch_ids.push(branchId);
    } else {
        roleForm.value.branch_ids.splice(idx, 1);
    }
}

function selectAllBranches(formRef: { branch_ids: string[] }) {
    formRef.branch_ids = branches.value.map(b => b.id);
}

function deselectAllBranches(formRef: { branch_ids: string[] }) {
    formRef.branch_ids = [];
}

function openRolePanel(user: any) {
    roleUser.value = user;
    roleForm.value = { user_id: user.id, role_id: '', branch_ids: [] };
    showRolePanel.value = true;
}

async function assignRole() {
    error.value = '';
    if (!roleForm.value.branch_ids.length) {
        error.value = 'Please select at least one branch.';
        return;
    }
    try {
        await api.post('/users/assign-role', roleForm.value);
        showRolePanel.value = false;
        await fetchData();
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to assign role.';
    }
}

async function revokeRole(userId: string, roleId: string, branchId: string) {
    error.value = '';
    try {
        await api.post('/users/revoke-role', {
            user_id: userId,
            role_id: roleId,
            branch_ids: [branchId],
        });
        await fetchData();
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to revoke role.';
    }
}

function toggleDefaultAccount(user: any) {
    if (user.is_active) {
        deactivateDefaultAccount(user);
    } else {
        activatingUser.value = user;
        activationPassword.value = '';
        showPasswordModal.value = true;
    }
}

async function confirmActivation() {
    if (!activationPassword.value || activationPassword.value.length < 8) {
        error.value = 'Password must be at least 8 characters.';
        return;
    }
    error.value = '';
    try {
        await api.put(`/users/${activatingUser.value.id}`, {
            password: activationPassword.value,
            is_active: true,
        });
        showPasswordModal.value = false;
        activatingUser.value = null;
        activationPassword.value = '';
        await fetchData();
    } catch (err: any) {
        const data = err.response?.data;
        error.value = data?.error?.message || data?.message || 'Failed to activate account.';
    }
}

async function deactivateDefaultAccount(user: any) {
    error.value = '';
    try {
        await api.put(`/users/${user.id}`, { is_active: false });
        await fetchData();
    } catch (err: any) {
        const data = err.response?.data;
        error.value = data?.error?.message || data?.message || 'Failed to deactivate account.';
    }
}

function formatRoleName(name: string) {
    return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

onMounted(fetchData);
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-6xl mx-auto'">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">User Management</h1>
                    <p class="text-text-tertiary mt-1">Manage users, roles, and permissions</p>
                </div>
                <button @click="addUser" class="px-4 py-2.5 bg-btn-primary text-white text-sm font-medium rounded-lg hover:bg-btn-primary-hover transition-colors">
                    Add User
                </button>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>

            <!-- Password Activation Modal -->
            <div v-if="showPasswordModal && activatingUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showPasswordModal = false">
                <div class="bg-surface-raised rounded-xl shadow-xl border border-border-theme p-6 w-full max-w-md mx-4">
                    <h2 class="text-lg font-semibold text-text-theme mb-2">Activate {{ activatingUser.name }}</h2>
                    <p class="text-sm text-text-tertiary mb-4">Set a password to activate this system account.</p>
                    <form @submit.prevent="confirmActivation" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Password</label>
                            <input v-model="activationPassword" type="password" required minlength="8" autocomplete="new-password"
                                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                        </div>
                        <div v-if="error" class="text-sm text-danger-theme">{{ error }}</div>
                        <div class="flex gap-3 justify-end">
                            <button type="button" @click="showPasswordModal = false" class="px-4 py-2.5 border border-border-input text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover transition-colors">Activate</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- User Form -->
            <div v-if="showForm" class="mb-6 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <h2 class="text-lg font-semibold text-text-theme mb-4">{{ editingUser ? 'Edit User' : 'Add User' }}</h2>
                <form @submit.prevent="saveUser" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Full Name</label>
                            <input v-model="form.name" type="text" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Email</label>
                            <input v-model="form.email" type="email" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">{{ editingUser ? 'New Password (leave blank to keep)' : 'Password' }}</label>
                            <input v-model="form.password" type="password" :required="!editingUser" minlength="8" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                        </div>
                        <div v-if="editingUser">
                            <label class="flex items-center gap-3 mt-6">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-border-input text-primary focus:ring-primary-ring" />
                                <span class="text-sm text-text-secondary">Active</span>
                            </label>
                        </div>
                    </div>

                    <!-- Branch Selection (multi-select checkboxes) -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-text-secondary">Assigned Branches</label>
                            <div class="flex gap-2">
                                <button type="button" @click="selectAllBranches(form)" class="text-xs text-primary hover:text-primary font-medium">Select All</button>
                                <button type="button" @click="deselectAllBranches(form)" class="text-xs text-text-tertiary hover:text-text-secondary font-medium">Clear</button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="b in branches" :key="b.id"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer transition-colors text-sm"
                                :class="form.branch_ids.includes(b.id)
                                    ? 'border-primary bg-primary-light/30 text-primary'
                                    : 'border-border-input hover:border-border-theme text-text-secondary'">
                                <input type="checkbox"
                                    :checked="form.branch_ids.includes(b.id)"
                                    @change="toggleBranch(b.id)"
                                    class="rounded border-border-input text-primary focus:ring-primary-ring" />
                                {{ b.name }}
                            </label>
                            <div v-if="!branches.length" class="text-sm text-text-tertiary py-2">No branches available.</div>
                        </div>
                    </div>

                    <div v-if="!editingUser">
                        <label class="block text-sm font-medium text-text-secondary mb-1">Initial Role</label>
                        <select v-model="form.role_id" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised max-w-md">
                            <option value="" disabled>Select role</option>
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ formatRoleName(r.name) }}</option>
                        </select>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" @click="showForm = false; editingUser = null" class="px-4 py-2.5 border border-border-input text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover transition-colors">{{ editingUser ? 'Update' : 'Create User' }}</button>
                    </div>
                </form>
            </div>

            <!-- Assign Role Panel -->
            <div v-if="showRolePanel && roleUser" class="mb-6 bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                <h2 class="text-lg font-semibold text-text-theme mb-4">Assign Role to {{ roleUser.name }}</h2>
                <form @submit.prevent="assignRole" class="space-y-4 max-w-md">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Role</label>
                        <select v-model="roleForm.role_id" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                            <option value="" disabled>Select role</option>
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ formatRoleName(r.name) }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-text-secondary">Branches</label>
                            <div class="flex gap-2">
                                <button type="button" @click="selectAllBranches(roleForm)" class="text-xs text-primary hover:text-primary font-medium">Select All</button>
                                <button type="button" @click="deselectAllBranches(roleForm)" class="text-xs text-text-tertiary hover:text-text-secondary font-medium">Clear</button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="b in branches" :key="b.id"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer transition-colors text-sm"
                                :class="roleForm.branch_ids.includes(b.id)
                                    ? 'border-primary bg-primary-light/30 text-primary'
                                    : 'border-border-input hover:border-border-theme text-text-secondary'">
                                <input type="checkbox"
                                    :checked="roleForm.branch_ids.includes(b.id)"
                                    @change="toggleRoleBranch(b.id)"
                                    class="rounded border-border-input text-primary focus:ring-primary-ring" />
                                {{ b.name }}
                            </label>
                            <div v-if="!branches.length" class="text-sm text-text-tertiary py-2">No branches available.</div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="showRolePanel = false" class="px-4 py-2.5 border border-border-input text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2.5 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover transition-colors">Assign Role</button>
                    </div>
                </form>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <!-- Users Table -->
            <div v-else class="bg-surface-raised rounded-xl shadow-sm border border-border-theme overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-border-theme bg-surface-alt">
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Name</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Email</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Branches</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Branch Roles</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Status</th>
                            <th class="text-right px-6 py-3 text-xs font-medium text-text-tertiary uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id" class="border-b border-gray-100 hover:bg-surface-alt">
                            <td class="px-6 py-4 text-sm font-medium text-text-theme">
                                <span>{{ user.name }}</span>
                                <span v-if="user.is_default_account"
                                    class="ml-2 px-1.5 py-0.5 bg-warning-light text-warning-theme text-[10px] font-semibold rounded uppercase tracking-wider">
                                    System
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-text-secondary">{{ user.email }}</td>
                            <td class="px-6 py-4 text-sm text-text-secondary">
                                <div v-if="user.branches && user.branches.length" class="flex flex-wrap gap-1">
                                    <span v-for="b in user.branches" :key="b.id"
                                        class="px-2 py-0.5 bg-surface-alt text-text-secondary rounded-full text-xs">
                                        {{ b.name }}
                                    </span>
                                </div>
                                <span v-else class="text-text-tertiary text-xs">No branches</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-text-secondary">
                                <div v-if="user.roles && user.roles.length">
                                    <span v-for="r in user.roles" :key="r.id + (r.pivot_branch_id || '')" class="inline-flex items-center gap-1 mr-2 mb-1 px-2 py-0.5 bg-primary-light text-primary rounded-full text-xs">
                                        {{ formatRoleName(r.name) }}
                                    </span>
                                </div>
                                <span v-else class="text-text-tertiary text-xs">No roles</span>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="user.is_default_account" class="flex items-center">
                                    <button type="button" @click="toggleDefaultAccount(user)"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-ring focus:ring-offset-1"
                                        :class="user.is_active ? 'bg-success-theme' : 'bg-gray-300'">
                                        <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform duration-200"
                                            :class="user.is_active ? 'translate-x-[18px]' : 'translate-x-[3px]'">
                                        </span>
                                    </button>
                                    <span class="ml-2 text-xs text-text-tertiary">{{ user.is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                                <span v-else class="px-2 py-0.5 text-xs font-medium rounded-full"
                                    :class="user.is_active ? 'bg-success-light text-success-theme' : 'bg-danger-light text-danger-theme'">
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button v-if="!user.is_default_account" @click="openRolePanel(user)" class="text-primary hover:text-primary text-sm font-medium mr-3">Assign Role</button>
                                <button v-if="!user.is_default_account" @click="editUser(user)" class="text-primary hover:text-primary text-sm font-medium">Edit</button>
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td colspan="6" class="px-6 py-12 text-center text-text-tertiary text-sm">No users found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </component>
</template>
