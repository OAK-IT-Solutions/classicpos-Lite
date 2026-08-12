<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps<{
    createOrder: () => Promise<string>;
    onApprove: (data: any) => Promise<void>;
    onCancel: () => void;
    disabled?: boolean;
}>();

const containerId = 'paypal-button-' + Math.random().toString(36).slice(2, 9);
const rendering = ref(false);

onMounted(() => {
    renderButton();
});

watch(() => props.disabled, () => {
    const container = document.getElementById(containerId);
    if (container) container.innerHTML = '';
    renderButton();
});

function renderButton() {
    if (props.disabled) return;
    if (typeof window.paypal === 'undefined') return;

    rendering.value = true;
    try {
        window.paypal.Buttons({
            createOrder: async () => {
                return await props.createOrder();
            },
            onApprove: async (data: any) => {
                await props.onApprove(data);
            },
            onCancel: () => {
                props.onCancel();
            },
            onError: (err: any) => {
                console.error('PayPal error', err);
            },
        }).render(`#${containerId}`);
    } finally {
        rendering.value = false;
    }
}
</script>

<template>
    <div v-if="rendering" class="text-sm text-text-tertiary py-2">Rendering PayPal button...</div>
    <div :id="containerId" class="min-h-[40px]"></div>
</template>
