<script setup lang="ts">
defineProps<{
    modelValue: any;
}>();

const emit = defineEmits(['update:modelValue', 'next', 'prev']);

const volumeOptions = [
    { value: '<10k', label: 'Less than $10k/month' },
    { value: '10k-50k', label: '$10k - $50k/month' },
    { value: '>50k', label: 'More than $50k/month' },
];

const hardwareOptions = [
    { value: 'receipt_printer', label: 'Receipt Printer' },
    { value: 'barcode_scanner', label: 'Barcode Scanner' },
    { value: 'card_terminal', label: 'Card Terminal' },
    { value: 'tablet', label: 'Tablet Register' },
];

function toggleHardware(value: string) {
    const current = [...modelValue.hardware];
    const idx = current.indexOf(value);
    if (idx >= 0) {
        current.splice(idx, 1);
    } else {
        current.push(value);
    }
    emit('update:modelValue', { ...modelValue, hardware: current });
}
</script>

<template>
    <form @submit.prevent="emit('next')" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">First Location Name</label>
            <input
                :value="modelValue.location"
                @input="emit('update:modelValue', { ...modelValue, location: ($event.target as HTMLInputElement).value })"
                type="text"
                required
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none"
                placeholder="Main Street Location"
            />
        </div>
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">Estimated Monthly Volume</label>
            <select
                :value="modelValue.estimated_volume"
                @change="emit('update:modelValue', { ...modelValue, estimated_volume: ($event.target as HTMLSelectElement).value })"
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-surface-raised"
            >
                <option v-for="vo in volumeOptions" :key="vo.value" :value="vo.value">{{ vo.label }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-3">Hardware Setup</label>
            <div class="grid grid-cols-2 gap-3">
                <label
                    v-for="ho in hardwareOptions"
                    :key="ho.value"
                    class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors"
                    :class="modelValue.hardware.includes(ho.value) ? 'border-blue-500 bg-primary-light' : 'border-border-theme hover:border-border-input'"
                >
                    <input
                        type="checkbox"
                        :checked="modelValue.hardware.includes(ho.value)"
                        @change="toggleHardware(ho.value)"
                        class="rounded border-border-input text-primary focus:ring-primary-ring"
                    />
                    <span class="text-sm text-text-secondary">{{ ho.label }}</span>
                </label>
            </div>
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
