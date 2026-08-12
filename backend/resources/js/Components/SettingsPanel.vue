<script setup lang="ts">
import { ref, computed, watch, markRaw } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useAuth } from '@/composables/useAuth';
import ProfileSettings from '@/Pages/Settings/Profile.vue';
import BusinessSettings from '@/Pages/Settings/Business.vue';
import UsersSettings from '@/Pages/Settings/Users.vue';
import RolesSettings from '@/Pages/Settings/Roles.vue';
import BranchesSettings from '@/Pages/Settings/Branches.vue';
import WarehousesSettings from '@/Pages/Settings/Warehouses.vue';
import TaxesSettings from '@/Pages/Settings/TaxProfiles.vue';
import PromotionsSettings from '@/Pages/Settings/Promotions.vue';
import LoyaltySettings from '@/Pages/Settings/Loyalty.vue';
import DevicesSettings from '@/Pages/Settings/Devices.vue';
import PrinterSettings from '@/Pages/Settings/Printer.vue';
import SubscriptionSettings from '@/Pages/Settings/Subscription.vue';
import LocaleSettings from '@/Pages/Settings/Locale.vue';
import IntegrationsSettings from '@/Pages/Settings/Integrations.vue';

const props = defineProps<{
    open: boolean;
    tab: string;
}>();

const emit = defineEmits<{
    close: [];
    'update:tab': [tab: string];
}>();

const auth = useAuth();

interface SettingsTab {
    key: string;
    label: string;
    component: any;
}

const tabs: SettingsTab[] = [
    { key: 'profile', label: 'My Profile', component: markRaw(ProfileSettings) },
    { key: 'business', label: 'Business', component: markRaw(BusinessSettings) },
    { key: 'users', label: 'Users', component: markRaw(UsersSettings) },
    { key: 'roles', label: 'Roles & Permissions', component: markRaw(RolesSettings) },
    { key: 'branches', label: 'Branches', component: markRaw(BranchesSettings) },
    { key: 'warehouses', label: 'Warehouses', component: markRaw(WarehousesSettings) },
    { key: 'taxes', label: 'Taxes', component: markRaw(TaxesSettings) },
    { key: 'promotions', label: 'Promotions', component: markRaw(PromotionsSettings) },
    { key: 'loyalty', label: 'Loyalty', component: markRaw(LoyaltySettings) },
    { key: 'devices', label: 'Devices', component: markRaw(DevicesSettings) },
    { key: 'printer', label: 'Printer & Drawer', component: markRaw(PrinterSettings) },
    { key: 'subscription', label: 'Subscription', component: markRaw(SubscriptionSettings) },
    { key: 'locale', label: 'Locale & Theme', component: markRaw(LocaleSettings) },
    { key: 'integrations', label: 'Integrations', component: markRaw(IntegrationsSettings) },
];

const activeKey = ref(props.tab);

watch(() => props.tab, (val) => { activeKey.value = val; });
watch(activeKey, (val) => emit('update:tab', val));

const activeTab = computed(() => tabs.find(t => t.key === activeKey.value) || tabs[0]);

const panelStyle = computed(() => ({
    transform: props.open ? 'translateX(0)' : 'translateX(100%)',
    transition: 'transform 0.25s ease-in-out',
}));

function close() {
    emit('close');
}
</script>

<template>
    <!-- Overlay -->
    <div
        v-if="open"
        class="fixed inset-0 z-50 bg-surface-overlay"
        @click="close"
    ></div>

    <!-- Panel -->
    <div
        class="fixed top-0 right-0 z-50 h-full w-full max-w-5xl bg-surface-raised shadow-2xl border-l border-border-theme overflow-hidden flex flex-col"
        :style="panelStyle"
    >
        <!-- Header -->
        <div class="flex items-center justify-between shrink-0 h-14 px-5 border-b border-border-theme bg-surface-raised">
            <div class="flex items-center gap-3 overflow-x-auto">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    @click="activeKey = t.key"
                    class="px-3 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors"
                    :class="activeKey === t.key ? 'bg-primary-light text-primary' : 'text-text-tertiary hover:text-text-secondary hover:bg-surface-alt'"
                >
                    {{ t.label }}
                </button>
            </div>
            <button @click="close" class="p-1.5 rounded-lg hover:bg-surface-alt shrink-0 ml-3">
                <svg class="w-5 h-5 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto bg-surface-alt">
            <component :is="activeTab.component" :embedded="true" />
        </div>
    </div>
</template>
