/**
 * Browser-level CSS regression for the three DinkERP modal variants.
 * Deterministic isolated DOM + the production CSS files; no API/login fixtures.
 * This is NOT authenticated workflow acceptance or a Naive UI portal E2E test.
 *
 * Run: pnpm exec playwright test e2e/modal-form-visual-qa.spec.ts --project=chromium --reporter=line
 * Screenshot attachments are produced by each passing test for human review.
 */
import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const css = [
  'tokens.css', 'base.css', 'buttons.css', 'forms.css', 'tables.css',
  'detail-workspace.css', 'modal.css', 'utilities.css', 'page-layout.css',
].map((file) => readFileSync(resolve(process.cwd(), 'src', 'styles', file), 'utf8'))

const fields = `<div class="form-grid">
  <label class="ds-field">Tên đơn vị<input class="native-test" type="text" value="Công ty Dinktech" /></label>
  <label class="ds-field">Mã định danh<input type="text" value="CUS-001" /></label>
  <label class="ds-field">Loại<select><option>Khách hàng</option></select></label>
  <label class="ds-field">Ngày tạo<input type="date" value="2026-11-04" /></label>
  <label class="ds-field">Ghi chú<textarea rows="4">Nội dung kiểm thử nhiều dòng</textarea></label>
  <label class="ds-field">Trạng thái<input type="text" disabled value="Không được phép thay đổi" /></label>
  <label class="checkbox-field"><input type="checkbox" checked />Kích hoạt</label>
  <label class="ds-field">Naive fixture<div class="n-input"><input class="naive-inner" value="Internal input" /></div></label>
  <div class="ds-field-error" role="alert">Thông báo trường không hợp lệ</div>
</div>`

const detailRows = Array.from({ length: 64 }, (_, index) =>
  `<p class="detail-line">Dòng nghiệp vụ ${index + 1} — dữ liệu chi tiết</p>`).join('')

function fixture(variant: 'standard' | 'detail' | 'utility'): string {
  const title = variant === 'standard' ? 'Chỉnh sửa khách hàng'
    : variant === 'detail' ? 'Chi tiết khách hàng / nhà cung cấp' : 'Xác nhận thay đổi'
  const body = variant === 'standard'
    ? `<form class="modal-card__body ds-dialog-form-fields" onsubmit="return false">${fields}</form>`
    : variant === 'detail'
      ? `<div class="ds-detail-tabs" role="tablist"><button type="button" aria-selected="true">Tổng quan</button><button type="button">Liên hệ</button><button type="button">Lịch sử</button></div><div class="ds-detail-body modal-card__body">${fields}${detailRows}</div>`
      : `<div class="ds-dialog-stack-body"><p class="ds-confirm-dialog__message">Bạn có chắc muốn lưu các thay đổi chưa đồng bộ này?</p></div>`
  const size = variant === 'standard' ? 'md' : variant === 'detail' ? 'xl' : 'sm'
  const workspace = variant === 'detail' ? ' ds-detail-workspace ds-detail-workspace--fixed' : ''
  return `<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
  <div class="ds-dialog-host ds-dialog-host--${size} ds-dialog-host--${variant}">
    <section class="ds-dialog-surface modal-card${workspace}" role="dialog" aria-modal="true" aria-label="QA Modal">
      <header class="modal-card__header"><h2 class="modal-card-title">${title}</h2>
        <button class="modal-card-button-close" type="button" data-ds-icon="close" aria-label="Đóng"></button></header>
      ${body}
      <footer class="modal-card__footer"><button type="button" class="secondary-button" data-ds-icon="cancel">Hủy</button>
        <button type="button" class="primary-button" data-ds-icon="save">Lưu</button></footer>
    </section>
  </div></body></html>`
}

// 600px is intentionally still two columns (the host is 576px wide).
// 580px is the important edge: the host is 556px wide and must collapse.
const sizes = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'tablet', width: 768, height: 750 },
  { name: 'edge-wide', width: 600, height: 720 },
  { name: 'edge-narrow', width: 580, height: 720 },
  { name: 'mobile', width: 375, height: 667 },
  { name: 'min-width', width: 320, height: 568 },
]

