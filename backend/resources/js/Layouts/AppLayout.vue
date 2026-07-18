<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import api from '@/composables/axios';
import SettingsPanel from '@/Components/SettingsPanel.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import GlobalSyncIndicator from '@/Components/GlobalSyncIndicator.vue';
import {
    LayoutDashboard, ShoppingCart, Receipt, Package, Truck,
    Landmark, ClipboardList, Settings, Headphones,
    ChevronDown, Plug,
} from 'lucide-vue-next';

const auth = useAuth();
const page = usePage();

const menuOpen = ref(false);
const sidebarOpen = ref(false);
const settingsOpen = ref(false);
const activeSettingsTab = ref('business');
const businessLogo = ref<string | null>(null);
const expandedSections = ref<Record<string, boolean>>({});

onMounted(async () => {
    if (!auth.user.value) {
        router.visit('/login');
        return;
    }
    try {
        const r = await api.get('/onboarding/status');
        businessLogo.value = r.data?.profile?.logo_url ?? null;
    } catch { /* ignore */ }
});

function openSettings(tab: string) {
    activeSettingsTab.value = tab;
    settingsOpen.value = true;
    sidebarOpen.value = false;
}

const topNavLinks = [
    { href: '/', label: 'Dashboard' },
    { href: '/pos', label: 'POS' },
    { href: '/sales', label: 'Sales' },
    { href: '/customers', label: 'Customers' },
    { href: '/products', label: 'Products' },
    { href: '/inventory', label: 'Inventory' },
];

const sidebarCategories = [
    {
        id: 'sales',
        label: 'Sales & Customers',
        icon: Receipt,
        links: [
            { href: '/sales', label: 'Sales' },
            { href: '/customers', label: 'Customers' },
            { href: '/invoices', label: 'Invoicing' },
            { href: '/receipts', label: 'Receipts' },
            { href: '/returns', label: 'Returns' },
            { href: '/payments', label: 'Payments' },
        ],
    },
    {
        id: 'products',
        label: 'Products & Inventory',
        icon: Package,
        links: [
            { href: '/products', label: 'Products' },
            { href: '/inventory', label: 'Inventory' },
            { href: '/stock-transfers', label: 'Stock Transfers' },
        ],
    },
    {
        id: 'supply',
        label: 'Supply Chain',
        icon: Truck,
        links: [
            { href: '/suppliers', label: 'Suppliers' },
            { href: '/purchase-orders', label: 'Purchase Orders' },
            { href: '/grn', label: 'Goods Receipt' },
        ],
    },
    {
        id: 'finance',
        label: 'Finance',
        icon: Landmark,
        links: [
            { href: '/chart-of-accounts', label: 'Chart of Accounts' },
            { href: '/journal-entries', label: 'Journal Entries' },
            { href: '/operating-accounts', label: 'Operating Accounts' },
            { href: '/bank-reconciliation', label: 'Reconciliation' },
        ],
    },
    {
        id: 'operations',
        label: 'Operations',
        icon: ClipboardList,
        links: [
            { href: '/cash-register', label: 'Cash Register' },
            { href: '/reports', label: 'Reports' },
            { href: '/sync-status', label: 'Sync Status' },
            { href: '/settings/audit-log', label: 'Audit Log' },
        ],
    },
    {
        id: 'settings',
        label: 'Settings',
        icon: Settings,
        tabs: [
            { tab: 'profile', label: 'My Profile' },
            { tab: 'business', label: 'Business' },
            { tab: 'users', label: 'Users' },
            { tab: 'roles', label: 'Roles & Permissions' },
            { tab: 'branches', label: 'Branches' },
            { tab: 'warehouses', label: 'Warehouses' },
            { tab: 'taxes', label: 'Taxes' },
            { tab: 'promotions', label: 'Promotions' },
            { tab: 'loyalty', label: 'Loyalty' },
            { tab: 'devices', label: 'Devices' },
            { tab: 'printer', label: 'Printer & Drawer' },
            { tab: 'locale', label: 'Locale & Theme' },
            { tab: 'subscription', label: 'Subscription' },
        ],
    },
    {
        id: 'integrations',
        label: 'Integrations',
        icon: Plug,
        links: [
            { href: '/integrations', label: 'All Integrations' },
        ],
    },
    {
        id: 'support',
        label: 'Support',
        icon: Headphones,
        links: [
            { href: '/tickets', label: 'Get Support' },
        ],
    },
];

