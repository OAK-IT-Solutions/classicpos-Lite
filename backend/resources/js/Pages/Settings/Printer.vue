<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Printer, Wifi, Usb, Save, CheckCircle2, XCircle, AlertCircle, RefreshCw, Cloud, CloudOff } from 'lucide-vue-next';
import { getPrinterConfig, setPrinterConfig, openDrawer, type PrinterConfig, type PrinterType } from '@/services/PrinterService';
import { useNetwork } from '@/composables/useNetwork';
import { useSyncSettings } from '@/composables/useSyncSettings';

defineProps<{ embedded?: boolean }>();

const { isOnline } = useNetwork();
const { updatePrinterConfig, isUpdating: isSyncingToServer } = useSyncSettings();

const config = ref<PrinterConfig>({
    type: 'browser',
    ip_address: '192.168.1.100',
    port: 9100,
    drawer_pin: 2,
    printer_name: '',
    device_id: '',
});

const loading = ref(true);
const saving = ref(false);
const testing = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);
const drawerTestResult = ref<{ success: boolean; message: string } | null>(null);

const printerTypes: { value: PrinterType; label: string; description: string; icon: any }[] = [
    {
        value: 'browser',
        label: 'Browser Print',
        description: 'Print using browser popup. No cash drawer support.',
        icon: Printer,
    },
    {
        value: 'usb',
        label: 'USB Printer',
        description: 'Connect via USB cable. Supports cash drawer kick-out.',
        icon: Usb,
    },
    {
        value: 'network',
        label: 'Network Printer',
        description: 'Connect via LAN/Wi-Fi. Supports cash drawer kick-out.',
        icon: Wifi,
    },
    {
        value: 'disabled',
        label: 'Disabled',
        description: 'Do not print receipts automatically.',
        icon: XCircle,
    },
];

async function loadConfig() {
    loading.value = true;
    try {
        const cfg = await getPrinterConfig();
        config.value = { ...cfg };
    } catch (err) {
        console.error('Failed to load printer config:', err);
    } finally {
        loading.value = false;
    }
}

async function saveConfig() {
    saving.value = true;
    testResult.value = null;
    let serverSynced = false;
    let serverError: string | null = null;

    try {
        // Always save locally first
        await setPrinterConfig(config.value);

        // Also sync to server if online
        if (isOnline.value) {
            try {
                await updatePrinterConfig(config.value);
                serverSynced = true;
            } catch (serverErr: any) {
                serverError = serverErr?.response?.data?.error?.message
                    || serverErr?.message
                    || 'Saved locally, but could not sync to server';
            }
        }

        if (serverSynced) {
            testResult.value = {
                success: true,
                message: 'Printer configuration saved and synced to server.',
            };
        } else if (serverError) {
            testResult.value = {
                success: true,
                message: `Saved locally on this device. ${serverError}`,
            };
        } else {
            testResult.value = {
                success: true,
                message: 'Printer configuration saved locally. Will sync to server when online.',
            };
        }
    } catch (err: any) {
        testResult.value = {
            success: false,
            message: err?.message || 'Failed to save configuration.',
        };
    } finally {
        saving.value = false;
    }
}

async function testPrint() {
    testing.value = true;
    testResult.value = null;
    try {
        const testReceipt = {
            invoiceNumber: 'TEST-' + Date.now(),
            branchName: 'ClassicPOS Test',
            items: [
                { name: 'Test Item 1', quantity: 1, price: 10.00 },
                { name: 'Test Item 2', quantity: 2, price: 5.00 },
            ],
            subtotal: 20.00,
            discount: 0,
            taxAmount: 1.50,
            total: 21.50,
            paymentMethod: 'cash',
            amountTendered: 25.00,
            changeDue: 3.50,
            date: new Date().toISOString(),
        };

        const { printReceiptOnly } = await import('@/services/PrinterService');
        const printed = await printReceiptOnly(testReceipt, config.value);
        testResult.value = {
            success: printed,
            message: printed
                ? 'Test receipt sent to printer successfully.'
                : 'Test receipt did not print. Check your printer configuration.',
        };
    } catch (err: any) {
        testResult.value = {
            success: false,
            message: err?.message || 'Test print failed.',
        };
    } finally {
        testing.value = false;
    }
}

