import { defineConfig } from '@playwright/test'

import baseConfig from './playwright.config'

/**
 * Firefox-only diagnostic override. Keeps the original localhost baseURL,
 * webServer, fixtures, projects and timeouts intact.
 *
 * Run with: pnpm exec playwright test --config=playwright.firefox-diagnostic.config.ts --project=firefox
 */
export default defineConfig(baseConfig, {
  outputDir: 'test-results/firefox-diagnostic',
  use: {
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
})