function isActive(href: string): boolean {
    if (href === '/') return page.url === '/';
    return page.url.startsWith(href);
}

function toggleSection(id: string) {
    expandedSections.value[id] = !expandedSections.value[id];
}

function isSectionExpanded(id: string): boolean {
    return expandedSections.value[id] ?? false;
}

watch(() => page.url, (url) => {
    for (const cat of sidebarCategories) {
        if (cat.links?.some(l => {
            if (l.href === '/') return url === '/';
            return url.startsWith(l.href);
        })) {
            expandedSections.value[cat.id] = true;
        }
    }
}, { immediate: true });

watch(settingsOpen, (open) => {
    if (open) expandedSections.value['settings'] = true;
});

function logout() {
    auth.logout().then(() => {
        router.visit('/login');
    });
}
</script>

<template>
    <div class="min-h-screen bg-surface-alt">
        <!-- Mobile sidebar overlay -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-surface-overlay lg:hidden" @click="sidebarOpen = false"></div>

        <!-- Sidebar — always fixed, main area uses lg:pl-64 -->
        <aside class="fixed inset-y-0 left-0 z-50 w-64 bg-sidebar-bg border-r border-sidebar-border transform transition-transform duration-200 ease-in-out -translate-x-full lg:translate-x-0 flex flex-col" :class="sidebarOpen ? '!translate-x-0' : ''">
            <div class="flex items-center gap-3 h-16 px-5 border-b border-sidebar-border shrink-0">
                <img v-if="businessLogo" :src="businessLogo" alt="Logo" class="h-8 w-auto rounded" />
                <div v-else class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                </div>
                <span class="text-lg font-bold text-text-theme truncate">{{ auth.user?.branch?.name || 'ClassicPOS' }}</span>
                <button @click="sidebarOpen = false" class="ml-auto p-1 rounded-lg hover:bg-surface-alt lg:hidden">
                    <svg class="w-5 h-5 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                <a href="/"
                    class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors"
                    :class="isActive('/') ? 'bg-sidebar-bg-active text-sidebar-text-active' : 'text-sidebar-text hover:bg-surface-alt hover:text-text-theme'">
                    <LayoutDashboard class="w-5 h-5 shrink-0" />
                    Dashboard
                </a>
                <a href="/pos"
                    class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors"
                    :class="isActive('/pos') ? 'bg-sidebar-bg-active text-sidebar-text-active' : 'text-sidebar-text hover:bg-surface-alt hover:text-text-theme'">
                    <ShoppingCart class="w-5 h-5 shrink-0" />
                    POS
                </a>

                <div v-for="category in sidebarCategories" :key="category.id" class="pt-4">
                    <button @click="toggleSection(category.id)"
                        class="flex items-center gap-3 px-3 py-2 w-full text-left text-sm font-medium rounded-lg transition-colors text-text-tertiary hover:text-text-theme hover:bg-surface-alt">
                        <component :is="category.icon" class="w-5 h-5 shrink-0" />
                        <span class="flex-1">{{ category.label }}</span>
                        <ChevronDown class="w-4 h-4 transition-transform duration-200"
                            :class="isSectionExpanded(category.id) ? '' : '-rotate-90'" />
                    </button>
                    <div class="overflow-hidden transition-all duration-200 ease-in-out"
                        :style="{ maxHeight: isSectionExpanded(category.id) ? '800px' : '0' }">
                        <div class="space-y-0.5 mt-0.5">
                            <a v-for="link in category.links || []"
                                :key="link.href"
                                :href="link.href"
                                class="flex items-center gap-3 pl-11 pr-3 py-1.5 text-sm font-medium rounded-lg transition-colors"
                                :class="isActive(link.href) ? 'bg-sidebar-bg-active text-sidebar-text-active' : 'text-sidebar-text hover:bg-surface-alt hover:text-text-theme'">
                                {{ link.label }}
                            </a>
                            <button v-for="link in category.tabs || []"
                                :key="link.tab"
                                @click="openSettings(link.tab)"
                                class="flex items-center gap-3 pl-11 pr-3 py-1.5 text-sm font-medium rounded-lg transition-colors w-full text-left"
                                :class="settingsOpen && activeSettingsTab === link.tab ? 'bg-sidebar-bg-active text-sidebar-text-active' : 'text-sidebar-text hover:bg-surface-alt hover:text-text-theme'">
                                {{ link.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="border-t border-sidebar-border p-3 shrink-0">
                <div class="flex items-center gap-3 px-3 py-2">
                    <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="" class="w-8 h-8 rounded-full object-cover shrink-0" />
                    <div v-else class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-semibold shrink-0">
                        {{ auth.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-text-theme truncate">{{ auth.user?.name }}</p>
                        <p class="text-xs text-text-tertiary truncate">{{ auth.user?.email }}</p>
                    </div>
                    <button @click="logout" class="p-1.5 rounded-lg text-text-tertiary hover:text-danger-theme hover:bg-danger-light transition-colors" title="Sign out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main area — lg:pl-64 makes room for fixed sidebar -->
        <div class="lg:pl-64">
            <!-- Top header with centered day-to-day nav -->
            <header class="bg-header-bg border-b border-header-border sticky top-0 z-30">
                <div class="grid grid-cols-3 items-center h-16 px-4 sm:px-6">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = true" class="p-2 rounded-lg hover:bg-surface-alt lg:hidden">
                            <svg class="w-6 h-6 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                    <nav class="flex items-center justify-center gap-1">
                        <a
                            v-for="link in topNavLinks"
                            :key="link.href"
                            :href="link.href"
                            class="px-3 py-2 text-sm font-medium rounded-lg transition-colors whitespace-nowrap"
                            :class="isActive(link.href) ? 'bg-surface-alt text-primary' : 'text-text-secondary hover:bg-surface-alt'"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                    <div class="flex items-center justify-end gap-4">
                        <NotificationBell />
                        <a href="/sync-status" class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-surface-alt transition-colors" title="Sync Status">
                            <GlobalSyncIndicator :show-label="true" variant="full" />
                        </a>
                        <span class="text-sm text-text-tertiary hidden sm:block">{{ auth.user?.name }}</span>
                        <div class="relative">
                            <button @click="menuOpen = !menuOpen" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-text-secondary rounded-lg hover:bg-surface-alt transition-colors">
                                <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="" class="w-8 h-8 rounded-full object-cover" />
                                <div v-else class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                    {{ auth.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                </div>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div v-if="menuOpen" class="absolute right-0 mt-2 w-48 bg-surface-raised rounded-lg shadow-lg border border-border-theme py-1 z-50">
                                <div class="px-4 py-2 border-b border-border-light">
                                    <p class="text-sm font-medium text-text-theme">{{ auth.user?.name }}</p>
                                    <p class="text-xs text-text-tertiary">{{ auth.user?.email }}</p>
                                </div>
                                <button @click="logout" class="w-full text-left px-4 py-2 text-sm text-danger-theme hover:bg-danger-light">
                                    Sign Out
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile: day-to-day nav strip below header -->
            <nav class="flex lg:hidden gap-1 px-4 py-2 bg-header-bg border-b border-header-border overflow-x-auto">
                <a
                    v-for="link in topNavLinks"
                    :key="link.href"
                    :href="link.href"
                    class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors whitespace-nowrap"
                    :class="isActive(link.href) ? 'bg-surface-alt text-primary' : 'text-text-secondary hover:bg-surface-alt'"
                >
                    {{ link.label }}
                </a>
            </nav>

            <!-- Page content -->
            <main class="px-4 sm:px-6 py-6">
                <slot />
            </main>
        </div>
    </div>

    <SettingsPanel
        :open="settingsOpen"
        :tab="activeSettingsTab"
        @close="settingsOpen = false"
        @update:tab="activeSettingsTab = $event"
    />
</template>
