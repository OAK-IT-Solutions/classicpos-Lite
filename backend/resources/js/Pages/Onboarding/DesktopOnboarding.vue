<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import api from '@/composables/axios';

const emit = defineEmits<{
    completed: [];
}>();

const currentStep = ref(1);
const submitting = ref(false);
const error = ref('');

const form = ref({
    name: '',
    email: '',
    password: '',
    business_name: '',
    business_type: 'bar_restaurant',
    currency: 'USD',
    country: 'US',
    timezone: 'America/New_York',
});

const businessTypes = [
    { value: 'bar_restaurant', label: 'Bar & Restaurant' },
    { value: 'retail', label: 'Retail Store' },
    { value: 'wholesale', label: 'Wholesale' },
    { value: 'salon', label: 'Salon & Barber' },
    { value: 'grocery', label: 'Grocery' },
    { value: 'other', label: 'Other' },
];

const countries = [
    { value: 'US', label: 'United States' },
    { value: 'KE', label: 'Kenya' },
    { value: 'UG', label: 'Uganda' },
    { value: 'TZ', label: 'Tanzania' },
    { value: 'RW', label: 'Rwanda' },
    { value: 'NG', label: 'Nigeria' },
    { value: 'GH', label: 'Ghana' },
    { value: 'ZA', label: 'South Africa' },
    { value: 'GB', label: 'United Kingdom' },
    { value: 'CA', label: 'Canada' },
    { value: 'AU', label: 'Australia' },
];

const currencies = [
    { value: 'USD', label: 'USD - US Dollar' },
    { value: 'KES', label: 'KES - Kenyan Shilling' },
    { value: 'UGX', label: 'UGX - Ugandan Shilling' },
    { value: 'TZS', label: 'TZS - Tanzanian Shilling' },
    { value: 'EUR', label: 'EUR - Euro' },
    { value: 'GBP', label: 'GBP - British Pound' },
    { value: 'NGN', label: 'NGN - Nigerian Naira' },
    { value: 'CAD', label: 'CAD - Canadian Dollar' },
    { value: 'AUD', label: 'AUD - Australian Dollar' },
    { value: 'ZAR', label: 'ZAR - South African Rand' },
];

const timezoneGroups = [
    {
        label: 'Africa',
        zones: [
            'Africa/Nairobi', 'Africa/Kampala', 'Africa/Dar_es_Salaam',
            'Africa/Kigali', 'Africa/Lagos', 'Africa/Accra', 'Africa/Johannesburg',
            'Africa/Cairo', 'Africa/Casablanca',
        ],
    },
    {
        label: 'America',
        zones: [
            'America/New_York', 'America/Chicago', 'America/Denver',
            'America/Los_Angeles', 'America/Anchorage', 'America/Halifax',
            'America/Toronto', 'America/Vancouver', 'America/Mexico_City',
            'America/Sao_Paulo', 'America/Argentina/Buenos_Aires',
        ],
    },
    {
        label: 'Europe',
        zones: [
            'Europe/London', 'Europe/Paris', 'Europe/Berlin',
            'Europe/Madrid', 'Europe/Rome', 'Europe/Amsterdam',
            'Europe/Stockholm', 'Europe/Moscow', 'Europe/Istanbul',
        ],
    },
    {
        label: 'Asia / Pacific',
        zones: [
            'Asia/Dubai', 'Asia/Kolkata', 'Asia/Bangkok',
            'Asia/Singapore', 'Asia/Shanghai', 'Asia/Tokyo',
            'Australia/Sydney', 'Pacific/Auckland', 'Pacific/Fiji',
        ],
    },
];