for (const theme of ['light', 'dark'] as const) {
  for (const size of sizes) {
    for (const variant of ['standard', 'detail', 'utility'] as const) {
      test(`${theme} ${size.name} ${variant}: modal geometry, scroll and form contract`, async ({ page }) => {
        await page.setViewportSize({ width: size.width, height: size.height })
        await page.setContent(fixture(variant))
        await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
        for (const style of css) await page.addStyleTag({ content: style })

        const host = page.locator('.ds-dialog-host')
        const surface = page.locator('.ds-dialog-surface')
        const header = surface.locator(':scope > header')
        const footer = surface.locator(':scope > footer')
        const body = variant === 'utility' ? surface.locator(':scope > .ds-dialog-stack-body')
          : variant === 'detail' ? surface.locator(':scope > .ds-detail-body')
            : surface.locator(':scope > .modal-card__body')

        const measurements = await page.evaluate(() => {
          const rect = (selector: string) => {
            const element = document.querySelector(selector)!
            const box = element.getBoundingClientRect()
            return { x: box.x, right: box.right, top: box.top, bottom: box.bottom,
              width: box.width, height: box.height }
          }
          return { host: rect('.ds-dialog-host'), surface: rect('.ds-dialog-surface'),
            docWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth,
            innerHeight: window.innerHeight }
        })
        expect(measurements.host.x).toBeGreaterThanOrEqual(-1)
        expect(measurements.host.right).toBeLessThanOrEqual(size.width + 1)
        expect(measurements.surface.bottom).toBeLessThanOrEqual(size.height + 1)
        expect(measurements.docWidth).toBeLessThanOrEqual(size.width + 1)
        await expect(header).toBeVisible()
        await expect(footer).toBeVisible()
        await expect(body).toHaveCSS('overflow-y', 'auto')
        await expect(body).toHaveCSS('padding-top', variant === 'utility' || size.width <= 640 ? '12px' : '16px')
        await expect(surface).toHaveCSS('border-top-width', '1px')
        await expect(header).toHaveCSS('border-bottom-width', '1px')
        await expect(footer).toHaveCSS('border-top-width', '1px')

        if (variant !== 'utility') {
          await expect(page.locator('.native-test')).toHaveCSS('height', '36px')
          await expect(page.locator('.native-test')).toHaveCSS('font-size', '14px')
          await expect(page.locator('.ds-field').first()).toHaveCSS('font-size', '13px')
          await expect(page.locator('.ds-field-error')).toHaveCSS('font-size', '12px')
          const errorColor = await page.locator('.ds-field-error').evaluate((element) =>
            getComputedStyle(element).color)
          const dangerToken = await page.evaluate(() => {
            const probe = document.createElement('span')
            probe.style.color = 'var(--ds-danger)'
            document.body.append(probe)
            const color = getComputedStyle(probe).color
            probe.remove()
            return color
          })
          expect(errorColor).toBe(dangerToken)
          const textareaHeight = await page.locator('textarea').evaluate((el) => el.getBoundingClientRect().height)
          expect(textareaHeight).toBeGreaterThan(36)
          const checkboxWidth = await page.locator('input[type="checkbox"]').evaluate((el) => el.getBoundingClientRect().width)
          expect(checkboxWidth).toBeLessThan(24)
          // Naive controls render their own input internals. This unstyled fixture
          // has Chromium's default 2px border, not the modal native rule's 1px.
          await expect(page.locator('.naive-inner')).not.toHaveCSS('border-top-width', '1px')
          const columns = await page.locator('.form-grid').evaluate((element) =>
            getComputedStyle(element).gridTemplateColumns.trim().split(/\s+/).length)
          expect(columns).toBe(measurements.host.width <= 560 ? 1 : 2)
        }
        if (variant === 'standard') {
          const input = page.locator('.native-test')
          const before = await input.boundingBox()
          await input.focus()
          await expect(input).toHaveCSS('outline-style', 'solid')
          const after = await input.boundingBox()
          expect(after!.height).toBeCloseTo(before!.height, 1)
          await expect(page.locator('input:disabled')).toBeDisabled()
        }
        if (variant === 'detail') {
          const tabs = page.locator('.ds-detail-tabs')
          const yBefore = (await tabs.boundingBox())!.y
          const footerYBefore = (await footer.boundingBox())!.y
          await page.locator('.ds-detail-body').evaluate((element) => { element.scrollTop = element.scrollHeight })
          const scrolled = await page.locator('.ds-detail-body').evaluate((element) => element.scrollTop)
          expect(scrolled).toBeGreaterThan(0)
          expect((await tabs.boundingBox())!.y).toBeCloseTo(yBefore, 1)
          expect((await footer.boundingBox())!.y).toBeCloseTo(footerYBefore, 1)
          await expect(tabs).toBeVisible()
        }

        const screenshot = await page.screenshot({ animations: 'disabled' })
        await test.info().attach(`${theme}-${size.name}-${variant}.png`,
          { body: screenshot, contentType: 'image/png' })
        await expect(host).toBeVisible()
      })
    }
  }
}
