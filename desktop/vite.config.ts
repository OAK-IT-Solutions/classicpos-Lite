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
    },
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
