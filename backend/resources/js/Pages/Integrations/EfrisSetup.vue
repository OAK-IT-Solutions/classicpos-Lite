<script setup lang="ts">
import { ref } from 'vue';
import { useIntegrations } from '@/composables/useIntegrations';
import { X, Loader2, CheckCircle, AlertCircle, ArrowRight, ArrowLeft, Eye, EyeOff } from 'lucide-vue-next';

const emit = defineEmits<{
    close: [];
    complete: [];
}>();

const { connect, loading, error } = useIntegrations();

const step = ref(1);
const totalSteps = 3;

const form = ref({
    weaf_email: '',
    weaf_password: '',
    tin: '',
    weaf_environment: 'sandbox' as 'sandbox' | 'production',
    auto_fiscalize: true,
    fiscalize_receipts: true,
});

const showPassword = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);

async function handleConnect() {
    try {
        await connect('efris', {
            name: 'URA EFRIS',
            ...form.value,
        });
        testResult.value = { success: true, message: 'EFRIS connected successfully!' };
        setTimeout(() => emit('complete'), 1500);
    } catch (e: any) {
        testResult.value = { success: false, message: e.response?.data?.error?.message || 'Connection failed' };
    }
}

function nextStep() {
    if (step.value < totalSteps) step.value++;
}

function prevStep() {
    if (step.value > 1) step.value--;
}

function canProceed(): boolean {
    if (step.value === 1) return !!form.value.weaf_email && !!form.value.weaf_password;
    if (step.value === 2) return !!form.value.tin && form.value.tin.length >= 10;
    return true;
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')"></div>
        <div class="relative bg-surface-raised rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-border-theme">
                <div>
                    <h2 class="text-lg font-bold text-text-theme">Connect URA EFRIS</h2>
                    <p class="text-xs text-text-tertiary">Electronic Fiscal Receipting and Invoicing System</p>
                </div>
                <button @click="emit('close')" class="p-1 rounded-lg hover:bg-surface-alt text-text-tertiary">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Progress -->
            <div class="px-6 py-3 bg-surface-alt border-b border-border-theme">
                <div class="flex items-center gap-2">
                    <div v-for="s in totalSteps" :key="s" class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                             :class="s <= step ? 'bg-primary text-white' : 'bg-gray-200 text-gray-500'">
                            {{ s }}
                        </div>
                        <div v-if="s < totalSteps" class="w-8 h-0.5" :class="s < step ? 'bg-primary' : 'bg-gray-200'"></div>
                    </div>
                    <span class="ml-2 text-xs text-text-tertiary">Step {{ step }} of {{ totalSteps }}</span>
                </div>
            </div>

            <!-- Content -->
            <div class="px-6 py-6">
                <!-- Step 1: WEAF Credentials -->
                <div v-if="step === 1">
                    <h3 class="text-sm font-semibold text-text-theme mb-4">WEAF API Credentials</h3>
                    <p class="text-xs text-text-tertiary mb-4">
                        Enter your WEAF Company account credentials. Sign up at
                        <a href="https://weafcompany.com/login" target="_blank" class="text-primary underline">weafcompany.com</a>
                        if you don't have an account.
                    </p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-text-theme mb-1">Email</label>
                            <input v-model="form.weaf_email" type="email"
                                class="w-full px-3 py-2 border border-border-theme rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                                placeholder="your@email.com" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-theme mb-1">Password</label>
                            <div class="relative">
                                <input v-model="form.weaf_password" :type="showPassword ? 'text' : 'password'"
                                    class="w-full px-3 py-2 pr-10 border border-border-theme rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                                    placeholder="Your WEAF password" />
                                <button @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-text-tertiary">
                                    <EyeOff v-if="showPassword" class="w-4 h-4" />
                                    <Eye v-else class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: TIN & Environment -->
                <div v-if="step === 2">
                    <h3 class="text-sm font-semibold text-text-theme mb-4">Tax Configuration</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-text-theme mb-1">Tax Identification Number (TIN)</label>
                            <input v-model="form.tin" type="text"
                                class="w-full px-3 py-2 border border-border-theme rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                                placeholder="e.g. 1000251604" />
                            <p class="text-xs text-text-tertiary mt-1">Your URA-issued TIN (10+ digits)</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-theme mb-1">Environment</label>
                            <div class="flex gap-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.weaf_environment" value="sandbox"
                                        class="w-4 h-4 text-primary focus:ring-primary" />
                                    <span class="text-sm text-text-theme">Sandbox (Testing)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.weaf_environment" value="production"
                                        class="w-4 h-4 text-primary focus:ring-primary" />
                                    <span class="text-sm text-text-theme">Production</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Settings -->
                <div v-if="step === 3">
                    <h3 class="text-sm font-semibold text-text-theme mb-4">Fiscalization Settings</h3>
                    <div class="space-y-4">
                        <label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg border border-border-theme hover:bg-surface-alt">
                            <input type="checkbox" v-model="form.auto_fiscalize"
                                class="w-4 h-4 mt-0.5 text-primary focus:ring-primary rounded" />
                            <div>
                                <p class="text-sm font-medium text-text-theme">Auto-fiscalize sales</p>
                                <p class="text-xs text-text-tertiary">Automatically send each completed sale to EFRIS for fiscalization</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg border border-border-theme hover:bg-surface-alt">
                            <input type="checkbox" v-model="form.fiscalize_receipts"
                                class="w-4 h-4 mt-0.5 text-primary focus:ring-primary rounded" />
                            <div>
                                <p class="text-sm font-medium text-text-theme">Generate receipts (not invoices)</p>
                                <p class="text-xs text-text-tertiary">Use fiscal receipts for non-VAT businesses. Uncheck for B2B invoices.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Error -->
                <div v-if="error || testResult" class="mt-4 p-3 rounded-lg text-sm"
                     :class="(testResult?.success === false || error) ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'">
                    <div class="flex items-center gap-2">
                        <CheckCircle v-if="testResult?.success" class="w-4 h-4 shrink-0" />
                        <AlertCircle v-else class="w-4 h-4 shrink-0" />
                        {{ testResult?.message || error }}
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-6 py-4 border-t border-border-theme bg-surface-alt">
                <button v-if="step > 1" @click="prevStep"
                    class="flex items-center gap-1 px-4 py-2 text-sm font-medium text-text-secondary hover:text-text-theme transition-colors">
                    <ArrowLeft class="w-4 h-4" /> Back
                </button>
                <div v-else></div>

                <div class="flex items-center gap-2">
                    <button @click="emit('close')"
                        class="px-4 py-2 text-sm font-medium text-text-secondary hover:text-text-theme transition-colors">
                        Cancel
                    </button>
                    <button v-if="step < totalSteps" @click="nextStep" :disabled="!canProceed()"
                        class="flex items-center gap-1 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Next <ArrowRight class="w-4 h-4" />
                    </button>
                    <button v-else @click="handleConnect" :disabled="loading"
                        class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50">
                        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
                        {{ loading ? 'Connecting...' : 'Connect' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