async function testDrawer() {
    testing.value = true;
    drawerTestResult.value = null;
    try {
        const ok = await openDrawer(config.value);
        drawerTestResult.value = {
            success: ok,
            message: ok
                ? 'Cash drawer opened successfully.'
                : 'Could not open cash drawer. Check that the drawer is connected to the printer\'s RJ11 port.',
        };
    } catch (err: any) {
        drawerTestResult.value = {
            success: false,
            message: err?.message || 'Drawer test failed.',
        };
    } finally {
        testing.value = false;
    }
}

const requiresIp = computed(() => config.value.type === 'network');
const canTestDrawer = computed(() => config.value.type === 'usb' || config.value.type === 'network');

onMounted(loadConfig);
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-4xl mx-auto'">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-text-theme">Printer & Cash Drawer</h1>
                    <p class="text-text-tertiary mt-1">Configure how receipts are printed and the cash drawer is opened</p>
                </div>
                <button @click="loadConfig" class="px-3 py-2 bg-surface-raised border border-border-input text-text-secondary rounded-lg text-sm font-medium hover:bg-surface-alt transition-colors flex items-center gap-1.5">
                    <RefreshCw class="w-3.5 h-3.5" />
                    Reload
                </button>
            </div>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading configuration...</div>

            <div v-else class="space-y-6">
                <!-- Printer Type Selection -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Printer Connection Type</h2>
                    <p class="text-sm text-text-tertiary mb-4">Select how your receipt printer is connected to this device. The cash drawer connects to the printer via an RJ11 cable and is triggered by the printer.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <button v-for="pt in printerTypes" :key="pt.value"
                            type="button"
                            @click="config.type = pt.value"
                            class="text-left p-4 border-2 rounded-lg transition-colors"
                            :class="config.type === pt.value ? 'border-primary bg-primary/5' : 'border-border-theme hover:border-primary/50 hover:bg-surface-alt'">
                            <div class="flex items-start gap-3">
                                <component :is="pt.icon" class="w-5 h-5 mt-0.5" :class="config.type === pt.value ? 'text-primary' : 'text-text-tertiary'" />
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-sm" :class="config.type === pt.value ? 'text-primary' : 'text-text-theme'">{{ pt.label }}</span>
                                        <span v-if="config.type === pt.value" class="text-xs px-1.5 py-0.5 rounded bg-primary text-white">SELECTED</span>
                                    </div>
                                    <p class="text-xs text-text-tertiary mt-1">{{ pt.description }}</p>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Network Settings (only if Network) -->
                <div v-if="requiresIp" class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Network Printer Settings</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Printer IP Address</label>
                            <input v-model="config.ip_address" type="text" placeholder="192.168.1.100"
                                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-input-bg text-text-theme" />
                            <p class="text-xs text-text-tertiary mt-1">The local network IP address of your printer.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Port</label>
                            <input v-model.number="config.port" type="number" min="1" max="65535" placeholder="9100"
                                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-input-bg text-text-theme" />
                            <p class="text-xs text-text-tertiary mt-1">Default: 9100</p>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Printer Name (optional)</label>
                            <input v-model="config.printer_name" type="text" placeholder="e.g., Epson TM-T20"
                                class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-ring focus:border-primary outline-none bg-input-bg text-text-theme" />
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2">
                        <AlertCircle class="w-4 h-4 text-amber-700 mt-0.5 flex-shrink-0" />
                        <div class="text-xs text-amber-900">
                            <strong>Network printing note:</strong> Browsers cannot open raw TCP sockets. For most network printers,
                            you'll need a small <strong>local print service</strong> running on this computer that forwards print jobs
                            to the printer's IP. See the printer help docs for setup instructions.
                        </div>
                    </div>
                </div>

                <!-- Cash Drawer Settings -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Cash Drawer</h2>
                    <p class="text-sm text-text-tertiary mb-4">The cash drawer connects to your printer via the RJ11/RJ12 port and is triggered by an electrical pulse.</p>

                    <div v-if="!canTestDrawer" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-900">
                        <strong>Note:</strong> Cash drawer kick-out requires a USB or Network printer. Browser printing does not support cash drawer control.
                    </div>

                    <div v-else>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Drawer Kick-out Pin</label>
                        <div class="flex gap-2 max-w-xs">
                            <button type="button" @click="config.drawer_pin = 2"
                                class="flex-1 px-3 py-2 border-2 rounded-lg text-sm font-medium transition-colors"
                                :class="config.drawer_pin === 2 ? 'border-primary bg-primary/5 text-primary' : 'border-border-theme text-text-secondary hover:border-primary/50'">
                                Pin 2 (Standard)
                            </button>
                            <button type="button" @click="config.drawer_pin = 5"
                                class="flex-1 px-3 py-2 border-2 rounded-lg text-sm font-medium transition-colors"
                                :class="config.drawer_pin === 5 ? 'border-primary bg-primary/5 text-primary' : 'border-border-theme text-text-secondary hover:border-primary/50'">
                                Pin 5 (Optional)
                            </button>
                        </div>
                        <p class="text-xs text-text-tertiary mt-2">Pin 2 is the most common. Use Pin 5 if your drawer is wired to the printer's optional connector.</p>
                    </div>

                    <div v-if="canTestDrawer" class="mt-4">
                        <button @click="testDrawer" :disabled="testing"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 flex items-center gap-1.5">
                            <span v-if="testing">Testing...</span>
                            <span v-else>Test Cash Drawer</span>
                        </button>
                        <div v-if="drawerTestResult" class="mt-3 p-3 rounded-lg flex items-start gap-2"
                            :class="drawerTestResult.success ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200'">
                            <CheckCircle2 v-if="drawerTestResult.success" class="w-4 h-4 text-emerald-700 mt-0.5 flex-shrink-0" />
                            <XCircle v-else class="w-4 h-4 text-red-700 mt-0.5 flex-shrink-0" />
                            <span class="text-sm" :class="drawerTestResult.success ? 'text-emerald-900' : 'text-red-900'">{{ drawerTestResult.message }}</span>
                        </div>
                    </div>
                </div>

                <!-- Test Print -->
                <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                    <h2 class="text-lg font-semibold text-text-theme mb-4">Test Print</h2>
                    <p class="text-sm text-text-tertiary mb-4">Send a test receipt to verify your printer is working correctly.</p>
                    <div class="flex gap-3">
                        <button @click="testPrint" :disabled="testing || config.type === 'disabled'"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 flex items-center gap-1.5">
                            <Printer class="w-4 h-4" />
                            {{ testing ? 'Printing...' : 'Send Test Print' }}
                        </button>
                    </div>
                    <div v-if="testResult" class="mt-3 p-3 rounded-lg flex items-start gap-2"
                        :class="testResult.success ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200'">
                        <CheckCircle2 v-if="testResult.success" class="w-4 h-4 text-emerald-700 mt-0.5 flex-shrink-0" />
                        <XCircle v-else class="w-4 h-4 text-red-700 mt-0.5 flex-shrink-0" />
                        <span class="text-sm" :class="testResult.success ? 'text-emerald-900' : 'text-red-900'">{{ testResult.message }}</span>
                    </div>
                </div>

                <!-- Save -->
                <div class="flex items-center justify-end gap-3">
                    <div class="text-xs text-text-tertiary flex items-center gap-1.5">
                        <Cloud v-if="isOnline" class="w-3.5 h-3.5 text-emerald-600" />
                        <CloudOff v-else class="w-3.5 h-3.5 text-amber-600" />
                        <span v-if="isOnline">Will sync to server</span>
                        <span v-else>Will save locally only</span>
                    </div>
                    <button @click="saveConfig" :disabled="saving || isSyncingToServer"
                        class="px-5 py-2.5 bg-gradient-to-r from-primary to-primary/80 text-white text-sm font-bold rounded-lg hover:from-primary-hover hover:to-primary transition-all shadow-md disabled:opacity-50 flex items-center gap-1.5">
                        <Save class="w-4 h-4" />
                        {{ saving ? 'Saving...' : 'Save Configuration' }}
                    </button>
                </div>

                <!-- Help Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-blue-900 mb-2">Setup Tips</h3>
                    <ul class="text-xs text-blue-800 space-y-1.5 list-disc list-inside">
                        <li><strong>USB:</strong> Most receipt printers (Epson, Xprinter, Star Micronics) work out of the box. The first time you print, your browser will ask for USB permission.</li>
                        <li><strong>Network:</strong> Configure the printer's static IP in the printer's setup menu first. Then test with a print from your computer.</li>
                        <li><strong>Cash Drawer:</strong> Connects to the printer's RJ11/RJ12 port. The printer sends a 24V pulse to release the latch when triggered.</li>
                        <li><strong>Browser fallback:</strong> Use this if you don't have a hardware printer. The browser will open a print dialog for any connected system printer.</li>
                    </ul>
                </div>
            </div>
        </div>
    </component>
</template>
