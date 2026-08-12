<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const links = [
    { href: '/settings/business', label: 'Business' },
    { href: '/settings/users', label: 'Users' },
    { href: '/settings/roles', label: 'Roles & Permissions' },
    { href: '/settings/branches', label: 'Branches' },
    { href: '/settings/warehouses', label: 'Warehouses' },
    { href: '/settings/tax-profiles', label: 'Taxes' },
    { href: '/settings/promotions', label: 'Promotions' },
    { href: '/settings/loyalty', label: 'Loyalty' },
    { href: '/settings/devices', label: 'Devices' },
    { href: '/settings/subscription', label: 'Subscription' },
];
</script>

<template>
    <div class="border-b border-border-theme mb-6">
        <nav class="flex gap-4 overflow-x-auto">
            <a
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="whitespace-nowrap px-3 py-3 text-sm font-medium border-b-2 transition-colors"
                :class="page.url.startsWith(link.href)
                    ? 'border-btn-primary text-primary'
                    : 'border-transparent text-text-tertiary hover:text-text-secondary hover:border-border-input'"
            >
                {{ link.label }}
            </a>
        </nav>
    </div>
</template>
