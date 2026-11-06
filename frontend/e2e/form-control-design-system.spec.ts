/**
 * FC01 — browser computed-style regression for DinkERP v2.6.0 Form Controls.
 * Uses real production CSS in an isolated DOM. It verifies geometry, states,
 * responsive layout and Naive internal isolation; does NOT authenticate or
 * exercise business APIs. Runtime NSelect/NInput themes are also gated by
 * scripts/design-system/form-control-contract.test.cjs.
 *
 * pnpm exec playwright test e2e/form-control-design-system.spec.ts --project=chromium
 */
import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const cssFiles = [
  'tokens.css', 'base.css', 'buttons.css', 'forms.css', 'tables.css',
  'detail-workspace.css', 'modal.css', 'utilities.css', 'page-layout.css',
].map((file) => readFileSync(resolve(process.cwd(), 'src', 'styles', file), 'utf8'))

const fields = `<div class="ds-form-grid">
  <label class="ds-field">Text<input id="text" class="field-input" placeholder="Text placeholder"></label>
  <label class="ds-field">Number<input id="number" type="number" value="20"></label>
  <label class="ds-field">Search<input id="search" type="search" placeholder="Find"></label>
  <label class="ds-field">Email<input id="email" type="email" placeholder="name@example.com"></label>
  <label class="ds-field">Date<input id="date" type="date" value="2026-11-05"></label>
  <label class="ds-field">Select<select id="select"><option>Active</option></select></label>
  <label class="ds-field">Textarea<textarea id="textarea" rows="3" placeholder="Enter notes">Three lines</textarea></label>
  <label class="ds-field">Invalid textarea<textarea id="invalid-textarea" rows="2" aria-invalid="true">Error</textarea></label>
  <label class="ds-field">Disabled<input id="disabled" disabled value="Fixed"></label>
  <label class="ds-field">Readonly<input id="readonly" readonly value="Read only"></label>
  <label class="ds-field">Invalid<input id="invalid" aria-invalid="true" value="Invalid"></label>
  <label class="checkbox-field"><input id="checkbox" type="checkbox" checked>Checkbox</label>
  <label class="checkbox-field"><input id="radio" type="radio" checked>Radio</label>
  <label class="checkbox-field"><input id="switch" type="checkbox" role="switch" checked>Switch</label>
  <label class="ds-field">Naive fixture<div class="n-input"><input id="naive-inner" value="Library interior"></div></label>
  <label class="ds-field">File upload<input type="file" id="file"></label>
</div>`

function fixture(kind: 'page' | 'modal'): string {
  const content = kind === 'page'
    ? `<section class="ds-page-panel">${fields}</section>`
    : `<div class="ds-dialog-host ds-dialog-host--standard ds-dialog-host--md">
        <section class="ds-dialog-surface modal-card" role="dialog" aria-label="FC01">
          <header class="modal-card__header">FC01</header>
          <div class="modal-card__body ds-dialog-form-fields">${fields}</div>
          <footer class="modal-card__footer">Done</footer>
        </section>
      </div>`
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>
    <body>${content}</body></html>`
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [320, 768, 1440]) {
    for (const kind of ['page', 'modal'] as const) {
      test(`${theme} ${width}px ${kind}: form-control tokens, state and responsive`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 })
        await page.setContent(fixture(kind))
        await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
        for (const css of cssFiles) await page.addStyleTag({ content: css })
        // Style-tag insertion triggers the controls' 200ms border transitions.
        // Measure settled tokens, not the first animation frame's UA border.
        await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' })
        for (const selector of ['#text', '#number', '#date', '#select', '#search', '#email']) {
          await expect(page.locator(selector)).toHaveCSS('height', '36px')
          await expect(page.locator(selector)).toHaveCSS('font-size', '14px')
          await expect(page.locator(selector)).toHaveCSS('padding-left', '12px')
          await expect(page.locator(selector)).toHaveCSS('border-top-width', '1px')
          await expect(page.locator(selector)).toHaveCSS('border-top-left-radius', '6px')
        }
        await expect(page.locator('.ds-field').first()).toHaveCSS('gap', '6px')
        await expect(page.locator('.checkbox-field').first()).toHaveCSS('gap', '6px')
        await expect(page.locator('#checkbox')).toHaveCSS('width', '18px')
        await expect(page.locator('#radio')).toHaveCSS('height', '18px')
        await expect(page.locator('#switch')).toHaveCSS('width', '36px')
        await expect(page.locator('#switch')).toHaveCSS('height', '20px')
        const textareaHeight = await page.locator('#textarea').evaluate((el) => el.getBoundingClientRect().height)
        expect(textareaHeight).toBeGreaterThan(36)
        // This is a mocked Naive wrapper. Its inner <input> MUST NOT have the
        // native 1px control border or a second native focus outline.
        await expect(page.locator('#naive-inner')).not.toHaveCSS('border-top-width', '1px')
        const styles = await page.evaluate(() => {
          const prop = (selector: string, key: keyof CSSStyleDeclaration) =>
            getComputedStyle(document.querySelector(selector) as Element)[key]
          return {
            normalBg: prop('#text', 'backgroundColor'),
            disabledBg: prop('#disabled', 'backgroundColor'),
            readonlyBg: prop('#readonly', 'backgroundColor'),
            normalBorder: prop('#text', 'borderTopColor'),
            invalidBorder: prop('#invalid', 'borderTopColor'),
            pageWidth: document.documentElement.scrollWidth,
          }
        })
        expect(styles.disabledBg).not.toBe(styles.normalBg)
        expect(styles.readonlyBg).not.toBe(styles.normalBg)
        expect(styles.invalidBorder).not.toBe(styles.normalBorder)
        expect(styles.pageWidth).toBeLessThanOrEqual(width)
        const input = page.locator('#text')
        const initialHeight = (await input.boundingBox())!.height
        await input.hover()
        const hover = await input.evaluate((el) => getComputedStyle(el).borderTopColor)
        expect(hover).not.toBe(styles.normalBorder)
        await input.focus()
        await expect(input).toHaveCSS('outline-style', 'none')
        await expect(input).not.toHaveCSS('box-shadow', 'none')
        const textarea = page.locator('#textarea')
        await textarea.focus()
        await expect(textarea).not.toHaveCSS('box-shadow', 'none')
        await expect(page.locator('#invalid-textarea')).toHaveCSS('border-top-color', styles.invalidBorder)
        expect((await input.boundingBox())!.height).toBe(initialHeight)
        const screenshot = await page.screenshot({ animations: 'disabled' })
        await test.info().attach(`fc01-${theme}-${width}-${kind}.png`,
          { body: screenshot, contentType: 'image/png' })
      })
    }
  }
}
