import { defineConfig, devices } from '@playwright/test'

// Test the actual `dist/` produced by Vite, not the dev server.
// No ERP database, login or live API is required.
export default defineConfig({
  testDir: './e2e',
  testMatch: '**/production-smoke.spec.ts',
  timeout: 30_000,
  expect: { timeout: 10_000 },
  retries: 0,
  workers: 1,
  reporter: 'line',
  use: {
    baseURL: 'http://127.0.0.1:4173',
    headless: true,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: 'pnpm preview --host 127.0.0.1 --port 4173 --strictPort',
    url: 'http://127.0.0.1:4173',
    reuseExistingServer: false,
    timeout: 30_000,
  },
})
