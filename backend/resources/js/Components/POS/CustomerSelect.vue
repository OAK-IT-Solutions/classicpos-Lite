<script setup lang="ts">
import { ref } from 'vue';
import { usePos } from '@/composables/usePos';
import api from '@/composables/axios';
import type { CustomerLookup } from '@/composables/usePos';

const props = defineProps<{
    modelValue: CustomerLookup | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [customer: CustomerLookup | null];
}>();

const { searchCustomers } = usePos();

const query = ref('');
const results = ref<CustomerLookup[]>([]);
const searching = ref(false);
const showDropdown = ref(false);

const showQuickAdd = ref(false);
const quickAddName = ref('');
const quickAddPhone = ref('');
const quickAddEmail = ref('');
const quickAddLocation = ref('');
const quickAddLoading = ref(false);
const quickAddError = ref('');

let debounceTimer: ReturnType<typeof setTimeout>;

async function onInput() {
    clearTimeout(debounceTimer);
    if (query.value.length < 2) {
        results.value = [];
        showDropdown.value = false;
        return;
    }
    searching.value = true;
    debounceTimer = setTimeout(async () => {
        try {
            results.value = await searchCustomers(query.value);
            showDropdown.value = results.value.length > 0;
        } catch {
            results.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
}

async function onFocus() {
    if (results.value.length > 0) {
        showDropdown.value = true;
        return;
    }
    results.value = await searchCustomers('');
    showDropdown.value = results.value.length > 0;
}

function select(customer: CustomerLookup) {
    emit('update:modelValue', customer);
    query.value = customer.name;
    showDropdown.value = false;
}

function clear() {
    emit('update:modelValue', null);
    query.value = '';
    results.value = [];
    showDropdown.value = false;
}

function openQuickAdd() {
    quickAddName.value = query.value;
    showQuickAdd.value = true;
    quickAddError.value = '';
}

async function submitQuickAdd() {
    if (!quickAddName.value.trim() || !quickAddPhone.value.trim()) {
        quickAddError.value = 'Name and phone are required.';
        return;
    }
    quickAddLoading.value = true;
    quickAddError.value = '';
    try {
        const res = await api.post('/customers', {
            name: quickAddName.value.trim(),
            phone: quickAddPhone.value.trim(),
            email: quickAddEmail.value.trim() || undefined,
            location: quickAddLocation.value.trim() || undefined,
        });
        const newCustomer = res.data.data as CustomerLookup;
        select(newCustomer);
        showQuickAdd.value = false;
        quickAddName.value = '';
        quickAddPhone.value = '';
        quickAddEmail.value = '';
        quickAddLocation.value = '';
    } catch (err: any) {
        quickAddError.value = err.response?.data?.error?.message
            || err.response?.data?.message
            || 'Failed to create customer.';
    } finally {
        quickAddLoading.value = false;
    }
}

function cancelQuickAdd() {
    showQuickAdd.value = false;
    quickAddError.value = '';
}
</script>

<template>
    <div class="relative">
        <label class="block text-sm font-medium text-text-secondary mb-1">Customer (optional)</label>
        <div class="relative">
            <input
                v-model="query"
                type="text"
                placeholder="Search by name or phone..."
                @input="onInput"
                @focus="onFocus"
                @blur="setTimeout(() => showDropdown = false, 200)"
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"
            />
            <button
                v-if="modelValue"
                @click="clear"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-text-tertiary hover:text-text-secondary"
                type="button"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div
            v-if="showDropdown && !showQuickAdd"
            class="absolute z-10 mt-1 w-full bg-surface-raised border border-border-theme rounded-lg shadow-lg max-h-48 overflow-y-auto"
        >
            <div v-if="searching" class="px-3 py-2 text-sm text-text-tertiary">Searching...</div>
            <button
                v-for="c in results"
                :key="c.id"
                @click="select(c)"
                class="w-full text-left px-3 py-2 hover:bg-surface-alt text-sm"
            >
                <span class="font-medium text-text-theme">{{ c.name }}</span>
                <span class="text-text-tertiary ml-2">{{ c.phone }}</span>
                <span v-if="c.loyalty_points > 0" class="text-xs text-warning-theme ml-2">{{ c.loyalty_points }} pts</span>
            </button>
            <button
                @click="openQuickAdd"
                class="w-full text-left px-3 py-2 border-t border-border-light text-sm text-primary hover:bg-primary-light font-medium"
            >
                + Quick Add "{{ query }}"
            </button>
        </div>
        <div
            v-if="showQuickAdd"
            class="mt-2 border border-border-theme rounded-lg p-3 bg-surface-alt space-y-2"
        >
            <h4 class="text-sm font-semibold text-text-theme">Quick Add Customer</h4>
            <div v-if="quickAddError" class="text-xs text-danger-theme">{{ quickAddError }}</div>
            <input
                v-model="quickAddName"
                type="text"
                placeholder="Name *"
                class="w-full border border-border-input rounded px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"
            />
            <input
                v-model="quickAddPhone"
                type="text"
                placeholder="Phone *"
                class="w-full border border-border-input rounded px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"
            />
            <input
                v-model="quickAddEmail"
                type="email"
                placeholder="Email (optional)"
                class="w-full border border-border-input rounded px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"
            />
            <input
                v-model="quickAddLocation"
                type="text"
                placeholder="Location (optional)"
                class="w-full border border-border-input rounded px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"
            />
            <div class="flex gap-2 pt-1">
                <button
                    @click="cancelQuickAdd"
                    class="flex-1 px-3 py-1.5 border border-border-theme rounded text-sm text-text-secondary hover:bg-surface-alt"
                    type="button"
                >
                    Cancel
                </button>
                <button
                    @click="submitQuickAdd"
                    :disabled="quickAddLoading"
                    class="flex-1 px-3 py-1.5 bg-btn-primary text-white rounded text-sm font-medium hover:bg-btn-primary-hover disabled:opacity-50"
                    type="button"
                >
                    {{ quickAddLoading ? 'Creating...' : 'Create & Select' }}
                </button>
            </div>
        </div>
        <div v-if="modelValue" class="mt-1.5 flex items-center gap-2 text-xs text-text-tertiary">
            <span>{{ modelValue.phone }}</span>
            <span v-if="modelValue.loyalty_points > 0" class="text-warning-theme font-medium">{{ modelValue.loyalty_points }} pts</span>
        </div>
    </div>
</template>
