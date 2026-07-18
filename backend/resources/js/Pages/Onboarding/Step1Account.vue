<script setup lang="ts">
defineProps<{
    modelValue: any;
}>();

const emit = defineEmits(['update:modelValue', 'next']);
</script>

<template>
    <form @submit.prevent="emit('next')" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">Full Name</label>
            <input
                :value="modelValue.name"
                @input="emit('update:modelValue', { ...modelValue, name: ($event.target as HTMLInputElement).value })"
                type="text"
                required
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none"
                placeholder="John Doe"
            />
        </div>
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">Work Email</label>
            <input
                :value="modelValue.email"
                @input="emit('update:modelValue', { ...modelValue, email: ($event.target as HTMLInputElement).value })"
                type="email"
                required
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none"
                placeholder="you@example.com"
            />
        </div>
        <div>
            <label class="block text-sm font-medium text-text-secondary mb-1">Password</label>
            <input
                :value="modelValue.password"
                @input="emit('update:modelValue', { ...modelValue, password: ($event.target as HTMLInputElement).value })"
                type="password"
                required
                minlength="8"
                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none"
                placeholder="Minimum 8 characters"
            />
            <p class="text-xs text-text-tertiary mt-1">At least 8 characters</p>
        </div>
        <button type="submit" class="w-full bg-btn-primary text-white rounded-lg py-2.5 font-medium hover:bg-btn-primary-hover transition-colors">
            Continue
        </button>
    </form>
</template>
