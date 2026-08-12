<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import api from '@/composables/axios';
import { useAuth } from '@/composables/useAuth';

const auth = useAuth();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const hasProfile = ref(false);

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
    currency: 'USD',
    country: '',
    timezone: 'Africa/Nairobi',
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

onMounted(async () => {
    try {
        const res = await api.get('/onboarding/status');
        hasProfile.value = res.data.onboarding_completed;
        if (res.data.profile) {
            form.value = { ...form.value, ...res.data.profile };
        }
    } catch {
        //
    } finally {
        loading.value = false;
    }
});

async function submit() {
    saving.value = true;
    error.value = '';

    try {
        await api.post('/onboarding/complete', {
            ...form.value,
            business_type: auth.user?.branch?.business_type || form.value.business_type,
        });
        auth.check().then(() => {
            router.visit('/dashboard');
        });
    } catch (err: any) {
        const data = err.response?.data;
        error.value = data?.error?.message || 'Failed to save profile. Please try again.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <AuthLayout wide>
        <h2 class="text-2xl font-semibold mb-2 text-text-theme">Complete Your Business Profile</h2>
        <p class="text-text-tertiary text-sm mb-6">Tell us about your business to get started</p>

        <div v-if="loading" class="text-center py-8 text-text-tertiary">Loading...</div>

        <div v-else>
            <div v-if="hasProfile" class="mb-4 p-3 bg-success-light border border-green-200 rounded-lg text-sm text-success-theme">
                Your business profile is complete. You can still update it below.
            </div>

            <div v-if="error" class="mb-4 p-3 bg-danger-light border border-red-200 rounded-lg text-sm text-danger-theme">
                {{ error }}
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <h3 class="text-sm font-semibold text-text-theme mb-3 uppercase tracking-wide">Business Information</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Legal Business Name *</label>
                            <input v-model="form.legal_business_name" type="text" required class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="My Bar & Grill LLC" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Trading Name</label>
                            <input v-model="form.trading_name" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="Same as legal" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Business Type *</label>
                            <select v-model="form.business_type" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-surface-raised">
                                <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Registration Number</label>
                            <input v-model="form.registration_number" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="BN/12345/2024" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Tax ID / EIN</label>
                            <input v-model="form.tax_id" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="XX-XXXXXXX" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Established Year</label>
                            <input v-model.number="form.established_year" type="number" min="1900" max="2030" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="2024" />
                        </div>
                    </div>
                </div>

                <div class="border-t border-border-theme pt-5">
                    <h3 class="text-sm font-semibold text-text-theme mb-3 uppercase tracking-wide">Contact & Location</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Country *</label>
                            <select v-model="form.country" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-surface-raised">
                                <option value="KE">Kenya</option>
                                <option value="UG">Uganda</option>
                                <option value="TZ">Tanzania</option>
                                <option value="RW">Rwanda</option>
                                <option value="NG">Nigeria</option>
                                <option value="GH">Ghana</option>
                                <option value="ZA">South Africa</option>
                                <option value="US">United States</option>
                                <option value="GB">United Kingdom</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Currency *</label>
                            <input v-model="form.currency" type="text" maxlength="3" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none uppercase" placeholder="USD" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Timezone *</label>
                            <select v-model="form.timezone" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-surface-raised">
                                <option value="Africa/Nairobi">Africa/Nairobi (EAT)</option>
                                <option value="Africa/Kampala">Africa/Kampala (EAT)</option>
                                <option value="Africa/Dar_es_Salaam">Africa/Dar es Salaam (EAT)</option>
                                <option value="Africa/Lagos">Africa/Lagos (WAT)</option>
                                <option value="Africa/Johannesburg">Africa/Johannesburg (SAST)</option>
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">America/New York (EST)</option>
                                <option value="Europe/London">Europe/London (GMT)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Phone</label>
                            <input v-model="form.phone" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="+254 712 345 678" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Email</label>
                            <input v-model="form.email" type="email" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="info@mybusiness.com" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Website</label>
                            <input v-model="form.website" type="url" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="https://mybusiness.com" />
                        </div>
                    </div>
                </div>

                <div class="border-t border-border-theme pt-5">
                    <h3 class="text-sm font-semibold text-text-theme mb-3 uppercase tracking-wide">Address</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Address Line 1</label>
                            <input v-model="form.address_line1" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="123 Main Street" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Address Line 2</label>
                            <input v-model="form.address_line2" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="Suite 100" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">City</label>
                            <input v-model="form.city" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="Nairobi" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">State / Province</label>
                            <input v-model="form.state_province" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="Nairobi County" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Postal Code</label>
                            <input v-model="form.postal_code" type="text" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none" placeholder="00100" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">VAT Registered</label>
                            <label class="flex items-center gap-3 mt-2">
                                <input v-model="form.vat_registered" type="checkbox" class="rounded border-border-input text-primary focus:ring-primary-ring" />
                                <span class="text-sm text-text-secondary">Yes, I am VAT registered</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="border-t border-border-theme pt-5">
                    <h3 class="text-sm font-semibold text-text-theme mb-3 uppercase tracking-wide">About</h3>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Business Description</label>
                        <textarea v-model="form.description" rows="3" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none resize-none" placeholder="Tell us a bit about your business..."></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="router.visit('/dashboard')" class="flex-1 border border-border-input text-text-secondary rounded-lg py-2.5 font-medium hover:bg-surface-alt transition-colors">
                        Skip for Now
                    </button>
                    <button type="submit" :disabled="saving" class="flex-1 bg-btn-primary text-white rounded-lg py-2.5 font-medium hover:bg-btn-primary-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        {{ saving ? 'Saving...' : 'Complete Profile' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthLayout>
</template>
