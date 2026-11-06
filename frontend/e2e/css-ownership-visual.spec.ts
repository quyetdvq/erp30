/** CSS-A01 remediation: real DinkERP stylesheets, synthetic DOM only.
 * Covers the formerly broken 540-600px modal cascade, shared button/table/tab,
 * page scroll ownership, and light/dark. Does not exercise logged-in APIs.
 * pnpm exec playwright test e2e/css-ownership-visual.spec.ts --project=chromium
 */
import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const sharedCSS = [
  'tokens.css', 'base.css', 'buttons.css', 'forms.css', 'tables.css',
  'detail-workspace.css', 'modal.css', 'utilities.css', 'page-layout.css',
].map((file) => readFileSync(resolve(process.cwd(), 'src', 'styles', file), 'utf8'))
const moduleCSS = readFileSync(resolve(process.cwd(), 'src/features/notifications/styles/notificationSettings.css'), 'utf8')

const fixture = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:8px;display:grid;gap:12px;">
  <div class="ds-dialog-host" id="conversion-host">
    <section class="ds-dialog-surface modal-card" role="dialog" aria-label="UOM conversion">
      <header class="modal-card__header"><h2>Conversion editor</h2></header>
      <div class="form-grid" id="conversion-grid">
        <label class="ds-field">Unit<input id="unit" value="PCS"></label>
        <label class="ds-field">Factor<input value="12"></label>
        <label class="ds-field">Price<input value="100"></label>
        <label class="ds-field">Usage<select><option>Sales</option></select></label>
      </div>
    </section>
  </div>
  <section id="ordinary-panel" class="content-panel ds-page-panel">
    <div class="m11-panel m11-panel--policies">
      <div class="m11-row"><div>Notification policy</div><div>State</div><div>Impact</div>
        <button id="notification-button" class="ds-button ds-button--primary">Save</button></div>
    </div>
    <div id="table-wrap" class="ds-table-scroll"><table class="data-table" style="min-width:760px">
      <thead><tr><th>Code</th><th>Description</th></tr></thead>
      <tbody><tr><td id="table-cell">A-001</td><td>Example</td></tr></tbody></table></div>
    <nav id="detail-tabs" class="ds-detail-tabs ds-detail-tabs--flat" role="tablist">
      <button id="selected-tab" role="tab" aria-selected="true">Overview</button>
      <button id="other-tab" role="tab" aria-selected="false">History</button>
    </nav>
  </section>
</body></html>`

for (const theme of ['light', 'dark'] as const) {
  for (const width of [320, 375, 520, 540, 560, 580, 600, 620, 768, 1440]) {
    test(`CSS ownership ${theme} ${width}px: modal, buttons, tabs, table, overflow`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 })
      await page.setContent(fixture)
      await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
      for (const css of sharedCSS) await page.addStyleTag({ content: css })
      await page.addStyleTag({ content: moduleCSS })
      await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' })
      const hostWidth = await page.locator('#conversion-host').evaluate((el) => el.getBoundingClientRect().width)
      const tracks = await page.locator('#conversion-grid').evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length)
      expect(tracks).toBe(hostWidth <= 560.5 ? 1 : 2)
      await expect(page.locator('#unit')).toHaveCSS('height', '36px')
      await expect(page.locator('#notification-button')).toHaveCSS('height', '36px')
      // CSS-A02 single-owner table cells use padding: 10px 12px (vertical, horizontal).
      await expect(page.locator('#table-cell')).toHaveCSS('padding-left', '12px')
      await expect(page.locator('#ordinary-panel')).toHaveCSS('overflow-x', 'visible')
      await expect(page.locator('#table-wrap')).toHaveCSS('overflow-x', 'auto')
      const [selected, primary] = await page.evaluate(() => {
        const selected = getComputedStyle(document.getElementById('selected-tab')!).borderBottomColor
        const primary = getComputedStyle(document.documentElement).getPropertyValue('--ds-primary').trim()
        const dummy = document.createElement('span');dummy.style.color = primary;document.body.appendChild(dummy)
        const result = getComputedStyle(dummy).color;dummy.remove()
        return [selected,result]
      })
      expect(selected).toBe(primary)
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 2)
    })
  }
}
