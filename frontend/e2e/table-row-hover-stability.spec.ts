/** CSS-A02-HF02: row hover must never move contact/security tables.
 * Uses production tokens, tables and buttons with realistic semantic tables.
 * No API/PBAC mocks: preserves the existing feature interaction contracts. */
import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const styles = ['tokens.css', 'base.css', 'buttons.css', 'tables.css']
  .map((file) => readFileSync(resolve(process.cwd(), 'src/styles', file), 'utf8'))

const fixture = `<!doctype html><html lang="vi"><head>
<meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body><main>
<section><h2>Liên hệ tổ chức</h2><div class="ds-table-scroll" data-testid="contacts-wrap">
<table class="data-table ds-table ds-table--contacts" data-testid="contacts">
<thead><tr><th>Tên liên hệ</th><th>Email</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
<tbody>
<tr data-testid="contacts-1" tabindex="0"><td>P09</td><td>quyet@example.test</td><td>Đang hoạt động</td>
<td><span class="table-row-actions"><button class="table-row-action ds-icon-control" aria-label="Xem liên hệ">X</button>
<button class="table-row-action ds-icon-control" aria-label="Sửa liên hệ">S</button></span></td></tr>
<tr data-testid="contacts-2" tabindex="0"><td>P10</td><td>other@example.test</td><td>Đang hoạt động</td>
<td><span class="table-row-actions"><button class="table-row-action ds-icon-control" aria-label="Xem liên hệ 2">X</button>
<button class="table-row-action ds-icon-control" aria-label="Sửa liên hệ 2">S</button></span></td></tr>
</tbody></table></div></section>
<section><h2>Policy PBAC (sticky actions)</h2>
<div class="list-table-wrap list-table-wrap--sticky-actions" data-testid="policy-wrap">
<table class="list-table ds-table" data-testid="policies">
<thead><tr><th>Organization scope</th><th>Resource</th><th>Participant</th><th>Actions / Effects</th><th>Thao tác</th></tr></thead>
<tbody>
<tr data-testid="policy-1" tabindex="0"><td>Dinktech Group</td><td>province</td><td>USER</td><td>ABSOLUTE_DENY:edit</td>
<td><span class="table-row-actions"><button class="table-row-action ds-icon-control" aria-label="Xem policy">X</button>
<button class="table-row-action ds-icon-control" aria-label="Sửa policy">S</button></span></td></tr>
<tr data-testid="policy-2" tabindex="0"><td>Dinktech Group</td><td>product</td><td>USER</td><td>ABSOLUTE_DENY:setstate</td>
<td><span class="table-row-actions"><button class="table-row-action ds-icon-control" aria-label="Xem policy 2">X</button>
<button class="table-row-action ds-icon-control" aria-label="Sửa policy 2">S</button></span></td></tr>
</tbody></table></div></section>
</main></body></html>`

const fixtureCSS = `body { margin:0; padding:18px; background:var(--ds-bg-canvas);
color:var(--ds-text-primary); } main { display:grid; gap:24px; min-width:0; max-width:1280px;
margin:auto } section { padding:14px; min-width:0; background:var(--ds-bg-surface);
border-radius:8px; } h2 { margin:0 0 12px; font-size:16px; }`

type Rectangle = { x: number; y: number; width: number; height: number }
async function geometry(page: Page, tableId: string): Promise<{
  rectangles: Rectangle[]; scrollWidth: number; wrapperWidth: number; documentWidth: number
}> {
  return page.evaluate((name) => {
    const table = document.querySelector<HTMLElement>(`[data-testid="${name}"]`)!
    const wrapper = table.parentElement!
    const row = table.querySelector('tbody tr')!
    const cells = [...row.querySelectorAll('td')]
    const action = row.querySelector('button')!
    const rect = (el: Element) => {
      const { x, y, width, height } = el.getBoundingClientRect()
      return { x, y, width, height }
    }
    return {
      rectangles: [rect(wrapper), rect(table), rect(row), ...cells.map(rect), rect(action)],
      scrollWidth: wrapper.scrollWidth,
      wrapperWidth: wrapper.getBoundingClientRect().width,
      documentWidth: document.documentElement.scrollWidth,
    }
  }, tableId)
}

