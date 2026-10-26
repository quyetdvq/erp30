import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import VueDevTools from 'vite-plugin-vue-devtools'
import { buildChunkFileName } from './scripts/build-output-names.ts'

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
        // Keep readable hashed names, but let Rolldown choose chunk boundaries.
        // The forced naive-ui/vendor/vue groups risk circular cross-chunk
        // initialization; the deployed bundle fails at lib-vendor -> lib-ui.
        // The dev server does not exercise that production-only split graph.
        entryFileNames: 'assets/app-[name]-[hash:8].js',
        chunkFileNames: buildChunkFileName,
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
