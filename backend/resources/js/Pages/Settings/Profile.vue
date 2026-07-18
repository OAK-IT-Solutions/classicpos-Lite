<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import api from '@/composables/axios';
import { useAuth } from '@/composables/useAuth';

defineProps<{ embedded?: boolean }>();

const auth = useAuth();
const loading = ref(true);
const saving = ref(false);
const saved = ref(false);
const error = ref('');

const profile = ref({
    name: '',
    email: '',
    branch: null as { id: string; name: string; location: string } | null,
    roles: [] as { id: string; name: string; branch_id: string }[],
    permissions: [] as string[],
    created_at: '',
});

const passwordForm = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showPasswordSection = ref(false);
const passwordError = ref('');
const passwordSaved = ref(false);

const showSecretSection = ref(false);
const secretSaved = ref(false);
const secretError = ref('');
const secretSaving = ref(false);
const secretForm = ref({ question: '', answer: '' });

const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);
const avatarSaving = ref(false);
const avatarError = ref('');

function onAvatarSelected(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files?.[0]) {
        avatarFile.value = target.files[0];
        const reader = new FileReader();
        reader.onload = () => avatarPreview.value = reader.result as string;
        reader.readAsDataURL(target.files[0]);
    }
}

async function saveAvatar() {
    if (!avatarFile.value) return;
    avatarSaving.value = true;
    avatarError.value = '';
    try {
        const fd = new FormData();
        fd.append('avatar', avatarFile.value);
        fd.append('name', profile.value.name);
        fd.append('email', profile.value.email);
        fd.append('_method', 'PUT');
        const res = await api.post('/auth/profile', fd);
        if (res.data?.user?.avatar_url) {
            auth.user.value = { ...auth.user.value, avatar_url: res.data.user.avatar_url };
        }
        avatarFile.value = null;
        avatarPreview.value = null;
    } catch (err: any) {
        avatarError.value = err?.response?.data?.error?.message || 'Failed to upload avatar.';
    } finally { avatarSaving.value = false; }
}

function removeAvatar() {
    avatarFile.value = null;
    avatarPreview.value = null;
}

async function saveSecretQuestion() {
    if (!secretForm.value.question || !secretForm.value.answer) return;
    secretSaving.value = true; secretError.value = ''; secretSaved.value = false;
    try {
        const params = new URLSearchParams({
            secret_question: secretForm.value.question,
            secret_answer: secretForm.value.answer,
        }).toString()
        await api.put(`/auth/profile/secret-question?${params}`);
        secretSaved.value = true;
        setTimeout(() => { showSecretSection.value = false }, 1500);
    } catch (err: any) {
        secretError.value = err?.response?.data?.message || 'Failed to save.';
    } finally { secretSaving.value = false }
}

onMounted(async () => {
    try {
        const res = await api.get('/auth/profile');
        Object.assign(profile.value, res.data.user);
    } catch {
        error.value = 'Failed to load profile.';
    } finally {
        loading.value = false;
    }
});

async function saveProfile() {
    saving.value = true;
    saved.value = false;
    error.value = '';

    try {
        const res = await api.put('/auth/profile', {
            name: profile.value.name,
            email: profile.value.email,
        });
        saved.value = true;
        auth.check();
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to save profile.';
    } finally {
        saving.value = false;
    }
}

async function savePassword() {
    saving.value = true;
    passwordSaved.value = false;
    passwordError.value = '';

    if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
        passwordError.value = 'Passwords do not match.';
        saving.value = false;
        return;
    }

    try {
        await api.put('/auth/profile', {
            current_password: passwordForm.value.current_password,
            password: passwordForm.value.password,
        });
        passwordSaved.value = true;
        passwordForm.value = { current_password: '', password: '', password_confirmation: '' };
    } catch (err: any) {
        passwordError.value = err.response?.data?.error?.message || 'Failed to update password.';
    } finally {
        saving.value = false;
    }
}

function getRoleBadgeClass(role: string) {
    switch (role) {
        case 'admin': return 'bg-purple-100 text-purple-700';
        case 'branch_manager': return 'bg-blue-100 text-primary';
        case 'cashier': return 'bg-success-light text-success-theme';
        case 'inventory_clerk': return 'bg-orange-100 text-orange-700';
        default: return 'bg-surface-alt text-text-secondary';
    }
}

