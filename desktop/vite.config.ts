import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@inertiajs/vue3': resolve(__dirname, 'src/shims/inertia.ts'),
      '@': resolve(__dirname, '../backend/resources/js'),
      [resolve(__dirname, '../backend/resources/js/Layouts/AppLayout.vue').replace(/\\/g, '/')]:
        resolve(__dirname, 'src/shims/AppLayout.vue'),
    },
    // Allow vite to resolve imports from desktop's node_modules
    // even when the import originates from backend/ files
    modules: [
      resolve(__dirname, 'node_modules'),
      resolve(__dirname, '../backend/node_modules'),
      'node_modules',
    ],
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
  },
});
