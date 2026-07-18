<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLocale, THEMES } from '@/composables/useLocale';
import dayjs from 'dayjs';

defineProps<{ embedded?: boolean }>();

const {
    settings,
    loading,
    theme,
    fetchLocale,
    updateLocale,
    setTheme,
} = useLocale();

const saving = ref(false);
const saved = ref(false);
const error = ref('');

onMounted(async () => {
    await fetchLocale();
});

const currencies = [
    { code: 'KES', symbol: 'KSh', name: 'Kenyan Shilling', flag: '🇰🇪' },
    { code: 'UGX', symbol: 'USh', name: 'Ugandan Shilling', flag: '🇺🇬' },
    { code: 'TZS', symbol: 'TSh', name: 'Tanzanian Shilling', flag: '🇹🇿' },
    { code: 'RWF', symbol: 'FRw', name: 'Rwandan Franc', flag: '🇷🇼' },
    { code: 'XAF', symbol: 'FCFA', name: 'Central African CFA', flag: '🇨🇲' },
    { code: 'XOF', symbol: 'CFA', name: 'West African CFA', flag: '🇸🇳' },
    { code: 'USD', symbol: '$', name: 'US Dollar', flag: '🇺🇸' },
    { code: 'EUR', symbol: '€', name: 'Euro', flag: '🇪🇺' },
    { code: 'GBP', symbol: '£', name: 'British Pound', flag: '🇬🇧' },
    { code: 'ZAR', symbol: 'R', name: 'South African Rand', flag: '🇿🇦' },
    { code: 'NGN', symbol: '₦', name: 'Nigerian Naira', flag: '🇳🇬' },
];

const dateFormats = [
    { value: 'DD/MM/YYYY', label: 'DD/MM/YYYY', example: dayjs().format('DD/MM/YYYY') },
    { value: 'MM/DD/YYYY', label: 'MM/DD/YYYY', example: dayjs().format('MM/DD/YYYY') },
    { value: 'YYYY-MM-DD', label: 'YYYY-MM-DD', example: dayjs().format('YYYY-MM-DD') },
];

const timeFormats = [
    { value: '12h', label: '12-hour (02:30 PM)', example: dayjs().format('hh:mm A') },
    { value: '24h', label: '24-hour (14:30)', example: dayjs().format('HH:mm') },
];

const languages = [
    { value: 'en', label: 'English', flag: '🇬🇧', available: true },
    { value: 'sw', label: 'Kiswahili', flag: '🇰🇪', available: false },
    { value: 'fr', label: 'Français', flag: '🇫🇷', available: false },
];

const timezones = [
    'Africa/Nairobi', 'Africa/Kampala', 'Africa/Dar_es_Salaam',
    'Africa/Kigali', 'Africa/Bujumbura', 'Africa/Juba',
    'Africa/Lagos', 'Africa/Accra', 'Africa/Johannesburg',
    'Africa/Cairo', 'Africa/Casablanca', 'Africa/Addis_Ababa',
    'Europe/London', 'Europe/Paris', 'Europe/Berlin',
    'America/New_York', 'America/Chicago', 'America/Denver',
    'America/Los_Angeles', 'Asia/Dubai', 'Asia/Kolkata',
    'UTC',
];

const previewDate = computed(() => {
    const now = dayjs();
    const fmt = settings.value.date_format;
    return now.format(fmt);
});

const previewTime = computed(() => {
    const now = dayjs();
    const fmt = settings.value.time_format === '24h' ? 'HH:mm' : 'hh:mm A';
    return now.format(fmt);
});

function mapSep(code: string): string {
    const map: Record<string, string> = { comma: ',', dot: '.', space: ' ' };
    return map[code] ?? code;
}

const previewCurrency = computed(() => {
    const s = settings.value;
    const thousandSep = mapSep(s.thousands_separator);
    const decimalSep = mapSep(s.decimal_separator);
    const parts = Number(15420.50).toFixed(s.decimal_places).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
    const formatted = parts.join(decimalSep);
    const cur = currencies.find(c => c.code === s.currency);
    const sym = cur?.symbol || s.currency;
    return s.currency_position === 'before' ? `${sym}${formatted}` : `${formatted} ${sym}`;
});

