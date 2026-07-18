<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next';

defineProps<{
    title: string;
    visible: boolean;
    loading?: boolean;
    error?: string | null;
}>();

const emit = defineEmits<{
    close: [];
    submit: [];
}>();
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
    >
        <div v-if="visible" class="fixed inset-0 z-40 flex justify-end">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-surface-overlay" @click="emit('close')" />

            <!-- Panel -->
            <Transition
                enter-active-class="transition-transform duration-200"
                leave-active-class="transition-transform duration-200"
                enter-from-class="translate-x-full"
                leave-to-class="translate-x-full"
            >
                <div v-if="visible" class="relative z-50 w-full max-w-md bg-surface-raised shadow-xl flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-5 border-b border-border-theme">
                        <h2 class="text-lg font-semibold text-text-theme">{{ title }}</h2>
                        <button
                            @click="emit('close')"
                            class="p-1.5 text-text-tertiary hover:text-text-secondary hover:bg-surface-alt rounded-md transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="emit('submit')" class="flex-1 overflow-y-auto px-6 py-6 space-y-5">
                        <slot />

                        <!-- Error -->
                        <div v-if="error" class="flex items-start gap-2 p-3 bg-danger-light border border-danger-theme/20 rounded-lg">
                            <AlertCircle class="w-4 h-4 text-danger-theme flex-shrink-0 mt-0.5" />
                            <p class="text-xs text-danger-theme">{{ error }}</p>
                        </div>
                    </form>

                    <!-- Footer -->
                    <div class="flex items-center gap-3 px-6 py-5 border-t border-border-theme bg-surface-alt">
                        <button
                            type="submit"
                            @click="emit('submit')"
                            :disabled="loading"
                            class="flex-1 px-4 py-2 text-sm font-medium bg-btn-primary text-btn-primary-text rounded-lg hover:bg-btn-primary-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <span v-if="loading">Saving\u2026</span>
                            <span v-else>Save</span>
                        </button>
                        <button
                            type="button"
                            @click="emit('close')"
                            class="flex-1 px-4 py-2 text-sm font-medium text-text-secondary bg-btn-secondary border border-btn-secondary-border rounded-lg hover:bg-btn-secondary-hover transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
