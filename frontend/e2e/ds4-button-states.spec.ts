// Standalone visual design-system contract: no login/API fixtures required.
// Run with installed Playwright Chromium on the actual development machine.
import { test, expect } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const tokenCSS = readFileSync(resolve(process.cwd(), 'src/styles/tokens.css'), 'utf8')
const buttonCSS = readFileSync(resolve(process.cwd(), 'src/styles/buttons.css'), 'utf8')

test.describe('DS4 buttons in both palettes', () => {
  for (const theme of ['light', 'dark']) {
    test(`${theme}: icon, focus, disabled, danger-outline and hover use shared tokens`, async ({ page }) => {
      await page.setContent(`<!doctype html><html><body>
        <button id="create" class="primary-button" data-ds-icon="add">Create</button>
        <button id="cancel" class="secondary-button" data-ds-icon="cancel">Cancel</button>
        <button id="danger" class="ds-button ds-button--danger-outline" data-ds-icon="delete">Remove</button>
        <button id="next" class="ds-icon-control" data-ds-icon="next" aria-label="Next page"></button>
        <button id="disabled" class="primary-button" data-ds-icon="save" disabled>Save</button>
      </body></html>`)
      await page.addStyleTag({ content: tokenCSS })
      await page.addStyleTag({ content: buttonCSS })
      await page.evaluate((mode) => document.documentElement.setAttribute('data-ds-theme', mode), theme)

      const palette = await page.evaluate(() => {
        const root = getComputedStyle(document.documentElement)
        return {
          primary: root.getPropertyValue('--ds-primary').trim(),
          danger: root.getPropertyValue('--ds-danger').trim(),
          surface: root.getPropertyValue('--ds-bg-surface').trim(),
          focus: root.getPropertyValue('--ds-focus-ring').trim(),
        }
      })
      const create = page.locator('#create')
      await expect(create).toHaveCSS('min-height', '36px')
      await expect(create).toHaveCSS('border-radius', '8px')
      const colorFromCSS = await page.evaluate((color) => {
        const probe = document.createElement('div')
        probe.style.backgroundColor = color
        document.body.appendChild(probe)
        const computed = getComputedStyle(probe).backgroundColor
        probe.remove()
        return computed
      }, palette.surface)
      await expect(page.locator('#danger')).toHaveCSS('background-color', colorFromCSS)
      for (const id of ['create', 'cancel', 'danger', 'next']) {
        const style = await page.locator(`#${id}`).evaluate((el) => {
          const before = getComputedStyle(el, '::before')
          return { content: before.content, mask: before.maskImage || before.webkitMaskImage, width: before.width }
        })
        expect(style.content).not.toBe('none')
        expect(style.mask).toContain('data:image/svg+xml')
        expect(style.width).toBe('18px')
      }
      await expect(page.locator('#disabled')).toBeDisabled()
      const baseBackground = await create.evaluate((el) => getComputedStyle(el).backgroundColor)
      await create.hover()
      await expect.poll(() => create.evaluate((el) => getComputedStyle(el).backgroundColor)).not.toBe(baseBackground)
      await page.keyboard.press('Tab')
      await expect(create).toBeFocused()
      await expect(create).toHaveCSS('outline-style', 'solid')
      await expect(create).toHaveCSS('outline-width', '2px')
      await expect(page.locator('#next')).toHaveAttribute('aria-label', 'Next page')
    })
  }
})

// DS4 HF1: UOM's close control uses the same 36px, accessible shared mask
// as other ERP dialogs, while Cancel uses the semantic undo icon (not X).
const modalCSS = readFileSync(resolve(process.cwd(), 'src/styles/modal.css'), 'utf8')

test.describe('DS4 HF1 UOM dialog chrome', () => {
  for (const theme of ['light', 'dark']) {
    test(`${theme}: UOM Cancel and header Close share the standard modal button presentation`, async ({ page }) => {
      await page.setContent(`<!doctype html><html><body>
        <div class="ds-dialog-host ds-dialog-host--sm">
          <section class="ds-dialog-surface" role="dialog" aria-label="Biểu mẫu quy đổi UOM">
            <header class="modal-card__header">
              <h3>Biểu mẫu quy đổi UOM</h3>
              <button id="uom-close" type="button" class="modal-card-button-close" data-ds-icon="close" aria-label="Đóng"></button>
            </header>
            <div class="ds-dialog-stack-body">UOM</div>
            <footer class="modal-card__footer">
              <button id="uom-cancel" type="button" class="secondary-button" data-ds-icon="cancel">Hủy</button>
              <button type="button" class="primary-button" data-ds-icon="save">Lưu</button>
            </footer>
          </section>
        </div>
      </body></html>`)
      for (const css of [tokenCSS, buttonCSS, modalCSS]) await page.addStyleTag({ content: css })
      await page.evaluate((mode) => document.documentElement.setAttribute('data-ds-theme', mode), theme)

      const close = page.locator('#uom-close')
      const cancel = page.locator('#uom-cancel')
      await expect(close).toHaveAttribute('aria-label', 'Đóng')
      await expect(close).toHaveCSS('width', '36px')
      await expect(close).toHaveCSS('height', '36px')
      await expect(cancel).toHaveCSS('min-height', '36px')
      const icons = await page.evaluate(() => {
        const closeButton = document.querySelector('#uom-close')!
        const cancelButton = document.querySelector('#uom-cancel')!
        const closeIcon = getComputedStyle(closeButton, '::before')
        const cancelIcon = getComputedStyle(cancelButton, '::before')
        return {
          closeMask: closeIcon.maskImage || closeIcon.webkitMaskImage,
          closeWidth: closeIcon.width,
          cancelMask: cancelIcon.maskImage || cancelIcon.webkitMaskImage,
          cancelWidth: cancelIcon.width,
        }
      })
      expect(icons.closeMask).toContain('data:image/svg+xml')
      expect(icons.closeWidth).toBe('15px')
      expect(icons.cancelMask).toContain('data:image/svg+xml')
      expect(icons.cancelWidth).toBe('18px')
      expect(icons.cancelMask).not.toBe(icons.closeMask)

      const background = await close.evaluate((element) => getComputedStyle(element).backgroundColor)
      await close.hover()
      await expect.poll(() => close.evaluate((element) => getComputedStyle(element).backgroundColor))
        .not.toBe(background)
    })
  }
})
