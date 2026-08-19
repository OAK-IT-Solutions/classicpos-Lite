import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import electron from 'vite-plugin-electron';
import { resolve } from 'path';

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    electron([
      {
        entry: 'electron/main.ts',
        vite: {
          build: {
            outDir: 'dist-electron',
            rollupOptions: {
              external: ['serialport', 'usb'],
            },
          },
        },
      },
      {
        entry: 'electron/preload.ts',
        onstart(args) {
          args.reload();
        },
        vite: {
          build: {
            outDir: 'dist-electron',
          },
        },
      },
    ]),
  ],
  resolve: {
    alias: {
      '@inertiajs/vue3': resolve(__dirname, 'src/shims/inertia.ts'),
      '@': resolve(__dirname, '../backend/resources/js'),
      [resolve(__dirname, '../backend/resources/js/Layouts/AppLayout.vue').replace(/\\/g, '/')]:
        resolve(__dirname, 'src/shims/AppLayout.vue'),
      'lucide-vue-next': resolve(__dirname, 'node_modules/lucide-vue-next'),
      'ag-grid-community': resolve(__dirname, 'node_modules/ag-grid-community'),
      'ag-grid-vue3': resolve(__dirname, 'node_modules/ag-grid-vue3'),
      'vue-chartjs': resolve(__dirname, 'node_modules/vue-chartjs'),
      'chart.js': resolve(__dirname, 'node_modules/chart.js'),
      'date-fns': resolve(__dirname, 'node_modules/date-fns'),
      'dayjs': resolve(__dirname, 'node_modules/dayjs'),
      'primevue/usetoast': resolve(__dirname, 'node_modules/primevue/usetoast'),
      'primevue/inputtext': resolve(__dirname, 'node_modules/primevue/inputtext'),
      'primevue/config': resolve(__dirname, 'node_modules/primevue/config'),
      'primevue/toast': resolve(__dirname, 'node_modules/primevue/toast'),
      'primevue/toastservice': resolve(__dirname, 'node_modules/primevue/toastservice'),
      'primevue/confirmationservice': resolve(__dirname, 'node_modules/primevue/confirmationservice'),
      'dexie': resolve(__dirname, 'node_modules/dexie'),
      'axios': resolve(__dirname, 'node_modules/axios'),
      '@primevue/themes/aura': resolve(__dirname, 'node_modules/@primevue/themes/aura'),
      'dayjs/plugin/advancedFormat': resolve(__dirname, 'node_modules/dayjs/plugin/advancedFormat'),
      'jspdf': resolve(__dirname, 'node_modules/jspdf'),
      'jspdf-autotable': resolve(__dirname, 'node_modules/jspdf-autotable'),
      'xlsx': resolve(__dirname, 'node_modules/xlsx'),
    },
    modules: [resolve(__dirname, 'node_modules'), 'node_modules'],
  },
  clearScreen: false,
  server: {
    port: 1420,
    strictPort: true,
  },
  envPrefix: ['VITE_'],
  build: {
    outDir: 'dist',
    target: 'chrome130',
    minify: !process.env.VITE_DEBUG ? 'esbuild' : false,
    sourcemap: !!process.env.VITE_DEBUG,
  },
});
