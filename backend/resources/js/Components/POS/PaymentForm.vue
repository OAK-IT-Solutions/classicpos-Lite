<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    modelValue: string;
    amountTendered: number;
    total: number;
    gateway?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:amountTendered': [value: number];
    'update:gateway': [value: string];
}>();

const changeDue = computed(() => Math.max(0, props.amountTendered - props.total));

const methods = [
    { value: 'cash', label: 'Cash' },
    { value: 'mobile_money', label: 'Mobile Money' },
    { value: 'card', label: 'Card' },
    { value: 'qr', label: 'QR' },
];

const gateways: Record<string, { value: string; label: string }[]> = {
    mobile_money: [
        { value: 'MTN MoMo', label: 'MTN MoMo' },
        { value: 'Airtel Money', label: 'Airtel Money' },
        { value: 'M-Pesa', label: 'M-Pesa' },
    ],
    card: [
        { value: 'card', label: 'Card Terminal' },
    ],
    qr: [
        { value: 'qr', label: 'QR Code' },
    ],
};

const availableGateways = computed(() => gateways[props.modelValue] || []);
const showGateway = computed(() => availableGateways.value.length > 0);

function formatCurrency(amount: number): string {
    return amount.toFixed(2);
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">Payment Method</label>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="m in methods"
                    :key="m.value"
                    @click="emit('update:modelValue', m.value)"
                    class="px-3 py-2.5 rounded-lg border text-sm font-medium transition-colors"
                    :class="modelValue === m.value ? 'border-blue-500 bg-primary-light text-primary' : 'border-border-theme text-text-secondary hover:bg-surface-alt'"
                    type="button"
                >
                    {{ m.label }}
                </button>
            </div>
        </div>

        <div v-if="showGateway">
            <label class="block text-sm font-medium text-text-secondary mb-1">Gateway</label>
            <select
                :value="gateway"
                @change="emit('update:gateway', ($event.target as HTMLSelectElement).value)"
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-surface-raised"
            >
                <option value="">Select gateway...</option>
                <option v-for="g in availableGateways" :key="g.value" :value="g.value">{{ g.label }}</option>
            </select>
        </div>

        <div v-if="modelValue === 'cash'">
            <label class="block text-sm font-medium text-text-secondary mb-1">Amount Tendered</label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-tertiary text-sm">$</span>
                <input
                    :value="amountTendered"
                    @input="emit('update:amountTendered', parseFloat(($event.target as HTMLInputElement).value) || 0)"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full pl-8 pr-3 py-2.5 border border-border-input rounded-lg text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none"
                    placeholder="0.00"
                />
            </div>
            <div class="flex gap-1.5 mt-2 flex-wrap">
                <button
                    v-for="amt in [5, 10, 20, 50, 100, 200, 500]"
                    :key="amt"
                    @click="emit('update:amountTendered', amt)"
                    class="px-2.5 py-1 text-xs font-medium rounded border transition-colors"
                    :class="amountTendered === amt ? 'border-blue-500 bg-primary-light text-primary' : 'border-border-theme text-text-secondary hover:bg-surface-alt'"
                    type="button"
                >
                    {{ formatCurrency(amt) }}
                </button>
                <button
                    @click="emit('update:amountTendered', total)"
                    class="px-2.5 py-1 text-xs font-medium rounded border border-green-200 text-success-theme hover:bg-success-light transition-colors"
                    type="button"
                >
                    Exact
                </button>
            </div>
        </div>

        <div class="bg-surface-alt rounded-lg p-3 space-y-1">
            <div class="flex justify-between text-sm">
                <span class="text-text-tertiary">Total</span>
                <span class="font-bold text-text-theme">{{ formatCurrency(total) }}</span>
            </div>
            <div v-if="amountTendered > 0" class="flex justify-between text-sm">
                <span class="text-text-tertiary">Change</span>
                <span class="font-bold text-success-theme">{{ formatCurrency(changeDue) }}</span>
            </div>
        </div>
    </div>
</template>
