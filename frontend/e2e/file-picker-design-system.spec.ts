/** FC03 — visible native file chooser + Product image button use one Design System.
 * Tests isolated production styles; file contents never leave the browser.
 */
import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const cssFiles = [
  'tokens.css', 'base.css', 'buttons.css', 'forms.css', 'tables.css',
  'detail-workspace.css', 'modal.css', 'utilities.css', 'page-layout.css',
].map((file) => readFileSync(resolve(process.cwd(), 'src', 'styles', file), 'utf8'))

function fixture(kind: 'page' | 'modal'): string {
  const fields = `<div class="ds-form-grid">
    <label class="ds-field">Name<input id="name" type="text" value="Example"></label>
    <label class="avatar-manager__file">Avatar image<input id="avatar" type="file" accept="image/png,image/jpeg,image/webp"></label>
    <label class="file-field">Import CSV/XLSX<input id="import" type="file" accept=".csv,.xlsx"><small>Source file</small></label>
    <div class="ds-field"><label for="user-avatar">Create user avatar</label><input id="user-avatar" type="file" accept="image/png"></div>
    <label class="ds-field">Disabled file<input id="disabled-file" type="file" disabled></label>
    <label class="ds-field">Invalid file<input id="invalid-file" type="file" aria-invalid="true"></label>
    <div class="ds-field"><span>Product image</span>
      <label class="ds-file-trigger" id="product-trigger">Choose image
        <input id="product-file" type="file" accept="image/png,image/jpeg,image/webp" aria-label="Choose image"></label>
    </div>
  </div>`
  const content = kind === 'page'
    ? `<section class="ds-page-panel">${fields}</section>`
    : `<div class="ds-dialog-host ds-dialog-host--standard ds-dialog-host--md">
        <section class="ds-dialog-surface modal-card" role="dialog" aria-label="File picker">
          <header class="modal-card__header">File picker</header>
          <div class="modal-card__body ds-dialog-form-fields">${fields}</div>
          <footer class="modal-card__footer">Done</footer>
        </section></div>`
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>${content}</body></html>`
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [320, 375, 768, 1440]) {
    for (const kind of ['page', 'modal'] as const) {
      test(`FC03 ${theme} ${width}px ${kind}: file picker, overlay and states`, async ({ page }) => {
        await page.setViewportSize({ width, height: 850 })
        await page.setContent(fixture(kind))
        await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
        for (const css of cssFiles) await page.addStyleTag({ content: css })
        // Eliminate initial border-color transitions so we assert stable token values.
        await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' })

        for (const id of ['avatar', 'import', 'user-avatar', 'disabled-file', 'invalid-file']) {
          await expect(page.locator(`#${id}`)).toHaveCSS('height', '36px')
          await expect(page.locator(`#${id}`)).toHaveCSS('border-top-width', '1px')
          await expect(page.locator(`#${id}`)).toHaveCSS('border-top-left-radius', '6px')
          await expect(page.locator(`#${id}`)).toHaveCSS('font-size', '14px')
        }
        await expect(page.locator('#product-trigger')).toHaveCSS('min-height', '36px')
        await expect(page.locator('#product-trigger')).toHaveCSS('border-top-width', '1px')
        await expect(page.locator('#product-file')).toHaveCSS('opacity', '0')
        await expect(page.locator('.avatar-manager__file')).toHaveCSS('gap', '6px')
        await expect(page.locator('.file-field')).toHaveCSS('gap', '6px')
        const visual = await page.evaluate(() => {
          const style = (id: string) => getComputedStyle(document.getElementById(id)!)
          return {
            textBorder: style('name').borderTopColor,
            fileBorder: style('avatar').borderTopColor,
            invalidBorder: style('invalid-file').borderTopColor,
            disabledBg: style('disabled-file').backgroundColor,
            normalBg: style('avatar').backgroundColor,
            pageWidth: document.documentElement.scrollWidth,
          }
        })
        expect(visual.fileBorder).toBe(visual.textBorder)
        expect(visual.invalidBorder).not.toBe(visual.fileBorder)
        expect(visual.disabledBg).not.toBe(visual.normalBg)
        expect(visual.pageWidth).toBeLessThanOrEqual(width)
        await page.locator('#avatar').setInputFiles({ name: 'avatar.png', mimeType: 'image/png', buffer: Buffer.from('test') })
        expect(await page.locator('#avatar').evaluate((el) => (el as HTMLInputElement).files?.[0]?.name)).toBe('avatar.png')
        await page.locator('#product-file').setInputFiles({ name: 'product.png', mimeType: 'image/png', buffer: Buffer.from('test') })
        expect(await page.locator('#product-file').evaluate((el) => (el as HTMLInputElement).files?.[0]?.name)).toBe('product.png')
        await page.locator('#avatar').focus()
        await expect(page.locator('#avatar')).not.toHaveCSS('box-shadow', 'none')
        await page.locator('#product-file').focus()
        await expect(page.locator('#product-trigger')).not.toHaveCSS('box-shadow', 'none')
        await test.info().attach(`fc03-${theme}-${width}-${kind}.png`,
          { body: await page.screenshot({ animations: 'disabled' }), contentType: 'image/png' })
      })
    }
  }
}