function nextStep() {
    if (currentStep.value < 2) {
        currentStep.value++;
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

function isValidStep1(): boolean {
    return form.value.name.length > 0 && form.value.email.length > 0 && form.value.password.length >= 8;
}

function isValidStep2(): boolean {
    return form.value.business_name.length > 0 && form.value.currency.length === 3 && form.value.country.length > 0;
}

async function submitSetup() {
    error.value = '';
    submitting.value = true;

    const payload = JSON.stringify({
        name: form.value.name,
        email: form.value.email,
        password: form.value.password,
        business_name: form.value.business_name,
        business_type: form.value.business_type,
        currency: form.value.currency,
        country: form.value.country,
        timezone: form.value.timezone,
    });

    try {
        let data: any;
        const isTauri = !!(window as any).__TAURI_INTERNALS__;

        if (isTauri) {
            const resp: { status: number; body: string; content_type: string } = await (window as any).__TAURI_INTERNALS__.invoke('api_request', {
                method: 'POST',
                path: '/api/v1/desktop/setup',
                body: payload,
            });
            if (resp.status >= 400) {
                try {
                    const parsed = JSON.parse(resp.body);
                    throw { response: { data: parsed } };
                } catch (parseErr: any) {
                    if (parseErr?.response) throw parseErr;
                    throw { response: { data: { error: { message: `Server error (${resp.status})` } } } };
                }
            }
            data = JSON.parse(resp.body);
        } else {
            const response = await api.post('/desktop/setup', JSON.parse(payload));
            data = response.data;
        }

        localStorage.setItem('auth_token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));

        if (isTauri) {
            emit('completed');
        } else {
            router.visit('/');
        }
    } catch (err: any) {
        const resp = err.response?.data;
        if (resp?.error?.message) {
            error.value = resp.error.message;
        } else if (resp?.errors) {
            error.value = Object.values(resp.errors).flat().join(', ');
        } else if (resp?.message) {
            error.value = resp.message;
        } else if (err.message) {
            error.value = `Setup failed: ${err.message}`;
        } else {
            error.value = 'Setup failed. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}

const stepLabels = ['Account', 'Business'];
</script>

<template>
    <AuthLayout wide>
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-text-theme tracking-tight">Set Up Your Business</h2>
            <p class="text-text-tertiary text-sm mt-1">Create your admin account and configure your business in 2 quick steps</p>
        </div>

        <div class="flex items-center gap-3 mb-8">
            <template v-for="(label, i) in stepLabels" :key="i">
                <div class="flex items-center gap-2.5">
                    <div
                        class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-300"
                        :class="i + 1 < currentStep ? 'bg-emerald-500 text-white' : i + 1 === currentStep ? 'bg-primary text-white shadow-lg shadow-primary/25' : 'bg-surface-alt text-text-tertiary border border-border-light'"
                    >
                        <svg v-if="i + 1 < currentStep" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else>{{ i + 1 }}</span>
                    </div>
                    <span class="text-sm font-medium hidden sm:block" :class="i + 1 === currentStep ? 'text-primary' : 'text-text-tertiary'">
                        {{ label }}
                    </span>
                </div>
                <div v-if="i < stepLabels.length - 1" class="h-px flex-1 mx-2 transition-colors duration-300" :class="i + 1 < currentStep ? 'bg-primary' : 'bg-border-light'"></div>
            </template>
        </div>

        <div v-if="error" class="mb-6 p-4 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-600 dark:text-red-400 flex items-start gap-3">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <span>{{ error }}</span>
        </div>

        <Transition name="fade" mode="out-in">
            <form v-if="currentStep === 1" @submit.prevent="nextStep" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-text-theme mb-1.5">Full Name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full bg-surface border border-border-input rounded-xl px-4 py-3 text-sm text-text-theme placeholder:text-text-tertiary/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        placeholder="e.g. John Doe"
                    />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-text-theme mb-1.5">Work Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full bg-surface border border-border-input rounded-xl px-4 py-3 text-sm text-text-theme placeholder:text-text-tertiary/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        placeholder="you@yourbusiness.com"
                    />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-text-theme mb-1.5">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        minlength="8"
                        class="w-full bg-surface border border-border-input rounded-xl px-4 py-3 text-sm text-text-theme placeholder:text-text-tertiary/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        placeholder="At least 8 characters"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="!isValidStep1()"
                    class="w-full bg-primary hover:bg-primary-hover text-white rounded-xl py-3 font-semibold text-sm shadow-lg shadow-primary/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none transition-all duration-200"
                >
                    Continue
                </button>
            </form>

            <form v-else-if="currentStep === 2" @submit.prevent="submitSetup" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-text-theme mb-1.5">Business Name</label>
                    <input
                        v-model="form.business_name"
                        type="text"
                        required
                        class="w-full bg-surface border border-border-input rounded-xl px-4 py-3 text-sm text-text-theme placeholder:text-text-tertiary/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        placeholder="e.g. My Bar & Grill"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-text-theme mb-1.5">Business Type</label>
                        <select
                            v-model="form.business_type"
                            class="w-full bg-surface border border-border-input rounded-xl px-4 py-3 text-sm text-text-theme focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        >
                            <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-text-theme mb-1.5">Currency</label>
                        <select
                            v-model="form.currency"
                            class="w-full bg-surface border border-border-input rounded-xl px-4 py-3 text-sm text-text-theme focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        >
                            <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-text-theme mb-1.5">Country</label>
                        <select
                            v-model="form.country"
                            class="w-full bg-surface border border-border-input rounded-xl px-4 py-3 text-sm text-text-theme focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        >
                            <option v-for="c in countries" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-text-theme mb-1.5">Timezone</label>
                        <select
                            v-model="form.timezone"
                            class="w-full bg-surface border border-border-input rounded-xl px-4 py-3 text-sm text-text-theme focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                        >
                            <optgroup v-for="group in timezoneGroups" :key="group.label" :label="group.label">
                                <option v-for="tz in group.zones" :key="tz" :value="tz">{{ tz.replace('_', ' ') }}</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3 pt-3">
                    <button
                        type="button"
                        @click="prevStep"
                        class="flex-1 border-2 border-border-input text-text-secondary rounded-xl py-3 font-semibold text-sm hover:bg-surface-alt transition-all"
                    >
                        Back
                    </button>
                    <button
                        type="submit"
                        :disabled="!isValidStep2() || submitting"
                        class="flex-1 bg-primary hover:bg-primary-hover text-white rounded-xl py-3 font-semibold text-sm shadow-lg shadow-primary/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none transition-all duration-200 flex items-center justify-center gap-2"
                    >
                        <svg v-if="submitting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ submitting ? 'Setting up...' : 'Complete Setup' }}
                    </button>
                </div>
            </form>
        </Transition>
    </AuthLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-enter-from {
    opacity: 0;
    transform: translateX(12px);
}
.fade-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}
</style>