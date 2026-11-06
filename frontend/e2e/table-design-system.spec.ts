/**
 * CSS-A02: browser contract for unified table chrome. Uses production CSS
 * with deterministic semantic markup (no backend/login/PBAC fixture).
 * Actual product workflows are covered by the existing Product/PriceList E2E.
 */
import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const css = [
  'tokens.css', 'base.css', 'buttons.css', 'forms.css', 'tables.css',
  'detail-workspace.css', 'modal.css', 'utilities.css', 'page-layout.css',
].map((file) => readFileSync(resolve(process.cwd(), 'src', 'styles', file), 'utf8'))

const fixtureLayout = `
  main { width: 100%; min-width: 0; max-width: 1280px; margin: auto; display: grid; gap: 14px; padding: 16px; }
  .test-panel { min-width: 0; border: 1px solid var(--ds-border-subtle);
    background: var(--ds-bg-surface); padding: 12px; border-radius: 8px; }
  .test-panel h2 { margin: 0 0 8px; font-size: 15px; }
  .test-panel > div { max-width: 100%; }
`

const columns = ['Sản phẩm', 'Loại', 'Trạng thái', 'Thao tác']
const items = [
  ['Sản phẩm A', 'STANDARD', 'ACTIVE', 'Xem'],
  ['Sản phẩm B', 'COMBO', 'DRAFT', 'Sửa'],
]
function table(name: string, wrapper: string, classes: string): string {
  const header = columns.map((col) => `<th scope="col">${col}</th>`).join('')
  const rows = items.map((item) => `<tr>${item.map((cell, i) =>
    `<td data-label="${columns[i]}">${cell}</td>`).join('')}</tr>`).join('')
  return `<section class="test-panel" data-testid="${name}"><h2>${name}</h2>
    <div class="${wrapper}"><table class="${classes}"><thead><tr>${header}</tr></thead>
    <tbody>${rows}</tbody></table></div></section>`
}
function fixture(): string {
  return `<!doctype html><html lang="vi"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    </head><body><main>
      ${table('list', 'list-table-wrap', 'list-table ds-table')}
      ${table('pricing', 'list-table-wrap list-table-wrap--sticky-actions list-table-wrap--price', 'list-table ds-table')}
      ${table('hierarchy', 'ds-table-scroll', 'master-table ds-data-grid ds-table ds-table--master')}
      ${table('detail', 'ds-table-scroll', 'detail-table ds-table ds-table--detail')}
      ${table('relation', 'ds-table-scroll', 'relation-table ds-table ds-table--product-relations ds-table--sticky-header')}
      ${table('contacts', 'ds-table-scroll', 'data-table ds-table ds-table--contacts ds-table--valign-top')}
      ${table('product-editor', 'ds-table-scroll', 'data-table ds-table ds-table--relationship')}
      ${table('assignment', 'ds-table-scroll', 'data-table ds-table ds-table--assignment')}
      ${table('security', 'ds-table-scroll', 'detail-table ds-data-grid ds-table ds-table--authorization')}
      ${table('workflow', 'ds-table-scroll ds-table-scroll--workflow', 'my-work-table ds-table ds-table--responsive-cards')}
    </main></body></html>`
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [320, 375, 560, 580, 760, 1024, 1440]) {
    test(`${theme} ${width}px: table cells, scroll, sticky, card-mode parity`, async ({ page }) => {
      await page.setViewportSize({ width, height: 850 })
      await page.setContent(fixture())
      await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
      for (const style of css) await page.addStyleTag({ content: style })
      await page.addStyleTag({ content: fixtureLayout })

      for (const name of ['list', 'pricing', 'hierarchy', 'detail', 'relation',
        'contacts', 'product-editor', 'assignment', 'security', 'workflow']) {
        const panel = page.getByTestId(name)
        await expect(panel.locator('th').first()).toHaveCSS('padding', '10px 12px')
        await expect(panel.locator('th').first()).toHaveCSS('font-size', '12px')
        if (name !== 'workflow' || width > 760) {
          await expect(panel.locator('td').first()).toHaveCSS('padding', '10px 12px')
        }
        await expect(panel.locator('td').first()).toHaveCSS('font-size', '13px')
      }
      const price = page.getByTestId('pricing')
      await expect(price.locator('table')).toHaveCSS('min-width', '1080px')
      await expect(price.locator('th').last()).toHaveCSS('position', 'sticky')
      await expect(price.locator('td').last()).toHaveCSS('position', 'sticky')
      await expect(page.getByTestId('relation').locator('th').first()).toHaveCSS('position', 'sticky')
      await expect(page.getByTestId('hierarchy').locator('table')).toHaveCSS('min-width', '1100px')

      const workflow = page.getByTestId('workflow')
      await expect(workflow.locator('thead')).toHaveCSS('display', width <= 760 ? 'none' : 'table-header-group')
      if (width <= 760) {
        await expect(workflow.locator('td').first()).toHaveCSS('display', 'grid')
        await expect(workflow.locator('td').first()).toHaveCSS('padding', '0px')
      }
      const scrollExtent = await page.evaluate(() => ({
        document: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
      }))
      expect(scrollExtent.document).toBeLessThanOrEqual(scrollExtent.viewport + 1)
      const row = page.getByTestId('list').locator('tbody tr').first()
      const expectedHover = await page.evaluate(() => {
        const sample = document.createElement('span')
        sample.style.backgroundColor = 'var(--ds-table-row-hover)'
        document.body.append(sample)
        const color = getComputedStyle(sample).backgroundColor
        sample.remove()
        return color
      })
      // HF02 eases background-color over 140ms: hover() moves the pointer but
      // does not wait for paint to settle. Poll the computed cell color instead
      // of sampling a transparent/intermediate animation frame immediately.
      await row.hover()
      await expect(row.locator('td').first()).toHaveCSS('background-color', expectedHover)
      if (width === 375 || width === 1440) {
        await test.info().attach(`table-${theme}-${width}.png`, {
          body: await page.screenshot({ animations: 'disabled', fullPage: true }),
          contentType: 'image/png',
        })
      }
    })
  }
}
