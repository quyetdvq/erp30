import { defineConfig, devices } from '@playwright/test'

// Browser-native synthetic Push. Runs against local Vite preview, never the DEV API.
// It does NOT prove a real Web Push provider accepted a delivery.
export default defineConfig({
  testDir: './e2e',
  testMatch: '**/m11-push-service-worker.spec.ts',
  timeout: 45_000,
  retries: 0,
  workers: 1,
  reporter: 'line',
  use: {
    baseURL: 'http://127.0.0.1:4174',
    headless: true,
    serviceWorkers: 'allow',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: 'pnpm preview --host 127.0.0.1 --port 4174 --strictPort',
    url: 'http://127.0.0.1:4174',
    reuseExistingServer: false,
    timeout: 30_000,
  },
})
