// Browser regression against the real Vue ListTable + AppDialogHost components.
// Uses a Vite-served no-auth fixture: no live credentials, PBAC, or API changes.
import { expect, test } from '@playwright/test'

const harness = '/e2e/fixtures/ds5-hf3-harness.html'

for (const theme of ['light', 'dark'] as const) {
  test(`${theme}: Customers header switches columns and alternates ASC/DESC without compound sort`, async ({ page }) => {
    await page.goto(`${harness}?theme=${theme}`)
    const name = page.locator('.list-table__sort').nth(0)
    const code = page.locator('.list-table__sort').nth(1)

    await expect(page.locator('#sort-value')).toHaveText('name')
    await code.click()
    await expect(page.locator('#sort-value')).toHaveText('customer_code')
    await expect(page.locator('.list-table th').nth(1)).toHaveAttribute('aria-sort', 'ascending')
    await code.click()
    await expect(page.locator('#sort-value')).toHaveText('-customer_code')
    await expect(page.locator('.list-table th').nth(1)).toHaveAttribute('aria-sort', 'descending')
    await code.click()
    await expect(page.locator('#sort-value')).toHaveText('customer_code')
    await name.click()
    await expect(page.locator('#sort-value')).toHaveText('name')
    await name.click()
    await expect(page.locator('#sort-value')).toHaveText('-name')
  })

  test(`${theme}: one top-right API box stays above the modal mask at z-index 999`, async ({ page }) => {
    await page.goto(`${harness}?theme=${theme}`)
    await page.locator('#page-error').click()
    const notices = page.locator('[data-test="global-api-notices"]')
    await expect(notices).toHaveCount(1)
    await expect(notices).toContainText('Customer validation failed')
    await expect(notices).toHaveCSS('position', 'fixed')
    await expect(notices).toHaveCSS('z-index', '999')
    await page.locator('#open-dialog').click()
    const modal = page.locator('.ds-dialog-host')
    await expect(modal).toBeVisible()
    await expect(modal.locator('[data-test="global-api-notices"]')).toHaveCount(0)
    await expect(notices).toHaveCount(1)
    await page.locator('#modal-error').click()
    await expect(notices).toContainText('BAD_CUSTOMER')
    const location = await notices.evaluate((element) => {
      const rect = element.getBoundingClientRect()
      const firstAlert = element.querySelector('.n-alert')?.getBoundingClientRect()
      const front = firstAlert
        ? document.elementFromPoint(firstAlert.left + firstAlert.width / 2, firstAlert.top + firstAlert.height / 2)
        : null
      return {
        top: rect.top,
        right: window.innerWidth - rect.right,
        overlayVisible: !!front?.closest('.api-error-notice-stack'),
      }
    })
    expect(location.top).toBeLessThan(32)
    expect(location.right).toBeLessThan(32)
    expect(location.overlayVisible).toBe(true)
    await page.locator('#close-dialog').click()
    await expect(modal).toHaveCount(0)
    await expect(notices).toHaveCount(1)
    await expect(notices).toContainText('Customer validation failed')
  })
}

for (const theme of ['light', 'dark'] as const) {
  test(`${theme}: notification box stays inside a narrow viewport when a modal opens`, async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 700 })
    await page.goto(`${harness}?theme=${theme}`)
    await page.locator('#page-error').click()
    await page.locator('#open-dialog').click()
    const notices = page.locator('[data-test="global-api-notices"]')
    await expect(notices).toBeVisible()
    await expect(notices).toHaveCSS('z-index', '999')
    const bounds = await notices.boundingBox()
    expect(bounds).not.toBeNull()
    expect(bounds!.x).toBeGreaterThanOrEqual(0)
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(375)
    expect(bounds!.y).toBeGreaterThanOrEqual(0)
    expect(bounds!.y + bounds!.height).toBeLessThanOrEqual(700)
    await expect(page.locator('.ds-dialog-host')).toBeVisible()
  })
}

// DS5 HF5: severity styling is checked on the real Naive UI alert (not a CSS mock).
for (const theme of ['light', 'dark'] as const) {
  test(`${theme}: toast shows contrasting danger and warning surfaces with no geometry shift`, async ({ page }) => {
    await page.goto(`${harness}?theme=${theme}`)
    await page.locator('#open-dialog').click()
    const toast = page.locator('[data-test="global-api-notices"]')
    await page.locator('#modal-error').click()
    const error = toast.locator('.n-alert.api-error-notice--error')
    await expect(error).toBeVisible()
    await expect(error).toHaveCSS('background-color', theme === 'light' ? 'rgb(255, 226, 230)' : 'rgb(74, 39, 49)')
    await expect(error).toHaveCSS('border-left-width', '4px')
    await expect(toast).toHaveCSS('z-index', '999')
    const size = await error.boundingBox()
    expect(size).not.toBeNull()
    await expect(error.locator('.n-alert__icon .n-icon')).toBeVisible()
    await page.locator('#close-dialog').click()
    await page.locator('#auth-error').click()
    const warning = toast.locator('.n-alert.api-error-notice--warning')
    await expect(warning).toBeVisible()
    await expect(warning).toHaveCSS('background-color', theme === 'light' ? 'rgb(255, 240, 208)' : 'rgb(74, 56, 32)')
    await expect(warning).toHaveCSS('border-left-width', '4px')
    await expect(warning.locator('.n-alert__icon .n-icon')).toBeVisible()
    const warningSize = await warning.boundingBox()
    expect(warningSize).not.toBeNull()
    expect(warningSize!.width).toBeCloseTo(size!.width, 0)
    await expect(toast).toHaveCSS('z-index', '999')
  })
}
