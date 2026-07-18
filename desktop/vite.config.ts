import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      // Shim @inertiajs/vue3 to our desktop adapter
      '@inertiajs/vue3': resolve(__dirname, 'src/shims/inertia.ts'),
      // Keep the @ alias working for shared composables from the backend
      '@': resolve(__dirname, '../backend/resources/js'),
      // Override the backend's AppLayout with our pass-through shim
      // This prevents double-layout when pages wrap content in <AppLayout>
      [resolve(__dirname, '../backend/resources/js/Layouts/AppLayout.vue').replace(/\\/g, '/')]:
        resolve(__dirname, 'src/shims/AppLayout.vue'),
    },
  },
  clearScreen: false,
  server: {
    port: 1420,
    strictPort: true,
  },
  envPrefix: ['VITE_', 'TAURI_ENV_*'],
  build: {
    target: process.env.TAURI_ENV_PLATFORM === 'windows' ? 'chrome105' : 'safari13',
    minify: !process.env.TAURI_ENV_DEBUG ? 'esbuild' : false,
    sourcemap: !!process.env.TAURI_ENV_DEBUG,
    rollupOptions: {
      // Alias the backend AppLayout to our shim
      plugins: [],
    },
  },
});
