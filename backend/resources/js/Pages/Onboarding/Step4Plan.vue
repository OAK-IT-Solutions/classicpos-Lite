<script setup lang="ts">
defineProps<{
    modelValue: any;
    submitting: boolean;
}>();

const emit = defineEmits(['update:modelValue', 'submit', 'prev']);

const plans = [
    {
        id: 'standard',
        name: 'Standard',
        monthly: 39,
        annual: 29,
        description: 'For growing businesses',
        features: ['1 Location', '2 Registers', '5 Users', '10K Products', 'Email Support'],
    },
    {
        id: 'premium',
        name: 'Premium',
        monthly: 99,
        annual: 79,
        description: 'For multi-location enterprises',
        features: ['Unlimited Locations', 'Unlimited Registers', 'Unlimited Users', 'Unlimited Products', 'Priority 24/7 Support'],
        popular: true,
    },
];
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-4">
        <div class="flex bg-surface-alt rounded-lg p-1 mb-4">
            <button
                type="button"
                @click="emit('update:modelValue', { ...modelValue, billing_cycle: 'annual' })"
                class="flex-1 py-2 text-sm font-medium rounded-md transition-colors"
                :class="modelValue.billing_cycle === 'annual' ? 'bg-surface-raised text-text-theme shadow-sm' : 'text-text-tertiary'"
            >
                Annual <span class="text-success-theme text-xs">-20%</span>
            </button>
            <button
                type="button"
                @click="emit('update:modelValue', { ...modelValue, billing_cycle: 'monthly' })"
                class="flex-1 py-2 text-sm font-medium rounded-md transition-colors"
                :class="modelValue.billing_cycle === 'monthly' ? 'bg-surface-raised text-text-theme shadow-sm' : 'text-text-tertiary'"
            >
                Monthly
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <button
                type="button"
                v-for="plan in plans"
                :key="plan.id"
                @click="emit('update:modelValue', { ...modelValue, plan: plan.id })"
                class="relative p-4 border-2 rounded-xl text-left transition-all"
                :class="modelValue.plan === plan.id ? 'border-blue-500 bg-primary-light' : 'border-border-theme hover:border-border-input'"
            >
                <div v-if="plan.popular" class="absolute -top-2.5 left-3 px-2 py-0.5 bg-btn-primary text-white text-xs font-medium rounded-full">
                    Popular
                </div>
                <h3 class="text-lg font-bold text-text-theme">{{ plan.name }}</h3>
                <p class="text-sm text-text-tertiary mt-1">{{ plan.description }}</p>
                <p class="mt-2">
                    <span class="text-2xl font-bold text-text-theme">${{ modelValue.billing_cycle === 'annual' ? plan.annual : plan.monthly }}</span>
                    <span class="text-sm text-text-tertiary">/month</span>
                </p>
                <p v-if="modelValue.billing_cycle === 'annual'" class="text-xs text-success-theme mt-1">
                    ${{ plan.annual * 12 }}/year billed annually
                </p>
                <ul class="mt-3 space-y-1">
                    <li v-for="f in plan.features" :key="f" class="text-xs text-text-secondary flex items-center gap-1">
                        <svg class="w-3 h-3 text-success-theme flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        {{ f }}
                    </li>
                </ul>
            </button>
        </div>

        <p class="text-xs text-text-tertiary text-center">No credit card required for your 30-day free trial. Start with full Standard features.</p>

        <div class="flex gap-3">
            <button type="button" @click="emit('prev')" class="flex-1 border border-border-input text-text-secondary rounded-lg py-2.5 font-medium hover:bg-surface-alt transition-colors">
                Back
            </button>
            <button
                type="submit"
                :disabled="submitting"
                class="flex-1 bg-btn-primary text-white rounded-lg py-2.5 font-medium hover:bg-btn-primary-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                {{ submitting ? 'Creating...' : 'Launch My POS System' }}
            </button>
        </div>
    </form>
</template>
