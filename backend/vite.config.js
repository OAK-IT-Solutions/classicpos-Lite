import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            strategies: 'generateSW',
            injectRegister: false,
            manifest: false,
            includeAssets: ['favicon.ico', 'icons/*.png', 'screenshots/*.png'],
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2,ttf,eot}'],
                navigateFallback: '/offline',
                navigateFallbackDenylist: [/^\/api/, /^\/admin/],
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'google-fonts-cache',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    {
                        urlPattern: /^\/api\/v1\/pos\/products(\?.*)?$/,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'pos-products-cache',
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    {
                        urlPattern: /^\/api\/v1\/categories(\?.*)?$/,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'pos-categories-cache',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                        },
                    },
                    {
                        urlPattern: /^\/api\/v1\/customers(\?.*)?$/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'customers-cache',
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                            networkTimeoutSeconds: 5,
                        },
                    },
                    {
                        urlPattern: /^\/api\/v1\/products\/by-barcode\/.*/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'barcode-lookup-cache',
                            expiration: {
                                maxEntries: 500,
                                maxAgeSeconds: 60 * 60 * 24 * 7,
                            },
                            networkTimeoutSeconds: 3,
                        },
                    },
                    {
                        urlPattern: /^\/api\/v1\/sync\/status/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'sync-status-cache',
                            networkTimeoutSeconds: 3,
                        },
                    },
                ],
            },
            devOptions: {
                enabled: false,
                type: 'module',
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
