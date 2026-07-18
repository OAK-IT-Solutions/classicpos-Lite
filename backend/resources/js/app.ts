import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { registerSW } from 'virtual:pwa-register';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ToastService from 'primevue/toastservice';
import { useAuth } from '@/composables/useAuth';
import { useLocale } from '@/composables/useLocale';
import { useNetwork, useNetworkAutoInit } from '@/composables/useNetwork';
import { startBackgroundSync } from '@/services/SyncService';

// Initialize theme from localStorage before mounting
useLocale();

// Initialize network monitoring globally
useNetwork();

createInertiaApp({
    resolve: (name) => {
        return resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        );
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.dark',
                        cssLayer: {
                            name: 'primevue',
                            order: 'tailwind-base, primevue, tailwind-utilities',
                        },
                    },
                },
            })
            .use(ToastService);

        const auth = useAuth();
        auth.restore();

        app.provide('auth', auth);
        app.provide('network', useNetwork());

        app.mount(el);

        // Validate token server-side after mount.
        // If invalid, check() clears localStorage and the 401 interceptor redirects to /login.
        auth.check();

        // Register service worker for PWA / offline support
        registerSW({
            immediate: true,
            onRegisteredSW(swUrl, registration) {
                console.log('[PWA] Service worker registered:', swUrl);

                // Check for updates every hour
                if (registration) {
                    setInterval(() => {
                        registration.update();
                    }, 60 * 60 * 1000);
                }
            },
            onRegisterError(error) {
                console.warn('[PWA] Service worker registration failed:', error);
            },
            onNeedRefresh() {
                console.log('[PWA] New content available, refresh to update.');
                // Could show a toast/banner here prompting the user to refresh
            },
            onOfflineReady() {
                console.log('[PWA] App ready to work offline.');
            },
        });

        // Start background sync engine
        startBackgroundSync();
    },
    progress: {
        color: '#2563eb',
    },
});
