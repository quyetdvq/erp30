import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import VueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  // The production SPA is deployed at the domain root.
  base: '/',
  // Keep the in-browser inspector available for `pnpm dev` only.
  plugins: [VueDevTools(), vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  build: {
    rolldownOptions: {
      output: {
        codeSplitting: {
          minSize: 10_000,
          groups: [
            {
              name: 'naive-ui',
              test: /[\\/]node_modules[\\/]naive-ui[\\/]/,
              // Do not ship every Naive UI component to routes that only need a few.
              // Split by dynamic-entry usage, with a bounded chunk-size target.
              entriesAware: true,
              minSize: 20_000,
              maxSize: 260_000,
            },
            {
              name: 'vue-runtime',
              test: /[\\/]node_modules[\\/](?:vue|@vue|vue-router|pinia)[\\/]/,
            },
            { name: 'icons', test: /[\\/]node_modules[\\/]@vicons[\\/]/ },
            { name: 'vendor', test: /[\\/]node_modules[\\/]/ },
          ],
        },
      },
    },
  },

  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,

    hmr: {
      host: process.env.VITE_HMR_HOST || 'erp.monmin.net',
      protocol: (process.env.VITE_HMR_PROTOCOL as 'ws' | 'wss' | undefined) || 'wss',
      clientPort: Number(process.env.VITE_HMR_PORT || 443),
    },
  },
})