function formatRoleName(role: string) {
    return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-3xl mx-auto'">
            <div v-if="!embedded" class="mb-8">
                <h1 class="text-2xl font-bold text-text-theme">My Profile</h1>
                <p class="text-text-tertiary mt-1">View and update your account settings</p>
            </div>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <div v-else class="space-y-6">
                <div v-if="saved" class="p-3 bg-success-light border border-success-theme/20 rounded-lg text-sm text-success-theme">
                    Profile updated successfully.
                </div>
                <div v-if="error" class="p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>

                <!-- Avatar -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Profile Photo</h2>
                    <div class="flex items-center gap-4">
                        <div class="relative w-20 h-20 rounded-full overflow-hidden bg-surface-alt flex-shrink-0">
                            <img v-if="avatarPreview || auth.user?.avatar_url" :src="avatarPreview || auth.user?.avatar_url!" alt="" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-2xl font-bold text-text-tertiary">
                                {{ profile.name?.charAt(0)?.toUpperCase() || 'U' }}
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover cursor-pointer text-center transition-colors">
                                {{ avatarFile ? 'Change Photo' : 'Upload Photo' }}
                                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="onAvatarSelected" />
                            </label>
                            <button v-if="avatarFile" @click="saveAvatar" :disabled="avatarSaving" class="px-4 py-2 bg-success-light text-success-theme rounded-lg text-sm font-medium hover:bg-success-light disabled:opacity-50 transition-colors">
                                {{ avatarSaving ? 'Saving...' : 'Save Photo' }}
                            </button>
                            <button v-if="avatarFile" @click="removeAvatar" class="px-4 py-2 bg-surface-alt text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors">
                                Cancel
                            </button>
                            <p class="text-xs text-text-tertiary">JPEG, PNG, or WebP. Max 2MB.</p>
                            <p v-if="avatarError" class="text-xs text-danger-theme">{{ avatarError }}</p>
                        </div>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Account Information</h2>
                    <form @submit.prevent="saveProfile" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Full Name</label>
                            <input v-model="profile.name" type="text" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Email Address</label>
                            <input v-model="profile.email" type="email" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                        </div>
                        <div v-if="profile.branch">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Branch</label>
                            <div class="flex items-center gap-2 text-sm text-text-secondary">
                                <span>{{ profile.branch.name }}</span>
                                <span v-if="profile.branch.location" class="text-text-tertiary">·</span>
                                <span v-if="profile.branch.location" class="text-text-tertiary">{{ profile.branch.location }}</span>
                            </div>
                        </div>
                        <div v-if="profile.created_at">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Member Since</label>
                            <span class="text-sm text-text-secondary">{{ new Date(profile.created_at).toLocaleDateString() }}</span>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" :disabled="saving" class="px-5 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                                {{ saving ? 'Saving...' : 'Save Profile' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Roles & Permissions -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Roles & Permissions</h2>
                    <div v-if="profile.roles.length" class="space-y-3">
                        <div v-for="role in profile.roles" :key="role.id" class="flex items-center gap-2">
                            <span :class="[getRoleBadgeClass(role.name), 'px-2.5 py-1 rounded-full text-xs font-medium']">
                                {{ formatRoleName(role.name) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-text-tertiary">No roles assigned. Contact an admin to assign roles.</p>

                    <div v-if="profile.permissions.length" class="mt-4 pt-4 border-t border-gray-100">
                        <h3 class="text-sm font-medium text-text-secondary mb-2">Your Permissions</h3>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="perm in profile.permissions" :key="perm" class="px-2 py-0.5 bg-surface-alt text-text-secondary rounded text-xs">
                                {{ perm.replace(/_/g, ' ') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Security Question -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-text-theme">Security Question</h2>
                        <button v-if="!showSecretSection" @click="showSecretSection = true" class="text-sm text-primary hover:text-primary">Set</button>
                    </div>

                    <div v-if="showSecretSection">
                        <div v-if="secretSaved" class="mb-3 p-3 bg-success-light border border-success-theme/20 rounded-lg text-sm text-success-theme">Security question saved.</div>
                        <div v-if="secretError" class="mb-3 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ secretError }}</div>
                        <form @submit.prevent="saveSecretQuestion" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Choose a Question</label>
                                <select v-model="secretForm.question" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring">
                                    <option value="" disabled>Select a question...</option>
                                    <option value="What city were you born in?">What city were you born in?</option>
                                    <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                                    <option value="What was your first pet's name?">What was your first pet's name?</option>
                                    <option value="What was the name of your first school?">What was the name of your first school?</option>
                                    <option value="What is your favorite book?">What is your favorite book?</option>
                                    <option value="What is the name of your childhood best friend?">What is the name of your childhood best friend?</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Your Answer</label>
                                <input v-model="secretForm.answer" type="text" required maxlength="255" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="showSecretSection = false" class="px-4 py-2 text-sm text-text-secondary hover:text-text-theme">Cancel</button>
                                <button type="submit" :disabled="secretSaving" class="px-5 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">{{ secretSaving ? 'Saving...' : 'Save' }}</button>
                            </div>
                        </form>
                        <p class="text-xs text-text-tertiary mt-2">Used for offline password recovery. Cannot be retrieved if forgotten.</p>
                    </div>
                    <p v-else class="text-sm text-text-tertiary">Set a security question for offline password recovery.</p>
                </div>

                <!-- Password Change -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-text-theme">Change Password</h2>
                        <button v-if="!showPasswordSection" @click="showPasswordSection = true" class="text-sm text-primary hover:text-primary">
                            Edit
                        </button>
                    </div>

                    <div v-if="showPasswordSection">
                        <div v-if="passwordSaved" class="mb-3 p-3 bg-success-light border border-success-theme/20 rounded-lg text-sm text-success-theme">
                            Password updated successfully.
                        </div>
                        <div v-if="passwordError" class="mb-3 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">
                            {{ passwordError }}
                        </div>

                        <form @submit.prevent="savePassword" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Current Password</label>
                                <input v-model="passwordForm.current_password" type="password" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">New Password</label>
                                <input v-model="passwordForm.password" type="password" required minlength="8" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Confirm New Password</label>
                                <input v-model="passwordForm.password_confirmation" type="password" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="showPasswordSection = false" class="px-4 py-2 text-sm text-text-secondary hover:text-text-theme">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="saving" class="px-5 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                                    {{ saving ? 'Updating...' : 'Update Password' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <p v-else class="text-sm text-text-tertiary">Last changed: unknown</p>
                </div>
            </div>
        </div>
    </component>
</template>
