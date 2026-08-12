<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { useAuth } from '@/composables/useAuth';
import Step1Account from './Step1Account.vue';
import Step2Business from './Step2Business.vue';
import Step3Operations from './Step3Operations.vue';
import Step4Plan from './Step4Plan.vue';

const auth = useAuth();
const currentStep = ref(1);
const submitting = ref(false);
const error = ref('');

const params = new URLSearchParams(window.location.search);
const referralCode = params.get('ref') || '';

const form = ref({
    name: '',
    email: '',
    password: '',
    business_name: '',
    business_type: 'bar_restaurant',
    country: 'US',
    currency: 'USD',
    tax_id: '',
    location: '',
    timezone: 'Africa/Nairobi',
    estimated_volume: '<10k',
    hardware: [] as string[],
    plan: 'standard',
    billing_cycle: 'annual',
});

const totalSteps = 4;

function nextStep() {
    if (currentStep.value < totalSteps) {
        currentStep.value++;
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

async function submitRegistration() {
    error.value = '';
    submitting.value = true;

    try {
        await auth.register({
            name: form.value.name,
            email: form.value.email,
            password: form.value.password,
            business_name: form.value.business_name,
            business_type: form.value.business_type,
            location: form.value.location,
            timezone: form.value.timezone,
            currency: form.value.currency,
            country: form.value.country,
            plan: form.value.plan,
            billing_cycle: form.value.billing_cycle,
            referral_code: referralCode || undefined,
        });
        router.visit('/dashboard');
    } catch (err: any) {
        const data = err.response?.data;
        if (data?.error?.message) {
            error.value = data.error.message;
        } else if (data?.errors) {
            error.value = Object.values(data.errors).flat().join(', ');
        } else {
            error.value = 'Registration failed. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}

const stepLabels = ['Account', 'Business', 'Operations', 'Plan'];
</script>

<template>
    <AuthLayout wide>
        <h2 class="text-2xl font-semibold mb-2 text-text-theme">Get Started</h2>
        <p class="text-text-tertiary text-sm mb-6">Set up your business in 4 quick steps</p>

        <div class="flex items-center gap-2 mb-8">
            <template v-for="(label, i) in stepLabels" :key="i">
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors"
                        :class="i + 1 <= currentStep ? 'bg-btn-primary text-white' : 'bg-border-light text-text-tertiary'"
                    >
                        {{ i + 1 }}
                    </div>
                    <span class="text-xs font-medium hidden sm:block" :class="i + 1 === currentStep ? 'text-primary' : 'text-text-tertiary'">
                        {{ label }}
                    </span>
                </div>
                <div v-if="i < stepLabels.length - 1" class="h-px flex-1 bg-border-light mx-1"></div>
            </template>
        </div>

        <div v-if="error" class="mb-4 p-3 bg-danger-light border border-red-200 rounded-lg text-sm text-danger-theme">
            {{ error }}
        </div>

        <Transition name="fade" mode="out-in">
            <Step1Account
                v-if="currentStep === 1"
                v-model="form"
                @next="nextStep"
            />
            <Step2Business
                v-else-if="currentStep === 2"
                v-model="form"
                @next="nextStep"
                @prev="prevStep"
            />
            <Step3Operations
                v-else-if="currentStep === 3"
                v-model="form"
                @next="nextStep"
                @prev="prevStep"
            />
            <Step4Plan
                v-else-if="currentStep === 4"
                v-model="form"
                @submit="submitRegistration"
                @prev="prevStep"
                :submitting="submitting"
            />
        </Transition>
    </AuthLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from {
    opacity: 0;
    transform: translateX(20px);
}
.fade-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}
</style>
