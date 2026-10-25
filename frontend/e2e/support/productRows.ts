import type { Locator, Page } from '@playwright/test'

/** Product code is a span inside the multi-line metadata cell, not a whole cell. */
export function productRow(page: Page, code: string): Locator {
  const escaped = code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return page.locator('tr.product-data-row').filter({
    has: page.locator('.product-code-value').filter({ hasText: new RegExp(`^${escaped}$`) }),
  })
}
