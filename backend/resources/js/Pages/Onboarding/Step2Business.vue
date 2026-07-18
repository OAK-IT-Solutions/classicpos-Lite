<script setup lang="ts">
defineProps<{
    modelValue: any;
}>();

const emit = defineEmits(['update:modelValue', 'next', 'prev']);

const businessTypes = [
    { value: 'bar_restaurant', label: 'Bar & Restaurant' },
    { value: 'retail', label: 'Retail Store' },
    { value: 'service', label: 'Service Business' },
    { value: 'pharmacy', label: 'Pharmacy' },
];
</script>

<template>
    <form @submit.prevent="emit('next')" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">Legal Business Name</label>
            <input
                :value="modelValue.business_name"
                @input="emit('update:modelValue', { ...modelValue, business_name: ($event.target as HTMLInputElement).value })"
                type="text"
                required
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none"
                placeholder="My Bar & Grill"
            />
        </div>
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">Industry Type</label>
            <select
                :value="modelValue.business_type"
                @change="emit('update:modelValue', { ...modelValue, business_type: ($event.target as HTMLSelectElement).value })"
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-surface-raised"
            >
                <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Country/Region</label>
                <select
                    :value="modelValue.country"
                    @change="emit('update:modelValue', { ...modelValue, country: ($event.target as HTMLSelectElement).value })"
                    class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-surface-raised"
                >
                    <option value="US">United States</option>
                    <option value="KE">Kenya</option>
                    <option value="UG">Uganda</option>
                    <option value="TZ">Tanzania</option>
                    <option value="RW">Rwanda</option>
                    <option value="NG">Nigeria</option>
                    <option value="GH">Ghana</option>
                    <option value="ZA">South Africa</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Base Currency</label>
                <input
                    :value="modelValue.currency"
                    @input="emit('update:modelValue', { ...modelValue, currency: ($event.target as HTMLInputElement).value })"
                    type="text"
                    maxlength="3"
                    class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none uppercase"
                    placeholder="USD"
                />
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">Tax ID / EIN <span class="text-text-tertiary font-normal">(optional)</span></label>
            <input
                :value="modelValue.tax_id"
                @input="emit('update:modelValue', { ...modelValue, tax_id: ($event.target as HTMLInputElement).value })"
                type="text"
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none"
                placeholder="XX-XXXXXXX"
            />
        </div>
        <div class="flex gap-3">
            <button type="button" @click="emit('prev')" class="flex-1 border border-border-input text-text-secondary rounded-lg py-2.5 font-medium hover:bg-surface-alt transition-colors">
                Back
            </button>
            <button type="submit" class="flex-1 bg-btn-primary text-white rounded-lg py-2.5 font-medium hover:bg-btn-primary-hover transition-colors">
                Continue
            </button>
        </div>
    </form>
</template>
