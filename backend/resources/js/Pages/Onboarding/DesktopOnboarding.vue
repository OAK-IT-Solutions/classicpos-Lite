<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import api from '@/composables/axios';

const emit = defineEmits<{
    completed: [];
    goToLogin: [];
}>();

const currentStep = ref(1);
const submitting = ref(false);
const error = ref('');
const setupAlreadyDone = ref(false);

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
        localStorage.setItem('classicpos_setup_complete', 'true');

        emit('completed');
    } catch (err: any) {
        const resp = err.response?.data;
        if (resp?.error?.code === 'ERR_SETUP_COMPLETE') {
            setupAlreadyDone.value = true;
            error.value = resp.error.message || 'Setup has already been completed.';
        } else if (resp?.error?.message) {
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
        <div class="ob-header">
            <h2>Set Up Your Business</h2>
            <p>Create your admin account and configure your business in 2 quick steps</p>
        </div>

        <div class="step-indicator">
            <template v-for="(label, i) in stepLabels" :key="i">
                <div class="step-item">
                    <div
                        class="step-circle"
                        :class="i + 1 < currentStep ? 'step-done' : i + 1 === currentStep ? 'step-active' : 'step-pending'"
                    >
                        <svg v-if="i + 1 < currentStep" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else>{{ i + 1 }}</span>
                    </div>
                    <span class="step-label" :class="i + 1 === currentStep ? 'step-label-active' : ''">{{ label }}</span>
                </div>
                <div v-if="i < stepLabels.length - 1" class="step-line" :class="i + 1 < currentStep ? 'step-line-done' : ''"></div>
            </template>
        </div>

        <div v-if="error" class="error-box">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <div>
                <span>{{ error }}</span>
                <button v-if="setupAlreadyDone" @click="emit('goToLogin')" class="go-login-btn">
                    Go to Login
                </button>
            </div>
        </div>

        <Transition name="fade" mode="out-in">
            <form v-if="currentStep === 1" @submit.prevent="nextStep" class="form-fields">
                <div class="field">
                    <label>Full Name</label>
                    <input v-model="form.name" type="text" required placeholder="e.g. John Doe" />
                </div>
                <div class="field">
                    <label>Work Email</label>
                    <input v-model="form.email" type="email" required placeholder="you@yourbusiness.com" />
                </div>
                <div class="field">
                    <label>Password</label>
                    <input v-model="form.password" type="password" required minlength="8" placeholder="At least 8 characters" />
                </div>
                <button type="submit" :disabled="!isValidStep1()" class="btn-primary">
                    Continue
                </button>
            </form>

            <form v-else-if="currentStep === 2" @submit.prevent="submitSetup" class="form-fields">
                <div class="field">
                    <label>Business Name</label>
                    <input v-model="form.business_name" type="text" required placeholder="e.g. My Bar & Grill" />
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Business Type</label>
                        <select v-model="form.business_type">
                            <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Currency</label>
                        <select v-model="form.currency">
                            <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Country</label>
                        <select v-model="form.country">
                            <option v-for="c in countries" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Timezone</label>
                        <select v-model="form.timezone">
                            <optgroup v-for="group in timezoneGroups" :key="group.label" :label="group.label">
                                <option v-for="tz in group.zones" :key="tz" :value="tz">{{ tz.replace('_', ' ') }}</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="button" @click="prevStep" class="btn-secondary">Back</button>
                    <button type="submit" :disabled="!isValidStep2() || submitting" class="btn-primary">
                        <svg v-if="submitting" class="spinner" fill="none" viewBox="0 0 24 24">
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
.ob-header {
    margin-bottom: 2rem;
}
.ob-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
    letter-spacing: -0.025em;
}
.ob-header p {
    margin: 0.375rem 0 0;
    color: #94a3b8;
    font-size: 0.875rem;
}

.step-indicator {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 2rem;
}
.step-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}
.step-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s;
}
.step-done {
    background: #22c55e;
    color: white;
}
.step-active {
    background: #3b82f6;
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
.step-pending {
    background: #1e293b;
    color: #64748b;
    border: 1px solid #334155;
}
.step-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #64748b;
}
.step-label-active {
    color: #3b82f6;
}
.step-line {
    flex: 1;
    height: 2px;
    background: #334155;
    transition: background 0.3s;
}
.step-line-done {
    background: #22c55e;
}

.error-box {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 12px;
    color: #fca5a5;
    font-size: 0.875rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.go-login-btn {
    margin-top: 0.75rem;
    display: block;
    width: 100%;
    padding: 0.625rem 1rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.go-login-btn:hover {
    background: #2563eb;
}

.form-fields {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.field {
    display: flex;
    flex-direction: column;
}
.field label {
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    color: #cbd5e1;
}
.field input,
.field select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 10px;
    color: #e2e8f0;
    font-size: 0.95rem;
    outline: none;
    transition: border-color 0.2s;
}
.field input::placeholder {
    color: #475569;
}
.field input:focus,
.field select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.field select {
    cursor: pointer;
    appearance: auto;
}
.field select option,
.field select optgroup {
    background: #0f172a;
    color: #e2e8f0;
}

.field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn-primary {
    width: 100%;
    padding: 0.875rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
.btn-primary:hover:not(:disabled) {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}
.btn-primary:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    box-shadow: none;
}

.btn-secondary {
    flex: 1;
    padding: 0.875rem;
    background: transparent;
    color: #94a3b8;
    border: 2px solid #334155;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-secondary:hover {
    background: #1e293b;
    border-color: #475569;
    color: #e2e8f0;
}

.btn-row {
    display: flex;
    gap: 0.75rem;
    padding-top: 0.75rem;
}
.btn-row .btn-primary {
    flex: 1;
}

.spinner {
    width: 1rem;
    height: 1rem;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.opacity-25 { opacity: 0.25; }
.opacity-75 { opacity: 0.75; }

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