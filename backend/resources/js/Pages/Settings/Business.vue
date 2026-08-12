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

const businessTypes = [
    { value: 'bar_restaurant', label: 'Bar & Restaurant' },
    { value: 'retail', label: 'Retail Store' },
    { value: 'service', label: 'Service Business' },
    { value: 'pharmacy', label: 'Pharmacy' },
];

const form = ref({
    legal_business_name: '',
    trading_name: '',
    business_type: 'bar_restaurant',
    tax_id: '',
    vat_registered: false,
    country: 'KE',
    address_line1: '',
    address_line2: '',
    city: '',
    state_province: '',
    postal_code: '',
    phone: '',
    email: '',
    website: '',
    registration_number: '',
    established_year: null as number | null,
    description: '',
});

const error = ref('');

const logoFile = ref<File | null>(null);
const logoPreview = ref<string | null>(null);
const existingLogo = ref<string | null>(null);
const logoSaving = ref(false);
const logoError = ref('');

function onLogoSelected(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files?.[0]) {
        logoFile.value = target.files[0];
        const reader = new FileReader();
        reader.onload = () => logoPreview.value = reader.result as string;
        reader.readAsDataURL(target.files[0]);
    }
}

onMounted(async () => {
    try {
        const res = await api.get('/onboarding/status');
        if (res.data.profile) {
            Object.assign(form.value, res.data.profile);
            existingLogo.value = res.data.profile.logo_url ?? null;
        }
    } catch {
        //
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    saved.value = false;
    error.value = '';

    try {
        const fd = new FormData();
        for (const [key, val] of Object.entries(form.value)) {
            fd.append(key, val === null ? '' : String(val));
        }
        fd.append('_method', 'PUT');
        if (logoFile.value) fd.append('logo', logoFile.value);
        const res = await api.post('/onboarding/profile', fd);
        if (res.data.profile?.logo_url) {
            existingLogo.value = res.data.profile.logo_url;
        }
        saved.value = true;
        logoFile.value = null;
        logoPreview.value = null;
        auth.check();
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to save.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-3xl mx-auto'">
            <div v-if="!embedded" class="mb-8">
                <h1 class="text-2xl font-bold text-text-theme">Business Settings</h1>
                <p class="text-text-tertiary mt-1">Manage your business profile and preferences</p>
            </div>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <div v-else>
                <div v-if="saved" class="mb-4 p-3 bg-success-light border border-success-theme/20 rounded-lg text-sm text-success-theme">
                    Business profile updated successfully.
                </div>
                <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>

                <form @submit.prevent="save" class="space-y-6">
                    <!-- Business Logo -->
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                        <h2 class="text-lg font-semibold text-text-theme mb-4">Business Logo</h2>
                        <div class="flex items-center gap-4">
                            <div class="relative w-24 h-24 rounded-xl overflow-hidden bg-surface-alt flex-shrink-0 border border-border-theme">
                                <img v-if="logoPreview || existingLogo" :src="logoPreview || existingLogo!" alt="Logo" class="w-full h-full object-contain" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <svg class="w-8 h-8 text-border-input" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="px-4 py-2 bg-btn-primary text-white rounded-lg text-sm font-medium hover:bg-btn-primary-hover cursor-pointer text-center transition-colors">
                                    {{ logoFile ? 'Change Logo' : 'Upload Logo' }}
                                    <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="onLogoSelected" />
                                </label>
                                <button v-if="logoFile" @click="logoFile = null; logoPreview = null" type="button" class="px-4 py-2 bg-surface-alt text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors">
                                    Remove
                                </button>
                                <p class="text-xs text-text-tertiary">JPEG, PNG, or WebP. Max 2MB.</p>
                                <p v-if="logoError" class="text-xs text-danger-theme">{{ logoError }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                        <h2 class="text-lg font-semibold text-text-theme mb-4">Business Information</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-text-secondary mb-1">Legal Business Name</label>
                                <input v-model="form.legal_business_name" type="text" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Trading Name</label>
                                <input v-model="form.trading_name" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Business Type</label>
                                <select v-model="form.business_type" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                                    <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Country</label>
                                <select v-model="form.country" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised">
                                    <option value="KE">Kenya</option>
                                    <option value="UG">Uganda</option>
                                    <option value="TZ">Tanzania</option>
                                    <option value="NG">Nigeria</option>
                                    <option value="ZA">South Africa</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Tax ID</label>
                                <input v-model="form.tax_id" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                        </div>
                    </div>

                    <div class="bg-primary-light border border-primary/20 rounded-xl p-4 text-sm text-primary">
                        <p class="font-medium">Currency & timezone settings moved</p>
                        <p class="mt-1">These are now managed in the <strong>Locale</strong> settings tab for better organization.</p>
                    </div>

                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                        <h2 class="text-lg font-semibold text-text-theme mb-4">Contact & Address</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-text-secondary mb-1">Address</label>
                                <input v-model="form.address_line1" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring mb-2" placeholder="Line 1" />
                                <input v-model="form.address_line2" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" placeholder="Line 2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">City</label>
                                <input v-model="form.city" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Postal Code</label>
                                <input v-model="form.postal_code" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Phone</label>
                                <input v-model="form.phone" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Email</label>
                                <input v-model="form.email" type="email" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="submit" :disabled="saving" class="px-6 py-2.5 bg-btn-primary text-white rounded-lg font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                            {{ saving ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </component>
</template>
