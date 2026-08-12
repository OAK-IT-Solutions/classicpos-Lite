import { ref, computed } from 'vue';
import api from './axios';
import dayjs from 'dayjs';
import advancedFormat from 'dayjs/plugin/advancedFormat';
import 'dayjs/locale/sw';
import 'dayjs/locale/fr';

dayjs.extend(advancedFormat);

const localeMap: Record<string, string> = {
    en: 'en',
    sw: 'sw',
    fr: 'fr',
};

interface LocaleSettings {
    currency: string;
    timezone: string;
    date_format: string;
    time_format: string;
    language: string;
    first_day_of_week: string;
    decimal_separator: string;
    thousands_separator: string;
    currency_position: string;
    decimal_places: number;
}

export interface ThemeOption {
    id: string;
    label: string;
    colorScheme: 'light' | 'dark';
    accent: string;
    preview: { bg: string; surface: string; text: string; primary: string };
}

export const THEMES: ThemeOption[] = [
    {
        id: 'theme-light-blue',
        label: 'Blue',
        colorScheme: 'light',
        accent: '#2563eb',
        preview: { bg: '#f9fafb', surface: '#ffffff', text: '#111827', primary: '#2563eb' },
    },
    {
        id: 'theme-dark-blue',
        label: 'Dark Blue',
        colorScheme: 'dark',
        accent: '#3b82f6',
        preview: { bg: '#111827', surface: '#1f2937', text: '#f3f4f6', primary: '#3b82f6' },
    },
    {
        id: 'theme-light-purple',
        label: 'Purple',
        colorScheme: 'light',
        accent: '#7c3aed',
        preview: { bg: '#f9fafb', surface: '#ffffff', text: '#111827', primary: '#7c3aed' },
    },
    {
        id: 'theme-light-red',
        label: 'Red',
        colorScheme: 'light',
        accent: '#dc2626',
        preview: { bg: '#f9fafb', surface: '#ffffff', text: '#111827', primary: '#dc2626' },
    },
    {
        id: 'theme-light-teal',
        label: 'Teal',
        colorScheme: 'light',
        accent: '#0d9488',
        preview: { bg: '#f9fafb', surface: '#ffffff', text: '#111827', primary: '#0d9488' },
    },
    {
        id: 'theme-dark-teal',
        label: 'Dark Teal',
        colorScheme: 'dark',
        accent: '#14b8a6',
        preview: { bg: '#111827', surface: '#1f2937', text: '#f3f4f6', primary: '#14b8a6' },
    },
];

const currencySymbols: Record<string, { symbol: string; code: string }> = {
    KES: { symbol: 'KSh', code: 'KES' },
    USD: { symbol: '$', code: 'USD' },
    EUR: { symbol: '€', code: 'EUR' },
    GBP: { symbol: '£', code: 'GBP' },
    UGX: { symbol: 'USh', code: 'UGX' },
    TZS: { symbol: 'TSh', code: 'TZS' },
    RWF: { symbol: 'FRw', code: 'RWF' },
    XAF: { symbol: 'FCFA', code: 'XAF' },
    XOF: { symbol: 'CFA', code: 'XOF' },
    ZAR: { symbol: 'R', code: 'ZAR' },
    NGN: { symbol: '₦', code: 'NGN' },
};

const sepMap: Record<string, string> = {
    '.': '.',
    comma: ',',
    dot: '.',
    space: ' ',
};

function mapSep(code: string): string {
    return sepMap[code] ?? code;
}

const defaults: LocaleSettings = {
    currency: 'KES',
    timezone: 'Africa/Nairobi',
    date_format: 'DD/MM/YYYY',
    time_format: '12h',
    language: 'en',
    first_day_of_week: 'monday',
    decimal_separator: '.',
    thousands_separator: 'comma',
    currency_position: 'before',
    decimal_places: 2,
};

const settings = ref<LocaleSettings>({ ...defaults });
const loaded = ref(false);
const loading = ref(false);

const THEME_KEY = 'classicpos_theme';

function loadTheme(): string {
    return localStorage.getItem(THEME_KEY) || 'theme-light-blue';
}

function saveTheme(value: string) {
    localStorage.setItem(THEME_KEY, value);
}

const theme = ref(loadTheme());

function applyTheme(val: string) {
    const root = document.documentElement;
    const allThemeClasses = THEMES.map(t => t.id);
    root.classList.remove(...allThemeClasses);

    if (val === 'system') {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        root.classList.add(prefersDark ? 'theme-dark-blue' : 'theme-light-blue');
    } else {
        root.classList.add(val);
    }
}

function setTheme(val: string) {
    theme.value = val;
    saveTheme(val);
    applyTheme(val);
}

function getThemeOption(val: string): ThemeOption {
    return THEMES.find(t => t.id === val) || THEMES[0];
}

function isDarkTheme(val: string): boolean {
    return getThemeOption(val).colorScheme === 'dark';
}

applyTheme(theme.value);

if (typeof window !== 'undefined') {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (theme.value === 'system') {
            applyTheme('system');
        }
    });
}

const symbol = computed(() => currencySymbols[settings.value.currency]?.symbol || settings.value.currency);

function formatCurrency(amount: number): string {
    const s = settings.value;
    const parts = Number(amount).toFixed(s.decimal_places).split('.');
    const thousandSep = mapSep(s.thousands_separator);
    const decimalSep = mapSep(s.decimal_separator);
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
    const formatted = parts.join(decimalSep);
    return s.currency_position === 'before' ? `${symbol.value}${formatted}` : `${formatted} ${symbol.value}`;
}

function formatDate(iso: string | Date): string {
    const s = settings.value;
    const lang = localeMap[s.language] || 'en';
    return dayjs(iso).locale(lang).format(s.date_format);
}

function formatTime(iso: string | Date): string {
    const s = settings.value;
    const lang = localeMap[s.language] || 'en';
    const fmt = s.time_format === '24h' ? 'HH:mm' : 'hh:mm A';
    return dayjs(iso).locale(lang).format(fmt);
}

function formatDateTime(iso: string | Date): string {
    return `${formatDate(iso)} ${formatTime(iso)}`;
}

async function fetchLocale(): Promise<void> {
    loading.value = true;
    try {
        const res = await api.get('/settings/locale');
        settings.value = { ...defaults, ...res.data.locale };
        loaded.value = true;
    } catch {
        //
    } finally {
        loading.value = false;
    }
}

async function updateLocale(payload: Partial<LocaleSettings>): Promise<void> {
    const merged = { ...settings.value, ...payload };
    await api.put('/settings/locale', merged);
    settings.value = merged;
}

export function useLocale() {
    return {
        settings,
        loaded,
        loading,
        theme,
        symbol,
        themes: THEMES,
        setTheme,
        getThemeOption,
        isDarkTheme,
        fetchLocale,
        updateLocale,
        formatCurrency,
        formatDate,
        formatTime,
        formatDateTime,
    };
}
