import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

/**
 * Deliberately separate from playwright.config.ts: these tests use REAL
 * staging sessions and real PBAC. They must never auto-run in mocked CI.
 */
if (process.env.DINKERP_E2E_STAGING_CONFIRMED !== 'YES') {
  throw new Error(
    'Set DINKERP_E2E_STAGING_CONFIRMED=YES only after confirming an isolated staging/test deployment.',
  )
}
const uiOrigin = process.env.DINKERP_E2E_UI_ORIGIN
const apiOrigin = process.env.DINKERP_E2E_API_ORIGIN
if (!uiOrigin || !apiOrigin) {
  throw new Error('Both DINKERP_E2E_UI_ORIGIN and DINKERP_E2E_API_ORIGIN are required.')
}
for (const value of [uiOrigin, apiOrigin]) {
  const url = new URL(value)
  if (
    !['http:', 'https:'].includes(url.protocol) ||
    url.pathname !== '/' ||
    url.search ||
    url.hash
  ) {
    throw new Error('E2E origins must be bare http(s) origins without path/query/fragment.')
  }
  if (url.hostname === 'monmin.net' || url.hostname.endsWith('.monmin.net')) {
    throw new Error('Refusing to run live security regression against a known production hostname.')
  }
}

export default defineConfig({
  testDir: './e2e/live',
  testMatch: '**/*.live.spec.ts',
  timeout: 90_000,
  expect: { timeout: 10_000 },
  retries: 0,
  workers: 1,
  reporter: 'line',
  use: {
    baseURL: uiOrigin,
    headless: true,
    trace: 'off',
    screenshot: 'off',
    video: 'off',
  },
  projects: [{ name: 'chromium-live', use: { ...devices['Desktop Chrome'] } }],
  // Both UI and API must already be running. Never start or migrate a database.
  webServer: undefined,
})