async function save() {
    saving.value = true;
    saved.value = false;
    error.value = '';
    try {
        await updateLocale({
            currency: settings.value.currency,
            timezone: settings.value.timezone,
            date_format: settings.value.date_format,
            time_format: settings.value.time_format,
            language: settings.value.language,
            first_day_of_week: settings.value.first_day_of_week,
            decimal_separator: settings.value.decimal_separator,
            thousands_separator: settings.value.thousands_separator,
            currency_position: settings.value.currency_position,
            decimal_places: settings.value.decimal_places,
        });
        saved.value = true;
    } catch (err: any) {
        error.value = err.response?.data?.error?.message || 'Failed to save locale settings.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <component :is="embedded ? 'div' : AppLayout" :class="embedded ? 'p-4' : ''">
        <div :class="embedded ? '' : 'max-w-3xl mx-auto'">
            <div v-if="!embedded" class="mb-8">
                <h1 class="text-2xl font-bold text-text-theme">Locale & Theme</h1>
                <p class="text-text-secondary mt-1">Manage currency, date/time formats, language, and appearance</p>
            </div>

            <div v-if="loading" class="text-center py-12 text-text-tertiary">Loading...</div>

            <div v-else>
                <div v-if="saved" class="mb-4 p-3 bg-success-light border border-success-theme/20 rounded-lg text-sm text-success-theme">
                    Locale settings saved successfully.
                </div>
                <div v-if="error" class="mb-4 p-3 bg-danger-light border border-danger-theme/20 rounded-lg text-sm text-danger-theme">{{ error }}</div>

                <form @submit.prevent="save" class="space-y-6">
                    <!-- Theme -->
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                        <h2 class="text-lg font-semibold text-text-theme mb-1">Theme</h2>
                        <p class="text-sm text-text-secondary mb-4">Choose your preferred color scheme and accent</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <button
                                v-for="t in THEMES"
                                :key="t.id"
                                type="button"
                                @click="setTheme(t.id)"
                                class="relative flex flex-col items-center gap-2 p-4 border-2 rounded-xl transition-all group"
                                :class="theme === t.id
                                    ? 'border-primary ring-2 ring-primary-ring bg-primary-light'
                                    : 'border-border-input hover:border-primary/40 hover:bg-surface-alt'"
                            >
                                <!-- Color Preview -->
                                <div class="w-full h-10 rounded-lg overflow-hidden flex shadow-sm">
                                    <div class="flex-1" :style="{ backgroundColor: t.preview.bg }"></div>
                                    <div class="flex-1" :style="{ backgroundColor: t.preview.surface }"></div>
                                    <div class="flex-1 flex items-center justify-center" :style="{ backgroundColor: t.preview.primary }">
                                        <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16z"/></svg>
                                    </div>
                                </div>
                                <span class="text-sm font-medium text-text-theme">{{ t.label }}</span>
                                <span class="text-xs text-text-tertiary capitalize">{{ t.colorScheme }}</span>
                                <!-- Checkmark -->
                                <div
                                    v-if="theme === t.id"
                                    class="absolute top-2 right-2 w-5 h-5 bg-primary rounded-full flex items-center justify-center"
                                >
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Currency -->
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                        <h2 class="text-lg font-semibold text-text-theme mb-4">Currency</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-text-secondary mb-1">Currency</label>
                                <select v-model="settings.currency" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option v-for="c in currencies" :key="c.code" :value="c.code">
                                        {{ c.flag }} {{ c.code }} — {{ c.name }} ({{ c.symbol }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Position</label>
                                <select v-model="settings.currency_position" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option value="before">Before amount (KSh 1,500)</option>
                                    <option value="after">After amount (1,500 KSh)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Decimal Places</label>
                                <select v-model="settings.decimal_places" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option :value="0">0 (1,500)</option>
                                    <option :value="2">2 (1,500.00)</option>
                                    <option :value="3">3 (1,500.000)</option>
                                    <option :value="4">4 (1,500.0000)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Decimal Separator</label>
                                <select v-model="settings.decimal_separator" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option value=".">Period (.)</option>
                                    <option value="comma">Comma (,)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Thousands Separator</label>
                                <select v-model="settings.thousands_separator" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option value="comma">Comma (,)</option>
                                    <option value="dot">Period (.)</option>
                                    <option value="space">Space ( )</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-surface-alt rounded-lg text-sm">
                            <span class="text-text-tertiary">Preview:</span>
                            <span class="ml-2 font-mono text-text-theme">{{ previewCurrency }}</span>
                        </div>
                    </div>

                    <!-- Time & Date -->
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                        <h2 class="text-lg font-semibold text-text-theme mb-4">Time & Date</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-text-secondary mb-1">Timezone</label>
                                <select v-model="settings.timezone" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Date Format</label>
                                <select v-model="settings.date_format" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option v-for="df in dateFormats" :key="df.value" :value="df.value">
                                        {{ df.label }} ({{ df.example }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">Time Format</label>
                                <select v-model="settings.time_format" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option v-for="tf in timeFormats" :key="tf.value" :value="tf.value">
                                        {{ tf.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-1">First Day of Week</label>
                                <select v-model="settings.first_day_of_week" class="w-full border border-border-input rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-ring bg-input-bg text-text-theme">
                                    <option value="monday">Monday</option>
                                    <option value="sunday">Sunday</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-surface-alt rounded-lg text-sm">
                            <span class="text-text-tertiary">Preview:</span>
                            <span class="ml-2 font-mono text-text-theme">{{ previewDate }} {{ previewTime }}</span>
                        </div>
                    </div>

                    <!-- Language -->
                    <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-6">
                        <h2 class="text-lg font-semibold text-text-theme mb-4">Language</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-text-secondary mb-1">App Language</label>
                                <div class="space-y-2">
                                    <label
                                        v-for="lang in languages"
                                        :key="lang.value"
                                        class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors"
                                        :class="settings.language === lang.value ? 'border-primary bg-primary-light' : 'border-border-input hover:border-primary/40'"
                                    >
                                        <input type="radio" v-model="settings.language" :value="lang.value" :disabled="!lang.available" class="sr-only" />
                                        <span class="text-lg">{{ lang.flag }}</span>
                                        <span class="text-sm font-medium text-text-theme flex-1">{{ lang.label }}</span>
                                        <span v-if="!lang.available" class="text-xs text-text-tertiary bg-surface-alt px-2 py-0.5 rounded">Coming Soon</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="submit" :disabled="saving" class="px-6 py-2.5 bg-btn-primary text-btn-primary-text rounded-lg font-medium hover:bg-btn-primary-hover disabled:opacity-50 transition-colors">
                            {{ saving ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </component>
</template>
