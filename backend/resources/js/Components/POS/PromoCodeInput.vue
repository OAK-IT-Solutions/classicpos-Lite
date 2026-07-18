<script setup lang="ts">
import { ref } from 'vue';
import { usePos } from '@/composables/usePos';

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { validatePromo } = usePos();

const valid = ref(false);
const checking = ref(false);
const feedback = ref('');

let debounceTimer: ReturnType<typeof setTimeout>;

async function onInput() {
    clearTimeout(debounceTimer);
    const code = props.modelValue.trim();
    if (!code) {
        valid.value = false;
        feedback.value = '';
        return;
    }
    checking.value = true;
    feedback.value = '';
    debounceTimer = setTimeout(async () => {
        try {
            const promo = await validatePromo(code);
            if (promo) {
                valid.value = true;
                const desc = promo.type === 'percentage' ? `${promo.value}% off` : `$${promo.value} off`;
                feedback.value = desc;
            } else {
                valid.value = false;
                feedback.value = 'Invalid or expired code';
            }
        } catch {
            valid.value = false;
            feedback.value = 'Could not validate code';
        } finally {
            checking.value = false;
        }
    }, 500);
}
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-text-secondary mb-1">Promo Code</label>
        <div class="relative">
            <input
                :value="modelValue"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value); onInput()"
                type="text"
                placeholder="Enter code..."
                maxlength="50"
                class="w-full border rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring"
                :class="valid ? 'border-green-300 bg-success-light' : feedback && !checking ? 'border-red-300 bg-danger-light' : 'border-border-input'"
            />
            <svg
                v-if="checking"
                class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-tertiary animate-spin"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </div>
        <p
            v-if="feedback"
            class="mt-1 text-xs"
            :class="valid ? 'text-success-theme' : 'text-danger-theme'"
        >
            {{ feedback }}
        </p>
    </div>
</template>