function expectStable(before: Awaited<ReturnType<typeof geometry>>, after: Awaited<ReturnType<typeof geometry>>) {
  expect(after.scrollWidth).toBe(before.scrollWidth)
  expect(after.wrapperWidth).toBeCloseTo(before.wrapperWidth, 2)
  expect(after.documentWidth).toBe(before.documentWidth)
  for (let i = 0; i < before.rectangles.length; i++) {
    for (const property of ['x', 'y', 'width', 'height'] as const) {
      expect(after.rectangles[i]![property], `rectangle #${i} changed ${property}`)
        .toBeCloseTo(before.rectangles[i]![property], 1)
    }
  }
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [320, 375, 560, 768, 1440]) {
    test(`${theme} ${width}px: contact and policy rows retain geometry across hover and focus`, async ({ page }) => {
      await page.setViewportSize({ width, height: 850 })
      await page.setContent(fixture)
      await page.evaluate((mode) => { document.documentElement.dataset.dsTheme = mode }, theme)
      for (const css of styles) await page.addStyleTag({ content: css })
      await page.addStyleTag({ content: fixtureCSS })
      const expectedHover = await page.evaluate(() => {
        const el = document.createElement('div')
        el.style.backgroundColor = 'var(--ds-table-row-hover)'
        document.body.append(el)
        const color = getComputedStyle(el).backgroundColor
        el.remove()
        return color
      })

      for (const [table, row1, row2] of [
        ['contacts', 'contacts-1', 'contacts-2'],
        ['policies', 'policy-1', 'policy-2'],
      ]) {
        // At 320px the sticky Actions cell can cover the first policy cell.
        // Hover the visible sticky cell (the actual mouse target), then assert
        // that the ENTIRE row gets the shared hover color without moving.
        const hoverCell = (rowId: string) => {
          const cells = page.getByTestId(rowId).locator('td')
          return table === 'policies' ? cells.last() : cells.first()
        }
        await page.mouse.move(1, 1)
        const before = await geometry(page, table!)
        await hoverCell(row1!).hover({ position: { x: 8, y: 8 } })
        const firstCell = page.getByTestId(row1!).locator('td').first()
        await expect(firstCell).toHaveCSS('background-color', expectedHover)
        await expect(page.getByTestId(row1!).locator('td').last()).toHaveCSS('background-color', expectedHover)
        expectStable(before, await geometry(page, table!))

        await hoverCell(row2!).hover({ position: { x: 8, y: 8 } })
        await expect(page.getByTestId(row2!).locator('td').first()).toHaveCSS('background-color', expectedHover)
        await expect(page.getByTestId(row2!).locator('td').last()).toHaveCSS('background-color', expectedHover)
        expectStable(before, await geometry(page, table!))

        await page.mouse.move(1, 1)
        await expect(firstCell).not.toHaveCSS('background-color', expectedHover)
        expectStable(before, await geometry(page, table!))

        // Keyboard modality: the focusable <tr tabindex=0> itself gets
        // :focus-visible, not :has(:focus-visible). Both must be supported.
        await page.keyboard.press('Tab')
        await page.getByTestId(row1!).focus()
        await expect(page.getByTestId(row1!)).toBeFocused()
        await expect(page.getByTestId(row1!)).toHaveCSS('display', 'table-row')
        await expect(firstCell).toHaveCSS('background-color', expectedHover)
        await expect(page.getByTestId(row1!).locator('td').last()).toHaveCSS('background-color', expectedHover)
        expectStable(before, await geometry(page, table!))
      }
      expect((await geometry(page, 'contacts')).documentWidth).toBeLessThanOrEqual(width + 1)
      if (width === 375 || width === 1440) {
        await page.evaluate(() => { if (document.activeElement instanceof HTMLElement) document.activeElement.blur() })
        await page.getByTestId('policy-1').locator('td').first().hover({ position: { x: 8, y: 8 } })
        await expect(page.getByTestId('policy-1').locator('td').first()).toHaveCSS('background-color', expectedHover)
        await test.info().attach(`table-hover-${theme}-${width}.png`, {
          body: await page.screenshot({ animations: 'disabled', fullPage: true }),
          contentType: 'image/png',
        })
      }
    })
  }
}
